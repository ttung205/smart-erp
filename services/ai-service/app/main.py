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

from . import agent, config, knowledge, ml, store, warehouse
from .auth import Actor, current_actor, optional_actor, require_analytics_actor
from .llm import LLMError

logging.basicConfig(level=logging.INFO, format="%(asctime)s %(levelname)s %(name)s: %(message)s")
log = logging.getLogger("ai")


async def sync_loop() -> None:
    while True:
        try:
            counts = await asyncio.to_thread(warehouse.sync)
            # Kho vừa được thay: huấn luyện lại mô hình để màn hình phân tích thấy giao dịch mới
            if counts.get("swapped"):
                await asyncio.to_thread(ml.warmup_cache)
        except Exception:
            log.exception("Đồng bộ kho phân tích lỗi (service=ai-service, user_id=system)")
        await asyncio.sleep(config.SYNC_INTERVAL_SECONDS)


@asynccontextmanager
async def lifespan(_: FastAPI):
    store.init()
    task: asyncio.Task | None = None
    if config.WAREHOUSE_SYNC_ENABLED and not warehouse.is_ready():
        try:
            await asyncio.to_thread(warehouse.sync)
        except Exception:
            log.exception("Không thể đồng bộ kho phân tích khi khởi động; dịch vụ tiếp tục ở trạng thái degraded")
    # Khởi động và nạp trước mô hình Machine Learning & Analytics vào In-memory Cache
    await asyncio.to_thread(ml.warmup_cache)
    try:
        await asyncio.to_thread(knowledge.warmup)
    except Exception:
        log.exception("Không dựng được index tài liệu nội bộ khi khởi động (service=ai-service, user_id=system); "
                      "tool search_company_policy sẽ thử lại khi được gọi")
    if config.WAREHOUSE_SYNC_ENABLED:
        task = asyncio.create_task(sync_loop())
    yield
    if task is not None:
        task.cancel()


app = FastAPI(
    title="Smart ERP - AI Service API",
    description="Trợ lý AI chỉ đọc và phân tích dữ liệu (BI, dự báo, phân khúc khách hàng). Yêu cầu Bearer JWT, quyền được kiểm tra theo từng route.",
    version="1.0.0",
    lifespan=lifespan,
    docs_url="/docs" if config.DOCS_ENABLED else None,
    redoc_url="/redoc" if config.DOCS_ENABLED else None,
    openapi_url="/openapi.json" if config.DOCS_ENABLED else None,
)
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
               "analytics_ready": warehouse.is_ready(),
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


# ==============================================================================
# REST API: MACHINE LEARNING & BUSINESS INTELLIGENCE DASHBOARD
# ==============================================================================
@router.get("/analytics/overview")
def analytics_overview(actor: Actor = Depends(require_analytics_actor)):
    """Trả về các thẻ chỉ số KPI kinh doanh tổng quan cho Dashboard BI (Yêu cầu JWT)."""
    return ok(ml.get_analytics_overview())


@router.get("/analytics/forecast")
def analytics_forecast(actor: Actor = Depends(require_analytics_actor)):
    """Dự báo doanh số 30 ngày tới kèm dải tin cậy 95% và chuỗi lịch sử 24 tháng (Yêu cầu JWT)."""
    return ok(ml.get_sales_forecast())


@router.get("/analytics/rfm-clusters")
def analytics_rfm_clusters(cluster_key: str | None = None, actor: Actor = Depends(require_analytics_actor)):
    """Phân khúc khách hàng RFM (K-Means k=4), Silhouette Score và danh sách khách hàng (Yêu cầu JWT)."""
    data = ml.get_rfm_clusters()
    if cluster_key:
        filtered = [c for c in data["clusters"] if c["key"] == cluster_key]
        return ok({**data, "clusters": filtered})
    return ok(data)


@router.get("/analytics/credit-risk")
def analytics_credit_risk(actor: Actor = Depends(require_analytics_actor)):
    """Danh sách các khoản công nợ có rủi ro cao nhất cần ưu tiên thu hồi (Yêu cầu JWT)."""
    return ok(ml.get_credit_risk())


@router.post("/analytics/refresh")
async def analytics_refresh(actor: Actor = Depends(current_actor)):
    """Tái huấn luyện mô hình ML từ dữ liệu hiện có. Chỉ đồng bộ ETL khi ETL được bật và người gọi là Super Admin."""
    if "SUPER_ADMIN" not in actor.roles and not actor.can("ai.use"):
        raise HTTPException(403, detail="Chỉ quản trị viên được làm mới mô hình phân tích")
    # ETL đọc chéo schema các service nên mặc định tắt; nút làm mới không được tự ý chạy nó
    sync_counts = None
    if config.WAREHOUSE_SYNC_ENABLED and "SUPER_ADMIN" in actor.roles:
        try:
            sync_counts = await asyncio.to_thread(warehouse.sync)
        except Exception:
            log.exception("Đồng bộ kho phân tích lỗi khi làm mới mô hình (user_id=%s)", actor.id)
    await asyncio.to_thread(ml.warmup_cache)
    return ok({"message": "Đã huấn luyện lại mô hình phân tích từ dữ liệu hiện có.", "synced": sync_counts})


app.include_router(router)

if __name__ == "__main__":
    import uvicorn
    uvicorn.run(app, host="0.0.0.0", port=config.PORT)

