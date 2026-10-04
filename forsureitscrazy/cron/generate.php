<?php
declare(strict_types=1);

/*
 * Daily job:
 *   1. DeepSeek proposes bizarre-but-true story ideas.
 *   2. Firecrawl searches the web for each one and scrapes the top sources + images.
 *   3. DeepSeek writes the article from those sources and picks its theme/layout.
 *   4. The previous day's stories and images are replaced in one transaction.
 * If the web pipeline can't produce enough stories, Wikipedia's "On this day" fills the gap.
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
require __DIR__ . '/../src/Firecrawl.php';
require __DIR__ . '/../src/DeepSeek.php';

const IMAGE_DIR = APP_ROOT . '/public/images/events';
const IMAGE_URL_PREFIX = 'images/events/';
const MAX_IMAGE_BYTES = 8 * 1024 * 1024;
const MAX_SOURCE_CHARS = 6000;

function logLine(string $msg): void
{
    echo '[' . date('Y-m-d H:i:s') . "] $msg\n";
}

/**
 * Downloads the first usable image from the candidates.
 *
 * @param list<array{image:string,page:string}> $candidates
 * @return array{bytes:string,ext:string,page:string}
 */
function fetchImage(Http $http, array $candidates): array
{
    foreach (array_slice($candidates, 0, 8) as $c) {
        try {
            [$bytes, $type] = $http->download($c['image'], MAX_IMAGE_BYTES);
            $size = @getimagesizefromstring($bytes);
            if ($size === false || $size[0] < 400 || $size[1] < 250) {
                continue;
            }
            $ext = match ($size['mime'] ?? $type) {
                'image/png'  => 'png',
                'image/webp' => 'webp',
                'image/gif'  => 'gif',
                'image/jpeg' => 'jpg',
                default      => null,
            };
            if ($ext !== null) {
                return ['bytes' => $bytes, 'ext' => $ext, 'page' => $c['page']];
            }
        } catch (Throwable) {
            // try the next candidate
        }
    }
    throw new RuntimeException('No usable image found');
}

function hostOf(string $url): string
{
    return preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));
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
$firecrawl = null;
try {
    $firecrawl = new Firecrawl($http, (string) ($cfg['firecrawl']['api_key'] ?? ''), $cfg['firecrawl']['base_url'] ?? 'https://api.firecrawl.dev');
} catch (Throwable $ex) {
    logLine($ex->getMessage() . ' — using Wikipedia only.');
}

$pdo = db();
$today = new DateTimeImmutable('today');
$batch = $today->format('Ymd') . '-' . bin2hex(random_bytes(3));

$new = [];
$newFiles = [];

$add = function (array $article, array $image, array $sources) use (&$new, &$newFiles, $batch): void {
    $fileName = sprintf('%s-%d.%s', $batch, count($new) + 1, $image['ext']);
    if (file_put_contents(IMAGE_DIR . '/' . $fileName, $image['bytes']) === false) {
        throw new RuntimeException('Could not write image file');
    }
    $newFiles[] = $fileName;
    $new[] = $article + [
        'image_path'   => IMAGE_URL_PREFIX . $fileName,
        'image_credit' => $image['page'],
        'sources'      => array_map(fn ($s) => ['title' => $s['title'], 'url' => $s['url']], $sources),
    ];
};

// 1) Web pipeline: DeepSeek ideas → Firecrawl research → DeepSeek article
if ($firecrawl !== null) {
    try {
        $recent = $pdo->query('SELECT title FROM topic_history ORDER BY id DESC LIMIT 100')->fetchAll(PDO::FETCH_COLUMN);
        $topics = array_slice($ai->proposeTopics($count, $cfg['topic_mode'] ?? 'mixed', $recent, $today->format('Y-m-d')), 0, $count);
        logLine(count($topics) . ' topic ideas from DeepSeek.');
    } catch (Throwable $ex) {
        logLine('Could not get topic ideas: ' . $ex->getMessage());
        $topics = [];
    }

    foreach ($topics as $topic) {
        if (count($new) >= $count) {
            break;
        }
        try {
            $results = $firecrawl->search($topic['query'], 4);
            if (!$results) {
                throw new RuntimeException('no web results');
            }
            $sources = array_map(fn ($r) => [
                'title' => $r['title'] ?: hostOf($r['url']),
                'url'   => $r['url'],
                'text'  => mb_substr($r['markdown'], 0, MAX_SOURCE_CHARS),
            ], $results);

            $article = $ai->writeArticle($topic['brief'] ?: $topic['query'], $sources);
            if (!$article['supported']) {
                throw new RuntimeException('sources do not support the story');
            }

            $candidates = [];
            foreach ($results as $r) {
                foreach ($r['images'] as $img) {
                    $candidates[] = ['image' => $img, 'page' => $r['url']];
                }
            }
            try {
                $image = fetchImage($http, $candidates);
            } catch (RuntimeException) {
                $image = fetchImage($http, $firecrawl->searchImages($topic['query']));
            }

            $add($article, $image, $sources);
            logLine('OK (web): ' . $topic['query']);
        } catch (Throwable $ex) {
            logLine('Skipped "' . $topic['query'] . '": ' . $ex->getMessage());
        }
    }
}

// 2) Fallback: Wikipedia "On this day"
if (count($new) < $count) {
    logLine('Filling ' . ($count - count($new)) . ' stories from Wikipedia.');
    try {
        $candidates = $wiki->onThisDay($today);
        shuffle($candidates);
    } catch (Throwable $ex) {
        logLine('Wikipedia unavailable: ' . $ex->getMessage());
        $candidates = [];
    }
    foreach ($candidates as $event) {
        if (count($new) >= $count) {
            break;
        }
        $page = $event['page'];
        try {
            $image = fetchImage($http, [['image' => $page['image'], 'page' => $page['url']]]);
            $text = $wiki->articleText($page['key']);
            if (mb_strlen($text) < 300) {
                $text = $page['extract'] . "\n\n" . $text;
            }
            $sources = [['title' => $page['title'] . ' — Wikipedia', 'url' => $page['url'], 'text' => $text]];
            $brief = ($event['year'] !== null ? "In {$event['year']}: " : '') . $event['text'];

            $article = $ai->writeArticle($brief, $sources);
            $article['year'] ??= $event['year'];
            $add($article, $image, $sources);
            logLine('OK (wikipedia): ' . $page['title']);
        } catch (Throwable $ex) {
            logLine('Skipped "' . $page['title'] . '": ' . $ex->getMessage());
        }
    }
}

if (count($new) < $count) {
    // Never wipe the site with a partial set — keep yesterday's stories instead.
    foreach ($newFiles as $f) {
        @unlink(IMAGE_DIR . '/' . $f);
    }
    logLine('Only ' . count($new) . " of $count stories generated — keeping existing stories.");
    exit(1);
}

// Replace the old stories in one transaction.
$pdo->beginTransaction();
try {
    $pdo->exec('DELETE FROM events');
    $stmt = $pdo->prepare(
        'INSERT INTO events (sort_order, event_year, title, summary, sections, fun_fact, category, emoji, theme, layout,
            image_path, image_credit, sources, batch_date, created_at)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $history = $pdo->prepare('INSERT INTO topic_history (title, created_at) VALUES (?, ?)');
    $now = date('Y-m-d H:i:s');
    foreach ($new as $i => $a) {
        $stmt->execute([
            $i + 1, $a['year'], $a['title'], $a['summary'],
            json_encode($a['sections'], JSON_UNESCAPED_UNICODE), $a['fun_fact'], $a['category'], $a['emoji'],
            $a['theme'], $a['layout'], $a['image_path'], $a['image_credit'],
            json_encode($a['sources'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
            $today->format('Y-m-d'), $now,
        ]);
        $history->execute([$a['title'], $now]);
    }
    $pdo->prepare('DELETE FROM topic_history WHERE created_at < ?')
        ->execute([$today->modify('-365 days')->format('Y-m-d')]);
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
foreach (glob(IMAGE_DIR . '/*') ?: [] as $file) {
    if (!in_array(basename($file), $newFiles, true) && basename($file) !== '.gitkeep') {
        @unlink($file);
    }
}

logLine("Done: $count new stories published.");
