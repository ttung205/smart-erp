import { randomUUID } from 'node:crypto';
import { Router } from 'express';
import nodemailer from 'nodemailer';
import { EMAIL_LOG_LIST_PERMISSIONS, EMAIL_SEND_PERMISSIONS, EMAIL_STATUS_PERMISSIONS, requireAnyEmailPermission } from './email.auth.js';

const MAX_RECIPIENTS = 50;

const recipientCount = (value) => (Array.isArray(value) ? value.length : value ? 1 : 0);
// Tên hiển thị đi vào header From: bỏ ký tự điều khiển/dấu nháy để không chèn thêm header hay đổi địa chỉ gửi
const displayName = (value) => String(value ?? '').replace(/[\r\n"<>]/g, ' ').trim().slice(0, 100) || 'Smart ERP';

export function getTransporter() {
  const host = process.env.SMTP_HOST || '127.0.0.1';
  const port = Number(process.env.SMTP_PORT) || 1025;
  const user = (process.env.SMTP_USER || '').trim();
  const pass = (process.env.SMTP_PASS || '').replace(/\s+/g, '');
  const config = { host, port, secure: port === 465, ignoreTLS: port === 1025 || port === 1026 };
  if (user && pass) config.auth = { user, pass };
  return nodemailer.createTransport(config);
}

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

export function createEmailRouter({ database, enqueue }) {
  const router = Router();
  router.post('/send', requireAnyEmailPermission(EMAIL_SEND_PERMISSIONS), async (req, res) => {
    const { to, cc, bcc, subject, text, html, body, attachments = [], senderName = 'Smart ERP' } = req.body;
    if (!to || (Array.isArray(to) && to.length === 0)) {
      return res.status(400).json({ success: false, error: { message: 'Địa chỉ người nhận (to) là bắt buộc.' } });
    }
    if (!subject) {
      return res.status(400).json({ success: false, error: { message: 'Tiêu đề email (subject) là bắt buộc.' } });
    }
    if (!Array.isArray(attachments)) {
      return res.status(400).json({ success: false, error: { message: 'attachments phải là một danh sách.' } });
    }

    if (recipientCount(to) + recipientCount(cc) + recipientCount(bcc) > MAX_RECIPIENTS) {
      return res.status(422).json({ success: false, error: { message: `Tối đa ${MAX_RECIPIENTS} người nhận mỗi email.` } });
    }

    const id = randomUUID();
    const recipient = Array.isArray(to) ? to.join(', ') : to;
    const mailOptions = {
      from: `"${displayName(senderName)}" <${process.env.MAIL_FROM_ADDRESS || 'sales@thanhtung.vn'}>`,
      to: recipient,
      cc: Array.isArray(cc) ? cc.join(', ') : cc,
      bcc: Array.isArray(bcc) ? bcc.join(', ') : bcc,
      subject,
      text: text || body || '',
      html: html || (body ? body.replace(/\n/g, '<br>') : ''),
      attachments: attachments.map((att) => {
        if (typeof att.content !== 'string') return att;
        const dataUrl = att.content.startsWith('data:');
        return {
          filename: att.filename || 'attachment.pdf',
          content: dataUrl ? att.content.split(',')[1] : att.content,
          encoding: dataUrl ? 'base64' : (att.encoding === 'base64' ? 'base64' : 'utf-8'),
          contentType: att.contentType || 'application/pdf',
        };
      }),
    };

    try {
      await enqueue({ id, mailOptions });
      await database.emailJobs.updateOne(
        { _id: id },
        { $setOnInsert: { recipient, subject, attachments_count: attachments.length, status: 'QUEUED', created_at: new Date() } },
        { upsert: true },
      );
      return res.status(202).json({ success: true, data: { id, to, subject, status: 'QUEUED' } });
    } catch (error) {
      console.error('[NotificationService] Email queue error:', error);
      return res.status(503).json({ success: false, error: { message: 'Không thể đưa email vào hàng đợi.' } });
    }
  });

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

export async function sendQueuedEmail(database, job, transporter = getTransporter()) {
  const existing = await database.emailJobs.findOne({ _id: job.id }, { projection: { status: 1 } });
  if (existing?.status === 'SENT') return;
  const { to, subject, attachments = [] } = job.mailOptions;
  await database.emailJobs.updateOne(
    { _id: job.id },
    { $setOnInsert: { recipient: to, subject, attachments_count: attachments.length, status: 'QUEUED', created_at: new Date() } },
    { upsert: true },
  );
  const info = await transporter.sendMail(job.mailOptions);
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
        attachments_count: job.mailOptions.attachments?.length || 0,
        created_at: new Date(),
      },
    },
    { upsert: true },
  );
}
