<?php
declare(strict_types=1);

const APP_ROOT = __DIR__ . '/..';

function config(): array
{
    static $config = null;
    if ($config === null) {
        $file = APP_ROOT . '/config.php';
        if (!is_file($file)) {
            http_response_code(500);
            exit("Missing config.php — copy config.example.php to config.php and edit it.\n");
        }
        $config = require $file;
        foreach (['DEEPSEEK_API_KEY' => 'deepseek', 'FIRECRAWL_API_KEY' => 'firecrawl'] as $env => $service) {
            $key = getenv($env);
            if ($key !== false && $key !== '') {
                $config[$service]['api_key'] = $key;
            }
        }
        date_default_timezone_set($config['timezone'] ?? 'UTC');
    }
    return $config;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $c = config()['db'];
        $pdo = new PDO($c['dsn'], $c['user'] ?? null, $c['pass'] ?? null, [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    }
    return $pdo;
}

function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

config();
