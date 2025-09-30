<?php
require_once __DIR__ . '/db.php';

header('Content-Type: application/json; charset=utf-8');

function readJsonInput(): array
{
    $input = file_get_contents('php://input');
    if ($input === false || $input === '') {
        return $_POST;
    }

    $data = json_decode($input, true);
    if (json_last_error() !== JSON_ERROR_NONE || !is_array($data)) {
        throw new InvalidArgumentException('Переданы некорректные данные.');
    }

    return $data;
}

function validateIdentifier(string $value, string $type = 'поле'): string
{
    if ($value === '') {
        throw new InvalidArgumentException("Не заполнено обязательное значение: {$type}.");
    }

    if (!preg_match('/^[A-Za-z0-9_]+$/', $value)) {
        throw new InvalidArgumentException("Недопустимое имя для {$type}.");
    }

    return str_replace('`', '``', $value);
}

function ensureMetricsTable(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS `calculated_metrics` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `label` VARCHAR(255) NOT NULL,
            `source_table` VARCHAR(128) NOT NULL,
            `source_column` VARCHAR(128) NOT NULL,
            `aggregation` VARCHAR(16) NOT NULL,
            `multiplier` DECIMAL(20,6) NOT NULL,
            `filter_column` VARCHAR(128) NULL,
            `filter_operator` VARCHAR(8) NULL,
            `filter_value` TEXT NULL,
            `result_value` DECIMAL(20,6) NOT NULL,
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;'
    );
}

function buildWhereClause(array $payload, string $tableQuoted, array &$parameters, array $validColumns): string
{
    $filterColumn = (string)($payload['filter_column'] ?? '');
    $filterOperator = (string)($payload['filter_operator'] ?? '');
    $filterValue = trim((string)($payload['filter_value'] ?? ''));

    if ($filterColumn === '' || $filterValue === '') {
        return '';
    }

    if (!in_array($filterColumn, $validColumns, true)) {
        throw new InvalidArgumentException('Выбранное поле для фильтра не найдено в таблице.');
    }

    $filterColumnSanitized = validateIdentifier($filterColumn, 'поле фильтра');

    $filterOperator = strtoupper($filterOperator);
    $allowedOperators = ['=', '!=', '>', '>=', '<', '<=', 'LIKE'];
    if (!in_array($filterOperator, $allowedOperators, true)) {
        throw new InvalidArgumentException('Недопустимый оператор фильтра.');
    }

    if ($filterOperator === 'LIKE') {
        $likeValue = str_replace('*', '%', $filterValue);
        if (strpos($likeValue, '%') === false && strpos($likeValue, '_') === false) {
            $likeValue = '%' . $likeValue . '%';
        }
        $parameters[':filterValue'] = $likeValue;
    } else {
        $parameters[':filterValue'] = $filterValue;
    }

    return sprintf(' WHERE `%s`.`%s` %s :filterValue', $tableQuoted, $filterColumnSanitized, $filterOperator);
}

try {
    $payload = readJsonInput();

    $label = trim((string)($payload['label'] ?? ''));
    if ($label === '') {
        throw new InvalidArgumentException('Введите название расчёта.');
    }

    $sourceTable = validateIdentifier((string)($payload['source_table'] ?? ''), 'таблицу');
    $sourceColumn = validateIdentifier((string)($payload['source_column'] ?? ''), 'поле');

    $aggregation = strtoupper((string)($payload['aggregation'] ?? 'SUM'));
    $allowedAggregations = ['SUM', 'AVG', 'MIN', 'MAX', 'COUNT'];
    if (!in_array($aggregation, $allowedAggregations, true)) {
        throw new InvalidArgumentException('Выбрано недопустимое действие агрегации.');
    }

    $rawMultiplier = str_replace(',', '.', (string)($payload['multiplier'] ?? 1));
    if (!is_numeric($rawMultiplier)) {
        throw new InvalidArgumentException('Множитель должен быть числом.');
    }
    $multiplier = round((float)$rawMultiplier, 6);

    $stmtColumns = $pdo->query("SHOW COLUMNS FROM `{$sourceTable}`");
    $columns = [];
    while ($column = $stmtColumns->fetch(PDO::FETCH_ASSOC)) {
        $columns[] = $column['Field'];
    }
    if (!in_array($payload['source_column'], $columns, true)) {
        throw new InvalidArgumentException('Выбранное поле отсутствует в таблице.');
    }

    $parameters = [];
    $whereClause = buildWhereClause($payload, $sourceTable, $parameters, $columns);

    $sql = sprintf('SELECT %s(`%s`) AS result_value FROM `%s`%s', $aggregation, $sourceColumn, $sourceTable, $whereClause);
    $stmt = $pdo->prepare($sql);
    $stmt->execute($parameters);
    $result = $stmt->fetchColumn();

    if ($result === false) {
        throw new RuntimeException('Не удалось получить данные для расчёта.');
    }

    $result = round(((float)$result) * $multiplier, 6);

    if (($payload['action'] ?? 'preview') === 'save') {
        ensureMetricsTable($pdo);
        $insertStmt = $pdo->prepare(
            'INSERT INTO `calculated_metrics` (`label`, `source_table`, `source_column`, `aggregation`, `multiplier`, `filter_column`, `filter_operator`, `filter_value`, `result_value`) VALUES (:label, :source_table, :source_column, :aggregation, :multiplier, :filter_column, :filter_operator, :filter_value, :result_value)'
        );
        $insertStmt->execute([
            ':label' => $label,
            ':source_table' => $payload['source_table'],
            ':source_column' => $payload['source_column'],
            ':aggregation' => $aggregation,
            ':multiplier' => $multiplier,
            ':filter_column' => $payload['filter_column'] ?? null,
            ':filter_operator' => $payload['filter_operator'] ?? null,
            ':filter_value' => $payload['filter_value'] ?? null,
            ':result_value' => $result,
        ]);
    }

    echo json_encode([
        'success' => true,
        'result' => $result,
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
} catch (Throwable $exception) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'message' => $exception->getMessage(),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
}
