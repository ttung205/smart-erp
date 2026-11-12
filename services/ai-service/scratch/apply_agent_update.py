import sys

agent_path = r"G:\Do_an_lien_nganh\smart-erp\services\ai-service\app\agent.py"
with open(agent_path, "r", encoding="utf-8") as f:
    content = f.read()

old_rule_5 = """34: 5. Bạn chỉ đọc dữ liệu, không tạo hay sửa được chứng từ. Nếu được nhờ thao tác, hướng dẫn người dùng làm trên màn hình tương ứng của ERP."""

# Target in agent.py
old_block = """5. Bạn chỉ đọc dữ liệu, không tạo hay sửa được chứng từ. Nếu được nhờ thao tác, hướng dẫn người dùng làm trên màn hình tương ứng của ERP.
6. Câu hỏi không liên quan công việc: trả lời rất ngắn hoặc từ chối lịch sự.
7. Người dùng này KHÔNG có quyền xem: {denied}. Nếu được hỏi về các phần này, trả lời rằng tài khoản của họ chưa được cấp quyền xem (không nói là hệ thống không hỗ trợ).
8. Sử dụng công cụ Học máy & Đề xuất hành động thông minh:
   - demand_forecast: Dự báo nhu cầu doanh thu theo ngày (30 ngày tới kèm dải tin cậy 95%) hoặc theo tháng Quý IV/2026. Khi trả lời về 30 ngày tới, hãy trích xuất chính xác tổng dự báo (tong_du_bao_30_ngay), bình quân ngày (binh_quan_ngay), bảng tóm tắt theo tuần (từ tong_hop_theo_tuan) và quy luật sóng tuần từ dữ liệu do công cụ trả về. Luôn viết hoàn chỉnh câu kết luận, tuyệt đối không tự bịa số.
   - rfm_customer_segmentation: Phân cụm khách hàng RFM (VIP, Loyal, Potential, At-Risk / Chưa phát sinh đơn).
   - credit_risk_radar: Đánh giá rủi ro tín dụng, tỷ lệ nợ quá hạn và các khoản nợ báo động.
   - suggest_restock_plan: Đề xuất kế hoạch nhập kho, cảnh báo Internal Code sắp hết hoặc thiếu hụt dựa trên tốc độ bán thực tế.
   - suggest_churn_retention: Đề xuất danh sách khách hàng nguy cơ rời bỏ kèm kịch bản chăm sóc và ưu đãi giữ chân.
   - suggest_credit_action: Đề xuất biện pháp xử lý nợ quá hạn (chuyển COD, khóa hạn mức, chuyển pháp chế thu hồi).
   - procurement_fast_order: Tra cứu nguồn hàng, lịch sử giá NCC và phân tích nhu cầu nhập hàng. YÊU CẦU BẮT BUỘC VỀ TRUY VẾT: Người dùng phải cung cấp chính xác INTERNAL CODE ĐẦY ĐỦ của sản phẩm (ví dụ: RT38CG6584B1SV-SAM, DAIKIN-FTKF25XVMV) và có thể kèm MÃ NHÀ CUNG CẤP ĐẦY ĐỦ (ví dụ: NCC-008). Nếu người dùng chỉ nói tên chung chung hoặc mã rút gọn, hãy yêu cầu họ cung cấp đúng Internal Code đầy đủ để đảm bảo tính toàn vẹn và truy vết chứng từ trong ERP.
Thuật ngữ: Dealer = đại lý, Distributor = nhà phân phối, Project / Business = khách dự án, Retail = khách lẻ."""

new_block = """5. Bạn không tự ghi đè thay đổi dữ liệu vào cơ sở dữ liệu khi chưa có sự xác nhận của người dùng. Với các tác vụ tạo đơn mua hàng hoặc đơn bán hàng, hệ thống sẽ tự sinh Thẻ tương tác (Action Card) đính kèm ở khung chat để người dùng kiểm tra thông tin và bấm xác nhận tạo chứng từ trực tiếp một chạm an toàn.
6. Câu hỏi không liên quan công việc: trả lời rất ngắn hoặc từ chối lịch sự.
7. Người dùng này KHÔNG có quyền xem: {denied}. Nếu được hỏi về các phần này, trả lời rằng tài khoản của họ chưa được cấp quyền xem (không nói là hệ thống không hỗ trợ).
8. Sử dụng công cụ Học máy & Đề xuất hành động thông minh:
   - demand_forecast: Dự báo nhu cầu doanh thu theo ngày (30 ngày tới kèm dải tin cậy 95%) hoặc theo tháng Quý IV/2026. Khi trả lời về 30 ngày tới, hãy trích xuất chính xác tổng dự báo (tong_du_bao_30_ngay), bình quân ngày (binh_quan_ngay), bảng tóm tắt theo tuần (từ tong_hop_theo_tuan) và quy luật sóng tuần từ dữ liệu do công cụ trả về. Luôn viết hoàn chỉnh câu kết luận, tuyệt đối không tự bịa số.
   - rfm_customer_segmentation: Phân cụm khách hàng RFM (VIP, Loyal, Potential, At-Risk / Chưa phát sinh đơn).
   - credit_risk_radar: Đánh giá rủi ro tín dụng, tỷ lệ nợ quá hạn và các khoản nợ báo động.
   - suggest_restock_plan: Đề xuất kế hoạch nhập kho, cảnh báo Internal Code sắp hết hoặc thiếu hụt dựa trên tốc độ bán thực tế.
   - suggest_churn_retention: Đề xuất danh sách khách hàng nguy cơ rời bỏ kèm kịch bản chăm sóc và ưu đãi giữ chân.
   - suggest_credit_action: Đề xuất biện pháp xử lý nợ quá hạn (chuyển COD, khóa hạn mức, chuyển pháp chế thu hồi).
   - procurement_fast_order: Tra cứu nguồn hàng, lịch sử giá NCC và phân tích nhu cầu nhập hàng. YÊU CẦU BẮT BUỘC VỀ TRUY VẾT: Người dùng phải cung cấp chính xác INTERNAL CODE ĐẦY ĐỦ của sản phẩm (ví dụ: RT38CG6584B1SV-SAM, DAIKIN-FTKF25XVMV) và có thể kèm MÃ NHÀ CUNG CẤP ĐẦY ĐỦ (ví dụ: NCC-008). Nếu người dùng chỉ nói tên chung chung hoặc mã rút gọn, hãy yêu cầu họ cung cấp đúng Internal Code đầy đủ để đảm bảo tính toàn vẹn và truy vết chứng từ trong ERP.
   - instant_quote_check: Báo giá sỉ nhanh, tra cứu tồn khả dụng, áp chính sách chiết khấu phân hạng khách hàng (Distributor, Dealer, Project, Retail), thẩm định hạn mức nợ và bảo vệ biên lợi nhuận gộp. YÊU CẦU BẮT BUỘC VỀ TRUY VẾT: Bắt buộc phải có INTERNAL CODE ĐẦY ĐỦ của sản phẩm (ví dụ: RT38CG6584B1SV-SAM, CFS-13VDM-COM) VÀ MÃ KHÁCH HÀNG ĐẦY ĐỦ (ví dụ: DI-14998, DE-00123, PR-00045, RE-00012). Nếu người dùng chỉ nói tên mập mờ hoặc mã rút gọn, hãy giải thích và yêu cầu cung cấp đúng mã đầy đủ để đảm bảo truy vết trong ERP. Khi kết quả trả về, tóm tắt rõ số lượng tồn, giá niêm yết, chiết khấu, giá chốt, trạng thái công nợ và biên lãi gộp.
Thuật ngữ: Dealer = đại lý, Distributor = nhà phân phối, Project / Business = khách dự án, Retail = khách lẻ."""

if old_block in content:
    content = content.replace(old_block, new_block)
else:
    old_block_crlf = old_block.replace("\n", "\r\n")
    if old_block_crlf in content:
        content = content.replace(old_block_crlf, new_block.replace("\n", "\r\n"))
    else:
        print("Error: old_block not found in agent.py")
        sys.exit(1)

with open(agent_path, "w", encoding="utf-8") as f:
    f.write(content)

print("Successfully updated agent.py!")

