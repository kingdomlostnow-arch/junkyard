-- قاعدة بيانات MySQL / MariaDB
CREATE DATABASE IF NOT EXISTS forsureitscrazy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE forsureitscrazy;

-- قصص اليوم الخمس (تُستبدل كل يوم)
CREATE TABLE IF NOT EXISTS events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sort_order    TINYINT UNSIGNED NOT NULL,
    event_year    INT NULL,
    title         VARCHAR(255) NOT NULL,
    summary       TEXT NOT NULL,
    sections      MEDIUMTEXT NOT NULL,        -- JSON: [{"heading": "...", "body": "..."}]
    fun_fact      TEXT NULL,
    category      VARCHAR(50) NULL,
    emoji         VARCHAR(16) NULL,
    theme         VARCHAR(20) NOT NULL DEFAULT 'neon',
    layout        VARCHAR(20) NOT NULL DEFAULT 'classic',
    image_path    VARCHAR(500) NULL,
    image_credit  VARCHAR(1000) NULL,         -- رابط الصفحة التي أُخذت منها الصورة
    sources       TEXT NULL,                  -- JSON: [{"title": "...", "url": "..."}]
    batch_date    DATE NOT NULL,
    created_at    DATETIME NOT NULL,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- عناوين القصص المنشورة سابقاً حتى لا يكررها الذكاء الاصطناعي (تُحذف بعد سنة)
CREATE TABLE IF NOT EXISTS topic_history (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    title       VARCHAR(255) NOT NULL,
    created_at  DATETIME NOT NULL,
    INDEX idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
