# Bài 05 — Chương 5: Định danh (Naming)

> Bám bài giảng *DS_Lec 4 - Định danh* (Trường CNTT Phenikaa): **thực thể, tên, địa chỉ, điểm truy cập, định danh, các loại không gian tên (phẳng, có cấu trúc, theo thuộc tính), phân giải tên.**
> Đăng nhập, JWT, OTP, TLS là chuyện *xác thực / bảo mật*, không phải trọng tâm của chương này. Bài chỉ giữ lại **OIDC** (mục 7) vì nó là cách một *thực thể không phải người* (pipeline CI/CD) được hệ thống tin cậy nhận diện. JWT của người dùng vẫn có trong [bài 13](13-so-tay-doc-code-theo-slide.md), phần Phụ lục.
> Mục tiêu: chỉ ra từng khái niệm của bài giảng trong hệ thống, đọc được chuỗi phân giải thật, và giải thích được OIDC.

## 1. Ý tưởng cốt lõi (theo bài giảng)

Trong hệ phân tán, để dùng một thực thể (host, service, file, người dùng, tiến trình) ta phải **tham chiếu** tới nó bằng *tên*, rồi tìm ra *địa chỉ* để *truy cập*:

| Khái niệm | Định nghĩa (bài giảng) | Hệ quả |
|---|---|---|
| **Thực thể (entity)** | bất kỳ thứ gì có trong hệ thống: host, printer, file, người dùng, tiến trình | cái cần được tham chiếu |
| **Tên (name)** | chuỗi bit hoặc ký tự dùng để tham chiếu tới thực thể | số tên của một thực thể **không bị hạn chế** |
| **Điểm truy cập (access point)** | nơi truy nhập vào thực thể | một thực thể có **nhiều** điểm truy cập |
| **Địa chỉ (address)** | tên của một điểm truy cập; là một loại tên đặc biệt | một tên có thể gồm nhiều địa chỉ; thực thể đổi địa chỉ trong lúc tồn tại; một địa chỉ có thể trỏ tới thực thể khác nhau ở thời điểm khác nhau |
| **Định danh (identifier)** | loại tên đặc biệt thỏa 3 yêu cầu bên dưới | dùng để truy xuất thực thể |

**Ba yêu cầu của định danh:** (1) một định danh chỉ tới nhiều nhất **một** thực thể; (2) mỗi thực thể chỉ có **một** định danh; (3) một định danh **mãi mãi** chỉ trỏ tới một thực thể (không tái sử dụng). Vấn đề: cạn kiệt không gian tên; giải pháp: mở rộng không gian tên hoặc tái sử dụng.

**Vì sao tách tên khỏi địa chỉ:** địa chỉ đổi (container tạo lại, máy bị thay), còn tên và định danh phải ổn định. Nối chúng là việc của **phân giải tên** (name resolution); mỗi không gian tên cần một cơ chế phân giải khác nhau.

## 2. Các khái niệm trong Smart ERP

| Khái niệm | Trong hệ thống | Ghi chú |
|---|---|---|
| Thực thể | service, container, máy EC2, sản phẩm, chứng từ, sự kiện, request | |
| Tên | `inventory-service` (tên Docker), `erp-inventory` (tên container), `/api/v1/inventory` (đường dẫn Kong), `erp.transportbooking.xyz` (tên miền), Internal Code của sản phẩm | một service có nhiều tên cùng lúc |
| Địa chỉ | `172.18.0.10` (container), `172.31.40.5` (máy A), `13.213.86.128` và `52.77.93.124` (ALB) | đổi khi container tạo lại hay máy ASG bị thay |
| Điểm truy cập | cổng `8004` của inventory: trong mạng Docker, cổng mở ra trên máy chủ, listener `8004` của ALB nội bộ | ba điểm truy cập cho một thực thể |
| Định danh | `product_id`, `event_id`, `X-Correlation-ID`, số chứng từ, ID máy EC2 (`i-0f33...`) | xem mục 3 |

**Ba tính chất của địa chỉ, đều xảy ra thật trong hệ thống:**
1. *Một tên, nhiều địa chỉ:* tên ALB ngoài phân giải ra hai IP; tên ALB nội bộ ra hai IP (`172.31.21.162`, `172.31.34.229`), mỗi vùng khả dụng một địa chỉ.
2. *Thực thể đổi địa chỉ:* container tạo lại thì IP trong `erp-network` có thể đổi (nên Kong chỉ nhớ kết quả DNS 10 giây). Máy ứng dụng trong Auto Scaling group bị thay nhiều lần trong lúc kiểm thử: `i-09817...` → `i-0a2aae...` → `i-03f14...` → `i-0e8bd...`, vai trò vẫn là "một máy ứng dụng".
3. *Một địa chỉ trỏ tới thực thể khác ở thời điểm khác:* IP của container đã dừng có thể được Docker cấp lại cho container khác. Vì vậy **không bao giờ hardcode IP**, mà truy cập bằng tên (CLAUDE.md cấm `localhost:800x` và `127.0.0.1` trong cấu hình liên service).

## 3. Định danh: ba yêu cầu áp vào hệ thống

| Định danh | Ai cấp | Thỏa yêu cầu thế nào |
|---|---|---|
| `product_id` (BIGINT) | catalog-service (DB) | duy nhất trong catalog; **service khác chỉ lưu `product_id`**, không lưu tên |
| **Internal Code** (`ZZ-DEMO-OUTBOX-1114-AOG`) | catalog sinh tự động từ designation + 3 chữ đầu thương hiệu, thêm `-2`, `-3` nếu trùng | là **tên dễ đọc**, không phải định danh dùng tham chiếu chéo service; UNIQUE để không có hai sản phẩm trùng mã |
| `event_id` (UUID) | producer lúc ghi outbox | mỗi sự kiện đúng một UUID; consumer dùng làm khóa loại trùng (`processed_events`) |
| `X-Correlation-ID` (UUID) | Kong sinh khi client không gửi | một request đúng một id, mọi service chuyển tiếp và ghi log |
| Số chứng từ `INV-{mã khách}-{ddmmyy}[-A..]` | sales; ghi vào `document_number_claims` **ngoài transaction** | **yêu cầu 3 (không tái sử dụng):** số đã thất bại không bao giờ cấp lại cho chứng từ khác |
| ID máy EC2 (`i-0f33...`) | AWS | duy nhất toàn cục, không dùng lại |
| Tên node RabbitMQ `rabbit@<hostname>` | cấu hình hostname cố định ([docker-compose.yml](../../../docker-compose.yml)) | định danh gắn với **dữ liệu**: nếu hostname đổi khi tạo lại container, RabbitMQ coi là một node mới, trống, và mất hàng đợi |

**Vì sao UUID cho `event_id` và correlation id, mà không dùng id tự tăng?** Nhiều service cùng sinh id **độc lập**, mỗi service một database riêng, không có nơi cấp số chung. UUID v4 có 122 bit ngẫu nhiên nên xác suất trùng gần như bằng 0 và **không cạn kiệt không gian tên** (vấn đề của bài giảng). Id tự tăng chỉ duy nhất trong một database.
**Tên khác định danh:** Internal Code là tên (dễ đọc, có thể có quy tắc, một thực thể có thể có nhiều tên); `product_id` là định danh. Đó là lý do mọi bảng của service khác lưu `product_id` (CLAUDE.md mục 3: chỉ lưu ID nguyên thủy, có index, không khóa ngoại).

Code: `Str::uuid()` ở [Outbox.php:21](../../../services/sales-service/app/Support/Outbox.php#L21) (cùng file ở catalog, eoffice); Kong sinh correlation id ở [kong.yml:147-151](../../../infrastructure/kong/kong.yml#L147-L151); số chứng từ ở [DocumentNumberGenerator.php:48-64](../../../services/sales-service/app/Support/DocumentNumberGenerator.php#L48-L64); sinh Internal Code ở [ProductController.php:407-418](../../../services/catalog-service/app/Http/Controllers/ProductController.php#L407-L418).

## 4. Ba loại không gian tên

| Loại | Đặc điểm (bài giảng) | Trong hệ thống |
|---|---|---|
| **Phẳng** | chuỗi bit hoặc ký tự không cấu trúc, **không chứa thông tin về vị trí** thực thể | `event_id`, `product_id`, ID máy EC2, tên container `erp-sales` |
| **Có cấu trúc** | phân cấp, đọc được, chứa quan hệ | tên miền `erp.transportbooking.xyz` (đọc từ phải sang trái: `xyz` → `transportbooking` → `erp`); đường dẫn `/api/v1/inventory/stocks` (service → tài nguyên); số chứng từ `INV-{mã khách}-{ddmmyy}`; ARN, `s3://smart-erp-backup/smart-erp` |
| **Theo thuộc tính** | tìm thực thể theo giá trị thuộc tính, không theo tên | trên giao diện lọc sản phẩm theo thương hiệu, danh mục, xuất xứ; Alloy chọn container để gom log theo **nhãn** `com.docker.compose.service` ([config.alloy](../../../infrastructure/monitoring/alloy/config.alloy) dòng 14-21) |

Mỗi loại cần một cơ chế phân giải khác nhau (bài giảng): không gian phẳng không suy ra được vị trí từ tên nên phải tra sổ hoặc hỏi; không gian có cấu trúc phân giải dần theo từng cấp.

## 5. Phân giải tên (tên → địa chỉ)

### 5.1. Từ ngoài vào: DNS phân cấp rồi ALB (chuỗi thật, đo ngày 14/11/2026)

```text
trình duyệt gõ  erp.transportbooking.xyz
 1. máy chủ gốc (root)  → chỉ tới máy chủ của miền  xyz        (generationxyz.nic.xyz, x.nic.xyz, y.nic.xyz)
 2. máy chủ của miền xyz → chỉ tới máy chủ tên của transportbooking.xyz   (ns1.pavietnam.vn, ns2.pavietnam.vn, nsbak.pavietnam.net)
 3. máy chủ tên của PA Việt Nam trả bản ghi CNAME (TTL 300 s):
        erp.transportbooking.xyz  →  erp-alb-516531883.ap-southeast-1.elb.amazonaws.com
 4. tên của ALB phân giải ra hai bản ghi A (TTL 60 s):
        13.213.86.128    52.77.93.124         (mỗi vùng khả dụng một địa chỉ)
 5. trình duyệt kết nối tới một trong hai địa chỉ; ALB chọn máy A hoặc máy ASG còn sống
```
Lấy lại bằng lệnh (nếu `nslookup` bị chặn cổng 53 thì dùng DNS qua HTTPS):
```bash
nslookup erp.transportbooking.xyz
nslookup -type=NS transportbooking.xyz
curl -s "https://dns.google/resolve?name=erp.transportbooking.xyz&type=A"
```
- **Cache nhiều tầng** (bài tập 1 của môn): bộ nhớ trình duyệt, hệ điều hành, máy chủ DNS đệ quy của nhà mạng; mỗi bản ghi có **TTL** riêng (300 s cho CNAME, 60 s cho A). TTL ngắn của ALB là chủ ý: địa chỉ của ALB đổi theo tải và vùng.
- **DNS là phân cấp, phân tán:** không có một bảng tên-địa chỉ tập trung (điều bài giảng nói *không phù hợp mạng cỡ lớn*); quyền phân giải được ủy quyền theo từng cấp. Đó là cách nó đạt yêu cầu "quy mô vô hạn, bền vững, chịu lỗi" của dịch vụ tên.
- Tên miền `erp.transportbooking.xyz` là bản ghi CNAME trỏ tới tên ALB chứ không phải địa chỉ IP; vì vậy khi ALB đổi IP, bản ghi của nhóm không phải sửa.

### 5.2. Trong một máy: DNS nhúng của Docker

```bash
docker compose exec sales-service cat /etc/resolv.conf              # nameserver 127.0.0.11
docker compose exec sales-service getent hosts inventory-service     # 172.18.0.10
```
- Mỗi container hỏi `127.0.0.11` (DNS nhúng của Docker); tên service trong compose được đăng ký tự động khi container chạy; code chỉ biết `http://inventory-service:8004`.
- **Kong nhớ kết quả phân giải 10 giây** (`KONG_DNS_VALID_TTL: "10"`, [docker-compose.yml:799](../../../docker-compose.yml#L799)): khi container đổi địa chỉ, Kong chậm tối đa 10 giây là gọi đúng chỗ mới, thay vì lỗi 502 kéo dài.
- Khi bật `INTERNAL_LB_HOST`, tên đổi từ `inventory-service` sang tên của ALB nội bộ ([docker-compose.ha.yml](../../../docker-compose.ha.yml)); ALB nội bộ phân giải ra hai địa chỉ rồi chọn máy khỏe.

### 5.3. So với các giải pháp của bài giảng

| Giải pháp (bài giảng) | Có trong hệ thống không? |
|---|---|
| **Quảng bá (broadcast)**, ví dụ ARP | Có ở tầng mạng: các container cùng mạng bridge của Docker dùng ARP để biết địa chỉ phần cứng của nhau. Hệ thống không tự cài. |
| **Con trỏ chuyển tiếp (forwarding pointer)** | Gần nhất: ALB chuyển hướng HTTP cổng 80 sang HTTPS bằng mã 301 (listener 80). |
| **Home-based** (có một "nhà" cố định giữ vị trí hiện tại) | Có ý tưởng tương tự: **ALB** có tên và địa chỉ bền, và biết máy nào đang sống để chuyển request tới. Điểm yếu cùng loại với bài giảng: mọi request đi qua "nhà" (ALB), nhưng ALB nhiều vùng nên không phải điểm lỗi đơn. |
| **Bảng băm phân tán (DHT), Chord** | **Không dùng.** Chord dành cho hệ rất lớn, không có sổ đăng ký trung tâm, nút ra vào liên tục. Hệ thống này chỉ có vài chục thực thể tên cố định nên sổ đăng ký tập trung (DNS, Docker DNS, ALB) là đủ và đơn giản. |
| **Phân cấp** | Có: **DNS** (mục 5.1). |

**Chord trong năm dòng (theo bài giảng, để hiểu vì sao không dùng):** các nút xếp trên một vòng định danh `0` đến `2^m − 1`; khóa `k` được đặt ở nút đầu tiên có định danh lớn hơn hoặc bằng `k`, gọi là `successor(k)`; mỗi nút giữ bảng finger gồm `m` mục để tìm khóa trong `O(log N)` bước; khi một nút vào hoặc ra, chỉ các khóa nằm giữa nút đó và nút kế tiếp phải chuyển (consistent hashing). Bài tập 2 của môn yêu cầu tự cài thuật toán này và viết test case; **đề cấm dùng công cụ LLM** để làm bài nộp, và dự án cũng không cài nó.

## 6. Điểm yếu cần thừa nhận (trả lời thật khi bị hỏi)
1. Không có service registry động (Consul, Eureka): Docker DNS và ALB đóng vai trò sổ đăng ký tĩnh; không phân biệt "đã đăng ký" với "đang khỏe" ở tầng DNS (phần khỏe do health check của ALB lo).
2. Docker DNS chỉ phục vụ trong một máy; giữa các máy phải qua ALB nội bộ, và tên ALB nội bộ truyền qua biến `INTERNAL_LB_HOST` (không tự khám phá).
3. Internal Code là tên chứ không phải định danh, nhưng UNIQUE nên người dùng dễ nhầm là mã tham chiếu; tham chiếu chéo service phải luôn bằng `product_id`.
4. `X-Correlation-ID` do client đặt được giữ nguyên, nên chỉ dùng để truy vết, không dùng để phân quyền, và log phải chống chèn dòng.
5. Chuỗi DNS ngoài phụ thuộc vào nhà đăng ký tên miền (PA Việt Nam): máy chủ tên của họ là một điểm phụ thuộc bên ngoài hệ thống AWS.

## 7. OIDC: danh tính của pipeline CI/CD (giữ lại từ chương xác thực)

### 7.1. Vấn đề cần giải
Workflow GitHub Actions phải ra lệnh cho AWS (chạy `ssm send-command` để deploy). Nhưng **GitHub không phải một người dùng AWS**, và AWS không biết "workflow này có đúng của repo `ttung205/smart-erp` không". Cách dễ là dán Access Key dài hạn vào GitHub Secrets: khóa không hết hạn, lộ một lần là kẻ khác dùng được từ bất cứ đâu, và phải tự nhớ xoay khóa. OIDC giải quyết bằng cách để **AWS tin vào chữ ký của GitHub** thay vì tin một mật khẩu chung.

### 7.2. OIDC là gì
- **OAuth 2.0** là chuẩn *ủy quyền* ("cho phép ứng dụng A truy cập tài nguyên B thay mặt tôi"). **OpenID Connect (OIDC)** là lớp *danh tính* xây trên OAuth 2.0: bên cung cấp danh tính (**IdP**) phát ra một **ID token** (một JWT) nói rõ "đây là ai" và ký bằng khóa riêng của nó. Bên nhận (**relying party**) kiểm tra chữ ký bằng **khóa công khai** của IdP rồi tin các thông tin trong token.
- Ở đây **GitHub là IdP** (issuer `https://token.actions.githubusercontent.com`), **AWS là bên nhận**, và "người" cần định danh là **một lần chạy workflow** (thực thể không phải người). Kiểu dùng này gọi là *workload identity federation* (liên kết danh tính cho khối lượng công việc).
- Ví von: GitHub như một cơ quan cấp hộ chiếu; mỗi lần chạy, nó cấp một hộ chiếu *tạm thời* ghi rõ "workflow của repo X, môi trường Y". AWS như hải quan: không cần biết mật khẩu nào, chỉ cần tin con dấu (chữ ký) của cơ quan cấp và đọc xem hộ chiếu có thuộc diện được vào không.

### 7.3. Luồng từng bước trong dự án

```text
GitHub Actions job (environment "production", đã được người duyệt)
  1. xin token  → GitHub ký một JWT (khóa riêng RS256 của GitHub), hiệu lực ngắn:
        iss = https://token.actions.githubusercontent.com
        sub = repo:ttung205/smart-erp:environment:production      (tên có cấu trúc của workflow)
        aud = sts.amazonaws.com
        + repository, ref, workflow, run_id, ...
  2. aws-actions/configure-aws-credentials gọi AWS STS: AssumeRoleWithWebIdentity (kèm token và ARN role)
  3. AWS lấy khóa công khai của GitHub (đã đăng ký OIDC provider trong IAM, tải qua /.well-known/openid-configuration và JWKS)
     → kiểm tra chữ ký token
     → đối chiếu TRUST POLICY của role github-deploy-smart-erp:
           aud phải là sts.amazonaws.com
           sub phải khớp CHÍNH XÁC repo này và environment production
  4. STS trả credential TẠM THỜI (AccessKeyId, SecretAccessKey, SessionToken), tối đa 1 giờ
  5. job dùng credential đó gọi ssm:SendCommand tới các máy; credential tự hết hạn
```
Nhật ký thật của lần deploy ngày 14/11/2026 (run `43555860308`): `role-to-assume: arn:aws:iam::002530139989:role/github-deploy-smart-erp`, `audience: sts.amazonaws.com`, `role-session-name: gha-deploy-43555860308`, rồi `Assuming role with OIDC` và `Authenticated as assumedRoleId AROAQBFWO35KYAG4M6Q5M:gha-deploy-43555860308`, tất cả trong khoảng **1,2 giây**. Tên phiên chứa số run nên mọi lệnh AWS của lần deploy đó truy được về đúng lần chạy.

Xem lại bằng lệnh: `gh run view <id> --log | grep -i "oidc\|assum"`.

### 7.4. Hai lớp kiểm soát
- **Phía AWS (trust policy):** `Principal` là OIDC provider của GitHub; điều kiện `sub` so khớp chính xác (`StringEquals`) với repo và environment `production`, kèm ID số của chủ sở hữu và repo (nên xóa repo rồi tạo lại cùng tên cũng không khớp); `aud` phải là `sts.amazonaws.com`. Chi tiết ở [bài 08](08-devops-aws.md) mục 2.8. (Tài khoản `dev-local` không có quyền đọc IAM nên chưa kiểm tra lại trust policy bằng lệnh; nội dung lấy từ tài liệu bài 08.)
- **Phía GitHub:** vì `sub` có `environment:production`, chỉ job đã được người duyệt cho chạy mới xin được token mang `sub` đó. Cổng duyệt tay vì thế còn là lớp bảo vệ ở phía AWS.
- **Quyền tối thiểu của role:** `ssm:SendCommand` chỉ trên các máy của hệ thống và tài liệu `AWS-RunShellScript` (xem bài 08).

### 7.5. Cấu hình trong repo
- Quyền xin token: [deploy.yml:30-33](../../../.github/workflows/deploy.yml#L30-L33) (`permissions: id-token: write`; thiếu dòng này GitHub không cấp token).
- Bước đăng nhập AWS: [deploy.yml:110-115](../../../.github/workflows/deploy.yml#L110-L115) (`aws-actions/configure-aws-credentials@v4`, `role-to-assume`, `role-session-name: gha-deploy-${{ github.run_id }}`).
- Biến không nhạy cảm của repo: `AWS_REGION`, `AWS_DEPLOY_ROLE_ARN`, `EC2_INSTANCE_ID`, `ASG_NAME` (xem `gh variable list`). **Secrets không có khóa AWS nào.**

### 7.6. So với JWT trong hệ thống của nhóm
| | JWT đăng nhập của người dùng (HS256) | OIDC của GitHub (RS256) |
|---|---|---|
| Khóa | **chung**: mọi service giữ cùng `JWT_SECRET` | **bất đối xứng**: GitHub giữ khóa riêng, AWS chỉ có khóa công khai |
| Lộ khóa ở bên nhận | kẻ có `JWT_SECRET` giả được token | không giả được token vì không có khóa riêng của GitHub |
| Thời hạn | 24 giờ | token hiệu lực ngắn; credential AWS tối đa 1 giờ |
| Ai kiểm tra | từng service tự kiểm | AWS STS, đối chiếu trust policy |

Đây chính là hướng nâng cấp đã nêu cho JWT của người dùng (RS256 và JWKS): OIDC là một ví dụ chạy thật của mô hình đó.

### 7.7. Điểm phải nói được
- OIDC khác OAuth ở chỗ nó **xác nhận danh tính** (ai), không chỉ cấp quyền truy cập.
- Không có bí mật dài hạn nào được lưu: lộ log hay lộ repo cũng không lộ khóa AWS.
- Cẩn thận trust policy: nếu điều kiện `sub` quá rộng (ví dụ `repo:ttung205/*` hoặc mọi nhánh), một workflow ở nhánh khác hay một repo khác cùng chủ có thể lấy được quyền. Hệ thống ghim vào `environment:production` có người duyệt.
- Giới hạn còn lại (nói thật, từ bài 08): environment `production` nên giới hạn chỉ nhánh `main`; repo công khai nên log workflow ai cũng đọc được, không được in bí mật ra log.

## 8. Câu hỏi hay gặp
1. **Tên khác địa chỉ khác định danh thế nào?** Tên là chuỗi dùng để tham chiếu (một thực thể nhiều tên); địa chỉ là tên của một điểm truy cập và **đổi được**; định danh là loại tên đặc biệt chỉ một thực thể, không đổi, không tái sử dụng.
2. **Ba tính chất của địa chỉ?** Một tên nhiều địa chỉ (tên ALB ra hai IP); thực thể đổi địa chỉ (container, máy ASG); một địa chỉ trỏ tới thực thể khác nhau theo thời điểm (IP container cấp lại).
3. **Vì sao dùng UUID cho `event_id`?** Nhiều service sinh độc lập, không có nơi cấp số chung, UUID không cạn kiệt và gần như không trùng.
4. **Internal Code có phải định danh không?** Không, là tên dễ đọc (UNIQUE). Định danh dùng tham chiếu chéo service là `product_id`.
5. **Vì sao số chứng từ không bao giờ cấp lại?** Yêu cầu thứ ba của định danh: số đã gửi đi giữ hàng mà kết quả không chắc (timeout) có thể còn một khoản giữ treo ở inventory dưới số đó (giữ chỗ idempotent theo số chứng từ); cấp lại số ấy cho chứng từ khác sẽ gắn nhầm khoản giữ cũ vào chứng từ mới. Ghi `document_number_claims` ngoài transaction để thất bại (rollback) không xóa dấu việc đã cấp số.
6. **Các loại không gian tên và ví dụ?** Phẳng (UUID, ID máy), có cấu trúc (tên miền, đường dẫn, số chứng từ), theo thuộc tính (lọc sản phẩm theo thương hiệu, nhãn Docker).
7. **Chuyện gì xảy ra khi gõ `erp.transportbooking.xyz`?** Chuỗi ở mục 5.1: root → `.xyz` → `ns1.pavietnam.vn` → CNAME → tên ALB → hai bản ghi A → ALB → máy; có cache và TTL ở từng tầng.
8. **Vì sao không hardcode IP?** IP đổi khi container tạo lại hay máy bị thay; dùng tên và để DNS phân giải.
9. **Vì sao Kong chỉ nhớ DNS 10 giây?** Địa chỉ container đổi; nhớ lâu thì gọi nhầm chỗ cũ và lỗi 502 kéo dài.
10. **Hệ thống có dùng Chord/DHT không? Khi nào cần?** Không. Cần khi có rất nhiều nút, thay đổi liên tục, không thể có sổ đăng ký trung tâm.
11. **ALB có giống home-based không?** Cùng ý tưởng: tên và địa chỉ bền, nó biết vị trí hiện tại của các máy và chuyển request tới.
12. **OIDC là gì, vì sao an toàn hơn Access Key?** Mục 7: token tạm thời do GitHub ký, AWS kiểm chữ ký và trust policy (đúng repo, đúng environment, đúng `aud`), cấp credential tối đa 1 giờ; không có khóa dài hạn để lộ.
13. **OIDC khác OAuth thế nào?** OAuth là ủy quyền; OIDC thêm lớp danh tính (ID token nói "đây là ai").
14. **Trust policy quá rộng thì sao?** Workflow ở nhánh hoặc repo khác có thể assume role; vì vậy `sub` so khớp chính xác và gắn `environment:production` có người duyệt.

## 9. Bài tập thực hành
1. Tự trả lời bài tập 1 của môn bằng chuỗi ở mục 5.1 (nhớ trích nguồn; đề cấm dùng công cụ LLM để viết bài nộp). Chạy `nslookup` và đọc TTL.
2. Trong máy A: `docker compose exec sales-service getent hosts inventory-service`, rồi tạo lại container `docker compose up -d --force-recreate inventory-service` và tra lại: so sánh địa chỉ (có thể đổi), tên vẫn là `inventory-service`. *(Thao tác này khởi động lại inventory trên production nên chỉ làm ở máy dev.)*
3. `getent ahostsv4 <tên ALB nội bộ>`: một tên, hai địa chỉ.
4. `aws autoscaling describe-scaling-activities --auto-scaling-group-name erp-app-asg --max-items 6`: máy ASG đổi ID, vai trò giữ nguyên.
5. `gh run view <id> --log | grep -i "oidc\|assum"`: thấy `Assuming role with OIDC` và tên phiên `gha-deploy-<số run>`.
6. Bài tập 2 (Chord) của môn: tự viết thuật toán, test case và báo cáo.

## 10. Tự kiểm tra (trả lời bằng lời của bạn)
- Phân biệt thực thể, tên, địa chỉ, điểm truy cập, định danh bằng ví dụ trong hệ thống.
- Kể ba yêu cầu của định danh và một ví dụ thỏa từng yêu cầu.
- Kể ba loại không gian tên và ví dụ của từng loại.
- Mô tả chuỗi phân giải của `erp.transportbooking.xyz` và chỉ ra chỗ nào là cache, chỗ nào là TTL.
- Vì sao ALB giống home-based, và vì sao hệ thống không cần Chord?
- Kể năm bước của luồng OIDC và hai điều kiện trong trust policy.
- So sánh OIDC (RS256) với JWT đăng nhập (HS256) về khóa và thời hạn.
