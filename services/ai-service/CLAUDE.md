# ai-service — hướng dẫn cho AI coding agent

Áp dụng toàn bộ quy tắc gốc tại `../../CLAUDE.md`. File này chỉ bổ sung những gì riêng cho service AI.
Chi tiết tool, phân quyền và checklist nằm ở `.agents/rules/service-rules.md`. Hướng dẫn chạy và mô phỏng dữ liệu nằm ở `README.md`.

## Vai trò

- **Trợ lý AI chỉ đọc** (chat, gọi tool SQL trên kho phân tích) và **phân tích BI/ML** (dự báo doanh số, phân khúc RFM, rủi ro công nợ, gợi ý đặt hàng).
- Port `8010`, prefix gateway `/api/v1/ai/`.
- Stack: Python 3.14, FastAPI + Uvicorn, Groq (LLM tương thích OpenAI, tool calling), pandas / statsmodels (Holt-Winters) / scikit-learn (KMeans), chromadb + fastembed (RAG tài liệu nội bộ), MySQL kho phân tích `erp_analytics_db`.

## Lệnh thường dùng

```bash
cd services/ai-service
.venv/Scripts/python -m uvicorn app.main:app --port 8010
.venv/Scripts/python -m unittest tests.test_idmap tests.test_export_etl
```

## Quy tắc riêng của service

1. **Chỉ đọc nghiệp vụ.** Tool chỉ SELECT trên kho phân tích `erp_analytics_db`. Không ghi vào DB của service nghiệp vụ.
2. **Không đọc database của service khác.** Dữ liệu đi qua ETL: gọi endpoint `/internal/analytics/*` của từng service (`app/export_client.py`) rồi nạp vào kho phân tích.
3. **Tool lọc theo quyền người hỏi.** `available_tools(actor)` chỉ đưa cho LLM các tool người đó có quyền; `run_tool` kiểm tra lại trước khi chạy. AI không có quyền tối cao.
4. **Số liệu do SQL tính**, đã định dạng sẵn. LLM chỉ chọn tool và diễn giải, không tự cộng trừ.
5. **JWT**: tự xác thực HS256 bằng secret chung với identity-service (`app/auth.py`).
6. **Thời gian** dùng UTC. Cấm dùng giờ server.

## Việc cần biết trước khi sửa

- `POST /api/v1/ai/sync` chỉ cho SUPER_ADMIN (đã kiểm tra trong `app/main.py`).
- `data/*.db`, `simulator/.backup/`, `.env` là dữ liệu và cấu hình cục bộ. Không commit.
- Tài liệu RAG đọc từ `docs/knowledge/`. Đây là nội dung trả lời người dùng cuối, không phải quy tắc viết code.
