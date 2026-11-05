# 07. Chương 7: Sao lưu và phục hồi

> Rubric: "Phương pháp sao lưu và phục hồi dữ liệu trong hệ thống phân tán" (1 điểm).
> Gợi ý của rubric: Database Backup (mysqldump), Incremental Backup (rsync, restic), Cloud Storage (S3), Data Replication, Automated Backup (shell script, cron).
> Trạng thái: **đã làm và đo thật trên máy dev** (sao lưu, kiểm chứng, diễn tập thảm hoạ). Phần đẩy lên S3 đã có code và đã thử đường lỗi, sẽ chạy thật khi deploy AWS.

## 1. Cái gì cần sao lưu, cái gì không

Hệ phân tán lưu dữ liệu ở nhiều nơi. Câu hỏi đầu tiên là: **mất cái nào thì không dựng lại được?**

| Dữ liệu | Nơi lưu | Sao lưu? | Lý do |
|---|---|---|---|
| Dữ liệu nghiệp vụ (9 schema `erp_*_db`) | MySQL, volume `mysql_data` | **Có** | Nguồn sự thật, không dựng lại được |
| Thông báo, tin nhắn | MongoDB (`erp_notification_db`, `erp_chat_db`) | **Có** | Lịch sử hội thoại và hộp thư |
| Tệp đính kèm chat | Volume `chat_files` | **Có** | Tệp người dùng tải lên |
| Queue RabbitMQ | Volume `rabbitmq_data` | **Không** | Message chỉ là trung gian; khi mất, `outbox_events` trong MySQL vẫn còn các event chưa gửi (PENDING/FAILED) và relay gửi lại. Cấu trúc queue nằm sẵn trong `definitions.json` |
| Log, metric | Loki, Prometheus | Không | Dữ liệu quan sát, giữ 7 ngày, dựng lại được theo thời gian |
| Mã nguồn, cấu hình | Git | Đã có | Git là bản sao lưu của code |
| Secret | `.env` | **Lưu riêng, ngoài Git** | Mất `.env` thì mất mật khẩu; xem mục 8 |

Câu trả lời chốt: *"Dữ liệu sinh ra từ nghiệp vụ thì sao lưu; dữ liệu trung gian hoặc dựng lại được thì không. RabbitMQ không cần sao lưu vì outbox là bản sao bền của các event chưa gửi."*

## 2. Thiết kế

```
 docker compose run --rm backup                      docker compose run --rm recovery <lệnh>
        │ (tài khoản erp_backup, CHỈ ĐỌC)                  │ (root, chỉ khi người vận hành chủ động)
        ▼                                                   ▼
  mysqldump × 9 schema ─┐                       verify  : thử phục hồi vào schema tạm, rồi xóa
  mongodump × 2 database ├─► ./backups/database-backup-<yyyymmdd>-<NN>/   restore : phục hồi vào dữ liệu THẬT (cần --yes)
  tar tệp chat ─────────┘      │
                                ├─ mysql/*.sql.gz   mongo/*.archive.gz   files/chat_files.tar.gz
                                ├─ SHA256SUMS       manifest.json
                                └─► (tùy chọn) aws s3 sync → s3://bucket/smart-erp/database-backup-<yyyymmdd>-<NN>/
```

| Quyết định | Vì sao |
|---|---|
| Chạy trong container, cùng cách trên máy dev và EC2 | Đúng nguyên tắc 100% Docker; một cách duy nhất |
| `backup` chỉ có tài khoản `erp_backup` (SELECT, SHOW VIEW, TRIGGER, EVENT, LOCK TABLES) | Nguyên tắc đặc quyền tối thiểu: tiến trình chạy hằng đêm không ghi được gì. Đã thử: `DELETE`/`CREATE` bị từ chối (ERROR 1142) |
| Phục hồi tách thành service `recovery` có root | Phục hồi là thao tác nguy hiểm, không nằm chung với tác vụ chạy tự động |
| `mysqldump --single-transaction` | Bản chụp nhất quán của từng schema mà không khoá bảng, nên hệ thống vẫn phục vụ trong lúc sao lưu |
| Mỗi schema/database một tệp | Phục hồi riêng được một schema (vd chỉ `erp_sales_db`) mà không đụng schema khác |
| Ghi vào thư mục `*.partial`, xong mới đổi tên | Bản dang dở không bao giờ bị nhầm là bản hợp lệ |
| Tên `database-backup-yyyymmdd-NN` (NN là số thứ tự trong ngày, tạo bằng `mkdir` nguyên tử) | Dễ đọc, sắp xếp theo thời gian đúng; hai lần sao lưu chạy chồng nhau không bao giờ trùng tên (đã thử: nhận `-01` và `-02`). Ngày tính theo giờ Việt Nam (UTC+7, đổi bằng `BACKUP_DAY_OFFSET_HOURS`); thời điểm chính xác nằm trong `manifest.json` (`created_at` UTC, `created_at_local`) |
| Xoá bản cũ theo `created_at` trong manifest, không theo tên | Đúng với mọi cách đặt tên, và không bao giờ xoá bản vừa tạo |
| `gzip -t` + `SHA256SUMS` + `manifest.json` | Phát hiện tệp hỏng/rỗng ngay lúc sao lưu và sau này |
| Đẩy `manifest.json` lên S3 **sau cùng** | Có manifest trên S3 nghĩa là bản đó đã đủ |
| Giữ 7 ngày cục bộ (`BACKUP_RETENTION_DAYS`), không bao giờ xoá bản mới nhất | Không đầy ổ đĩa, nhưng luôn còn ít nhất một bản |

Code: [infrastructure/backup/backup.sh](../../../infrastructure/backup/backup.sh) (một tệp, bốn lệnh), [Dockerfile](../../../infrastructure/backup/Dockerfile), hai service `backup` và `recovery` trong [docker-compose.yml](../../../docker-compose.yml), tài khoản `erp_backup` trong [03-create-service-users.sh](../../../infrastructure/mysql/init/03-create-service-users.sh).

## 3. Cách dùng

```powershell
docker compose run --rm backup                  # sao lưu ngay
docker compose run --rm backup list             # liệt kê các bản
docker compose run --rm recovery verify         # kiểm chứng bản mới nhất (không đụng dữ liệu thật)

# Phục hồi (NGUY HIỂM, ghi đè). Thiếu --yes thì chỉ in kế hoạch.
docker compose stop sales-service sales-relay sales-scheduler          # dừng service ghi vào schema này
docker compose run --rm recovery restore latest --mysql=erp_sales_db --yes
docker compose up -d sales-service sales-relay sales-scheduler
```
Các lựa chọn của `restore`: `--mysql` (tất cả) hoặc `--mysql=a,b`, `--mongo` hoặc `--mongo=db`, `--files`; bản cụ thể bằng tên (vd `database-backup-20261105-02`) hoặc `latest`.

## 4. Số liệu đo thật (máy dev, dữ liệu mẫu)

| Chỉ số | Kết quả |
|---|---|
| Dữ liệu MySQL | ~515 MB (sales 277 MB, finance 82 MB, inventory 78 MB, analytics 50 MB, ...) |
| Một bản sao lưu | **38 MB** (nén ~13 lần), 9 schema + 2 database Mongo |
| Thời gian sao lưu | **8 đến 15 giây**, hệ thống vẫn chạy |
| `verify` toàn bộ (phục hồi thử cả 9 schema + 2 database Mongo vào chỗ tạm) | **107 đến 160 giây** (hai lần đo, thay đổi theo tải của máy) |
| Phục hồi `erp_sales_db` (277 MB) | **48 giây** |

### Diễn tập thảm hoạ (đã chạy)
Quy trình như vận hành thật, trên `erp_sales_db`:
1. Dừng `sales-service`, `sales-relay`, `sales-scheduler`.
2. Sao lưu (mốc thời gian dữ liệu cuối cùng được bảo vệ).
3. **Phá:** `DELETE` toàn bộ `outbox_events` và `DROP TABLE document_number_claims`.
4. `restore latest --mysql=erp_sales_db --yes`.
5. Bật lại service, smoke test.

| Bảng | Trước | Sau khi phá | Sau khi phục hồi |
|---|---|---|---|
| `outbox_events` | 5 | 0 | **5** |
| `document_number_claims` | 4 | (bảng biến mất) | **4** |
| `invoices` / `quotations` / `sales_orders` | 31 328 / 4 457 / 23 929 | không đổi | **31 328 / 4 457 / 23 929** |

(Lệnh xoá `invoices` trong lần chạy này bị chặn bởi khoá ngoại nên số hoá đơn không đổi từ đầu; mình ghi đúng như đã xảy ra.)

**Kết luận đo được:** lệnh phục hồi **49 giây**; cả quy trình từ lúc dừng service đến lúc chạy lại **84 giây**.

### RTO và RPO là gì, của hệ thống này bao nhiêu?
- **RPO (Recovery Point Objective):** chấp nhận mất tối đa bao nhiêu dữ liệu, tính theo thời gian. Bằng khoảng cách giữa hai lần sao lưu. Sao lưu hằng đêm thì **RPO tối đa 24 giờ**; sao lưu mỗi giờ thì **tối đa 1 giờ**. Đây là lựa chọn của người vận hành (chạy `cron` thưa hay dày), không phải giới hạn kỹ thuật: một lần sao lưu chỉ tốn ~10 giây và không chặn hệ thống.
- **RTO (Recovery Time Objective):** từ lúc sự cố đến lúc dùng lại được. Đo được **~84 giây cho một schema lớn nhất**. Ước tính phục hồi toàn bộ hệ thống (9 schema + Mongo) khoảng **2 đến 3 phút** (số liệu `verify` cho thấy phục hồi tuần tự cả 9 schema mất khoảng 105 giây), cộng thời gian dựng lại máy nếu mất cả máy chủ.
- Các số trên đo trên dữ liệu mẫu ~515 MB; dữ liệu thật lớn hơn thì thời gian tăng gần tuyến tính theo dung lượng.

## 5. Tự động hoá và đưa lên AWS

### Trên EC2 (cron)
```bash
# /etc/cron.d/smart-erp-backup : mỗi giờ một lần, ghi log
0 * * * * ubuntu cd /opt/smart-erp && docker compose run --rm backup >> /var/log/smart-erp-backup.log 2>&1
```
(Chọn "mỗi giờ" thì RPO tối đa 1 giờ.) Trên máy Windows có thể dùng Task Scheduler gọi cùng lệnh.

### Đẩy lên S3 (đã có code, sẽ chạy thật ở bước AWS)
Đặt trong `.env` của server: `BACKUP_S3_URI=s3://<bucket>/smart-erp`. Việc cần làm trên AWS (chưa chạy, làm cùng lúc deploy):
```bash
aws s3api create-bucket --bucket <bucket> --region ap-southeast-1 \
  --create-bucket-configuration LocationConstraint=ap-southeast-1
aws s3api put-public-access-block --bucket <bucket> \
  --public-access-block-configuration BlockPublicAcls=true,IgnorePublicAcls=true,BlockPublicPolicy=true,RestrictPublicBuckets=true
aws s3api put-bucket-encryption --bucket <bucket> \
  --server-side-encryption-configuration '{"Rules":[{"ApplyServerSideEncryptionByDefault":{"SSEAlgorithm":"AES256"}}]}'
# Lifecycle: tự xóa bản sao lưu cũ hơn 14 ngày
aws s3api put-bucket-lifecycle-configuration --bucket <bucket> --lifecycle-configuration \
  '{"Rules":[{"ID":"expire-old-backups","Status":"Enabled","Filter":{"Prefix":"smart-erp/"},"Expiration":{"Days":14}}]}'
```
Quyền tối thiểu cho IAM role của EC2 (không dùng khoá truy cập dài hạn):
```json
{ "Version": "2012-10-17", "Statement": [
  { "Effect": "Allow", "Action": ["s3:PutObject", "s3:GetObject"], "Resource": "arn:aws:s3:::<bucket>/smart-erp/*" },
  { "Effect": "Allow", "Action": "s3:ListBucket", "Resource": "arn:aws:s3:::<bucket>" } ] }
```
**Chi tiết dễ vấp:** container gọi dịch vụ metadata của EC2 để lấy thông tin đăng nhập của role. IMDSv2 mặc định chỉ cho 1 "hop", nên container (thêm một lớp mạng) bị chặn. Phải đặt `http_put_response_hop_limit = 2` khi tạo EC2 (Terraform hoặc `aws ec2 modify-instance-metadata-options`).

Trạng thái kiểm thử đường S3: **chỉ mới thử đường lỗi** (không có thông tin đăng nhập thì thoát mã 2, thông báo rõ, bản cục bộ vẫn còn). Đường thành công chưa chạy vì chưa có bucket.

## 6. Đối chiếu với gợi ý của rubric

| Gợi ý | Có không | Chi tiết |
|---|---|---|
| Database backup (mysqldump) | **Có** | mysqldump + mongodump, kiểm chứng toàn vẹn, kiểm chứng phục hồi, đã diễn tập thảm hoạ |
| Cloud storage (S3) | **Có code, chờ AWS** | `aws s3 sync` + manifest cuối; kèm bucket, mã hoá, lifecycle, IAM tối thiểu |
| Automated backup (cron) | **Có** (cấu hình ở bước deploy) | Một lệnh duy nhất, chạy được bằng cron |
| Incremental backup (rsync, restic) | **Không** | Chỉ sao lưu đầy đủ. Dữ liệu còn nhỏ (38 MB nén) nên sao lưu đầy đủ mỗi giờ vẫn rẻ. Muốn khôi phục đến một thời điểm bất kỳ (PITR) cần bật binary log của MySQL (`log_bin`) rồi phát lại; chưa làm |
| Data replication | **Không đầy đủ** | Mongo chạy replica set nhưng chỉ 1 node (phục vụ transaction của chat, không nhân bản dữ liệu). MySQL không có replica. Hướng phát triển: RDS Multi-AZ, replica read |

## 7. Thầy có thể hỏi

**H: Sao lưu và replication khác nhau thế nào? Có replication rồi có cần sao lưu không?**
Đ: Replication bảo vệ trước **hỏng phần cứng** (máy chết thì bản sao thay thế) nhưng sao chép luôn cả **lỗi của con người**: lỡ `DROP TABLE` thì bản sao cũng mất ngay. Sao lưu là bản chụp tại một thời điểm trong quá khứ nên quay lại trước khi lỗi xảy ra được. Hai thứ bổ sung nhau, không thay nhau.

**H: Em biết bản sao lưu dùng được không?**
Đ: Một bản sao lưu chưa từng phục hồi thử thì chưa phải bản sao lưu. Bọn em có lệnh `verify` phục hồi từng schema vào chỗ tạm, so số bảng với manifest rồi xoá, và đã diễn tập thảm hoạ thật: phá dữ liệu, phục hồi, đối chiếu số dòng.

**H: RPO và RTO của hệ thống?**
Đ: RTO đo được khoảng 84 giây cho schema lớn nhất (cả quy trình dừng, phục hồi, bật lại); RPO bằng chu kỳ cron, mỗi giờ thì tối đa 1 giờ. Muốn RPO thấp hơn thì bật binary log để phát lại thay đổi sau bản sao lưu.

**H: Bản sao lưu lưu ở đâu? Máy chủ cháy thì sao?**
Đ: Cục bộ chỉ để phục hồi nhanh. Bản chống thảm hoạ nằm ở S3 (khác hạ tầng với EC2), mã hoá, chặn truy cập công khai, có vòng đời tự xoá sau 14 ngày.

**H: Trong lúc sao lưu có làm hệ thống ngừng không? Dữ liệu có nhất quán không?**
Đ: Không ngừng, nhờ `--single-transaction`: mỗi schema là một bản chụp nhất quán tại một thời điểm. Nhưng các schema được chụp ở các thời điểm cách nhau vài giây, nên **không nhất quán xuyên service**. Điều này chấp nhận được vì các service vốn đã chấp nhận nhất quán cuối cùng: sau phục hồi, outbox gửi lại event còn thiếu, `processed_events` chặn xử lý trùng, và job đối soát giữ hàng dọn phần lệch.

**H: Phục hồi xong có vấn đề gì về nhất quán giữa các service?**
Đ: Có thể có lệch nhỏ (vd Inventory đã giữ hàng cho một chứng từ mà Sales vừa phục hồi về trước thời điểm đó). Job đối soát chạy 5 phút một lần sẽ nhả khoản giữ mồ côi (xem bài 06). Đây là lý do thiết kế các thao tác đều idempotent.

## 8. Điểm yếu (nói thật khi bị hỏi)

- **Chỉ sao lưu đầy đủ, không tăng dần, không PITR.** RPO bằng chu kỳ cron.
- **Bản cục bộ nằm cùng máy với dữ liệu** (`./backups`). Mất máy là mất cả hai nếu chưa đẩy lên S3. Vì vậy S3 là phần bắt buộc ở production.
- **Sao lưu không nhất quán xuyên schema** (xem câu hỏi trên).
- **Mongo không có PITR:** `mongodump` chụp từng collection ở thời điểm khác nhau (không dùng `--oplog`).
- **Không mã hoá bản cục bộ** (S3 có mã hoá phía server). Bản sao lưu chứa dữ liệu thật nên thư mục `backups/` bị `.gitignore` chặn và phải được bảo vệ như `.env`.
- **Chưa có cảnh báo khi sao lưu thất bại hoặc quá cũ.** Hướng làm: kiểm tra tuổi của `LATEST` rồi đẩy lên Prometheus.
- **Bí mật (`.env`) không nằm trong bản sao lưu** và không nằm trong Git. Mất `.env` thì không đăng nhập được MySQL bằng các tài khoản cũ. Ở AWS sẽ đặt secret vào SSM Parameter Store.
- **Mới thử phục hồi trên dữ liệu mẫu ở máy dev,** chưa thử trên EC2.

## 9. Thực hành (để có ảnh cho báo cáo)

1. **Sao lưu và xem kết quả:** `docker compose run --rm backup`, rồi mở `backups\database-backup-<yyyymmdd>-<NN>\manifest.json` và xem `SHA256SUMS`.
2. **Kiểm chứng:** `docker compose run --rm recovery verify`, chụp phần "KIỂM TRA THÀNH CÔNG".
3. **Diễn tập thảm hoạ nhỏ** (an toàn, trên bảng ít dữ liệu):
   ```powershell
   docker compose stop sales-service sales-relay sales-scheduler
   docker compose run --rm backup
   # Đăng nhập Adminer bằng root, chạy: DELETE FROM erp_sales_db.outbox_events;  rồi kiểm tra bảng trống
   docker compose run --rm recovery restore latest --mysql=erp_sales_db --yes     # đo thời gian
   docker compose up -d sales-service sales-relay sales-scheduler
   ```
   Mở Adminer, `SELECT COUNT(*) FROM outbox_events;` thấy dữ liệu trở lại. Ghi thời gian vào báo cáo.
4. **Thử an toàn:** chạy `restore` **không có** `--yes` để thấy nó chỉ in kế hoạch và thoát mã 3, không đụng gì.

## 10. Tự kiểm tra
- [ ] Giải thích RPO và RTO, nêu số đo được và cách giảm RPO.
- [ ] Phân biệt sao lưu với nhân bản dữ liệu, cho ví dụ nên dùng cái nào.
- [ ] Nói được vì sao không sao lưu RabbitMQ và dữ liệu nào cần sao lưu.
- [ ] Giải thích vì sao `backup` chỉ có quyền đọc còn phục hồi tách riêng.
- [ ] Nói được vì sao `verify` quan trọng hơn bản thân việc sao lưu.
- [ ] Kể 4 điểm yếu của phần sao lưu mà không nhìn tài liệu.
