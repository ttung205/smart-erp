# Stage 1 (base): Python + thư viện + mã nguồn
FROM python:3.12-slim AS base
WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends     curl     && rm -rf /var/lib/apt/lists/*

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# Stage 2 (test): docker build --target test; biên dịch được và import được (giống job ai-check của CI)
FROM base AS test
CMD ["sh", "-c", "python -m compileall -q app && python -c 'import app.main'"]

# Stage 3 (runtime): stage cuối cùng = image chạy thật
FROM base AS runtime
ENV AI_SERVICE_PORT=8010
EXPOSE 8010

CMD ["python", "-m", "uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8010"]
