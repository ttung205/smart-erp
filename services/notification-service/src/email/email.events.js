/**
 * Gửi email theo event: service nghiệp vụ (Sales, Purchase) phát event *EmailRequested qua outbox,
 * notification-service nhận event, ghi bản ghi email_jobs (QUEUED) rồi đưa job vào hàng đợi erp.email.send.
 * Nội dung (To, CC, tiêu đề, nội dung) do bên phát quyết định; tệp đính kèm chỉ là tham chiếu {source, id},
 * được tải từ service sở hữu tệp lúc gửi (xem attachment.fetcher.js).
 */

export const EMAIL_EVENT_TYPES = ['QuotationEmailRequested', 'SupplierInquiryEmailRequested'];

export const MAX_RECIPIENTS = 50;
export const MAX_ATTACHMENTS = 10;
const ATTACHMENT_SOURCES = ['sales', 'purchase'];
const SIMPLE_EMAIL = /^[^\s@,;<>"'()[\]\\]+@[^\s@,;<>"'()[\]\\]+\.[^\s@,;<>"'()[\]\\]+$/;
const JOB_ID = /^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i;

// Tên hiển thị đi vào header From: bỏ ký tự điều khiển/dấu nháy để không chèn thêm header hay đổi địa chỉ gửi
const displayName = (value) => String(value ?? '').replace(/[\r\n"<>]/g, ' ').trim().slice(0, 100) || 'Smart ERP';
const escapeHtml = (value) => String(value).replace(/[&<>"']/g, (ch) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[ch]);

export class InvalidEmailEvent extends Error {
  constructor(message, jobId = null) {
    super(message);
    this.jobId = jobId;
  }
}

function addressList(value, field, jobId) {
  if (value === undefined || value === null) return [];
  if (!Array.isArray(value)) throw new InvalidEmailEvent(`${field} phải là danh sách địa chỉ.`, jobId);
  return value.map((address) => {
    if (typeof address !== 'string' || !SIMPLE_EMAIL.test(address.trim()) || address.length > 254) {
      throw new InvalidEmailEvent(`Địa chỉ email không hợp lệ trong ${field}.`, jobId);
    }
    return address.trim();
  });
}

/** Chuyển envelope của event thành job gửi mail; ném InvalidEmailEvent nếu dữ liệu không hợp lệ. */
export function buildEmailJob(envelope, { fromAddress }) {
  const email = envelope?.payload?.email;
  const jobId = typeof email?.job_id === 'string' && JOB_ID.test(email.job_id) ? email.job_id.toLowerCase() : null;
  if (!jobId) throw new InvalidEmailEvent('Event thiếu email.job_id hợp lệ.');

  const to = addressList(email.to, 'to', jobId);
  const cc = addressList(email.cc, 'cc', jobId);
  if (to.length === 0) throw new InvalidEmailEvent('Thiếu người nhận (to).', jobId);
  if (to.length + cc.length > MAX_RECIPIENTS) throw new InvalidEmailEvent(`Tối đa ${MAX_RECIPIENTS} người nhận mỗi email.`, jobId);

  const subject = typeof email.subject === 'string' ? email.subject.replace(/[\r\n]+/g, ' ').trim() : '';
  if (!subject) throw new InvalidEmailEvent('Thiếu tiêu đề email.', jobId);

  const attachments = envelope.payload.attachments ?? [];
  if (!Array.isArray(attachments) || attachments.length > MAX_ATTACHMENTS) {
    throw new InvalidEmailEvent(`Tối đa ${MAX_ATTACHMENTS} tệp đính kèm.`, jobId);
  }
  const attachmentRefs = attachments.map((ref) => {
    if (!ATTACHMENT_SOURCES.includes(ref?.source) || !Number.isInteger(ref?.id) || ref.id <= 0) {
      throw new InvalidEmailEvent('Tệp đính kèm tham chiếu không hợp lệ.', jobId);
    }
    return {
      source: ref.source,
      id: ref.id,
      filename: String(ref.filename ?? `attachment-${ref.id}`).replace(/[\r\n"]/g, '_').slice(0, 255),
      contentType: String(ref.content_type ?? 'application/octet-stream').slice(0, 150),
    };
  });

  const body = typeof email.body === 'string' ? email.body : '';
  return {
    id: jobId,
    eventId: envelope.event_id,
    eventType: envelope.event_type,
    mailOptions: {
      from: `"${displayName(email.sender_name)}" <${fromAddress}>`,
      to: to.join(', '),
      cc: cc.length ? cc.join(', ') : undefined,
      subject,
      text: body,
      html: escapeHtml(body).replace(/\r?\n/g, '<br>'),
    },
    attachmentRefs,
  };
}

/** Nhận event gửi mail: ghi email_jobs rồi đưa job vào hàng đợi. Idempotent theo job_id. */
export class EmailEventHandler {
  constructor({ database, enqueue, fromAddress = process.env.MAIL_FROM_ADDRESS || 'sales@thanhtung.vn', logger = console }) {
    this.database = database;
    this.enqueue = enqueue;
    this.fromAddress = fromAddress;
    this.logger = logger;
  }

  canHandle(eventType) {
    return EMAIL_EVENT_TYPES.includes(eventType);
  }

  async handle(envelope) {
    let job;
    try {
      job = buildEmailJob(envelope, { fromAddress: this.fromAddress });
    } catch (error) {
      if (!(error instanceof InvalidEmailEvent)) throw error;
      // Event sai định dạng không thể sửa bằng cách thử lại: ghi nhận lỗi để người gửi thấy trên trạng thái email
      this.logger.warn(`[Email] Bỏ qua ${envelope?.event_type} (${envelope?.event_id}): ${error.message}`);
      if (error.jobId) await this.recordFailure(error.jobId, envelope, error.message);
      return { skipped: 'INVALID_EMAIL_EVENT' };
    }

    const existing = await this.database.emailJobs.findOne({ _id: job.id }, { projection: { status: 1 } });
    if (existing?.status === 'SENT') return { skipped: 'EMAIL_ALREADY_SENT' };

    await this.database.emailJobs.updateOne(
      { _id: job.id },
      {
        $setOnInsert: {
          recipient: job.mailOptions.to,
          subject: job.mailOptions.subject,
          attachments_count: job.attachmentRefs.length,
          status: 'QUEUED',
          source_event_id: job.eventId,
          source_event_type: job.eventType,
          created_at: new Date(),
        },
      },
      { upsert: true },
    );
    // Lỗi hàng đợi (RabbitMQ tạm sập) được ném ra để event thử lại; job đã ghi ở trên nên thử lại vẫn idempotent
    await this.enqueue({ id: job.id, mailOptions: job.mailOptions, attachmentRefs: job.attachmentRefs });
    return { emailQueued: job.id };
  }

  async recordFailure(jobId, envelope, message) {
    await this.database.emailJobs.updateOne(
      { _id: jobId },
      {
        $set: { status: 'FAILED', error: message },
        $setOnInsert: {
          recipient: String(envelope?.payload?.email?.to ?? ''),
          subject: String(envelope?.payload?.email?.subject ?? ''),
          attachments_count: 0,
          source_event_id: envelope?.event_id,
          source_event_type: envelope?.event_type,
          created_at: new Date(),
        },
      },
      { upsert: true },
    );
  }
}
