# ai-service

Nền tảng cho AI agent ra quyết định trong ERP (phân tích bán hàng, tồn kho → đề xuất mua hàng, liên hệ NCC...).

Bước đầu tiên là **bộ mô phỏng dữ liệu** (`simulator/`): sinh 1 năm hoạt động (01/10/2025 → 30/09/2026)
của một nhà phân phối thiết bị gia dụng, nhất quán giữa tất cả service, để agent có dữ liệu sát thực tế
để đọc và phân tích.

## Cài đặt

```bash
cd services/ai-service
python -m venv .venv
.venv/Scripts/python -m pip install -r requirements.txt   # Windows
```

## Quy trình

```bash
# 1. Cào danh mục sản phẩm thật (~1000 SKU) từ dienmayxanh.com, có cache, chạy lại không tải lại
python -m simulator.scrape_products
python -m simulator.scrape_products --fill     # bù danh mục render bằng JS từ sitemap

# 2. Xem thử kết quả mô phỏng (không ghi DB)
python -m simulator.run --dry-run

# 3. TẮT các service đang chạy, rồi nạp dữ liệu (tự backup DB vào simulator/.backup/)
python -m simulator.run

# 4. Kiểm tra tính nhất quán giữa các DB
python -m simulator.validate
```

Dữ liệu deterministic theo `SEED` trong `config.py`: chạy lại cho ra đúng bộ dữ liệu đó.

## Cấu trúc

| File | Vai trò |
|---|---|
| `config.py` | Toàn bộ giả định kinh doanh: cơ cấu khách, chiết khấu, công nợ, mùa vụ, Tết, VAT, chính sách tồn kho |
| `scrape_products.py` | Cào sản phẩm thật (tên, model, hãng, giá niêm yết, thông số, xuất xứ, lượt bán) |
| `people.py` | Sinh tên, địa chỉ (34 tỉnh / 3.321 xã phường sau sáp nhập 7/2025), MST hợp lệ, SĐT, email |
| `masterdata.py` | Sản phẩm, nhà cung cấp (map theo hãng), khách hàng, nhân viên |
| `engine.py` | Mô phỏng từng ngày: đặt hàng, giữ chỗ, xuất kho, hóa đơn VAT, thu tiền, rà soát tồn, mua hàng |
| `writers.py` | Chuyển sang đúng schema + định dạng của từng service (Laravel / EF Core) |
| `run.py` | Backup, xóa dữ liệu nghiệp vụ cũ, nạp dữ liệu mới |
| `validate.py` | Đối chiếu chéo: thẻ kho ↔ tồn, sales ↔ inventory, purchase ↔ inventory, VAT ↔ công nợ ↔ sổ cái |

## Những gì được mô phỏng

- **Khách hàng**: ~320 đại lý, 18 NPP cấp 2, ~45 khách dự án, khách lẻ tại showroom. Có khách mới gia nhập
  giữa năm, đại lý ngừng mua, ~12% khách trả chậm, đơn bị từ chối do nợ quá hạn.
- **Nhu cầu**: mùa vụ theo ngành hàng (điều hòa/quạt mùa hè, quạt sưởi/bình nóng lạnh mùa đông, máy sấy/hút ẩm
  mùa nồm), cao điểm Tết Bính Ngọ (17/02/2026) và nghỉ Tết, khác biệt vùng miền, sale 11.11 / Black Friday.
- **Vòng đời sản phẩm**: model mới ra mắt giữa năm (dự báo ban đầu sai lệch), model cũ giảm dần và bị ngừng
  → hàng tồn chậm luân chuyển.
- **Mua hàng**: rà soát tồn thứ 2 hằng tuần (NCC trong nước) / hằng tháng (nhập khẩu, USD), dự báo có nhiễu,
  NCC giao trễ, giao thiếu, hàng lỗi; hãng điều chỉnh giá (`product_price_histories`).
- **Tài chính**: hóa đơn VAT (8%, điều hòa 10%), công nợ theo hạn, phiếu thu gom nhiều hóa đơn, trả một phần.

## Giữ nguyên / thay thế khi nạp

- Giữ: user và role gốc, phòng ban (identity), hệ thống tài khoản, quỹ, tài khoản ngân hàng (finance), danh sách kho.
- Thêm: 12 nhân viên mô phỏng vào identity (sale theo vùng, mua hàng, kho, kế toán), mật khẩu giống `sale@erp.local`.
- Thay: danh mục, khách hàng, NCC và toàn bộ chứng từ nghiệp vụ.

`DbInitializer` của inventory/purchase chỉ seed khi bảng trống nên không ghi đè dữ liệu mô phỏng.

## An toàn

- Mọi email khách hàng / NCC dùng TLD `.example` (RFC 2606), không thể gửi ra ngoài; dev dùng Mailpit.
- Tên pháp nhân NCC chỉ để tham khảo; MST, người liên hệ, số tài khoản đều là dữ liệu giả.

## Chưa mô phỏng (dự kiến bổ sung)

- Trả hàng bán / hóa đơn điều chỉnh, trả hàng mua.
- Thanh toán cho NCC (finance chưa có phải trả 331), giá vốn 632.
- Công nợ đầu kỳ (coi như bắt đầu dùng ERP từ 01/10/2025 với tồn kho đầu kỳ).
