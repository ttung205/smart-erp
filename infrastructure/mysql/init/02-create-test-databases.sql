-- Database dành riêng cho test tự động (PHPUnit, dotnet test). Trống, test tự tạo bảng và dữ liệu.
-- Tách hẳn khỏi erp_*_db để RefreshDatabase không bao giờ xóa dữ liệu dev.
CREATE DATABASE IF NOT EXISTS `erp_identity_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `erp_catalog_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `erp_sales_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `erp_eoffice_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `erp_finance_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `erp_purchase_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS `erp_inventory_test` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Mẫu `erp\_%\_test`: mọi database test đặt tên theo quy ước đều dùng được, không cần cấp quyền lại.
GRANT ALL PRIVILEGES ON `erp\_%\_test`.* TO 'erp_user'@'%';

FLUSH PRIVILEGES;
