"""Kết nối MySQL dùng chung cho ai-service (lịch sử hội thoại, kho phân tích, nguồn ETL)."""
from __future__ import annotations

from datetime import date, datetime
from decimal import Decimal

import pymysql
from pymysql.constants import FIELD_TYPE
from pymysql.converters import conversions

from . import config

# PIPES_AS_CONCAT: giữ nguyên cú pháp `a || ' ' || b` của các truy vấn tool.
# Không bật ONLY_FULL_GROUP_BY: các truy vấn báo cáo chọn cột phụ thuộc khóa nhóm.
SQL_MODE = "PIPES_AS_CONCAT,STRICT_TRANS_TABLES,NO_ENGINE_SUBSTITUTION"


def _decimal(value: str) -> int | float:
    # SUM(INT) trả "12" -> int; cột tiền DECIMAL trả "12.50" -> float. Tránh Decimal lẫn float khi tính toán.
    return float(value) if "." in value else int(value)


_CONVERSIONS = {**conversions, FIELD_TYPE.NEWDECIMAL: _decimal, FIELD_TYPE.DECIMAL: _decimal}


class Row(tuple):
    """Dòng kết quả truy cập được cả theo vị trí (row[0]) lẫn theo tên cột (row["key"])."""

    _names: tuple[str, ...] = ()

    def __getitem__(self, item):
        if isinstance(item, str):
            return tuple.__getitem__(self, self._names.index(item))
        return tuple.__getitem__(self, item)

    def keys(self):
        return list(self._names)


def _make_row(names: tuple[str, ...]):
    return type("Row", (Row,), {"_names": names})


class Cursor:
    """Bọc cursor để câu SQL dùng placeholder `?` như SQLite và trả về Row."""

    def __init__(self, cursor):
        self._cursor = cursor
        names = tuple(d[0] for d in cursor.description or ())
        self._row = _make_row(names)

    def fetchone(self):
        row = self._cursor.fetchone()
        return self._row(row) if row is not None else None

    def fetchall(self):
        return [self._row(r) for r in self._cursor.fetchall()]

    def __iter__(self):
        return iter(self.fetchall())

    @property
    def rowcount(self) -> int:
        return self._cursor.rowcount

    @property
    def lastrowid(self) -> int:
        return self._cursor.lastrowid


class Connection:
    def __init__(self, raw):
        self.raw = raw

    def execute(self, sql: str, params: list | tuple | None = None) -> Cursor:
        # `%` trong SQL (DATE_FORMAT, LIKE) phải nhân đôi vì pymysql nội suy tham số bằng %s
        cursor = self.raw.cursor()
        cursor.execute(sql.replace("%", "%%").replace("?", "%s"), list(params or ()))
        return Cursor(cursor)

    def executemany(self, sql: str, rows: list[tuple]) -> None:
        with self.raw.cursor() as cursor:
            cursor.executemany(sql.replace("%", "%%").replace("?", "%s"), rows)

    def commit(self) -> None:
        self.raw.commit()

    def close(self) -> None:
        self.raw.close()

    def __enter__(self):
        return self

    def __exit__(self, *exc):
        self.close()


def connect(database: str | None = None, *, read_only: bool = False) -> Connection:
    raw = pymysql.connect(
        host=config.MYSQL_HOST,
        port=config.MYSQL_PORT,
        user=config.MYSQL_USER,
        password=config.MYSQL_PASSWORD,
        database=database or config.ANALYTICS_DB_NAME,
        charset="utf8mb4",
        autocommit=True,
        conv=_CONVERSIONS,
        init_command=f"SET SESSION sql_mode='{SQL_MODE}'",
    )
    if read_only:
        with raw.cursor() as cursor:
            cursor.execute("SET SESSION TRANSACTION READ ONLY")
    return Connection(raw)


def to_text(value):
    """Chuẩn hóa giá trị đọc từ MySQL về dạng chuỗi/số như kho phân tích đang lưu."""
    if isinstance(value, datetime):
        return value.strftime("%Y-%m-%d %H:%M:%S")
    if isinstance(value, date):
        return value.isoformat()
    if isinstance(value, Decimal):
        return float(value)
    if isinstance(value, bytes):
        return int.from_bytes(value, "big")
    return value
