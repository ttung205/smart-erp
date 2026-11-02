---
title: Xử lý hàng trả từ khách
group: business
audience: nhân viên kinh doanh, kiểm định kỹ thuật, thủ kho, kế toán bán hàng
status: verified
source: services/sales-service/routes/api.php (nhóm /sale-return-requests, /sale-returns); services/sales-service/app/Models/SaleReturnRequest.php; docs/knowledge/technical/luong-ban-hang-giu-kho.md
verified_by: DuyAnh
verified_at: 2026-11-02
---

# Xử lý hàng trả từ khách

## Mục đích

Khi khách trả hàng đã mua, hàng phải được kiểm định, đưa về đúng kho và ghi nhận công nợ điều chỉnh. Quy trình có hai cấp duyệt để tránh nhận hàng lỗi hoặc hoàn tiền sai.

## Ai thực hiện

| Bước | Quyền |
|---|---|
| Tạo yêu cầu trả hàng | `sale_return.create` |
| Xem yêu cầu | `sale_return.view` |
| Kiểm định kỹ thuật, nhận hàng kỹ thuật, từ chối kỹ thuật | `sale_return.technical` |
| Duyệt hoặc từ chối cấp chính | `sale_return.approve` |
| Ghi sổ phiếu trả hàng | `sale_return.post` |

## Điều kiện

- Hàng trả phải thuộc một hóa đơn đã phát hành. Hệ thống chỉ cho chọn hóa đơn hợp lệ (có API lấy danh sách hóa đơn đủ điều kiện).

## Các bước

1. **Tạo yêu cầu trả hàng.** Chọn hóa đơn gốc, chọn mặt hàng và số lượng trả. Yêu cầu ở trạng thái `PENDING`.
2. **Bắt đầu kiểm định.** Bộ phận kỹ thuật nhận yêu cầu và bắt đầu kiểm tra. Ghi kết quả kiểm định.
3. **Kiểm định kết luận.**
   - Hàng đạt: bộ phận kỹ thuật nhận hàng vào kho kỹ thuật, trạng thái `TECHNICAL_RECEIVED`.
   - Hàng không đạt: bộ phận kỹ thuật từ chối yêu cầu, trạng thái `REJECTED`. Kết thúc.
4. **Yêu cầu nhập kho chính.** Bộ phận kỹ thuật chuyển yêu cầu sang kho chính, trạng thái `PENDING_WAREHOUSE`.
5. **Duyệt cấp chính.** Người có quyền `sale_return.approve` duyệt hoặc từ chối.
   - Duyệt: trạng thái `PENDING_FINANCE`, chuyển sang kế toán.
   - Từ chối: yêu cầu kết thúc ở `REJECTED`.
6. **Ghi sổ.** Người có quyền `sale_return.post` ghi sổ phiếu trả. Hàng được nhập lại kho và điều chỉnh hóa đơn VAT, công nợ khách được cập nhật.
7. Yêu cầu chuyển sang `COMPLETED`.

## Trạng thái

`PENDING` → `TECHNICAL_RECEIVED` → `PENDING_WAREHOUSE` → `PENDING_FINANCE` → `COMPLETED`
Nhánh từ chối: `PENDING` hoặc `TECHNICAL_RECEIVED` → `REJECTED`

## Lỗi thường gặp

- Không thấy nút duyệt cấp chính: tài khoản thiếu quyền `sale_return.approve`. Xem [loi-xac-thuc-va-quyen.md](../faq/loi-xac-thuc-va-quyen.md).
- Không ghi sổ được: yêu cầu chưa qua đủ các bước trước, hoặc chưa ở `PENDING_FINANCE`.

## Cần xác nhận trước khi đưa vào bot

- Tiêu chí kiểm định đạt và không đạt: ai quyết định, có biên bản kèm theo không.
- Hoàn tiền: quy trình hoàn tiền cho khách nằm ở đâu (phiếu chi, công nợ đối trừ). Tài liệu này chưa mô tả.
- Trường hợp hàng trả một phần: có được trả một phần của dòng không, và số lượng tối đa là bao nhiêu.
- Hàng trả đã nhận vào kho nhưng yêu cầu bị từ chối ở cấp chính: hàng xử lý thế nào.
- Ai có quyền tạo yêu cầu trả hàng (thường là nhân viên kinh doanh hay kế toán).
