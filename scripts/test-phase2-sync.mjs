import { createHmac } from 'node:crypto';

const JWT_SECRET = 'smart_erp_super_secure_jwt_secret_key_2026';

function generateToken() {
  const encode = (val) => Buffer.from(JSON.stringify(val)).toString('base64url');
  const header = encode({ typ: 'JWT', alg: 'HS256' });
  const payload = encode({
    iss: 'identity-service',
    sub: '1',
    name: 'Administrator',
    roles: ['admin'],
    permissions: ['*'],
    exp: Math.floor(Date.now() / 1000) + 3600,
  });
  const sig = createHmac('sha256', JWT_SECRET).update(`${header}.${payload}`).digest('base64url');
  return `${header}.${payload}.${sig}`;
}

async function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function main() {
  console.log('================================================================');
  console.log('   KIỂM THỬ GIAI ĐOẠN 2: CATALOG -> RABBITMQ -> INVENTORY SYNC   ');
  console.log('================================================================');

  const token = generateToken();
  const testSku = `SYNC-${Date.now().toString().slice(-6)}`;
  console.log(`\n[Bước 1] Chuẩn bị mã SKU thử nghiệm: ${testSku}`);

  // 1. Gọi Catalog Service tạo sản phẩm mới
  console.log('[Bước 2] Gửi POST /api/products sang Catalog Service (:8002)...');
  const createResp = await fetch('http://127.0.0.1:8002/api/products', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Correlation-ID': `corr_test_${Date.now()}`,
    },
    body: JSON.stringify({
      sku: testSku,
      designation: 'Màn hình chuyên đồ họa UltraSharp 32 4K',
      brand: 'Dell Technologies',
      category: 'Màn hình máy tính',
      gross_price: 28500000.0,
      cost_price: 22000000.0,
      com: 'Nhật Bản',
      unit: 'Chiếc',
      min_stock: 12,
    }),
  });

  if (!createResp.ok) {
    const errorText = await createResp.text();
    throw new Error(`Catalog Service trả về lỗi HTTP ${createResp.status}: ${errorText}`);
  }

  const createResult = await createResp.json();
  console.log('[Bước 2 Kết quả]', JSON.stringify(createResult));
  const newProductId = createResult.data.id;
  console.log(`[OK] Đã tạo thành công sản phẩm mới với ID: ${newProductId}, SKU: ${testSku}`);

  // 2. Chờ 2 giây để message truyền qua RabbitMQ và Inventory Service xử lý
  console.log('\n[Bước 3] Đang chờ 2.5s để RabbitMQ truyền event ProductCreated tới Inventory Service...');
  await sleep(2500);

  // 3. Kiểm tra Inventory Service xem đã tự sinh dòng tồn kho chưa
  console.log(`[Bước 4] Tra cứu tồn kho tại Inventory Service (:8004) cho ProductId: ${newProductId}...`);
  const stockResp = await fetch(`http://127.0.0.1:8004/api/v1/inventory/stocks?productId=${newProductId}`, {
    headers: {
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
    },
  });

  if (!stockResp.ok) {
    const errorText = await stockResp.text();
    throw new Error(`Inventory Service trả về lỗi HTTP ${stockResp.status}: ${errorText}`);
  }

  const stockResult = await stockResp.json();
  console.log('[Bước 4 Kết quả]', JSON.stringify(stockResult, null, 2));

  const stocks = stockResult.data || [];
  if (stocks.length === 0) {
    throw new Error(`[FAIL] Không tìm thấy dòng tồn kho nào cho ProductId ${newProductId}!`);
  }

  console.log(`\n[THÀNH CÔNG] Tìm thấy ${stocks.length} bản ghi tồn kho được tự động sinh tại các kho:`);
  for (const item of stocks) {
    console.log(` - Kho ID: ${item.warehouse_id} (${item.warehouse_name || 'N/A'}) | On-Hand: ${item.quantity_on_hand} | Reserved: ${item.quantity_reserved} | Available: ${item.quantity_available} | Xuất xứ: ${item.origin}`);
  }

  console.log('\n================================================================');
  console.log('   GIAI ĐOẠN 2 ĐÃ HOÀN TẤT VÀ XÁC MINH ĐỒNG BỘ 100% THÀNH CÔNG!   ');
  console.log('================================================================\n');
}

main().catch((err) => {
  console.error('\n[LỖI THỰC THI]', err);
  process.exit(1);
});
