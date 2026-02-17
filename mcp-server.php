<?php

/**
 * Laravel Debugbar MCP Server v1.0.0
 * Реализация протокола Модельного Контекста (MCP) для анализа логов Laravel Debugbar.
 * 
 * @author  Sergey O (@Sergey0002)
 * @telegram @Sergey0002
 * @github  https://github.com/sergey0002/Laravel-Debugbar-MCP
 * @license MIT with Additional Restrictions (see LICENSE)
 * 
 * ⚠️ ВНИМАНИЕ: Только для локального сервера разработчика!
 * Не размещайте на публичных или production серверах.
 */

set_error_handler(function ($errno, $errstr, $errfile, $errline) {
    logError("PHP Error [$errno]: $errstr in $errfile on line $errline");
});

set_exception_handler(function ($exception) {
    logError("PHP Exception: " . $exception->getMessage() . "\n" . $exception->getTraceAsString());
});

// Определение путей
$baseDir = dirname(__DIR__);
$debugbarPath = $baseDir . DIRECTORY_SEPARATOR . 'storage' . DIRECTORY_SEPARATOR . 'debugbar';
$errorLogPath = __DIR__ . DIRECTORY_SEPARATOR . 'error.log';

function logError($message) {
    global $errorLogPath;
    file_put_contents($errorLogPath, "[" . date('Y-m-d H:i:s') . "] " . $message . PHP_EOL, FILE_APPEND);
}

function sendResponse($response) {
    echo json_encode($response) . "\n";
}

function sendError($id, $code, $message, $data = null) {
    sendResponse([
        'jsonrpc' => '2.0',
        'id' => $id,
        'error' => [
            'code' => $code,
            'message' => $message,
            'data' => $data
        ]
    ]);
}

// Основной цикл прослушивания
while ($line = fgets(STDIN)) {
    $request = json_decode($line, true);
    if (!$request) continue;

    $method = $request['method'] ?? '';
    $id = $request['id'] ?? null;

    try {
        switch ($method) {
            case 'initialize':
                sendResponse([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'protocolVersion' => '2024-11-05',
                        'capabilities' => [
                            'tools' => (object)[]
                        ],
                        'serverInfo' => [
                            'name' => 'laravel-debugbar-mcp',
                            'version' => '1.0.0',
                            'author' => 'Sergey O (@Sergey0002)',
                            'telegram' => '@Sergey0002'
                        ]
                    ]
                ]);
                break;

            case 'tools/list':
                sendResponse([
                    'jsonrpc' => '2.0',
                    'id' => $id,
                    'result' => [
                        'tools' => [
                            [
                                'name' => 'debugbar_clear',
                                'description' => 'Очищает все логи Debugbar.',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => (object)[]
                                ]
                            ],
                            [
                                'name' => 'debugbar_get_request_tree',
                                'description' => 'Возвращает иерархическое дерево последних запросов с данными GET/POST, Time, Memory. Referer всегда включён.',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'limit' => [
                                            'type' => 'integer',
                                            'description' => 'Количество последних файлов логов для анализа (по умолчанию 50)',
                                            'default' => 50
                                        ],
                                        'include_session' => [
                                            'type' => 'boolean',
                                            'description' => 'Включить данные сессии в вывод (по умолчанию false)',
                                            'default' => false
                                        ],
                                        'include_cookies' => [
                                            'type' => 'boolean',
                                            'description' => 'Включить cookies в вывод (по умолчанию false)',
                                            'default' => false
                                        ]
                                    ]
                                ]
                            ],
                            [
                                'name' => 'debugbar_get_logs',
                                'description' => 'Получает детальные структурированные отчеты по логам (модели, виды, ошибки, сообщения).',
                                'inputSchema' => [
                                    'type' => 'object',
                                    'properties' => [
                                        'log_ids' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                            'description' => 'Массив ID логов. Если не указан — берутся последние запросы.'
                                        ],
                                        'limit' => [
                                            'type' => 'integer',
                                            'description' => 'Количество последних запросов (если log_ids пуст, по умолчанию 10)',
                                            'default' => 10
                                        ],
                                        'sections' => [
                                            'type' => 'array',
                                            'items' => ['type' => 'string'],
                                            'description' => 'Секции для извлечения. По умолчанию: models, views, route, exceptions, messages, gate. Доступные: session (отключена по умолчанию из-за большого объёма данных).'
                                        ]
                                    ]
                                ]
                            ]
                        ]
                    ]
                ]);
                break;

            case 'tools/call':
                $toolName = $request['params']['name'] ?? '';
                $arguments = $request['params']['arguments'] ?? [];
                handleToolCall($id, $toolName, $arguments);
                break;

            default:
                if ($id !== null) {
                    sendError($id, -32601, "Method not found: $method");
                }
                break;
        }
    } catch (Exception $e) {
        logError($e->getMessage());
        if ($id !== null) {
            sendError($id, -32603, "Internal error: " . $e->getMessage());
        }
    }
}

function handleToolCall($id, $name, $args) {
    global $debugbarPath;

    switch ($name) {
        case 'debugbar_clear':
            if (!is_dir($debugbarPath)) {
                sendToolResult($id, "Директория $debugbarPath не найдена.");
                return;
            }
            $files = glob($debugbarPath . DIRECTORY_SEPARATOR . '*.json');
            $count = 0;
            foreach ($files as $file) {
                if (unlink($file)) $count++;
            }
            sendToolResult($id, "Очищено файлов: $count");
            break;

        case 'debugbar_get_request_tree':
            if (!is_dir($debugbarPath)) {
                sendToolResult($id, "Директория $debugbarPath не найдена.");
                return;
            }
            $limit = $args['limit'] ?? 50;
            $includeSession = $args['include_session'] ?? false;
            $includeCookies = $args['include_cookies'] ?? false;
            $files = glob($debugbarPath . DIRECTORY_SEPARATOR . '*.json');
            usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
            $files = array_slice($files, 0, $limit);
            
            $allNodes = [];
            foreach ($files as $file) {
                $content = file_get_contents($file);
                $data = json_decode($content, true);
                if (!$data) continue;

                $meta = $data['__meta'] ?? [];
                $reqData = $data['request']['data'] ?? [];
                
                // Извлечение заголовков (referer всегда включён)
                $referer = extractHeader($reqData, 'referer');
                $location = extractHeader($reqData, 'location');

                // Сбор данных запроса
                $requestInfo = [
                    'params' => [
                        'query' => parseSfDump($reqData['request_query'] ?? []),
                        'request' => parseSfDump($reqData['request_request'] ?? []),
                    ],
                    'performance' => [
                        'time' => $reqData['duration_str'] ?? 'UNKNOWN',
                        'memory' => $data['memory']['peak_usage_str'] ?? 'UNKNOWN',
                    ],
                    'controller' => null
                ];
                
                // Cookies - только если явно запрошено
                if ($includeCookies) {
                    $requestInfo['cookies'] = parseSfDump($reqData['request_cookies'] ?? []);
                }
                
                // Session - только если явно запрошено
                if ($includeSession) {
                    $requestInfo['session'] = parseSfDump($data['session'] ?? []);
                }

                if (isset($data['route']['controller'])) {
                    if (preg_match('/([\\\\A-Za-z0-9]+Controller)/', $data['route']['controller'], $m)) {
                        $requestInfo['controller'] = $m[1] . (preg_match('/::(\w+)|@(\w+)/', $data['route']['controller'], $am) ? '@' . ($am[1] ?: $am[2]) : '');
                    }
                }

                $id_node = $meta['id'] ?? basename($file, '.json');
                $allNodes[$id_node] = [
                    'id' => $id_node,
                    'url' => $reqData['full_url'] ?? $meta['uri'] ?? 'UNKNOWN',
                    'method' => $meta['method'] ?? 'UNKNOWN',
                    'status' => (int)($reqData['status'] ?? 0),
                    'time' => date('H:i:s', (int)($meta['utime'] ?? filemtime($file))),
                    'utime' => $meta['utime'] ?? filemtime($file),
                    'info' => $requestInfo,
                    'referer' => $referer,
                    'location' => $location,
                    'session_token' => $data['session']['_token'] ?? '',
                    'children' => []
                ];
            }

            uasort($allNodes, function($a, $b) { return $a['utime'] <=> $b['utime']; });

            $tree = [];
            $childIds = [];
            foreach ($allNodes as $nodeId => &$node) {
                foreach ($allNodes as $pId => &$pNode) {
                    if ($nodeId === $pId) continue;
                    if (($pNode['location'] === $node['url'] && $pNode['utime'] < $node['utime'] && ($node['utime'] - $pNode['utime'] < 5)) ||
                        ($node['referer'] === $pNode['url'] && $pNode['utime'] < $node['utime'] && (!$node['session_token'] || $node['session_token'] === $pNode['session_token']))) {
                        $pNode['children'][] = &$node;
                        $childIds[] = $nodeId;
                        break;
                    }
                }
            }

            foreach ($allNodes as $nodeId => &$node) {
                if (!in_array($nodeId, $childIds)) $tree[] = &$node;
            }
            usort($tree, function($a, $b) { return $b['utime'] <=> $a['utime']; });

            sendToolResult($id, json_encode($tree, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            break;

        case 'debugbar_get_logs':
            if (!is_dir($debugbarPath)) {
                sendToolResult($id, "Директория $debugbarPath не найдена.", true);
                return;
            }
            
            $logIds = $args['log_ids'] ?? [];
            $limit = $args['limit'] ?? 10;
            $sections = $args['sections'] ?? ['models', 'views', 'route', 'exceptions', 'messages', 'gate'];
            
            if (empty($logIds)) {
                $files = glob($debugbarPath . DIRECTORY_SEPARATOR . '*.json');
                usort($files, function($a, $b) { return filemtime($b) - filemtime($a); });
                $files = array_slice($files, 0, $limit);
                foreach ($files as $file) $logIds[] = basename($file, '.json');
            }
            
            $reports = [];
            foreach ($logIds as $logId) {
                $file = $debugbarPath . DIRECTORY_SEPARATOR . $logId . '.json';
                if (!file_exists($file)) continue;
                $data = json_decode(file_get_contents($file), true);
                if (!$data) continue;

                $report = buildFullReport($data);
                $filteredReport = ['meta' => $report['meta']];
                foreach ($sections as $sec) {
                    if (isset($report[$sec])) $filteredReport[$sec] = $report[$sec];
                    elseif (isset($data[$sec])) $filteredReport[$sec] = parseSfDump($data[$sec]);
                }
                $reports[] = $filteredReport;
            }

            sendToolResult($id, json_encode(['reports' => $reports], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
            break;

        default:
            sendToolResult($id, "Tool not found: $name", true);
            break;
    }
}

/**
 * Извлекает значение заголовка из HTML-дампа Debugbar
 */
function extractHeader($reqData, $headerName) {
    // Ищем в request_headers
    $source = $reqData['request_headers'] ?? '';
    if (empty($source)) return '';
    
    // Паттерн: ключ заголовка -> array -> значение в sf-dump-str
    // Формат sf-dump: "referer" => array:1 [ 0 => "http://..." ]
    // Ищем: "<span class=sf-dump-key>referer</span>" ... "<span class=sf-dump-str...>http://...</span>"
    $pattern = '/"<span class=sf-dump-key>' . preg_quote($headerName, '/') . '<\/span>".*?"<span class=sf-dump-str[^>]*>([^<]*)<\/span>"/s';
    if (preg_match($pattern, $source, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
    return '';
}

/**
 * Парсит HTML-дамп sf-dump в читаемый текст
 */
function parseSfDump($data) {
    if (is_array($data)) {
        $result = [];
        foreach ($data as $k => $v) $result[$k] = parseSfDump($v);
        return $result;
    }
    
    if (!is_string($data)) return $data;
    if (strpos($data, 'sf-dump') === false) return $data;

    // Удаляем скрипты
    $data = preg_replace('/<script.*?>.*?<\/script>/is', '', $data);
    
    // Заменяем ключи и значения для лучшей читаемости
    $data = preg_replace('/<span[^>]*class=["\']sf-dump-key["\'][^>]*>(.*?)<\/span>/s', '$1', $data);
    $data = preg_replace('/<span[^>]*class=["\']sf-dump-str["\'][^>]*>(.*?)<\/span>/s', '"$1"', $data);
    $data = preg_replace('/<span[^>]*class=["\']sf-dump-note["\'][^>]*>(.*?)<\/span>/s', '($1)', $data);
    
    // Удаляем все остальные теги
    $data = strip_tags($data);
    // Декодируем сущности
    $data = html_entity_decode($data, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    
    // Убираем лишние пробелы и пустые строки
    $lines = explode("\n", $data);
    $cleanedLines = [];
    foreach ($lines as $line) {
        $trimmed = trim($line);
        if ($trimmed !== '') $cleanedLines[] = $line;
    }
    
    return implode("\n", $cleanedLines);
}

/**
 * Строит полный структурированный отчёт по одному запросу
 */
function buildFullReport($data) {
    $meta = $data['__meta'] ?? [];
    $reqData = $data['request']['data'] ?? [];
    
    $controller = null;
    if (isset($data['route']['controller'])) {
        $cStr = $data['route']['controller'];
        if (preg_match('/([\\\\A-Za-z0-9]+Controller)/', $cStr, $m)) {
            $controller = [
                'class' => $m[1],
                'uri' => $data['route']['uri'] ?? null,
                'action' => preg_match('/::(\w+)|@(\w+)/', $cStr, $am) ? ($am[1] ?: $am[2]) : null,
            ];
        }
    }
    
    $models = [];
    if (isset($data['models']['data'])) {
        foreach ($data['models']['data'] as $class => $mdata) {
            $models[] = ['class' => $class, 'retrieved' => $mdata['retrieved'] ?? 0, 'created' => $mdata['created'] ?? 0, 'updated' => $mdata['updated'] ?? 0, 'deleted' => $mdata['deleted'] ?? 0];
        }
    }
    
    $views = [];
    if (isset($data['views']['templates'])) {
        foreach ($data['views']['templates'] as $t) {
            $views[] = ['name' => $t['name_original'] ?? $t['name'], 'render_count' => $t['render_count'] ?? 1];
        }
    }
    
    $queries = [];
    if (isset($data['queries']['statements'])) {
        foreach ($data['queries']['statements'] as $s) {
            $queries[] = ['sql' => $s['sql'], 'duration' => $s['duration'] ?? 0, 'source' => $s['source']['name'] ?? null];
        }
    }

    $exceptions = [];
    if (isset($data['exceptions']['exceptions'])) {
        foreach ($data['exceptions']['exceptions'] as $e) {
            $exceptions[] = ['message' => $e['message'], 'file' => $e['file'], 'line' => $e['line']];
        }
    }

    return [
        'meta' => [
            'id' => $meta['id'] ?? null,
            'url' => $reqData['full_url'] ?? $meta['uri'] ?? null,
            'method' => $meta['method'] ?? null,
            'status' => (int)($reqData['status'] ?? 0),
            'datetime' => $meta['datetime'] ?? null,
        ],
        'route' => $controller,
        'models' => $models,
        'views' => $views,
        'queries' => $queries,
        'exceptions' => $exceptions,
        'messages' => parseSfDump($data['messages']['data'] ?? []),
        'gate' => parseSfDump($data['gate']['messages'] ?? []),
        'session' => parseSfDump($data['session'] ?? [])
    ];
}

function sendToolResult($id, $text, $isError = false) {
    sendResponse([
        'jsonrpc' => '2.0',
        'id' => $id,
        'result' => [
            'content' => [['type' => 'text', 'text' => $text]],
            'isError' => $isError
        ]
    ]);
}
