# Công cụ sao lưu / phục hồi. Nền mongo:7 để có sẵn mongodump, mongorestore, mongosh;
# thêm mysql client (mysqldump), aws cli (đẩy lên S3) và jq (ghi manifest).
FROM mongo:7

RUN apt-get update \
    && apt-get install -y --no-install-recommends mysql-client-core-8.0 awscli jq ca-certificates \
    && rm -rf /var/lib/apt/lists/*

COPY backup.sh /usr/local/bin/backup.sh
RUN chmod +x /usr/local/bin/backup.sh

WORKDIR /backups
ENTRYPOINT ["/usr/local/bin/backup.sh"]
CMD ["help"]
