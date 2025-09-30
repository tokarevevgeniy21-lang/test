<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('employees:view');

$masterId = $_GET['id'] ?? 0;
$master = getFromTable('users', '*', 'id = ? AND role_id = 4', [$masterId]);

if (!$master) {
    $_SESSION['error'] = 'Мастер не найден';
    header('Location: employees.php');
    exit;
}
$master = $master[0];

// Получаем историю операций
$stmt = $pdo->prepare("
    SELECT * FROM salary_payments 
    WHERE user_id = ? 
    ORDER BY payment_date DESC
");
$stmt->execute([$masterId]);
$transactions = $stmt->fetchAll();

$balance = getMasterBalance($masterId);

renderHeader('Баланс мастера: ' . htmlspecialchars($master['full_name']));
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Баланс мастера: <?= htmlspecialchars($master['full_name']) ?></h5>
                <span class="badge bg-<?= $balance >= 0 ? 'success' : 'danger' ?>">
                    <?= number_format($balance, 2) ?> ₽
                </span>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Тип операции</th>
                                <th>Заказ</th>
                                <th>Сумма</th>
                                <th>Описание</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $transaction): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i', strtotime($transaction['payment_date'])) ?></td>
                                <td>
                                    <span class="badge bg-<?= $transaction['amount'] >= 0 ? 'success' : 'danger' ?>">
                                        <?= $transaction['amount'] >= 0 ? 'Начисление' : 'Вычет' ?>
                                    </span>
                                </td>
                                <td>
                                    <?php if ($transaction['order_id']): ?>
                                    <a href="order_view.php?id=<?= $transaction['order_id'] ?>">
                                        #<?= $transaction['order_id'] ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td class="fw-bold <?= $transaction['amount'] >= 0 ? 'text-success' : 'text-danger' ?>">
                                    <?= number_format($transaction['amount'], 2) ?> ₽
                                </td>
                                <td><?= htmlspecialchars($transaction['calculation_details']) ?></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>