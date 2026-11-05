#!/usr/bin/env bash
# Sao lưu và phục hồi dữ liệu Smart ERP (MySQL, MongoDB, tệp đính kèm chat).
#
#   backup                      sao lưu mọi thứ vào /backups/database-backup-<yyyymmdd>-<NN>/ (và lên S3 nếu có BACKUP_S3_URI)
#   list                        liệt kê các bản sao lưu
#   verify  [bản|latest]        kiểm tra toàn vẹn + thử phục hồi vào schema tạm rồi xóa (không đụng dữ liệu thật)
#   restore [bản|latest] --mysql[=schema,...] --mongo[=db,...] --files --yes
#                               PHỤC HỒI vào dữ liệu thật (ghi đè). Thiếu --yes thì chỉ in kế hoạch.
#
# Một bản sao lưu là một thư mục: mysql/<schema>.sql.gz, mongo/<database>.archive.gz, files/chat_files.tar.gz,
# SHA256SUMS và manifest.json. Thư mục chỉ xuất hiện sau khi mọi thứ xong và qua kiểm tra (tên *.partial là dang dở).
set -euo pipefail

BACKUP_ROOT="${BACKUP_ROOT:-/backups}"
RETENTION_DAYS="${BACKUP_RETENTION_DAYS:-7}"
MYSQL_HOST="${MYSQL_HOST:-mysql}"
MONGO_URI="${MONGO_URI:-mongodb://mongo:27017/?replicaSet=rs0}"
FILES_DIR="${BACKUP_FILES_DIR:-/data/chat_files}"
# Tên bản: database-backup-yyyymmdd-NN (NN là số thứ tự trong ngày, bắt đầu từ 01).
# Ngày trong tên tính theo giờ địa phương = UTC + BACKUP_DAY_OFFSET_HOURS (mặc định 7: giờ Việt Nam).
# Thời điểm chính xác luôn nằm trong manifest.json (created_at theo UTC, created_at_local theo giờ địa phương).
DAY_OFFSET_HOURS="${BACKUP_DAY_OFFSET_HOURS:-7}"
SET_RE='^database-backup-[0-9]{8}-[0-9]{2,}$'

log() { printf '%s %s\n' "$(date -u +%H:%M:%S)" "$*"; }
die() { printf 'LỖI: %s\n' "$*" >&2; exit 1; }
need() { local v; for v in "$@"; do [ -n "${!v:-}" ] || die "thiếu biến môi trường $v"; done; }

# mysql_as <user> <mật khẩu> <tham số...>: mật khẩu qua biến môi trường, không lộ trên dòng lệnh
mysql_as() { local user=$1 pass=$2; shift 2; MYSQL_PWD="$pass" mysql -h"$MYSQL_HOST" -u"$user" "$@"; }
as_backup() { mysql_as "$MYSQL_BACKUP_USER" "$MYSQL_BACKUP_PASSWORD" "$@"; }
as_root() { mysql_as root "$MYSQL_ROOT_PASSWORD" "$@"; }

schemas_of_server() {
  as_backup -N -B -e "SELECT schema_name FROM information_schema.schemata WHERE schema_name LIKE 'erp\\_%\\_db' ORDER BY 1"
}

# Chỉ database của ứng dụng (erp_*); bỏ admin/config/local của chính MongoDB
mongo_dbs() {
  mongosh --quiet "$MONGO_URI" --eval 'db.adminCommand({listDatabases:1,nameOnly:true}).databases.map(d=>d.name).filter(n=>n.startsWith("erp_")).join(" ")' | tr ' ' '\n'
}

# ---------------------------------------------------------------------------------------------
cmd_backup() {
  need MYSQL_BACKUP_USER MYSQL_BACKUP_PASSWORD
  local name started dir tmp day idx
  started=$(date -u +%s)
  day=$(date -u -d "@$((started + DAY_OFFSET_HOURS * 3600))" +%Y%m%d)
  mkdir -p "$BACKUP_ROOT"
  # Chọn số thứ tự trống đầu tiên trong ngày. mkdir (không -p) chỉ thành công cho đúng một tiến trình,
  # nên hai lần sao lưu chạy chồng nhau không bao giờ nhận cùng một tên.
  for ((idx = 1; ; idx++)); do
    name=$(printf 'database-backup-%s-%02d' "$day" "$idx")
    [ -e "$BACKUP_ROOT/$name" ] && continue
    mkdir "$BACKUP_ROOT/$name.partial" 2> /dev/null && break
  done
  dir="$BACKUP_ROOT/$name"
  tmp="$dir.partial"
  mkdir -p "$tmp/mysql" "$tmp/mongo" "$tmp/files"
  trap 'rm -rf "$tmp"' EXIT

  local -a schemas
  mapfile -t schemas < <(schemas_of_server)
  [ "${#schemas[@]}" -gt 0 ] || die "không thấy schema erp_*_db nào (kiểm tra quyền của $MYSQL_BACKUP_USER)"

  local counts="$tmp/.tables" s
  : > "$counts"
  for s in "${schemas[@]}"; do
    log "MySQL: $s"
    MYSQL_PWD="$MYSQL_BACKUP_PASSWORD" mysqldump -h"$MYSQL_HOST" -u"$MYSQL_BACKUP_USER" \
      --single-transaction --quick --no-tablespaces --triggers --events \
      --set-gtid-purged=OFF --column-statistics=0 --databases "$s" | gzip -c > "$tmp/mysql/$s.sql.gz"
    printf '%s %s\n' "$s" "$(as_backup -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$s' AND table_type='BASE TABLE'")" >> "$counts"
  done

  local -a mdbs
  mapfile -t mdbs < <(mongo_dbs)
  if [ "${#mdbs[@]}" -eq 0 ] || [ -z "${mdbs[0]}" ]; then
    log "MongoDB: chưa có database erp_* nào, bỏ qua"
  else
    local d
    for d in "${mdbs[@]}"; do
      log "MongoDB: $d"
      mongodump --uri="$MONGO_URI" --db "$d" --archive --quiet | gzip -c > "$tmp/mongo/$d.archive.gz"
    done
  fi

  if [ -d "$FILES_DIR" ] && [ -n "$(ls -A "$FILES_DIR" 2> /dev/null)" ]; then
    log "Tệp đính kèm chat: $FILES_DIR"
    tar -czf "$tmp/files/chat_files.tar.gz" -C "$FILES_DIR" .
  else
    log "Tệp đính kèm chat: trống, bỏ qua"
  fi

  log "Kiểm tra toàn vẹn"
  local f
  while IFS= read -r -d '' f; do
    [ -s "$f" ] || die "tệp rỗng: $f"
    gzip -t "$f" || die "tệp nén hỏng: $f"
  done < <(find "$tmp" -type f -name '*.gz' -print0)
  (cd "$tmp" && find . -type f -name '*.gz' -print0 | sort -z | xargs -0 sha256sum > SHA256SUMS)

  local finished version mongo_json schemas_json
  finished=$(date -u +%s)
  version=$(as_backup -N -B -e "SELECT VERSION()")
  mongo_json=$(for f in "$tmp"/mongo/*.archive.gz; do
      [ -e "$f" ] || continue
      printf '%s\t%s\n' "$(basename "$f" .archive.gz)" "$(stat -c %s "$f")"
    done | jq -Rn '[inputs | split("\t") | {key: .[0], value: (.[1]|tonumber)}] | from_entries')
  schemas_json=$(while read -r sname tables; do
      printf '%s\t%s\t%s\n' "$sname" "$tables" "$(stat -c %s "$tmp/mysql/$sname.sql.gz")"
    done < "$counts" | jq -Rn '[inputs | split("\t") | {key: .[0], value: {tables: (.[1]|tonumber), dump_bytes: (.[2]|tonumber)}}] | from_entries')
  jq -n \
    --arg set "$name" \
    --arg created "$(date -u -d "@$started" +%Y-%m-%dT%H:%M:%SZ)" \
    --arg local "$(date -u -d "@$((started + DAY_OFFSET_HOURS * 3600))" +%Y-%m-%dT%H:%M:%S)$(printf '%+03d:00' "$DAY_OFFSET_HOURS")" \
    --argjson duration "$((finished - started))" --arg version "$version" \
    --argjson mongo "$mongo_json" --argjson schemas "$schemas_json" \
    --argjson files "$(stat -c %s "$tmp/files/chat_files.tar.gz" 2> /dev/null || echo 0)" \
    '{set: $set, created_at: $created, created_at_local: $local, duration_seconds: $duration, mysql_version: $version,
      mysql_schemas: $schemas, mongo_databases: $mongo, chat_files_bytes: $files}' > "$tmp/manifest.json"
  rm -f "$counts"

  mv "$tmp" "$dir"
  trap - EXIT
  echo "$name" > "$BACKUP_ROOT/LATEST"
  log "Xong bản $name: $(du -sh "$dir" | cut -f1) trong $((finished - started)) giây"

  prune_old_sets "$name"
  upload_to_s3 "$dir" "$name"
}

# Xóa bản cũ hơn RETENTION_DAYS ngày (tính theo created_at trong manifest). Không bao giờ xóa bản vừa tạo.
prune_old_sets() {
  local keep=$1 d name created limit
  limit=$(($(date -u +%s) - RETENTION_DAYS * 86400))
  for d in "$BACKUP_ROOT"/*/; do
    name=$(basename "$d")
    if [[ $name =~ $SET_RE ]] && [ "$name" != "$keep" ] && [ -f "$d/manifest.json" ]; then
      created=$(date -u -d "$(jq -r .created_at "$d/manifest.json")" +%s)
      if [ "$created" -lt "$limit" ]; then
        log "Xóa bản cũ hơn ${RETENTION_DAYS} ngày: $name"
        rm -rf "$d"
      fi
    elif [[ $name == *.partial ]] && [ -n "$(find "$d" -maxdepth 0 -mmin +1440)" ]; then
      rm -rf "$d"
    fi
  done
}

upload_to_s3() {
  local dir=$1 name=$2 uri="${BACKUP_S3_URI:-}"
  [ -n "$uri" ] || { log "Không đặt BACKUP_S3_URI: chỉ lưu cục bộ"; return 0; }
  uri="${uri%/}/$name"
  log "Đẩy lên $uri"
  # manifest.json đẩy sau cùng: có manifest nghĩa là bản trên S3 đã đủ
  if aws s3 sync "$dir" "$uri/" --exclude manifest.json --only-show-errors \
    && aws s3 cp "$dir/manifest.json" "$uri/manifest.json" --only-show-errors; then
    log "Đã đẩy lên S3"
  else
    printf 'LỖI: đẩy lên S3 thất bại, bản cục bộ vẫn còn tại %s\n' "$dir" >&2
    return 2
  fi
}

# ---------------------------------------------------------------------------------------------
resolve_set() {
  local want="${1:-latest}"
  if [ "$want" = latest ]; then
    [ -f "$BACKUP_ROOT/LATEST" ] || die "chưa có bản sao lưu nào"
    want=$(cat "$BACKUP_ROOT/LATEST")
  fi
  [[ $want =~ $SET_RE ]] || die "tên bản sao lưu không hợp lệ: $want (dạng database-backup-yyyymmdd-NN)"
  [ -f "$BACKUP_ROOT/$want/manifest.json" ] || die "không có bản $want (hoặc chưa hoàn tất)"
  printf '%s' "$BACKUP_ROOT/$want"
}

cmd_list() {
  local latest d name
  latest=$(cat "$BACKUP_ROOT/LATEST" 2> /dev/null || true)
  printf '%-28s %-19s %10s %7s %10s  %s\n' BẢN TẠO_LÚC_GIỜ_VN DUNG_LƯỢNG SCHEMA THỜI_GIAN GHI_CHÚ
  for d in "$BACKUP_ROOT"/*/; do
    name=$(basename "$d")
    [[ $name =~ $SET_RE ]] && [ -f "$d/manifest.json" ] || continue
    printf '%-28s %-19s %10s %7s %9ss  %s\n' "$name" "$(jq -r '.created_at_local[0:19]' "$d/manifest.json")" \
      "$(du -sh "$d" | cut -f1)" "$(jq '.mysql_schemas | length' "$d/manifest.json")" \
      "$(jq .duration_seconds "$d/manifest.json")" "$([ "$name" = "$latest" ] && echo mới nhất)"
  done
}

# ---------------------------------------------------------------------------------------------
cmd_verify() {
  need MYSQL_BACKUP_USER MYSQL_ROOT_PASSWORD
  local set started f s tmpdb want got failed=0
  set=$(resolve_set "${1:-latest}")
  started=$(date +%s)
  log "Kiểm tra bản $(basename "$set")"

  (cd "$set" && sha256sum -c --quiet SHA256SUMS) || die "checksum không khớp: bản sao lưu bị hỏng"
  log "Checksum khớp"

  for f in "$set"/mysql/*.sql.gz; do
    s=$(basename "$f" .sql.gz)
    tmpdb="restore_check_$s"
    as_root -e "DROP DATABASE IF EXISTS \`$tmpdb\`"
    # Chỉ đổi tên schema ở dòng CREATE DATABASE / USE để phục hồi vào schema tạm
    gunzip -c "$f" | sed -E "/^(CREATE DATABASE|USE) /s/\`$s\`/\`$tmpdb\`/" | as_root
    want=$(jq -r ".mysql_schemas[\"$s\"].tables" "$set/manifest.json")
    got=$(as_root -N -B -e "SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$tmpdb' AND table_type='BASE TABLE'")
    as_root -e "DROP DATABASE \`$tmpdb\`"
    if [ "$want" = "$got" ]; then
      log "MySQL $s: phục hồi thử OK ($got bảng)"
    else
      log "MySQL $s: SAI số bảng (manifest $want, phục hồi $got)"
      failed=1
    fi
  done

  local m db err
  for m in "$set"/mongo/*.archive.gz; do
    [ -e "$m" ] || break
    db=$(basename "$m" .archive.gz)
    err=$(mktemp)
    if gunzip -c "$m" | mongorestore --uri="$MONGO_URI" --archive --nsFrom='$db$.$col$' --nsTo='restore_check_$db$.$col$' 2> "$err"; then
      log "MongoDB $db: phục hồi thử OK ($(mongosh --quiet "$MONGO_URI" --eval "db.getSiblingDB('restore_check_$db').getCollectionNames().length") collection)"
    else
      log "MongoDB $db: THẤT BẠI: $(tail -3 "$err")"
      failed=1
    fi
    mongosh --quiet "$MONGO_URI" --eval "db.getSiblingDB('restore_check_$db').dropDatabase()" > /dev/null
    rm -f "$err"
  done

  if [ -f "$set/files/chat_files.tar.gz" ]; then
    tar -tzf "$set/files/chat_files.tar.gz" > /dev/null && log "Tệp chat: giải nén thử OK"
  fi

  [ "$failed" = 0 ] || die "kiểm tra THẤT BẠI"
  log "KIỂM TRA THÀNH CÔNG sau $(($(date +%s) - started)) giây"
}

# ---------------------------------------------------------------------------------------------
cmd_restore() {
  need MYSQL_ROOT_PASSWORD
  local want=latest do_mysql="" do_mongo="" do_files=0 yes=0 arg
  for arg in "$@"; do
    case "$arg" in
      --mysql) do_mysql=all ;;
      --mysql=*) do_mysql="${arg#--mysql=}" ;;
      --mongo) do_mongo=all ;;
      --mongo=*) do_mongo="${arg#--mongo=}" ;;
      --files) do_files=1 ;;
      --yes) yes=1 ;;
      -*) die "tham số lạ: $arg" ;;
      *) want=$arg ;;
    esac
  done
  [ -n "$do_mysql" ] || [ -n "$do_mongo" ] || [ "$do_files" = 1 ] || die "chọn thứ cần phục hồi: --mysql[=schema,...] --mongo[=db,...] --files"

  local set s f
  set=$(resolve_set "$want")
  local -a targets=()
  if [ -n "$do_mysql" ]; then
    if [ "$do_mysql" = all ]; then
      for f in "$set"/mysql/*.sql.gz; do targets+=("$(basename "$f" .sql.gz)"); done
    else
      IFS=, read -r -a targets <<< "$do_mysql"
      for s in "${targets[@]}"; do [ -f "$set/mysql/$s.sql.gz" ] || die "bản này không có schema $s"; done
    fi
  fi

  local -a mtargets=()
  if [ -n "$do_mongo" ]; then
    if [ "$do_mongo" = all ]; then
      for f in "$set"/mongo/*.archive.gz; do
        [ -e "$f" ] && mtargets+=("$(basename "$f" .archive.gz)")
      done
    else
      IFS=, read -r -a mtargets <<< "$do_mongo"
      for s in "${mtargets[@]}"; do [ -f "$set/mongo/$s.archive.gz" ] || die "bản này không có database Mongo $s"; done
    fi
    [ "${#mtargets[@]}" -gt 0 ] || die "bản này không có database Mongo nào"
  fi

  echo "KẾ HOẠCH PHỤC HỒI từ bản $(basename "$set") (tạo lúc $(jq -r .created_at_local "$set/manifest.json") giờ địa phương):"
  if [ -n "$do_mysql" ]; then echo "  - MySQL: XÓA rồi dựng lại ${targets[*]}"; fi
  if [ -n "$do_mongo" ]; then echo "  - MongoDB: XÓA rồi nạp lại ${mtargets[*]}"; fi
  if [ "$do_files" = 1 ]; then echo "  - Tệp chat: giải nén đè vào $FILES_DIR"; fi
  echo "  Dữ liệu sau thời điểm sao lưu sẽ MẤT. Hãy dừng các service ghi vào dữ liệu này trước."
  if [ "$yes" != 1 ]; then
    echo "Chưa làm gì cả. Thêm --yes để thực hiện."
    exit 3
  fi

  local started t0
  started=$(date +%s)
  for s in "${targets[@]}"; do
    t0=$(date +%s)
    log "MySQL: phục hồi $s"
    as_root -e "DROP DATABASE IF EXISTS \`$s\`"
    gunzip -c "$set/mysql/$s.sql.gz" | as_root
    log "MySQL $s xong sau $(($(date +%s) - t0)) giây"
  done
  for s in "${mtargets[@]}"; do
    t0=$(date +%s)
    log "MongoDB: phục hồi $s"
    gunzip -c "$set/mongo/$s.archive.gz" | mongorestore --uri="$MONGO_URI" --archive --drop --quiet
    log "MongoDB $s xong sau $(($(date +%s) - t0)) giây"
  done
  if [ "$do_files" = 1 ]; then
    [ -f "$set/files/chat_files.tar.gz" ] || die "bản này không có tệp chat"
    mkdir -p "$FILES_DIR" && tar -xzf "$set/files/chat_files.tar.gz" -C "$FILES_DIR"
    log "Tệp chat đã giải nén"
  fi
  log "PHỤC HỒI XONG sau $(($(date +%s) - started)) giây (đây là RTO đo được cho phần vừa làm)"
}

case "${1:-help}" in
  backup) cmd_backup ;;
  list) cmd_list ;;
  verify)
    shift
    cmd_verify "$@"
    ;;
  restore)
    shift
    cmd_restore "$@"
    ;;
  *) sed -n '2,11p' "$0" | sed 's/^# \{0,1\}//' ;;
esac
