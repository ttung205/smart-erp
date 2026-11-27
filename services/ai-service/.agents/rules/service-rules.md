# Quy tắc chi tiết — ai-service

Đọc cùng `../../../../CLAUDE.md` (gốc repo) và `../../CLAUDE.md` (tổng quan service).

## 1. Hợp đồng API

| Route | Phương thức | Xác thực | Ghi chú |
|---|---|---|---|
| `/api/v1/ai/health`, `/health` | GET | không | trạng thái model, LLM, kho phân tích |
| `/api/v1/ai/chat` | POST | JWT | body `{message (1–2000 ký tự), conversation_id?}` → `{conversation_id, answer, tools_used, as_of}` |
| `/api/v1/ai/conversations` | GET | JWT | lịch sử hội thoại của chính người dùng |
| `/api/v1/ai/conversations/{id}` | GET, DELETE | JWT | chỉ thao tác trên hội thoại của mình |
| `/api/v1/ai/sync` | POST | JWT + SUPER_ADMIN | đồng bộ kho phân tích ngay |
| `/api/v1/ai/analytics/overview` | GET | JWT + `ai.use` hoặc `finance.view` hoặc SUPER_ADMIN | KPI tổng quan |
| `/api/v1/ai/analytics/forecast` | GET | như trên | dự báo doanh số theo ngày (Holt-Winters) |
| `/api/v1/ai/analytics/rfm-clusters` | GET | như trên | phân khúc khách hàng (KMeans, 4 cụm), lọc `cluster_key` |
| `/api/v1/ai/analytics/credit-risk` | GET | như trên | rủi ro công nợ |
| `/api/v1/ai/analytics/refresh` | POST | JWT + `ai.use` hoặc SUPER_ADMIN | huấn luyện lại mô hình, ETL chỉ chạy khi bật cấu hình và người gọi là SUPER_ADMIN |

Quy tắc chung:
- Phản hồi thành công bọc `{"success": true, "data": ...}` (hàm `ok()` trong `app/main.py`).
- Envelope do `app/envelope.py` tạo: `ok()` và `fail()`. Mọi phản hồi có `meta.correlation_id` (lấy từ `X-Correlation-ID` hoặc sinh mới) và `meta.timestamp`.
- Mã lỗi theo `docs/contracts/api-spec.md`: `UNAUTHENTICATED` (401), `PERMISSION_DENIED` (403), `RESOURCE_NOT_FOUND` (404), `VALIDATION_FAILED` (422, kể cả lỗi schema của FastAPI), `DEPENDENCY_UNAVAILABLE` (503, LLM không phản hồi).
- Pydantic schema dùng snake_case, khớp JSON trên wire.

## 2. Tool của trợ lý

- Định nghĩa trong `app/tools.py` (`TOOLS`, `TOOL_BY_NAME`), mỗi tool là `Tool(name, mô tả, schema, permissions, handler)`. Hiện có 22 tool.
- Tên tool: `sales_report`, `compare_sales`, `inventory_status`, `product_info`, `product_trend`, `customer_info`, `receivables_report`, `purchase_report`, `supplier_info`, `stock_card_trace`, `purchase_price_variance_alert`, `order_list`, `procurement_fast_order`, `instant_quote_check`, `demand_forecast`, `rfm_customer_segmentation`, `credit_risk_radar`, `search_company_policy`, `slow_moving_deadstock_radar`, `suggest_restock_plan`, `suggest_churn_retention`, `suggest_credit_action`.
- Tham số vào tool là `dict` đã kiểm tra; lỗi tham số trả `{"loi": ...}` để LLM diễn giải, không ném exception ra ngoài.
- Sau mỗi lượt hỏi, nhật ký tool được ghi bằng `store.log_tool_calls` (gọi từ `app/agent.py`) để truy vết.

## 3. ETL và kho phân tích

- `app/warehouse.py`: đồng bộ định kỳ (`SYNC_INTERVAL_SECONDS`) từ các endpoint export nội bộ, ghi vào schema `erp_analytics_db`, hoán đổi bản mới khi xong (swap).
- Khi kho được hoán đổi, `ml.warmup_cache()` huấn luyện lại để màn hình BI thấy dữ liệu mới.
- Lỗi đồng bộ được log kèm `service=ai-service`, `user_id=system`. Không nuốt lỗi.

## 4. Học máy và RAG

- Dự báo: Holt-Winters theo mùa tuần, kiểm định ngoài mẫu 30 ngày (`ml.py`).
- Phân khúc: RFM + KMeans (`n_clusters=4`, `random_state=42`).
- Gợi ý đặt hàng: `suggest_restock_plan` và `procurement_fast_order` (`ml.get_sku_procurement_ml_insight`).
- RAG: `app/knowledge.py` đọc `docs/knowledge/`, chunk theo heading, index bằng chromadb, embedding bằng fastembed. Index dựng lúc khởi động, nếu lỗi thì thử lại khi tool được gọi.

## 5. Kiểm thử

- `tests/test_idmap.py`: ánh xạ mã nội bộ.
- `tests/test_export_etl.py`: ETL qua export API.
- `tests/smoke_tools.py`: chạy thử tool trên kho thật.
- Thêm tool mới thì thêm test kiểm tra quyền: người không có quyền không được thấy tool và không chạy được tool.

## 6. Checklist trước khi bàn giao

- [ ] Tool mới khai báo `permissions` đúng.
- [ ] Tool chỉ SELECT trên `erp_analytics_db`.
- [ ] Số liệu tính bằng SQL/Python, không để LLM tính.
- [ ] Không đọc DB của service khác.
- [ ] Thời gian UTC.
- [ ] Không commit `.env`, `data/*.db`, `simulator/.backup/`.
