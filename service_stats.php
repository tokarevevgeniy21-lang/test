<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('services:view');

$serviceId = $_GET['id'] ?? 0;
$service = getServiceById($serviceId);

if (!$service) {
    $_SESSION['error'] = 'Услуга не найдена';
    header('Location: services.php');
    exit;
}

// Получаем статистику
$stats = getServiceUsageStats($serviceId);
$employeeStats = getServiceUsageByEmployees($serviceId);

renderHeader('Статистика услуги: ' . htmlspecialchars($service['name']));
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Статистика услуги: <strong><?= htmlspecialchars($service['name']) ?></strong></h5>
            </div>
            <div class="card-body">
                <!-- Основная статистика -->
                <div class="row mb-4">
                    <div class="col-md-3">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <h6>Использований</h6>
                                <h3><?= $stats[0]['usage_count'] ?? 0 ?></h3>
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
                                <h6>Средняя цена</h6>
                                <h4><?= number_format($stats[0]['avg_price'] ?? $service['price'], 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h6>Текущая цена</h6>
                                <h4><?= number_format($service['price'], 2) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Статистика по сотрудникам -->
                <h5 class="mb-3">Статистика по сотрудникам</h5>
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Сотрудник</th>
                                <th>Количество оказаний</th>
                                <th>Общая выручка</th>
                                <th>Доля от общей выручки</th>
                            </tr>
                        </thead>
                        <tbody>
                                                        <?php if (empty($employeeStats)): ?>
                            <tr>
                                <td colspan="4" class="text-center text-muted py-4">
                                    <i class="bi bi-people" style="font-size: 2rem;"></i>
                                    <p class="mt-2">Нет данных по сотрудникам</p>
                                </td>
                            </tr>
                            <?php else: ?>
                                <?php 
                                $totalRevenue = $stats[0]['total_revenue'] ?? 0;
                                foreach ($employeeStats as $empStat): 
                                    $percentage = $totalRevenue > 0 ? ($empStat['total_revenue'] / $totalRevenue) * 100 : 0;
                                ?>
                                <tr>
                                    <td><?= htmlspecialchars($empStat['employee_name']) ?></td>
                                    <td><?= $empStat['service_count'] ?></td>
                                    <td><?= number_format($empStat['total_revenue'], 2) ?> ₽</td>
                                    <td>
                                        <div class="progress" style="height: 20px;">
                                            <div class="progress-bar" role="progressbar" 
                                                 style="width: <?= $percentage ?>%;" 
                                                 aria-valuenow="<?= $percentage ?>" 
                                                 aria-valuemin="0" 
                                                 aria-valuemax="100">
                                                <?= round($percentage, 1) ?>%
                                            </div>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>

                <!-- Графики (можно добавить позже с Chart.js) -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Распределение по сотрудникам</h6>
                            </div>
                            <div class="card-body">
                                <div id="employeeChart" style="height: 300px;">
                                    <p class="text-muted text-center py-5">График будет отображаться здесь</p>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6 class="card-title mb-0">Динамика использования</h6>
                            </div>
                            <div class="card-body">
                                <div id="usageChart" style="height: 300px;">
                                    <p class="text-muted text-center py-5">График будет отображаться здесь</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="mt-3">
                    <a href="services.php" class="btn btn-secondary">
                        <i class="bi bi-arrow-left"></i> Назад к списку услуг
                    </a>
                    <a href="service_edit.php?id=<?= $service['id'] ?>" class="btn btn-primary ms-2">
                        <i class="bi bi-pencil"></i> Редактировать
                    </a>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Подключение Chart.js для графиков -->
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
document.addEventListener('DOMContentLoaded', function() {
    // Данные для графиков (можно получить через AJAX или PHP)
    const employeeData = [
        <?php foreach ($employeeStats as $empStat): ?>
        {
            name: '<?= addslashes($empStat['employee_name']) ?>',
            revenue: <?= $empStat['total_revenue'] ?>,
            count: <?= $empStat['service_count'] ?>
        },
        <?php endforeach; ?>
    ];

    // Здесь можно добавить код для инициализации графиков
    // когда будете готовы реализовать эту функциональность
});
</script>

<?php renderFooter(); ?>