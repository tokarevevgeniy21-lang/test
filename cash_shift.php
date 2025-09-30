<?php
// cash_shift.php - Управление кассовыми сменами (исправленная версия)
require_once 'inc/layout.php';

requireAuth();
requirePermission('cash:access');

global $pdo;

$action = $_GET['action'] ?? '';



// Обработка открытия смены
if ($action === 'open' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $startBalance = (float)$_POST['start_balance'];
        
        // Проверяем нет ли активной смены
        $activeShift = getActiveCashShift($_SESSION['user_id']);
        if ($activeShift) {
            throw new Exception('У вас уже есть активная смена');
        }
        
        // Открываем новую смену
        $stmt = $pdo->prepare("
            INSERT INTO cashier_shifts (cashier_id, start_date, start_balance, status) 
            VALUES (?, NOW(), ?, 'open')
        ");
        $stmt->execute([$_SESSION['user_id'], $startBalance]);
        
        $_SESSION['success'] = 'Кассовая смена успешно открыта';
        header('Location: cash.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка открытия смены: ' . $e->getMessage();
        header('Location: cash_shift.php?action=open');
        exit;
    }
}

// Обработка закрытия смены
if ($action === 'close' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $actualCash = (float)$_POST['actual_cash'];
        $comments = trim($_POST['comments'] ?? '');
        
        // Получаем активную смену
        $activeShift = getActiveCashShift($_SESSION['user_id']);
        if (!$activeShift) {
            throw new Exception('Нет активной смены для закрытия');
        }
        
        // Рассчитываем итоги смены - ПРАВИЛЬНАЯ ФОРМУЛА
        $shiftStats = getShiftStatistics($activeShift['id']);
        
        // Ожидаемая сумма = начальный баланс + приход наличными - расход наличными
        $expectedCash = $activeShift['start_balance'] + $shiftStats['cash_income'] - $shiftStats['cash_expense'];
        $difference = $actualCash - $expectedCash;
        
        // Закрываем смену
        $stmt = $pdo->prepare("
            UPDATE cashier_shifts 
            SET end_date = NOW(), 
                end_balance = ?, 
                actual_cash = ?, 
                difference = ?, 
                status = 'closed', 
                comments = ?
            WHERE id = ?
        ");
        $stmt->execute([$expectedCash, $actualCash, $difference, $comments, $activeShift['id']]);
        
        $_SESSION['success'] = 'Кассовая смена успешно закрыта. ' . 
                             ($difference != 0 ? 'Расхождение: ' . number_format($difference, 2) . ' руб.' : 'Расхождений нет.');
        header('Location: cash.php');
        exit;
        
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка закрытия смены: ' . $e->getMessage();
        header('Location: cash_shift.php?action=close');
        exit;
    }
}

// Показываем соответствующий интерфейс
if ($action === 'open') {
    showOpenShiftForm();
} elseif ($action === 'close') {
    showCloseShiftForm();
} else {
    header('Location: cash.php');
    exit;
}

function showOpenShiftForm() {
    renderHeader('Открытие кассовой смены');
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📊 Открытие кассовой смены</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Начальный баланс *</label>
                            <input type="number" step="0.01" class="form-control" name="start_balance" required value="0.00">
                            <small class="form-text text-muted">Сумма наличных в кассе на начало смены</small>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-success">✅ Открыть смену</button>
                            <a href="cash.php" class="btn btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    renderFooter();
}

function showCloseShiftForm() {
    $activeShift = getActiveCashShift($_SESSION['user_id']);
    if (!$activeShift) {
        header('Location: cash.php');
        exit;
    }
    
    $shiftStats = getShiftStatistics($activeShift['id']);
    
    // ПРАВИЛЬНЫЙ РАСЧЕТ
    $startBalance = (float)($activeShift['start_balance'] ?? 0);
    $cashIncome = (float)($shiftStats['cash_income'] ?? 0);
    $cashExpense = (float)($shiftStats['cash_expense'] ?? 0);
    $expectedCash = $startBalance + $cashIncome - $cashExpense;
    
    renderHeader('Закрытие кассовой смены');
    ?>
    <div class="row justify-content-center">
        <div class="col-md-6">
            <div class="card">
                <div class="card-header">
                    <h5 class="card-title mb-0">📋 Закрытие кассовой смены</h5>
                </div>
                <div class="card-body">
                    <?php if (isset($_SESSION['error'])): ?>
                        <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                        <?php unset($_SESSION['error']); ?>
                    <?php endif; ?>
                    
                    <div class="mb-3">
                        <h6>📊 Статистика смены</h6>
                        <table class="table table-sm">
                            <tr>
                                <td>Начало смены:</td>
                                <td><strong><?= date('d.m.Y H:i', strtotime($activeShift['start_date'])) ?></strong></td>
                            </tr>
                            <tr>
                                <td>Начальный баланс:</td>
                                <td><strong><?= number_format($startBalance, 2) ?> ₽</strong></td>
                            </tr>
                            <tr class="table-success">
                                <td>Приход наличными:</td>
                                <td><strong>+<?= number_format($cashIncome, 2) ?> ₽</strong></td>
                            </tr>
                            <tr class="table-danger">
                                <td>Расход наличными:</td>
                                <td><strong>-<?= number_format($cashExpense, 2) ?> ₽</strong></td>
                            </tr>
                            <tr class="table-primary">
                                <td>Ожидаемая сумма:</td>
                                <td><strong><?= number_format($expectedCash, 2) ?> ₽</strong></td>
                            </tr>
                            <tr>
                                <td>Операций за смену:</td>
                                <td><strong><?= $shiftStats['operations_count'] ?></strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <form method="POST">
                        <div class="mb-3">
                            <label class="form-label">Фактическая сумма в кассе *</label>
                            <input type="number" step="0.01" class="form-control" name="actual_cash" required 
                                   value="<?= number_format($expectedCash, 2) ?>">
                            <small class="form-text text-muted">Пересчитайте фактическое количество наличных в кассе</small>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Комментарии (необязательно)</label>
                            <textarea class="form-control" name="comments" rows="3" placeholder="Причины расхождений..."></textarea>
                        </div>
                        <div class="d-grid gap-2">
                            <button type="submit" class="btn btn-warning">📋 Закрыть смену</button>
                            <a href="cash.php" class="btn btn-secondary">Отмена</a>
                        </div>
                    </form>
                </div>
            </div>
        </div>
    </div>
    <?php
    renderFooter();
}
?>