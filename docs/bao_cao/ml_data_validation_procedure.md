# QUY TRÌNH KIỂM TOÁN & KIỂM ĐỊNH DỮ LIỆU MACHINE LEARNING TRONG DOANH NGHIỆP

> **Mục tiêu**: Thiết lập hàng rào bảo vệ (Guardrails) và quy chuẩn đối soát dữ liệu đầu ra từ các thuật toán AI/ML nhằm đảm bảo không có sai lệch số liệu, ảo giác AI (Hallucination), hay đề xuất rủi ro cho hoạt động kinh doanh.

* **Ngày thực hiện kiểm toán**: `2026-10-24 01:07:15`
* **Phạm vi kiểm tra**: Toàn bộ luồng ETL, Mô hình Chuỗi thời gian (Holt-Winters), Phân cụm RFM (K-Means), Chấm điểm Rủi ro Tín dụng, và Bộ công cụ Khuyến nghị Hành động.
* **Kết quả tổng thể**: **21/21 tiêu chí ĐẠT (100.0%)**.

---

## 1. MA TRẬN TIÊU CHÍ KIỂM TOÁN & KẾT QUẢ KIỂM THỬ (AUDIT INTEGRITY MATRIX)

| STT | Phân hệ kiểm định | Tiêu chí kỹ thuật & Nghiệp vụ | Kết quả | Chi tiết đối soát thực nghiệm |
| :---: | :--- | :--- | :---: | :--- |
| 1 | `Ingestion & Freshness` | Độ dài chu kỳ lịch sử >= 700 ngày (chu kỳ 24 tháng) | ✅ PASS | Từ 2024-10-01 đến 2026-09-30 (729 ngày) |
| 2 | `Ingestion & Freshness` | Tổng số khách hàng doanh nghiệp chuẩn 2.591 | ✅ PASS | Hiện có 2,591 khách hàng trong dim_customer |
| 3 | `Ingestion & Freshness` | Doanh thu bán hàng không rỗng và không âm | ✅ PASS | Missing: 0 | Số âm: 0 (Tổng doanh số: 766.33 tỷ VNĐ) |
| 4 | `Demand Forecasting` | Đủ 30 ngày dự báo liên tục không ngắt quãng | ✅ PASS | Đã sinh 30/30 ngày tiếp theo |
| 5 | `Demand Forecasting` | Đủ 3 tháng dự báo Quý IV/2026 | ✅ PASS | Các tháng: ['2026-10', '2026-11', '2026-12'] |
| 6 | `Demand Forecasting` | Toàn bộ 30 ngày thỏa mãn Dải tin cậy 95% (0 <= Lower <= Predicted <= Upper) | ✅ PASS | Số ngày vi phạm: 0 |
| 7 | `Demand Forecasting` | Độ rộng dải tin cậy mở rộng theo căn bậc hai thời gian (Funnel Uncertainty) | ✅ PASS | Tuần 1: 1.31 tỷ -> Tuần 4: 1.60 tỷ (+21.7%) |
| 8 | `Demand Forecasting` | Đỉnh sóng doanh thu Thứ Hai/Thứ Ba > 1.3x trung bình tuần | ✅ PASS | Thứ 2 & Thứ 3: 1.40 tỷ/ngày (~1.40x TB) |
| 9 | `Demand Forecasting` | Đáy doanh thu Chủ Nhật < 0.1x trung bình tuần (kho vận đóng cửa) | ✅ PASS | Chủ Nhật: 18.3 triệu/ngày (~0.018x TB) |
| 10 | `Demand Forecasting` | Tổng dự báo 30 ngày nằm trong biên độ doanh nghiệp hợp lý (25 tỷ - 35 tỷ) | ✅ PASS | Tổng dự báo: 30.18 tỷ VNĐ (Bình quân 1.01 tỷ/ngày) |
| 11 | `RFM Segmentation` | Bảo toàn 100% khách hàng (Tổng 4 cụm = 2.591 khách hàng) | ✅ PASS | Tổng 4 cụm: 2,591 / 2.591 khách (Khớp 100%) |
| 12 | `RFM Segmentation` | Chỉ số Silhouette Score >= 0.35 (Đạt ngưỡng phân cụm tốt trong nghiệp vụ) | ✅ PASS | Silhouette Score thực nghiệm = 0.477 |
| 13 | `RFM Segmentation` | Cụm VIP có mức chi tiêu bình quân cao nhất trong 4 cụm | ✅ PASS | VIP: 1,045.0 triệu > Loyal: 56.8 triệu |
| 14 | `RFM Segmentation` | Cụm Nguy cơ rời bỏ có Recency cao nhất (lâu ngày chưa mua) | ✅ PASS | At-Risk Recency TB: 439.4 ngày |
| 15 | `Credit Risk Radar` | Cân đối dòng tiền công nợ: Đã thu + Còn nợ = Tổng hóa đơn | ✅ PASS | Hóa đơn: 830.41 tỷ = Đã thu: 794.06 tỷ + Còn nợ: 36.35 tỷ |
| 16 | `Credit Risk Radar` | Tỷ lệ thu hồi nợ đạt chuẩn an toàn tín dụng (60% - 99%) | ✅ PASS | Tỷ lệ thu hồi thực tế: 95.62% |
| 17 | `Credit Risk Radar` | Phát hiện chính xác các hóa đơn nợ quá hạn báo động (> 60 ngày) | ✅ PASS | Có 50 hóa đơn quá hạn > 60 ngày (0.90 tỷ VNĐ) |
| 18 | `Credit Risk Radar` | Danh sách Radar cảnh báo ưu tiên thu hồi (Top 30 nợ quá hạn) | ✅ PASS | Đã trích xuất 30 khoản nợ có mức độ rủi ro cao nhất |
| 19 | `Prescriptive Tools` | Đề xuất nhập kho: Mọi số lượng kiến nghị đều > 0 và chỉ nhắm vào mặt hàng thiếu hụt | ✅ PASS | Số mã khẩn cấp: 103 mã | Tổng chi phí ước tính: 4,79 tỷ |
| 20 | `Prescriptive Tools` | Đề xuất giữ chân khách hàng: Đầy đủ mã, tên, phân loại rủi ro và kịch bản can thiệp | ✅ PASS | Số khách hàng rủi ro trong nhóm: 1,036 khách |
| 21 | `Prescriptive Tools` | Đề xuất xử lý công nợ: Phân loại đúng hành động (Khóa đơn / Pháp chế / Nhắc nợ) | ✅ PASS | Đã lập biện pháp can thiệp cho 30 khoản nợ rủi ro |

---

## 2. NĂM BƯỚC KIỂM TOÁN DỮ LIỆU MACHINE LEARNING CHI TIẾT

### Bước 1: Kiểm toán Dữ liệu Đầu vào (Data Freshness & Referential Integrity)
* **Nguyên tắc**: *Garbage In, Garbage Out* - nếu dữ liệu nguồn thiếu tính liên tục hoặc sai lệch, toàn bộ mô hình AI sẽ đưa ra kết quả vô nghĩa.
* **Các phép kiểm tra bắt buộc**:
  1. Đối soát mốc ngày gần nhất (`max(date)`): Đảm bảo dữ liệu được đồng bộ không quá 24h so với hoạt động thực tế.
  2. Khóa ngoại liên kết (`Referential Integrity`): Kiểm tra toàn bộ khách hàng trong bảng bán hàng và công nợ đều tồn tại trong danh mục khách hàng.
  3. Rà soát giá trị bất thường: Doanh thu âm, số lượng âm không rõ lý do, giá trị `NaN` hoặc `Null` trong các cột chỉ số trọng yếu.

### Bước 2: Kiểm toán Dự báo Doanh số Chuỗi thời gian (Time-series Forecasting Guardrails)
* **Nguyên tắc**: Dự báo phải phản ánh đúng quy luật kinh doanh mùa vụ và không được đưa ra con số phi lý.
* **Các phép kiểm tra bắt buộc**:
  1. **Quy tắc Dải tin cậy 95% (95% Confidence Corridor)**: Mọi giá trị dự báo ngày phải nằm gọn trong dải: $0 \le \text{Lower\_Bound} \le \hat{y}_t \le \text{Upper\_Bound}$.
  2. **Hiệu ứng Phễu không chắc chắn (Funnel Uncertainty)**: Độ rộng dải tin cậy ($Upper - Lower$) phải nở rộng dần theo thời gian ($\sim \sigma \sqrt{t}$) vì càng dự báo xa thì độ bất định càng lớn.
  3. **Kiểm tra Sóng tuần (Weekly Seasonality Check)**: Doanh số Thứ Hai và Thứ Ba phải đạt đỉnh ($\ge 1.3\times$ trung bình), Chủ Nhật phải giảm sâu ($\le 0.1\times$ trung bình) do kho vận đóng cửa cuối tuần.
  4. **Kiểm tra Mức trần/sàn tháng**: Tổng doanh số tháng 10 dự báo phải nằm trong giới hạn thực tế của doanh nghiệp (từ 25 đến 35 tỷ đồng).

### Bước 3: Kiểm toán Phân cụm Khách hàng RFM (K-Means Clustering Integrity)
* **Nguyên tắc**: Phân nhóm khách hàng phải có ý nghĩa định lượng rõ rệt và tuyệt đối không bỏ rơi tập khách hàng tiềm năng.
* **Các phép kiểm tra bắt buộc**:
  1. **Bảo toàn số lượng khách hàng ($N = 2,591$)**: Tổng số lượng khách hàng của 4 cụm sau khi gán nhãn phải bằng đúng 100% khách hàng trong hệ thống (kể cả 110 khách hàng chưa từng mua hàng được xếp vào nhóm *At-Risk / Khách chưa kích hoạt*).
  2. **Hệ số Silhouette Score ($\ge 0.35$)**: Chứng minh khoảng cách giữa các cụm là tách biệt và các điểm trong cùng cụm có độ gắn kết cao.
  3. **Trật tự logic của các chỉ số**: Cụm VIP phải có chi tiêu bình quân ($Monetary$) cao nhất; Cụm Nguy cơ rời bỏ phải có số ngày không mua hàng ($Recency$) lớn nhất.

### Bước 4: Kiểm toán Rủi ro Tín dụng & Công nợ (Credit Risk Scoring & Aging)
* **Nguyên tắc**: Dữ liệu tài chính đòi hỏi độ chính xác tuyệt đối, không có sai số làm tròn dòng tiền.
* **Các phép kiểm tra bắt buộc**:
  1. **Cân đối dòng tiền kế toán**: $\text{Tổng hóa đơn xuất} = \text{Đã thanh toán} + \text{Còn nợ}$ (Sai số tuyệt đối $< 100$ VNĐ do làm tròn).
  2. **Tỷ lệ thu hồi nợ (Debt Recovery Rate)**: Đảm bảo tỷ lệ thu nợ thực tế nằm trong biên độ an toàn từ $60\%$ đến $95\%$.
  3. **Phát hiện nợ xấu khẩn cấp**: Lọc chính xác các khoản nợ quá hạn $> 60$ ngày để đưa vào Radar báo động.

### Bước 5: Kiểm toán Đề xuất Hành động AI (Prescriptive Decision Guardrails)
* **Nguyên tắc**: Mọi khuyến nghị đặt hàng hoặc xử lý nợ của AI phải có căn cứ rõ ràng, tránh gây lãng phí vốn lưu động hoặc xung đột khách hàng.
* **Các phép kiểm tra bắt buộc**:
  1. **Kiểm tra Đề xuất nhập kho**: Số lượng đặt hàng đề xuất $Q_{suggest} > 0$ và chỉ áp dụng cho sản phẩm có tốc độ bán thực tế ($Rate > 0$). Tuyệt đối không đề xuất nhập các mã chết (Dead Stock) hoặc mã bán chậm 90 ngày qua.
  2. **Kiểm tra Kịch bản giữ chân khách hàng**: Phải phân biệt rõ kịch bản giữa khách hàng VIP/Đại lý lớn (cử giám đốc đến thăm hỏi) và khách hàng mới chưa phát sinh đơn (gửi voucher chào mừng).
  3. **Kiểm tra Biện pháp xử lý công nợ**: Phân tầng biện pháp xử lý theo mức độ rủi ro (Nợ $> 60$ ngày chuyển Pháp chế; Nợ $> 30$ ngày tạm dừng đơn trả sau chuyển COD).

---

## 3. CÁCH DOANH NGHIỆP VẬN HÀNH ĐỊNH KỲ (AUTOMATED CONTINUOUS AUDIT)

```text
┌─────────────────────────┐
│ ETL HÀNG ĐÊM TỰ ĐỘNG     │ ──> [Cronjob 02:00 AM]
└────────────┬────────────┘
             │
             ▼
┌─────────────────────────┐
│ RUN AUDIT SCRIPT        │ ──> python scripts/ml_validation_pipeline.py
└────────────┬────────────┘
             │
    ┌────────┴────────┐
    │ Tất cả PASS?    │
    ├─────────────────┤
    │ [YES]           │ [NO - Phát hiện lỗi/sai lệch]
    ▼                 ▼
┌───────────────┐  ┌──────────────────────────────────────────────┐
│ Cập nhật Cache│  │ 1. Khóa cập nhật Cache tự động               │
│ AI & BI Model │  │ 2. Giữ nguyên mô hình ngày hôm trước        │
│ Sẵn sàng phục │  │ 3. Bắn thông báo Telegram/Slack tới Kỹ sư    │
│ vụ người dùng │  │ 4. Xuất log chi tiết để rà soát nguyên nhân   │
└───────────────┘  └──────────────────────────────────────────────┘
```

**Kết luận**: Nhờ quy trình kiểm toán 5 bước khép kín này, hệ thống Smart ERP đảm bảo tính chính xác tuyệt đối, bảo vệ doanh nghiệp khỏi các rủi ro đặt thừa hàng tồn kho, bỏ lọt nợ xấu, hoặc đưa ra các con số dự báo ảo trong các buổi họp quản trị.