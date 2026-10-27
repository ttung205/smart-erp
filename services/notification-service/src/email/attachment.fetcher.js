/** Tổng dung lượng tệp đính kèm tối đa của một email (khớp giới hạn upload 20 MB của Sales cho từng tệp). */
export const MAX_ATTACHMENT_BYTES = 25 * 1024 * 1024;

/**
 * Tải tệp đính kèm từ service sở hữu bằng khóa nội bộ. Lỗi (service tạm sập, tệp chưa có) được ném ra
 * để hàng đợi gửi mail thử lại; thử lại hết số lần thì email được ghi FAILED kèm lý do.
 */
export function createAttachmentFetcher({ services, internalServiceKey, fetchImpl = fetch, timeoutMs = 15000 }) {
  const urlOf = {
    sales: (id) => `${services.sales}/api/internal/attachments/${id}/download`,
    purchase: (id) => `${services.purchase}/internal/inquiry-attachments/${id}`,
  };

  return async function fetchAttachment(ref) {
    const build = urlOf[ref.source];
    if (!build) throw new Error(`Nguồn tệp đính kèm không hỗ trợ: ${ref.source}`);

    const response = await fetchImpl(build(ref.id), {
      headers: { 'X-Internal-Service-Key': internalServiceKey },
      signal: AbortSignal.timeout(timeoutMs),
    });
    if (!response.ok) {
      throw new Error(`Không tải được tệp "${ref.filename}" từ ${ref.source}-service (HTTP ${response.status}).`);
    }
    const declared = Number(response.headers?.get?.('content-length') ?? 0);
    if (declared > MAX_ATTACHMENT_BYTES) throw new Error(`Tệp "${ref.filename}" vượt giới hạn dung lượng.`);

    const content = Buffer.from(await response.arrayBuffer());
    if (content.length > MAX_ATTACHMENT_BYTES) throw new Error(`Tệp "${ref.filename}" vượt giới hạn dung lượng.`);
    return { filename: ref.filename, content, contentType: ref.contentType };
  };
}
