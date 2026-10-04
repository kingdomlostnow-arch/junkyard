<?php
declare(strict_types=1);

require __DIR__ . '/../src/bootstrap.php';
require __DIR__ . '/_layout.php';

$events = db()->query('SELECT * FROM events ORDER BY sort_order')->fetchAll();

layoutStart(config()['site_name'] . ' — قصص اليوم', 'خمس أحداث حقيقية مجنونة من التاريخ، تتجدد يومياً.');
?>
<?php if (!$events): ?>
    <section class="empty">
        <h2>لا توجد قصص بعد 🤯</h2>
        <p>ستظهر أول خمس قصص بعد تشغيل مهمة التوليد اليومية.</p>
    </section>
<?php else: ?>
    <h1 class="day-title">قصص يوم <?= e(date('j/n/Y', strtotime($events[0]['batch_date']))) ?></h1>
    <section class="grid">
        <?php foreach ($events as $i => $ev): ?>
            <a class="card<?= $i === 0 ? ' card--hero' : '' ?>" href="event.php?id=<?= (int) $ev['id'] ?>">
                <div class="card__img">
                    <img src="<?= e($ev['image_path']) ?>" alt="<?= e($ev['title']) ?>" loading="<?= $i === 0 ? 'eager' : 'lazy' ?>">
                    <span class="card__num">#<?= $i + 1 ?></span>
                </div>
                <div class="card__body">
                    <div class="meta">
                        <?php if ($ev['event_year'] !== null): ?><span class="year"><?= e((string) $ev['event_year']) ?></span><?php endif; ?>
                        <?php if ($ev['category']): ?><span class="tag"><?= e($ev['category']) ?></span><?php endif; ?>
                    </div>
                    <h2><?= e($ev['title']) ?></h2>
                    <p><?= e($ev['summary']) ?></p>
                    <span class="more">اقرأ القصة كاملة ←</span>
                </div>
            </a>
        <?php endforeach; ?>
    </section>
<?php endif; ?>
<?php layoutEnd(); ?>
