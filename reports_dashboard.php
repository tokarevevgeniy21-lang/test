<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

// Получаем данные для дашборда
$today = date('Y-m-d');
$monthStart = date('Y-m-01');
$monthEnd = date('Y-m-t');

function getDashboardStats($startDate, $endDate) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            -- Основные метрики
            COUNT(DISTINCT o.id) as total_orders,
            COUNT(DISTINCT c.id) as new_clients,
            COALESCE(SUM(o.total_amount), 0) as total_revenue,
            COALESCE(SUM(o.profit), 0) as total_profit,
            
            -- Активность
            COUNT(DISTINCT o.master_id) as active_masters,
            COUNT(DISTINCT o.manager_id) as active_managers,
            
            -- Средние значения
            COALESCE(AVG(o.total_amount), 0) as avg_order,
            COALESCE(AVG(TIMESTAMPDIFF(HOUR, o.created_at, o.closed_at)), 0) as avg_repair_hours
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        WHERE o.created_at BETWEEN ? AND ?
    ");
    
    $stmt->execute([$startDate, $endDate]);
    return $stmt->fetch();
}

function getDailyStats($date) {
    global $pdo;
    
    $stmt = $pdo->prepare("
        SELECT 
            COUNT(o.id) as today_orders,
            COALESCE(SUM(o.total_amount), 0) as today_revenue,
            COUNT(DISTINCT c.id) as today_clients
        FROM orders o
        LEFT JOIN clients c ON o.client_id = c.id
        WHERE DATE(o.created_at) = ?
    ");
    
    $stmt->execute([$date]);
    return $stmt->fetch();
}

$monthStats = getDashboardStats($monthStart, $monthEnd);
$todayStats = getDailyStats($today);

renderHeader('Дашборд отчетов');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">📊 Дашборд аналитики</h5>
            </div>
            <div class="card-body">
                <!-- Ключевые метрики -->
                <div class="row mb-4">
                    <div class="col-md-2">
                        <div class="card bg-primary text-white text-center">
                            <div class="card-body">
                                <h6>Заказов сегодня</h6>
                                <h3><?= $todayStats['today_orders'] ?? 0 ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-success text-white text-center">
                            <div class="card-body">
                                <h6>Выручка сегодня</h6>
                                <h4><?= number_format($todayStats['today_revenue'] ?? 0, 0) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-info text-white text-center">
                            <div class="card-body">
                                <h6>Заказов за месяц</h6>
                                <h3><?= $monthStats['total_orders'] ?></h3>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-warning text-white text-center">
                            <div class="card-body">
                                <h6>Выручка за месяц</h6>
                                <h4><?= number_format($monthStats['total_revenue'], 0) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-danger text-white text-center">
                            <div class="card-body">
                                <h6>Прибыль за месяц</h6>
                                <h4><?= number_format($monthStats['total_profit'], 0) ?> ₽</h4>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="card bg-secondary text-white text-center">
                            <div class="card-body">
                                <h6>Новых клиентов</h6>
                                <h3><?= $monthStats['new_clients'] ?></h3>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Быстрые ссылки на отчеты -->
                <div class="row">
                    <div class="col-md-3 mb-3">
                        <a href="reports_orders.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-clipboard-data" style="font-size: 2rem;"></i>
                                <h6>Отчеты по заказам</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports_finance.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-graph-up" style="font-size: 2rem;"></i>
                                <h6>Финансовые отчеты</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports_employees.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-people" style="font-size: 2rem;"></i>
                                <h6>Отчеты по сотрудникам</h6>
                            </div>
                        </a>
                    </div>
                    <div class="col-md-3 mb-3">
                        <a href="reports_clients.php" class="card text-decoration-none">
                            <div class="card-body text-center">
                                <i class="bi bi-person-badge" style="font-size: 2rem;"></i>
                                <h6>Аналитика клиентов</h6>
                            </div>
                        </a>
                    </div>
                </div>

                <!-- Дополнительная статистика -->
                <div class="row mt-4">
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Эффективность за месяц</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h5><?= $monthStats['active_masters'] ?></h5>
                                        <small>Активных мастеров</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?= $monthStats['active_managers'] ?></h5>
                                        <small>Активных менеджеров</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?= round($monthStats['avg_repair_hours'], 1) ?></h5>
                                        <small>Среднее время ремонта (ч)</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="card">
                            <div class="card-header">
                                <h6>Финансовые показатели</h6>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-4">
                                        <h5><?= number_format($monthStats['avg_order'], 0) ?> ₽</h5>
                                        <small>Средний чек</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?= number_format($monthStats['total_revenue'] / max($monthStats['total_orders'], 1), 0) ?> ₽</h5>
                                        <small>На заказ</small>
                                    </div>
                                    <div class="col-4">
                                        <h5><?= $monthStats['total_orders'] > 0 ? number_format(($monthStats['total_profit'] / $monthStats['total_revenue']) * 100, 1) : 0 ?>%</h5>
                                        <small>Маржа</small>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<?php renderFooter(); ?>