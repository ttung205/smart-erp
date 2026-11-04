# Stage 1 (build): cài dependency và build file tĩnh
FROM node:22-alpine AS build

WORKDIR /app

COPY package.json package-lock.json* ./
RUN npm ci || npm install

COPY . .
RUN npm run build

# Stage 2 (test): docker build --target test. Frontend chưa có unit test; kiểm tra là build production chạy được.
# (Không chạy lint toàn repo: CI chỉ lint file đã sửa trong PR, còn lỗi lint cũ chưa dọn.)
FROM build AS test
CMD ["npm", "run", "build"]

# Stage 3 (runtime): stage cuối cùng = nginx chỉ chứa file tĩnh đã build
FROM nginx:alpine AS runtime

COPY --from=build /app/dist /usr/share/nginx/html
COPY nginx.conf /etc/nginx/conf.d/default.conf

EXPOSE 80

CMD ["nginx", "-g", "daemon off;"]
