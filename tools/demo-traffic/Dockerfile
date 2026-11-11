FROM python:3.12-slim
WORKDIR /app

COPY requirements.txt ./
RUN pip install --no-cache-dir -r requirements.txt

COPY demo_traffic ./demo_traffic

# Trạng thái tiến độ trong ngày (để chạy bù khi khởi động lại) nằm ở volume /state
VOLUME ["/state"]
CMD ["python", "-m", "demo_traffic", "daemon"]
