# Bài 09. Ngân hàng câu hỏi bảo vệ (theo 8 chương)

Cách dùng: đọc câu hỏi, tự trả lời thành tiếng trong 30 đến 60 giây rồi mới xem đáp án. Mỗi đáp án là bản rút gọn để nói được ngay; phần "Chi tiết" chỉ về bài học có đủ lập luận, file và số đo. Câu nào có số đo thì nêu số đo, thầy tin con số hơn tin lời.

Ba nguyên tắc khi trả lời:
1. **Nói cơ chế, rồi chỉ vào code.** "Em chống bán âm bằng UPDATE có điều kiện, ở file này" tốt hơn "em dùng transaction".
2. **Thừa nhận giới hạn trước khi bị hỏi.** Mỗi chương đều có mục điểm yếu, nêu một điểm yếu kèm hướng khắc phục là điểm cộng.
3. **Tách "đã làm và đã đo" khỏi "hướng phát triển".** Không nhận một thứ chưa có là đã có.

---

## Chương 1. Mở đầu

**1. Hệ phân tán là gì? Project của em phân tán ở chỗ nào?**
Là tập hợp máy hoặc tiến trình độc lập, giao tiếp qua mạng, người dùng thấy như một hệ thống. Smart ERP gồm 10 service (PHP, .NET, Node, Python) mỗi service một database riêng, giao tiếp bằng REST, RabbitMQ và WebSocket; hạ tầng AWS chạy nhiều máy sau một load balancer. Chi tiết: bài 00, mục 1 và 2.

**2. Vì sao chọn microservice thay vì một khối (monolith)?**
ERP có nhiều miền tách bạch (bán, mua, kho, kế toán, nhân sự), mỗi miền do một nhóm phụ trách và chọn được công nghệ hợp: kho dùng .NET vì cần transaction chặt, notification dùng Node vì I/O realtime. Mỗi service deploy và hỏng độc lập. Đổi lại phải giải quyết nhất quán dữ liệu, lỗi mạng, message trùng, giám sát nhiều nơi, và phần lớn công sức project nằm ở đó. Chi tiết: bài 00, mục 6.

**3. Hệ phân tán khó ở những điểm nào, project xử lý thế nào?**
Bốn khó khăn và cách xử lý: không có transaction chung (dùng UPDATE có điều kiện, saga, outbox), mạng có thể đứt (timeout, retry, circuit breaker), message có thể trùng (consumer idempotent), một máy chết không được kéo cả hệ thống (nhiều AZ, ALB, ASG). Chi tiết: bài 04, 06, 08.

**4. Hệ thống có tính trong suốt (transparency) ở mức nào?**
Trong suốt về truy cập và vị trí: người dùng chỉ biết một địa chỉ `erp.transportbooking.xyz`, request đi qua ALB và Kong, không biết service nào ở máy nào. Trong suốt về lỗi ở mức một phần: một máy web chết thì người dùng chỉ thấy lỗi lẻ khoảng 50 giây rồi không còn. Chưa trong suốt hoàn toàn: chat và Grafana chỉ ở máy A.

**5. Mục tiêu thiết kế của hệ thống là gì?**
Tính sẵn sàng (nhiều AZ, tự thay máy), khả năng mở rộng (ASG tăng máy theo tải), nhất quán tồn kho (không bán âm), và dễ vận hành (Docker, CD qua SSM, không có khóa dài hạn). Mỗi mục tiêu có thí nghiệm đo được trong bài 08-chiu-loi.

---

## Chương 2. Kiến trúc

**6. Hệ thống theo kiểu kiến trúc nào?**
Microservices phân lớp: mỗi service theo Clean Architecture (Presentation, Application, Domain, Infrastructure), các service nói chuyện qua API gateway (Kong) và message broker (RabbitMQ). Chi tiết: bài 00, 01.

**7. Vai trò của API gateway Kong?**
Điểm vào duy nhất cho mọi request: định tuyến theo tiền tố (`/api/v1/sales/...`), giới hạn tốc độ, sinh `X-Correlation-ID`, che địa chỉ nội bộ. Service nội bộ (`/internal/*`) không đi qua gateway. Chi tiết: bài 01, mục 2.

**8. Mỗi service một database có lợi gì, bất lợi gì?**
Lợi: cô lập hỏng hóc và thay đổi schema, service sở hữu rõ dữ liệu của mình. Bất lợi: không JOIN và không khóa ngoại chéo, phải lưu bản chụp (snapshot) vào chứng từ và dùng API composition cho báo cáo. Hiện dùng một máy chủ MySQL nhiều schema với tài khoản riêng; cô lập quyền chứ chưa cô lập tài nguyên. Chi tiết: bài 02.

**9. Vì sao MySQL cho nghiệp vụ, MongoDB cho thông báo và chat?**
Nghiệp vụ tiền và kho cần ACID và ràng buộc quan hệ. Thông báo và tin nhắn là tài liệu ghi nhiều, đọc theo người nhận, không cần JOIN. Chi tiết: bài 02, mục 6.

**10. Kiến trúc triển khai trên AWS gồm gì?**
ALB (chứng chỉ ACM) chia request cho máy A và các máy của Auto Scaling group ở hai AZ; RDS MySQL Multi-AZ và EFS nằm trong subnet riêng; bí mật ở SSM Parameter Store; backup lên S3; CD bằng GitHub Actions với OIDC rồi SSM Run Command. Sơ đồ: `images/aws-ha-architecture.png`. Chi tiết: bài 08-devops-aws, mục 1.

**11. Vì sao máy A đứng riêng, không nằm trong ASG?**
Máy A có RabbitMQ, Mongo, relay outbox, chat, notification, Grafana: các thành phần giữ trạng thái hoặc chỉ được chạy một bản. ASG chỉ hợp với tầng không giữ trạng thái. Đây cũng là điểm lỗi đơn còn lại, và em có hướng khắc phục (Amazon MQ, Mongo dịch vụ ngoài, relay nhiều bản có khóa lãnh đạo).

---

## Chương 3. Tiến trình và luồng

**12. Tiến trình và luồng khác nhau thế nào? Project dùng cái nào?**
Tiến trình có bộ nhớ riêng, luồng dùng chung bộ nhớ trong một tiến trình. Laravel dùng nhiều tiến trình (PHP-FPM), .NET dùng thread pool với async/await, Node và Python dùng vòng lặp sự kiện một luồng. Chi tiết: bài 03, mục 3.

**13. Vì sao Node và Python phải tránh việc tính nặng?**
Chỉ có một luồng chạy mã, một đoạn tính nặng chặn mọi người dùng. Bên Python việc nặng được đẩy sang luồng phụ bằng `asyncio.to_thread`.

**14. Quản lý tiến trình thế nào? Việc định kỳ chạy ở đâu?**
Docker thay cho PM2 hay Supervisor: `restart: unless-stopped`, healthcheck và `depends_on`. Đối soát giữ hàng chạy mỗi 5 phút ở container `sales-scheduler` (có `withoutOverlapping`), relay outbox quét mỗi 1,5 giây, sao lưu bằng cron mỗi giờ. Chi tiết: bài 03, mục 4 đến 6.

**15. Tắt êm là gì, vì sao cần?**
Nhận tín hiệu dừng thì ngừng nhận việc mới, làm nốt việc dở rồi thoát (notification dừng consumer và đóng WebSocket trước). Tắt đột ngột có thể bỏ dở message; nhờ consumer idempotent nên giao lại cũng không hại.

---

## Chương 4. Trao đổi thông tin

**16. Vì sao dùng cả REST lẫn message queue?**
Hai bài toán khác nhau. Giữ hàng cần câu trả lời ngay (thiếu hàng thì không lập được chứng từ) nên dùng REST. Thông báo thì không chờ và không được làm sales phụ thuộc vào notification nên dùng event. Chi tiết: bài 04, mục 1.

**17. Transactional outbox giải quyết vấn đề gì?**
"Ghi DB" và "gửi message" là hai việc rời nhau, một trong hai có thể hỏng. Outbox ghi event cùng transaction với dữ liệu: có đơn thì chắc chắn có event. Relay quét `PENDING` rồi gửi đến khi thành công. Chi tiết: bài 04, mục 3.

**18. Message bị gửi hai lần thì sao?**
Bình thường và đã tính trước (giao ít nhất một lần). Consumer ghi `(event_id, consumer_name)` vào `processed_events` cùng transaction với xử lý; trùng thì bỏ qua và ack. Khóa chính của bảng này là lớp chặn cuối khi hai bản đến cùng lúc.

**19. RabbitMQ chết thì sao?**
Nghiệp vụ vẫn chạy: event nằm ở `outbox_events`, relay gửi lại khi broker sống. Đã chạy thật: dừng RabbitMQ, đơn vẫn tạo được (201), event chuyển từ FAILED sang PUBLISHED sau khi bật lại. Chi tiết: bài 08-chiu-loi, TN2.

**20. Gọi REST giữa service có những lớp bảo vệ nào?**
Timeout 2 đến 5 giây, retry tối đa 2 lần chỉ cho lệnh idempotent, circuit breaker (5 lỗi liên tiếp thì mở 30 giây), chuyển tiếp `X-Correlation-ID`, và `503 DEPENDENCY_UNAVAILABLE` thay vì dùng dữ liệu rỗng. Chi tiết: bài 04, mục 2.

---

## Chương 5. Định danh

**21. Các service tìm thấy nhau thế nào?**
Bên trong một máy: DNS nội bộ của Docker (`http://inventory-service:8004`), Kong cũng trỏ bằng tên. Từ ngoài vào: tên miền `erp.transportbooking.xyz` (CNAME tới ALB), ALB định tuyến theo đường dẫn tới target group. URL service khác lấy từ biến `*_SERVICE_URL`, không hardcode. Chi tiết: bài 05, mục 2.

**22. Vì sao mỗi service tự kiểm tra JWT, không hỏi identity?**
Tránh điểm nghẽn và điểm chết đơn (identity sập thì cả hệ không xác thực được), giảm độ trễ. Đổi lại không thu hồi token tức thì được và quyền được chụp vào token lúc đăng nhập.

**23. JWT có mã hóa không? Chống đổi thuật toán thế nào?**
Không, chỉ ký; payload đọc được nên không để bí mật trong đó. Code kiểm tra cứng `alg == HS256` trước khi tính chữ ký, chặn `alg: none`. Hướng cải tiến: RS256 để chỉ identity giữ khóa ký.

**24. GitHub deploy lên AWS không có access key, vì sao an toàn?**
OIDC: GitHub ký token ngắn hạn, trust policy của role chỉ nhận đúng repo và environment `production` (có người duyệt), credential STS hết hạn sau tối đa 1 giờ. Không có khóa dài hạn nào trong GitHub. Chi tiết: bài 08-devops-aws, mục 2.

---

## Chương 6. Đồng bộ hóa

**25. Hai người cùng đặt khi chỉ còn đủ cho một người thì sao?**
`UPDATE ... WHERE quantity >= :qty` chạy nguyên tử: InnoDB khóa hàng, hai UPDATE xếp hàng, người đầu trừ được, người sau có số dòng ảnh hưởng bằng 0 và nhận `409 INSUFFICIENT_STOCK`. Có test 20 luồng song song: đúng 1 thành công, 19 bị từ chối, tồn không âm. Chi tiết: bài 06, mục 3.

**26. Vì sao không dùng khóa phân tán (Redis, ZooKeeper)?**
Mỗi dữ liệu thuộc một service và một database ACID, khóa của database đã đủ mạnh; thêm Redis chỉ thêm điểm hỏng. Nơi duy nhất cần là relay khi chạy nhiều máy, nên relay chỉ chạy ở máy A và các máy ASG tắt relay (`Outbox__RelayEnabled=false`).

**27. Saga khác 2PC chỗ nào?**
2PC khóa tài nguyên ở mọi bên khi chờ coordinator, một bên chết là treo, và MySQL với Mongo hay RabbitMQ không cùng XA. Saga chia thành chuỗi transaction cục bộ, bên nào lỗi thì chạy hành động bù trừ; đổi lại là nhất quán cuối cùng.

**28. Timeout thì Sales không biết Inventory đã giữ hàng chưa, xử lý thế nào?**
Coi là không chắc và chủ động nhả hàng của số chứng từ đó. Nhả idempotent theo số chứng từ và số đã thất bại không bao giờ được cấp lại. Thiếu hàng (409) là kết quả chắc chắn nên không cần nhả. Job đối soát mỗi 5 phút dọn phần lệch còn sót.

**29. Làm sao đảm bảo mỗi chứng từ chỉ ghi sổ kho một lần?**
Sổ cái có `UNIQUE (document_type, document_id, warehouse_id, product_id)`; ghi lại cùng chứng từ với cùng số lượng là idempotent, khác số lượng trả `422 DOCUMENT_ALREADY_POSTED`. UNIQUE là trọng tài cuối cùng khi hai request chạy đua.

---

## Chương 7. Sao lưu

**30. Sao lưu khác replication thế nào? Có Multi-AZ rồi có cần sao lưu không?**
Replication (Multi-AZ) bảo vệ trước hỏng phần cứng nhưng sao chép luôn lỗi của con người: lỡ `DROP TABLE` thì bản chờ cũng mất ngay. Sao lưu là bản chụp tại một thời điểm quá khứ. Hai thứ bổ sung nhau.

**31. Em biết bản sao lưu dùng được không?**
Có lệnh `verify` phục hồi từng schema vào chỗ tạm, so số bảng với manifest rồi xóa; đã diễn tập thảm họa thật (phá dữ liệu, phục hồi, đối chiếu số dòng). Phục hồi 8 schema vào RDS mất 12 giây. Chi tiết: bài 07, mục 4.

**32. RPO và RTO của hệ thống?**
Đo được: phục hồi schema lớn nhất khoảng 84 giây cho cả quy trình. RPO của sao lưu bằng chu kỳ cron (mỗi giờ thì tối đa 1 giờ); RPO của RDS bằng 0 khi mất một AZ (đồng bộ). Muốn RPO nhỏ hơn thì bật binary log.

**33. Bản sao lưu lưu ở đâu, có nhất quán không?**
Bản chống thảm họa ở bucket S3 `smart-erp-backup` (thiết kế: SSE-S3, chặn truy cập công khai, xóa sau 14 ngày, máy A không có quyền xóa; các thiết lập này chưa kiểm chứng lại bằng lệnh `get-*`, xem bài 07 mục 5). Cùng vùng với hệ thống, chưa sao chép chéo vùng. Sao lưu chạy mỗi giờ bằng cron trên máy A. Mỗi schema nhất quán nhờ `--single-transaction`, nhưng các schema được chụp cách nhau vài giây nên không nhất quán xuyên service. Chấp nhận được vì outbox, `processed_events` và job đối soát sửa phần lệch sau phục hồi.

---

## Chương 8. Tính chịu lỗi

**34. Một máy web chết thì người dùng thấy gì?**
Có cửa sổ khoảng 50 giây với một phần request lỗi vì ALB cần vài lần health check mới loại máy; sau đó không còn lỗi. Đã đo trên AWS bằng máy đo gọi site thật mỗi giây (bài 08-chiu-loi, TN A1 và A2). ALB không tự thử lại request đã gửi vào máy chết.

**35. RDS Multi-AZ failover thì ứng dụng ra sao? Vì sao .NET chậm hơn PHP?**
Gián đoạn khoảng 8 giây với PHP và 20 giây với .NET. PHP-FPM mở kết nối mới cho mỗi request nên đi theo DNS mới ngay; .NET giữ connection pool nên các kết nối cũ phải hết thời gian chờ (15 giây) rồi mới nối lại.

**36. Multi-AZ khác read replica thế nào?**
Multi-AZ: bản chờ đồng bộ, chỉ để chuyển khi lỗi, không đọc được. Read replica: bất đồng bộ, đọc được nhưng có độ trễ, dùng để tăng khả năng đọc.

**37. Auto Scaling group làm gì? Khi nào thêm và bớt máy?**
Giữ số máy mong muốn (Min 1, Desired 2, Max 3) và thay máy hỏng, thêm bớt theo tải: target tracking theo số request mỗi máy mỗi phút, mục tiêu 300. Thêm máy cần 3 điểm đo liên tiếp vượt ngưỡng, bớt máy cần 15 điểm đo liên tiếp dưới ngưỡng. Đo được: thêm máy sau khoảng 6 phút kể từ lúc tải bắt đầu, bớt máy sau khoảng 18 phút kể từ lúc tải dừng. Tăng nhanh giảm chậm để tránh dao động.

**38. Máy mới của ASG tự cấu hình thế nào?**
Dựng từ golden AMI (Docker, image đã kéo sẵn, không có `.env`), user data lấy `.env` và thẻ image từ SSM Parameter Store bằng IAM role, mount EFS rồi chạy `deploy-aws.sh`. Đo: user data khoảng 89 giây, cộng khởi động và ALB kiểm tra thành khoảng 2,5 phút kể từ lúc máy bị hủy đến khi máy thay vào lại.

**33a. Có gì không nằm trong bản sao lưu?**
Tệp trên EFS (sales, purchase, eoffice) và tệp chat đều có trong bản sao lưu: `backup.sh` gói mỗi thư mục thành `files/*.tar.gz` rồi đẩy lên S3 cùng bản dump DB, mỗi giờ. Điều chưa có: phiên bản hoá từng tệp (RPO là 1 giờ) và chưa thử `restore --efs` trên AWS thật (đã thử trong container với dữ liệu giả). Queue RabbitMQ cố ý không sao lưu vì outbox còn bản gốc.

**39. Một AZ sập thì sao?**
Máy web ở AZ còn lại tiếp tục phục vụ, ASG tự dựng máy bù ở AZ còn sống, RDS failover sang bản chờ, EFS còn mount target ở AZ kia. Mất AZ 1a thì máy A mất theo nên chat, notification, RabbitMQ, Mongo, Grafana và `sales-scheduler` ngừng: đây là điểm lỗi đơn đã biết. Không mất dữ liệu nghiệp vụ: máy ở 1b vẫn ghi dữ liệu và `outbox_events` trong cùng transaction vào RDS; relay chỉ chạy ở A nên các dòng `PENDING` chờ trong RDS, khi A lên lại thì phát, notification tạo thông báo trễ (không mất, `processed_events` chặn trùng). Hộp thư và tin nhắn (Mongo) nằm trên A, khôi phục từ bản sao lưu mỗi giờ; tệp chat nằm trên EFS nên không mất theo A. Kịch bản này suy luận từ thiết kế, chưa thử thật. Chi tiết: bài 08 mục 5. Web có thể chỉ còn một máy trong khoảng 5 đến 6 phút trước khi máy bù lên.

**39a. (Vặn) Mất AZ 1a, người dùng còn làm được gì và thông báo của họ đi đâu?**
Còn đăng nhập, tạo báo giá, đơn, chứng từ nhờ máy ASG ở 1b và RDS đã chuyển sang bản chờ. Chat và thông báo tức thời ngừng. Sự kiện (ví dụ "báo giá gửi duyệt") đã nằm trong RDS cùng transaction nên không mất; thông báo xuất hiện muộn sau khi A lên lại. Job đối soát giữ hàng (5 phút một lần) cũng dừng nên khoản giữ mồ côi, nếu có, được dọn muộn.

**40. Hệ thống có điểm lỗi đơn nào?**
Máy A (RabbitMQ, Mongo, relay outbox, scheduler, chat, notification, Grafana, sao lưu). Biện pháp hiện tại: event nằm trong outbox nên không mất khi A chết; web vẫn chạy nhờ các máy khác. Hướng khắc phục: Amazon MQ, Mongo dịch vụ ngoài, relay nhiều bản với khóa lãnh đạo.

**41. Triển khai có gây gián đoạn không?**
Cuốn chiếu: máy A trước rồi từng máy InService của ASG, mỗi máy có smoke test, lỗi ở máy nào thì dừng và các máy sau giữ bản cũ. Luôn còn ít nhất một máy phục vụ nhưng mỗi máy có 20 đến 30 giây lỗi một phần, nên chưa phải zero-downtime. Thẻ image chỉ ghi vào SSM khi tất cả máy xong, để máy ASG mới sinh ra không chạy bản chưa kiểm chứng.

**42. Circuit breaker để làm gì, trạng thái thế nào?**
Tránh dồn request vào service đang chết và cạn worker. CLOSED, sau 5 lỗi liên tiếp thì OPEN 30 giây, rồi HALF_OPEN cho một request thử. Lỗi 4xx (như 409 hết hàng) không tính. Mở thì người dùng nhận `503 DEPENDENCY_UNAVAILABLE` ngay thay vì treo 30 giây.

---

## Câu hỏi tổng hợp và "vặn"

**43. Nếu có thêm một tuần, em làm gì tiếp?**
Theo thứ tự rủi ro: (1) loại điểm lỗi đơn ở máy A (Amazon MQ, Mongo dịch vụ ngoài), (2) chỉ số scale tốt hơn (độ trễ hoặc CPU) và warm pool để rút thời gian thêm máy, (3) đưa hạ tầng thành code (Terraform) và cảnh báo CloudWatch qua SNS, (4) đặt EC2 vào subnet riêng với NAT hoặc VPC endpoint, (5) RS256 và refresh token cho JWT.

**44. Đưa các service ra nhiều máy riêng thì khác gì bây giờ?**
Hiện tại là nhân bản: máy nào cũng chạy đủ bộ. Tách theo chức năng (ví dụ ai-service một máy) thì phải thay tên container bằng địa chỉ mà mọi máy phân giải được (DNS riêng của VPC, load balancer nội bộ, hoặc service discovery), đổi `*_SERVICE_URL`, mở security group giữa các máy; timeout, retry và `X-Correlation-ID` đã có sẵn cho lỗi từng phần. Em chưa làm phần này, ghi là hướng phát triển.

**45. Phần nào em tự làm, phần nào dùng sẵn?**
Trả lời trung thực theo phân công nhóm. Với phần AWS và chịu lỗi: em tự dựng và đo (ALB, RDS, EFS, subnet riêng, ASG, CD qua OIDC, các thí nghiệm). Nếu bị hỏi vào dòng code cụ thể, mở file và đọc từng bước thay vì đoán.

**46. Em đã gặp sự cố gì khi triển khai, xử lý ra sao?** (chọn một để kể)
Ví dụ ASG không tự mở rộng khi tạo tải: nguyên nhân là chưa tạo chính sách scaling, em kiểm tra cảnh báo CloudWatch và thấy không có alarm, tạo target tracking rồi chạy lại thì tăng 2 lên 3 sau khoảng 6 phút. Danh sách đầy đủ ở bài 08-devops-aws, mục 6 (sự cố 1 đến 12).

---

## Tự kiểm tra trước khi bảo vệ

- [ ] Nói được trong 1 phút: kiến trúc từ người dùng đến database (vẽ được sơ đồ).
- [ ] Mở được file chứa UPDATE có điều kiện, bảng outbox, `processed_events`, cấu hình ASG.
- [ ] Nhớ 6 con số: 50 giây (mất một máy), 8 và 20 giây (failover PHP, .NET), 6 phút (thêm máy), 18 phút (bớt máy), 12 giây (phục hồi vào RDS).
- [ ] Nêu được 3 điểm yếu kèm hướng khắc phục mà không cần nhìn tài liệu.
- [ ] Biết sửa cho đúng khi nói nhầm giữa "đã làm" và "hướng phát triển".
