<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('parts:view');

$partId = $_GET['id'] ?? 0;
$part = getPartById($partId);

if (!$part) {
    $_SESSION['error'] = 'Запчасть не найдена';
    header('Location: parts.php');
    exit;
}

// Получаем статистику
$stats = getPartsUsageStats($partId);
$masterStats = getPartsUsageByMasters($partId);

renderHeader('Статистика запчасти: ' . htmlspecialchars($part['name']));
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Статистика запчасти: <strong><?= htmlspecialchars($part['name']) ?></strong></h5>
            </div>
            <div class="card-body">
                <!-- Основная статистика -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <h6>Использовано</h6>
                                <h3><?= $stats[0]['total_used'] ?? 0 ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h6>Общая выручка</h6>
                                <h4><?= number_format($stats[0]['total_revenue'] ?? 0, 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h6>Общая себестоимость</h6>
                                <h4><?= number_format($stats[0]['total_cost'] ?? 0, 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h6>Общая прибыль</h6>
                                <h4><?= number_format($stats[0]['total_profit'] ?? 0, 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика по мастерам -->
                <h5 class="mb-3">Статистика по мастерам</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Мастер</th>
                                <th>Количество использований</th>
                                <th>Общая выручка</th>
                                <th>Общая себестоимость</th>
                                <th>Прибыль</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($masterStats)): ?>
                            <tr>
                                <td colspan="5" class="text-center text-muted py-4">
                                    <i class="bi bi-people" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Нет данных по мастерам</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php foreach ($masterStats as $masterStat): ?>
                                <tr>
                                    <td><?= htmlspecialchars($masterStat['master_name']) ?></td>
                                    <td><?= $masterStat['parts_used'] ?></td>
                                    <td><?= number_format($masterStat['total_revenue'], 2) ?> ₽</td>
                                    <td><?= number_format($masterStat['total_cost'], 2) ?> ₽</td>
                                    <td class="fw-bold text-success">
                                        <?= number_format($masterStat['total_revenue'] - $masterStat['total_cost'], 2) ?> ₽
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <div class="mt-3">
                    <a href="parts.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Назад к списку запчастей
                    </a>
                    <a href="part_edit.php?id=<?= $part['id'] ?>" class="btn btn-primary ms-2">
                        <i class="bi bi-pencil"></i> Редактировать
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>