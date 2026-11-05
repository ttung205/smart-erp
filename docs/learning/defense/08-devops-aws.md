# 08. DevOps trên AWS: những gì đã dựng và vì sao

> Bài này giải thích **mục đích** của từng thứ bạn đã tạo trên console (Budget, Credits, S3, IAM role, Security Group), nó liên quan gì tới hệ thống phân tán, và thầy có thể hỏi gì.
> Phần deploy (EC2, Caddy, Grafana, image GHCR) sẽ được bổ sung vào bài này sau khi triển khai xong.

## 1. Bức tranh tổng thể

```
 Người dùng ──https (443) / http (80)──►[ Security Group ]──► EC2 (Ubuntu + Docker)
                                         tường lửa           │
                                                              ├─ Caddy ─► Kong ─► các service ─► MySQL, Mongo, RabbitMQ
                                                              │            (cùng chạy trong Docker, như trên máy bạn)
                                                              │
 Bạn (quản trị) ──IAM + Session Manager──► EC2                │  (không cần cổng 22, không cần khóa SSH)
                                                              │
                                  IAM role gắn vào EC2 ───────┴──► S3 bucket smart-erp-backup (bản sao lưu)
```

Ba nguyên tắc xuyên suốt, thầy rất thích nghe:
1. **Đặc quyền tối thiểu (least privilege):** mỗi thứ chỉ được làm đúng việc nó cần.
2. **Không có bí mật dài hạn nằm trên máy chủ:** dùng quyền tạm thời của IAM role thay vì Access Key.
3. **Diện tấn công nhỏ nhất:** chỉ mở đúng hai cổng, mọi thứ còn lại đóng.

## 2. Từng bước: mục đích, nếu bỏ qua thì sao, kiểm tra thế nào

### 2.1. Chọn Region Singapore
- **Là gì:** AWS đặt máy ở nhiều khu vực địa lý (Region). Mỗi tài nguyên (EC2, S3 bucket) thuộc **một** Region. IAM và Billing là dịch vụ toàn cầu nên ô Region hiện "Global".
- **Vì sao Singapore (ap-southeast-1):** gần Việt Nam nhất trong các Region mở cho tài khoản bạn, nên độ trễ thấp và giá hợp lý.
- **Nếu bỏ qua:** tạo nhầm ở Region khác thì máy, bucket và Security Group nằm rải rác, không thấy nhau (Security Group không dùng chung giữa các Region), và dễ quên xoá.

### 2.2. Budget 20 USD
- **Là gì:** một cảnh báo chi phí gửi email khi chi tiêu thực tế hoặc dự báo chạm ngưỡng.
- **Hiểu đúng:** Budget **chỉ cảnh báo, không tự chặn chi tiêu**. Đây là lưới an toàn để không bị "hoá đơn bất ngờ" nếu quên tắt máy.
- **Credits (120 USD):** AWS trừ tiền credit trước khi tính vào thẻ của bạn. Bạn có 100 USD Free Tier và 20 USD tặng khi tạo Budget, hạn đến 27/10/2027. Trang Credits cho biết còn bao nhiêu.
- **Nếu bỏ qua:** một máy `m7i-flex.large` chạy cả tháng tốn vài chục USD; không cảnh báo thì chỉ biết khi hết credit.

### 2.3. S3 bucket `smart-erp-backup`
**S3** là kho lưu **đối tượng** (file) với độ bền rất cao, tách biệt khỏi máy EC2. Đây là nơi giữ bản sao lưu. Lý do phải tách: nếu máy EC2 hỏng hoặc bị xoá thì bản sao lưu nằm cùng máy cũng mất theo (xem bài 07, điểm yếu "bản cục bộ nằm cùng máy").

Ba thiết lập bạn đã bật, mỗi cái chống một rủi ro khác nhau:

| Thiết lập | Chống rủi ro | Giải thích |
|---|---|---|
| **Block all public access** | **Rò rỉ dữ liệu** | Bản sao lưu chứa dữ liệu thật của công ty. Rất nhiều vụ lộ dữ liệu nổi tiếng là do bucket vô tình để công khai. Bật chặn này thì dù ai đó lỡ cấu hình sai (ACL, policy) bucket vẫn không thể công khai |
| **Mã hoá SSE-S3** | **Lộ dữ liệu khi lấy được đĩa/ổ lưu trữ** | S3 mã hoá dữ liệu khi lưu (AES-256, khoá do AWS quản lý), trong suốt với ứng dụng. Đây là mã hoá *at rest* |
| **Lifecycle rule 14 ngày** | **Chi phí tăng mãi và dữ liệu cũ tồn tại vô thời hạn** | S3 tự xoá bản sao lưu cũ hơn 14 ngày. Là chính sách lưu giữ (retention) ở phía đám mây, bổ sung cho 7 ngày ở phía máy chủ |

- **Prefix `smart-erp/`:** S3 không có thư mục thật, chỉ có tên đối tượng; phần `smart-erp/` đứng đầu tên gọi là prefix và hoạt động như thư mục. Dùng prefix để một bucket phục vụ nhiều mục đích mà quyền vẫn tách bạch.
- **Nếu bỏ qua mã hoá hay chặn công khai:** vẫn chạy, nhưng khi bị hỏi về bảo mật dữ liệu sao lưu thì không trả lời được.

### 2.4. IAM role `smart-erp-ec2-role`
Đây là phần quan trọng nhất về bảo mật, cũng là phần dễ hiểu sai nhất.

**Vấn đề:** máy EC2 cần ghi lên S3. Cách "dễ" là tạo một Access Key rồi dán vào file `.env` trên máy chủ. Nhược điểm: khoá đó **không hết hạn**, ai lấy được file (lộ repo, lộ ảnh chụp, máy chủ bị xâm nhập) là dùng được từ bất cứ đâu.

**Cách đúng (đã làm): IAM role.** Máy EC2 "đóng vai" một role và AWS cấp cho nó **thông tin đăng nhập tạm thời, tự xoay vòng** (hết hạn sau vài giờ). Không có khoá lâu dài nào để lộ.

Một role luôn có hai phần, đừng nhầm:
| Phần | Trả lời câu hỏi | Ở đây |
|---|---|---|
| **Trust policy** (chính sách tin cậy) | *Ai được phép đóng vai role này?* | Dịch vụ EC2. Bước "Trusted entity: AWS service → EC2" trong lúc tạo role chính là tạo phần này |
| **Permission policy** (chính sách quyền) | *Role này được làm gì?* | Hai policy bên dưới |

Hai permission policy:
1. **`AmazonSSMManagedInstanceCore`** (do AWS viết sẵn): cho phép chương trình **SSM Agent** trên máy EC2 đăng ký với dịch vụ Systems Manager. Nhờ đó bạn mở được **Session Manager**: một cửa sổ dòng lệnh vào máy ngay trên trình duyệt, được xác thực bằng IAM, **không cần cổng 22 và không cần khoá SSH**. Thiếu policy này thì máy không xuất hiện trong Session Manager và bạn không có cách nào vào máy.
2. **Inline policy `smart-erp-backup-s3`** (do bạn viết, "gắn cứng" vào role):
   - `s3:PutObject` + `s3:GetObject` chỉ trên `smart-erp-backup/smart-erp/*`: ghi bản sao lưu, và đọc lại khi phục hồi.
   - `s3:ListBucket` chỉ trên chính bucket đó: liệt kê các bản.
   - **Không có `DeleteObject`:** nếu máy chủ bị xâm nhập, kẻ tấn công **không xoá được các bản sao lưu** (chống ransomware). Việc xoá cũ do chính S3 làm (lifecycle), không do máy chủ.

**Instance profile:** EC2 không gắn trực tiếp role mà gắn qua một "instance profile" (cái hộp chứa role). Khi tạo role cho EC2 trên console, AWS tự tạo profile cùng tên, nên bạn không thấy bước này.

> **Câu chốt:** "Máy chủ không giữ khoá AWS nào. Nó nhận quyền tạm thời từ IAM role, chỉ ghi được vào một thư mục của một bucket và không xoá được gì."

### 2.5. Security Group `smart-erp-web-sg`
- **Là gì:** tường lửa ảo gắn vào máy EC2. Mặc định **chặn mọi kết nối vào**; bạn chỉ khai báo những gì được **cho phép**.
- **Stateful:** nếu cho phép một kết nối đi vào thì phản hồi của nó tự động được đi ra mà không cần khai báo thêm (khác NACL, vốn là stateless, phải khai báo hai chiều).
- **Hai quy tắc đã tạo:**
  - **Cổng 443 (HTTPS):** người dùng truy cập hệ thống.
  - **Cổng 80 (HTTP):** Caddy cần cổng này để **xin chứng chỉ Let's Encrypt** (Let's Encrypt gọi vào cổng 80 để chứng minh bạn sở hữu tên miền) và để chuyển người dùng từ http sang https.
  - Nguồn `0.0.0.0/0` (mọi nơi) vì đây là website công khai.
- **Vì sao KHÔNG mở cổng 22 (SSH):** cổng 22 là mục tiêu số một của các chương trình dò mật khẩu tự động, chỉ vài phút sau khi máy lên mạng đã bị dò. Session Manager thay thế SSH, **an toàn hơn**: xác thực bằng IAM (có MFA), mọi phiên được ghi lại, không có cổng nào để tấn công.
- **Outbound mặc định cho phép tất cả:** máy cần chủ động đi ra để tải image từ GHCR, cài gói, xin chứng chỉ và ghi lên S3. (Giới hạn outbound chặt hơn là hướng nâng cao.)
- **Nếu bỏ qua:** máy không có Security Group riêng sẽ dùng nhóm mặc định, thường quá rộng hoặc kín nhầm.

### 2.6. VPC mặc định
Mọi máy EC2 nằm trong một mạng riêng ảo (**VPC**). Bạn dùng **VPC mặc định**: AWS đã dựng sẵn mạng con công khai và cổng Internet. Đơn giản cho bài tập. Một hệ thống sản xuất thật sẽ tự dựng VPC với mạng con **riêng** cho cơ sở dữ liệu (không có đường ra Internet) và mạng con công khai chỉ cho tầng web. Đây là điểm yếu bạn nên tự nói trước khi thầy hỏi.

### 2.7. Tài khoản mẫu: không bao giờ để mật khẩu công khai trên máy chủ ra Internet
- **Vấn đề:** seeder của identity tạo sẵn 18 tài khoản (có SUPER_ADMIN) với mật khẩu cố định `password123`, công khai trong README. Trên máy dev thì tiện, nhưng trên một địa chỉ HTTPS công khai thì ai cũng đăng nhập được với quyền cao nhất.
- **Vì sao nguy hiểm ngay cả khi tên miền ít người biết:** khi Caddy xin chứng chỉ, tên miền được ghi vào **nhật ký chứng chỉ công khai (Certificate Transparency)**. Có các chương trình tự động theo dõi nhật ký đó và dò thử các tên miền mới trong vài phút.
- **Cách xử lý (đã làm trong `scripts/deploy-aws.sh`):** `SEED=1` chỉ nạp identity và **bắt buộc có `DEMO_PASSWORD`** (từ 14 ký tự), kiểm tra ngay đầu script. Nạp xong script đổi mật khẩu của mọi tài khoản `@erp.local` thành mật khẩu đó, tất cả diễn ra **trước khi bật gateway**, nên không có thời điểm nào `password123` tồn tại trên máy chủ công khai. Script `scripts/aws/set-demo-password.sh` dùng riêng được về sau.
- **Nguyên tắc:** thất bại sớm (không để mất 10 phút kéo image rồi mới báo thiếu mật khẩu), an toàn mặc định (thiếu mật khẩu thì từ chối chứ không nạp mật khẩu yếu), và chỉ đụng tới đúng nhóm tài khoản mẫu (người dùng thật không bị ảnh hưởng).

## 3. Vì sao tất cả điều này thuộc môn "ứng dụng phân tán"?

| Chương rubric | Phần AWS liên quan |
|---|---|
| **Ch5 Định danh** | IAM role (định danh máy chủ bằng quyền tạm thời), JWT giữa các service, chứng chỉ TLS (Let's Encrypt) định danh website, DNS |
| **Ch7 Sao lưu** | S3 bên ngoài máy chủ, mã hoá, lifecycle, quyền không xoá được |
| **Ch8 Chịu lỗi** | Giám sát Grafana, khởi động lại tự động (`restart: unless-stopped`), image có thẻ theo commit để quay lui |
| **Ch2 Kiến trúc** | Cùng một kiến trúc microservice chạy bằng Docker trên một máy chủ đám mây |

## 4. Thầy có thể hỏi

**H: Vì sao dùng IAM role mà không dùng Access Key?**
Đ: Access Key không hết hạn, nằm trên máy chủ nên lộ là dùng được từ khắp nơi. IAM role cấp quyền tạm thời, tự xoay vòng, không có khoá nào để lộ.

**H: Sao không mở cổng SSH để quản trị?**
Đ: Cổng 22 bị dò mật khẩu liên tục. Session Manager xác thực bằng IAM, ghi nhật ký mọi phiên và không cần mở cổng nào, nên an toàn hơn và vẫn vào được máy.

**H: Bucket sao lưu có thể bị công khai không? Dữ liệu có được mã hoá không?**
Đ: Không: bật "Block all public access" nên dù cấu hình sai bucket vẫn không công khai được. Dữ liệu mã hoá khi lưu bằng SSE-S3 (AES-256). Máy chủ chỉ ghi và đọc được trong một thư mục, không có quyền xoá.

**H: Security Group và firewall thường khác gì? Stateful nghĩa là gì?**
Đ: Security Group là tường lửa ảo gắn vào máy, chặn mặc định mọi kết nối vào và chỉ mở những gì khai báo. Stateful nghĩa là kết nối đã được cho phép thì phản hồi tự được đi ra, không cần luật chiều ngược lại.

**H: Chi phí? Làm sao tránh phát sinh?**
Đ: Dùng credit 120 USD, đặt Budget 20 USD để cảnh báo qua email, dừng máy khi không dùng, và dọn tài nguyên (Elastic IP, bucket, EC2) sau khi nộp bài.

## 5. Điểm yếu (nói thật khi bị hỏi)
- Dùng **VPC mặc định**, mạng con công khai cho tất cả; chưa tách mạng con riêng cho database.
- **Một máy EC2, một Availability Zone:** máy hoặc vùng hỏng thì cả hệ thống ngừng. Chưa có cân bằng tải hay tự mở rộng.
- Tài khoản dùng vai trò **toàn quyền** (AccountFullAccessRole) cho tiện, chưa tách quyền theo công việc.
- Bucket **chưa bật versioning**: nếu bản sao lưu bị ghi đè cùng tên thì không khôi phục bản cũ được (tên bản sao lưu có số thứ tự nên rủi ro thấp).
- Security Group cho phép outbound tất cả.

## 6. Tự kiểm tra
- [ ] Giải thích vì sao đặt Budget và nó có chặn chi tiêu không.
- [ ] Kể 3 thiết lập bảo mật của bucket và mỗi cái chống rủi ro nào.
- [ ] Phân biệt trust policy với permission policy của một IAM role.
- [ ] Giải thích vì sao role không có quyền xoá và vì sao không dùng Access Key.
- [ ] Nói được vì sao mở cổng 80 mà không mở cổng 22.
- [ ] Vẽ lại sơ đồ mục 1 mà không nhìn tài liệu.
