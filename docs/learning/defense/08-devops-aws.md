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

### 2.7. Tài khoản đầu tiên: không có tài khoản mẫu với mật khẩu công khai
- **Vấn đề ban đầu:** seeder tạo sẵn 18 tài khoản (có SUPER_ADMIN) với mật khẩu cố định `password123`, công khai trong README. Trên máy dev thì tiện, nhưng trên địa chỉ HTTPS công khai thì ai cũng đăng nhập được với quyền cao nhất. Tên miền mới còn bị các chương trình tự động theo dõi **nhật ký chứng chỉ công khai (Certificate Transparency)** và dò thử trong vài phút sau khi cấp chứng chỉ.
- **Cách xử lý ban đầu:** nạp tài khoản mẫu rồi đổi mật khẩu ngay trước khi bật gateway. Hiệu quả nhưng vẫn là "dọn dẹp sau khi tạo".
- **Cách xử lý cuối cùng (tốt hơn):** production **không bao giờ có tài khoản mẫu**. `SEED=1` chỉ nạp phòng ban, vai trò và quyền, rồi lệnh `identity:bootstrap-admin` tạo **một** Super Admin với mật khẩu mạnh (từ 14 ký tự) do bạn cung cấp. Lệnh `identity:purge-demo-users` xoá các tài khoản `@erp.local` còn sót.
- **Đăng nhập:** bằng **mã nhân viên** (dạng `AC-2005`) kèm **OTP gửi qua email** (6 số, sống 5 phút, dùng một lần, tối đa 5 lần sai) hoặc mật khẩu. OTP chỉ lưu mã băm HMAC; số lần thử và việc tiêu thụ mã dùng UPDATE có điều kiện nên các yêu cầu song song không vượt được giới hạn (đúng cơ chế chương 6).
- **Nguyên tắc:** an toàn mặc định (không tạo ra thứ nguy hiểm rồi mới sửa), thất bại sớm (thiếu thông tin thì script dừng ngay đầu), và tách bạch dữ liệu nền với dữ liệu người dùng.
- **Email OTP qua Amazon SES** (tài khoản đã ở chế độ production, các địa chỉ đã được xác minh). Thông tin kết nối SMTP nằm trong `.env` trên máy chủ chứ không nằm trong Git. Lưu ý: SES qua SMTP dùng **khoá SMTP của IAM user (lâu dài)**, trái với nguyên tắc "không có khoá dài hạn". Vì vậy IAM user đó chỉ nên có quyền gửi email.

### 2.8. CD: tự động triển khai không cần khoá AWS và không cần cổng 22
**Chuỗi hoạt động khi bạn merge vào `main`:**
```
push vào main ─► CI (chạy test)            ┐
              └► Build images (12 image) ──┴─► Deploy production
                                                 1. verify-ci   : CI của ĐÚNG commit này phải xanh, nếu đỏ thì dừng ngay
                                                 2. chờ duyệt   : environment "production" có 1 người duyệt bắt buộc
                                                 3. OIDC        : GitHub xin token tạm thời, AWS cấp quyền của role deploy
                                                 4. SSM         : gửi lệnh tới EC2 (không SSH): git pull + scripts/deploy-aws.sh
                                                 5. smoke test  : kiểm tra các route /health qua HTTPS; lỗi thì job đỏ
```

**OIDC là gì và vì sao tốt hơn Access Key?** Cách "dễ" là tạo Access Key rồi dán vào GitHub Secrets: khoá đó không hết hạn, lộ là dùng được từ khắp nơi. Với OIDC, mỗi lần chạy GitHub tự ký một **token tạm thời** ghi rõ "tôi là workflow của repo X, môi trường Y". AWS kiểm tra chữ ký rồi cấp quyền của một IAM role trong **tối đa 1 giờ**. **Không có khoá dài hạn nào nằm trong GitHub** (mục Secrets trống, chỉ có 3 biến không nhạy cảm: Region, ID máy, ARN của role).

**Trust policy của role `github-deploy-smart-erp` (ai được đóng vai role này):**
```json
{ "Effect": "Allow",
  "Principal": { "Federated": "arn:aws:iam::<ACCOUNT_ID>:oidc-provider/token.actions.githubusercontent.com" },
  "Action": "sts:AssumeRoleWithWebIdentity",
  "Condition": { "StringEquals": {
      "token.actions.githubusercontent.com:sub": "repo:<owner>@<id>/smart-erp@<id>:environment:production",
      "token.actions.githubusercontent.com:aud": "sts.amazonaws.com" } } }
```
- **`sub` (chủ thể):** chỉ token của **đúng repo này, đúng môi trường `production`** mới được. Dùng `StringEquals` (so khớp chính xác), không dùng ký tự đại diện. Giá trị có kèm **ID số** của chủ sở hữu và repo, nên xoá repo rồi ai đó tạo lại cùng tên cũng không khớp.
- **`aud` (đối tượng nhận):** token phải dành cho AWS STS, không dùng lại token của dịch vụ khác.
- Vì `sub` có `environment:production`, **chỉ job đã được người duyệt cho chạy** mới lấy được quyền AWS. Cổng duyệt tay vì thế còn là một lớp bảo vệ của AWS chứ không chỉ của GitHub.

**Các lớp bảo vệ khác trong `deploy.yml`:**
- **Kiểm tra thẻ image bằng biểu thức chính quy** (`latest` hoặc `sha-` kèm chữ số hex) vì giá trị này được ghép vào lệnh chạy trên máy chủ: chống chèn lệnh.
- **`concurrency`:** hai lần deploy không bao giờ chạy song song.
- Lệnh chạy bằng user `ubuntu`, không phải root, nên file trong `/opt/smart-erp` không bị đổi chủ.
- **Quay lui:** chạy tay workflow với `image_tag` là `sha-xxxxxxx` để về đúng một commit trước đó.

**Đã vá thêm sau khi rà soát:**
| Điểm yếu | Vá |
|---|---|
| Deploy kích hoạt bởi "Build images", không xét CI: commit có CI đỏ vẫn tới bước chờ duyệt | Job `verify-ci` chạy **trước** và dừng nếu CI của cùng commit không xanh (đã thử với commit đỏ thật và commit xanh thật) |
| Sửa tài liệu cũng build lại 12 image và xin duyệt deploy | `paths-ignore` cho `docs/**` và `**.md` |
| EC2 đang dừng thì lỗi khó hiểu | Thông báo rõ: bật máy rồi chạy lại bằng `workflow_dispatch` |

**Còn lại (nói thật khi bị hỏi):** môi trường `production` chưa giới hạn nhánh được deploy (nên đặt chỉ `main` ở Settings → Environments); quyền của role (policy `ssm-deploy-smart-erp`) cần được rà soát để chỉ cho gửi lệnh tới **đúng một instance** với tài liệu `AWS-RunShellScript`; repo công khai nên log workflow ai cũng đọc được (không được in bí mật ra log).

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

**H: CD của em hoạt động thế nào? Vì sao không dùng Access Key của AWS trong GitHub?**
Đ: GitHub dùng OIDC: mỗi lần chạy, nó ký một token tạm thời nói rõ repo và môi trường; AWS kiểm tra chữ ký rồi cấp quyền của một IAM role trong tối đa 1 giờ. Không có khoá dài hạn nào nằm trong GitHub nên không có gì để lộ. Role chỉ cho đóng vai khi token đúng repo và đúng môi trường `production`.

**H: Làm sao đảm bảo không ai deploy mã lỗi lên production?**
Đ: Nhiều lớp: CI của đúng commit phải xanh (job `verify-ci`), môi trường `production` bắt buộc một người duyệt tay, và vì quyền AWS gắn với môi trường đó nên chỉ job đã được duyệt mới lấy được quyền. Sai thì quay lui bằng cách chạy tay với `image_tag` của commit trước.

**H: Sao deploy bằng SSM mà không SSH?**
Đ: SSM gửi lệnh qua kết nối mà chính máy chủ chủ động mở ra AWS, nên không cần mở cổng 22. Xác thực bằng IAM và có nhật ký, không có khoá SSH nào để quản lý hay bị lộ.

## 5. Điểm yếu (nói thật khi bị hỏi)
- Dùng **VPC mặc định**, mạng con công khai cho tất cả; chưa tách mạng con riêng cho database.
- **Một máy EC2, một Availability Zone:** máy hoặc vùng hỏng thì cả hệ thống ngừng. Chưa có cân bằng tải hay tự mở rộng.
- Tài khoản dùng vai trò **toàn quyền** (AccountFullAccessRole) cho tiện, chưa tách quyền theo công việc.
- Bucket **chưa bật versioning**: nếu bản sao lưu bị ghi đè cùng tên thì không khôi phục bản cũ được (tên bản sao lưu có số thứ tự nên rủi ro thấp).
- Security Group cho phép outbound tất cả.

## 6. Nhật ký sự cố khi triển khai (để trả lời "kể một lần xử lý sự cố")

### Sự cố 1. "Error establishing SSH connection" khi bấm Connect
- **Hiện tượng:** không vào được máy EC2 ngay sau khi tạo.
- **Giả thuyết đúng:** tab mặc định của nút Connect là EC2 Instance Connect, **dùng SSH qua cổng 22**, mà Security Group cố ý không mở cổng 22.
- **Cách giải quyết:** chọn tab **Session Manager**. Lỗi này chính là bằng chứng Security Group hoạt động đúng.
- **Bài học:** đóng cổng 22 là chủ ý, đường quản trị là Session Manager.

### Sự cố 2. `connection reset by peer` khi kéo image
- **Hiện tượng:** `docker compose pull` dừng ở lớp của chat-service, các image khác đã tải xong.
- **Nguyên nhân:** sự cố mạng thoáng qua tới máy chủ lưu image của GitHub, không phải lỗi cấu hình.
- **Cách giải quyết:** Docker giữ lại các lớp đã tải nên chạy lại chỉ tải phần còn thiếu. Sau đó thêm vòng tự thử lại 4 lần vào `deploy-aws.sh`.
- **Bài học:** thao tác qua Internet phải chịu được lỗi tạm thời (retry), đúng tinh thần chương 8.

### Sự cố 3. MySQL lần đầu: `MYSQL_ONETIME_PASSWORD: unbound variable`
- **Hiện tượng:** `erp-mysql is unhealthy` khi triển khai lần đầu; sau đó container tự lên lại và `healthy`.
- **Giả thuyết ban đầu (sai):** healthcheck không có `start_period`, MySQL khởi tạo chậm hơn 50 giây. Log cho thấy khởi tạo chỉ mất ~10 giây nên giả thuyết này sai.
- **Cách tìm ra:** so sánh log máy chủ với log máy dev. Máy dev ghi `running ...03-...sh`, máy chủ ghi `sourcing ...03-...sh`.
- **Nguyên nhân gốc:** `git clone` trên Linux cho file `.sh` quyền 644 (không thực thi), nên entrypoint của MySQL **nạp thẳng** file vào shell của nó. Dòng `set -euo pipefail` trong script rò rỉ sang entrypoint và `set -u` làm nó chết ở bước sau. Trên Windows mọi file bind-mount đều hiện là thực thi được nên không bao giờ gặp.
- **Cách sửa:** đặt quyền thực thi trong git **và** bọc script trong shell con để dù bị nạp thẳng cũng không rò rỉ. Đã tái hiện lỗi bằng một image có file 644 trước khi sửa, và kiểm chứng cả hai đường sau khi sửa.
- **Bài học:** môi trường dev (Windows) khác production (Linux) ở những chi tiết nhỏ như quyền file; lỗi chỉ lộ khi chạy trên môi trường thật. Khi chẩn đoán, **đừng tin giả thuyết đầu tiên, hãy so sánh log của hai môi trường**.

## 7. Tự kiểm tra
- [ ] Giải thích vì sao đặt Budget và nó có chặn chi tiêu không.
- [ ] Kể 3 thiết lập bảo mật của bucket và mỗi cái chống rủi ro nào.
- [ ] Phân biệt trust policy với permission policy của một IAM role.
- [ ] Giải thích vì sao role không có quyền xoá và vì sao không dùng Access Key.
- [ ] Nói được vì sao mở cổng 80 mà không mở cổng 22.
- [ ] Vẽ lại sơ đồ mục 1 mà không nhìn tài liệu.
