<?php
require_once 'inc/layout.php';
require_once 'part_issue.php';
requireAuth();
requirePermission('parts:issue');

$partId = $_GET['id'] ?? 0;
$part = getPartById($partId);

if (!$part) {
    $_SESSION['error'] = 'Запчасть не найдена';
    header('Location: parts.php');
    exit;
}

$masters = getActiveMasters();

// Обработка быстрой выдачи
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $masterId = (int)$_POST['master_id'];
    $quantity = (int)$_POST['quantity'];
    $orderId = !empty($_POST['order_id']) ? (int)$_POST['order_id'] : null;
    
    if ($quantity <= 0) {
        $_SESSION['error'] = 'Количество должно быть больше 0';
    } elseif ($quantity > $part['stock_quantity']) {
        $_SESSION['error'] = 'Недостаточно запчастей на складе';
    } elseif (issuePartToMaster($partId, $masterId, $quantity, $orderId)) {
        $_SESSION['success'] = 'Запчасть успешно выдана мастеру';
        header('Location: parts.php');
        exit;
    } else {
        $_SESSION['error'] = 'Ошибка при выдаче запчасти';
    }
}

renderHeader('Быстрая выдача: ' . htmlspecialchars($part['name']));
?>

<div class="row">
    <div class="col-md-6 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Быстрая выдача: <?= htmlspecialchars($part['name']) ?></h5>
            </div>
            <div class="card-body">
                <div class="alert alert-info mb-3">
                    <strong>В наличии:</strong> <?= $part['stock_quantity'] ?> шт.<br>
                    <strong>Цена продажи:</strong> <?= number_format($part['sale_price'], 2) ?> ₽<br>
                    <strong>Себестоимость:</strong> <?= number_format($part['cost_price'], 2) ?> ₽
                </div>
                
                <form method="POST">
                    <div class="mb-3">
                        <label class="form-label">Мастер *</label>
                        <select class="form-select" name="master_id" required>
                            <option value="">-- Выберите мастера --</option>
                            <?php foreach ($masters as $master): ?>
                            <option value="<?= $master['id'] ?>"><?= htmlspecialchars($master['full_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Количество *</label>
                        <input type="number" class="form-control" name="quantity" value="1" 
                               min="1" max="<?= $part['stock_quantity'] ?>" required>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Номер заказа</label>
                        <input type="number" class="form-control" name="order_id" placeholder="Обязательно">
                    </div>
                    
                    <div class="alert alert-warning">
                        <strong>Внимание:</strong> При использовании запчасти в заказе, из зарплаты мастера 
                        будет удержано 40% от прибыли (<?= number_format(($part['sale_price'] - $part['cost_price']) * 0.4, 2) ?> ₽ с единицы).
                    </div>
                    
                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Выдать запчасть</button>
                        <a href="parts.php" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>