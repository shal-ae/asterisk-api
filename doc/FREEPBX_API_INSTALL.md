# 📦 Установка папки `api/` на FreePBX + доступ к базе

Эта инструкция поможет скопировать всю папку `api/` с PHP-API на сервер FreePBX и настроить подключение к базе данных CDR.

---

## ✅ 1. Подключитесь к серверу FreePBX

```bash
ssh root@<IP_адрес_FreePBX>
```

---

## ✅ 2. Скопируйте всю папку `api/` на сервер

### 🟩 Через `scp`:

```bash
scp -r ./api/ root@<IP_адрес_FreePBX>:/var/www/html/
```

### 🟦 Через `rsync`:

```bash
rsync -avz ./api/ root@<IP_адрес_FreePBX>:/var/www/html/api/
```

После копирования установите корректные права:

```bash
chown -R asterisk:asterisk /var/www/html/api
chmod -R 755 /var/www/html/api
```

---

## ✅ 3. Где взять пароль от базы FreePBX

Открой файл:

```bash
cat /etc/freepbx.conf
```

Найди строки:

```php
'AMPDBUSER' => 'freepbxuser',
'AMPDBPASS' => '**************',
```

Также можно проверить:

```bash
cat /etc/asterisk/manager.conf
```

или:

```bash
cat ~/.my.cnf
```

---

## ✅ 4. Проверь работу API

Открой в браузере:

```
http://<IP>/api/cdr_api.php
```

Если видишь:

```json
{"error": "Unauthorized"}
```

— значит API работает, и требует API-ключ.

---

## ✅ 5. Пример запроса

```bash
curl -H "Authorization: Bearer my-secret-key-123" \
     "http://<IP>/api/cdr_api.php?date_from=2025-05-01&answered=1"
```

---

## 🔒 Дополнительно (по желанию)

- Ограничить доступ по IP — через `.htaccess` или Apache
- Настроить HTTPS (например, через Let's Encrypt)
- Защитить директорию `api/` паролем
- Установить Fail2Ban или IP-фильтрацию

---