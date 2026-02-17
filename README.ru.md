# Laravel Debugbar MCP Server

MCP (Model Context Protocol) сервер для анализа логов Laravel Debugbar. Позволяет ИИ-агентам получать доступ к данным отладки Laravel через стандартизированный протокол.

## Версия: 1.0.0

**Автор:** Sergey O (@Sergey0002)  
**Telegram:** @Sergey0002  
**GitHub:** https://github.com/sergey0002/laravel-debuger-mcp

---

## ⚠️ ВАЖНО: Ограничения использования

Данное ПО предназначено **исключительно для использования на локальном сервере разработчика**.

**ЗАПРЕЩАЕТСЯ:**
- Размещение на публичных серверах
- Размещение на production-серверах

---

## Совместимость

Протестировано и работает с:
- **Laravel**: 7.x, 8.x, 9.x, 10.x, 11.x, 12.x
- **PHP**: 8.0+
- **Laravel Debugbar**: 3.x+

---

## Установка

### Шаг 1: Создание папки

Создайте папку `.ladebugermcp` в корне вашего Laravel проекта:

```bash
mkdir .ladebugermcp
```

### Шаг 2: Скачивание файла

Скачайте файл `mcp-server.php` и поместите его в созданную папку:

```
ваш-laravel-проект/
├── .ladebugermcp/
│   └── mcp-server.php    ← Файл MCP сервера
├── app/
├── storage/
│   └── debugbar/         ← Логи Debugbar (автоматически)
└── ...
```

### Шаг 3: Добавление в Git Ignore (ОБЯЗАТЕЛЬНО!)

**Важно!** Добавьте папку в локальный git ignore, чтобы случайно не опубликовать её:

**Вариант A: Локальный exclude (рекомендуется)**
```bash
echo "/.ladebugermcp/" >> .git/info/exclude
```

**Вариант B: В .gitignore проекта**
```gitignore
# MCP сервер Debugbar (локальный инструмент разработки)
.ladebugermcp/
```

Это предотвратит случайную публикацию конфиденциальных данных отладки в публичных репозиториях.

---

## Настройка MCP

### Для Kilo Code

Добавьте конфигурацию в файл `.kilocode/mcp.json` в корне проекта:

```json
{
  "mcpServers": {
    "laravel-debug": {
      "command": "C:\\laragon\\bin\\php\\php-8.2.30-nts-Win32-vs16-x64\\php.exe",
      "args": [".ladebugermcp/mcp-server.php"],
      "disabled": false
    }
  }
}
```

### Для VS Code (Cline, Roo Code и др.)

Добавьте конфигурацию в настройки MCP:

**Windows:**
```json
{
  "mcpServers": {
    "laravel-debug": {
      "command": "C:\\laragon\\bin\\php\\php-8.2.30-nts-Win32-vs16-x64\\php.exe",
      "args": ["C:\\путь\\к\\проекту\\.ladebugermcp\\mcp-server.php"],
      "disabled": false
    }
  }
}
```

**Linux/macOS:**
```json
{
  "mcpServers": {
    "laravel-debug": {
      "command": "php",
      "args": ["/путь/к/проекту/.ladebugermcp/mcp-server.php"],
      "disabled": false
    }
  }
}
```

### Важно о путях

- **`command`** — полный путь к исполняемому файлу PHP
- **`args`** — путь к файлу `mcp-server.php` (относительный или абсолютный)
- После изменения конфигурации **перезапустите VS Code**

---

## Требования

- **PHP** 8.0+
- **Laravel** с установленным [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar)
- Логи Debugbar должны сохраняться в `storage/debugbar/*.json`

### Установка Laravel Debugbar

```bash
composer require barryvdh/laravel-debugbar --dev
```

---

## Инструменты

### `debugbar_clear`

Очищает все логи Debugbar.

```
Параметры: нет
Возвращает: "Очищено файлов: N"
```

### `debugbar_get_request_tree`

Возвращает иерархическое дерево последних запросов.

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `limit` | integer | 50 | Количество логов |
| `include_session` | boolean | false | Включить данные сессии |
| `include_cookies` | boolean | false | Включить cookies |

**Пример ответа:**

```json
{
  "id": "01KHP7NYCYMXKDT5JJGPJ4TAQD",
  "url": "http://example.com/crm/reports/sales",
  "method": "GET",
  "status": 200,
  "time": "16:42:04",
  "info": {
    "params": { "query": "...", "request": "..." },
    "performance": { "time": "660ms", "memory": "30MB" },
    "controller": "App\\Http\\Controllers\\ReportController@index"
  },
  "referer": "http://example.com/dashboard",
  "children": [...]
}
```

### `debugbar_get_logs`

Получает детальные структурированные отчёты по логам.

| Параметр | Тип | По умолчанию | Описание |
|----------|-----|--------------|----------|
| `log_ids` | array | [] | ID логов (если пусто - последние) |
| `limit` | integer | 10 | Количество логов |
| `sections` | array | models, views, route, exceptions, messages, gate | Секции для извлечения |

**Доступные секции:**

- `meta` - ID, URL, method, status, datetime (всегда включена)
- `models` - модели Eloquent (retrieved/created/updated/deleted)
- `views` - шаблоны Blade с render_count
- `queries` - SQL запросы с duration и source
- `route` - контроллер, URI, action
- `exceptions` - ошибки
- `messages` - Log сообщения
- `gate` - проверки прав
- `session` - данные сессии (отключена по умолчанию из-за объёма)

**Пример:**

```json
{
  "meta": { "id": "...", "url": "...", "method": "POST", "status": 200 },
  "models": [
    { "class": "App\\Models\\User", "retrieved": 5, "created": 0 }
  ],
  "views": [
    { "name": "users.index", "render_count": 1 }
  ],
  "queries": [
    { "sql": "select * from users", "duration": 0.005, "source": "UserController.php" }
  ],
  "route": {
    "class": "App\\Http\\Controllers\\UserController",
    "uri": "GET /users",
    "action": "index"
  }
}
```

---

## Сценарий использования: Браузерное тестирование

### Протокол отладки

Используйте этот протокол для систематического анализа проблем через браузерное тестирование:

#### 1. Очистка логов (обязательно перед тестом)

```
Вызов: debugbar_clear
Результат: "Очищено файлов: N"
```

#### 2. Запрос на выполнение действий

Попросите пользователя выполнить конкретное действие в браузере:

**Примеры запросов:**
- "Откройте страницу /crm/orders и нажмите кнопку 'Экспорт'"
- "Перейдите в раздел отчётов и примените фильтр по дате"
- "Создайте новый заказ через форму"

#### 3. Получение дерева запросов

```
Вызов: debugbar_get_request_tree
Параметры: { "limit": 20 }
```

**Анализ дерева:**
- Найдите целевой запрос по URL
- Проверьте HTTP статус (200, 302, 500)
- Изучите parent-child связи (referer → location)

#### 4. Детальный анализ логов

```
Вызов: debugbar_get_logs
Параметры: { "sections": ["queries", "models", "exceptions"] }
```

### Типичные сценарии

| Проблема | Секции для анализа | Что искать |
|----------|-------------------|------------|
| Медленная страница | `queries`, `models`, `views` | SQL с duration > 100ms, N+1 (retrieved > 100) |
| Ошибка 500 | `exceptions`, `route` | message, file, line в exceptions |
| Лишние редиректы | дерево запросов | цепочка referer → location |
| Проблемы прав | `gate`, `route` | проверки gate, контроллер |

---

## Пример настройки rules для команды "браузерное тестирование"

Добавьте в ваш файл правил (например, `.kilocode/rules/Правила.md`):

```markdown
### MCP Laravel Debug (Отладка и Логи)
Прямой доступ к логам Debugbar через `.ladebugermcp/mcp-server.php`.

**Протокол отладки**:
1. **Очистка**: Перед началом теста всегда вызывай `debugbar_clear`
2. **Тест**: Попроси пользователя выполнить конкретное действие в браузере
3. **Анализ**: Используй `debugbar_get_request_tree` для анализа цепочки запросов
4. **Детали**: При обнаружении аномалий используй `debugbar_get_logs`

**Типичные сценарии**:
- **Медленная страница**: `queries`, `models`, `views` — найти медленные SQL и N+1 проблемы
- **Ошибка 500**: `exceptions`, `route` — изучить стек ошибки и контроллер
- **Редиректы**: анализировать `referer` → `location` цепочки в дереве запросов
- **Права доступа**: `gate`, `route` — проверить проверки авторизации

**Советы**:
- Не запрашивай `session` без необходимости — большой объём данных
- Анализируй queries с `duration > 100ms` — потенциальные проблемы
- Проверяй models с `retrieved > 100` — возможные N+1 проблемы
```

---

## Протокол MCP

Сервер реализует JSON-RPC 2.0 протокол:

### initialize

```json
{"jsonrpc": "2.0", "id": 1, "method": "initialize"}
```

### tools/list

```json
{"jsonrpc": "2.0", "id": 2, "method": "tools/list"}
```

### tools/call

```json
{
  "jsonrpc": "2.0",
  "id": 3,
  "method": "tools/call",
  "params": {
    "name": "debugbar_get_request_tree",
    "arguments": { "limit": 10 }
  }
}
```

---

## Лицензия

MIT License с дополнительными ограничениями. См. файл [LICENSE](LICENSE).

**Используйте на свой страх и риск.**

---

**Автор:** Sergey O (@Sergey0002)  
**Telegram:** @Sergey0002
