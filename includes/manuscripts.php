<?php
/**
 * 稿件資料存取與狀態轉換。
 *
 * 狀態機：
 *   編輯中(editing) --作者送出--> 審查中(reviewing) --編輯通過--> 已通過(approved)
 *                     <--編輯退回--
 *
 * 每個狀態轉換都用「UPDATE ... WHERE 目前狀態 = ?」完成，
 * 在資料庫層確保只有合法的轉換會成功，不只靠前端隱藏按鈕。
 */
require_once __DIR__ . '/helpers.php';

function categories(): array
{
    return db()->query('SELECT id, name FROM categories ORDER BY id')->fetchAll();
}

/** 作者自己的稿件清單，可依狀態、類別篩選 */
function author_manuscripts(int $authorId, ?string $status = null, ?int $categoryId = null): array
{
    $sql = 'SELECT m.id, m.title, m.status, m.updated_at, c.name AS category
            FROM manuscripts m JOIN categories c ON c.id = m.category_id
            WHERE m.author_id = ?';
    $params = [$authorId];
    if ($status !== null && isset(STATUS_LABELS[$status])) {
        $sql .= ' AND m.status = ?';
        $params[] = $status;
    }
    if ($categoryId) {
        $sql .= ' AND m.category_id = ?';
        $params[] = $categoryId;
    }
    $sql .= ' ORDER BY m.updated_at DESC, m.id DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/** 編輯的待審查清單 */
function pending_manuscripts(): array
{
    return db()->query(
        "SELECT m.id, m.title, m.submitted_at, c.name AS category, u.display_name AS author
         FROM manuscripts m
         JOIN categories c ON c.id = m.category_id
         JOIN users u ON u.id = m.author_id
         WHERE m.status = 'reviewing'
         ORDER BY m.submitted_at ASC, m.id ASC"
    )->fetchAll();
}

function find_manuscript(int $id): ?array
{
    $stmt = db()->prepare(
        'SELECT m.*, c.name AS category, u.display_name AS author
         FROM manuscripts m
         JOIN categories c ON c.id = m.category_id
         JOIN users u ON u.id = m.author_id
         WHERE m.id = ?'
    );
    $stmt->execute([$id]);
    return $stmt->fetch() ?: null;
}

/** 取得作者自己的稿件；不是自己的就當作不存在 */
function find_own_manuscript(int $id, int $authorId): ?array
{
    $m = find_manuscript($id);
    return ($m && (int) $m['author_id'] === $authorId) ? $m : null;
}

function review_logs(int $manuscriptId): array
{
    $stmt = db()->prepare(
        'SELECT l.action, l.comment, l.created_at, u.display_name
         FROM review_logs l JOIN users u ON u.id = l.user_id
         WHERE l.manuscript_id = ?
         ORDER BY l.created_at DESC, l.id DESC'
    );
    $stmt->execute([$manuscriptId]);
    return $stmt->fetchAll();
}

/** 最近一次退回意見（若稿件目前是編輯中且曾被退回） */
function latest_reject_comment(int $manuscriptId): ?array
{
    $stmt = db()->prepare(
        "SELECT action, comment, created_at FROM review_logs
         WHERE manuscript_id = ? ORDER BY created_at DESC, id DESC LIMIT 1"
    );
    $stmt->execute([$manuscriptId]);
    $log = $stmt->fetch();
    return ($log && $log['action'] === 'reject') ? $log : null;
}

/** 驗證表單，回傳錯誤訊息陣列 */
function validate_manuscript(array $input): array
{
    $errors = [];
    $title = trim($input['title'] ?? '');
    $content = trim($input['content'] ?? '');
    $categoryIds = array_column(categories(), 'id');

    if (!in_array((int) ($input['category_id'] ?? 0), $categoryIds, true)) {
        $errors[] = '請選擇類別。';
    }
    if ($title === '') {
        $errors[] = '標題不可空白。';
    } elseif (mb_strlen($title) > 200) {
        $errors[] = '標題不可超過 200 字。';
    }
    if ($content === '') {
        $errors[] = '文章內容不可空白。';
    }
    return $errors;
}

function create_manuscript(int $authorId, array $input): int
{
    $stmt = db()->prepare(
        "INSERT INTO manuscripts (author_id, category_id, title, content, status)
         VALUES (?, ?, ?, ?, 'editing')"
    );
    $stmt->execute([$authorId, (int) $input['category_id'], trim($input['title']), trim($input['content'])]);
    return (int) db()->lastInsertId();
}

/** 編輯稿件：僅限本人且狀態為編輯中 */
function update_manuscript(int $id, int $authorId, array $input): bool
{
    $stmt = db()->prepare(
        "UPDATE manuscripts SET category_id = ?, title = ?, content = ?
         WHERE id = ? AND author_id = ? AND status = 'editing'"
    );
    $stmt->execute([(int) $input['category_id'], trim($input['title']), trim($input['content']), $id, $authorId]);
    // 內容沒變時 rowCount 為 0，所以再確認一次稿件是否仍可編輯
    return $stmt->rowCount() > 0 || (bool) find_editable($id, $authorId);
}

function find_editable(int $id, int $authorId): ?array
{
    $m = find_own_manuscript($id, $authorId);
    return ($m && $m['status'] === 'editing') ? $m : null;
}

/** 刪除稿件：僅限本人且狀態為編輯中 */
function delete_manuscript(int $id, int $authorId): bool
{
    $stmt = db()->prepare("DELETE FROM manuscripts WHERE id = ? AND author_id = ? AND status = 'editing'");
    $stmt->execute([$id, $authorId]);
    return $stmt->rowCount() > 0;
}

/** 執行狀態轉換並寫入審查歷程（同一個交易） */
function transition(int $id, string $from, string $to, string $action, int $userId, ?int $authorId = null, ?string $comment = null): bool
{
    $pdo = db();
    $pdo->beginTransaction();
    try {
        $sql = 'UPDATE manuscripts SET status = ?'
            . ($action === 'submit' ? ', submitted_at = NOW()' : '')
            . ' WHERE id = ? AND status = ?';
        $params = [$to, $id, $from];
        if ($authorId !== null) {
            $sql .= ' AND author_id = ?';
            $params[] = $authorId;
        }
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        if ($stmt->rowCount() === 0) {
            $pdo->rollBack();
            return false;
        }
        $pdo->prepare('INSERT INTO review_logs (manuscript_id, user_id, action, comment) VALUES (?, ?, ?, ?)')
            ->execute([$id, $userId, $action, $comment !== '' ? $comment : null]);
        $pdo->commit();
        return true;
    } catch (Throwable $e) {
        $pdo->rollBack();
        throw $e;
    }
}

function submit_manuscript(int $id, int $authorId): bool
{
    return transition($id, 'editing', 'reviewing', 'submit', $authorId, $authorId);
}

function approve_manuscript(int $id, int $editorId, ?string $comment): bool
{
    return transition($id, 'reviewing', 'approved', 'approve', $editorId, null, $comment);
}

function reject_manuscript(int $id, int $editorId, ?string $comment): bool
{
    return transition($id, 'reviewing', 'editing', 'reject', $editorId, null, $comment);
}
