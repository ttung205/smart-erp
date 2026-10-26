/**
 * Rule registry: event_type → cách sinh thông báo.
 *
 *   category    'task' (việc cần làm) | 'info'
 *   recipients  (payload) => { userIds?, permissions? }  — người thực hiện (actor) luôn bị loại trừ
 *   actionKey   (payload) => khóa việc cần làm; cùng khóa thì 1 người xử lý xong là xong cho tất cả
 *   resolves    (payload) => các actionKey được đóng khi event này đến
 *
 * Thêm loại thông báo mới chỉ cần thêm rule ở đây + binding routing key trong RabbitMQ.
 */

const money = (amount, currency = 'VND') =>
  amount == null ? '' : new Intl.NumberFormat('vi-VN', { style: 'currency', currency: currency || 'VND', maximumFractionDigits: 0 }).format(amount);

const actorName = (p) => p.actor?.name || 'Hệ thống';
const userIds = (...ids) => ids.filter((id) => Number.isInteger(id));
const withReason = (text, reason) => (reason ? `${text} Lý do: ${reason}` : text);

const quotationKey = (p) => `quotation:${p.quotation_id}:approve`;
const orderKey = (p) => `order:${p.order_id}:approve`;
const deliveryKey = (p) => `delivery:${p.delivery_request_id}:approve`;
const dispatchKey = (p) => `dispatch:${p.dispatch_id}:process`;
const leaveKey = (p) => `leave:${p.id}:approve`;
const attendanceKey = (p) => `attendance:${p.id}:approve`;
const payrollApproveKey = (p) => `payroll:${p.period_id}:approve`;
const payrollPayKey = (p) => `payroll:${p.period_id}:pay`;
const officeKey = (kind) => (p) => `office:${kind}:${p.id}:approve`;

const officeRules = (kind, label, path) => {
  const key = officeKey(kind);
  return {
    [`Office${kind}RequestSubmitted`]: {
      category: 'task',
      recipients: (p) => ({ userIds: userIds(p.approver?.id) }),
      title: (p) => `${label} ${p.code} cần duyệt`,
      body: (p) => `${p.requester_name} đề nghị ${money(p.total_amount)}: ${p.title}`,
      link: () => `${path}?scope=to_approve`,
      actionKey: key,
    },
    [`Office${kind}RequestApproved`]: {
      category: 'info',
      recipients: (p) => ({ userIds: userIds(p.requester_id) }),
      title: (p) => `${label} ${p.code} đã được duyệt`,
      body: (p) => withReason(`${actorName(p)} đã duyệt đề nghị.`, p.comment),
      link: (p) => `${path}/${p.id}`,
      resolves: (p) => [key(p)],
    },
    [`Office${kind}RequestRejected`]: {
      category: 'info',
      recipients: (p) => ({ userIds: userIds(p.requester_id) }),
      title: (p) => `${label} ${p.code} bị từ chối`,
      body: (p) => withReason(`${actorName(p)} đã từ chối đề nghị.`, p.comment),
      link: (p) => `${path}/${p.id}`,
      resolves: (p) => [key(p)],
    },
    [`Office${kind}RequestReturned`]: {
      category: 'info',
      recipients: (p) => ({ userIds: userIds(p.requester_id) }),
      title: (p) => `${label} ${p.code} bị trả lại`,
      body: (p) => withReason(`${actorName(p)} trả lại đề nghị để bổ sung.`, p.comment),
      link: (p) => `${path}/${p.id}`,
      resolves: (p) => [key(p)],
    },
    [`Office${kind}RequestCancelled`]: {
      category: 'info',
      recipients: () => ({}),
      resolves: (p) => [key(p)],
    },
  };
};

export const rules = {
  ...officeRules('Purchase', 'Đề nghị mua sắm', '/eoffice/purchase-requests'),
  ...officeRules('Payment', 'Đề nghị thanh toán', '/eoffice/payment-requests'),
  ItemInquiryRequested: {
    category: 'task',
    recipients: (p) => ({ userIds: userIds(p.assignee_id, p.watcher_id) }),
    title: (p) => `Yêu cầu hỏi hàng ${p.code} cần xử lý`,
    body: (p) => `${actorName(p)} đã gửi yêu cầu hỏi hàng.`,
    link: (p) => `/purchase/item-inquiries/${p.request_id}`,
    actionKey: (p) => `item-inquiry:${p.request_id}:process`,
  },
  ItemInquiryResponded: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requested_by, p.assignee_id, p.watcher_id) }),
    title: (p) => `Yêu cầu hỏi hàng ${p.code} đã phản hồi`,
    body: () => 'Các item đã có kết quả hỏi hàng và gross price mới.',
    link: (p) => `/sale/item-inquiries/${p.request_id}`,
    resolves: (p) => [`item-inquiry:${p.request_id}:process`],
  },
  // ── Báo giá ──────────────────────────────────────────────
  QuotationSubmitted: {
    category: 'task',
    recipients: () => ({ permissions: ['quotation.approve'] }),
    title: (p) => `Báo giá ${p.quotation_number} cần phê duyệt`,
    body: (p) => `${actorName(p)} gửi duyệt báo giá cho ${p.customer_name || 'khách hàng'} · ${money(p.total_amount, p.currency)}`,
    link: (p) => `/quotation/${p.quotation_id}`,
    actionKey: quotationKey,
  },
  QuotationApproved: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.created_by_id) }),
    title: (p) => `Báo giá ${p.quotation_number} đã được duyệt`,
    body: (p) => `${actorName(p)} đã phê duyệt báo giá cho ${p.customer_name || 'khách hàng'}.`,
    link: (p) => `/quotation/${p.quotation_id}`,
    resolves: (p) => [quotationKey(p)],
  },
  QuotationRejected: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.created_by_id) }),
    title: (p) => `Báo giá ${p.quotation_number} bị từ chối`,
    body: (p) => withReason(`${actorName(p)} đã từ chối báo giá.`, p.reason),
    link: (p) => `/quotation/${p.quotation_id}`,
    resolves: (p) => [quotationKey(p)],
  },

  // ── Đơn bán hàng ─────────────────────────────────────────
  SalesOrderSubmitted: {
    category: 'task',
    recipients: () => ({ permissions: ['order.approve'] }),
    title: (p) => `Đơn hàng ${p.order_number} cần phê duyệt`,
    body: (p) => `${actorName(p)} gửi duyệt đơn bán hàng cho ${p.customer_name || 'khách hàng'} · ${money(p.total_amount, p.currency)}`,
    link: (p) => `/order/${p.order_id}`,
    actionKey: orderKey,
  },
  SalesOrderApproved: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.created_by_id) }),
    title: (p) => `Đơn hàng ${p.order_number} đã được duyệt`,
    body: (p) => `${actorName(p)} đã phê duyệt đơn bán hàng cho ${p.customer_name || 'khách hàng'}.`,
    link: (p) => `/order/${p.order_id}`,
    resolves: (p) => [orderKey(p)],
  },
  SalesOrderRejected: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.created_by_id) }),
    title: (p) => `Đơn hàng ${p.order_number} bị từ chối`,
    body: (p) => withReason(`${actorName(p)} đã từ chối đơn bán hàng.`, p.reason),
    link: (p) => `/order/${p.order_id}`,
    resolves: (p) => [orderKey(p)],
  },

  // ── Yêu cầu giao hàng: gửi người có quyền duyệt giao hàng ─
  DeliveryRequested: {
    category: 'task',
    recipients: () => ({ permissions: ['delivery.manage'] }),
    title: (p) => `Yêu cầu giao hàng ${p.request_number} cần duyệt`,
    body: (p) => `${actorName(p)} tạo yêu cầu giao hàng cho đơn ${p.order_number || ''} (${p.customer_name || 'khách hàng'}) từ ${p.warehouse_name || 'kho'}.`,
    link: () => '/delivery-request',
    actionKey: deliveryKey,
  },
  DeliveryApproved: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.created_by_id) }),
    title: (p) => `Yêu cầu giao hàng ${p.request_number} đã được duyệt`,
    body: (p) => `${actorName(p)} đã duyệt yêu cầu giao hàng cho đơn ${p.order_number || ''}.`,
    link: () => '/delivery-request',
    resolves: (p) => [deliveryKey(p)],
  },
  DeliveryRejected: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.created_by_id) }),
    title: (p) => `Yêu cầu giao hàng ${p.request_number} bị từ chối`,
    body: (p) => `${actorName(p)} đã từ chối yêu cầu giao hàng cho đơn ${p.order_number || ''}.`,
    link: () => '/delivery-request',
    resolves: (p) => [deliveryKey(p)],
  },

  // ── Yêu cầu xuất kho: gửi người có quyền kho ─────────────
  WarehouseDispatchRequested: {
    category: 'task',
    recipients: () => ({ permissions: ['inventory.manage', 'inventory.issue'] }),
    title: (p) => `Yêu cầu xuất kho ${p.invoice_number}`,
    body: (p) => `${actorName(p)} gửi chứng từ ${p.invoice_number} xuống ${p.warehouse_name || 'kho'} · khách ${p.customer_name || ''}.`,
    link: () => '/warehouse-request-sale',
    actionKey: dispatchKey,
  },

  // ── Nghỉ phép: người duyệt là line manager trong payload ─
  LeaveRequestSubmitted: {
    category: 'task',
    recipients: (p) => ({ userIds: userIds(p.approver?.id) }),
    title: (p) => `Đơn nghỉ phép ${p.code} cần duyệt`,
    body: (p) => `${p.requester_name} xin nghỉ: ${p.title}`,
    link: () => '/eoffice/leave?scope=to_approve',
    actionKey: leaveKey,
  },
  LeaveRequestApproved: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requester_id) }),
    title: (p) => `Đơn nghỉ phép ${p.code} đã được duyệt`,
    body: (p) => (p.comment ? `${actorName(p)}: ${p.comment}` : `${actorName(p)} đã duyệt đơn nghỉ phép.`),
    link: (p) => `/eoffice/leave/${p.id}`,
    resolves: (p) => [leaveKey(p)],
  },
  LeaveRequestRejected: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requester_id) }),
    title: (p) => `Đơn nghỉ phép ${p.code} bị từ chối`,
    body: (p) => withReason(`${actorName(p)} đã từ chối đơn nghỉ phép.`, p.comment),
    link: (p) => `/eoffice/leave/${p.id}`,
    resolves: (p) => [leaveKey(p)],
  },
  LeaveRequestReturned: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requester_id) }),
    title: (p) => `Đơn nghỉ phép ${p.code} bị trả lại`,
    body: (p) => withReason(`${actorName(p)} trả lại đơn để bạn chỉnh sửa.`, p.comment),
    link: (p) => `/eoffice/leave/${p.id}`,
    resolves: (p) => [leaveKey(p)],
  },
  LeaveRequestCancelled: {
    category: 'info',
    // Đơn hủy khi chưa duyệt thì chỉ cần đóng việc của người duyệt, không báo thêm
    recipients: () => ({}),
    resolves: (p) => [leaveKey(p)],
  },

  // ── Chấm công: yêu cầu bổ sung/bù công/remote/công tác/OT đi qua luồng duyệt của quản lý trực tiếp ──
  AttendanceRequestSubmitted: {
    category: 'task',
    recipients: (p) => ({ userIds: userIds(p.approver?.id) }),
    title: (p) => `${p.type_label} ${p.code} cần duyệt`,
    body: (p) => `${p.requester_name} gửi yêu cầu: ${p.title}`,
    link: () => '/eoffice/attendance-requests?scope=to_approve',
    actionKey: attendanceKey,
  },
  AttendanceRequestApproved: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requester_id) }),
    title: (p) => `${p.type_label} ${p.code} đã được duyệt`,
    body: (p) => withReason(`${actorName(p)} đã duyệt yêu cầu.`, p.comment),
    link: (p) => `/eoffice/attendance-requests/${p.id}`,
    resolves: (p) => [attendanceKey(p)],
  },
  AttendanceRequestRejected: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requester_id) }),
    title: (p) => `${p.type_label} ${p.code} bị từ chối`,
    body: (p) => withReason(`${actorName(p)} đã từ chối yêu cầu.`, p.comment),
    link: (p) => `/eoffice/attendance-requests/${p.id}`,
    resolves: (p) => [attendanceKey(p)],
  },
  AttendanceRequestReturned: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(p.requester_id) }),
    title: (p) => `${p.type_label} ${p.code} bị trả lại`,
    body: (p) => withReason(`${actorName(p)} trả lại yêu cầu để bổ sung.`, p.comment),
    link: (p) => `/eoffice/attendance-requests/${p.id}`,
    resolves: (p) => [attendanceKey(p)],
  },
  AttendanceRequestCancelled: {
    category: 'info',
    // Hủy khi chưa duyệt thì chỉ đóng việc của người duyệt, không báo thêm
    recipients: () => ({}),
    resolves: (p) => [attendanceKey(p)],
  },

  // ── Bảng lương: payload không mang số tiền, chỉ báo trạng thái ──
  PayrollSubmitted: {
    category: 'task',
    recipients: () => ({ permissions: ['payroll.approve'] }),
    title: (p) => `Bảng lương tháng ${p.label} cần duyệt`,
    body: (p) => `${actorName(p)} đã gửi bảng lương ${p.employee_count} nhân viên chờ duyệt.`,
    link: () => '/eoffice/payroll',
    actionKey: payrollApproveKey,
  },
  PayrollApproved: {
    category: 'task',
    recipients: () => ({ permissions: ['payroll.pay'] }),
    title: (p) => `Bảng lương tháng ${p.label} đã duyệt, cần chi lương`,
    body: (p) => `${actorName(p)} đã duyệt bảng lương ${p.employee_count} nhân viên.`,
    link: () => '/eoffice/payroll',
    actionKey: payrollPayKey,
    resolves: (p) => [payrollApproveKey(p)],
  },
  PayrollReturned: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(...(p.recipient_ids || [])) }),
    title: (p) => `Bảng lương tháng ${p.label} bị trả lại`,
    body: (p) => withReason(`${actorName(p)} trả lại bảng lương để chỉnh sửa.`, p.comment),
    link: () => '/eoffice/payroll',
    resolves: (p) => [payrollApproveKey(p)],
  },
  PayslipPublished: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(...(p.recipient_ids || [])) }),
    title: (p) => `Phiếu lương tháng ${p.label} đã sẵn sàng`,
    body: () => 'Bạn có thể xem chi tiết phiếu lương trong mục Phiếu lương của tôi.',
    link: () => '/eoffice/payslip',
  },
  PayrollPaid: {
    category: 'info',
    recipients: (p) => ({ userIds: userIds(...(p.recipient_ids || [])) }),
    title: (p) => `Lương tháng ${p.label} đã được chi`,
    body: () => 'Kế toán đã chi lương, vui lòng kiểm tra tài khoản nhận lương.',
    link: () => '/eoffice/payslip',
    resolves: (p) => [payrollPayKey(p)],
  },

  // ── Sản phẩm mới: thông báo đến bộ phận Kho vận (LOGISTIC) để theo dõi tồn kho ──
  ProductCreated: {
    category: 'info',
    recipients: () => ({ permissions: ['inventory.view'] }),
    title: (p) => `Sản phẩm mới [${p.sku || p.name}]`,
    body: (p) => `Sản phẩm "${p.name || ''}" (SKU: ${p.sku || ''}) vừa được thêm vào hệ thống. Tồn kho đã khởi tạo tự động.`,
    link: () => '/inventory',
  },
};
