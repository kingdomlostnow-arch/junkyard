-- قاعدة بيانات MySQL / MariaDB
CREATE DATABASE IF NOT EXISTS forsureitscrazy CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE forsureitscrazy;

CREATE TABLE IF NOT EXISTS events (
    id            INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    sort_order    TINYINT UNSIGNED NOT NULL,
    event_year    INT NULL,
    title         VARCHAR(255) NOT NULL,
    summary       TEXT NOT NULL,
    content       MEDIUMTEXT NOT NULL,
    fun_fact      TEXT NULL,
    category      VARCHAR(100) NULL,
    image_path    VARCHAR(500) NULL,
    image_credit  VARCHAR(500) NULL,
    source_title  VARCHAR(255) NULL,
    source_url    VARCHAR(500) NULL,
    batch_date    DATE NOT NULL,
    created_at    DATETIME NOT NULL,
    INDEX idx_sort (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
