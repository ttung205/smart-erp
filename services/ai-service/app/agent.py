"""Vòng lặp agent: LLM chọn tool → ai-service chạy tool (theo quyền người hỏi) → LLM diễn giải kết quả."""
from __future__ import annotations

import asyncio
import json
import logging
import time
from dataclasses import dataclass, field
from datetime import date

from . import config, llm, store, warehouse
from .auth import Actor
from .tools import TOOL_SCOPE, TOOLS, available_tools, run_tool

log = logging.getLogger("ai.agent")

ROLE_LABELS = {
    "SUPER_ADMIN": "Quản trị hệ thống", "SALE": "Nhân viên kinh doanh", "SALE_MANAGER": "Trưởng phòng kinh doanh",
    "PURCHASE": "Nhân viên mua hàng", "PURCHASE_MANAGER": "Trưởng phòng mua hàng", "LOGISTIC": "Nhân viên kho vận",
    "LOGISTIC_MANAGER": "Trưởng phòng kho vận", "FINANCE_ACCOUNTING": "Kế toán",
    "FINANCE_ACCOUNTING_MANAGER": "Kế toán trưởng", "TECHNICAL": "Kỹ thuật viên", "TECHNICAL_MANAGER": "Trưởng phòng kỹ thuật",
}

SYSTEM_PROMPT = """Bạn là Trợ lý AI nội bộ của công ty Smart ERP, nhà phân phối thiết bị gia dụng (kho tại Hà Nội; khách hàng gồm đại lý, nhà phân phối cấp 2, khách dự án và khách lẻ trên toàn quốc).

Người đang hỏi: {name} ({roles}).
Hôm nay: {today}. Dữ liệu ERP có đến ngày {as_of} (đồng bộ lúc {synced_at}). Khi người dùng nói "tháng này", "tuần này", "gần đây" thì tính theo ngày dữ liệu {as_of}, không theo hôm nay.

Quy tắc:
1. Mọi con số phải lấy từ công cụ. Không bịa số, không tự cộng trừ lại số tiền công cụ đã trả. Muốn so sánh hai kỳ thì gọi compare_sales.
2. Công cụ báo không có quyền thì nói rõ người dùng không có quyền xem phần đó; không suy đoán.
3. Trả lời bằng tiếng Việt, ngắn gọn, đi thẳng vào câu hỏi, luôn nêu kỳ dữ liệu. Liệt kê nhiều dòng thì dùng bảng markdown (tối đa khoảng 10 dòng).
4. Sau số liệu có thể thêm 1-2 nhận xét hoặc gợi ý hành động ngắn, dựa trên chính dữ liệu đó.
5. Bạn chỉ đọc dữ liệu, không tạo hay sửa được chứng từ. Nếu được nhờ thao tác, hướng dẫn người dùng làm trên màn hình tương ứng của ERP.
6. Câu hỏi không liên quan công việc: trả lời rất ngắn hoặc từ chối lịch sự.
7. Người dùng này KHÔNG có quyền xem: {denied}. Nếu được hỏi về các phần này, trả lời rằng tài khoản của họ chưa được cấp quyền xem (không nói là hệ thống không hỗ trợ).
Thuật ngữ: Dealer = đại lý, Distributor = nhà phân phối, Project / Business = khách dự án, Retail = khách lẻ."""


@dataclass
class Answer:
    conversation_id: int
    answer: str
    tools_used: list[str] = field(default_factory=list)
    as_of: str | None = None


def _system_prompt(actor: Actor) -> tuple[str, str]:
    with warehouse.connect() as con:
        meta = dict(con.execute("select key, value from meta").fetchall())
    roles = ", ".join(ROLE_LABELS.get(r, r) for r in actor.roles) or "nhân viên"
    denied = [TOOL_SCOPE[t.name] for t in TOOLS if t.name in TOOL_SCOPE and not actor.can(*t.permissions)]
    prompt = SYSTEM_PROMPT.format(name=actor.name, roles=roles, today=date.today().isoformat(),
                                  denied=", ".join(denied) or "(không có giới hạn)",
                                  as_of=meta.get("as_of", "?"), synced_at=meta.get("synced_at", "?"))
    return prompt, meta.get("as_of")


async def ask(actor: Actor, message: str, conversation_id: int | None) -> Answer:
    conv_id = store.get_or_create_conversation(actor.id, conversation_id, message)
    prompt, as_of = _system_prompt(actor)
    tools = available_tools(actor)
    messages = [{"role": "system", "content": prompt}, *store.history(conv_id, config.HISTORY_MESSAGES),
                {"role": "user", "content": message}]
    store.add_message(conv_id, "user", message)

    calls_log: list[dict] = []
    answer = ""
    for _ in range(config.MAX_TOOL_ROUNDS):
        reply = await llm.chat(messages, [t.schema() for t in tools])
        tool_calls = reply.get("tool_calls") or []
        if not tool_calls:
            answer = (reply.get("content") or "").strip()
            break
        messages.append({"role": "assistant", "content": reply.get("content") or "", "tool_calls": tool_calls})
        for call in tool_calls:
            name = call["function"]["name"]
            try:
                args = json.loads(call["function"].get("arguments") or "{}")
            except json.JSONDecodeError:
                args = {}
            started = time.perf_counter()
            result = await asyncio.to_thread(run_tool, actor, name, args)
            content = json.dumps(result, ensure_ascii=False, default=str)
            calls_log.append({"tool": name, "arguments": args, "ok": "loi" not in result,
                              "result_chars": len(content), "duration_ms": int((time.perf_counter() - started) * 1000)})
            messages.append({"role": "tool", "tool_call_id": call["id"], "content": content[:12000]})
    if not answer:
        # Hết số vòng mà model vẫn muốn gọi tool: yêu cầu trả lời với dữ liệu đã có
        messages.append({"role": "user", "content": "Hãy trả lời ngay với dữ liệu đã có."})
        answer = ((await llm.chat(messages)).get("content") or "").strip() or "Xin lỗi, tôi chưa trả lời được câu này."

    message_id = store.add_message(conv_id, "assistant", answer)
    if calls_log:
        store.log_tool_calls(conv_id, message_id, actor.id, calls_log)
    log.info("user=%s conv=%s tools=%s", actor.id, conv_id, [c["tool"] for c in calls_log])
    return Answer(conv_id, answer, list(dict.fromkeys(c["tool"] for c in calls_log)), as_of)
