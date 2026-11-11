-- 測試資料（密碼：作者 author123、編輯 editor123）
USE submission_review;
SET NAMES utf8mb4;

INSERT INTO users (username, password_hash, display_name, role) VALUES
('alice',  '$2y$10$GH6DEZGU1v3yOK2n/tIh4OZMtJuDrnmZfhkLI84wTnvZpolRp4may', '王小明', 'author'),
('bob',    '$2y$10$GH6DEZGU1v3yOK2n/tIh4OZMtJuDrnmZfhkLI84wTnvZpolRp4may', '李小華', 'author'),
('editor', '$2y$10$PS4qSCCxHFUw9N51QbtEKenf2q6aK3EsmW4LbBB7YM2gulZxN9oLi', '陳主編', 'editor');

INSERT INTO categories (name) VALUES ('科技'), ('生活'), ('旅遊'), ('文學'), ('評論');

INSERT INTO manuscripts (author_id, category_id, title, content, status, submitted_at) VALUES
(1, 1, '生成式 AI 對大學教育的影響', '近年生成式 AI 快速普及，學生的學習方式也隨之改變……', 'editing', NULL),
(1, 3, '台東三天兩夜慢旅行', '第一天從台北搭火車出發，沿著東海岸一路向南……', 'reviewing', NOW()),
(1, 2, '租屋族的省錢料理', '一只電鍋就能完成的五道料理……', 'approved', NOW()),
(2, 4, '雨夜', '雨落在巷口的鐵皮屋頂上，像一首聽了很多年的歌……', 'reviewing', NOW()),
(2, 5, '從學生視角看校園數位化', '選課系統、線上繳費、行動學生證……', 'editing', NULL);

INSERT INTO review_logs (manuscript_id, user_id, action, comment) VALUES
(2, 1, 'submit',  NULL),
(3, 1, 'submit',  NULL),
(3, 3, 'approve', '內容實用，排版清楚。'),
(4, 2, 'submit',  NULL);
