import { createHmac } from 'node:crypto';
import { once } from 'node:events';
import { createServer } from 'node:http';
import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { io as Client } from 'socket.io-client';
import { createRealtime } from '../src/realtime/socket.js';

const JWT_SECRET = 'test-jwt-secret-key-12345';

function generateTestToken(userId) {
  const encode = (value) => Buffer.from(JSON.stringify(value)).toString('base64url');
  const header = encode({ typ: 'JWT', alg: 'HS256' });
  const payload = encode({
    iss: 'identity-service',
    sub: String(userId),
    name: `User ${userId}`,
    exp: Math.floor(Date.now() / 1000) + 3600,
  });
  const signature = createHmac('sha256', JWT_SECRET).update(`${header}.${payload}`).digest('base64url');
  return `${header}.${payload}.${signature}`;
}

describe('Socket.IO Realtime Hub', () => {
  it('từ chối kết nối nếu không có hoặc sai JWT token', async () => {
    const server = createServer();
    const realtime = createRealtime(server, { jwtSecret: JWT_SECRET, logger: { log() {}, warn() {}, error() {} } });
    server.listen(0);
    await once(server, 'listening');
    const { port } = server.address();

    const client = Client(`http://127.0.0.1:${port}`, {
      path: '/api/v1/notifications/socket.io',
      transports: ['websocket'],
      auth: { token: 'invalid.token.signature' },
      reconnection: false,
    });

    const [error] = await once(client, 'connect_error');
    assert.match(error.message, /AUTH_TOKEN_INVALID/);

    client.close();
    realtime.io.close();
    server.close();
  });

  it('kết nối thành công với JWT hợp lệ và nhận sự kiện toUser đúng room', async () => {
    const server = createServer();
    const realtime = createRealtime(server, { jwtSecret: JWT_SECRET, logger: { log() {}, warn() {}, error() {} } });
    server.listen(0);
    await once(server, 'listening');
    const { port } = server.address();

    const userId = 42;
    const token = generateTestToken(userId);

    const client = Client(`http://127.0.0.1:${port}`, {
      path: '/api/v1/notifications/socket.io',
      transports: ['websocket'],
      auth: { token },
      reconnection: false,
    });

    await once(client, 'connect');
    assert.equal(client.connected, true);

    const receivedPromise = new Promise((resolve) => {
      client.on('notification:new', (data) => resolve(data));
    });

    // Phát sự kiện tới user 42
    realtime.toUser(userId, 'notification:new', { id: 101, title: 'Đơn hàng mới' });

    const received = await receivedPromise;
    assert.deepEqual(received, { id: 101, title: 'Đơn hàng mới' });

    client.close();
    realtime.io.close();
    server.close();
  });
});
