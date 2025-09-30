<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:view');

global $pdo;
$orderId = (int)$_GET['id'];

// Получаем данные заказа
$order = getOrderById($orderId);
if (!$order) {
    $_SESSION['error'] = 'Заказ не найден';
    header('Location: orders.php');
    exit;
}

// Получаем дополнительные данные
$orderServices = getOrderServices($orderId);
$orderParts = getOrderParts($orderId);
$comments = getOrderComments($orderId);
$user = getUserById($_SESSION['user_id']);

// Получаем статусы
$statuses = getFromTable('statuses', '*', 'is_active = 1', [], 'ORDER BY name');
if (empty($statuses)) {
    $statuses = getFromTable('statuses', '*', '', [], 'ORDER BY name');
}

// Проверяем права для изменения статуса на "выдан"
$canChangeToIssued = in_array($user['role_id'], [1, 5]);
$issuedStatusId = 4;

// Проверяем просрочку
$isOverdue = false;
$daysDiff = 0;
$deadlineDate = null;

try {
    $today = new DateTime();
    
    // Если есть установленный дедлайн - используем его
    if (!empty($order['deadline'])) {
        $deadlineDate = new DateTime($order['deadline']);
    } else {
        // Если нет дедлайна - рассчитываем из даты создания + 14 дней
        $createdDate = new DateTime($order['created_at']);
        $deadlineDate = clone $createdDate;
        $deadlineDate->modify('+14 days');
    }
    
    $isOverdue = $deadlineDate < $today;
    $daysDiff = $today->diff($deadlineDate)->days;
    if ($isOverdue) $daysDiff = -$daysDiff;
    
} catch (Exception $e) {
    error_log("Ошибка обработки даты: " . $e->getMessage());
}

// Обработка добавления комментария
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_comment'])) {
    $commentText = trim($_POST['comment'] ?? '');
    
    if (!empty($commentText)) {
        if (addOrderComment($orderId, $_SESSION['user_id'], $commentText)) {
            $_SESSION['success'] = 'Комментарий добавлен';
            header("Location: order_view.php?id=$orderId");
            exit;
        } else {
            $_SESSION['error'] = 'Ошибка при добавлении комментария';
        }
    } else {
        $_SESSION['error'] = 'Комментарий не может быть пустым';
    }
}

// Обработка изменения статуса
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $newStatusId = (int)$_POST['status_id'];
    
    // Проверка прав для статуса "выдан"
    if ($newStatusId == $issuedStatusId && !$canChangeToIssued) {
        $_SESSION['error'] = 'Недостаточно прав для установки статуса "выдан"';
        header("Location: order_view.php?id=$orderId");
        exit;
    }
    
    // Обновляем статус
    if (updateOrderStatus($orderId, $newStatusId, $_SESSION['user_id'])) {
        // Добавляем комментарий об изменении статуса
        $oldStatus = $order['status_name'];
        $newStatus = getFromTable('statuses', 'name', 'id = ?', [$newStatusId]);
        $newStatus = $newStatus[0]['name'] ?? 'Неизвестно';
        
        addOrderComment($orderId, $_SESSION['user_id'], "Изменен статус: $oldStatus → $newStatus");
        
        $_SESSION['success'] = 'Статус заказа обновлен';
        header("Location: order_view.php?id=$orderId");
        exit;
    } else {
        $_SESSION['error'] = 'Ошибка при изменении статуса';
    }
}

renderHeader('Заказ #' . $order['id']);
?>

<div class="row">
    <div class="col-md-8">
        <!-- Основная информация -->
        <div class="card <?= $isOverdue ? 'border-danger' : '' ?>">
            <div class="card-header d-flex justify-content-between align-items-center <?= $isOverdue ? 'bg-light-danger' : '' ?>">
    <div>
        <h5 class="card-title mb-0">Информация о заказе #<?= $order['id'] ?></h5>
        <div class="mt-1">
            <span class="badge bg-<?= $isOverdue ? 'danger' : 'success' ?>">
                <i class="bi bi-calendar-event"></i>
                <?php if (!empty($order['deadline'])): ?>
                    Дедлайн: <?= date('d.m.Y', strtotime($order['deadline'])) ?>
                <?php else: ?>
                    Расчетный срок: <?= $deadlineDate ? $deadlineDate->format('d.m.Y') : '' ?>
                <?php endif; ?>
                <?= $isOverdue ? ' (ПРОСРОЧЕНО)' : '' ?>
            </span>
            <small class="text-muted ms-2">
                <?= $isOverdue ? "Просрочка: " . abs($daysDiff) . " дн." : "Осталось: $daysDiff дн." ?>
            </small>
        </div>
    </div>
    
    <?php if (hasPermission('orders:edit-deadline')): ?>
    <button type="button" class="btn btn-sm btn-<?= $isOverdue ? 'danger' : 'outline-primary' ?>" 
            data-bs-toggle="modal" data-bs-target="#deadlineModal">
        <i class="bi bi-calendar-plus"></i>
        <?= empty($order['deadline']) ? 'Установить дедлайн' : 'Изменить' ?>
    </button>
    <?php endif; ?>
</div>
            
            <div class="card-body">
                <div class="row">
                    <div class="col-md-6">
    <h6>Клиент</h6>
    <p>
        <strong><?= htmlspecialchars($order['client_full_name'] ?? $order['client_company_name'] ?? 'Без имени') ?></strong><br>
        <?php if (!empty($order['client_phone'])): ?>
            📞 <?= htmlspecialchars($order['client_phone']) ?><br>
        <?php endif; ?>
        <?php if (!empty($order['client_email'])): ?>
            📧 <?= htmlspecialchars($order['client_email']) ?>
        <?php endif; ?>
    </p>
    
    <!-- ИСТОРИЯ КЛИЕНТА -->
    <?php if (!empty($order['client_phone'])): ?>
        <?php
        // Получаем историю заказов клиента по телефону
        $clientHistory = getFromTable('orders o', 
            'COUNT(*) as total_orders, 
             SUM(o.total_amount) as total_spent,
             AVG(o.total_amount) as avg_order',
            'EXISTS (SELECT 1 FROM clients c WHERE c.id = o.client_id AND c.phone = ?)',
            [$order['client_phone']]);
        
        $history = $clientHistory[0] ?? [];
        $totalOrders = $history['total_orders'] ?? 0;
        $totalSpent = $history['total_spent'] ?? 0;
        $avgOrder = $history['avg_order'] ?? 0;
        ?>
        
        <?php if ($totalOrders > 1): ?>
            <div class="mt-3 p-2 border rounded bg-light">
                <h6>📊 История клиента</h6>
                <div class="small">
                    <div class="d-flex justify-content-between">
                        <span>Всего заказов:</span>
                        <strong><?= $totalOrders ?></strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Общая сумма:</span>
                        <strong class="text-success"><?= number_format($totalSpent, 2) ?> ₽</strong>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Средний чек:</span>
                        <strong><?= number_format($avgOrder, 2) ?> ₽</strong>
                    </div>
                    <?php if ($totalOrders > 5): ?>
                        <span class="badge bg-success mt-1">Постоянный клиент</span>
                    <?php elseif ($totalOrders > 2): ?>
                        <span class="badge bg-info mt-1">Лояльный клиент</span>
                    <?php endif; ?>
                </div>
            </div>
        <?php elseif ($totalOrders == 1): ?>
            <div class="mt-2">
                <small class="text-muted">🎯 Первое обращение</small>
            </div>
        <?php endif; ?>
    <?php endif; ?>
    <!-- КОНЕЦ ИСТОРИИ КЛИЕНТА -->
    
    <h6 class="mt-3">Устройство</h6>
    <p>
        <?= htmlspecialchars($order['device_category'] ?? '') ?><br>
        <?= htmlspecialchars($order['brand_name'] ?? '') ?> <?= htmlspecialchars($order['device_model'] ?? '') ?><br>
        <?php if (!empty($order['serial_number'])): ?>
            SN: <?= htmlspecialchars($order['serial_number']) ?>
        <?php endif; ?>
    </p>
</div>
                    
                    <div class="col-md-6">
                        <h6>Персонал</h6>
                        <p>
                            Менеджер: <strong><?= htmlspecialchars($order['manager_name'] ?? 'Не назначен') ?></strong><br>
                            Мастер: <strong><?= htmlspecialchars($order['master_name'] ?? 'Не назначен') ?></strong>
                        </p>
                        
                        <h6>Статус</h6>
                        <form method="POST">
                            <input type="hidden" name="change_status" value="1">
                            <select name="status_id" class="form-select" onchange="this.form.submit()">
                                <option value="">-- Выберите статус --</option>
                                <?php foreach ($statuses as $status): ?>
                                <option value="<?= $status['id'] ?>" 
                                    <?= $order['status_id'] == $status['id'] ? 'selected' : '' ?>
                                    <?= ($status['id'] == $issuedStatusId && !$canChangeToIssued) ? 'disabled' : '' ?>>
                                    <?= htmlspecialchars($status['name']) ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </form>
                    </div>
                </div>
                
                <?php if (!empty($order['problem_description'])): ?>
                <div class="mt-3">
                    <h6>Описание проблемы</h6>
                    <p class="text-muted"><?= nl2br(htmlspecialchars($order['problem_description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Секция услуг -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Услуги</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="bi bi-plus"></i> Добавить услугу
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($orderServices)): ?>
                    <p class="text-muted text-center py-3">Услуги не добавлены</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Услуга</th>
                                    <th>Кол-во</th>
                                    <th>Цена</th>
                                    <th>Сумма</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderServices as $service): ?>
                                <tr>
                                    <td><?= htmlspecialchars($service['service_name']) ?></td>
                                    <td><?= $service['quantity'] ?></td>
                                    <td><?= number_format($service['price'], 2) ?> ₽</td>
                                    <td><?= number_format($service['quantity'] * $service['price'], 2) ?> ₽</td>
                                    <td>
                                        <div class="btn-group btn-group-sm">
                                            <a href="service_edit.php?order_id=<?= $order['id'] ?>&service_id=<?= $service['id'] ?>" 
                                               class="btn btn-outline-secondary" title="Редактировать">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (in_array($user['role_id'], [1, 2, 3])): ?>
                                            <form action="order_remove_service.php" method="POST" class="d-inline">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" 
                                                        onclick="return confirm('Удалить услугу?')" title="Удалить">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Секция запчастей -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Запчасти</h5>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addPartModal">
                    <i class="bi bi-plus"></i> Добавить запчасть
                </button>
            </div>
            <div class="card-body">
                <?php if (empty($orderParts)): ?>
                    <p class="text-muted text-center py-3">Запчасти не добавлены</p>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-striped">
                            <thead>
                                <tr>
                                    <th>Запчасть</th>
                                    <th>Кол-во</th>
                                    <th>Цена</th>
                                    <th>Сумма</th>
                                    <th>Выдано мастеру</th>
                                    <th>Действия</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orderParts as $part): ?>
                                <tr>
                                    <td><?= htmlspecialchars($part['part_name']) ?></td>
                                    <td><?= $part['quantity'] ?></td>
                                    <td><?= number_format($part['price'], 2) ?> ₽</td>
                                    <td><?= number_format($part['quantity'] * $part['price'], 2) ?> ₽</td>
                                    <td><?= $part['issued_to_master'] ? '<span class="badge bg-success">Да</span>' : '<span class="badge bg-secondary">Нет</span>' ?></td>
                                    <td>
                                        <?php if (in_array($user['role_id'], [1, 2, 3])): ?>
                                        <form action="order_remove_part.php" method="POST" class="d-inline">
                                            <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                            <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" 
                                                    onclick="return confirm('Удалить запчасть?')" title="Удалить">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-md-4">
        <!-- Финансы -->
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Финансовая информация</h5>
            </div>
            <div class="card-body">
                <div class="d-flex justify-content-between mb-2">
                    <span>Сумма заказа:</span>
                    <strong><?= number_format($order['total_amount'] ?? 0, 2) ?> ₽</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Скидка:</span>
                    <strong class="text-danger">-<?= number_format($order['discount'] ?? 0, 2) ?> ₽</strong>
                </div>
                <div class="d-flex justify-content-between mb-2">
                    <span>Оплачено:</span>
                    <strong class="text-success"><?= number_format($order['payments_total'] ?? 0, 2) ?> ₽</strong>
                </div>
                <hr>
                <div class="d-flex justify-content-between mb-2">
                    <span>К оплате:</span>
                    <strong class="text-primary"><?= number_format(($order['total_amount'] ?? 0) - ($order['payments_total'] ?? 0), 2) ?> ₽</strong>
                </div>
                <?php if (!empty($order['profit'])): ?>
                <div class="d-flex justify-content-between">
                    <span>Прибыль:</span>
                    <strong class="text-success"><?= number_format($order['profit'], 2) ?> ₽</strong>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Комментарии -->
        <div class="card mt-4">
            <div class="card-header">
                <h5 class="card-title mb-0">Комментарии</h5>
            </div>
            <div class="card-body">
                <form method="POST">
                    <input type="hidden" name="add_comment" value="1">
                    <div class="mb-3">
                        <textarea class="form-control" name="comment" rows="3" placeholder="Введите ваш комментарий..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Добавить комментарий</button>
                </form>

                <div class="mt-3" style="max-height: 300px; overflow-y: auto;">
                    <?php if (empty($comments)): ?>
                        <p class="text-muted text-center">Комментариев пока нет</p>
                    <?php else: ?>
                        <?php foreach ($comments as $comment): ?>
                        <div class="border-bottom pb-2 mb-2">
                            <div class="d-flex justify-content-between align-items-start">
                                <strong><?= htmlspecialchars($comment['user_name'] ?? 'Пользователь') ?></strong>
                                <small class="text-muted"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></small>
                            </div>
                            <p class="mb-0 small"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                        </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальные окна -->
<?php include 'modals/order_modals.php'; ?>

<style>
.bg-light-danger {
    background-color: #fff5f5 !important;
    border-bottom: 2px solid #dc3545 !important;
}
.border-danger {
    border: 2px solid #dc3545 !important;
}
</style>

<script>
// Скрипты для модальных окон
function updatePartInfo() {
    const select = document.getElementById('partSelect');
    const selectedOption = select.options[select.selectedIndex];
    const priceInput = document.getElementById('priceInput');
    const quantityInput = document.getElementById('quantityInput');
    const maxQuantitySpan = document.getElementById('maxQuantity');
    const totalPrice = document.getElementById('totalPrice');
    
    if (selectedOption && selectedOption.value) {
        const price = parseFloat(selectedOption.dataset.price);
        const stock = parseInt(selectedOption.dataset.stock);
        
        priceInput.value = price.toFixed(2);
        maxQuantitySpan.textContent = stock;
        quantityInput.max = stock;
        quantityInput.value = Math.min(1, stock);
        calculateTotal();
    }
}

function validateQuantity() {
    const quantityInput = document.getElementById('quantityInput');
    const maxQuantity = parseInt(document.getElementById('maxQuantity').textContent);
    if (quantityInput.value > maxQuantity) {
        quantityInput.value = maxQuantity;
    }
    calculateTotal();
}

function calculateTotal() {
    const quantity = parseInt(document.getElementById('quantityInput').value);
    const price = parseFloat(document.getElementById('priceInput').value);
    const totalPrice = document.getElementById('totalPrice');
    if (!isNaN(quantity) && !isNaN(price)) {
        totalPrice.value = (quantity * price).toFixed(2);
    }
}

document.getElementById('addPartModal')?.addEventListener('show.bs.modal', function() {
    setTimeout(updatePartInfo, 100);
});
</script>

<?php renderFooter(); ?>