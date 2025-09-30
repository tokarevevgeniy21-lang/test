<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('parts:view');

$masterId = $_GET['master_id'] ?? null;
$status = $_GET['status'] ?? 'issued';

// Получаем выданные запчасти
$masterParts = getMasterParts($masterId, $status);
$masters = getActiveMasters();

renderHeader('Выданные запчасти мастерам');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Выданные запчасти</h5>
                <div>
                    <a href="part_issue.php" class="btn btn-success btn-sm">
                        <i class="bi bi-plus"></i> Новая выдача
                    </a>
                </div>
            </div>
            <div class="card-body">
                <!-- Фильтры -->
                <form method="GET" class="row g-3 mb-4">
                    <div class="col-md-3">
                        <select class="form-select" name="master_id">
                            <option value="">Все мастера</option>
                            <?php foreach ($masters as $master): ?>
                            <option value="<?= $master['id'] ?>" <?= $masterId == $master['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($master['full_name']) ?>
                            </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" name="status">
                            <option value="issued" <?= $status == 'issued' ? 'selected' : '' ?>>Выданные</option>
                            <option value="used" <?= $status == 'used' ? 'selected' : '' ?>>Использованные</option>
                        </select>
                    </div>
                    <div class="col-md-2">
                        <button type="submit" class="btn btn-primary w-100">Применить</button>
                    </div>
                    <div class="col-md-2">
                        <a href="parts_master_list.php" class="btn btn-secondary w-100">Сбросить</a>
                    </div>
                </form>

                <!-- Таблица выданных запчастей -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Дата выдачи</th>
                                <th>Мастер</th>
                                <th>Запчасть</th>
                                <th>Количество</th>
                                <th>Себестоимость</th>
                                <th>Цена продажи</th>
                                <th>Заказ</th>
                                <th>Статус</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($masterParts)): ?>
                            <tr>
                                <td colspan="9" class="text-center text-muted py-4">
                                    <i class="bi bi-box-seam" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Нет выданных запчастей</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($masterParts as $mp): ?>
                                <tr>
                                    <td><?= date('d.m.Y H:i', strtotime($mp['issue_date'])) ?></td>
                                    <td><?= htmlspecialchars($mp['master_name']) ?></td>
                                    <td><?= htmlspecialchars($mp['part_name']) ?></td>
                                    <td><?= $mp['quantity'] ?></td>
                                    <td><?= number_format($mp['cost_price'], 2) ?> ₽</td>
                                    <td><?= number_format($mp['sale_price'], 2) ?> ₽</td>
                                    <td>
                                        <?php if ($mp['order_id']): ?>
                                            <a href="order_view.php?id=<?= $mp['order_id'] ?>" class="btn btn-sm btn-outline-info">
                                                #<?= $mp['order_id'] ?>
                                            </a>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <span class="badge bg-<?= $mp['status'] == 'used' ? 'success' : 'warning' ?>">
                                            <?= $mp['status'] == 'used' ? 'Использована' : 'Выдана' ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($mp['status'] == 'issued' && hasPermission('parts:use')): ?>
                                        <button class="btn btn-sm btn-success use-part" data-id="<?= $mp['id'] ?>" title="Отметить использование">
                                            <i class="bi bi-check"></i> Использовать
                                        </button>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для использования запчасти -->
<div class="modal fade" id="usePartModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Использование запчасти</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                <form id="usePartForm">
                    <input type="hidden" name="master_part_id" id="masterPartId">
                    <div class="mb-3">
                        <label class="form-label">Номер заказа *</label>
                        <input type="number" class="form-control" name="order_id" required>
                    </div>
                    <div class="alert alert-info">
                        После подтверждения запчасть будет списана, а из зарплаты мастера будет удержано 40% от прибыли.
                    </div>
                </form>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                <button type="button" class="btn btn-primary" id="confirmUse">Подтвердить</button>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Использование запчасти
    document.querySelectorAll('.use-part').forEach(btn => {
        btn.addEventListener('click', function() {
            const masterPartId = this.dataset.id;
            document.getElementById('masterPartId').value = masterPartId;
            $('#usePartModal').modal('show');
        });
    });
    
    // Подтверждение использования
    document.getElementById('confirmUse').addEventListener('click', function() {
        const formData = new FormData(document.getElementById('usePartForm'));
        
        fetch('ajax/use_part.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Ошибка: ' + data.message);
            }
        })
        .catch(error => {
            alert('Ошибка сети: ' + error);
        });
    });
});
</script>

<?php renderFooter(); ?>