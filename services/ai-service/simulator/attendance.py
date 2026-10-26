"""Sinh dữ liệu chấm công mẫu quý III/2026 (tháng 7, 8, 9) vào erp_eoffice_db.

Nguồn nhân sự là bảng users của identity (MySQL). Dữ liệu sinh ra gồm hồ sơ nhân sự/lương, log máy chấm công,
nghỉ phép đã duyệt và các yêu cầu bổ sung/bù công, remote, công tác, OT kèm lịch sử duyệt - đủ để chạy khóa công
và tính lương cho cả ba tháng. Chạy lại được: chỉ xóa dữ liệu do chính simulator tạo (gắn nhãn "[Mô phỏng]").

    python -m simulator.attendance --dry-run     # chỉ in thống kê
    python -m simulator.attendance               # backup erp_eoffice_db, nạp dữ liệu, tính lại bảng công

Biến môi trường kết nối giống import_mysql: SIMULATOR_MYSQL_HOST/PORT/USER/PASSWORD.
Bảng công được tính bằng chính code của eoffice-service (php artisan attendance:recalculate trong container erp-eoffice).
"""
from __future__ import annotations

import argparse
import os
import random
import subprocess
import sys
from collections import defaultdict
from dataclasses import dataclass, field
from datetime import date, datetime, timedelta
from pathlib import Path

import pymysql

SEED = 20260701
TAG = "[Mô phỏng]"
FIRST_DAY = date(2026, 7, 1)
LAST_DAY = date(2026, 9, 30)
MONTHS = ["2026-07", "2026-08", "2026-09"]
MYSQL_BACKUP_DIR = Path(__file__).resolve().parent / ".backup" / "mysql"

# Lương cơ bản (VND/tháng) theo vai trò: (thấp, cao, phụ cấp cố định)
SALARY_BY_ROLE = {
    "Super Admin": (55_000_000, 55_000_000, 2_000_000),
    "Manager": (28_000_000, 35_000_000, 1_500_000),
    "Sale": (12_000_000, 16_000_000, 1_000_000),
    "Purchase": (13_000_000, 15_000_000, 1_000_000),
    "Technical": (15_000_000, 18_000_000, 1_000_000),
    "Finance & Accounting": (13_000_000, 16_000_000, 1_000_000),
    "Logistic": (9_000_000, 12_000_000, 800_000),
}
BANKS = ["Vietcombank", "Techcombank", "MB Bank", "ACB", "BIDV", "VPBank"]
HYBRID_USERS = {17, 19, 22}      # sale làm việc kết hợp
REMOTE_USERS = {11}              # kỹ thuật viên hệ thống làm việc từ xa
NEW_HIRE = (28, date(2026, 7, 15))  # vào làm giữa tháng 7 → tháng 7 chỉ có nửa tháng công
BUSINESS_TRIPS = {8: (date(2026, 8, 10), date(2026, 8, 12)), 19: (date(2026, 9, 14), date(2026, 9, 15))}
SATURDAY_OT_USERS = {25, 26, 27}  # kho vận tăng ca thứ 7 theo mùa hàng


@dataclass
class Emp:
    id: int
    name: str
    department_id: int | None
    manager_id: int | None
    role: str
    profile: dict = field(default_factory=dict)


@dataclass
class Req:
    """Một yêu cầu công sẽ được ghi vào attendance_requests."""
    user: Emp
    type: str
    start: date
    end: date
    requested_in: str | None
    requested_out: str | None
    reason: str
    status: str = "APPROVED"
    submitted_at: datetime | None = None
    comment: str | None = None
    code: str = ""


def connect(database: str):
    return pymysql.connect(
        host=os.getenv("SIMULATOR_MYSQL_HOST", "127.0.0.1"), port=int(os.getenv("SIMULATOR_MYSQL_PORT", "3306")),
        user=os.getenv("SIMULATOR_MYSQL_USER", "erp_user"), password=os.getenv("SIMULATOR_MYSQL_PASSWORD", "erp_secret"),
        database=database, charset="utf8mb4", autocommit=False, cursorclass=pymysql.cursors.DictCursor,
    )


def hhmm(minutes: int) -> str:
    return f"{minutes // 60:02d}:{minutes % 60:02d}"


def at(day: date, minutes: int, seconds: int = 0) -> datetime:
    return datetime(day.year, day.month, day.day) + timedelta(minutes=minutes, seconds=seconds)


def role_group(role: str) -> str:
    if role == "Super Admin":
        return role
    if "Manager" in role:
        return "Manager"
    return role


def load_staff() -> list[Emp]:
    con = connect("erp_identity_db")
    try:
        with con.cursor() as cur:
            cur.execute(
                "select u.id, u.name, u.department_id, u.line_manager_id, coalesce(r.name, '') as role "
                "from users u left join user_roles ur on ur.user_id = u.id left join roles r on r.id = ur.role_id "
                "where u.is_active = 1 order by u.id")
            return [Emp(r["id"], r["name"], r["department_id"], r["line_manager_id"], r["role"]) for r in cur.fetchall()]
    finally:
        con.close()


def build_profile(emp: Emp, rng: random.Random, shift_id: int | None) -> dict:
    low, high, allowance = SALARY_BY_ROLE.get(role_group(emp.role), (10_000_000, 12_000_000, 800_000))
    base = low if low == high else rng.randrange(low, high + 1, 500_000)
    hire = date(2021, 1, 1) + timedelta(days=rng.randint(0, 365 * 5 - 30))
    if emp.id == NEW_HIRE[0]:
        hire = NEW_HIRE[1]
    mode = "REMOTE" if emp.id in REMOTE_USERS else "HYBRID" if emp.id in HYBRID_USERS else "ONSITE"
    return {
        "user_id": emp.id, "user_name": emp.name, "employee_code": f"NV{emp.id:04d}", "hire_date": hire,
        "base_salary": base, "insurance_salary": base, "fixed_allowance": allowance,
        "dependents": rng.choice([0, 0, 1, 1, 2]), "bank_name": rng.choice(BANKS),
        "bank_account": "".join(rng.choice("0123456789") for _ in range(11)), "work_mode": mode, "shift_id": shift_id,
    }


def workdays(holidays: set[date]) -> list[date]:
    days, d = [], FIRST_DAY
    while d <= LAST_DAY:
        if d.weekday() < 5 and d not in holidays:
            days.append(d)
        d += timedelta(days=1)
    return days


def approver_of(emp: Emp, staff: dict[int, Emp]) -> Emp | None:
    manager = staff.get(emp.manager_id) if emp.manager_id else None
    return manager if manager and manager.id != emp.id else None


class Generator:
    def __init__(self, staff: list[Emp], holidays: set[date], leave_types: dict[str, int], last_leave_number: int) -> None:
        self.staff = {e.id: e for e in staff}
        self.holidays = holidays
        self.days = workdays(holidays)
        self.leave_types = leave_types
        self.leave_counter = last_leave_number
        self.punches: list[dict] = []
        self.requests: list[Req] = []
        self.leaves: list[dict] = []

    # ---------- nghỉ phép / công tác / remote ----------
    def plan_leaves(self, emp: Emp, rng: random.Random, taken: set[date]) -> dict[date, str]:
        """Trả về {ngày: 'FULL' | 'MORNING' | 'AFTERNOON'} và ghi các đơn nghỉ đã duyệt."""
        half: dict[date, str] = {}
        episodes = [("ANNUAL", rng.randint(1, 3)) for _ in range(rng.choice([0, 1, 1, 2]))]
        if rng.random() < 0.25:
            episodes.append(("SICK", rng.randint(1, 2)))
        if rng.random() < 0.08:
            episodes.append(("UNPAID", 1))
        if rng.random() < 0.3:
            episodes.append(("ANNUAL_HALF", 0))
        for kind, length in episodes:
            candidates = [d for d in self.days if d not in taken and d >= max(FIRST_DAY, emp.profile["hire_date"] + timedelta(days=1))]
            rng.shuffle(candidates)
            for start in candidates:
                i = self.days.index(start)
                span = self.days[i:i + max(length, 1)]
                if len(span) < max(length, 1) or any(d in taken for d in span) or (span[-1] - span[0]).days > length + 3:
                    continue
                if kind == "ANNUAL_HALF":
                    session = rng.choice(["MORNING", "AFTERNOON"])
                    half[span[0]] = session
                    taken.add(span[0])
                    self.add_leave(emp, "ANNUAL", span[0], span[0], session, session, 0.5, rng)
                else:
                    for d in span:
                        taken.add(d)
                    self.add_leave(emp, kind, span[0], span[-1], "FULL", "FULL", float(len(span)), rng)
                break
        return half

    def add_leave(self, emp: Emp, kind: str, start: date, end: date, s_session: str, e_session: str, total: float, rng: random.Random) -> None:
        self.leave_counter += 1
        approver = approver_of(emp, self.staff)
        submitted = at(start - timedelta(days=rng.randint(1, 6)), 9 * 60 + rng.randint(0, 120))
        reasons = {"ANNUAL": "Nghỉ phép năm", "SICK": "Nghỉ ốm", "UNPAID": "Việc gia đình, xin nghỉ không lương"}
        self.leaves.append({
            "code": f"NP-2026-{self.leave_counter:04d}", "user": emp, "type_id": self.leave_types[kind], "start": start, "end": end,
            "start_session": s_session, "end_session": e_session, "total": total,
            "reason": f"{TAG} {reasons[kind]}", "approver": approver, "submitted_at": submitted,
            "decided_at": submitted + timedelta(hours=rng.randint(1, 20)),
        })

    # ---------- một ngày làm việc ----------
    def request(self, emp: Emp, kind: str, start: date, end: date, in_: str | None, out: str | None, reason: str, submitted: datetime) -> Req:
        req = Req(emp, kind, start, end, in_, out, f"{TAG} {reason}", submitted_at=submitted)
        self.requests.append(req)
        return req

    def day_punches(self, emp: Emp, day: date, rng: random.Random, source: str, scenario: str, half: str | None, overtime: int | None) -> Req | None:
        """Sinh log của một ngày; trả về yêu cầu sửa công nếu kịch bản cần."""
        note = TAG if source == "WEB" else None
        device = None if source == "WEB" else "MCC-01"
        in_min = 480 - rng.randint(0, 12) if rng.random() > 0.1 else 480 + rng.randint(1, 10)
        out_min = 1020 + rng.randint(0, 25)
        if half == "MORNING":      # nghỉ buổi sáng
            in_min, out_min = 780 - rng.randint(0, 5), 1020 + rng.randint(0, 10)
        elif half == "AFTERNOON":  # nghỉ buổi chiều
            in_min, out_min = 480 - rng.randint(0, 8), 720 + rng.randint(0, 5)
        elif scenario == "late":
            in_min = 480 + rng.randint(15, 55)
        elif scenario == "early":
            out_min = 1020 - rng.randint(45, 150)
        if overtime and half is None:
            out_min = overtime + rng.randint(0, 12)
        record = lambda m: self.punches.append({"user": emp, "at": at(day, m, rng.randint(0, 50)), "source": source, "note": note, "device": device, "request": None})  # noqa: E731
        if scenario == "forgot_out":
            record(in_min)
        elif scenario == "forgot_in":
            record(out_min)
        elif scenario in ("absent", "makeup"):
            pass
        else:
            record(in_min)
            record(out_min)
        return None

    def generate(self) -> None:
        for emp in self.staff.values():
            rng = random.Random(f"{SEED}:{emp.id}")
            hire = emp.profile["hire_date"]
            taken: set[date] = set()
            trips = BUSINESS_TRIPS.get(emp.id)
            if trips:
                for d in self.days:
                    if trips[0] <= d <= trips[1]:
                        taken.add(d)
                self.request(emp, "BUSINESS_TRIP", trips[0], trips[1], None, None, "Đi công tác gặp khách hàng", at(trips[0] - timedelta(days=3), 9 * 60))
            half = self.plan_leaves(emp, rng, taken)
            mode = emp.profile["work_mode"]
            remote_days = self.plan_remote(emp, rng, taken, mode)
            saturday_ot = self.plan_saturday_ot(emp, rng) if emp.id in SATURDAY_OT_USERS else []

            for day in self.days:
                if day < hire or day in taken and day not in half:
                    continue
                source = "WEB" if day in remote_days else "SIMULATOR"
                scenario = "normal"
                if day not in remote_days and day not in half:
                    r = rng.random()
                    scenario = ("absent" if r < 0.008 else "makeup" if r < 0.015 else "forgot_out" if r < 0.04 else "forgot_in" if r < 0.05
                                else "late" if r < 0.13 else "early" if r < 0.16 else "normal")
                overtime = None
                if scenario in ("normal", "late") and day not in half and rng.random() < (0.10 if emp.role == "Logistic" else 0.05):
                    overtime = rng.choice([1170, 1200, 1230, 1260])
                    self.request(emp, "OVERTIME", day, day, "17:30", hhmm(overtime), "Làm thêm giờ chốt đơn hàng cuối ngày", at(day, 15 * 60 + rng.randint(0, 90)))
                self.day_punches(emp, day, rng, source, scenario, half.get(day), overtime)
                self.fix_anomaly(emp, day, rng, scenario)

            for d, window_end in saturday_ot:
                for m in (505, window_end + rng.randint(0, 10)):
                    self.punches.append({"user": emp, "at": at(d, m, rng.randint(0, 50)), "source": "SIMULATOR", "note": None, "device": "MCC-01", "request": None})

    def plan_remote(self, emp: Emp, rng: random.Random, taken: set[date], mode: str) -> set[date]:
        remote: set[date] = set()
        if mode == "ONSITE":
            return remote
        if mode == "REMOTE":
            for month in MONTHS:
                span = [d for d in self.days if d.strftime("%Y-%m") == month and d not in taken and d >= emp.profile["hire_date"]]
                if span:
                    remote.update(span)
                    self.request(emp, "REMOTE", span[0], span[-1], None, None, "Làm việc từ xa theo hợp đồng", at(span[0] - timedelta(days=2), 9 * 60))
            return remote
        weeks = defaultdict(list)
        for d in self.days:
            if d not in taken:
                weeks[d.isocalendar()[:2]].append(d)
        for days in weeks.values():
            for d in rng.sample(days, min(len(days), rng.choice([1, 1, 2]))):
                remote.add(d)
                self.request(emp, "REMOTE", d, d, None, None, "Làm việc tại nhà", at(d - timedelta(days=1), 16 * 60))
        return remote

    def plan_saturday_ot(self, emp: Emp, rng: random.Random) -> list[tuple[date, int]]:
        saturdays = [FIRST_DAY + timedelta(days=i) for i in range((LAST_DAY - FIRST_DAY).days + 1)
                     if (FIRST_DAY + timedelta(days=i)).weekday() == 5]
        chosen = rng.sample(saturdays, 3)
        out = []
        for d in sorted(chosen):
            end = rng.choice([750, 780, 810])  # 12:30 - 13:30
            self.request(emp, "OVERTIME", d, d, "08:30", hhmm(end), "Nhập hàng và kiểm kê cuối tuần", at(d - timedelta(days=2), 15 * 60))
            out.append((d, end))
        return out

    def fix_anomaly(self, emp: Emp, day: date, rng: random.Random, scenario: str) -> None:
        submitted = at(day + timedelta(days=1), 9 * 60 + rng.randint(0, 90))
        if submitted.weekday() >= 5:
            submitted += timedelta(days=7 - submitted.weekday())
        if scenario == "forgot_out":
            self.request(emp, "ADD_PUNCH", day, day, None, hhmm(1020 + rng.randint(0, 25)), "Quên chấm công khi ra về", submitted)
        elif scenario == "forgot_in":
            self.request(emp, "ADD_PUNCH", day, day, hhmm(480 - rng.randint(0, 10)), None, "Quên chấm công khi vào làm", submitted)
        elif scenario == "makeup":
            self.request(emp, "MAKEUP", day, day, hhmm(480 - rng.randint(0, 8)), hhmm(1020 + rng.randint(0, 20)), "Máy chấm công lỗi, thực tế có đi làm cả ngày", submitted)

    def resolve_september_cases(self) -> None:
        """Để lại vài ca chưa xử lý ở tháng 9 cho các màn hình duyệt/cảnh báo: 1 chờ duyệt, 1 bị từ chối, 2 chưa gửi yêu cầu."""
        fixes = [r for r in self.requests if r.type in ("ADD_PUNCH", "MAKEUP") and r.start.strftime("%Y-%m") == "2026-09"
                 and approver_of(r.user, self.staff)]
        fixes.sort(key=lambda r: (r.start, r.user.id))
        tail = fixes[-4:]
        if len(tail) >= 1:
            tail[0].status = "PENDING"
        if len(tail) >= 2:
            tail[1].status, tail[1].comment = "REJECTED", "Không có minh chứng đi làm, vui lòng liên hệ HR"
        for r in tail[2:]:
            self.requests.remove(r)  # chưa gửi yêu cầu: ngày vẫn thiếu chấm công / vắng

    def finalize_requests(self) -> None:
        self.requests.sort(key=lambda r: (r.submitted_at, r.user.id, r.start))
        number = 0
        for r in self.requests:
            number += 1
            r.code = f"CC-2026-{number:04d}"
        # Log ADJUSTMENT chỉ phát sinh khi yêu cầu được duyệt
        for r in self.requests:
            if r.status != "APPROVED" or r.type not in ("ADD_PUNCH", "MAKEUP"):
                continue
            for time_text in (r.requested_in, r.requested_out):
                if time_text:
                    h, m = map(int, time_text.split(":"))
                    self.punches.append({"user": r.user, "at": at(r.start, h * 60 + m), "source": "ADJUSTMENT", "note": f"{TAG} {r.code}", "device": None, "request": r})


# ---------------- ghi vào database ----------------
def backup_eoffice() -> Path:
    MYSQL_BACKUP_DIR.mkdir(parents=True, exist_ok=True)
    path = MYSQL_BACKUP_DIR / f"eoffice-{datetime.now():%Y%m%d-%H%M%S}.sql"
    user = os.getenv("SIMULATOR_MYSQL_USER", "erp_user")
    password = os.getenv("SIMULATOR_MYSQL_PASSWORD", "erp_secret")
    command = ["docker", "exec", "erp-mysql", "mysqldump", f"-u{user}", f"-p{password}", "--single-transaction",
               "--skip-lock-tables", "--no-tablespaces", "--databases", "erp_eoffice_db"]
    with path.open("wb") as handle:
        result = subprocess.run(command, stdout=handle, stderr=subprocess.PIPE)
    if result.returncode != 0 or path.stat().st_size == 0:
        raise RuntimeError("Không backup được erp_eoffice_db: " + result.stderr.decode("utf-8", "replace")[:300])
    return path


def clear_simulated(cur) -> None:
    cur.execute("select id from attendance_requests where reason like %s", (f"{TAG}%",))
    request_ids = [r["id"] for r in cur.fetchall()]
    cur.execute("select id from leave_requests where reason like %s", (f"{TAG}%",))
    leave_ids = [r["id"] for r in cur.fetchall()]
    for kind, ids in (("attendance_request", request_ids), ("leave_request", leave_ids)):
        for i in range(0, len(ids), 500):
            chunk = ids[i:i + 500]
            marks = ",".join(["%s"] * len(chunk))
            cur.execute(f"delete from approval_steps where approvable_type=%s and approvable_id in ({marks})", (kind, *chunk))
            cur.execute(f"delete from approval_histories where approvable_type=%s and approvable_id in ({marks})", (kind, *chunk))
    if request_ids:
        cur.execute("delete from attendance_requests where reason like %s", (f"{TAG}%",))
    if leave_ids:
        cur.execute("delete from leave_requests where reason like %s", (f"{TAG}%",))
    cur.execute("delete from punch_logs where source='SIMULATOR' or note like %s", (f"{TAG}%",))
    cur.execute("delete from attendance_days where work_date between %s and %s", (FIRST_DAY, LAST_DAY))


def write_all(gen: Generator, profiles: list[dict]) -> dict[str, int]:
    con = connect("erp_eoffice_db")
    now = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    try:
        with con.cursor() as cur:
            clear_simulated(cur)
            for p in profiles:
                cur.execute(
                    "insert ignore into employee_profiles (user_id,user_name,employee_code,hire_date,base_salary,insurance_salary,fixed_allowance,"
                    "dependents,bank_name,bank_account,work_mode,shift_id,is_active,created_at,updated_at) values "
                    "(%(user_id)s,%(user_name)s,%(employee_code)s,%(hire_date)s,%(base_salary)s,%(insurance_salary)s,%(fixed_allowance)s,"
                    "%(dependents)s,%(bank_name)s,%(bank_account)s,%(work_mode)s,%(shift_id)s,1,%(now)s,%(now)s)", {**p, "now": now})
            for leave in gen.leaves:
                cur.execute(
                    "insert into leave_requests (code,user_id,user_name,department_id,leave_type_id,start_date,start_session,end_date,end_session,"
                    "total_days,reason,status,submitted_at,decided_at,created_at,updated_at) values (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,'APPROVED',%s,%s,%s,%s)",
                    (leave["code"], leave["user"].id, leave["user"].name, leave["user"].department_id, leave["type_id"], leave["start"],
                     leave["start_session"], leave["end"], leave["end_session"], leave["total"], leave["reason"], leave["submitted_at"],
                     leave["decided_at"], leave["submitted_at"], leave["decided_at"]))
                write_approval(cur, "leave_request", cur.lastrowid, leave["user"], leave["approver"], "APPROVED", leave["submitted_at"], leave["decided_at"], None)

            for req in gen.requests:
                approver = approver_of(req.user, gen.staff)
                decided = req.submitted_at + timedelta(hours=2 + (req.user.id * 7 + req.start.day) % 20)
                status = req.status if approver else "APPROVED"
                cur.execute(
                    "insert into attendance_requests (code,type,user_id,user_name,department_id,start_date,end_date,requested_in,requested_out,reason,"
                    "status,submitted_at,decided_at,created_at,updated_at) values (%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s,%s)",
                    (req.code, req.type, req.user.id, req.user.name, req.user.department_id, req.start, req.end, req.requested_in, req.requested_out,
                     req.reason, status, req.submitted_at, None if status == "PENDING" else decided, req.submitted_at, decided))
                request_id = cur.lastrowid
                req.id = request_id  # type: ignore[attr-defined]
                write_approval(cur, "attendance_request", request_id, req.user, approver, status, req.submitted_at, decided, req.comment)

            rows = []
            for p in gen.punches:
                request = p.get("request")
                rows.append((p["user"].id, p["at"], p["at"].date(), p["source"], p.get("device"), p.get("note"),
                             getattr(request, "id", None) if request else None, now, now))
            cur.executemany(
                "insert ignore into punch_logs (user_id,punched_at,work_date,source,device_id,note,attendance_request_id,created_at,updated_at) "
                "values (%s,%s,%s,%s,%s,%s,%s,%s,%s)", rows)
            for month in MONTHS:
                year, mon = map(int, month.split("-"))
                cur.execute("insert ignore into payroll_periods (year,month,status,created_at,updated_at) values (%s,%s,'DRAFT',%s,%s)", (year, mon, now, now))
        con.commit()
    except Exception:
        con.rollback()
        raise
    finally:
        con.close()
    return {"punches": len(gen.punches), "requests": len(gen.requests), "leaves": len(gen.leaves), "profiles": len(profiles)}


def write_approval(cur, kind: str, approvable_id: int, requester: Emp, approver: Emp | None, status: str, submitted: datetime, decided: datetime, comment: str | None) -> None:
    def history(actor: Emp, action: str, when: datetime, text: str | None = None) -> None:
        cur.execute("insert into approval_histories (approvable_type,approvable_id,actor_id,actor_name,action,comment,created_at) values (%s,%s,%s,%s,%s,%s,%s)",
                    (kind, approvable_id, actor.id, actor.name, action, text, when))

    history(requester, "CREATED", submitted - timedelta(minutes=5))
    history(requester, "SUBMITTED", submitted)
    if approver is None:
        history(requester, "AUTO_APPROVED", submitted)
        return
    cur.execute("insert into approval_steps (approvable_type,approvable_id,step_order,approver_id,approver_name,status,comment,acted_at,created_at,updated_at) "
                "values (%s,%s,1,%s,%s,%s,%s,%s,%s,%s)",
                (kind, approvable_id, approver.id, approver.name, status, comment, None if status == "PENDING" else decided, submitted, decided))
    if status != "PENDING":
        history(approver, status, decided, comment)


def summarize(gen: Generator) -> None:
    by_month = defaultdict(int)
    for p in gen.punches:
        by_month[p["at"].strftime("%Y-%m")] += 1
    kinds = defaultdict(int)
    for r in gen.requests:
        kinds[(r.type, r.status)] += 1
    print(f"Nhân viên: {len(gen.staff)} · ngày làm việc: {len(gen.days)} ({gen.days[0]} → {gen.days[-1]})")
    print("Log chấm công theo tháng:", dict(sorted(by_month.items())))
    print(f"Nghỉ phép đã duyệt: {len(gen.leaves)}")
    for (kind, status), count in sorted(kinds.items()):
        print(f"  Yêu cầu {kind:<14} {status:<9} {count}")


def main() -> int:
    if hasattr(sys.stdout, "reconfigure"):
        sys.stdout.reconfigure(encoding="utf-8")  # console Windows mặc định cp1252 không in được tiếng Việt
    parser = argparse.ArgumentParser(description=__doc__, formatter_class=argparse.RawDescriptionHelpFormatter)
    parser.add_argument("--dry-run", action="store_true", help="chỉ sinh và in thống kê, không ghi database")
    parser.add_argument("--no-backup", action="store_true", help="bỏ qua mysqldump erp_eoffice_db")
    parser.add_argument("--no-recalc", action="store_true", help="không chạy attendance:recalculate sau khi nạp")
    args = parser.parse_args()

    staff = load_staff()
    eo = connect("erp_eoffice_db")
    try:
        with eo.cursor() as cur:
            cur.execute("select date from holidays")
            holidays = {r["date"] for r in cur.fetchall()}
            cur.execute("select code, id from leave_types")
            leave_types = {r["code"]: r["id"] for r in cur.fetchall()}
            cur.execute("select id from shifts where is_default = 1 limit 1")
            shift = cur.fetchone()
            cur.execute("select coalesce(max(cast(substring(code, 9) as unsigned)), 0) as n from leave_requests where code like 'NP-2026-%%' and reason not like %s", (f"{TAG}%",))
            last_leave = int(cur.fetchone()["n"])
    finally:
        eo.close()
    if not shift:
        sys.exit("Chưa có ca làm việc mặc định: chạy php artisan db:seed --class=AttendanceMasterDataSeeder trong container eoffice trước.")

    rng = random.Random(SEED)
    for emp in staff:
        emp.profile = build_profile(emp, rng, shift["id"])
    gen = Generator(staff, holidays, leave_types, last_leave)
    gen.generate()
    gen.resolve_september_cases()
    gen.finalize_requests()
    summarize(gen)

    if args.dry_run:
        print("Dry-run: chưa ghi gì vào database.")
        return 0
    if not args.no_backup:
        print("Backup:", backup_eoffice())
    print("Đã nạp:", write_all(gen, [e.profile for e in staff]))
    if not args.no_recalc:
        command = ["docker", "exec", "erp-eoffice", "php", "artisan", "attendance:recalculate", *MONTHS]
        print("Tính lại bảng công:", " ".join(command))
        result = subprocess.run(command)
        return result.returncode
    return 0


if __name__ == "__main__":
    sys.exit(main())
