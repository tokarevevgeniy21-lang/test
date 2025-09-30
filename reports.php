<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('reports:view');

renderHeader('Отчеты и аналитика');
?>

<div class="row">
    <!-- Отчеты по заказам -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-clipboard-data" style="font-size: 2rem;"></i>
                <h5 class="card-title">Отчеты по заказам</h5>
                <p class="card-text">Аналитика заказов и статусов</p>
                <a href="reports_orders.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Финансовые отчеты -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-graph-up" style="font-size: 2rem;"></i>
                <h5 class="card-title">Финансовые отчеты</h5>
                <p class="card-text">Доходы, расходы, прибыль</p>
                <a href="reports_finance.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Отчеты по сотрудникам -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-people" style="font-size: 2rem;"></i>
                <h5 class="card-title">Отчеты по сотрудникам</h5>
                <p class="card-text">Эффективность мастеров и менеджеров</p>
                <a href="reports_employees.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Аналитика клиентов -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-person-badge" style="font-size: 2rem;"></i>
                <h5 class="card-title">Аналитика клиентов</h5>
                <p class="card-text">Демография и источники клиентов</p>
                <a href="reports_clients.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Аналитика устройств -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-phone" style="font-size: 2rem;"></i>
                <h5 class="card-title">Аналитика устройств</h5>
                <p class="card-text">По брендам, категориям и моделям</p>
                <a href="reports_devices.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Аналитика услуг -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-tools" style="font-size: 2rem;"></i>
                <h5 class="card-title">Аналитика услуг</h5>
                <p class="card-text">Популярные услуги и запчасти</p>
                <a href="reports_services.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Кассовые отчеты -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-cash-coin" style="font-size: 2rem;"></i>
                <h5 class="card-title">Кассовые отчеты</h5>
                <p class="card-text">Движение денежных средств</p>
                <a href="reports_cash.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Маркетинговые отчеты -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-megaphone" style="font-size: 2rem;"></i>
                <h5 class="card-title">Маркетинговые отчеты</h5>
                <p class="card-text">Эффективность рекламы и акций</p>
                <a href="reports_marketing.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Общие отчеты -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-bar-chart" style="font-size: 2rem;"></i>
                <h5 class="card-title">Общие отчеты</h5>
                <p class="card-text">Сводные отчеты и дашборды</p>
                <a href="reports_general.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Отчеты по гарантии -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-shield-check" style="font-size: 2rem;"></i>
                <h5 class="card-title">Гарантийные отчеты</h5>
                <p class="card-text">Анализ гарантийных случаев</p>
                <a href="reports_warranty.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>

    <!-- Серийные номера -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-upc-scan" style="font-size: 2rem;"></i>
                <h5 class="card-title">Анализ серийных номеров</h5>
                <p class="card-text">Статистика по устройствам</p>
                <a href="reports_serial.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>
    <!-- Временные отчеты -->
    <div class="col-md-3 mb-4">
        <div class="card h-100">
            <div class="card-body text-center">
                <i class="bi bi-calendar" style="font-size: 2rem;"></i>
                <h5 class="card-title">Временная аналитика</h5>
                <p class="card-text">Динамика по дням/неделям/месяцам</p>
                <a href="reports_time.php" class="btn btn-primary">Открыть</a>
            </div>
        </div>
    </div>
</div>
<!-- Дашборд -->
<div class="col-md-3 mb-4">
    <div class="card h-100">
        <div class="card-body text-center">
            <i class="bi bi-speedometer2" style="font-size: 2rem;"></i>
            <h5 class="card-title">Дашборд</h5>
            <p class="card-text">Общая статистика и ключевые метрики</p>
            <a href="reports_dashboard.php" class="btn btn-primary">Открыть</a>
        </div>
    </div>
</div>


<?php renderFooter(); ?>