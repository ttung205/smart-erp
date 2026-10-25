"""ai-service: trợ lý AI nội bộ (chỉ đọc) trên kho phân tích của ERP.

    uvicorn app.main:app --port 8010
"""
from __future__ import annotations

import asyncio
import logging
from contextlib import asynccontextmanager

from fastapi import APIRouter, Depends, FastAPI, HTTPException
from fastapi.responses import JSONResponse
from pydantic import BaseModel, Field

from . import agent, config, store, warehouse
from .auth import Actor, current_actor
from .llm import LLMError

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
log = logging.getLogger("ai")


async def sync_loop() -> None:
    while True:
        try:
            await asyncio.to_thread(warehouse.sync)
        except Exception:
            log.exception("Đồng bộ kho phân tích lỗi")
        await asyncio.sleep(config.SYNC_INTERVAL_SECONDS)


@asynccontextmanager
async def lifespan(_: FastAPI):
    store.init()
    task: asyncio.Task | None = None
    if config.WAREHOUSE_SYNC_ENABLED and not config.ANALYTICS_DB.exists():
        try:
            await asyncio.to_thread(warehouse.sync)
        except Exception:
            log.exception("Không thể đồng bộ kho phân tích khi khởi động; dịch vụ tiếp tục ở trạng thái degraded")
    if config.WAREHOUSE_SYNC_ENABLED:
        task = asyncio.create_task(sync_loop())
    yield
    if task is not None:
        task.cancel()


app = FastAPI(title="ai-service", lifespan=lifespan)
router = APIRouter(prefix="/api/v1/ai")


def ok(data) -> dict:
    return {"success": True, "data": data}


@app.exception_handler(HTTPException)
async def http_error(_, exc: HTTPException):
    code = {401: "UNAUTHORIZED", 403: "FORBIDDEN", 404: "NOT_FOUND", 422: "VALIDATION_FAILED"}.get(exc.status_code, "ERROR")
    return JSONResponse({"success": False, "error": {"code": code, "message": exc.detail}}, status_code=exc.status_code)


@app.exception_handler(LLMError)
async def llm_error(_, exc: LLMError):
    return JSONResponse({"success": False, "error": {"code": "AI_UNAVAILABLE", "message": str(exc)}},
                        status_code=exc.status)


class ChatRequest(BaseModel):
    message: str = Field(min_length=1, max_length=2000)
    conversation_id: int | None = None


@app.get("/health")
@router.get("/health")
def health():
    return ok({"service": "ai-service", "model": config.GROQ_MODEL, "llm_configured": bool(config.GROQ_API_KEY),
               "analytics_ready": config.ANALYTICS_DB.exists(),
               "warehouse_sync_enabled": config.WAREHOUSE_SYNC_ENABLED})


@router.post("/chat")
async def chat(body: ChatRequest, actor: Actor = Depends(current_actor)):
    result = await agent.ask(actor, body.message.strip(), body.conversation_id)
    return ok(result.__dict__)


@router.get("/conversations")
def conversations(actor: Actor = Depends(current_actor)):
    return ok(store.list_conversations(actor.id))


@router.get("/conversations/{conversation_id}")
def conversation(conversation_id: int, actor: Actor = Depends(current_actor)):
    messages = store.conversation_messages(actor.id, conversation_id)
    if messages is None:
        raise HTTPException(404, detail="Không tìm thấy hội thoại")
    return ok(messages)


@router.delete("/conversations/{conversation_id}")
def delete_conversation(conversation_id: int, actor: Actor = Depends(current_actor)):
    if not store.delete_conversation(actor.id, conversation_id):
        raise HTTPException(404, detail="Không tìm thấy hội thoại")
    return ok(True)


@router.post("/sync")
async def sync_now(actor: Actor = Depends(current_actor)):
    if "SUPER_ADMIN" not in actor.roles:
        raise HTTPException(403, detail="Chỉ quản trị hệ thống được đồng bộ thủ công")
    return ok(await asyncio.to_thread(warehouse.sync))


app.include_router(router)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=config.PORT)
