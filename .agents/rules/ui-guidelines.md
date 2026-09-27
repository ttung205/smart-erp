# QUY ĐỊNH GIAO DIỆN (UI & DESIGN SYSTEM GUIDELINES)
> **Dự án**: Smart ERP (Clean Canvas & Enterprise B2B)  
> **Phiên bản áp dụng**: v2.0+  
> **Phạm vi**: 100% màn hình, component và module Frontend

---

## 1. NGUYÊN TẮC CỐT LÕI (CORE PHILOSOPHY)

Hệ thống Smart ERP là phần mềm B2B phục vụ tác vụ quản trị doanh nghiệp chuyên sâu. Trọng tâm của giao diện là:
* **Hiệu suất & Tốc độ thao tác**: Tối đa hóa diện tích hiển thị dữ liệu (High Information Density), giảm thao tác thừa.
* **Clean Canvas (Nền sáng phẳng - Tinh gọn)**: Phân tách cấu trúc bằng đường viền siêu mỏng (hairline border) và khoảng cách (spacing), không dùng hiệu ứng trang trí lòe loẹt.
* **Nhất quán 100%**: Mọi màn hình phải tuân thủ chặt chẽ cùng một bộ token và component tiêu chuẩn.

---

## 2. 100% SỬ DỤNG COMPONENT TỪ PRIMEVUE

Tuyệt đối **KHÔNG** tự viết lại hoặc dùng thẻ HTML thuần với CSS tự chế cho những thành phần mà PrimeVue đã hỗ trợ.

| Nhóm Component | PrimeVue Component Bắt Buộc | Quy Định Áp Dụng |
| :--- | :--- | :--- |
| **Nút bấm** | `<Button>` | Dùng các variant: `severity="primary"`, `"secondary"`, `"danger"`, `"success"`, `"warn"`. Dùng `outlined` hoặc `text` khi không phải nút hành động chính. Không viết `<button class="...">` tự chế. |
| **Bảng dữ liệu** | `<DataTable>`, `<Column>`, `<Paginator>` | Chuẩn hóa bảng quản trị, thanh cuộn, phân trang, lọc và sắp xếp. |
| **Nhập liệu (Input)** | `<InputText>`, `<InputNumber>`, `<Textarea>`, `<IconField>`, `<InputIcon>` | Toàn bộ trường nhập form. Không tự style thẻ `<input>`. |
| **Lựa chọn** | `<Select>` (Dropdown), `<MultiSelect>`, `<Checkbox>`, `<RadioButton>`, `<ToggleSwitch>` | Quản lý lựa chọn danh mục, trạng thái, bộ lọc. |
| **Thời gian & Ngày** | `<DatePicker>` | Chuẩn hóa chọn ngày/khoảng ngày. |
| **Khung chứa (Layout)** | `<Card>`, `<Panel>`, `<Divider>`, `<Toolbar>` | Khung bao ngoài của màn hình hoặc phân chia vùng chức năng. |
| **Hộp thoại & Lớp phủ** | `<Dialog>`, `<ConfirmDialog>`, `<Drawer>` | Dùng cho form thêm/sửa nhanh và xác nhận thao tác nguy hiểm. |
| **Thông báo & Trạng thái** | `<Toast>`, `<Message>`, `<Tag>`, `<Badge>` | Thông báo tức thời và hiển thị trạng thái dữ liệu (badge soft-toned). |
| **Điều hướng** | `<Breadcrumb>`, `<Tabs>`, `<TabList>`, `<TabPanel>`, `<Menu>` | Điều hướng cấp màn hình và nội bộ trang. |

---

## 3. CÁC QUY TẮC CẤM TUYỆT ĐỐI (STRICT PROHIBITIONS)

### ❌ 1. CẤM GRADIENT (No Gradients)
* **Quy định**: Cấm hoàn toàn `linear-gradient`, `radial-gradient` trên bất kỳ thành phần nào (background trang, sidebar, button, card header, text gradient).
* **Chuẩn áp dụng**: Sử dụng màu đơn sắc (solid colors):
  * Nền canvas: `#F8FAFC` (Slate-50)
  * Nền card/panel: `#FFFFFF` (Pure White)
  * Nút Primary: `#4F46E5` (Indigo-600)
  * Text tiêu đề: `#0F172A` (Slate-900)

### ❌ 2. CẤM GLASSMORPHISM (No Glassmorphism / Frosted Glass)
* **Quy định**: Cấm thuộc tính `backdrop-filter: blur()`, cấm nền bán trong suốt làm mờ nội dung phía sau kiểu trang trí tiêu dùng (consumer app).
* **Chuẩn áp dụng**: Mọi container, sidebar, header, modal phải có màu nền đục rõ ràng (`#ffffff` 100% opacity), phân tách ranh giới mạch lạc.

### ❌ 3. CẤM CARD LỒNG CARD (No Nested Cards)
* **Quy định**: Mỗi vùng nội dung chỉ có **tối đa 1 cấp Card duy nhất** bao bọc ngoài cùng. Tuyệt đối không lồng `<Card>` vào trong một `<Card>` khác. Không tạo các box viền lồng nhau nhiều tầng gây nặng nề thị giác.
* **Chuẩn áp dụng**:
  * Bên trong Card chính: Phân chia các nhóm thông tin bằng khoảng cách (`gap`), tiêu đề nhóm (`<h4>`), hoặc đường kẻ `<Divider />`.
  * Không tạo hiệu ứng "hộp trong hộp trong hộp".

### ❌ 4. CẤM ĐỔ BÓNG TRÀN LAN (No Shadow Pollution)
* **Quy định**: Cấm lạm dụng `box-shadow` đậm hoặc gán shadow lên từng card, button, table row, input.
* **Chuẩn áp dụng**:
  * Giao diện ưu tiên **phẳng (Flat Layout)**.
  * Phân cách các vùng bằng **hairline border**: `border: 1px solid var(--border-subtle)` (`#E2E8F0`).
  * Chỉ chấp nhận shadow cực nhẹ (`--shadow-sm`) cho card tổng thể hoặc shadow của floating elements (Dialog/Modal, Toast, Dropdown Menu khi mở).

---

## 4. DESIGN TOKENS CHỐT SẴN (LOCKED DESIGN TOKENS)

### 4.1. Bo góc (Border Radius): CHỐT CỨNG 6px
* Toàn bộ các thành phần trong hệ thống phải có bo góc đồng nhất: **`6px`** (`0.375rem`).
* Áp dụng cho:
  * `<Button>`, `<InputText>`, `<Select>`, form controls: `border-radius: 6px;`
  * `<Card>`, `<Panel>`, `<Dialog>`: `border-radius: 6px;`
  * `<Tag>`, `<Badge>` trạng thái: `border-radius: 6px;` (hoặc 4px cho micro badge)
  * Header của `<DataTable>`: `border-radius: 6px 6px 0 0;`
* **Ngoại lệ duy nhất**: Avatar người dùng hoặc Status Dot tròn (chỉ thị online/offline) được phép dùng `50%` (`rounded-full`). Tuyệt đối không dùng `rounded-xl` (12px), `rounded-2xl` (16px) hay pill-shaped button.

### 4.2. Hệ Spacing (Unified 4px/8px Spacing Scale)
Hệ thống sử dụng duy nhất một thang khoảng cách chuẩn bội số của 4px / 8px. Không dùng các số lẻ tùy hứng (như 7px, 9px, 11px, 13px, 17px, 21px).

| Token | Giá trị | Rem | Tình huống sử dụng bắt buộc |
| :--- | :--- | :--- | :--- |
| **`space-1`** | `4px` | `0.25rem` | Khoảng cách micro: Icon với text bên trong button/badge, padding badge nhỏ. |
| **`space-2`** | `8px` | `0.5rem` | Khoảng cách label đến input field, padding compact, gap giữa các filter chips. |
| **`space-3`** | `12px` | `0.75rem` | Khoảng cách giữa các nút trong button group, padding ô bảng dữ liệu thu gọn. |
| **`space-4`** | `16px` | `1.0rem` | Padding chuẩn của Card/Form container, gap giữa các form row, grid gap chuẩn. |
| **`space-5`** | `20px` | `1.25rem` | Padding mở rộng cho các modal, panel chính. |
| **`space-6`** | `24px` | `1.5rem` | Khoảng cách giữa các section lớn trong trang, padding lề trang. |
| **`space-8`** | `32px` | `2.0rem` | Khoảng cách phân đoạn chính của màn hình. |

---

## 5. CHUẨN HÓA VĂN PHONG (HUMANIZE COPYWRITING)

Tất cả chữ hiển thị (label, button text, placeholder, thông báo hệ thống, nội dung AI sinh ra) phải là **tiếng Việt thực tế chuẩn nghiệp vụ ERP**:

### 5.1. Dứt khoát, trực diện vào hành động
* **ĐÚNG**: "Thêm mới sản phẩm", "Lưu thay đổi", "Xác nhận duyệt đơn", "Hủy bỏ", "Xuất file Excel", "Đặt lại bộ lọc".
* **SAI**: "Trình nộp dữ liệu", "Tiết kiệm", "Nộp", "Nhận được kết quả", "Bấm vào đây để...".

### 5.2. Trạng thái rỗng (Empty States) thân thiện & có hướng dẫn
* **ĐÚNG**: "Chưa có đơn hàng nào trong phân hệ này. Nhấn 'Tạo đơn mới' để bắt đầu." hoặc "Không tìm thấy dữ liệu phù hợp với bộ lọc hiện tại."
* **SAI**: "Trống rỗng!", "Không có gì ở đây cả!", "No data found."

### 5.3. Thông báo rõ ràng (Actionable Alerts & Toasts)
* **ĐÚNG**: "Đã cập nhật bảng giá đối tác thành công." / "Mã nhà cung cấp 'NCC-004' đã tồn tại trong hệ thống. Vui lòng kiểm tra lại."
* **SAI**: "Thành công!", "Lỗi hệ thống 500!", "Có lỗi xảy ra, thử lại sau."

### 5.4. Cấm từ ngữ AI sáo rỗng
* Tuyệt đối cấm văn phong quảng cáo, hoa mỹ sáo rỗng: "Khám phá sức mạnh quản trị tối thượng", "Trải nghiệm tính năng ma thuật", "Giải pháp đỉnh cao nâng tầm doanh nghiệp".

---

## 6. MỖI MÀN HÌNH CHỈ VIẾT ĐÚNG TITLE MÀN ĐÓ (PAGE TITLE ISOLATION)

### 6.1. Quy tắc một tiêu đề duy nhất
* Mỗi màn hình/view chỉ có **đúng 1 tiêu đề chính (Primary Page Title)** nằm ở vị trí Page Header / Breadcrumb trên cùng của nội dung chính (Main Content).
* Ví dụ:
  * Màn hình danh sách: `<h1>Danh Mục Sản Phẩm</h1>`
  * Màn hình chi tiết: `<h1>Đơn Mua Hàng #PO-2024-001</h1>`

### 6.2. Cấm lặp lại tiêu đề (No Title Duplication)
* Khi ở trên header của trang đã có tiêu đề `Danh Mục Sản Phẩm`, bên dưới trong thẻ `<Card>` hoặc `<DataTable>` **KHÔNG ĐƯỢC** viết lại một thẻ `<h2>` hoặc `<h3>` có chữ `Danh Mục Sản Phẩm`.
* Trong Card hoặc DataTable, chỉ dùng thanh Toolbar chứa các công cụ: Tìm kiếm, Bộ lọc trạng thái, Nút Thêm mới, Nút Xuất dữ liệu.

### 6.3. Không slogan, không mô tả dài dòng
* Dưới tiêu đề trang không chèn các câu văn dài dòng mang tính trang trí.
* Nếu thực sự cần ghi chú phạm vi, chỉ dùng tối đa 1 dòng subtitle ngắn gọn với màu `var(--text-muted)`:
  * Ví dụ: `<p class="page-subtitle">Quản lý định danh SKU, nhóm ngành hàng và chính sách giá bán buôn.</p>`

### 6.4. Tiêu đề Modal / Drawer phải chính xác theo ngữ cảnh
* Khi mở Dialog/Drawer, tiêu đề phải nói rõ hành động: "Thêm Mới Khách Hàng", "Cập Nhật Thông Tin #KH-012", "Xác Nhận Hủy Đơn Hàng", không để tiêu đề mập mờ ("Thông tin", "Thao tác", "Chi tiết").

---

## 7. BẢNG CHECKLIST KIỂM DUYỆT TRƯỚC KHI XUẤT CODE

Mỗi khi sinh hoặc sửa bất kỳ component/màn hình nào, bắt buộc rà soát checklist:

- [ ] **100% PrimeVue**: Nút bấm là `<Button>`, bảng là `<DataTable>`, form input là `<InputText>`/`<Select>`...
- [ ] **Không Gradient**: Không có bất kỳ dải màu chuyển đổi nào trong CSS/Style.
- [ ] **Không Glassmorphism**: Không dùng `backdrop-filter: blur()`, không để nền mờ trong suốt.
- [ ] **Không Card lồng Card**: Tối đa 1 cấp Card bọc ngoài, cấu trúc bên trong phẳng.
- [ ] **Không Shadow tràn lan**: Không gán shadow dày đặc; ưu tiên hairline border `1px solid #E2E8F0`.
- [ ] **Bo góc chuẩn 6px**: Toàn bộ input, button, card, modal đều dùng `border-radius: 6px`.
- [ ] **Hệ Spacing 4px/8px**: Padding, margin, gap là bội số của 4/8 (4, 8, 12, 16, 20, 24, 32px).
- [ ] **Văn phong chuẩn tiếng Việt**: Gãy gọn, chuyên nghiệp, không từ dịch máy, không văn AI sáo rỗng.
- [ ] **Title độc nhất**: Đúng 1 tiêu đề cho toàn màn hình, không lặp lại tiêu đề ở Card con bên dưới.
