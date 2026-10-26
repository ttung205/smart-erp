import assert from 'node:assert/strict';
import { describe, it } from 'node:test';
import { rules } from '../src/notifications/rules.js';

describe('Quy tắc thông báo chấm công / bảng lương', () => {
  const attendance = { id: 12, code: 'CC-2026-0012', type_label: 'Bù công', requester_id: 42, requester_name: 'Nguyễn Văn A', title: 'Bù công · 26/10/2026', approver: { id: 8 }, actor: { id: 8, name: 'Trưởng phòng' } };
  const payroll = { period_id: 5, label: '09/2026', employee_count: 24, actor: { id: 7, name: 'Kế toán lương' }, recipient_ids: [42, 43] };

  it('yêu cầu công gửi việc cần làm cho người duyệt và đóng việc khi có quyết định', () => {
    assert.equal(rules.AttendanceRequestSubmitted.category, 'task');
    assert.deepEqual(rules.AttendanceRequestSubmitted.recipients(attendance), { userIds: [8] });
    const key = rules.AttendanceRequestSubmitted.actionKey(attendance);
    for (const type of ['Approved', 'Rejected', 'Returned', 'Cancelled']) {
      assert.deepEqual(rules[`AttendanceRequest${type}`].resolves(attendance), [key], type);
    }
    assert.deepEqual(rules.AttendanceRequestApproved.recipients(attendance), { userIds: [42] });
    assert.deepEqual(rules.AttendanceRequestCancelled.recipients(attendance), {}, 'Hủy không báo thêm cho ai');
  });

  it('bảng lương chờ duyệt gửi theo quyền payroll.approve, duyệt xong chuyển việc sang người chi lương', () => {
    assert.deepEqual(rules.PayrollSubmitted.recipients(payroll), { permissions: ['payroll.approve'] });
    assert.deepEqual(rules.PayrollApproved.recipients(payroll), { permissions: ['payroll.pay'] });
    assert.deepEqual(rules.PayrollApproved.resolves(payroll), [rules.PayrollSubmitted.actionKey(payroll)]);
    assert.deepEqual(rules.PayrollPaid.resolves(payroll), [rules.PayrollApproved.actionKey(payroll)]);
  });

  it('phiếu lương và thông báo chi lương gửi tới từng nhân viên mà không có số tiền', () => {
    for (const type of ['PayslipPublished', 'PayrollPaid', 'PayrollReturned']) {
      assert.deepEqual(rules[type].recipients(payroll), { userIds: [42, 43] }, type);
      assert.doesNotMatch(`${rules[type].title(payroll)} ${rules[type].body(payroll)}`, /\d{1,3}([.,]\d{3})+|₫/, type);
    }
    assert.match(rules.PayslipPublished.title(payroll), /09\/2026/);
  });
});
