FROM node:22-alpine

WORKDIR /app

# Cài đặt dependencies trước để tận dụng Docker layer cache
COPY package.json package-lock.json* ./
RUN npm ci --omit=dev || npm install --omit=dev

# Chép toàn bộ mã nguồn ứng dụng
COPY . .

# Đảm bảo thư mục database tồn tại cho SQLite
RUN mkdir -p database

ENV PORT=8006
ENV NODE_ENV=production
EXPOSE 8006

CMD ["npm", "start"]
