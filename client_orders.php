<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('clients:view');
error_reporting(E_ALL);
ini_set('display_errors', 1);

$clientId = $_GET['id'] ?? 0;

// Получаем данные клиента
$client = getFromTable('clients', '*', 'id = ?', [$clientId]);
if (!$client) {
    $_SESSION['error'] = 'Клиент не найден';
    header('Location: clients.php');
    exit;
}
$client = $client[0];

// Получаем заказы клиента
try {
    $stmt = $pdo->prepare("SELECT * FROM orders WHERE client_id = ? ORDER BY created_at DESC");
    $stmt->execute([$clientId]);
    $orders = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Error getting orders: " . $e->getMessage());
    $orders = [];
}

$totalOrders = count($orders);
$totalSpent = array_sum(array_column($orders, 'total_amount'));

// Отладка - раскомментируйте если нужно проверить
/*
echo "Client ID: " . $clientId . "<br>";
echo "Found orders: " . count($orders) . "<br>";
if (count($orders) > 0) {
    foreach ($orders as $order) {
        echo "Order ID: " . $order['id'] . "<br>";
    }
}
*/

renderHeader('Заказы клиента: ' . ($client['full_name'] ?: $client['company_name']));
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">
                    Заказы клиента: <strong><?= htmlspecialchars($client['full_name'] ?: $client['company_name']) ?></strong>
                </h5>
            </div>
            <div class="card-body">
                <!-- Статистика -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <h6>Всего заказов</h6>
                                <h3><?= $totalOrders ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h6>Общая сумма</h6>
                                <h4><?= number_format($totalSpent, 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h6>Средний чек</h6>
                                <h4><?= $totalOrders > 0 ? number_format($totalSpent / $totalOrders, 2) : 0 ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h6>Телефон</h6>
                                <h5><?= $client['phone'] ?: '—' ?></h5>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Список заказов -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>ID заказа</th>
                                <th>Дата создания</th>
                                <th>Устройство</th>
                                <th>Статус</th>
                                <th>Сумма</th>
                                <th>Менеджер</th>
                                <th>Мастер</th>
                                <th>Действия</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($orders)): ?>
                            <tr>
                                <td colspan="8" class="text-center text-muted py-4">
                                    <i class="bi bi-cart" style="font-size: 2rem;"></i>
                                    <p class="mt-2">У клиента нет заказов</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($orders as $order): ?>
                                <?php
                                $status = getFromTable('statuses', '*', 'id = ?', [$order['status_id']]);
                                $status = $status ? $status[0] : ['name' => 'Неизвестно', 'color' => '#6c757d'];
                                ?>
                                <tr>
                                    <td>#<?= $order['id'] ?></td>
                                    <td><?= date('d.m.Y H:i', strtotime($order['created_at'])) ?></td>
                                    <td>
                                        <?php
                                        $deviceInfo = [];
                                        if ($order['device_category_id']) {
                                            $category = getFromTable('device_categories', 'name', 'id = ?', [$order['device_category_id']]);
                                            if ($category) $deviceInfo[] = $category[0]['name'];
                                        }
                                        if ($order['brand_id']) {
                                            $brand = getFromTable('brands', 'name', 'id = ?', [$order['brand_id']]);
                                            if ($brand) $deviceInfo[] = $brand[0]['name'];
                                        }
                                        if ($order['device_model_id']) {
                                            $model = getFromTable('device_models', 'name', 'id = ?', [$order['device_model_id']]);
                                            if ($model) $deviceInfo[] = $model[0]['name'];
                                        }
                                        echo implode(' ', $deviceInfo) ?: '—';
                                        ?>
                                    </td>
                                    <td>
                                        <span class="badge" style="background-color: <?= $status['color'] ?>; color: white;">
                                            <?= htmlspecialchars($status['name']) ?>
                                        </span>
                                    </td>
                                    <td><?= number_format($order['total_amount'], 2) ?> ₽</td>
                                    <td>
                                        <?php if ($order['manager_id']): ?>
                                            <?php $manager = getFromTable('users', 'full_name', 'id = ?', [$order['manager_id']]) ?>
                                            <?= $manager ? htmlspecialchars($manager[0]['full_name']) : '—' ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($order['master_id']): ?>
                                            <?php $master = getFromTable('users', 'full_name', 'id = ?', [$order['master_id']]) ?>
                                            <?= $master ? htmlspecialchars($master[0]['full_name']) : '—' ?>
                                        <?php else: ?>
                                            —
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <a href="order_view.php?id=<?= $order['id'] ?>" class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-eye"></i> Просмотр
                                        </a>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="clients.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Назад к списку клиентов
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>