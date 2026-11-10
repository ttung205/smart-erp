"""Nạp bộ dữ liệu SQLite do simulator sinh vào các database MySQL của Docker.

Quy trình an toàn:
    python -m simulator.run
    python -m simulator.validate
    python -m simulator.import_mysql --check
    python -m simulator.import_mysql

Nguồn mặc định là các DB SQLite dev trong services/. Dùng --source <thư mục> để nạp từ một bản snapshot
(simulator/.backup/<thời điểm>/ gồm identity.sqlite, catalog.sqlite, sales.sqlite, finance.sqlite, inventory.db,
purchase.db).

Id role, phòng ban, tài khoản kế toán, kho và user mẫu ở nguồn thường KHÁC id đã seed trên MySQL đích, nên script
ghép chúng theo khóa tự nhiên (tên role, mã tài khoản, email...) và đổi mọi cột tham chiếu (xem idmap.py). --check
in bảng ghép và dừng nếu có giá trị không ghép được.

Lệnh nạp thật hỏi xác nhận, rồi tạo một bản mysqldump (docker exec erp-mysql) trước khi thay dữ liệu nghiệp vụ.
Với MySQL không chạy trong container (RDS) dùng --skip-backup sau khi đã sao lưu bằng backup.sh.
"""
from __future__ import annotations

import argparse
import os
import sqlite3
import subprocess
import sys
from datetime import datetime
from pathlib import Path

import pymysql

from .idmap import DOMAINS, apply_user_aliases, build_id_maps, remap_rows, suspect_columns, user_id_conflicts
from .run import CLEAR, DB, SIMULATED_USER


SOURCE_FILES = {
    "identity": "identity.sqlite",
    "catalog": "catalog.sqlite",
    "sales": "sales.sqlite",
    "inventory": "inventory.db",
    "purchase": "purchase.db",
    "finance": "finance.sqlite",
}

MYSQL_DATABASES = {
    "identity": "erp_identity_db",
    "catalog": "erp_catalog_db",
    "sales": "erp_sales_db",
    "inventory": "erp_inventory_db",
    "purchase": "erp_purchase_db",
    "finance": "erp_finance_db",
}


def mysql_connection(database: str | None = None):
    return pymysql.connect(
        host=os.getenv("SIMULATOR_MYSQL_HOST", "127.0.0.1"),
        port=int(os.getenv("SIMULATOR_MYSQL_PORT", "3306")),
        user=os.getenv("SIMULATOR_MYSQL_USER", "erp_user"),
        password=os.getenv("SIMULATOR_MYSQL_PASSWORD", "erp_secret"),
        database=database,
        charset="utf8mb4",
        autocommit=False,
    )


def source_paths(source_dir: Path | None) -> dict[str, Path]:
    paths = dict(DB) if source_dir is None else {s: source_dir / name for s, name in SOURCE_FILES.items()}
    missing = [str(path) for path in paths.values() if not path.exists()]
    if missing:
        raise RuntimeError("Không thấy file nguồn:\n- " + "\n- ".join(missing) + "\nDùng --source <thư mục snapshot>.")
    return paths


def read_source_lookups(paths: dict[str, Path]) -> dict[str, dict[int, str]]:
    """domain -> {id nguồn: khóa tự nhiên} đọc từ các bảng danh mục trong DB nguồn."""
    lookups: dict[str, dict[int, str]] = {}
    for domain, (service, table, key) in DOMAINS.items():
        con = sqlite3.connect(f"file:{paths[service]}?mode=ro", uri=True)
        try:
            lookups[domain] = {row[0]: row[1] for row in con.execute(f'select id, "{key}" from "{table}"')}
        finally:
            con.close()
    return lookups


def read_target_lookups() -> tuple[dict[str, dict[str, int]], dict[int, str]]:
    """domain -> {khóa tự nhiên: id đích} đọc từ MySQL đích, kèm {id: email} của mọi user đích."""
    lookups: dict[str, dict[str, int]] = {}
    users: dict[int, str] = {}
    for domain, (service, table, key) in DOMAINS.items():
        con = mysql_connection(MYSQL_DATABASES[service])
        try:
            with con.cursor() as cur:
                cur.execute(f"select `{key}`, id from `{table}`")
                lookups[domain] = {row[0]: row[1] for row in cur.fetchall()}
                if domain == "user":
                    users = {user_id: email for email, user_id in lookups[domain].items()}
        finally:
            con.close()
    return lookups, users


def sqlite_columns(con: sqlite3.Connection, table: str) -> list[str]:
    return [row[1] for row in con.execute(f'pragma table_info("{table}")')]


def mysql_columns(con, database: str, table: str) -> list[tuple[str, str, object, str]]:
    with con.cursor() as cur:
        cur.execute(
            "select column_name, is_nullable, column_default, extra from information_schema.columns "
            "where table_schema=%s and table_name=%s order by ordinal_position",
            (database, table),
        )
        return list(cur.fetchall())


def table_exists(con, database: str, table: str) -> bool:
    with con.cursor() as cur:
        cur.execute(
            "select 1 from information_schema.tables where table_schema=%s and table_name=%s",
            (database, table),
        )
        return cur.fetchone() is not None


def selected_identity_rows(source: sqlite3.Connection) -> tuple[list[tuple], list[tuple]]:
    users = source.execute("select * from users order by id").fetchall()
    user_columns = sqlite_columns(source, "users")
    email_index = user_columns.index("email")
    id_index = user_columns.index("id")
    simulated = [row for row in users if SIMULATED_USER.match(row[email_index])]
    ids = {row[id_index] for row in simulated}
    roles = [row for row in source.execute("select * from user_roles") if row[0] in ids]
    return simulated, roles


def source_plan(service: str, source: sqlite3.Connection) -> dict[str, tuple[list[str], list[tuple]]]:
    if service == "identity":
        users, roles = selected_identity_rows(source)
        return {
            "users": (sqlite_columns(source, "users"), users),
            "user_roles": (sqlite_columns(source, "user_roles"), roles),
        }

    plan = {}
    for table in CLEAR[service]:
        columns = sqlite_columns(source, table)
        plan[table] = (columns, source.execute(f'select * from "{table}"').fetchall())
    return plan


def preflight(paths: dict[str, Path], user_aliases: dict[str, str] | None = None) -> tuple[dict, dict]:
    """Đọc nguồn, ghép id nguồn sang id đích, đổi id trong dữ liệu sẽ nạp và kiểm tra schema đích.

    Trả về (plans, report); dừng bằng RuntimeError nếu có bất kỳ vấn đề nào (schema lệch, id không ghép được...)."""
    plans: dict = {}
    errors: list[str] = []
    problems: set[str] = set()

    source_lookup = read_source_lookups(paths)
    target_lookup, target_users = read_target_lookups()
    errors += apply_user_aliases(target_lookup["user"], user_aliases or {})
    simulated = {uid: email for uid, email in source_lookup["user"].items() if SIMULATED_USER.match(email)}
    maps, unmatched = build_id_maps(source_lookup, target_lookup, keep={"user": set(simulated)})
    errors += user_id_conflicts(simulated, target_users, lambda email: bool(SIMULATED_USER.match(email)))

    table_columns: dict[tuple[str, str], list[str]] = {}
    for service, database in MYSQL_DATABASES.items():
        source = sqlite3.connect(f"file:{paths[service]}?mode=ro", uri=True)
        target = mysql_connection(database)
        try:
            plan = source_plan(service, source)
            plans[service] = plan
            for table, (columns, rows) in list(plan.items()):
                table_columns[(service, table)] = columns
                plan[table] = (columns, remap_rows(service, table, columns, rows, maps, unmatched, problems))
                if not table_exists(target, database, table):
                    errors.append(f"{database}.{table}: bảng không tồn tại")
                    continue
                destination_meta = mysql_columns(target, database, table)
                destination = {column[0] for column in destination_meta}
                missing = [column for column in columns if column not in destination]
                if missing:
                    errors.append(f"{database}.{table}: MySQL thiếu cột {', '.join(missing)}")
                required = [
                    name for name, nullable, default, extra in destination_meta
                    if name not in columns and nullable == "NO" and default is None and "auto_increment" not in extra
                ]
                if required:
                    errors.append(
                        f"{database}.{table}: dữ liệu mô phỏng thiếu cột MySQL bắt buộc {', '.join(required)}"
                    )
        finally:
            source.close()
            target.close()
    errors += sorted(problems)
    if errors:
        raise RuntimeError("Không nạp được:\n- " + "\n- ".join(errors))
    report = {
        "maps": maps, "unmatched": unmatched, "source": source_lookup, "simulated_users": len(simulated),
        "suspects": suspect_columns(table_columns),
    }
    return plans, report


def print_mapping_report(report: dict) -> None:
    print("Bảng ghép id nguồn -> đích (theo khóa tự nhiên):")
    for domain in DOMAINS:
        source, mapped, missing = report["source"][domain], report["maps"][domain], report["unmatched"][domain]
        changed = {s: t for s, t in mapped.items() if s != t}
        line = f"  [{domain}] ghép {len(mapped)}/{len(source)}, đổi id {len(changed)}"
        if domain != "user" and changed:
            line += ": " + ", ".join(f"{s}->{t}" for s, t in sorted(changed.items()))
        print(line)
        if missing:
            print("    không có trên đích (chỉ lỗi nếu dữ liệu nạp có tham chiếu tới): "
                  + "; ".join(f"{s}={k}" for s, k in sorted(missing.items())))
    print(f"  user mô phỏng sẽ được nạp mới (giữ nguyên id): {report['simulated_users']}")
    if report["suspects"]:
        print("CẢNH BÁO: cột trông như tham chiếu danh mục/người dùng nhưng CHƯA có quy tắc đổi id (idmap.REMAP_RULES):")
        for column in report["suspects"]:
            print("  -", column)


def backup_mysql() -> Path:
    target = Path(__file__).resolve().parent / ".backup" / "mysql"
    target.mkdir(parents=True, exist_ok=True)
    path = target / f"smart-erp-{datetime.now():%Y%m%d-%H%M%S}.sql"
    user = os.getenv("SIMULATOR_MYSQL_USER", "erp_user")
    password = os.getenv("SIMULATOR_MYSQL_PASSWORD", "erp_secret")
    command = [
        "docker", "exec", "erp-mysql", "mysqldump",
        f"-u{user}", f"-p{password}", "--single-transaction", "--skip-lock-tables",
        "--no-tablespaces", "--databases", *MYSQL_DATABASES.values(),
    ]
    with path.open("wb") as output:
        subprocess.run(command, stdout=output, check=True)
    return path


def insert_rows(cur, table: str, columns: list[str], rows: list[tuple]) -> None:
    if not rows:
        return
    quoted = ", ".join(f"`{column}`" for column in columns)
    placeholders = ", ".join(["%s"] * len(columns))
    sql = f"insert into `{table}` ({quoted}) values ({placeholders})"
    for start in range(0, len(rows), 1000):
        cur.executemany(sql, rows[start:start + 1000])


def load_service(service: str, plan: dict[str, tuple[list[str], list[tuple]]]) -> None:
    database = MYSQL_DATABASES[service]
    target = mysql_connection(database)
    try:
        with target.cursor() as cur:
            cur.execute("set foreign_key_checks=0")
            if service == "identity":
                cur.execute("select id, email from users")
                ids = [uid for uid, email in cur.fetchall() if SIMULATED_USER.match(email)]
                if ids:
                    placeholders = ",".join(["%s"] * len(ids))
                    cur.execute(f"delete from user_roles where user_id in ({placeholders})", ids)
                    cur.execute(f"delete from users where id in ({placeholders})", ids)
                insert_rows(cur, "users", *plan["users"])
                insert_rows(cur, "user_roles", *plan["user_roles"])
            else:
                for table in CLEAR[service]:
                    cur.execute(f"delete from `{table}`")
                for table in reversed(CLEAR[service]):
                    columns, rows = plan[table]
                    insert_rows(cur, table, columns, rows)
            cur.execute("set foreign_key_checks=1")
        target.commit()
    except Exception:
        target.rollback()
        raise
    finally:
        target.close()


def confirm_destructive(args) -> None:
    host = os.getenv("SIMULATOR_MYSQL_HOST", "127.0.0.1")
    port = os.getenv("SIMULATOR_MYSQL_PORT", "3306")
    print(f"ĐÍCH: MySQL {host}:{port}")
    print("Sẽ XÓA dữ liệu nghiệp vụ trong " + ", ".join(MYSQL_DATABASES.values()) + " rồi nạp dữ liệu mô phỏng.")
    if args.yes:
        return
    if input("Gõ 'nap' để tiếp tục: ").strip() != "nap":
        raise SystemExit("Đã hủy, chưa thay đổi gì.")


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="chỉ kiểm tra schema, ghép id và số dòng, không ghi MySQL")
    parser.add_argument("--source", type=Path, help="thư mục snapshot chứa các file SQLite nguồn (mặc định: DB dev)")
    parser.add_argument("--skip-backup", action="store_true",
                        help="bỏ bước mysqldump qua docker exec (dùng khi MySQL là RDS và đã sao lưu bằng backup.sh)")
    parser.add_argument("--yes", action="store_true", help="không hỏi xác nhận trước khi xóa dữ liệu đích")
    parser.add_argument("--map-user", action="append", default=[], metavar="EMAIL_NGUỒN=EMAIL_ĐÍCH",
                        help="ghép user nguồn với user đích đã đổi email, ví dụ admin@erp.local=ten@gmail.com (lặp lại được)")
    args = parser.parse_args()

    user_aliases = {}
    for item in args.map_user:
        source_email, separator, target_email = item.partition("=")
        if not separator or not source_email or not target_email:
            parser.error(f"--map-user cần dạng EMAIL_NGUỒN=EMAIL_ĐÍCH, nhận được: {item}")
        user_aliases[source_email.strip()] = target_email.strip()

    plans, report = preflight(source_paths(args.source), user_aliases)
    for service, plan in plans.items():
        print(f"[{service}] " + ", ".join(f"{table}={len(rows)}" for table, (_, rows) in plan.items()))
    print_mapping_report(report)
    if args.check:
        print("Schema tương thích và id ghép được; chưa thay đổi dữ liệu MySQL.")
        return

    confirm_destructive(args)
    if args.skip_backup:
        print("Bỏ qua backup (--skip-backup): bạn chịu trách nhiệm đã có bản sao lưu.")
    else:
        try:
            backup = backup_mysql()
        except (OSError, subprocess.CalledProcessError) as error:
            raise SystemExit(
                f"Backup bằng docker exec erp-mysql thất bại ({error}). MySQL đích không phải container erp-mysql? "
                "Sao lưu bằng backup.sh rồi chạy lại với --skip-backup."
            )
        print("Backup MySQL ->", backup)
    for service, plan in plans.items():
        load_service(service, plan)
        print(f"[{service}] đã nạp xong")
    print("Hoàn tất nạp dữ liệu mô phỏng vào MySQL.")


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    main()
