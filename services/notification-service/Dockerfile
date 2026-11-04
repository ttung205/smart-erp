# Stage 1 (deps): toàn bộ dependencies. Copy package*.json trước để tận dụng Docker layer cache
FROM node:22-alpine AS deps
WORKDIR /app
COPY package.json package-lock.json* ./
RUN npm ci || npm install

# Stage 2 (test): docker build --target test, chạy test bằng scripts/test.ps1 (cần MONGODB_URI)
FROM deps AS test
COPY . .
CMD ["npm", "test"]

# Stage 3 (runtime): stage cuối cùng = image chạy thật, chỉ có dependency production, không có mã test
FROM node:22-alpine AS runtime
WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci --omit=dev || npm install --omit=dev

COPY . .
RUN rm -rf test

ENV PORT=8006
ENV NODE_ENV=production
EXPOSE 8006

CMD ["npm", "start"]
