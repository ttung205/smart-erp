import { Router } from 'express';
import nodemailer from 'nodemailer';

// In-memory email logs for audit and tracking
const emailLogs = [];

// Create reusable Nodemailer transporter
export function getTransporter() {
  const host = process.env.SMTP_HOST || '127.0.0.1';
  const port = Number(process.env.SMTP_PORT) || 1025; // 1025 is default Mailpit SMTP port
  const user = (process.env.SMTP_USER || '').trim();
  const pass = (process.env.SMTP_PASS || '').replace(/\s+/g, '');

  const config = {
    host,
    port,
    secure: port === 465,
    ignoreTLS: port === 1025 || port === 1026, // ignore TLS for local Mailpit / Mailtrap
  };

  if (user && pass) {
    config.auth = { user, pass };
  }

  return nodemailer.createTransport(config);
}

export const emailRouter = Router();

// Send Email Endpoint (Sync API)
emailRouter.post('/send', async (req, res) => {
  try {
    const {
      to,
      cc,
      bcc,
      subject,
      text,
      html,
      body,
      attachments = [],
      senderName = 'Smart ERP - Công ty Thanh Tùng'
    } = req.body;

    if (!to) {
      return res.status(400).json({
        success: false,
        error: { message: 'Địa chỉ người nhận (to) là bắt buộc.' }
      });
    }

    if (!subject) {
      return res.status(400).json({
        success: false,
        error: { message: 'Tiêu đề email (subject) là bắt buộc.' }
      });
    }

    const fromAddress = process.env.MAIL_FROM_ADDRESS || 'sales@thanhtung.vn';
    const from = `"${senderName}" <${fromAddress}>`;

    // Process attachments (support base64 content, buffers, or direct paths)
    const formattedAttachments = (attachments || []).map((att) => {
      if (att.content && typeof att.content === 'string' && att.content.startsWith('data:')) {
        // Data URL format
        const base64Data = att.content.split(',')[1];
        return {
          filename: att.filename || 'attachment.pdf',
          content: Buffer.from(base64Data, 'base64'),
          contentType: att.contentType || 'application/pdf'
        };
      } else if (att.content && typeof att.content === 'string') {
        // Base64 string or plain text
        return {
          filename: att.filename || 'attachment.pdf',
          content: Buffer.from(att.content, att.encoding === 'base64' ? 'base64' : 'utf-8'),
          contentType: att.contentType || 'application/pdf'
        };
      }
      return att;
    });

    const mailOptions = {
      from,
      to: Array.isArray(to) ? to.join(', ') : to,
      cc: Array.isArray(cc) ? cc.join(', ') : cc,
      bcc: Array.isArray(bcc) ? bcc.join(', ') : bcc,
      subject,
      text: text || body || '',
      html: html || (body ? body.replace(/\n/g, '<br>') : ''),
      attachments: formattedAttachments
    };

    const transporter = getTransporter();
    const info = await transporter.sendMail(mailOptions);

    const logEntry = {
      id: Date.now(),
      messageId: info.messageId,
      to,
      subject,
      attachmentsCount: formattedAttachments.length,
      sentAt: new Date().toISOString(),
      status: 'SENT'
    };
    emailLogs.unshift(logEntry);
    if (emailLogs.length > 100) emailLogs.pop();

    return res.json({
      success: true,
      data: {
        messageId: info.messageId,
        to,
        subject,
        sentAt: logEntry.sentAt,
        response: info.response
      }
    });
  } catch (error) {
    console.error('[NotificationService] Lỗi gửi email:', error);
    return res.status(500).json({
      success: false,
      error: {
        message: error.message || 'Không thể gửi email.',
        details: error.stack
      }
    });
  }
});

// Email Logs Endpoint
emailRouter.get('/logs', (req, res) => {
  res.json({
    success: true,
    data: emailLogs
  });
});
