<?php
declare(strict_types=1);

function layoutStart(string $title, string $description = ''): void
{
    $site = config()['site_name'];
    ?>
<!doctype html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($title) ?></title>
    <meta name="description" content="<?= e($description) ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;800&family=Bungee&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="assets/style.css">
</head>
<body>
<header class="site-header">
    <a class="logo" href="./" dir="ltr">for<span>sure</span>its<em>crazy</em></a>
    <p class="tagline">خمس قصص حقيقية… لا تُصدَّق. تتجدد كل يوم عند منتصف الليل.</p>
</header>
<main>
<?php
}

function layoutEnd(): void
{
    $midnight = (new DateTimeImmutable('tomorrow'))->getTimestamp() * 1000;
    ?>
</main>
<footer class="site-footer">
    <div class="countdown">القصص الجديدة بعد <strong id="countdown" data-target="<?= $midnight ?>">--:--:--</strong></div>
    <p>المصادر والصور: ويكيبيديا وويكيميديا كومنز · النصوص مكتوبة بالذكاء الاصطناعي (DeepSeek)</p>
</footer>
<script>
(function () {
    var el = document.getElementById('countdown');
    var target = Number(el.dataset.target);
    function pad(n) { return String(n).padStart(2, '0'); }
    function tick() {
        var s = Math.max(0, Math.floor((target - Date.now()) / 1000));
        el.textContent = pad(Math.floor(s / 3600)) + ':' + pad(Math.floor(s % 3600 / 60)) + ':' + pad(s % 60);
        if (s === 0) { setTimeout(function () { location.reload(); }, 5 * 60 * 1000); return; }
        setTimeout(tick, 1000);
    }
    tick();
})();
</script>
</body>
</html>
<?php
}
