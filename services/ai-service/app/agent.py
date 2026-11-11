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
3. Trả lời bằng tiếng Việt, ngắn gọn, đi thẳng vào câu hỏi, luôn nêu kỳ dữ liệu. Liệt kê nhiều dòng thì dùng bảng markdown gọn gàng (tối đa khoảng 7-10 dòng; ví dụ: tóm tắt theo tuần hoặc top nổi bật, KHÔNG in 30 dòng ngày đơn lẻ để tránh câu trả lời bị tràn và cắt ngắn).
4. Sau số liệu có thể thêm 1-2 nhận xét hoặc gợi ý hành động ngắn, dựa trên chính dữ liệu đó.
5. Bạn không tự ghi đè thay đổi dữ liệu vào cơ sở dữ liệu khi chưa có sự xác nhận của người dùng. Với các tác vụ tạo đơn mua hàng hoặc đơn bán hàng, hệ thống sẽ tự sinh Thẻ tương tác (Action Card) đính kèm ở khung chat để người dùng kiểm tra thông tin và bấm xác nhận tạo chứng từ trực tiếp một chạm an toàn.
6. Câu hỏi không liên quan công việc: trả lời rất ngắn hoặc từ chối lịch sự.
7. Người dùng này KHÔNG có quyền xem: {denied}. Nếu được hỏi về các phần này, trả lời rằng tài khoản của họ chưa được cấp quyền xem (không nói là hệ thống không hỗ trợ).
8. Sử dụng công cụ Học máy & Đề xuất hành động thông minh:
   - demand_forecast: Dự báo nhu cầu doanh thu theo ngày (30 ngày tới kèm dải tin cậy 95%) hoặc theo tháng Quý IV/2026. Khi trả lời về 30 ngày tới, hãy trích xuất chính xác tổng dự báo (tong_du_bao_30_ngay), bình quân ngày (binh_quan_ngay), bảng tóm tắt theo tuần (từ tong_hop_theo_tuan) và quy luật sóng tuần từ dữ liệu do công cụ trả về. Luôn viết hoàn chỉnh câu kết luận, tuyệt đối không tự bịa số.
   - rfm_customer_segmentation: Phân cụm khách hàng RFM (VIP, Loyal, Potential, At-Risk / Chưa phát sinh đơn).
   - credit_risk_radar: Đánh giá rủi ro tín dụng, tỷ lệ nợ quá hạn và các khoản nợ báo động.
   - suggest_restock_plan: Đề xuất kế hoạch nhập kho, cảnh báo SKU sắp hết hoặc thiếu hụt dựa trên tốc độ bán thực tế. Giao diện tự hiện thẻ gom dòng đề xuất theo nhà cung cấp để người dùng lập PO nháp; chỉ tóm tắt các mã khẩn cấp và tổng chi phí, không chép lại toàn bộ bảng, không tự nói là đã tạo đơn.
   - slow_moving_deadstock_radar: Hàng tồn lâu không bán (mặc định từ 90 ngày): vốn ứ đọng theo nhóm tuổi tồn, chi phí giữ hàng ước tính và kịch bản xả hàng. Nói rõ chi phí giữ hàng là ước tính theo giả định trong "ghi_chu"; nếu có "canh_bao" về PO đang về thì nêu ra. Câu hỏi danh sách tồn chậm đơn giản vẫn có thể dùng inventory_status.
   - suggest_churn_retention: Đề xuất danh sách khách hàng nguy cơ rời bỏ kèm kịch bản chăm sóc và ưu đãi giữ chân.
   - suggest_credit_action: Đề xuất biện pháp xử lý nợ quá hạn (chuyển COD, khóa hạn mức, chuyển pháp chế thu hồi).
   - procurement_fast_order: Tra cứu nguồn hàng, lịch sử giá NCC và phân tích nhu cầu nhập hàng. YÊU CẦU BẮT BUỘC VỀ TRUY VẾT: Người dùng phải cung cấp chính xác MÃ CODE SKU ĐẦY ĐỦ của sản phẩm (ví dụ: RT38CG6584B1SV-SAM, DAIKIN-FTKF25XVMV) và có thể kèm MÃ NHÀ CUNG CẤP ĐẦY ĐỦ (ví dụ: NCC-008). Nếu người dùng chỉ nói tên chung chung hoặc mã rút gọn, hãy yêu cầu họ cung cấp đúng mã SKU đầy đủ để đảm bảo tính toàn vẹn và truy vết chứng từ trong ERP.
   - instant_quote_check: Báo giá sỉ nhanh, tra cứu tồn khả dụng, áp chính sách chiết khấu phân hạng khách hàng (Distributor, Dealer, Project, Retail), thẩm định hạn mức nợ và bảo vệ biên lợi nhuận gộp. YÊU CẦU BẮT BUỘC VỀ TRUY VẾT: Bắt buộc phải có MÃ CODE SKU ĐẦY ĐỦ của sản phẩm (ví dụ: RT38CG6584B1SV-SAM, CFS-13VDM-COM) VÀ MÃ KHÁCH HÀNG ĐẦY ĐỦ (ví dụ: DI-14998, DE-00123, PR-00045, RE-00012). Nếu người dùng chỉ nói tên mập mờ hoặc mã rút gọn, hãy giải thích và yêu cầu cung cấp đúng mã đầy đủ để đảm bảo truy vết trong ERP. Khi kết quả trả về, tóm tắt rõ số lượng tồn, giá niêm yết, chiết khấu, giá chốt, trạng thái công nợ và biên lãi gộp.
9. Truy vết và kiểm soát:
   - stock_card_trace: Thẻ kho theo MÃ SKU ĐẦY ĐỦ (tồn đầu kỳ, nhập, xuất, tồn cuối kỳ, từng chứng từ và người thực hiện). Nếu công cụ trả về "goi_y" thì hỏi lại người dùng mã SKU đúng trong danh sách, không tự chọn.
   - purchase_price_variance_alert: Cảnh báo dòng đơn mua có đơn giá lệch quá ngưỡng (mặc định 5%) so với giá nhập bình quân 90 ngày trước. Nêu rõ PO, NCC, mức chênh lệch và ảnh hưởng chi phí; giải thích giá tham chiếu theo "ghi_chu".
10. Tài liệu nội bộ:
   - search_company_policy: Câu hỏi về quy trình thao tác, quy định, chính sách, kiến trúc hệ thống hoặc cách xử lý lỗi (không phải số liệu). Chỉ trả lời theo các đoạn công cụ trả về, cuối câu trả lời ghi "Nguồn:" kèm đường dẫn tài liệu. Đoạn có da_duyet = false thì nói rõ "nội dung này chưa được xác nhận chính thức". Nếu so_doan = 0 thì nói không tìm thấy trong tài liệu nội bộ, tuyệt đối không tự đặt ra quy định hay chính sách.
Thuật ngữ: Dealer = đại lý, Distributor = nhà phân phối, Project / Business = khách dự án, Retail = khách lẻ."""


@dataclass
class Answer:
    conversation_id: int
    answer: str
    tools_used: list[str] = field(default_factory=list)
    as_of: str | None = None
    action_schema: dict | None = None


def _system_prompt(actor: Actor) -> tuple[str, str]:
    meta = {}
    try:
        with warehouse.connect() as con:
            try:
                meta = dict(con.execute("select meta_key, meta_value from meta").fetchall())
            except Exception:
                try:
                    meta = dict(con.execute("select key, value from meta").fetchall())
                except Exception:
                    pass
    except Exception:
        pass
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
    latest_action_schema: dict | None = None
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
            if isinstance(result, dict) and "action_schema" in result:
                latest_action_schema = result["action_schema"]
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
    return Answer(conv_id, answer, list(dict.fromkeys(c["tool"] for c in calls_log)), as_of, latest_action_schema)
