<?php
// انسخ هذا الملف إلى config.php ثم عدّل القيم.
return [
    'site_name' => 'forsureitscrazy',

    // المنطقة الزمنية التي يُحسب فيها "منتصف الليل" وتاريخ اليوم
    'timezone' => 'Asia/Riyadh',

    'events_per_day' => 5,

    'db' => [
        'dsn'  => 'mysql:host=localhost;dbname=forsureitscrazy;charset=utf8mb4',
        'user' => 'forsureitscrazy',
        'pass' => 'CHANGE_ME',
    ],

    'deepseek' => [
        // يمكن تركه فارغاً واستخدام متغير البيئة DEEPSEEK_API_KEY بدلاً منه
        'api_key'  => '',
        'base_url' => 'https://api.deepseek.com',
        'model'    => 'deepseek-chat',
    ],

    // البحث في الإنترنت وجلب المصادر والصور
    'firecrawl' => [
        // يمكن تركه فارغاً واستخدام متغير البيئة FIRECRAWL_API_KEY بدلاً منه
        'api_key'  => '',
        'base_url' => 'https://api.firecrawl.dev',
    ],

    // نوع المواضيع: 'mixed' (تاريخية وحديثة) أو 'history' أو 'modern'
    'topic_mode' => 'mixed',

    // مصدر احتياطي إذا فشل البحث في الإنترنت: ويكيبيديا "في مثل هذا اليوم"
    'wikipedia' => [
        'lang' => 'en',
        // ويكيميديا تشترط User-Agent يحتوي وسيلة تواصل
        'user_agent' => 'forsureitscrazy/1.0 (https://example.com; you@example.com)',
    ],

    // لغة المقالات التي يكتبها الذكاء الاصطناعي
    'output_language' => 'Arabic',
];
