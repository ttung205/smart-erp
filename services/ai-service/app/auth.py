"""Xác thực JWT HS256 do identity-service cấp, cùng bộ luật với các service khác:
alg = HS256, chữ ký hợp lệ, iss = identity-service, có sub, chưa hết hạn."""
from __future__ import annotations

import base64
import hashlib
import hmac
import json
import time
from dataclasses import dataclass, field

from fastapi import Header, HTTPException

from . import config


@dataclass
class Actor:
    id: int
    name: str
    email: str
    roles: list[str] = field(default_factory=list)
    permissions: set[str] = field(default_factory=set)

    def can(self, *codes: str) -> bool:
        """Có ít nhất một trong các quyền (Super Admin luôn được)."""
        return "SUPER_ADMIN" in self.roles or any(c in self.permissions for c in codes)


def _b64decode(part: str) -> bytes:
    return base64.urlsafe_b64decode(part + "=" * (-len(part) % 4))


def verify_token(token: str) -> Actor:
    if not config.JWT_SECRET:
        raise HTTPException(500, detail="ai-service chưa cấu hình JWT_SECRET")
    try:
        header_b64, payload_b64, signature_b64 = token.split(".")
        header = json.loads(_b64decode(header_b64))
        claims = json.loads(_b64decode(payload_b64))
    except (ValueError, json.JSONDecodeError):
        raise HTTPException(401, detail="Token không hợp lệ")
    if header.get("alg") != "HS256":
        raise HTTPException(401, detail="Token không hợp lệ")
    expected = hmac.new(config.JWT_SECRET.encode(), f"{header_b64}.{payload_b64}".encode(), hashlib.sha256).digest()
    if not hmac.compare_digest(expected, _b64decode(signature_b64)):
        raise HTTPException(401, detail="Chữ ký token không hợp lệ")
    if claims.get("iss") != config.JWT_ISSUER or not claims.get("sub"):
        raise HTTPException(401, detail="Token không hợp lệ")
    if int(claims.get("exp", 0)) <= time.time():
        raise HTTPException(401, detail="Phiên đăng nhập đã hết hạn")
    return Actor(
        id=int(claims["sub"]),
        name=claims.get("name", ""),
        email=claims.get("email", ""),
        roles=list(claims.get("roles") or []),
        permissions=set(claims.get("permissions") or []),
    )


def current_actor(authorization: str = Header(default="")) -> Actor:
    if not authorization.lower().startswith("bearer "):
        raise HTTPException(401, detail="Thiếu token đăng nhập")
    actor = verify_token(authorization[7:].strip())
    if not actor.can(config.REQUIRED_PERMISSION):
        raise HTTPException(403, detail="Bạn chưa được cấp quyền sử dụng trợ lý AI")
    return actor
