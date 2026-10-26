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

export function createEmailRouter({ db, enqueue }) {
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
      db.prepare(`INSERT INTO email_jobs (id, recipient, subject, attachments_count, status, created_at)
        VALUES (?, ?, ?, ?, 'QUEUED', ?) ON CONFLICT(id) DO NOTHING`)
        .run(id, recipient, subject, attachments.length, new Date().toISOString());
      return res.status(202).json({ success: true, data: { id, to, subject, status: 'QUEUED' } });
    } catch (error) {
      console.error('[NotificationService] Email queue error:', error);
      return res.status(503).json({ success: false, error: { message: 'Không thể đưa email vào hàng đợi.' } });
    }
  });

  router.get('/logs', requireAnyEmailPermission(EMAIL_LOG_LIST_PERMISSIONS), (_req, res) => {
    const rows = db.prepare(`SELECT id, message_id AS messageId, recipient AS "to", subject,
      attachments_count AS attachmentsCount, status, created_at AS createdAt, sent_at AS sentAt, error
      FROM email_jobs ORDER BY created_at DESC LIMIT 100`).all();
    res.json({ success: true, data: rows });
  });
  router.get('/logs/:id', requireAnyEmailPermission(EMAIL_STATUS_PERMISSIONS), (req, res) => {
    const row = db.prepare(`SELECT id, message_id AS messageId, recipient AS "to", subject,
      attachments_count AS attachmentsCount, status, created_at AS createdAt, sent_at AS sentAt, error
      FROM email_jobs WHERE id = ?`).get(req.params.id);
    return row ? res.json({ success: true, data: row }) : res.status(404).json({ success: false, error: { message: 'Email job không tồn tại.' } });
  });
  return router;
}

export async function sendQueuedEmail(db, job, transporter = getTransporter()) {
  const existing = db.prepare('SELECT status FROM email_jobs WHERE id = ?').get(job.id);
  if (existing?.status === 'SENT') return;
  const { to, subject, attachments = [] } = job.mailOptions;
  db.prepare(`INSERT INTO email_jobs (id, recipient, subject, attachments_count, status, created_at)
    VALUES (?, ?, ?, ?, 'QUEUED', ?) ON CONFLICT(id) DO NOTHING`)
    .run(job.id, to, subject, attachments.length, new Date().toISOString());
  const info = await transporter.sendMail(job.mailOptions);
  db.prepare(`UPDATE email_jobs SET status = 'SENT', message_id = ?, sent_at = ?, error = NULL WHERE id = ?`)
    .run(info.messageId, new Date().toISOString(), job.id);
}

export function markEmailFailed(db, job, error) {
  db.prepare(`INSERT INTO email_jobs (id, recipient, subject, attachments_count, status, error, created_at)
    VALUES (?, ?, ?, ?, 'FAILED', ?, ?) ON CONFLICT(id) DO UPDATE SET status = 'FAILED', error = excluded.error`)
    .run(job.id, job.mailOptions.to, job.mailOptions.subject, job.mailOptions.attachments?.length || 0,
      error.message, new Date().toISOString());
}
