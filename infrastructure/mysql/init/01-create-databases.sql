-- Smart Distributed ERP: tạo schema cho từng service (database-per-service)
-- SCRIPT AUTO-RUNS ON FIRST DOCKER MYSQL BOOT

-- 1. Identity Service Database
CREATE DATABASE IF NOT EXISTS `erp_identity_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 2. Catalog Service Database (Master Data)
CREATE DATABASE IF NOT EXISTS `erp_catalog_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 3. Purchase Service Database
CREATE DATABASE IF NOT EXISTS `erp_purchase_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 4. Inventory Service Database (Stock Engine, Ledger)
CREATE DATABASE IF NOT EXISTS `erp_inventory_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 5. Sales Service Database (Quotations, Orders, Deliveries)
CREATE DATABASE IF NOT EXISTS `erp_sales_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 6. E-Office Service Database (Approvals, Requests)
CREATE DATABASE IF NOT EXISTS `erp_eoffice_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 7. Analytics & AI Service Database
CREATE DATABASE IF NOT EXISTS `erp_analytics_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 8. Finance Service Database
CREATE DATABASE IF NOT EXISTS `erp_finance_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- 9. Chat Service Database (khi chuyển từ SQLite sang MySQL)
CREATE DATABASE IF NOT EXISTS `erp_chat_db` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- Quyền truy cập: mỗi service một tài khoản riêng, xem 03-create-service-users.sh
