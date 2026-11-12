# Stage 1 (base): Python + thư viện + mã nguồn
FROM python:3.12-slim AS base
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends     curl     && rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

# Model nhúng cho RAG tải sẵn lúc build (layer riêng, không tải lại khi chỉ đổi mã nguồn); container chạy không cần mạng
ARG KNOWLEDGE_EMBED_MODEL=sentence-transformers/paraphrase-multilingual-MiniLM-L12-v2
ENV KNOWLEDGE_EMBED_MODEL=${KNOWLEDGE_EMBED_MODEL} KNOWLEDGE_MODEL_CACHE=/opt/models
RUN python -c "import os; from fastembed import TextEmbedding; TextEmbedding(os.environ['KNOWLEDGE_EMBED_MODEL'], cache_dir=os.environ['KNOWLEDGE_MODEL_CACHE'])"

COPY . .

# Stage 2 (test): docker build --target test; biên dịch được và import được (giống job ai-check của CI)
FROM base AS test
CMD ["sh", "-c", "python -m compileall -q app && python -c 'import app.main'"]

# Stage 3 (runtime): stage cuối cùng = image chạy thật
FROM base AS runtime
ENV AI_SERVICE_PORT=8010
EXPOSE 8010

CMD ["python", "-m", "uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8010"]
