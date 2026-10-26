"""Nạp bộ dữ liệu SQLite do simulator sinh vào các database MySQL của Docker.

Quy trình an toàn:
    python -m simulator.run
    python -m simulator.validate
    python -m simulator.import_mysql --check
    python -m simulator.import_mysql

Lệnh nạp thật tự tạo một bản mysqldump trước khi thay dữ liệu nghiệp vụ.
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

from .run import CLEAR, DB, SIMULATED_USER


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


def preflight() -> dict[str, dict[str, tuple[list[str], list[tuple]]]]:
    plans = {}
    errors = []
    for service, database in MYSQL_DATABASES.items():
        source = sqlite3.connect(f"file:{DB[service]}?mode=ro", uri=True)
        target = mysql_connection(database)
        try:
            plan = source_plan(service, source)
            plans[service] = plan
            for table, (columns, _) in plan.items():
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
    if errors:
        raise RuntimeError("Schema SQLite/MySQL chưa đồng bộ:\n- " + "\n- ".join(errors))
    return plans


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


def main() -> None:
    parser = argparse.ArgumentParser()
    parser.add_argument("--check", action="store_true", help="chỉ kiểm tra schema và số dòng, không ghi MySQL")
    args = parser.parse_args()

    plans = preflight()
    for service, plan in plans.items():
        print(f"[{service}] " + ", ".join(f"{table}={len(rows)}" for table, (_, rows) in plan.items()))
    if args.check:
        print("Schema tương thích; chưa thay đổi dữ liệu MySQL.")
        return

    backup = backup_mysql()
    print("Backup MySQL ->", backup)
    for service, plan in plans.items():
        load_service(service, plan)
        print(f"[{service}] đã nạp xong")
    print("Hoàn tất nạp dữ liệu mô phỏng vào MySQL.")


if __name__ == "__main__":
    sys.stdout.reconfigure(encoding="utf-8")
    main()
