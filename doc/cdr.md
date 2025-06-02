# cdr.php

Предоставляет доступ к журналу звонков FreePBX с возможностью фильтрации, группировки и получения ссылок на аудиозаписи
разговоров.

## 🔗 Endpoint

```
GET /api/cdr.php
```

## 📥 Параметры запроса (GET)

| Параметр       | Тип                 | Описание                                                             |
|----------------|---------------------|----------------------------------------------------------------------|
| `src`          | строка              | Номер вызывающего                                                    |
| `dst`          | строка              | Номер вызываемого                                                    |
| `date_from`    | дата (`YYYY-MM-DD`) | Начало диапазона дат                                                 |
| `date_to`      | дата (`YYYY-MM-DD`) | Конец диапазона дат                                                  |
| `answered`     | `0` / `1`           | Фильтр по статусу: `1` — только отвеченные, `0` — только пропущенные |
| `min_duration` | целое               | Минимальная длительность (в секундах)                                |
| `page`         | целое               | Номер страницы (по умолчанию 1)                                      |
| `per_page`     | целое               | Кол-во записей на страницу (по умолчанию 100, максимум 1000)         |
| `fieldset`     | строка              | `all` - для отладки (выводятся все поля из таблицы)                  |

## 📦 Пример запроса

```http
GET /api/cdr.php?date_from=2025-05-01&date_to=2025-05-10&answered=1&min_duration=10&page=1
Authorization: Bearer my-secret-key-123
```

## ✅ Ответ

Успешный ответ возвращает JSON:

```json
{
  "status": "OK",
  "page": 1,
  "per_page": 20,
  "total": 42,
  "pages_count": 3,
  "content": [
    {
      "linkedid": "171680001.001",
      "items": [
        {
          "uniqueid": "171680001.001",
          "calldate": "2025-05-01 12:34:56",
          "accountcode": "",
          "src": "1001",
          "dst": "83425554433",
          "channel": "PJSIP/1001-00000123",
          "dstchannel": "PJSIP/mts-trunk-00000124",
          "dcontext": "from-internal",
          "duration": 34,
          "billsec": 30,
          "disposition": "ANSWERED",
          "src_ext": "1001",
          "dst_ext": null,
          "src_trunk": "PJSIP/1001",
          "dst_trunk": "mts-trunk",
          "recording_urls": [
            "2025/05/01/out-8342-1001-171680001.001.wav"
          ]
        }
      ]
    }
  ]
}
```

## 🔎 Поля в `items[]`

| Поле                     | Описание                                                  |
|--------------------------|-----------------------------------------------------------|
| `uniqueid`               | Уникальный ID вызова                                      |
| `linkedid`               | Общий ID цепочки вызовов                                  |
| `calldate`               | Дата и время звонка                                       |
| `src`, `dst`             | Номера вызова                                             |
| `channel`, `dstchannel`  | Каналы Asterisk                                           |
| `src_ext`, `dst_ext`     | Внутренние номера                                         |
| `src_trunk`, `dst_trunk` | Имена транков                                             |
| `duration`               | Полная длительность (сек.)                                |
| `billsec`                | Время разговора (сек.)                                    |
| `disposition`            | Статус звонка (`ANSWERED`, `NO ANSWER`, `FAILED`, `BUSY`) |
| `recording_urls`         | Ссылки на записи разговоров (если есть)                   |
| `accountcode`            | Внутренний номер исходящего при вызове click-to-call      |

## ⚠️ Примечания

- Файлы записей ищутся в `/var/spool/asterisk/monitor/YYYY/MM/DD/` с расширениями `.wav` или `.mp3`
- Пагинация работает по CDR-записям, а не по группам `linkedid`

## 🧰 Примеры

### Получить все входящие звонки за май 2025:

```http
GET /api/cdr_api.php?date_from=2025-05-01&date_to=2025-05-31&answered=1&min_duration=10
Authorization: Bearer my-secret-key-123
```

### Получить только пропущенные звонки:

```http
GET /api/cdr_api.php?answered=0
Authorization: Bearer my-secret-key-123
```

----
[В начало](../README.md)

