
# 📘 Инструкция: Как включить CEL (Call Event Logging) в FreePBX

## 🧩 Шаг 1. Установить модуль CEL через GUI

1. Перейди в **GUI FreePBX**.
2. Меню: **Admin → Module Admin**.
3. Включи режим отображения **Disabled and Not Installed** (если выключен).
4. Найди модуль **"Call Event Logging"**.
5. Если он не установлен:
   - Нажми **Install**, выбери **"Download and install"**, трек — `stable`.
   - Затем нажми **"Process" → "Confirm" → "Apply Config"`**.

---

## 🧱 Шаг 2. Убедись, что модули CEL загружены

Открой терминал и проверь:

```bash
asterisk -x "module show like cel"
```

Должны быть загружены:

```
cel.so
cel_odbc.so
cel_custom.so
...
```

Если они **не загружены**, пропиши:

```ini
[modules]
preload = cel.so
preload = cel_odbc.so
```

Файл: `/etc/asterisk/modules_custom.conf`

Затем перезапусти Asterisk:

```bash
fwconsole restart
```

---

## ⚙️ Шаг 3. Включи CEL логирование

Проверь статус:

```bash
asterisk -x "cel show status"
```

Если **`CEL Logging: Disabled`**, то:

1. Создай файл:

```bash
nano /etc/asterisk/cel_general_custom.conf
```

2. Добавь содержимое:

```ini
[general]
enabled = yes
```

3. Перезапусти Asterisk:

```bash
fwconsole restart
```

---

## 🔗 Шаг 4. Проверка

1. Соверши тестовый звонок.
2. Проверь записи CEL:

```bash
mysql -u root -p asteriskcdrdb -e "SELECT * FROM cel ORDER BY eventtime DESC LIMIT 5;"
```

---

## ✅ Готово!

Теперь FreePBX ведёт расширенный журнал звонков (CEL).

📌 **Совет:** сохрани инструкцию:

```bash
nano /root/how_to_enable_cel.txt
```
