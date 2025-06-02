# Asterisk API

Простой REST API для интеграции с FreePBX / Asterisk. 

Проект предоставляет доступ к журналу звонков, аудиозаписям, внутренним номерам и функции click-to-call.

Протестировано на FreePBX v15

## 🛠 Установка
[Инструкция по установке](doc/install.md)

## 🔐 Авторизация

Все запросы требуют API-ключ в заголовке:

```
Authorization: Bearer YOUR_API_KEY
```

## 📦 Эндпоинты

#### 1. `cdr.php` — [Получение списка звонков](doc/cdr.md)

#### 2. `account.php` — [Список внутренних номеров](doc/account.md)

#### 3. `download.php` — [Скачивание записей звонков](doc/download.md)

#### 4. `make_call.php` — [Исходящий вызов (click-to-call)](doc/make_call.md)


## 🛠 Зависимости

- PHP 7.4+
- Расширение PDO для MySQL
- FreePBX/Asterisk с включённой записью разговоров
- Доступ к `/var/spool/asterisk/outgoing` и `/var/spool/asterisk/monitor`

---

