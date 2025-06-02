#!/bin/bash

# Конвертирует wav в mp3 ( sox + lame ), обновляет фалы в базе данных и удаляет wav
# можно указать за какое количество дней брать файлы
# /path/to/convert_and_cleanup.sh --days=3
#
#  Можно в CRON
# */30 * * * * /path/to/convert_and_cleanup.sh
# Или только новые (например, за последние 2 дня)
# */30 * * * * /path/to/convert_and_cleanup.sh --days=2

# === НАСТРОЙКИ ===
BASE_DIR="/var/spool/asterisk/monitor"
LOG="/var/log/convert_mp3.log"
DB_USER="freepbxuser"
DB_PASS="YOUR_PASSWORD"
DB_NAME="asteriskcdrdb"

# === ОБРАБОТКА ПАРАМЕТРА --days=N ===
DAYS=""
for arg in "$@"; do
  if [[ "$arg" =~ ^--days=([0-9]+)$ ]]; then
    DAYS="-mtime -${BASH_REMATCH[1]}"
  fi
done

# === ОСНОВНАЯ ЛОГИКА ===
echo "[$(date)] Starting conversion scan..." >> "$LOG"

find "$BASE_DIR" -type f -name "*.wav" $DAYS | while read wav; do
    mp3="${wav%.wav}.mp3"
    if [ ! -f "$mp3" ]; then
        echo "[$(date)] Converting: $wav" >> "$LOG"

        sox "$wav" -t wavpcm - | lame - "$mp3" >> "$LOG" 2>&1

        if [ $? -eq 0 ]; then
            echo "[$(date)] Success: $mp3 created. Removing $wav" >> "$LOG"
            rm "$wav"

            fname=$(basename "$wav")
            newname="${fname%.wav}.mp3"

            mysql -u"$DB_USER" -p"$DB_PASS" "$DB_NAME" -e "
              UPDATE cdr SET recordingfile = '$newname' WHERE recordingfile = '$fname';
            " >> "$LOG" 2>&1
        else
            echo "[$(date)] ERROR: Failed to convert $wav" >> "$LOG"
        fi
    fi
done

echo "[$(date)] Conversion scan finished." >> "$LOG"
