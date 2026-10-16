import { Server } from 'socket.io';
import { verifyToken } from '../auth/jwt.js';

/**
 * Socket.IO server: client gửi JWT ở handshake (auth.token), mỗi user vào room user:{id}.
 * Socket chỉ là kênh báo hiệu; dữ liệu gốc luôn đọc lại được qua REST.
 */
export function createRealtime(httpServer, { jwtSecret, logger = console }) {
  const io = new Server(httpServer, {
    path: '/api/v1/notifications/socket.io',
    cors: { origin: true, credentials: true },
  });

  io.use((socket, next) => {
    const actor = verifyToken(socket.handshake.auth?.token, jwtSecret);
    if (!actor) return next(new Error('AUTH_TOKEN_INVALID'));
    socket.data.actor = actor;
    return next();
  });

  io.on('connection', (socket) => {
    const { actor } = socket.data;
    socket.join(`user:${actor.id}`);
    logger.log(`[Realtime] User ${actor.id} kết nối (${socket.id})`);
  });

  return {
    io,
    toUser(userId, event, data) {
      io.to(`user:${userId}`).emit(event, data);
    },
    broadcast(event, data) {
      io.emit(event, data);
    },
  };
}
