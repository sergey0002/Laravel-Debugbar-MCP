# Laravel Debugbar MCP Server

MCP (Model Context Protocol) server for analyzing Laravel Debugbar logs. Allows AI agents to access Laravel debugging data through a standardized protocol.

## Version: 1.0.0

**Author:** Sergey O (@Sergey0002)  
**Telegram:** @Sergey0002  
**GitHub:** https://github.com/sergey0002/Laravel-Debugbar-MCP

---

## ⚠️ IMPORTANT: Usage Restrictions

This software is intended **exclusively for use on a local developer server**.

**PROHIBITED:**
- Deployment on public servers
- Deployment on production servers

---

## Compatibility

Tested and works with:
- **Laravel**: 7.x, 8.x, 9.x, 10.x, 11.x, 12.x
- **PHP**: 8.0+
- **Laravel Debugbar**: 3.x+

---

## Installation

### Step 1: Create Folder

Create a `.ladebugermcp` folder in your Laravel project root:

```bash
mkdir .ladebugermcp
```

### Step 2: Download File

Download `mcp-server.php` and place it in the created folder:

```
your-laravel-project/
├── .ladebugermcp/
│   └── mcp-server.php    ← MCP server file
├── app/
├── storage/
│   └── debugbar/         ← Debugbar logs (automatic)
└── ...
```

### Step 3: Add to Git Ignore (REQUIRED!)

**Important!** Add the folder to your local git ignore to prevent accidental publication:

**Option A: Local exclude (recommended)**
```bash
echo "/.ladebugermcp/" >> .git/info/exclude
```

**Option B: In project .gitignore**
```gitignore
# MCP Debugbar server (local development tool)
.ladebugermcp/
```

This prevents accidental publication of sensitive debugging data in public repositories.

---

## MCP Configuration

### For Kilo Code

Add configuration to `.kilocode/mcp.json` in your project root:

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

### For VS Code (Cline, Roo Code, etc.)

Add configuration to MCP settings:

**Windows:**
```json
{
  "mcpServers": {
    "laravel-debug": {
      "command": "C:\\laragon\\bin\\php\\php-8.2.30-nts-Win32-vs16-x64\\php.exe",
      "args": ["C:\\path\\to\\project\\.ladebugermcp\\mcp-server.php"],
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
      "args": ["/path/to/project/.ladebugermcp/mcp-server.php"],
      "disabled": false
    }
  }
}
```

### Important Notes About Paths

- **`command`** — full path to PHP executable
- **`args`** — path to `mcp-server.php` (relative or absolute)
- After changing configuration **restart VS Code**

---

## Requirements

- **PHP** 8.0+
- **Laravel** with [Laravel Debugbar](https://github.com/barryvdh/laravel-debugbar) installed
- Debugbar logs must be saved to `storage/debugbar/*.json`

### Install Laravel Debugbar

```bash
composer require barryvdh/laravel-debugbar --dev
```

---

## Tools

### `debugbar_clear`

Clears all Debugbar logs.

```
Parameters: none
Returns: "Cleared files: N"
```

### `debugbar_get_request_tree`

Returns hierarchical tree of recent requests.

| Parameter | Type | Default | Description |
|----------|-----|--------------|----------|
| `limit` | integer | 50 | Number of logs |
| `include_session` | boolean | false | Include session data |
| `include_cookies` | boolean | false | Include cookies |

**Example response:**

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

Gets detailed structured reports from logs.

| Parameter | Type | Default | Description |
|----------|-----|--------------|----------|
| `log_ids` | array | [] | Log IDs (if empty - recent) |
| `limit` | integer | 10 | Number of logs |
| `sections` | array | models, views, route, exceptions, messages, gate | Sections to extract |

**Available sections:**

- `meta` - ID, URL, method, status, datetime (always included)
- `models` - Eloquent models (retrieved/created/updated/deleted)
- `views` - Blade templates with render_count
- `queries` - SQL queries with duration and source
- `route` - controller, URI, action
- `exceptions` - errors
- `messages` - Log messages
- `gate` - authorization checks
- `session` - session data (disabled by default due to size)

**Example:**

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

## Usage Scenario: Browser Testing

### Debugging Protocol

Use this protocol for systematic problem analysis through browser testing:

#### 1. Clear Logs (required before test)

```
Call: debugbar_clear
Result: "Cleared files: N"
```

#### 2. Request Action

Ask the user to perform a specific action in the browser:

**Example requests:**
- "Open the /crm/orders page and click the 'Export' button"
- "Go to the reports section and apply a date filter"
- "Create a new order through the form"

#### 3. Get Request Tree

```
Call: debugbar_get_request_tree
Parameters: { "limit": 20 }
```

**Tree analysis:**
- Find the target request by URL
- Check HTTP status (200, 302, 500)
- Study parent-child relationships (referer → location)

#### 4. Detailed Log Analysis

```
Call: debugbar_get_logs
Parameters: { "sections": ["queries", "models", "exceptions"] }
```

### Typical Scenarios

| Problem | Sections to Analyze | What to Look For |
|----------|-------------------|------------|
| Slow page | `queries`, `models`, `views` | SQL with duration > 100ms, N+1 (retrieved > 100) |
| Error 500 | `exceptions`, `route` | message, file, line in exceptions |
| Extra redirects | request tree | referer → location chain |
| Permission issues | `gate`, `route` | gate checks, controller |

---

## Example Rules Configuration

Add to your rules file (e.g., `.kilocode/rules/Правила.md`):

```markdown
### MCP Laravel Debug (Debugging and Logs)
Direct access to Debugbar logs via `.ladebugermcp/mcp-server.php`.

**Debugging Protocol**:
1. **Clear**: Always call `debugbar_clear` before starting a test
2. **Test**: Ask the user to perform a specific action in the browser
3. **Analyze**: Use `debugbar_get_request_tree` to analyze the request chain
4. **Details**: Use `debugbar_get_logs` when anomalies are found

**Typical Scenarios**:
- **Slow page**: `queries`, `models`, `views` — find slow SQL and N+1 issues
- **Error 500**: `exceptions`, `route` — study error stack and controller
- **Redirects**: analyze `referer` → `location` chains in request tree
- **Access rights**: `gate`, `route` — check authorization

**Tips**:
- Don't request `session` without necessity — large data volume
- Analyze queries with `duration > 100ms` — potential issues
- Check models with `retrieved > 100` — possible N+1 issues
```

---

## MCP Protocol

Server implements JSON-RPC 2.0 protocol:

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

## License

MIT License with additional restrictions. See [LICENSE](LICENSE) file.

**Use at your own risk.**

---

**Author:** Sergey O (@Sergey0002)  
**Telegram:** @Sergey0002

---

## Документация на русском языке

Полная документация на русском языке доступна в файле [README.ru.md](README.ru.md)
