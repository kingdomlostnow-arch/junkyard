<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/_layout.php';

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$ev = null;
if ($id) {
    $stmt = db()->prepare('SELECT * FROM events WHERE id = ?');
    $stmt->execute([$id]);
    $ev = $stmt->fetch() ?: null;
}

if ($ev === null) {
    // Old links stop working once the day's stories are replaced.
    http_response_code(404);
    layoutStart('القصة غير موجودة');
    echo '<section class="empty"><h2>هذه القصة لم تعد موجودة</h2><p>القصص تتجدد كل يوم. <a href="./">شاهد قصص اليوم</a></p></section>';
    layoutEnd();
    exit;
}

$sections = json_decode($ev['sections'], true) ?: [];
$sources = json_decode((string) $ev['sources'], true) ?: [];
$layout = in_array($ev['layout'], ['classic', 'magazine', 'timeline'], true) ? $ev['layout'] : 'classic';
$creditHost = preg_replace('/^www\./', '', (string) parse_url((string) $ev['image_credit'], PHP_URL_HOST));

$others = db()->prepare('SELECT id, title, image_path, theme FROM events WHERE id <> ? ORDER BY sort_order');
$others->execute([$ev['id']]);

function paragraphs(string $text): void
{
    foreach (preg_split('/\R{2,}/', trim($text)) as $para) {
        echo '<p>' . nl2br(e(trim($para))) . '</p>';
    }
}

function safeUrl(?string $url): string
{
    return preg_match('#^https?://#i', (string) $url) ? (string) $url : '#';
}

layoutStart($ev['title'] . ' — ' . config()['site_name'], $ev['summary'], 'theme-' . $ev['theme']);
?>
<article class="story story--<?= e($layout) ?>">
    <a class="back" href="./">→ كل قصص اليوم</a>

    <?php ob_start(); ?>
    <div class="meta">
        <?php if ($ev['emoji']): ?><span class="emoji"><?= e($ev['emoji']) ?></span><?php endif; ?>
        <?php if ($ev['event_year'] !== null): ?><span class="year"><?= e((string) $ev['event_year']) ?></span><?php endif; ?>
        <?php if ($ev['category']): ?><span class="tag"><?= e($ev['category']) ?></span><?php endif; ?>
    </div>
    <h1><?= e($ev['title']) ?></h1>
    <p class="lead"><?= e($ev['summary']) ?></p>
    <?php $heading = ob_get_clean(); ?>

    <?php if ($layout === 'magazine'): ?>
        <header class="hero" style="background-image: url('<?= e($ev['image_path']) ?>')">
            <div class="hero__text"><?= $heading ?></div>
        </header>
    <?php else: ?>
        <header><?= $heading ?></header>
        <figure>
            <img src="<?= e($ev['image_path']) ?>" alt="<?= e($ev['title']) ?>">
        </figure>
    <?php endif; ?>
    <?php if ($creditHost): ?>
        <p class="credit">مصدر الصورة: <a href="<?= e(safeUrl($ev['image_credit'])) ?>" target="_blank" rel="noopener nofollow" dir="ltr"><?= e($creditHost) ?></a></p>
    <?php endif; ?>

    <div class="content">
        <?php foreach ($sections as $i => $s): ?>
            <section class="chapter">
                <h2><?= e($s['heading'] ?? '') ?></h2>
                <?php paragraphs((string) ($s['body'] ?? '')); ?>
            </section>
            <?php if ($ev['fun_fact'] && $i === intdiv(count($sections) - 1, 2)): ?>
                <aside class="fact"><strong>🤯 هل تعلم؟</strong> <?= e($ev['fun_fact']) ?></aside>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>

    <?php if ($sources): ?>
        <section class="sources">
            <h3>المصادر</h3>
            <ol>
                <?php foreach ($sources as $src): ?>
                    <li><a href="<?= e(safeUrl($src['url'] ?? '')) ?>" target="_blank" rel="noopener nofollow"><?= e($src['title'] ?? $src['url'] ?? '') ?></a></li>
                <?php endforeach; ?>
            </ol>
        </section>
    <?php endif; ?>
</article>

<section class="others">
    <h2>قصص أخرى اليوم</h2>
    <div class="others__list">
        <?php foreach ($others as $o): ?>
            <a class="theme-<?= e($o['theme']) ?>" href="event.php?id=<?= (int) $o['id'] ?>">
                <img src="<?= e($o['image_path']) ?>" alt="" loading="lazy">
                <span><?= e($o['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php layoutEnd(); ?>
