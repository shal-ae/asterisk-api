# 📦 Установка

---

## ✅ 1. Подключитесь к серверу

```bash
ssh root@<IP_адрес_FreePBX>
```

---

## ✅ 2. Скопируйте всю папку `api/` на сервер в `/var/www/html/`

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

## ⚙️ 3. Конфигурация

Чувствительные настройки хранятся в `.api.env.php`:

Скопируйте файл:

```bash
cp .api.env.php.dist .api.env.php
```

```php
<?php
return [
    'db_host' => 'localhost',
    'db_name_cdr' => 'asteriskcdrdb',
    'db_name_conf' => 'asterisk',
    'db_user' => 'freepbxuser',
    'db_pass' => '',
    'api_key' => '',
    'recordings_path' => '/var/spool/asterisk/monitor',
    'outgoing_path' =>  '/var/spool/asterisk/outgoing',
    'outgoing_temp_path' =>  '/tmp',
    'outgoing_context' =>  'from-internal'
];

```

Установите `api_key`, `db_pass`

---

### Где взять пароль от базы FreePBX

Откройте файл:

```bash
cat /etc/freepbx.conf
```

Найдите строки:

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

## ✅ 4. Проверьте работу API

Откройте в браузере:

```
http://<IP>/api/cdr.php
```

Если ответ:

```json
{
  "error": "Unauthorized"
}
```

— значит API работает, и требует API-ключ.

---

## 🛡️ Безопасность (дополнительно к API_KEY)

- Ограничить доступ по IP — через `.htaccess` или Apache
- Следите за правами на директорию `/var/spool/asterisk/outgoing`
- Установить Fail2Ban или IP-фильтрацию

----
[В начало](../README.md)

