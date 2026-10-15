import { createHmac } from 'node:crypto';
import { io as Client } from 'socket.io-client';

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
  console.log('========================================================================');
  console.log('   KIỂM THỬ GIAI ĐOẠN 3: BACKEND-TO-FRONTEND REALTIME SYNC QUA SOCKET   ');
  console.log('========================================================================');

  const token = generateToken();
  const testSku = `REALTIME-${Date.now().toString().slice(-6)}`;
  console.log(`\n[Bước 1] Chuẩn bị token và SKU kiểm thử: ${testSku}`);

  // 1. Kết nối Socket.IO tới Notification Hub (:8006)
  console.log('[Bước 2] Thiết lập kết nối Socket.IO tới http://127.0.0.1:8006...');
  const socket = Client('http://127.0.0.1:8006', {
    path: '/api/v1/notifications/socket.io',
    transports: ['websocket', 'polling'],
    auth: { token },
    reconnection: false,
    timeout: 5000,
  });

  await new Promise((resolve, reject) => {
    socket.on('connect', () => {
      console.log(`[Bước 2 OK] Socket.IO đã kết nối! Socket ID = ${socket.id}`);
      resolve();
    });
    socket.on('connect_error', (err) => {
      reject(new Error(`Lỗi kết nối Socket.IO: ${err.message}`));
    });
  });

  // 2. Đăng ký lắng nghe sự kiện catalog:product_created
  let receivedProduct = null;
  const productCreatedPromise = new Promise((resolve, reject) => {
    const timer = setTimeout(() => {
      reject(new Error('Hết thời gian chờ (timeout 10s) không nhận được sự kiện catalog:product_created qua socket!'));
    }, 10000);

    socket.on('catalog:product_created', (data) => {
      clearTimeout(timer);
      receivedProduct = data;
      console.log('\n>>> [REALTIME EVENT NHẬN ĐƯỢC TỪ SOCKET.IO] <<<');
      console.log(JSON.stringify(data, null, 2));
      resolve(data);
    });
  });

  // 3. Kích hoạt tạo sản phẩm mới ở Catalog Service
  console.log('\n[Bước 3] Gửi yêu cầu tạo sản phẩm mới tới Catalog Service (:8002)...');
  const createResp = await fetch('http://127.0.0.1:8002/api/products', {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Accept': 'application/json',
      'Authorization': `Bearer ${token}`,
      'X-Correlation-ID': `corr_rt_${Date.now()}`,
    },
    body: JSON.stringify({
      sku: testSku,
      designation: 'Bàn phím cơ không dây Custom Pro X',
      brand: 'Keychron Master',
      category: 'Phụ kiện máy tính',
      gross_price: 3650000.0,
      cost_price: 2500000.0,
      com: 'Đài Loan',
      unit: 'Chiếc',
      min_stock: 20,
    }),
  });

  if (!createResp.ok) {
    const errText = await createResp.text();
    throw new Error(`Catalog Service trả về lỗi HTTP ${createResp.status}: ${errText}`);
  }

  const createResult = await createResp.json();
  console.log('[Bước 3 OK] Sản phẩm đã được lưu tại Catalog:', JSON.stringify(createResult));

  // 4. Chờ sự kiện broadcast qua socket
  console.log('\n[Bước 4] Đang chờ Socket.IO bắn broadcast sự kiện realtime tới Client (không cần F5)...');
  await productCreatedPromise;

  if (receivedProduct.sku !== testSku) {
    throw new Error(`SKU nhận được (${receivedProduct.sku}) không khớp với SKU đã tạo (${testSku})!`);
  }

  console.log(`\n[XÁC MINH HOÀN TẤT] SKU [${receivedProduct.sku}] - "${receivedProduct.name}" đã được đẩy realtime tới Client thành công!`);

  socket.close();
  console.log('\n========================================================================');
  console.log('   GIAI ĐOẠN 3 ĐÃ HOÀN TẤT VÀ KIỂM THỬ REALTIME 100% THÀNH CÔNG!         ');
  console.log('========================================================================\n');
}

main().catch((err) => {
  console.error('\n[LỖI]', err);
  process.exit(1);
});
