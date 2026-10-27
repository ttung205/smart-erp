import { Router } from 'express';
import nodemailer from 'nodemailer';
import { EMAIL_LOG_LIST_PERMISSIONS, EMAIL_STATUS_PERMISSIONS, requireAnyEmailPermission } from './email.auth.js';

/** Express 4 không tự bắt lỗi của handler async. */
const wrap = (handler) => (req, res, next) => Promise.resolve(handler(req, res, next)).catch(next);

const EMAIL_LOG_FIELDS = (doc) => ({
  id: doc._id,
  messageId: doc.message_id ?? null,
  to: doc.recipient,
  subject: doc.subject,
  attachmentsCount: doc.attachments_count ?? 0,
  status: doc.status,
  createdAt: doc.created_at.toISOString(),
  sentAt: doc.sent_at ? doc.sent_at.toISOString() : null,
  error: doc.error ?? null,
});

export function getTransporter() {
  const host = process.env.SMTP_HOST || '127.0.0.1';
  const port = Number(process.env.SMTP_PORT) || 1025;
  const user = (process.env.SMTP_USER || '').trim();
  const pass = (process.env.SMTP_PASS || '').replace(/\s+/g, '');
  const config = { host, port, secure: port === 465, ignoreTLS: port === 1025 || port === 1026 };
  if (user && pass) config.auth = { user, pass };
  return nodemailer.createTransport(config);
}

/**
 * Chỉ tra cứu: email được gửi bằng cách phát event *EmailRequested (xem email.events.js), không còn API gửi trực tiếp.
 */
export function createEmailRouter({ database }) {
  const router = Router();
  router.get('/logs', requireAnyEmailPermission(EMAIL_LOG_LIST_PERMISSIONS), wrap(async (_req, res) => {
    const docs = await database.emailJobs.find().sort({ created_at: -1 }).limit(100).toArray();
    res.json({ success: true, data: docs.map(EMAIL_LOG_FIELDS) });
  }));
  router.get('/logs/:id', requireAnyEmailPermission(EMAIL_STATUS_PERMISSIONS), wrap(async (req, res) => {
    const doc = await database.emailJobs.findOne({ _id: req.params.id });
    return doc ? res.json({ success: true, data: EMAIL_LOG_FIELDS(doc) }) : res.status(404).json({ success: false, error: { message: 'Email job không tồn tại.' } });
  }));
  return router;
}

/**
 * Gửi một job trong hàng đợi. Idempotent: job đã SENT thì bỏ qua. Tệp đính kèm được tải lúc gửi;
 * lỗi tải hoặc lỗi SMTP được ném ra để hàng đợi thử lại.
 */
export async function sendQueuedEmail(database, job, transporter = getTransporter(), fetchAttachment = null) {
  const existing = await database.emailJobs.findOne({ _id: job.id }, { projection: { status: 1 } });
  if (existing?.status === 'SENT') return;
  const { to, subject } = job.mailOptions;
  const refs = job.attachmentRefs ?? [];
  await database.emailJobs.updateOne(
    { _id: job.id },
    { $setOnInsert: { recipient: to, subject, attachments_count: refs.length, status: 'QUEUED', created_at: new Date() } },
    { upsert: true },
  );

  if (refs.length > 0 && !fetchAttachment) throw new Error('Chưa cấu hình bộ tải tệp đính kèm.');
  const attachments = await Promise.all(refs.map((ref) => fetchAttachment(ref)));
  const info = await transporter.sendMail({ ...job.mailOptions, attachments });
  await database.emailJobs.updateOne(
    { _id: job.id },
    { $set: { status: 'SENT', message_id: info.messageId, sent_at: new Date(), error: null } },
  );
}

export async function markEmailFailed(database, job, error) {
  await database.emailJobs.updateOne(
    { _id: job.id },
    {
      $set: { status: 'FAILED', error: error.message },
      $setOnInsert: {
        recipient: job.mailOptions.to,
        subject: job.mailOptions.subject,
        attachments_count: job.attachmentRefs?.length || 0,
        created_at: new Date(),
      },
    },
    { upsert: true },
  );
}
