<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

try {
    if (!isset($_GET['table']) || $_GET['table'] === '') {
        throw new InvalidArgumentException('Не указано название таблицы.');
    }

    $table = $_GET['table'];
    if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
        throw new InvalidArgumentException('Указано недопустимое название таблицы.');
    }

    $quotedTable = str_replace('`', '``', $table);
    $stmt = $pdo->query("SHOW COLUMNS FROM `{$quotedTable}`");
    $columns = [];
    while ($column = $stmt->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $column['Field'];
    }

    echo json_encode([
        'success' => true,
        'columns' => $columns,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
