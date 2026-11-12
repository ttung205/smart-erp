"""Tra cứu tài liệu nội bộ (RAG) trên `docs/knowledge/`.

Tài liệu Markdown được chia đoạn theo mục `##`, nhúng vector bằng model đa ngôn ngữ chạy cục bộ (fastembed, ONNX) và
lưu trong ChromaDB chạy trong tiến trình. Index nằm trong bộ nhớ và tự dựng lại khi tài liệu thay đổi (so dấu vân tay
đường dẫn + mtime + kích thước): kho tri thức nhỏ nên dựng lại chỉ mất vài giây, không cần volume lưu index.

Thư mục `_draft/` không bao giờ được index. Tài liệu chưa có `verified_by`/`verified_at` vẫn được index nhưng gắn cờ
`verified = False` để trợ lý nói rõ nội dung chưa được người có thẩm quyền xác nhận.
"""
from __future__ import annotations

import hashlib
import logging
import re
import threading
from dataclasses import dataclass
from pathlib import Path

from . import config

log = logging.getLogger("ai.knowledge")

GROUPS = ("business", "technical", "faq", "policy")
MAX_CHUNK_CHARS = 1500
COLLECTION = "knowledge"


class KnowledgeUnavailable(RuntimeError):
    """Không dựng được index (thiếu thư mục tài liệu, không nạp được model nhúng)."""


@dataclass
class Chunk:
    id: str
    text: str
    meta: dict


def _frontmatter(text: str) -> tuple[dict, str]:
    """Đọc khối `---` đầu file dạng `key: value` (đủ cho frontmatter của kho tri thức, không cần YAML đầy đủ)."""
    match = re.match(r"^---\s*\n(.*?)\n---\s*\n", text, re.S)
    if not match:
        return {}, text
    meta = {}
    for line in match.group(1).splitlines():
        if ":" in line:
            key, value = line.split(":", 1)
            value = value.strip()
            meta[key.strip()] = None if value in ("", "null", "~") else value
    return meta, text[match.end():]


def _split(body: str) -> list[tuple[str, str]]:
    """Chia thân tài liệu theo mục `##` thành (tên mục, nội dung). Phần trước mục đầu tiên là phần mở đầu."""
    sections, heading, lines = [], "Mở đầu", []
    for line in body.splitlines():
        if line.startswith("## "):
            sections.append((heading, "\n".join(lines).strip()))
            heading, lines = line[3:].strip(), []
        elif not line.startswith("# "):
            lines.append(line)
    sections.append((heading, "\n".join(lines).strip()))
    return [(h, c) for h, c in sections if c]


def _lines(block: str) -> list[str]:
    """Khối liền (bảng, danh sách dài) vượt giới hạn thì cắt theo dòng; bảng lặp lại dòng tiêu đề ở mỗi phần."""
    rows = block.splitlines()
    header = "\n".join(rows[:2]) if len(rows) > 2 and rows[0].startswith("|") and rows[1].startswith("|") else ""
    body = rows[2:] if header else rows
    parts, current = [], header
    for row in body:
        if current != header and len(current) + len(row) > MAX_CHUNK_CHARS:
            parts.append(current)
            current = header
        current = f"{current}\n{row}" if current else row
    return [*parts, current] if current else parts


def _pieces(content: str) -> list[str]:
    """Mục quá dài thì cắt theo đoạn văn (dòng trống) để mỗi phần không quá MAX_CHUNK_CHARS."""
    if len(content) <= MAX_CHUNK_CHARS:
        return [content]
    pieces, current = [], ""
    for para in re.split(r"\n\s*\n", content):
        for block in _lines(para) if len(para) > MAX_CHUNK_CHARS else [para]:
            if current and len(current) + len(block) > MAX_CHUNK_CHARS:
                pieces.append(current)
                current = ""
            current = f"{current}\n\n{block}" if current else block
    return [*pieces, current] if current else pieces


def _files() -> list[Path]:
    root = config.KNOWLEDGE_DIR
    if not root.is_dir():
        raise KnowledgeUnavailable(f"Không tìm thấy thư mục tài liệu {root} (cấu hình KNOWLEDGE_DIR)")
    return sorted(p for group in GROUPS for p in (root / group).glob("*.md"))


def load_chunks(files: list[Path]) -> list[Chunk]:
    chunks = []
    for path in files:
        meta, body = _frontmatter(path.read_text(encoding="utf-8"))
        group = path.parent.name
        rel = f"docs/knowledge/{group}/{path.name}"
        title = meta.get("title") or path.stem
        verified = bool(meta.get("verified_by") and meta.get("verified_at"))
        for heading, content in _split(body):
            for n, piece in enumerate(_pieces(content)):
                chunks.append(Chunk(
                    id=f"{rel}#{len(chunks)}",
                    # Tiêu đề và tên mục đi kèm nội dung để câu hỏi ngắn vẫn khớp đúng chủ đề
                    text=f"{title} — {heading}\n{piece}",
                    meta={"source": rel, "title": title, "heading": heading, "group": group, "part": n,
                          "verified": verified, "verified_by": meta.get("verified_by") or ""},
                ))
    return chunks


def _fingerprint(files: list[Path]) -> str:
    digest = hashlib.sha256(config.KNOWLEDGE_EMBED_MODEL.encode())
    for path in files:
        stat = path.stat()
        digest.update(f"{path}|{stat.st_mtime_ns}|{stat.st_size}".encode())
    return digest.hexdigest()


class _Index:
    def __init__(self) -> None:
        self._lock = threading.Lock()
        self._fingerprint: str | None = None
        self._model = None
        self._client = None
        self._collection = None

    def _embed(self, texts: list[str]) -> list[list[float]]:
        if self._model is None:
            from fastembed import TextEmbedding  # nạp muộn: chỉ tốn bộ nhớ khi có người dùng RAG
            try:
                self._model = TextEmbedding(config.KNOWLEDGE_EMBED_MODEL, cache_dir=config.KNOWLEDGE_MODEL_CACHE)
            except Exception as exc:
                raise KnowledgeUnavailable(f"Không nạp được model nhúng {config.KNOWLEDGE_EMBED_MODEL}: {exc}") from exc
        return [vector.tolist() for vector in self._model.embed(texts)]

    def ensure(self) -> int:
        """Dựng lại index nếu tài liệu đổi. Trả về số đoạn đang có trong index."""
        files = _files()
        fingerprint = _fingerprint(files)
        with self._lock:
            if fingerprint == self._fingerprint and self._collection is not None:
                return self._collection.count()
            import chromadb
            from chromadb.config import Settings

            chunks = load_chunks(files)
            if self._client is None:
                self._client = chromadb.EphemeralClient(Settings(anonymized_telemetry=False))
            collection = self._client.get_or_create_collection(COLLECTION, metadata={"hnsw:space": "cosine"})
            stale = collection.get(include=[])["ids"]
            if stale:
                collection.delete(ids=stale)
            if chunks:
                collection.add(ids=[c.id for c in chunks], documents=[c.text for c in chunks],
                               embeddings=self._embed([c.text for c in chunks]), metadatas=[c.meta for c in chunks])
            self._collection, self._fingerprint = collection, fingerprint
            log.info("Đã dựng index tài liệu: %d tệp, %d đoạn (service=ai-service, user_id=system)",
                     len(files), len(chunks))
            return len(chunks)

    def search(self, query: str, group: str | None, limit: int) -> list[dict]:
        if not self.ensure():
            return []
        result = self._collection.query(query_embeddings=self._embed([query]), n_results=limit,
                                        where={"group": group} if group else None)
        return [{"text": doc, "meta": meta, "score": 1 - distance}
                for doc, meta, distance in zip(result["documents"][0], result["metadatas"][0], result["distances"][0])]


_index = _Index()


def warmup() -> int:
    return _index.ensure()


def search(query: str, group: str | None = None, limit: int = 4) -> list[dict]:
    """Các đoạn liên quan nhất, xếp giảm dần theo độ liên quan (cosine, 0..1)."""
    return _index.search(query, group, limit)
