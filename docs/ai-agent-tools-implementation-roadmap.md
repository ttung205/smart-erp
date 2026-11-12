# KẾ HOẠCH TRIỂN KHAI HOÀN THIỆN AI-SERVICE THEO KIẾN TRÚC ENTERPRISE AI AGENT 8 TẦNG
## (8-LAYER ACTIONABLE AI AGENT ARCHITECTURE & ML INTEGRATION)

> **Tài liệu tham chiếu chuẩn**: Tuân thủ nghiêm ngặt các quy chuẩn kiến trúc và lập trình tại:
> - [AGENTS.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/AGENTS.md) (Master Architecture & 7 nguyên tắc tự vấn)
> - [api-guidelines.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/api-guidelines.md) (RESTful Envelope & Gateway Routing)
> - [architecture.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/architecture.md) (Clean Architecture, Event-Driven, Saga & Concurrency)
> - [database-guidelines.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_rule/database-guidelines.md) (Database-per-Service, Logical Keys & Outbox)
> - [UI_RULES.md](file:///g:/Do_an_lien_nganh/smart-erp/docs/Ai_UI_rule/UI_RULES.md) (Vue 3, PrimeVue 4, Tailwind CSS, Generative UI Action Cards)

---

## 1. TỔNG QUAN KIẾN TRÚC ENTERPRISE AI AGENT 8 TẦNG (FULL-STACK AGENT ARCHITECTURE)

Nhằm chuẩn hóa `ai-service` thành nền tảng AI doanh nghiệp toàn diện phục vụ đồ án liên ngành và vận hành thực tế, hệ thống được quy hoạch và hoàn thiện theo **Kiến trúc AI Agent 8 tầng tiêu chuẩn công nghiệp (Production-Grade Enterprise AI Stack)**:

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                    KIẾN TRÚC AI-SERVICE ENTERPRISE 8 TẦNG (AI AGENT FULL STACK)                 │
├─────────────────────────────────────────────────────────────────────────────────────────────────┤
│ [08] OBSERVABILITY   │ Giám sát, Tracing (Langfuse/OpenTelemetry), Audit Tool Calls & Chi phí   │
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [07] CACHE + ROUTER  │ In-memory ML Cache, Semantic Response Cache & Smart Intent Router        │
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [06] GUARDRAILS      │ Phòng vệ an toàn: Chặn Prompt Injection, Masking PII, Hallucination Check│
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [05] PHÂN QUYỀN      │ RBAC hạt mịn: Kiểm soát Token JWT, Tiền lọc Tool & Thẩm tra Runtime      │
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [04] TOOLS • MCP     │ 24 Actionable Tools kết hợp Scikit-Learn ML & Chuẩn hóa MCP Server       │
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [03] RAG             │ Tri thức phi cấu trúc: Vector Search (Chroma/PgVector) cho Quy chế & SOP │
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [02] SYSTEM PROMPT   │ Ngữ cảnh động: Dynamic Injection vai trò, as_of, quyền hạn (Zero-Hardcode)│
├──────────────────────┼──────────────────────────────────────────────────────────────────────────┤
│ [01] MODEL           │ Groq Cloud API (gpt-oss-120b) + Fallback, hỗ trợ Local LLM (vLLM/Ollama) │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
```

### 1.1. Bối cảnh & Bước chuyển đổi
Hệ thống `ai-service` sở hữu kho phân tích dữ liệu và học máy mạnh mẽ (`ml.py`: K-Means RFM, Holt-Winters kết hợp ngoại sinh thời tiết, Credit Risk Scoring) và bộ công cụ AI chỉ-đọc (`app/tools.py`). 

Mục tiêu cốt lõi:
1. **Chuyển đổi từ Passive Read-Only sang Actionable & Generative Agent**: Cho phép người dùng ra lệnh bằng ngôn ngữ tự nhiên $\rightarrow$ Agent tự gọi tool tính toán $\rightarrow$ Trả về thẻ tương tác (Action Card) trên Web $\rightarrow$ Người dùng bấm 1 nút để thực thi ngay (tạo Draft PO, tạo Draft SO, lập Báo giá).
2. **Tuân thủ nghiêm ngặt 8 tầng kiến trúc**: Đảm bảo phân quyền hạt mịn, bảo vệ dữ liệu với Guardrails, tối ưu tốc độ bằng Cache + Router, và giám sát minh bạch qua Observability.

```text
┌─────────────────────────────────────────────────────────────────────────────────────────────────┐
│                                 LUỒNG TƯƠNG TÁC AGENTIC ERP                                     │
│                                                                                                 │
│  [ Người dùng Chat ] ──► [ API Gateway :8000 ] ──► [ ai-service :8010 ]                         │
│                                                             │                                   │
│  ┌──────────────────────────────────────────────────────────┴────────────────────────────────┐  │
│  │ Vòng lặp Agent (Function Calling):                                                        │  │
│  │ 1. LLM nhận diện SKU & Intent ──► Gọi Tool Copilot: procurement_fast_order / instant_quote│  │
│  │ 2. Tool truy vấn giá + Tồn kho + Phân tích ML + Kiểm tra nợ/biên lãi                      │  │
│  │ 3. Trả về Action Schema Payload: { action_type: 'ACTION_CARD_...', can_execute, ... }    │  │
│  └──────────────────────────────────────────────────────────┬────────────────────────────────┘  │
│                                                             │                                   │
│  [ Vue 3 Chat Drawer ] ◄── Render Interactive Card ◄────────┘                                   │
│            │                                                                                    │
│            ├─► Người dùng bấm [Xác nhận Tạo PO] ──► [ Gateway :8000 ] ──► [ purchase-service ]   │
│            └─► Người dùng bấm [Xác nhận Tạo SO] ──► [ Gateway :8000 ] ──► [ sales-service ]      │
└─────────────────────────────────────────────────────────────────────────────────────────────────┘
```

---

## 2. KẾ HOẠCH TRIỂN KHAI CHI TIẾT TUÂN THỦ 8 TẦNG KIẾN TRÚC

### 🟢 TẦNG 01: MODEL (Nền tảng Mô hình Ngôn ngữ Lớn)
* **Hiện trạng**: Đã cấu hình Groq API (`openai/gpt-oss-120b`) với cơ chế Fallback tự động sang `openai/gpt-oss-20b` và `qwen/qwen3.8-27b` khi bị Rate Limit (HTTP 429).
* **Các bước triển khai**:
  1. **Bước 1.1 - Hoàn thiện Module LLM Client (`app/llm.py`)**: Tối ưu hóa timeout, cơ chế exponential backoff khi gọi completions, chuẩn hóa cấu trúc message gửi và nhận.
  2. **Bước 1.2 - Hỗ trợ Local LLM Engine (On-Premise)**: Cho phép chuyển đổi linh hoạt qua biến môi trường `GROQ_BASE_URL` trỏ về máy chủ nội bộ chạy **vLLM** hoặc **Ollama** (`Qwen-2.5-14B-Instruct` hoặc `Llama-3.1-8B`), đáp ứng yêu cầu vận hành bảo mật dữ liệu offline 100%.

### 🟢 TẦNG 02: SYSTEM PROMPT (Ngữ cảnh & Chỉ thị Vận hành Động)
* **Hiện trạng**: `SYSTEM_PROMPT` trong `app/agent.py` đã loại bỏ hoàn toàn các con số hardcode mẫu. Tự động inject động danh tính `name`, danh sách vai trò `roles`, ngày chốt dữ liệu `as_of`, thời điểm đồng bộ `synced_at`, và danh sách quyền bị cấm `denied`.
* **Các bước triển khai**:
  1. **Bước 2.1 - Chỉ thị Zero-Hardcode & Dynamic Data Extraction**: Bắt buộc mọi số liệu doanh thu, tồn kho, công nợ và dự báo ML phải trích xuất trực tiếp từ kết quả công cụ trả về.
  2. **Bước 2.2 - Hướng dẫn định dạng Markdown & Bảng biểu**: Hạn chế in bảng dài quá 7-10 dòng (tóm tắt theo tuần hoặc top nổi bật) để chống tràn token và cắt cụt câu trả lời.
  3. **Bước 2.3 - Chỉ thị sinh Action Schema (Generative UI Directive)**: Dạy model cách nhận diện ý định muốn tạo đơn/thao tác để trả về cấu trúc payload JSON `action_schema`, kích hoạt thẻ tương tác trên giao diện người dùng.

### 🟡 TẦNG 03: RAG (Retrieval-Augmented Generation - Tri thức phi cấu trúc)
* **Hiện trạng**: Hệ thống đang sử dụng cơ chế Text-to-Data SQL trên kho phân tích MySQL `erp_analytics_db`. Chưa có khả năng tra cứu tài liệu dạng văn bản tự do (SOP quy trình, chính sách bảo hành, điều khoản chiết khấu).
* **Các bước triển khai**:
  1. **Bước 3.1 - Tích hợp Vector Database nội bộ**: Nhúng **ChromaDB** ở chế độ chạy trong tiến trình (embedded, lưu ra thư mục cục bộ) vào `ai-service`. Không dùng container riêng. Embedding model chạy cục bộ (ví dụ bge-m3 hỗ trợ tiếng Việt), LLM sinh câu trả lời vẫn dùng Groq.
  2. **Bước 3.2 - Xây dựng Pipeline Nạp & Phân mảnh tài liệu (Document Ingestion)**: Tài liệu nằm trong `docs/knowledge/`, chia theo 4 nhóm:
     - *Nghiệp vụ* (`business/`): Các luồng ERP như tạo đơn bán, duyệt báo giá, nhập kho, hàng trả, đối soát 3 bên (3-Way Matching). Nguồn: code các service, người nghiệp vụ xác nhận.
     - *Kỹ thuật* (`technical/`): Kiến trúc, API, sự kiện, triển khai. Nguồn: `docs/architecture/`, `docs/contracts/`, `docs/deployment.md`.
     - *FAQ & xử lý lỗi* (`faq/`): Lỗi thường gặp kèm thông báo nguyên văn và cách xử lý. Nguồn: lỗi thực tế khi kiểm thử.
     - *Chính sách* (`policy/`): Hạn mức công nợ theo nhóm đại lý, quy chế chiết khấu doanh số, chính sách bảo hành và đổi trả (ví dụ đổi mới trong 30 ngày, bảo hành máy nén điều hòa 5 năm). Nguồn: văn bản do người có thẩm quyền ban hành, **không** sinh tự động từ code. Bot không được tự suy ra nội dung nhóm này.
     - Bản nháp sinh tự động đặt ở `docs/knowledge/_draft/`, chỉ được index sau khi người duyệt xác nhận (có `verified_by`, `verified_at` trong frontmatter).
  3. **Bước 3.3 - Khởi tạo Tool RAG**: Bổ sung Tool `search_company_policy(query, category)` vào `app/tools.py`, với `category` nhận một trong bốn giá trị `business`, `technical`, `faq`, `policy`. LLM tự kết hợp dữ liệu giao dịch SQL và tài liệu nội bộ, và mỗi đoạn trả về kèm đường dẫn nguồn để đối chiếu ở Tầng 06.2.

### 🟢 TẦNG 04: TOOLS • MCP (Actionable Function Calling & Chuẩn hóa MCP)
* **Hiện trạng**: 20 tool trong `app/tools.py`: 12 tool SQL chỉ đọc (gồm `stock_card_trace`, `purchase_price_variance_alert`), 3 tool Machine Learning (`demand_forecast`, `rfm_customer_segmentation`, `credit_risk_radar`), 3 tool gợi ý hành động (`suggest_restock_plan`, `suggest_churn_retention`, `suggest_credit_action`) và 2 Tool Copilot (`procurement_fast_order` & `instant_quote_check`). Chưa có MCP Server.
* **Các bước triển khai**:
  1. **Bước 4.1 - Triển khai Top Actionable Tools**: Xây dựng các tool tương tác 2 chiều (tra cứu giá NCC $\rightarrow$ gợi ý số lượng theo ML $\rightarrow$ tạo Draft PO/SO) theo Bảng quy hoạch Mục 3.
  2. **Bước 4.2 - Chuẩn hóa giao thức Model Context Protocol (MCP)**:
     - Đóng gói các tool ERP thành một **MCP Server** chuẩn (`mcp-server-erp`).
     - Cung cấp chuẩn MCP Resources (đọc danh mục, tồn kho) và MCP Tools (tạo đơn, chạy dự báo) để tích hợp liền mạch với các trợ lý AI chuẩn công nghiệp (Claude Desktop, Antigravity IDE, Cursor).

### 🟢 TẦNG 05: PHÂN QUYỀN (Fine-grained RBAC & Bảo mật Thực thi)
* **Hiện trạng**: Kiểm tra Token JWT (HS256) từ `identity-service`, tiền lọc công cụ khả dụng (`available_tools`), và kiểm tra thẩm quyền lần 2 tại thời điểm chạy (`run_tool`).
* **Các bước triển khai**:
  1. **Bước 5.1 - Mở rộng Scope quyền hạn hạt mịn**: Bổ sung các quyền tác vụ: `purchase.create`, `order.create`, `quotation.create`, `finance.approve`.
  2. **Bước 5.2 - Gắn cờ quyền hạn vào Action Card Payload**:
     - Trường `can_execute: actor.can('purchase.create')` hoặc `actor.can('order.create')` được đính kèm vào payload gửi xuống Vue 3.
     - Nếu người dùng thiếu quyền, giao diện hiển thị Action Card ở chế độ xem thông tin (Read-only Preview) với nút xác nhận bị vô hiệu hóa kèm icon khóa và thông báo yêu cầu cấp quyền.

### 🟡 TẦNG 06: GUARDRAILS (Lớp phòng vệ An toàn Đầu vào & Đầu ra)
* **Hiện trạng**: Pydantic kiểm tra độ dài tin nhắn input (min_length=1, max_length=2000). Bộ lọc Strict Code Traceability chặn các truy vấn mã rút gọn hoặc mơ hồ.
* **Các bước triển khai**:
  1. **Bước 6.1 - Input Guardrail (Bảo vệ Đầu vào)**:
     - *Phát hiện Prompt Injection / Jailbreak*: Bộ lọc regex & semantic check phát hiện các hành vi yêu cầu bỏ qua System Prompt, tiết lộ JWT Secret hoặc can thiệp trái phép.
     - *Strict Code Traceability*: Bắt buộc người dùng cung cấp chính xác SKU đầy đủ và Mã đối tác đầy đủ để bảo toàn tính toàn vẹn chứng từ ERP.
  2. **Bước 6.2 - Output Guardrail (Kiểm soát Đầu ra)**:
     - *Bảo mật thông tin nhạy cảm (PII Masking)*: Tự động che số CCCD, mật khẩu băm, số thẻ ngân hàng cá nhân trước khi trả về Client.
     - *Xác thực tính trung thực (Hallucination Checker)*: Đối chiếu các con số chính (doanh thu, tiền nợ, số lượng tồn) trong câu trả lời văn bản với kết quả thô của tool.

### 🟡 TẦNG 07: CACHE + ROUTER (Tối ưu Hiệu năng & Định tuyến Thông minh)
* **Hiện trạng**: Đã có In-memory Cache cho ML (`ml.warmup_cache()`) giúp phản hồi thuật toán < 50ms và Fallback Router khi gặp lỗi 429.
* **Các bước triển khai**:
  1. **Bước 7.1 - Xây dựng Semantic Response Cache**:
     - Lưu trữ embedding các câu hỏi phổ biến và câu trả lời tương ứng trong ngày vào In-memory Cache.
     - Khi nhận câu hỏi mới có cosine similarity $\ge 93\%$ với câu hỏi đã có, trả ngay kết quả trong < 30ms mà không tốn token Groq API.
  2. **Bước 7.2 - Xây dựng Smart Intent Router**:
     - *Phân luồng Chitchat*: Nhận diện câu chào hỏi xã giao $\rightarrow$ Trả về mẫu câu chào tiêu chuẩn ngay lập tức.
     - *Phân luồng SQL*: Nhận diện câu hỏi số liệu tồn kho, doanh số $\rightarrow$ Kích hoạt bộ tool SQL trên `erp_analytics_db`.
     - *Phân luồng Machine Learning*: Nhận diện câu hỏi dự báo, phân nhóm khách hàng, cảnh báo nợ $\rightarrow$ Kích hoạt bộ tool `ml.py`.

### 🟡 TẦNG 08: OBSERVABILITY (Giám sát Toàn diện, Tracing & Đánh giá)
* **Hiện trạng**: Lưu log hội thoại và log gọi tool vào bảng `conversations`, `messages`, `tool_calls` trong database phân tích.
* **Các bước triển khai**:
  1. **Bước 8.1 - Tích hợp Nền tảng Tracing Langfuse**:
     - Cài đặt SDK langfuse vào `ai-service`.
     - Tự động ghi nhận vết (Trace) từng request: thời gian intent classification, thời gian LLM sinh token, thời gian tool chạy, tổng token in/out.
  2. **Bước 8.2 - Dashboard Quản trị & Feedback Loop**:
     - Xây dựng bảng theo dõi chi phí API hàng ngày, tỷ lệ lỗi HTTP 429/500, và độ trễ P95/P99.
     - Bổ sung tính năng đánh giá câu trả lời (Like/Dislike) từ người dùng trên khung chat để làm dữ liệu tinh chỉnh Prompt.

---

## 3. BẢNG QUY HOẠCH HỆ THỐNG 24 CÔNG CỤ AI CHUẨN MCP (MODEL CONTEXT PROTOCOL)

Toàn bộ công cụ của `ai-service` được phân chia thành 3 cấu phần cốt lõi:
* **MCP Tools**: 24 công cụ thực thi có Schema đầu vào chuẩn JSON Schema, kiểm tra RBAC và trả về dữ liệu hoặc Action Card.
* **MCP Resources**: Các URI tài nguyên dữ liệu chuẩn của ERP (`erp://catalog/products/{sku}`, `erp://inventory/stock-summary`, `erp://finance/customers/{id}/debt`).
* **MCP Prompts**: Bộ kịch bản mẫu định hướng xử lý nghiệp vụ (`procurement_negotiation_prompt`, `margin_guard_prompt`, `debt_recovery_prompt`).

### BẢNG DANH MỤC 24 CÔNG CỤ MCP THEO 5 PHÂN HỆ CỐT LÕI CỦA ERP

#### 📦 PHÂN HỆ 1: MUA HÀNG & QUẢN LÝ NHÀ CUNG CẤP (PROCUREMENT & SOURCING)
| STT | Mã Tool MCP | Quyền hạn RBAC | Dịch vụ & ML liên kết | Mô tả nghiệp vụ & Giao diện tương tác (Generative UI) | Trạng thái |
| :-: | :--- | :--- | :--- | :--- | :---: |
| **01** | `procurement_fast_order` | `purchase.create` | `purchase-service`<br>`catalog-service`<br>`ml.py` (Holt-Winters) | **Truy cứu giá NCC & Tạo Draft PO**: Nhập SKU $\rightarrow$ So sánh giá các NCC, gợi ý SL nhập theo ML $\rightarrow$ Thẻ tương tác chọn giá và nút `[Tạo Đơn mua PO]`. | ✅ **HOÀN THÀNH (PILOT)** |
| **02** | `smart_restock_plan` | `purchase.view`<br>`inventory.view` | `inventory-service`<br>`ml.py` (Dự báo nhu cầu) | **Đề xuất kế hoạch nhập kho thông minh**: Quét mã hàng $\text{Available} \le \text{MinStock}$, tính tốc độ bán $\rightarrow$ Cảnh báo thiếu hàng kèm nút `[Tạo PO khuyến nghị]`. | 🚀 Sprint 1 |
| **03** | `supplier_scorecard_evaluate` | `purchase.view` | `purchase-service`<br>`FactPurchase` | **Đánh giá xếp hạng KPI/SLA Nhà cung cấp**: Tổng hợp tỷ lệ giao trễ, tỷ lệ hàng lỗi phải hoàn trả, thời gian giao hàng (Lead Time) và độ biến động giá. | 🚀 Sprint 2 |
| **04** | `purchase_price_variance_alert` | `purchase.view` | `purchase-service` (qua kho phân tích)<br>`fact_po_line` | **Cảnh báo biến động giá mua (PPV)**: Phát hiện dòng PO có đơn giá lệch quá ngưỡng (mặc định 5%) so với giá nhập bình quân theo số lượng 90 ngày trước ngày đặt, xếp theo ảnh hưởng chi phí. Chưa so với hợp đồng khung vì hệ thống chưa lưu hợp đồng khung. | ✅ **ĐÃ TÍCH HỢP** |
| **05** | `resolve_matching_discrepancy` | `purchase.view`<br>`inventory.view` | `purchase-service`<br>`inventory-service` | **Đối soát sai lệch 3 bên (3-Way Matching)**: So khớp Đơn mua PO $\leftrightarrow$ Phiếu nhập kho GRN $\leftrightarrow$ Hóa đơn VAT, tự sinh biên bản chênh lệch số lượng/giá. | 🚀 Sprint 3 |

#### 🏭 PHÂN HỆ 2: KHO VẬN & QUẢN TRỊ TỒN KHO (WAREHOUSE & LOGISTICS)
| STT | Mã Tool MCP | Quyền hạn RBAC | Dịch vụ & ML liên kết | Mô tả nghiệp vụ & Giao diện tương tác (Generative UI) | Trạng thái |
| :-: | :--- | :--- | :--- | :--- | :---: |
| **06** | `stock_card_trace` | `inventory.view` | `inventory-service` (qua kho phân tích)<br>`stock_move` | **Truy vết thẻ kho theo dòng thời gian**: Theo mã SKU đầy đủ: tồn đầu kỳ, tổng nhập, tổng xuất, tồn cuối kỳ và từng phát sinh theo số chứng từ, kho, tồn sau phát sinh, nhân viên thực hiện. Chưa gồm sổ giữ chỗ (Reservation). | ✅ **ĐÃ TÍCH HỢP** |
| **07** | `slow_moving_deadstock_radar` | `inventory.view`<br>`report.view` | `inventory-service`<br>`ml.py` (Velocity scoring) | **Nhận diện hàng tồn kho chậm luân chuyển (Dead Stock)**: Quét các SKU $>90$ ngày không phát sinh đơn bán, tính chi phí ứ đọng vốn và gợi ý kịch bản xả hàng. | 🚀 Sprint 1 |
| **08** | `inter_warehouse_transfer_plan` | `inventory.create` | `inventory-service`<br>`catalog-service` | **Đề xuất điều chuyển hàng liên kho**: Phân tích kho dư thừa và kho thiếu hụt cục bộ $\rightarrow$ Trả về thẻ điều chuyển kèm nút `[Tạo Phiếu Xuất Điều Chuyển]`. | 🚀 Sprint 1 |
| **09** | `warehouse_slotting_rebalance` | `inventory.view` | `inventory-service`<br>ABC Classification | **Khuyến nghị sắp xếp vị trí kho (Slotting)**: Đề xuất chuyển các mặt hàng bán chạy (Nhóm A) về gần cửa xuất hàng để tối ưu quãng đường di chuyển. | 🚀 Sprint 3 |
| **10** | `cycle_count_dispatcher` | `inventory.create` | `inventory-service` | **Khởi tạo lệnh kiểm kê đột xuất**: Phát hiện các SKU có tỷ lệ hủy đơn cao hoặc có nghi vấn sai lệch tồn $\rightarrow$ Tự động tạo phiếu phân công kiểm kho ngẫu nhiên. | 🚀 Sprint 3 |

#### 💰 PHÂN HỆ 3: BÁN HÀNG & PHÂN PHỐI B2B (SALES & DISTRIBUTION)
| STT | Mã Tool MCP | Quyền hạn RBAC | Dịch vụ & ML liên kết | Mô tả nghiệp vụ & Giao diện tương tác (Generative UI) | Trạng thái |
| :-: | :--- | :--- | :--- | :--- | :---: |
| **11** | `instant_quote_check` | `order.view`<br>`catalog.view` | `inventory-service`<br>`catalog-service`<br>`sales-service` | **Báo giá sỉ & Check kho nhanh**: Tra tồn khả dụng, tự động áp chính sách chiết khấu theo phân hạng đại lý $\rightarrow$ Trả về giá chốt bán sỉ kèm nút `[Lập Báo giá / SO]`. | ✅ **HOÀN THÀNH (PILOT)** |
| **12** | `smart_margin_guard` | `order.create`<br>`report.view` | `catalog-service`<br>`sales-service`<br>`FactSales` (Giá vốn) | **Bảo vệ biên lãi & Đàm phán giá**: Khi khách mặc cả, kiểm tra sàn biên lãi gộp $\rightarrow$ Cảnh báo nguy cơ bán lỗ, gợi ý tăng số lượng để bù lãi. | ✅ **TÍCH HỢP TRONG TOOL 11** |
| **13** | `create_sales_order_nlp` | `order.create` | `sales-service`<br>`catalog-service`<br>LLM Entity Extraction | **Lên đơn bán hàng từ câu chat/voice**: Đọc văn bản tự nhiên $\rightarrow$ Tự bóc tách khách hàng, mã hàng, số lượng $\rightarrow$ Trả về thẻ xem trước đơn kèm nút `[Xác nhận SO]`. | 🚀 Sprint 2 |
| **14** | `suggest_accessory_bundle` | `catalog.view` | `catalog-service`<br>`inventory-service`<br>Association Rules | **Gợi ý bán kèm phụ kiện biên lãi cao**: Khi lên đơn điều hòa/tủ lạnh, tự gợi ý combo (ống đồng, giá treo, aptomat) có lãi gộp 25-35% kèm nút `[➕ Thêm vào Đơn]`. | 🚀 Sprint 2 |
| **15** | `at_risk_winback_action` | `customer.view`<br>`order.create` | `sales-service`<br>`ml.py` (K-Means RFM: At-Risk) | **Kích hoạt đại lý nguy cơ rời bỏ**: Lọc khách hàng VIP/Loyal $>60$ ngày chưa mua $\rightarrow$ Đề xuất kịch bản chăm sóc (tặng voucher chiết khấu 2%) kèm nút `[Gửi Tri Ân]`. | 🚀 Sprint 2 |

#### 📊 PHÂN HỆ 4: TÀI CHÍNH, KẾ TOÁN & CÔNG NỢ (FINANCE & CREDIT)
| STT | Mã Tool MCP | Quyền hạn RBAC | Dịch vụ & ML liên kết | Mô tả nghiệp vụ & Giao diện tương tác (Generative UI) | Trạng thái |
| :-: | :--- | :--- | :--- | :--- | :---: |
| **16** | `credit_risk_precheck` | `finance.view` | `finance-service`<br>`ml.py` (Credit Risk Scoring) | **Thẩm định tín dụng & Kiểm tra nợ xấu**: Kiểm tra hạn mức nợ còn trống và số ngày quá hạn trước khi cho phép xuất kho $\rightarrow$ Đề xuất nới hạn mức hoặc chặn đơn hàng. | 🚀 Sprint 3 |
| **17** | `receivables_aging_audit` | `finance.view` | `finance-service`<br>`receivable` Table | **Kiểm toán phân bổ tuổi nợ (Aging Report)**: Báo cáo chi tiết công nợ theo các xô 0-30 ngày, 31-60 ngày, 61-90 ngày và trên 90 ngày. | 🚀 Sprint 3 |
| **18** | `suggest_credit_action` | `finance.create` | `finance-service`<br>`sales-service` | **Đề xuất hành động xử lý công nợ**: Tự động sinh danh sách hành động (gửi email nhắc nợ tự động, chuyển hình thức sang COD tiền mặt, chuyển pháp chế). | 🚀 Sprint 3 |
| **19** | `cash_flow_forecast_30d` | `finance.view`<br>`report.view` | `finance-service`<br>`purchase-service`<br>`sales-service` | **Dự báo dòng tiền vào ra 30 ngày tới**: Đối chiếu giữa lịch thu tiền từ hóa đơn bán hàng đến hạn với lịch thanh toán cho các đơn mua PO. | 🚀 Sprint 3 |
| **20** | `ledger_subledger_reconcile` | `finance.view` | `finance-service`<br>`inventory-service` | **Tự động đối soát Sổ cái ↔ Sổ chi tiết**: Kiểm tra đối chiếu chéo số dư TK 156 (Tồn kho) với Thẻ kho, TK 131 (Phải thu) với Báo cáo công nợ. | 🚀 Sprint 4 |

#### 🏛️ PHÂN HỆ 5: ĐIỀU HÀNH, BAN GIÁM ĐỐC & E-OFFICE (EXECUTIVE & OPERATIONS)
| STT | Mã Tool MCP | Quyền hạn RBAC | Dịch vụ & ML liên kết | Mô tả nghiệp vụ & Giao diện tương tác (Generative UI) | Trạng thái |
| :-: | :--- | :--- | :--- | :--- | :---: |
| **21** | `executive_root_cause_bi` | `SUPER_ADMIN`<br>`report.view` | `ai-service`<br>`FactSales` Data Warehouse | **Phân tích nguyên nhân biến động tài chính**: Trả lời câu hỏi *"Tại sao doanh thu tuần này giảm nhưng lợi nhuận lại tăng?"* bằng phân tích cơ cấu nhóm hàng. | 🚀 Sprint 4 |
| **22** | `demand_forecast_ml` | `report.view` | `ai-service`<br>`ml.py` (Holt-Winters) | **Dự báo chuỗi thời gian doanh thu & nhu cầu**: Dự báo theo ngày trong 30 ngày tới kèm dải tin cậy 95%, phân tích quy luật sóng tuần. | ✅ **ĐÃ TÍCH HỢP** |
| **23** | `rfm_customer_segmentation_ml` | `customer.view`<br>`report.view` | `ai-service`<br>`ml.py` (K-Means k=4) | **Phân cụm khách hàng RFM Machine Learning**: Tự động phân chia 2.591 khách hàng thành 4 nhóm kèm Silhouette Score định lượng chất lượng cụm. | ✅ **ĐÃ TÍCH HỢP** |
| **24** | `eoffice_doc_summarizer_dispatch` | `ai.use` | `eoffice-service`<br>LLM Document Summarizer | **Tóm tắt tờ trình & Đề xuất tuyến phê duyệt**: Đọc nội dung hợp đồng / công văn nội bộ $\rightarrow$ Tóm tắt 3 ý chính, gợi ý người ký duyệt phù hợp. | 🚀 Sprint 4 |

---

## 4. CHI TIẾT KẾT QUẢ ĐÃ HOÀN THÀNH Ở LUỒNG THÍ ĐIỂM (PILOT PHASE SUMMARY)

Triển khai thí điểm hoàn chỉnh cho **Tool 1 (`procurement_fast_order`)** và **Tool 11/12 (`instant_quote_check` & `smart_margin_guard`)** đã hoàn tất trọn vẹn cả 4 giai đoạn kỹ thuật:

```text
┌────────────────────────┐     ┌────────────────────────┐     ┌────────────────────────┐     ┌────────────────────────┐
│  GIAI ĐOẠN 1: API DTO  │ ──► │  GIAI ĐOẠN 2: ML MODEL │ ──► │  GIAI ĐOẠN 3: COPILOT  │ ──► │  GIAI ĐOẠN 4: GEN-UI   │
│   Backend C# & Laravel │     │ Holt-Winters in ml.py  │     │ tools.py & agent.py    │     │ Action Cards Vue 3     │
│       [x] HOÀN TẤT     │     │      [x] HOÀN TẤT      │     │      [x] HOÀN TẤT      │     │      [x] HOÀN TẤT      │
└────────────────────────┘     └────────────────────────┘     └────────────────────────┘     └────────────────────────┘
```

---

### GIAI ĐOẠN 1: CHUẨN HÓA API CONTRACTS VÀ SERVICE INTEGRATION (✅ ĐÃ HOÀN THÀNH)
* **Purchase Service (C# .NET 10)**:
  - Chuẩn hóa `CreatePurchaseOrderDto` trong `Purchase.Application/DTOs/PurchaseOrders/PurchaseOrderDtos.cs` nhận `supplier_id`, `warehouse_id`, `items` (`product_id`, `sku`, `unit_cost`, `quantity_ordered`, `tax_rate`).
  - Endpoint `POST /api/v1/purchase/orders` hỗ trợ tạo Draft PO có xác thực JWT Bearer và Correlation-ID.
* **Sales Service (PHP Laravel 11)**:
  - Chuẩn hóa `SalesOrderController.php` nhận `customer_id`, `items` (`product_id`, `sku`, `selling_price`, `quantity`, `vat_percent`).
  - Chuẩn hóa `QuotationController.php` nhận báo giá B2B.

---

### GIAI ĐOẠN 2: TÍCH HỢP MACHINE LEARNING TẠI `services/ai-service/app/ml.py` (✅ ĐÃ HOÀN THÀNH)
* Bổ sung hàm `get_sku_procurement_ml_insight(sku, available_stock, min_stock)`:
  - Kết hợp mô hình Holt-Winters dự báo nhu cầu 30 ngày với tốc độ bán bình quân 90 ngày gần nhất từ kho phân tích.
  - Công thức SCM Safety Stock tính số lượng nhập khuyến nghị:
    $$\text{Target} = \text{Demand}_{30d} + \text{MinStock}; \quad \text{SuggestedQty} = \max(0, \text{Target} - \text{AvailableStock})$$
  - Trích xuất biên lãi gộp lịch sử và đánh giá mức độ cấp bách (`CRITICAL`, `WARNING`, `NORMAL`).

---

### GIAI ĐOẠN 3: XÂY DỰNG TOOL COPILOT VÀ PROMPT SYSTEM (✅ ĐÃ HOÀN THÀNH)
* **Quy tắc Nghiêm ngặt (Strict Full-Code Traceability & Zero-Hardcode)**:
  - Loại bỏ hoàn toàn số liệu hardcode mẫu trong `SYSTEM_PROMPT`. 100% tính toán động từ database.
  - Bắt buộc nhập chính xác **MÃ CODE SKU ĐẦY ĐỦ** (ví dụ: `RT38CG6584B1SV-SAM`, `CFS-13VDM-COM`, `DAIKIN-FTKF25XVMV`) và **MÃ KHÁCH HÀNG / NCC ĐẦY ĐỦ** (`DI-14998`, `DE-67750`, `NCC-008`). Chặn hoàn toàn mã rút gọn hoặc từ khóa mơ hồ.
* **Tool `procurement_fast_order`**:
  - Tra cứu giá 4 NCC gần nhất, tự động đánh dấu `is_best_price: True` cho NCC có giá nhập tốt nhất.
  - Đóng gói `action_schema` chứa `can_execute = actor.can("purchase.create")`.
* **Tool `instant_quote_check`**:
  - Báo giá sỉ theo phân hạng đối tác: Distributor (18%), Dealer (12%), Project (10%), Retail (0%) + Thưởng số lượng volume bonus (+2%).
  - Tự động thẩm định tín dụng từ bảng `receivable`: phát hiện nợ quá hạn và nợ vượt hạn mức tín dụng (`credit_limit`).
  - Bảo vệ biên lãi gộp (`smart_margin_guard`): Cảnh báo nếu lãi $<5\%$ và chặn báo động `"LO_VON"` nếu đơn giá bán thấp hơn giá vốn.
  - Đóng gói `action_schema` chứa `can_execute = actor.can("order.create")`.
* **Kiểm thử Unit Test Backend**: 9/9 Test cases đạt 100% tại `scratch/test_instant_quote.py`.

---

### GIAI ĐOẠN 4: THIẾT KẾ GENERATIVE UI ACTION CARDS TRÊN VUE 3 (✅ ĐÃ HOÀN THÀNH)
* **`frontend/src/components/ai/ProcurementActionCard.vue`**:
  - Header hiển thị SKU, Tên sản phẩm, Tồn khả dụng.
  - Banner ML gợi ý số lượng đặt mua từ Holt-Winters.
  - Form chọn Nhà cung cấp (kèm giá, kỳ hạn nợ, biên lãi, nhãn `★ GIÁ TỐT NHẤT`).
  - Ô chỉnh số lượng, tự động tính tổng tiền gồm VAT 10%.
  - Nút `[🚀 Xác nhận Tạo Đơn Mua (Draft PO)]` liên kết `purchaseApi.createOrder`.
  - Tự động khóa và hiển thị icon `🔒` nếu tài khoản thiếu quyền `purchase.create`.
* **`frontend/src/components/ai/SalesActionCard.vue`**:
  - Header hiển thị Mã KH, Tên KH, Phân hạng đại lý, SKU.
  - Bộ 3 Badge rủi ro thời gian thực: Tồn kho, Hạn mức tín dụng / nợ quá hạn, Biên lãi gộp.
  - Tương tác chỉnh số lượng và % chiết khấu đàm phán, tự động tính đơn giá chốt, VAT 8% và tổng thanh toán.
  - 2 Nút hành động: `[🚀 Tạo Đơn Bán Hàng (Draft SO)]` và `[📑 Lập Báo Giá (Quotation)]`.
  - Tự động khóa nếu tài khoản thiếu quyền `order.create`.
* **Tích hợp Store & View**:
  - `frontend/src/stores/aiStore.js`: Bổ sung lưu trữ `action_schema` trong tin nhắn.
  - `frontend/src/components/ai/AiConversationView.vue`: Nhúng Action Cards tự động, hỗ trợ đóng/hủy, bổ sung `TOOL_LABELS`.
* **Vite Build**: Lệnh `npm --prefix frontend run build` chuyển đổi thành công 856 modules không lỗi.

---

## 5. CÁC BƯỚC TIẾP THEO CẦN KIỂM TRA (CHECKLIST KIỂM THỬ THỰC TẾ & NGHIỆM THU)

Sau khi hoàn tất toàn bộ luồng Pilot, các bước tiếp theo cần được kiểm tra thực tế (End-to-End Verification) trên trình duyệt trước khi mở rộng sang các phân hệ khác:

### 📋 Checklist Tự vấn Kiến trúc Clean Architecture
- [x] **1. Logic đặt ở đúng tầng Clean Architecture**: Nghiệp vụ tạo đơn PO/SO nằm trong Application Service của C# và Laravel. `ai-service` chỉ đóng vai trò Orchestrator gợi ý.
- [x] **2. Hướng phụ thuộc một chiều**: UI gọi Gateway REST API, không gọi chéo trực tiếp database.
- [x] **3. Dễ dàng viết Unit Test**: `procurement_fast_order` và `instant_quote_check` nhận `Actor` và `args`, trả về dictionary thuần.
- [x] **4. Không lộ Domain Entity**: Trả về Action Schema là DTO thuần túy đã lọc dữ liệu nhạy cảm.
- [x] **5. Múi giờ chuẩn UTC & Format Tiền tệ VNĐ**: Chuẩn ISO-8601 UTC và format `Intl.NumberFormat('vi-VN')`.
- [x] **6. Zero-Hardcode & Dynamic Computation**: 100% số liệu lấy từ database và mô hình ML.
- [x] **7. Strict Code Traceability**: Chặn toàn bộ mã mờ, bắt buộc mã SKU và mã khách hàng đầy đủ.

---

### 🧪 Bộ 6 Kịch bản Kiểm thử Toàn diện trên Trình duyệt (End-to-End Test Scenarios)

#### 🔹 Kịch bản 1: Luồng Mua Hàng Nhanh (Procurement Action Card $\rightarrow$ Draft PO)
- [ ] **Bước 1.1**: Đăng nhập tài khoản Thu mua (`purchase@erp.local` có quyền `purchase.create`).
- [ ] **Bước 1.2**: Mở Chat Assistant, gửi prompt:
  > *"Tra cứu nguồn hàng và lên kế hoạch nhập hàng nhanh cho sản phẩm RT38CG6584B1SV-SAM"*
- [ ] **Bước 1.3**: Kiểm tra phản hồi: AI trích xuất phân tích ML (nhu cầu 30 ngày, khuyến nghị số lượng nhập) và hiển thị thẻ `ProcurementActionCard`.
- [ ] **Bước 1.4**: Trên thẻ tương tác, chọn Nhà cung cấp giá tốt nhất, điều chỉnh số lượng thành `15` cái.
- [ ] **Bước 1.5**: Bấm nút `[🚀 Xác nhận Tạo Đơn Mua (Draft PO)]`.
- [ ] **Bước 1.6**: Xác nhận Toast thành công hiển thị, thẻ chuyển sang trạng thái đã tạo đơn kèm mã PO. Mở màn hình `/purchase/orders` kiểm tra bản ghi đơn mua mới được tạo ở trạng thái `Draft` hoặc `PendingApproval`.

#### 🔹 Kịch bản 2: Luồng Báo Giá Sỉ & Lên Đơn Bán Hàng (Sales Action Card $\rightarrow$ Draft SO / Quotation)
- [ ] **Bước 2.1**: Đăng nhập tài khoản Kinh doanh (`sale@erp.local` có quyền `order.create`).
- [ ] **Bước 2.2**: Mở Chat Assistant, gửi prompt:
  > *"Báo giá sỉ 10 bộ điều hòa CFS-13VDM-COM cho đại lý DI-14998"*
- [ ] **Bước 2.3**: Kiểm tra phản hồi: AI tự động áp dụng chiết khấu Distributor $18\%$, tính đơn giá $7.371.800$ đ, thuế VAT 8%, hiển thị thẻ `SalesActionCard` với 3 Badge xanh (Tồn kho đủ, Hạn mức nợ an toàn, Biên lãi $13.5\%$).
- [ ] **Bước 2.4**: Bấm nút `[🚀 Tạo Đơn Bán Hàng (Draft SO)]`.
- [ ] **Bước 2.5**: Kiểm tra Toast thành công hiển thị, thẻ ghi nhận mã chứng từ đơn bán. Mở màn hình `/sales/orders` thấy đơn bán hàng vừa tạo với đầy đủ thông tin khách hàng và sản phẩm.

#### 🔹 Kịch bản 3: Kiểm thử Phòng vệ Truy vết Mã (Traceability Guardrail)
- [ ] **Bước 3.1**: Trong khung chat, hỏi câu mơ hồ:
  > *"Báo giá cho tôi máy giặt toshiba với"* hoặc *"Lập đơn mua cho mã CFS-13"*
- [ ] **Bước 3.2**: Xác nhận AI **từ chối** trả lời qua loa, yêu cầu người dùng cung cấp chính xác **Mã code SKU đầy đủ** và **Mã khách hàng đầy đủ** để đảm bảo tính truy vết chứng từ ERP.

#### 🔹 Kịch bản 4: Kiểm thử Thẩm định Tín dụng & Cảnh báo Nợ Quá Hạn
- [ ] **Bước 4.1**: Gửi prompt yêu cầu báo giá cho khách hàng có nợ quá hạn thực tế:
  > *"Báo giá 5 bộ CFS-13VDM-COM cho khách hàng DI-71173"*
- [ ] **Bước 4.2**: Xác nhận AI phát hiện khách hàng có **212,9 triệu đồng nợ quá hạn**.
- [ ] **Bước 4.3**: Thẻ `SalesActionCard` hiển thị Badge đỏ: `[Có nợ quá hạn hoặc vượt hạn mức]` để nhắc nhở nhân viên kinh doanh đôn đốc thu hồi nợ trước khi giao hàng.

#### 🔹 Kịch bản 5: Kiểm thử Bảo vệ Biên Lợi Nhuận Gộp (Smart Margin Guard)
- [ ] **Bước 5.1**: Trên thẻ `SalesActionCard` của mã `CFS-13VDM-COM` (giá vốn $6.377.000$ đ), chỉnh tỷ lệ chiết khấu thương lượng lên `35%`.
- [ ] **Bước 5.2**: Đơn giá sau chiết khấu giảm xuống $5.843.500$ đ (thấp hơn giá vốn).
- [ ] **Bước 5.3**: Xác nhận thẻ lập tức chuyển sang Badge đỏ cảnh báo: `[LO_VON: Bán lỗ -9.1%]` để ngăn ngừa rủi ro thất thoát doanh thu.

#### 🔹 Kịch bản 6: Kiểm thử Phân quyền Hạt mịn RBAC (Fine-Grained Security)
- [ ] **Bước 6.1**: Đăng nhập với tài khoản Kỹ thuật (`tech@erp.local`) hoặc tài khoản chỉ có quyền xem báo cáo (thiếu `purchase.create` và `order.create`).
- [ ] **Bước 6.2**: Yêu cầu AI tra cứu nguồn hàng và báo giá.
- [ ] **Bước 6.3**: Xác nhận thẻ Action Card vẫn hiển thị thông tin để xem trước (Preview), nhưng các nút `[Tạo Đơn]` bị vô hiệu hóa (disabled) kèm icon `🔒` và dòng thông báo yêu cầu cấp quyền.

---

## 6. LỘ TRÌNH CÁC SPRINT TIẾP THEO (POST-PILOT SPRINT ROADMAP)

Sau khi nghiệm thu luồng Pilot thành công, toàn bộ 24 công cụ MCP sẽ được triển khai mở rộng theo 4 Sprint:

```text
┌───────────────────────┐    ┌───────────────────────┐    ┌───────────────────────┐    ┌───────────────────────┐
│        SPRINT 1       │ ──►│        SPRINT 2       │ ──►│        SPRINT 3       │ ──►│        SPRINT 4       │
│  Kho vận & Tồn kho    │    │  Bán hàng & Đàm phán  │    │  Tài chính & Đối soát │    │ Chuẩn MCP & Langfuse  │
│  (Tools 02,06,07,08)  │    │  (Tools 13,14,15)     │    │  (Tools 05,16,18,19)  │    │ (Tools 21,24, Server) │
└───────────────────────┘    └───────────────────────┘    └───────────────────────┘    └───────────────────────┘
```

### 🚀 Sprint 1: Kho vận & Quản trị tồn kho thông minh (Logistics & Inventory Intelligence)
- [ ] **Tool 02 (`smart_restock_plan`)**: Quét mã hàng $\text{Available} \le \text{MinStock}$, tính tốc độ bán $\rightarrow$ Cảnh báo thiếu hàng kèm nút `[Tạo PO khuyến nghị]`.
- [x] **Tool 06 (`stock_card_trace`)**: Truy vết thẻ kho theo dòng thời gian theo từng số phiếu nhập/xuất và nhân viên thực hiện.
- [ ] **Tool 07 (`slow_moving_deadstock_radar`)**: Nhận diện hàng tồn kho $>90$ ngày không phát sinh đơn bán $\rightarrow$ Đề xuất xả hàng.
- [ ] **Tool 08 (`inter_warehouse_transfer_plan`)**: Phân tích kho thừa và kho thiếu cục bộ $\rightarrow$ Trả về thẻ điều chuyển liên kho kèm nút `[Tạo Phiếu Xuất Điều Chuyển]`.

### 🚀 Sprint 2: Bán hàng B2B Nâng cao & Phân tích Đàm phán (Advanced Sales & Negotiation)
- [ ] **Tool 13 (`create_sales_order_nlp`)**: Lên đơn bán hàng từ câu chat/voice tự do $\rightarrow$ Bóc tách thực thể khách hàng, mã hàng, số lượng.
- [ ] **Tool 14 (`suggest_accessory_bundle`)**: Gợi ý combo phụ kiện biên lãi cao ($25-35\%$) khi mua điều hòa/tủ lạnh kèm nút `[➕ Thêm vào Đơn]`.
- [ ] **Tool 15 (`at_risk_winback_action`)**: Kích hoạt đại lý nguy cơ rời bỏ $\rightarrow$ Đề xuất voucher chiết khấu $2\%$ kèm nút `[Gửi Tri Ân]`.
- [ ] **Tool 03 (`supplier_scorecard_evaluate`)**: Đánh giá SLA nhà cung cấp (hiện có một phần trong `purchase_report` chế độ `supplier_performance`).
- [x] **Tool 04 (`purchase_price_variance_alert`)**: Cảnh báo biến động giá mua bất thường.

### 🚀 Sprint 3: Tài chính, Công nợ & Đối soát 3 bên (Finance, Credit & 3-Way Reconciliation)
- [ ] **Tool 05 (`resolve_matching_discrepancy`)**: Đối soát 3 bên Đơn mua PO $\leftrightarrow$ Phiếu nhập kho GRN $\leftrightarrow$ Hóa đơn VAT, tự sinh biên bản chênh lệch.
- [ ] **Tool 16 (`credit_risk_precheck`)**: Thẩm định tín dụng và kiểm tra nợ xấu trước khi cho phép xuất kho.
- [ ] **Tool 18 (`suggest_credit_action`)**: Đề xuất hành động xử lý công nợ (tạm khóa đơn, chuyển COD tiền mặt, chuyển pháp chế).
- [ ] **Tool 19 (`cash_flow_forecast_30d`)**: Dự báo dòng tiền thu - chi 30 ngày tới dựa trên lịch thanh toán PO và lịch thu nợ hóa đơn.

### 🚀 Sprint 4: Chuẩn hóa Giao thức MCP Server, Observability & Điều hành (Executive & MCP)
- [ ] **MCP Server Adapter (`/mcp/sse`)**: Đóng gói toàn bộ 24 công cụ và Resources chuẩn Model Context Protocol cho Claude Desktop, Antigravity IDE, Cursor.
- [ ] **Tool 21 (`executive_root_cause_bi`)**: Phân tích nguyên nhân biến động tài chính phục vụ Ban Giám Đốc và Hội đồng chấm đồ án.
- [ ] **Tool 24 (`eoffice_doc_summarizer_dispatch`)**: Tóm tắt tờ trình hợp đồng và đề xuất tuyến phê duyệt điện tử E-Office.
- [ ] **Observability & Tracing**: Tích hợp Langfuse SDK vào `ai-service` để giám sát latency, token in/out và audit log hành động Agent.

---

## 7. BẢNG PHÂN CÔNG CỔNG VÀ GIAO TIẾP HỆ THỐNG TRONG SMART-ERP

| Dịch vụ | Cổng | Công nghệ | Nhiệm vụ chính trong Hệ sinh thái Agentic ERP |
| :--- | :---: | :--- | :--- |
| **`ai-service`** | **8010** | Python FastAPI | LLM Agent Core, 20 tool (lộ trình 24), Scikit-Learn ML Models, MySQL Analytics (`erp_analytics_db`) |
| **`identity-service`** | **8001** | PHP Laravel | Xác thực JWT (HS256), Quản trị người dùng & Phân quyền RBAC |
| **`catalog-service`** | **8002** | PHP Laravel | Danh mục sản phẩm, Phân loại ngành hàng, Bảng giá niêm yết, Khách hàng, Nhà cung cấp |
| **`purchase-service`** | **8003** | C# .NET 10 | Quản lý Đơn mua hàng (PO), Đề nghị mua sắm (PR), Kiểm nhận hàng (GRN) |
| **`inventory-service`** | **8004** | C# .NET 10 | Quản lý Tồn kho thực tế, Giữ chỗ (Reservation), Thẻ kho, Điều chuyển |
| **`sales-service`** | **8005** | PHP Laravel | Quản lý Đơn bán hàng (SO), Báo giá (Quotation), Giao hàng (Delivery) |
| **`notification-service`**| **8006** | Node.js | Quả chuông thông báo cá nhân (Inbox Bell) & Email Dispatcher |
| **`eoffice-service`** | **8007** | PHP Laravel | Văn phòng điện tử: tờ trình, phê duyệt, chấm công |
| **`finance-service`** | **8008** | PHP Laravel | Công nợ phải thu, Thu chi, Sổ cái |
| **`chat-service`** | **8009** | C# .NET 10 SignalR | Quản lý kênh chat nội bộ phòng ban & Realtime Bot Worker |
| **`api-gateway`** | **8000** | Kong (DB-less) | Định tuyến API tập trung, Rate Limiting, Correlation-ID Tracking |
| **`frontend`** | **5175** | Vue 3 + PrimeVue | Giao diện Single Page Application (SPA), Generative UI Action Cards |
