<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:view');
global $pdo;
$orderId = (int)$_GET['id'];

// Используем функцию getOrderById вместо getFromTable
$order = getOrderById($orderId);

if (!$order) {
    $_SESSION['error'] = 'Заказ не найден';
    header('Location: orders.php');
    exit;
}

// Получаем услуги
try {
    $stmt = $pdo->prepare("SELECT * FROM services WHERE is_active = 1 ORDER BY name");
    $stmt->execute();
    $servicesList = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting services: " . $e->getMessage());
    $servicesList = [];
}

// Получаем запчасти  
try {
    $stmt = $pdo->prepare("SELECT * FROM parts WHERE is_active = 1 AND stock_quantity > 0 ORDER BY name");
    $stmt->execute();
    $partsList = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting parts: " . $e->getMessage());
    $partsList = [];
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
$canChangeToIssued = in_array($user['role_id'], [1, 5]); // Владелец и приемщик
$issuedStatusId = 4; // ID статуса "выдан"
$penaltyDetails = getPenaltyDetails($order['id']);
// Обработка изменения статуса - ТЕСТОВАЯ ВЕРСИЯ
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_status'])) {
    $newStatusId = (int)$_POST['status_id'];
    
    error_log("=== НАЧАЛО ОБРАБОТКИ СТАТУСА В order_view.php ===");
    error_log("POST данные: " . print_r($_POST, true));
    error_log("Сессия: user_id = " . ($_SESSION['user_id'] ?? 'НЕТ'));
    error_log("Order ID: $orderId, New Status ID: $newStatusId");
    
    // Проверка прав для статуса "выдан"
    if ($newStatusId == $issuedStatusId && !$canChangeToIssued) {
        error_log("ОШИБКА ПРАВ: Недостаточно прав для статуса 'выдан'");
        $_SESSION['error'] = 'Недостаточно прав для установки статуса "выдан"';
        header("Location: order_view.php?id=$orderId");
        exit;
    }
    
    // ПРОСТОЙ ТЕСТ - обновим статус напрямую
    global $pdo;
    try {
        error_log("Пытаемся выполнить простой UPDATE...");
        
        $stmt = $pdo->prepare("UPDATE orders SET status_id = ?, updated_at = NOW() WHERE id = ?");
        $result = $stmt->execute([$newStatusId, $orderId]);
        
        if ($result) {
            $affectedRows = $stmt->rowCount();
            error_log("ПРОСТОЙ UPDATE УСПЕШЕН! Затронуто строк: $affectedRows");
            
            // Добавляем комментарий
            $oldStatus = $order['status_name'];
            $newStatus = getFromTable('statuses', 'name', 'id = ?', [$newStatusId]);
            $newStatus = $newStatus[0]['name'] ?? 'Неизвестно';
            
            addOrderComment($orderId, $_SESSION['user_id'], "Изменен статус: $oldStatus → $newStatus");
            
            $_SESSION['success'] = 'Статус заказа обновлен';
            header("Location: order_view.php?id=$orderId");
            exit;
        } else {
            error_log("ПРОСТОЙ UPDATE НЕ УДАЛСЯ");
            $_SESSION['error'] = 'Ошибка при изменении статуса (простой запрос)'; 
        }
    } catch (PDOException $e) {
        error_log("EXCEPTION в простом запросе: " . $e->getMessage());
        $_SESSION['error'] = 'Ошибка базы данных: ' . $e->getMessage();
    }
    
    // Если простой запрос не сработал, пробуем через функцию
    error_log("Пробуем через функцию updateOrderStatus...");
    if (updateOrderStatus($orderId, $newStatusId, $_SESSION['user_id'])) {
        error_log("ФУНКЦИЯ updateOrderStatus УСПЕШНА");
        // ... успешный код
    } else {
        error_log("ФУНКЦИЯ updateOrderStatus ВЕРНУЛА FALSE");
        $_SESSION['error'] = 'Ошибка при изменении статуса'; 
    }
}


renderHeader('Заказ #' . $order['id']);
?>

<div class="row">
    <div class="col-md-8">
        <body class="<?= !empty($order['deadline']) && (new DateTime($order['deadline'])) < (new DateTime()) ? 'order-overdue' : '' ?>">
        <!-- Основная информация -->
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
    <div>
        <h5 class="card-title mb-0">Информация о заказе #<?= $order['id'] ?></h5>
        <?php if (!empty($order['deadline'])): ?>
            <?php
            $deadline = new DateTime($order['deadline']);
            $today = new DateTime();
            $isOverdue = $deadline < $today;
            $daysDiff = $today->diff($deadline)->days;
            ?>
            <div class="mt-1">
                <span class="badge bg-<?= $isOverdue ? 'danger' : 'success' ?>">
                    <i class="bi bi-calendar-event"></i>
                    Дедлайн: <?= date('d.m.Y', strtotime($order['deadline'])) ?>
                    <?= $isOverdue ? ' (ПРОСРОЧЕНО)' : '' ?>
                </span>
                <small class="text-muted ms-2">
                    <?php if ($isOverdue): ?>
                        Просрочка: <?= $daysDiff ?> дн.
                    <?php else: ?>
                        Осталось: <?= $daysDiff ?> дн.
                    <?php endif; ?>
                </small>
            </div>
        <?php endif; ?>
    </div>
    
    <?php if (hasPermission('orders:edit-deadline')): ?>
    <div>
        <button type="button" class="btn btn-sm btn-<?= !empty($order['deadline']) && $isOverdue ? 'danger' : 'outline-primary' ?>" 
                data-bs-toggle="modal" data-bs-target="#deadlineModal">
            <i class="bi bi-calendar-plus"></i>
            <?= empty($order['deadline']) ? 'Установить дедлайн' : 'Изменить дедлайн' ?>
        </button>
    </div>
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
                    </div>
                    <div class="col-md-6">
                        <h6>Устройство</h6>
                        <p>
                            <?= htmlspecialchars($order['device_category'] ?? '') ?><br>
                            <?= htmlspecialchars($order['brand_name'] ?? '') ?> <?= htmlspecialchars($order['device_model'] ?? '') ?><br>
                            <?php if (!empty($order['serial_number'])): ?>
                                SN: <?= htmlspecialchars($order['serial_number']) ?>
                            <?php endif; ?>
                        </p>
                    </div>
                </div>
                
                <div class="row mt-3">
                    <div class="col-md-6">
                        <h6>Персонал</h6>
                        <p>
                            Менеджер: <strong><?= htmlspecialchars($order['manager_name'] ?? 'Не назначен') ?></strong><br>
                            Мастер: <strong><?= htmlspecialchars($order['master_name'] ?? 'Не назначен') ?></strong>
                        </p>
                        
                    </div>
                    <div class="col-md-6">
                       <h6>Статус</h6>
                        <form method="POST" class="d-inline">
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
                    <p><?= nl2br(htmlspecialchars($order['problem_description'])) ?></p>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Секция услуг -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Услуги</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addServiceModal">
                    <i class="bi bi-plus"></i> Добавить услугу
                </button>
            </div>
            <div class="card-body">
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
                            <?php if (empty($orderServices)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-3">Услуги не добавлены</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($orderServices as $service): ?>
                                <tr>
                                    <td><?= htmlspecialchars($service['service_name']) ?></td>
                                    <td><?= $service['quantity'] ?></td>
                                    <td><?= number_format($service['price'], 2) ?> ₽</td>
                                    <td><?= number_format($service['quantity'] * $service['price'], 2) ?> ₽</td>
                                    <td class="text-nowrap">
                                        <div class="btn-group btn-group-sm">
                                            <a href="service_edit.php?order_id=<?= $order['id'] ?>&service_id=<?= $service['id'] ?>" class="btn btn-outline-secondary">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <?php if (in_array($user['role_id'], [1, 2, 3])): // Владелец, администратор, менеджер ?>
                                            <form action="order_remove_service.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="service_id" value="<?= $service['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Удалить услугу?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Секция запчастей -->
        <div class="card mt-4">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Запчасти</h5>
                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addPartModal">
                    <i class="bi bi-plus"></i> Добавить запчасть
                </button>
            </div>
            <div class="card-body">
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
                            <?php if (empty($orderParts)): ?>
                            <tr>
                                <td colspan="6" class="text-center text-muted py-3">Запчасти не добавлены</td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($orderParts as $part): ?>
                                <tr>
                                    <td><?= htmlspecialchars($part['part_name']) ?></td>
                                    <td><?= $part['quantity'] ?></td>
                                    <td><?= number_format($part['price'], 2) ?> ₽</td>
                                    <td><?= number_format($part['quantity'] * $part['price'], 2) ?> ₽</td>
                                    <td><?= $part['issued_to_master'] ? 'Да' : 'Нет' ?></td>
                                    <td class="text-nowrap">
                                        <div class="btn-group btn-group-sm">
                                            <?php if (in_array($user['role_id'], [1, 2, 3])): // Владелец, администратор, менеджер ?>
                                            <form action="order_remove_part.php" method="POST" style="display:inline;">
                                                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                                                <input type="hidden" name="part_id" value="<?= $part['id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger" onclick="return confirm('Удалить запчасть?')">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </form>
                                            <?php endif; ?>
                                        </div>
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
                <div class="d-flex justify-content-between mb-2">
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
                        <textarea class="form-control" name="comment" rows="3" placeholder="Добавить комментарий..." required></textarea>
                    </div>
                    <button type="submit" class="btn btn-primary w-100">Добавить</button>
                </form>

                <div class="mt-3" style="max-height: 300px; overflow-y: auto;">
                    <?php foreach ($comments as $comment): ?>
                    <div class="border-bottom pb-2 mb-2">
                        <div class="d-flex justify-content-between">
                            <strong><?= htmlspecialchars($comment['user_name']) ?></strong>
                            <small class="text-muted"><?= date('d.m.Y H:i', strtotime($comment['created_at'])) ?></small>
                        </div>
                        <p class="mb-0"><?= nl2br(htmlspecialchars($comment['comment'])) ?></p>
                    </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления услуги -->
<div class="modal fade" id="addServiceModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавить услугу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="order_add_service.php">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Услуга *</label>
                        <select class="form-select" name="service_id" required>
                            <option value="">-- Выберите услугу --</option>
                            <?php if (!empty($servicesList)): ?>
                                <?php foreach ($servicesList as $service): ?>
                                <option value="<?= $service['id'] ?>" data-price="<?= $service['price'] ?>">
                                    <?= htmlspecialchars($service['name']) ?> - <?= number_format($service['price'], 2) ?> ₽
                                </option>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <option value="" disabled>Нет доступных услуг</option>
                            <?php endif; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Количество *</label>
                        <input type="number" class="form-control" name="quantity" value="1" min="1" required>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Модальное окно для добавления запчасти -->
<div class="modal fade" id="addPartModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Добавить запчасть к заказу</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="order_add_part.php">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Запчасть *</label>
                        <select class="form-select" name="part_id" required id="partSelect" onchange="updatePartInfo()">
                            <option value="">-- Выберите запчасть --</option>
                            <?php 
                            $partsList = getFromTable('parts', '*', 'is_active = 1 AND stock_quantity > 0', [], 'ORDER BY name');
                            if (!empty($partsList)): 
                                foreach ($partsList as $part): 
                            ?>
                            <option value="<?= $part['id'] ?>" 
                                    data-price="<?= $part['sale_price'] ?>" 
                                    data-stock="<?= $part['stock_quantity'] ?>"
                                    data-name="<?= htmlspecialchars($part['name']) ?>">
                                <?= htmlspecialchars($part['name'] ?? 'Без названия') ?> 
                                - <?= number_format($part['sale_price'] ?? 0, 2) ?> ₽
                                (В наличии: <?= $part['stock_quantity'] ?> шт.)
                            </option>
                            <?php 
                                endforeach;
                            else: 
                            ?>
                            <option value="" disabled>Нет доступных запчастей на складе</option>
                            <?php endif; ?>
                        </select>
                        <div class="form-text">Показываются только запчасти в наличии</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Количество *</label>
                        <input type="number" class="form-control" name="quantity" value="1" min="1" required 
                               id="quantityInput" onchange="validateQuantity()">
                        <div class="form-text" id="quantityHelp">Максимально доступно: <span id="maxQuantity">0</span> шт.</div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Цена за единицу</label>
                        <input type="number" class="form-control" name="price" id="priceInput" readonly>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Общая сумма</label>
                        <input type="number" class="form-control" id="totalPrice" readonly>
                    </div>
                    
                    <div class="mb-3 form-check">
                        <input type="checkbox" class="form-check-input" name="issued_to_master" id="issuedToMaster" value="1">
                        <label class="form-check-label" for="issuedToMaster">
                            ✅ Выдано мастеру (будет списано со склада)
                        </label>
                    </div>
                    
                    <div class="alert alert-info">
                        <strong>Внимание:</strong> При отметке "Выдано мастеру" запчасть будет автоматически 
                        списана со склада и добавлена в учет выданных мастеру запчастей.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" id="submitButton">Добавить</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Модальное окно для сроков диагностики -->
<div class="modal fade" id="diagnosticModal" tabindex="-1">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">
                    <i class="bi bi-binoculars me-2"></i>
                    Управление сроками диагностики
                </h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" action="update_diagnostic_deadline.php">
                <input type="hidden" name="order_id" value="<?= $order['id'] ?>">
                <div class="modal-body">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Дней на диагностику *</label>
                                <input type="number" class="form-control" name="diagnostic_days" 
                                       value="<?= $order['diagnostic_days'] ?? 3 ?>" min="1" max="30" required>
                                <div class="form-text">Стандартно 3 дня</div>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Дедлайн диагностики</label>
                                <input type="date" class="form-control" name="diagnostic_deadline" 
                                       value="<?= !empty($order['diagnostic_deadline']) ? date('Y-m-d', strtotime($order['diagnostic_deadline'])) : '' ?>"
                                       readonly style="background-color: #f8f9fa;">
                                <div class="form-text">Рассчитается автоматически</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Общий дедлайн заказа (опционально)</label>
                        <input type="date" class="form-control" name="deadline" 
                               value="<?= !empty($order['deadline']) ? date('Y-m-d', strtotime($order['deadline'])) : '' ?>">
                        <div class="form-text">Окончательный срок выполнения всего заказа</div>
                    </div>
                    
                    <?php if (!empty($order['diagnostic_deadline'])): ?>
                        <div class="alert alert-<?= $isDiagnosticOverdue ? 'danger' : 'info' ?>">
                            <h6>Текущие сроки</h6>
                            <p class="mb-1">
                                <strong>Диагностика до:</strong> <?= date('d.m.Y', strtotime($order['diagnostic_deadline'])) ?>
                                <?php if ($isDiagnosticOverdue): ?>
                                    <span class="badge bg-danger ms-2">ПРОСРОЧЕНО на <?= abs($diagnosticDaysLeft) ?> дн.</span>
                                <?php else: ?>
                                    <span class="badge bg-success ms-2">Осталось <?= $diagnosticDaysLeft ?> дн.</span>
                                <?php endif; ?>
                            </p>
                            <?php if ($isDiagnosticOverdue && $order['master_id']): ?>
                                <p class="mb-0 mt-2">
                                    <i class="bi bi-exclamation-triangle"></i>
                                    <strong>Возможный штраф за просрочку диагностики:</strong> 
                                    <?= number_format(calculateDiagnosticPenalty($order['id']), 2) ?> ₽
                                </p>
                            <?php endif; ?>
                        </div>
                    <?php endif; ?>
                </div>
                <div class="modal-footer">
                    <?php if (!empty($order['diagnostic_deadline'])): ?>
                    <button type="submit" class="btn btn-danger" name="remove_diagnostic" value="1">
                        <i class="bi bi-calendar-x"></i> Сбросить сроки
                    </button>
                    <?php endif; ?>
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Отмена</button>
                    <button type="submit" class="btn btn-primary" name="update_diagnostic">
                        <i class="bi bi-calendar-check"></i> Сохранить
                    </button>


                </div>
            </form>


            <?php if ($penaltyDetails['total'] > 0): ?>
<div class="alert alert-danger">
    <h6><i class="bi bi-exclamation-triangle"></i> Накопленные штрафы</h6>
    <p class="mb-2">Общая сумма штрафов: <strong><?= number_format($penaltyDetails['total'], 2) ?> ₽</strong></p>
    
    <?php foreach ($penaltyDetails['details'] as $detail): ?>
    <div class="small">
        • <?= $detail['reason'] ?>: 
        <strong><?= number_format($detail['final'], 2) ?> ₽</strong>
    </div>
    <?php endforeach; ?>
    
    <div class="mt-2 small text-muted">
        Максимальный штраф не превышает <?= $penaltyDetails['max_percentage'] ?>% от суммы заказа
    </div>
</div>
<?php endif; ?>
        </div>
    </div>
</div>
<script>
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
    } else {
        priceInput.value = '';
        totalPrice.value = '';
        maxQuantitySpan.textContent = '0';
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

// Инициализация при загрузке модального окна
document.getElementById('addPartModal').addEventListener('show.bs.modal', function() {
    setTimeout(updatePartInfo, 100);
});
// Автоматический расчет дедлайна диагностики
document.addEventListener('DOMContentLoaded', function() {
    const daysInput = document.querySelector('input[name="diagnostic_days"]');
    const deadlineInput = document.querySelector('input[name="diagnostic_deadline"]');
    
    function calculateDeadline() {
        const days = parseInt(daysInput.value) || 3;
        const today = new Date();
        today.setDate(today.getDate() + days);
        
        const year = today.getFullYear();
        const month = String(today.getMonth() + 1).padStart(2, '0');
        const day = String(today.getDate()).padStart(2, '0');
        
        deadlineInput.value = `${year}-${month}-${day}`;
    }
    
    daysInput.addEventListener('change', calculateDeadline);
    daysInput.addEventListener('input', calculateDeadline);
    
    // Рассчитать при загрузке
    calculateDeadline();
});
</script>

<?php renderFooter(); ?>