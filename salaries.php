<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('salaries:view');


$employees = getEmployeesWithSalary();

// Обработка изменения правил расчета
if ($_SERVER['REQUEST_METHOD'] === 'POST' && hasPermission('salaries:edit')) {
    $userId = (int)$_POST['user_id'];
    $calculationType = $_POST['calculation_type'];
    $calculationValue = (float)$_POST['calculation_value'];
    $partsDeduction = (float)$_POST['parts_deduction_percent'];
    $user = getFromTable('users', 'role_id', 'id = ?', [$userId]);
    $roleId = $user[0]['role_id'] ?? 0;
    $isMaster = ($roleId == 4); // ID роли мастера
    
    $partsDeduction = $isMaster ? (float)$_POST['parts_deduction_percent'] : 0;
    try {
        // Проверяем существующее правило
        $stmt = $pdo->prepare("SELECT id FROM salary_rules WHERE user_id = ?");
        $stmt->execute([$userId]);
        $existingRule = $stmt->fetch();
        
        if ($existingRule) {
            $stmt = $pdo->prepare("
                UPDATE salary_rules 
                SET calculation_type = ?, calculation_value = ?, parts_deduction_percent = ?, updated_at = NOW() 
                WHERE user_id = ?
            ");
            $stmt->execute([$calculationType, $calculationValue, $partsDeduction, $userId]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO salary_rules (user_id, calculation_type, calculation_value, parts_deduction_percent, is_active) 
                VALUES (?, ?, ?, ?, 1)
            ");
            $stmt->execute([$userId, $calculationType, $calculationValue, $partsDeduction]);
        }
        
        $_SESSION['success'] = 'Правила расчета обновлены';
        header('Location: salaries.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка: ' . $e->getMessage();
    }
}

renderHeader('Управление зарплатами');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Зарплаты сотрудников</h5>
                <span class="badge bg-secondary">Всего: <?= count($employees) ?></span>
            </div>
            <div class="card-body">
                
                <!-- Мастера -->
                <h4 class="mt-4">Мастера</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Мастер</th>
                                <th>Заказов</th>
                                <th>Начисления</th>
                                <th>Вычеты</th>
                                <th>Баланс</th>
                                <th>Тип расчета</th>
                                <th>Ставка</th>
                                <th>Вычет %</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <?php if ($employee['role_name'] === 'master'): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                                        <?php if ($employee['phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($employee['phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $employee['completed_orders'] ?></td>
                                    <td class="text-success"><?= number_format($employee['total_earnings'], 2) ?> ₽</td>
                                    <td class="text-danger"><?= number_format($employee['total_deductions'], 2) ?> ₽</td>
                                    <td class="fw-bold <?= $employee['current_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($employee['current_balance'], 2) ?> ₽
                                    </td>
                                    <td>
                                        <?php 
                                        $calcTypes = [
                                            'percent' => 'Процент',
                                            'fixed' => 'Фиксированная'
                                        ];
                                        echo $calcTypes[$employee['calculation_type']] ?? 'По умолчанию';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($employee['calculation_type'] == 'percent'): ?>
                                            <?= $employee['calculation_value'] ?>%
                                        <?php elseif ($employee['calculation_type'] == 'fixed'): ?>
                                            <?= number_format($employee['calculation_value'], 2) ?> ₽
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td><?= $employee['parts_deduction_percent'] ?? 40 ?>%</td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-salary" 
        data-user-id="<?= $employee['id'] ?>"
        data-user-name="<?= htmlspecialchars($employee['full_name']) ?>"
        data-calculation-type="<?= $employee['calculation_type'] ?>"
        data-calculation-value="<?= $employee['calculation_value'] ?>"
        data-parts-deduction="<?= $employee['parts_deduction_percent'] ?? 40 ?>"
        data-is-master="1">
    <i class="bi bi-pencil"></i> Изменить
</button>
                                        
                                        <a href="salary_details.php?user_id=<?= $employee['id'] ?>" class="btn btn-sm btn-outline-info">
                                            <i class="bi bi-list"></i> Детали
                                        </a>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Остальные сотрудники -->
                <h4 class="mt-4">Административный персонал</h4>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Сотрудник</th>
                                <th>Должность</th>
                                <th>Начисления</th>
                                <th>Вычеты</th>
                                <th>Баланс</th>
                                <th>Тип расчета</th>
                                <th>Ставка</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($employees as $employee): ?>
                                <?php if ($employee['role_name'] !== 'master'): ?>
                                <tr>
                                    <td>
                                        <strong><?= htmlspecialchars($employee['full_name']) ?></strong>
                                        <?php if ($employee['phone']): ?>
                                            <br><small class="text-muted"><?= htmlspecialchars($employee['phone']) ?></small>
                                        <?php endif; ?>
                                    </td>
                                    <td><?= htmlspecialchars($employee['role_name']) ?></td>
                                    <td class="text-success"><?= number_format($employee['total_earnings'], 2) ?> ₽</td>
                                    <td class="text-danger"><?= number_format($employee['total_deductions'], 2) ?> ₽</td>
                                    <td class="fw-bold <?= $employee['current_balance'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                        <?= number_format($employee['current_balance'], 2) ?> ₽
                                    </td>
                                    <td>
                                        <?php 
                                        $calcTypes = [
                                            'percent' => 'Процент',
                                            'fixed' => 'Фиксированная'
                                        ];
                                        echo $calcTypes[$employee['calculation_type']] ?? 'По умолчанию';
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ($employee['calculation_type'] == 'percent'): ?>
                                            <?= $employee['calculation_value'] ?>%
                                        <?php elseif ($employee['calculation_type'] == 'fixed'): ?>
                                            <?= number_format($employee['calculation_value'], 2) ?> ₽
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <button class="btn btn-sm btn-outline-primary edit-salary" 
        data-user-id="<?= $employee['id'] ?>"
        data-user-name="<?= htmlspecialchars($employee['full_name']) ?>"
        data-calculation-type="<?= $employee['calculation_type'] ?>"
        data-calculation-value="<?= $employee['calculation_value'] ?>"
        data-parts-deduction="0"
        data-is-master="0">
    <i class="bi bi-pencil"></i> Изменить
</button>
                                    </td>
                                </tr>
                                <?php endif; ?>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>
</div>

<!-- Модальное окно редактирования -->
<div class="modal fade" id="editSalaryModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Настройка зарплаты</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="salaryForm">
                <input type="hidden" name="user_id" id="userId">
                <div class="modal-body">
                    <h6 id="userName"></h6>
                    
                    <div class="mb-3">
                        <label class="form-label">Тип расчета *</label>
                        <select class="form-select" name="calculation_type" id="calculationType" required>
                            <option value="percent">Процент от заказа</option>
                            <option value="fixed">Фиксированная сумма</option>
                        </select>
                    </div>
                    
                    <div class="mb-3" id="valueField">
                        <label class="form-label" id="valueLabel">Процент *</label>
                        <input type="number" class="form-control" name="calculation_value" step="0.01" min="0" required>
                    </div>
                    
                    <div class="mb-3" id="deductionField">
                        <label class="form-label">Вычет за запчасти (%) *</label>
                        <input type="number" class="form-control" name="parts_deduction_percent" value="40" min="0" max="100" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    console.log('DOM loaded'); // Проверим что DOM загружен
    
    const calculationType = document.getElementById('calculationType');
    const valueLabel = document.getElementById('valueLabel');
    const valueInput = document.querySelector('input[name="calculation_value"]');
    const deductionField = document.getElementById('deductionField');
    const deductionInput = document.querySelector('input[name="parts_deduction_percent"]');

    // Изменение типа расчета
    if (calculationType) {
        calculationType.addEventListener('change', function() {
            updateValueField();
        });
    }

    function updateValueField() {
        const type = calculationType.value;
        if (type === 'percent') {
            valueLabel.textContent = 'Процент *';
            valueInput.placeholder = 'Например: 40';
            valueInput.step = '1';
            if (deductionField) deductionField.style.display = 'block';
        } else if (type === 'fixed') {
            valueLabel.textContent = 'Фиксированная сумма *';
            valueInput.placeholder = 'Например: 1000';
            valueInput.step = '0.01';
            if (deductionField) deductionField.style.display = 'none';
        }
    }

    // Открытие модального окна
    document.querySelectorAll('.edit-salary').forEach(btn => {
        btn.addEventListener('click', function() {
            console.log('Edit button clicked'); // Проверим что клик работает
            
            const userId = this.dataset.userId;
            const userName = this.dataset.userName;
            let calculationTypeValue = this.dataset.calculationType;
            const calculationValue = this.dataset.calculationValue;
            const partsDeduction = this.dataset.partsDeduction;
            
            console.log('Data:', {userId, userName, calculationTypeValue, calculationValue, partsDeduction});
            
            // Исправляем возможные ошибки в данных
            if (calculationTypeValue === 'percentage') {
                calculationTypeValue = 'percent';
            }
            
            document.getElementById('userId').value = userId;
            document.getElementById('userName').textContent = userName;
            document.getElementById('calculationType').value = calculationTypeValue || 'percent';
            document.querySelector('input[name="calculation_value"]').value = calculationValue || '';
            
            if (deductionInput) {
                deductionInput.value = partsDeduction || '40';
            }
            
            // Определяем является ли сотрудник мастером
            const row = this.closest('tr');
            const isMaster = row.querySelector('td:nth-child(8)')?.textContent.includes('%');
            
            if (deductionField) {
                deductionField.style.display = isMaster ? 'block' : 'none';
            }
            
            updateValueField();
            
            // Показываем модальное окно
            const modal = new bootstrap.Modal(document.getElementById('editSalaryModal'));
            modal.show();

            // В конец модального окна добавьте обработчик формы
document.getElementById('salaryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('salaries.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Закрываем модальное окно
        const modal = bootstrap.Modal.getInstance(document.getElementById('editSalaryModal'));
        modal.hide();
        
        // Перезагружаем страницу через 500ms
        setTimeout(() => {
            location.reload();
        }, 500);
    })
    .catch(error => {
        console.error('Error:', error);
        alert('Ошибка при сохранении');
    });
});
        });
    });
    
    // Инициализация при загрузке
    updateValueField();
});
document.getElementById('salaryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const formData = new FormData(this);
    
    fetch('salaries.php', {
        method: 'POST',
        body: formData
    })
    .then(response => response.text())
    .then(data => {
        // Закрываем модальное окно
        const modal = bootstrap.Modal.getInstance(document.getElementById('editSalaryModal'));
        modal.hide();
        
        // Показываем уведомление об успехе
        showNotification('Настройки зарплаты успешно обновлены', 'success');
        
        // Обновляем данные в таблице без перезагрузки страницы
        updateEmployeeRow(formData.get('user_id'), formData);
    })
    .catch(error => {
        console.error('Error:', error);
        showNotification('Ошибка при сохранении', 'error');
    });
});

function updateEmployeeRow(userId, formData) {
    const calculationType = formData.get('calculation_type');
    const calculationValue = formData.get('calculation_value');
    const partsDeduction = formData.get('parts_deduction_percent');
    
    // Находим строку сотрудника
    const row = document.querySelector(`.edit-salary[data-user-id="${userId}"]`).closest('tr');
    
    if (row) {
        // Обновляем ячейки
        const calcTypes = {
            'percent': 'Процент',
            'fixed': 'Фиксированная'
        };
        
        // Тип расчета
        row.cells[5].textContent = calcTypes[calculationType] || 'По умолчанию';
        
        // Ставка
        if (calculationType === 'percent') {
            row.cells[6].textContent = calculationValue + '%';
        } else if (calculationType === 'fixed') {
            row.cells[6].textContent = parseFloat(calculationValue).toLocaleString('ru-RU', {
                minimumFractionDigits: 2,
                maximumFractionDigits: 2
            }) + ' ₽';
        }
        
        // Вычет % (только для мастеров)
        if (row.cells[7]) {
            row.cells[7].textContent = partsDeduction + '%';
        }
        
        // Обновляем data-атрибуты кнопки
        const button = row.querySelector('.edit-salary');
        button.dataset.calculationType = calculationType;
        button.dataset.calculationValue = calculationValue;
        button.dataset.partsDeduction = partsDeduction;
    }
}

function showNotification(message, type) {
    // Создаем уведомление
    const alert = document.createElement('div');
    alert.className = `alert alert-${type === 'success' ? 'success' : 'danger'} alert-dismissible fade show`;
    alert.style.position = 'fixed';
    alert.style.top = '20px';
    alert.style.right = '20px';
    alert.style.zIndex = '9999';
    alert.style.minWidth = '300px';
    alert.innerHTML = `
        ${message}
        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
    `;
    
    document.body.appendChild(alert);
    
    // Автоматически скрываем через 3 секунды
    setTimeout(() => {
        if (alert.parentNode) {
            alert.parentNode.removeChild(alert);
        }
    }, 3000);
}
</script>

<?php renderFooter(); ?>