<?php
declare(strict_types=1);

/*
 * Daily job: picks random real events from Wikipedia's "On this day",
 * downloads their images, has DeepSeek write the articles, then replaces
 * the previous day's events and images.
 *
 * Crontab (runs at 00:00 server time):
 *   0 0 * * * php /path/to/forsureitscrazy/cron/generate.php >> /path/to/forsureitscrazy/storage/cron.log 2>&1
 */

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit('CLI only');
}

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/../src/Http.php';
require __DIR__ . '/../src/Wikipedia.php';
require __DIR__ . '/../src/DeepSeek.php';

const IMAGE_DIR = APP_ROOT . '/public/images/events';
const IMAGE_URL_PREFIX = 'images/events/';
const MAX_IMAGE_BYTES = 8 * 1024 * 1024;

function logLine(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . "] $msg\n";
}

$lock = fopen(APP_ROOT . '/storage/generate.lock', 'c');
if (!flock($lock, LOCK_EX | LOCK_NB)) {
    logLine('Another run is in progress, exiting.');
    exit(0);
}

$cfg = config();
$count = (int) ($cfg['events_per_day'] ?? 5);
$http = new Http($cfg['wikipedia']['user_agent']);
$wiki = new Wikipedia($http, $cfg['wikipedia']['lang'] ?? 'en');
$ai = new DeepSeek(
    $http,
    (string) $cfg['deepseek']['api_key'],
    $cfg['deepseek']['base_url'] ?? 'https://api.deepseek.com',
    $cfg['deepseek']['model'] ?? 'deepseek-chat',
    $cfg['output_language'] ?? 'Arabic',
);

$today = new DateTimeImmutable('today');
$batch = $today->format('Ymd') . '-' . bin2hex(random_bytes(3));

try {
    $candidates = $wiki->onThisDay($today);
} catch (Throwable $ex) {
    logLine('Could not fetch events: ' . $ex->getMessage() . ' — keeping existing events.');
    exit(1);
}
shuffle($candidates);
logLine(count($candidates) . " candidate events for {$today->format('m-d')}.");

$new = [];
$newFiles = [];
$usedPages = [];
foreach ($candidates as $event) {
    if (count($new) >= $count) {
        break;
    }
    $page = $event['page'];
    if (isset($usedPages[$page['key']])) {
        continue;
    }
    try {
        // Image
        [$bytes, $type] = $http->download($page['image'], MAX_IMAGE_BYTES);
        $ext = match (true) {
            str_contains($type, 'png')  => 'png',
            str_contains($type, 'webp') => 'webp',
            str_contains($type, 'gif')  => 'gif',
            str_contains($type, 'jpeg'), str_contains($type, 'jpg') => 'jpg',
            default => throw new RuntimeException("Not an image ($type)"),
        };
        if (@getimagesizefromstring($bytes) === false) {
            throw new RuntimeException('Downloaded file is not a valid image');
        }

        // Article
        $source = $wiki->articleText($page['key']);
        if (mb_strlen($source) < 300) {
            $source = $page['extract'] . "\n\n" . $source;
        }
        $article = $ai->writeArticle($event['year'], $event['text'], $page['title'], $source);

        $fileName = sprintf('%s-%d.%s', $batch, count($new) + 1, $ext);
        if (file_put_contents(IMAGE_DIR . '/' . $fileName, $bytes) === false) {
            throw new RuntimeException('Could not write image file');
        }
        $newFiles[] = $fileName;
        $usedPages[$page['key']] = true;

        $new[] = $article + [
            'year'         => $event['year'],
            'image_path'   => IMAGE_URL_PREFIX . $fileName,
            'image_credit' => 'Wikipedia / Wikimedia Commons',
            'source_title' => $page['title'],
            'source_url'   => $page['url'],
        ];
        logLine('OK: ' . $page['title']);
    } catch (Throwable $ex) {
        logLine('Skipped "' . $page['title'] . '": ' . $ex->getMessage());
    }
}

if (count($new) < $count) {
    // Never wipe the site with a partial set — keep yesterday's events instead.
    foreach ($newFiles as $f) {
        @unlink(IMAGE_DIR . '/' . $f);
    }
    logLine('Only ' . count($new) . " of $count events generated — keeping existing events.");
    exit(1);
}

// Replace the old events in one transaction.
$pdo = db();
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM events');
    $stmt = $pdo->prepare(
        'INSERT INTO events (sort_order, event_year, title, summary, content, fun_fact, category,
            image_path, image_credit, source_title, source_url, batch_date, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $now = date('Y-m-d H:i:s');
    foreach ($new as $i => $a) {
        $stmt->execute([
            $i + 1, $a['year'], $a['title'], $a['summary'], $a['content'], $a['fun_fact'], $a['category'],
            $a['image_path'], $a['image_credit'], $a['source_title'], $a['source_url'],
            $today->format('Y-m-d'), $now,
        ]);
    }
    $pdo->commit();
} catch (Throwable $ex) {
    $pdo->rollBack();
    foreach ($newFiles as $f) {
        @unlink(IMAGE_DIR . '/' . $f);
    }
    logLine('Database error: ' . $ex->getMessage());
    exit(1);
}

// Delete the previous images.
foreach (glob(IMAGE_DIR . '/*') as $file) {
    if (!in_array(basename($file), $newFiles, true) && basename($file) !== '.gitkeep') {
        @unlink($file);
    }
}

logLine("Done: $count new events published.");
