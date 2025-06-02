# account.php

Возвращает список внутренних SIP/PJSIP номеров и имён пользователей из конфигурационной базы Asterisk.

---

## 🔗 Endpoint

```
GET /api/account.php
```

---

**Ответ:**
```json
{
  "status": "OK",
  "count": 2,
  "extensions": [
    { "extension": "10", "name": "Кондратий" },
    { "extension": "20", "name": "Егор" }
  ]
}
```
----
[В начало](../README.md)


