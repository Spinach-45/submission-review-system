-- 投稿審查系統 資料庫結構
CREATE DATABASE IF NOT EXISTS submission_review
  DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE submission_review;
SET NAMES utf8mb4;

DROP TABLE IF EXISTS review_logs;
DROP TABLE IF EXISTS manuscripts;
DROP TABLE IF EXISTS categories;
DROP TABLE IF EXISTS users;

CREATE TABLE users (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    username      VARCHAR(50)  NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    display_name  VARCHAR(50)  NOT NULL,
    role          ENUM('author', 'editor') NOT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE categories (
    id   INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(50) NOT NULL UNIQUE
) ENGINE=InnoDB;

-- status: editing = 編輯中, reviewing = 審查中, approved = 已通過
CREATE TABLE manuscripts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    author_id    INT NOT NULL,
    category_id  INT NOT NULL,
    title        VARCHAR(200) NOT NULL,
    content      TEXT NOT NULL,
    status       ENUM('editing', 'reviewing', 'approved') NOT NULL DEFAULT 'editing',
    submitted_at DATETIME NULL,
    created_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at   DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_manuscript_author   FOREIGN KEY (author_id)   REFERENCES users(id),
    CONSTRAINT fk_manuscript_category FOREIGN KEY (category_id) REFERENCES categories(id),
    INDEX idx_author_status (author_id, status),
    INDEX idx_status (status)
) ENGINE=InnoDB;

-- 審查歷程：送出、通過、退回都會留下紀錄
CREATE TABLE review_logs (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    manuscript_id INT NOT NULL,
    user_id       INT NOT NULL,
    action        ENUM('submit', 'approve', 'reject') NOT NULL,
    comment       TEXT NULL,
    created_at    DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    CONSTRAINT fk_log_manuscript FOREIGN KEY (manuscript_id) REFERENCES manuscripts(id) ON DELETE CASCADE,
    CONSTRAINT fk_log_user       FOREIGN KEY (user_id)       REFERENCES users(id)
) ENGINE=InnoDB;
