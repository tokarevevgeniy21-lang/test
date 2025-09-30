<?php
require_once __DIR__ . '/db.php';

function fetchAvailableTables(PDO $pdo): array
{
    $stmt = $pdo->query('SHOW TABLES');
    $tables = [];
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $tables[] = $row[0];
    }
    sort($tables);
    return $tables;
}

function fetchCalculatedMetrics(PDO $pdo): array
{
    try {
        $stmt = $pdo->query('SELECT `id`, `label`, `source_table`, `source_column`, `aggregation`, `multiplier`, `filter_column`, `filter_operator`, `filter_value`, `result_value`, `created_at` FROM `calculated_metrics` ORDER BY `created_at` DESC LIMIT 50');
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        // Таблица ещё не создана
        return [];
    }
}

$tables = fetchAvailableTables($pdo);
$hasTables = !empty($tables);
$metrics = fetchCalculatedMetrics($pdo);
?>
<!DOCTYPE html>
<html lang="ru">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Конструктор формул — <?= htmlspecialchars(APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css">
    <style>
        body {
            background-color: #f6f7fb;
        }

        .formula-card {
            box-shadow: 0 1rem 3rem rgba(0, 0, 0, 0.08);
        }

        .preview-output {
            min-height: 64px;
        }

        .badge-operator {
            font-size: 0.9rem;
        }
    </style>
</head>

<body>
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container-fluid">
            <a class="navbar-brand" href="index.php"><?= htmlspecialchars(APP_NAME, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></a>
            <div class="d-flex">
                <a class="btn btn-outline-light" href="formula_builder.php">Конструктор формул</a>
            </div>
        </div>
    </nav>

    <main class="container mb-5">
        <div class="row g-4">
            <div class="col-lg-6">
                <div class="card formula-card">
                    <div class="card-header bg-primary text-white">
                        <h5 class="mb-0">Конструктор расчётов</h5>
                    </div>
                    <div class="card-body">
                        <?php if (!$hasTables): ?>
                            <div class="alert alert-warning" role="alert">
                                В базе данных пока нет таблиц. Добавьте хотя бы одну таблицу в MySQL, чтобы использовать конструктор формул.
                            </div>
                        <?php endif; ?>
                        <form id="formula-form" class="needs-validation" novalidate>
                            <div class="mb-3">
                                <label for="formulaLabel" class="form-label">Название расчёта</label>
                                <input type="text" class="form-control" id="formulaLabel" name="label" placeholder="Например, Доход за месяц" required>
                                <div class="invalid-feedback">Введите понятное название формулы.</div>
                            </div>

                            <div class="row g-3">
                                <div class="col-md-6">
                                    <label for="sourceTable" class="form-label">Исходная таблица</label>
                                    <select class="form-select" id="sourceTable" name="source_table" required <?= $hasTables ? '' : 'disabled' ?>>
                                        <option value="" disabled selected><?= $hasTables ? 'Выберите таблицу' : 'Нет доступных таблиц' ?></option>
                                        <?php foreach ($tables as $table): ?>
                                            <option value="<?= htmlspecialchars($table, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>">
                                                <?= htmlspecialchars($table, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="invalid-feedback">Выберите таблицу-источник.</div>
                                </div>
                                <div class="col-md-6">
                                    <label for="sourceColumn" class="form-label">Поле</label>
                                    <select class="form-select" id="sourceColumn" name="source_column" required disabled>
                                        <option value="" disabled selected>Сначала выберите таблицу</option>
                                    </select>
                                    <div class="invalid-feedback">Выберите поле для расчёта.</div>
                                </div>
                            </div>

                            <div class="row g-3 mt-0 mt-md-3">
                                <div class="col-md-6">
                                    <label for="aggregation" class="form-label">Агрегация</label>
                                    <select class="form-select" id="aggregation" name="aggregation" required>
                                        <option value="SUM" selected>Сумма</option>
                                        <option value="AVG">Среднее</option>
                                        <option value="MIN">Минимум</option>
                                        <option value="MAX">Максимум</option>
                                        <option value="COUNT">Количество</option>
                                    </select>
                                </div>
                                <div class="col-md-6">
                                    <label for="multiplier" class="form-label">Множитель</label>
                                    <input type="number" step="0.01" class="form-control" id="multiplier" name="multiplier" value="1" required>
                                    <div class="form-text">Полученный результат будет умножен на это значение.</div>
                                </div>
                            </div>

                            <hr class="my-4">

                            <h6>Условия отбора (необязательно)</h6>
                            <div class="row g-3">
                                <div class="col-md-5">
                                    <label for="filterColumn" class="form-label">Поле для фильтра</label>
                                    <select class="form-select" id="filterColumn" name="filter_column" disabled>
                                        <option value="" selected>Без фильтра</option>
                                    </select>
                                </div>
                                <div class="col-md-3">
                                    <label for="filterOperator" class="form-label">Оператор</label>
                                    <select class="form-select" id="filterOperator" name="filter_operator" disabled>
                                        <option value="=">=</option>
                                        <option value=">">&gt;</option>
                                        <option value=">=">&gt;=</option>
                                        <option value="<">&lt;</option>
                                        <option value="<=">&lt;=</option>
                                        <option value="!=">!=</option>
                                        <option value="LIKE">LIKE</option>
                                    </select>
                                </div>
                                <div class="col-md-4">
                                    <label for="filterValue" class="form-label">Значение</label>
                                    <input type="text" class="form-control" id="filterValue" name="filter_value" placeholder="Например, завершён">
                                </div>
                            </div>

                            <div class="d-flex gap-2 mt-4">
                                <button type="button" class="btn btn-outline-primary" id="previewButton" <?= $hasTables ? '' : 'disabled' ?>>Предпросмотр</button>
                                <button type="submit" class="btn btn-primary" <?= $hasTables ? '' : 'disabled' ?>>Рассчитать и сохранить</button>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="card mt-4">
                    <div class="card-header">
                        <h6 class="mb-0">Предпросмотр результата</h6>
                    </div>
                    <div class="card-body">
                        <div id="previewOutput" class="preview-output d-flex align-items-center justify-content-center text-muted">
                            Данные предварительного расчёта появятся здесь.
                        </div>
                    </div>
                </div>
            </div>

            <div class="col-lg-6">
                <div class="card h-100">
                    <div class="card-header bg-light">
                        <div class="d-flex justify-content-between align-items-center">
                            <h5 class="mb-0">История сохранённых расчётов</h5>
                            <span class="badge bg-secondary">Последние 50 записей</span>
                        </div>
                    </div>
                    <div class="card-body p-0">
                        <?php if (empty($metrics)): ?>
                            <div class="p-4 text-center text-muted">
                                Пока нет сохранённых расчётов. Создайте первый с помощью формы слева.
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table class="table table-hover mb-0 align-middle">
                                    <thead class="table-light">
                                        <tr>
                                            <th>#</th>
                                            <th>Название</th>
                                            <th>Источник</th>
                                            <th>Агрегация</th>
                                            <th class="text-end">Результат</th>
                                            <th>Дата</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($metrics as $metric): ?>
                                            <tr>
                                                <td class="text-muted">#<?= (int) $metric['id'] ?></td>
                                                <td><?= htmlspecialchars($metric['label'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                                <td>
                                                    <div><?= htmlspecialchars($metric['source_table'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                                                    <small class="text-muted">поле: <?= htmlspecialchars($metric['source_column'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                                                    <?php if (!empty($metric['filter_column'])): ?>
                                                        <div>
                                                            <span class="badge bg-light text-dark border badge-operator">
                                                                <?= htmlspecialchars($metric['filter_column'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                                                <?= htmlspecialchars($metric['filter_operator'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                                                <?= htmlspecialchars($metric['filter_value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?>
                                                            </span>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <div><?= htmlspecialchars($metric['aggregation'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></div>
                                                    <small class="text-muted">× <?= htmlspecialchars($metric['multiplier'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></small>
                                                </td>
                                                <td class="text-end fw-semibold"><?= htmlspecialchars($metric['result_value'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                                <td class="text-muted"><?= htmlspecialchars($metric['created_at'], ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8') ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script>
        const sourceTableSelect = document.getElementById('sourceTable');
        const sourceColumnSelect = document.getElementById('sourceColumn');
        const filterColumnSelect = document.getElementById('filterColumn');
        const filterOperatorSelect = document.getElementById('filterOperator');
        const previewButton = document.getElementById('previewButton');
        const previewOutput = document.getElementById('previewOutput');
        const formulaForm = document.getElementById('formula-form');

        const resetColumnSelect = () => {
            sourceColumnSelect.innerHTML = '<option value="" disabled selected>Сначала выберите таблицу</option>';
            sourceColumnSelect.disabled = true;

            filterColumnSelect.innerHTML = '<option value="" selected>Без фильтра</option>';
            filterColumnSelect.disabled = true;
            filterOperatorSelect.disabled = true;
        };

        const populateColumns = async (tableName) => {
            resetColumnSelect();
            if (!tableName) {
                return;
            }

            try {
                const response = await fetch(`formula_builder_columns.php?table=${encodeURIComponent(tableName)}`);
                if (!response.ok) {
                    throw new Error('Не удалось получить список полей.');
                }
                const data = await response.json();
                if (!Array.isArray(data.columns) || data.columns.length === 0) {
                    throw new Error('В выбранной таблице нет доступных полей.');
                }

                sourceColumnSelect.disabled = false;
                filterColumnSelect.disabled = false;
                filterOperatorSelect.disabled = false;

                sourceColumnSelect.innerHTML = '<option value="" disabled selected>Выберите поле</option>';
                filterColumnSelect.innerHTML = '<option value="" selected>Без фильтра</option>';

                data.columns.forEach(column => {
                    const option = document.createElement('option');
                    option.value = column;
                    option.textContent = column;
                    sourceColumnSelect.appendChild(option.cloneNode(true));
                    filterColumnSelect.appendChild(option);
                });
            } catch (error) {
                console.error(error);
                previewOutput.classList.remove('text-muted');
                previewOutput.textContent = error.message;
            }
        };

        sourceTableSelect.addEventListener('change', (event) => {
            populateColumns(event.target.value);
        });

        const formToJSON = (form) => {
            const formData = new FormData(form);
            return Object.fromEntries(formData.entries());
        };

        const sendFormulaRequest = async (payload) => {
            const response = await fetch('formula_builder_process.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify(payload)
            });

            const data = await response.json();
            if (!response.ok || !data.success) {
                throw new Error(data.message || 'Произошла ошибка при выполнении запроса.');
            }
            return data;
        };

        previewButton.addEventListener('click', async () => {
            if (!formulaForm.checkValidity()) {
                formulaForm.classList.add('was-validated');
                return;
            }

            previewOutput.classList.remove('text-muted');
            previewOutput.textContent = 'Выполняем расчёт...';

            try {
                const payload = formToJSON(formulaForm);
                payload.action = 'preview';
                const data = await sendFormulaRequest(payload);
                previewOutput.textContent = `Результат: ${data.result}`;
            } catch (error) {
                previewOutput.textContent = error.message;
            }
        });

        formulaForm.addEventListener('submit', async (event) => {
            event.preventDefault();
            event.stopPropagation();

            if (!formulaForm.checkValidity()) {
                formulaForm.classList.add('was-validated');
                return;
            }

            const originalButtonText = event.submitter.textContent;
            event.submitter.disabled = true;
            event.submitter.textContent = 'Сохраняем...';

            try {
                const payload = formToJSON(formulaForm);
                payload.action = 'save';
                const data = await sendFormulaRequest(payload);
                previewOutput.classList.remove('text-muted');
                previewOutput.textContent = `Результат сохранён: ${data.result}`;
                window.location.reload();
            } catch (error) {
                previewOutput.textContent = error.message;
            } finally {
                event.submitter.disabled = false;
                event.submitter.textContent = originalButtonText;
            }
        });

        // Включаем Bootstrap валидацию
        (() => {
            const forms = document.querySelectorAll('.needs-validation');
            Array.from(forms).forEach(form => {
                form.addEventListener('submit', event => {
                    if (!form.checkValidity()) {
                        event.preventDefault();
                        event.stopPropagation();
                    }
                    form.classList.add('was-validated');
                }, false);
            });
        })();
    </script>
</body>

</html>
