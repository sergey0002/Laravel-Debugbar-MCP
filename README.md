# Laravel Debugbar MCP Server

MCP (Model Context Protocol) сервер для анализа логов Laravel Debugbar. Позволяет ИИ-агентам получать доступ к данным отладки Laravel через стандартизированный протокол.

## Версия: 2.8.0

## Возможности

- **Дерево запросов** - иерархическое отображение HTTP запросов с parent-child связями
- **Детальные логи** - модели, views, SQL запросы, исключения, сообщения
- **Очистка логов** - удаление всех файлов debugbar

## Установка

### 1. Клонирование

```bash
git clone https://github.com/sergey0002/laravel-debuger-mcp.git
```

### 2. Настройка в Kilo Code / VS Code

Добавьте в `.kilocode/mcp.json` или `mcp_settings.json`:

```json
{
  "mcpServers": {
    "laravel-debug": {
      "command": "C:\\laragon\\bin\\php\\php-8.2.30-nts-Win32-vs16-x64\\php.exe",
      "args": ["путь/к/mcp-server.php"],
      "disabled": false
    }
  }
}
```

### 3. Требования

- PHP 8.0+
- Laravel с установленным [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar)
- Путь к логам: `storage/debugbar/*.json`

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

## Разработка

### Структура файлов

```
.ladebugermcp/
├── mcp-server.php      # Основной файл сервера
├── README.md           # Документация
├── LICENSE             # MIT лицензия
└── error.log           # Лог ошибок (создаётся автоматически)
```

### Добавление новых инструментов

1. Добавьте описание в `tools/list`
2. Добавьте обработчик в `handleToolCall()`
3. Реализуйте функцию извлечения данных

## Лицензия

MIT License

## Автор

Sergey

## Ссылки

- [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar)
- [Model Context Protocol](https://modelcontextprotocol.io/)
