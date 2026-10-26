"""Cấu hình ai-service, đọc từ biến môi trường / file .env cạnh thư mục app."""
from __future__ import annotations

import os
from pathlib import Path

ROOT = Path(__file__).resolve().parents[1]
SERVICES = ROOT.parent


def _load_dotenv() -> None:
    env_file = ROOT / ".env"
    if not env_file.exists():
        return
    for raw in env_file.read_text(encoding="utf-8").splitlines():
        line = raw.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, value = line.split("=", 1)
        os.environ.setdefault(key.strip(), value.strip().strip('"').strip("'"))


_load_dotenv()

PORT = int(os.getenv("AI_SERVICE_PORT", "8010"))

# Groq: API tương thích OpenAI, có gói miễn phí (https://console.groq.com)
GROQ_API_KEY = os.getenv("GROQ_API_KEY", "")
GROQ_BASE_URL = os.getenv("GROQ_BASE_URL", "https://api.groq.com/openai/v1")
GROQ_MODEL = os.getenv("GROQ_MODEL", "openai/gpt-oss-120b")
# Hạn mức gói miễn phí tính riêng từng model: bị giới hạn (429) thì chuyển sang model dự phòng
GROQ_FALLBACK_MODELS = [m.strip() for m in os.getenv("GROQ_FALLBACK_MODELS", "openai/gpt-oss-20b,qwen/qwen3.8-27b").split(",") if m.strip()]
LLM_TIMEOUT = float(os.getenv("LLM_TIMEOUT", "60"))
MAX_TOOL_ROUNDS = int(os.getenv("MAX_TOOL_ROUNDS", "6"))
HISTORY_MESSAGES = int(os.getenv("HISTORY_MESSAGES", "8"))

# JWT do identity-service cấp (HS256, secret dùng chung với các service)
JWT_SECRET = os.getenv("JWT_SECRET", "")
JWT_ISSUER = os.getenv("JWT_ISSUER", "identity-service")
REQUIRED_PERMISSION = "ai.use"

# MySQL: lịch sử hội thoại và kho phân tích cùng nằm trong schema ANALYTICS_DB_NAME
MYSQL_HOST = os.getenv("MYSQL_HOST", "127.0.0.1")
MYSQL_PORT = int(os.getenv("MYSQL_PORT", "3306"))
MYSQL_USER = os.getenv("MYSQL_USER", "erp_user")
MYSQL_PASSWORD = os.getenv("MYSQL_PASSWORD", "erp_secret")
ANALYTICS_DB_NAME = os.getenv("ANALYTICS_DB_NAME", "erp_analytics_db")
SYNC_INTERVAL_SECONDS = int(os.getenv("SYNC_INTERVAL_SECONDS", "300"))
WAREHOUSE_SYNC_ENABLED = os.getenv("WAREHOUSE_SYNC_ENABLED", "true").lower() in {"1", "true", "yes", "on"}

# Nguồn dữ liệu cho ETL (chỉ đọc, cùng MySQL server). Đây là điểm lệch chuẩn đã biết (architecture.md §13 #2):
# đích là consume event `erp.analytics.events` hoặc export API của từng service.
SOURCE_DB = {
    "identity": os.getenv("SOURCE_IDENTITY_DB", "erp_identity_db"),
    "catalog": os.getenv("SOURCE_CATALOG_DB", "erp_catalog_db"),
    "sales": os.getenv("SOURCE_SALES_DB", "erp_sales_db"),
    "inventory": os.getenv("SOURCE_INVENTORY_DB", "erp_inventory_db"),
    "purchase": os.getenv("SOURCE_PURCHASE_DB", "erp_purchase_db"),
    "finance": os.getenv("SOURCE_FINANCE_DB", "erp_finance_db"),
}
