<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('salaries:view');

$userId = $_GET['user_id'] ?? 0;
$user = getFromTable('users', '*', 'id = ?', [$userId]);

if (!$user) {
    $_SESSION['error'] = 'Сотрудник не найден';
    header('Location: salaries.php');
    exit;
}
$user = $user[0];

// Получаем детализацию зарплаты
$stmt = $pdo->prepare("
    SELECT 
        sp.*,
        o.total_amount,
        o.id as order_id
    FROM salary_payments sp
    LEFT JOIN orders o ON sp.order_id = o.id
    WHERE sp.user_id = ?
    ORDER BY sp.payment_date DESC
");
$stmt->execute([$userId]);
$transactions = $stmt->fetchAll();

// Статистика
$totalEarnings = array_sum(array_column(array_filter($transactions, fn($t) => $t['amount'] > 0), 'amount'));
$totalDeductions = array_sum(array_column(array_filter($transactions, fn($t) => $t['amount'] < 0), 'amount'));
$balance = $totalEarnings + $totalDeductions;

renderHeader('Детализация зарплаты: ' . htmlspecialchars($user['full_name']));
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Детализация зарплаты: <?= htmlspecialchars($user['full_name']) ?></h5>
            </div>
            <div class="card-body">
                <!-- Статистика -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h6>Всего начислено</h6>
                                <h4><?= number_format($totalEarnings, 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-danger text-white text-center">
                            <div class="card-body">
                                <h6>Всего вычетов</h6>
                                <h4><?= number_format(abs($totalDeductions), 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h6>Текущий баланс</h6>
                                <h4><?= number_format($balance, 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h6>Операций</h6>
                                <h4><?= count($transactions) ?></h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Детализация -->
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Дата</th>
                                <th>Тип</th>
                                <th>Заказ</th>
                                <th>Сумма заказа</th>
                                <th>Сумма операции</th>
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
                                    <a href="order_view.php?id=<?= $transaction['order_id'] ?>" class="btn btn-sm btn-outline-info">
                                        #<?= $transaction['order_id'] ?>
                                    </a>
                                    <?php else: ?>
                                    <span class="text-muted">—</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($transaction['total_amount']): ?>
                                        <?= number_format($transaction['total_amount'], 2) ?> ₽
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

                <div class="mt-3">
                    <a href="salaries.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Назад к списку
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>