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

$others = db()->prepare('SELECT id, title, image_path FROM events WHERE id <> ? ORDER BY sort_order');
$others->execute([$ev['id']]);

layoutStart($ev['title'] . ' — ' . config()['site_name'], $ev['summary']);
?>
<article class="story">
    <a class="back" href="./">→ كل قصص اليوم</a>
    <div class="meta">
        <?php if ($ev['event_year'] !== null): ?><span class="year"><?= e((string) $ev['event_year']) ?></span><?php endif; ?>
        <?php if ($ev['category']): ?><span class="tag"><?= e($ev['category']) ?></span><?php endif; ?>
    </div>
    <h1><?= e($ev['title']) ?></h1>
    <p class="lead"><?= e($ev['summary']) ?></p>

    <figure>
        <img src="<?= e($ev['image_path']) ?>" alt="<?= e($ev['title']) ?>">
        <figcaption><?= e($ev['image_credit']) ?></figcaption>
    </figure>

    <div class="content">
        <?php foreach (preg_split('/\R{2,}/', $ev['content']) as $para): ?>
            <p><?= nl2br(e(trim($para))) ?></p>
        <?php endforeach; ?>
    </div>

    <?php if ($ev['fun_fact']): ?>
        <aside class="fact"><strong>🤯 هل تعلم؟</strong> <?= e($ev['fun_fact']) ?></aside>
    <?php endif; ?>

    <?php if ($ev['source_url']): ?>
        <p class="source">المصدر: <a href="<?= e($ev['source_url']) ?>" target="_blank" rel="noopener" dir="ltr"><?= e($ev['source_title']) ?> — Wikipedia</a></p>
    <?php endif; ?>
</article>

<section class="others">
    <h2>قصص أخرى اليوم</h2>
    <div class="others__list">
        <?php foreach ($others as $o): ?>
            <a href="event.php?id=<?= (int) $o['id'] ?>">
                <img src="<?= e($o['image_path']) ?>" alt="" loading="lazy">
                <span><?= e($o['title']) ?></span>
            </a>
        <?php endforeach; ?>
    </div>
</section>
<?php layoutEnd(); ?>
