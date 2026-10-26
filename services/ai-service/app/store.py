"""Lưu hội thoại và nhật ký gọi tool (để kiểm tra lại câu trả lời và cải thiện về sau)."""
from __future__ import annotations

import json
from contextlib import closing
from datetime import datetime

from . import db

SCHEMA = [
    """create table if not exists conversations (id bigint primary key auto_increment, user_id bigint not null,
        title varchar(255) not null, created_at datetime not null, updated_at datetime not null,
        index ix_conv_user (user_id, updated_at)) default charset utf8mb4""",
    """create table if not exists messages (id bigint primary key auto_increment, conversation_id bigint not null,
        role varchar(32) not null, content mediumtext not null, created_at datetime not null,
        index ix_msg_conv (conversation_id, id)) default charset utf8mb4""",
    """create table if not exists tool_calls (id bigint primary key auto_increment, conversation_id bigint not null,
        message_id bigint, user_id bigint not null, tool varchar(64) not null, arguments text not null,
        ok tinyint not null, result_chars int not null, duration_ms int not null, created_at datetime not null)
        default charset utf8mb4""",
]


def _now() -> str:
    return datetime.now().strftime("%Y-%m-%d %H:%M:%S")


def connect() -> db.Connection:
    return db.connect()


def init() -> None:
    with closing(connect()) as con:
        for statement in SCHEMA:
            con.execute(statement)


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
        return cur.lastrowid


def log_tool_calls(conversation_id: int, message_id: int, user_id: int, calls: list[dict]) -> None:
    with closing(connect()) as con:
        con.executemany(
            "insert into tool_calls (conversation_id, message_id, user_id, tool, arguments, ok, result_chars, "
            "duration_ms, created_at) values (?, ?, ?, ?, ?, ?, ?, ?, ?)",
            [(conversation_id, message_id, user_id, c["tool"], json.dumps(c["arguments"], ensure_ascii=False),
              int(c["ok"]), c["result_chars"], c["duration_ms"], _now()) for c in calls])


def list_conversations(user_id: int, limit: int = 30) -> list[dict]:
    with closing(connect()) as con:
        rows = con.execute("select id, title, updated_at from conversations where user_id = ? "
                           "order by updated_at desc, id desc limit ?", [user_id, limit]).fetchall()
    return [{**dict(r), "updated_at": db.to_text(r["updated_at"])} for r in rows]


def conversation_messages(user_id: int, conversation_id: int) -> list[dict] | None:
    with closing(connect()) as con:
        if not con.execute("select 1 from conversations where id = ? and user_id = ?",
                           [conversation_id, user_id]).fetchone():
            return None
        rows = con.execute("select id, role, content, created_at from messages where conversation_id = ? order by id",
                           [conversation_id]).fetchall()
    return [{**dict(r), "created_at": db.to_text(r["created_at"])} for r in rows]


def delete_conversation(user_id: int, conversation_id: int) -> bool:
    with closing(connect()) as con:
        cur = con.execute("delete from conversations where id = ? and user_id = ?", [conversation_id, user_id])
        if cur.rowcount:
            con.execute("delete from messages where conversation_id = ?", [conversation_id])
        return bool(cur.rowcount)
