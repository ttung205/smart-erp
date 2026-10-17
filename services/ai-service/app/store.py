"""Lưu hội thoại và nhật ký gọi tool (để kiểm tra lại câu trả lời và cải thiện về sau)."""
from __future__ import annotations

import json
import sqlite3
from contextlib import closing
from datetime import datetime

from . import config

SCHEMA = """
create table if not exists conversations (id integer primary key autoincrement, user_id integer not null,
    title text not null, created_at text not null, updated_at text not null);
create table if not exists messages (id integer primary key autoincrement, conversation_id integer not null,
    role text not null, content text not null, created_at text not null);
create table if not exists tool_calls (id integer primary key autoincrement, conversation_id integer not null,
    message_id integer, user_id integer not null, tool text not null, arguments text not null, ok integer not null,
    result_chars integer not null, duration_ms integer not null, created_at text not null);
create index if not exists ix_conv_user on conversations(user_id, updated_at);
create index if not exists ix_msg_conv on messages(conversation_id, id);
"""


def _now() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def connect() -> sqlite3.Connection:
    config.CONVERSATION_DB.parent.mkdir(parents=True, exist_ok=True)
    con = sqlite3.connect(config.CONVERSATION_DB)
    con.row_factory = sqlite3.Row
    return con


def init() -> None:
    with closing(connect()) as con:
        con.executescript(SCHEMA)


def get_or_create_conversation(user_id: int, conversation_id: int | None, first_message: str) -> int:
    with closing(connect()) as con:
        if conversation_id:
            row = con.execute("select id from conversations where id = ? and user_id = ?",
                              [conversation_id, user_id]).fetchone()
            if row:
                return row["id"]
        title = first_message.strip().replace("\n", " ")
        cur = con.execute("insert into conversations (user_id, title, created_at, updated_at) values (?, ?, ?, ?)",
                          [user_id, title[:80] + ("…" if len(title) > 80 else ""), _now(), _now()])
        con.commit()
        return cur.lastrowid


def history(conversation_id: int, limit: int) -> list[dict]:
    with closing(connect()) as con:
        rows = con.execute("select role, content from messages where conversation_id = ? order by id desc limit ?",
                           [conversation_id, limit]).fetchall()
    return [{"role": r["role"], "content": r["content"]} for r in reversed(rows)]


def add_message(conversation_id: int, role: str, content: str) -> int:
    with closing(connect()) as con:
        cur = con.execute("insert into messages (conversation_id, role, content, created_at) values (?, ?, ?, ?)",
                          [conversation_id, role, content, _now()])
        con.execute("update conversations set updated_at = ? where id = ?", [_now(), conversation_id])
        con.commit()
        return cur.lastrowid


def log_tool_calls(conversation_id: int, message_id: int, user_id: int, calls: list[dict]) -> None:
    with closing(connect()) as con:
        con.executemany(
            "insert into tool_calls (conversation_id, message_id, user_id, tool, arguments, ok, result_chars, "
            "duration_ms, created_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [(conversation_id, message_id, user_id, c["tool"], json.dumps(c["arguments"], ensure_ascii=False),
              int(c["ok"]), c["result_chars"], c["duration_ms"], _now()) for c in calls])
        con.commit()


def list_conversations(user_id: int, limit: int = 30) -> list[dict]:
    with closing(connect()) as con:
        rows = con.execute("select id, title, updated_at from conversations where user_id = ? "
                           "order by updated_at desc limit ?", [user_id, limit]).fetchall()
    return [dict(r) for r in rows]


def conversation_messages(user_id: int, conversation_id: int) -> list[dict] | None:
    with closing(connect()) as con:
        if not con.execute("select 1 from conversations where id = ? and user_id = ?",
                           [conversation_id, user_id]).fetchone():
            return None
        rows = con.execute("select id, role, content, created_at from messages where conversation_id = ? order by id",
                           [conversation_id]).fetchall()
    return [dict(r) for r in rows]


def delete_conversation(user_id: int, conversation_id: int) -> bool:
    with closing(connect()) as con:
        cur = con.execute("delete from conversations where id = ? and user_id = ?", [conversation_id, user_id])
        if cur.rowcount:
            con.execute("delete from messages where conversation_id = ?", [conversation_id])
        con.commit()
        return bool(cur.rowcount)
