import { spawn } from 'node:child_process';
import { createHmac } from 'node:crypto';
import { io as Client } from 'socket.io-client';

const JWT_SECRET = 'smart_erp_super_secure_jwt_secret_key_2026';

function generateToken(userId) {
  const encode = (val) => Buffer.from(JSON.stringify(val)).toString('base64url');
  const header = encode({ typ: 'JWT', alg: 'HS256' });
  const payload = encode({
    iss: 'identity-service',
    sub: String(userId),
    name: 'Test Administrator',
    permissions: ['*'],
    exp: Math.floor(Date.now() / 1000) + 3600,
  });
  const sig = createHmac('sha256', JWT_SECRET).update(`${header}.${payload}`).digest('base64url');
  return `${header}.${payload}.${sig}`;
}

async function sleep(ms) {
  return new Promise((resolve) => setTimeout(resolve, ms));
}

async function run() {
  console.log('--- KIỂM TRA KHỞI ĐỘNG NOTIFICATION SERVICE & SOCKET.IO TRÊN CỔNG 8006 ---');

  const child = spawn('node', ['src/index.js'], {
    cwd: process.cwd(),
    env: { ...process.env, PORT: '8006' },
    stdio: ['ignore', 'pipe', 'pipe'],
  });

  child.stdout.on('data', (d) => process.stdout.write(`[Service STDOUT] ${d}`));
  child.stderr.on('data', (d) => process.stderr.write(`[Service STDERR] ${d}`));

  // Đợi service khởi động
  await sleep(1500);

  const token = generateToken(1);
  console.log('[Test Client] Kết nối Socket.IO tới http://127.0.0.1:8006...');

  const socket = Client('http://127.0.0.1:8006', {
    path: '/api/v1/notifications/socket.io',
    transports: ['websocket', 'polling'],
    auth: { token },
    reconnection: false,
    timeout: 5000,
  });

  await new Promise((resolve, reject) => {
    socket.on('connect', () => {
      console.log(`[Test Client] [OK] Đã kết nối Socket.IO thành công! Socket ID = ${socket.id}`);
      resolve();
    });
    socket.on('connect_error', (err) => {
      console.error(`[Test Client] [FAIL] Lỗi kết nối Socket.IO: ${err.message}`);
      reject(err);
    });
  });

  socket.close();
  child.kill('SIGINT');
  await sleep(500);
  console.log('--- HOÀN TẤT THỬ NGHIỆM GIAI ĐOẠN 1 THÀNH CÔNG RỰC RỠ! ---');
  process.exit(0);
}

run().catch((err) => {
  console.error('[Error]', err);
  process.exit(1);
});
