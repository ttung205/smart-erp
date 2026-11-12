# demo-traffic

Bot sinh giao dịch hằng ngày cho môi trường demo. Bot đi qua API thật của gateway, nên dữ liệu đi đủ logic nghiệp vụ: giữ hàng nguyên tử, xuất kho, outbox, công nợ. Không ghi thẳng vào database. ai-service tự kéo dữ liệu mới qua ETL (5 phút một lần).

Lịch sử ban đầu nạp một lần bằng `services/ai-service/simulator`. Bot chỉ nối tiếp từ hôm nay.

## Mỗi ngày bot làm gì

| Bước | Tài khoản | API |
|---|---|---|
| Lập đơn bán, gửi duyệt | `sale` | `POST /sales/orders`, `/orders/{id}/submit-approval` |
| Duyệt đơn, duyệt yêu cầu giao | `sale.manager` | `/orders/{id}/approve`, `PUT /deliveries/{id}/status` |
| Tạo yêu cầu giao | `sale` | `POST /sales/deliveries` |
| Lập hóa đơn (giữ hàng), gửi kho | `finance` | `POST /sales/invoices` |
| Phân công, xuất kho | `logistic` | `/invoices/{id}/assign-logistics`, `/invoices/{id}/warehouse-post` |
| Hóa đơn VAT (sinh công nợ) | `finance.manager` | `POST /sales/vat-invoices` |
| Đặt hàng khi dưới tồn tối thiểu | `purchase`, `purchase.manager` | `POST /purchase/orders`, submit, approve, `/purchase/receipts`, confirm |
| Thu nợ quá hạn (khoảng 60%) | `finance.manager` | `POST /finance/vouchers`, `/vouchers/{id}/post` |

- Đơn bán được rải đều trong giờ làm việc (mặc định 8h–17h giờ Việt Nam), mỗi lượt cách nhau 15 phút.
- Bổ sung hàng và thu nợ chạy mỗi ngày một lần. Inventory tăng tồn khi xử lý event `GoodsReceived` (bất đồng bộ), nên chạy dày hơn sẽ đặt trùng PO.
- Ngày máy tắt: số đơn bỏ lỡ (tối đa `DEMO_MAX_CATCHUP_DAYS` ngày) dồn vào hôm nay. API ghi thời điểm hiện tại, nên không sinh được chứng từ cho ngày đã qua.

## Chạy

```bash
# Trong .env: DEMO_TRAFFIC_PASSWORD = mật khẩu chung của các tài khoản mẫu (DemoUserSeeder)
docker compose --profile demo up -d demo-traffic
docker compose logs -f demo-traffic

# Chạy một lượt ngay (bán 5 đơn, bổ sung hàng, thu nợ)
docker compose --profile demo run --rm demo-traffic python -m demo_traffic once --orders 5
```

| Biến | Mặc định | Ý nghĩa |
|---|---|---|
| `DEMO_ORDERS_PER_DAY` | 30 | Số đơn trung bình mỗi ngày (thứ 7 x0.5, Chủ nhật x0.2) |
| `DEMO_MAX_CATCHUP_DAYS` | 3 | Số ngày bỏ lỡ tối đa được chạy bù |
| `DEMO_MAX_PO_PER_DAY` | 5 | Số đơn mua tối đa mỗi ngày |
| `DEMO_WAREHOUSE_ID` | 1 | Kho bán và nhận hàng |
| `DEMO_*_EMAIL` | tài khoản mẫu | Đổi tài khoản cho từng vai trò |

Chỉ dùng cho môi trường demo. Không bật profile `demo` trên production.

## Kiểm thử

```bash
python -m unittest discover -s tests -t .
```
