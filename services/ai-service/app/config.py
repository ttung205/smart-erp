"""Cấu hình ai-service, đọc từ biến môi trường / file .env cạnh thư mục app."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SERVICES = ROOT.parent


def _load_dotenv() -> None:
    env_file = ROOT / ".env"
    if not env_file.exists():
        return
    for raw in env_file.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


_load_dotenv()

PORT = int(os.getenv("AI_SERVICE_PORT", "8010"))
# Swagger UI (/docs, /redoc) và /openapi.json: tắt ở production bằng APP_DOCS_ENABLED=false
DOCS_ENABLED = os.getenv("APP_DOCS_ENABLED", "true").lower() in {"1", "true", "yes", "on"}

# Groq: API tương thích OpenAI, có gói miễn phí (https://console.groq.com)
GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_BASE_URL = os.getenv("GROQ_BASE_URL", "https://api.groq.com/openai/v1")
GROQ_MODEL = os.getenv("GROQ_MODEL", "openai/gpt-oss-120b")
# Hạn mức gói miễn phí tính riêng từng model: bị giới hạn (429) thì chuyển sang model dự phòng
GROQ_FALLBACK_MODELS = [m.strip() for m in os.getenv("GROQ_FALLBACK_MODELS", "openai/gpt-oss-20b,qwen/qwen3.8-27b").split(",") if m.strip()]
LLM_TIMEOUT = float(os.getenv("LLM_TIMEOUT", "60"))
MAX_TOOL_ROUNDS = int(os.getenv("MAX_TOOL_ROUNDS", "6"))
HISTORY_MESSAGES = int(os.getenv("HISTORY_MESSAGES", "8"))

# JWT do identity-service cấp (HS256, secret dùng chung với các service)
JWT_SECRET = os.getenv("JWT_SECRET", "")
JWT_ISSUER = os.getenv("JWT_ISSUER", "identity-service")
REQUIRED_PERMISSION = "ai.use"

# MySQL: lịch sử hội thoại và kho phân tích cùng nằm trong schema ANALYTICS_DB_NAME
MYSQL_HOST = os.getenv("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", "3306"))
MYSQL_USER = os.getenv("MYSQL_USER", "erp_user")
MYSQL_PASSWORD = os.getenv("MYSQL_PASSWORD", "erp_secret")
ANALYTICS_DB_NAME = os.getenv("ANALYTICS_DB_NAME", "erp_analytics_db")
SYNC_INTERVAL_SECONDS = int(os.getenv("SYNC_INTERVAL_SECONDS", "300"))
WAREHOUSE_SYNC_ENABLED = os.getenv("WAREHOUSE_SYNC_ENABLED", "true").lower() in {"1", "true", "yes", "on"}
# Dữ liệu phân tích hiện là dữ liệu mô phỏng (simulator). Giao diện ghi nhãn theo cờ này; chỉ tắt khi có dữ liệu thật.
ANALYTICS_DATA_SIMULATED = os.getenv("ANALYTICS_DATA_SIMULATED", "true").lower() in {"1", "true", "yes", "on"}

# Nguồn dữ liệu cho ETL: endpoint export nội bộ của từng service (không đọc database của service khác).
# URL không có giá trị mặc định: thiếu cấu hình thì đồng bộ báo lỗi rõ ràng thay vì gọi nhầm địa chỉ.
SERVICE_URLS = {
    "identity": os.getenv("IDENTITY_SERVICE_URL", "").rstrip("/"),
    "catalog": os.getenv("CATALOG_SERVICE_URL", "").rstrip("/"),
    "sales": os.getenv("SALES_SERVICE_URL", "").rstrip("/"),
    "inventory": os.getenv("INVENTORY_SERVICE_URL", "").rstrip("/"),
    "purchase": os.getenv("PURCHASE_SERVICE_URL", "").rstrip("/"),
    "finance": os.getenv("FINANCE_SERVICE_URL", "").rstrip("/"),
}
INTERNAL_SERVICE_KEY = os.getenv("INTERNAL_SERVICE_KEY", "")
EXPORT_TIMEOUT_SECONDS = float(os.getenv("EXPORT_TIMEOUT_SECONDS", "5"))
EXPORT_PAGE_SIZE = int(os.getenv("EXPORT_PAGE_SIZE", "1000"))

# RAG tài liệu nội bộ (docs/knowledge). Trong container thư mục được mount chỉ đọc và trỏ bằng KNOWLEDGE_DIR.
KNOWLEDGE_DIR = Path(os.getenv("KNOWLEDGE_DIR", str(SERVICES.parent / "docs" / "knowledge")))
# Model nhúng đa ngôn ngữ chạy cục bộ qua fastembed (ONNX, không cần GPU/torch)
KNOWLEDGE_EMBED_MODEL = os.getenv("KNOWLEDGE_EMBED_MODEL", "sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2")
KNOWLEDGE_MODEL_CACHE = os.getenv("KNOWLEDGE_MODEL_CACHE") or None
# Đoạn có độ liên quan (cosine) thấp hơn ngưỡng này bị loại: trả "không tìm thấy" thay vì ngữ cảnh lạc đề
KNOWLEDGE_MIN_SCORE = float(os.getenv("KNOWLEDGE_MIN_SCORE", "0.42"))
