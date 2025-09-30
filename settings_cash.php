<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('settings:manage');

$tableConfig = [
    'table' => 'cash_sessions',
    'fields' => [
        'id' => 'int',
        'user_id' => 'int',
        'start_amount' => 'float',
        'end_amount' => 'float',
        'start_time' => 'string',
        'end_time' => 'string',
        'is_open' => 'bool'
    ],
    'id_field' => 'id'
];

// Обработка AJAX запросов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // Для открытия смены устанавливаем текущее время и пользователя
    if ($_POST['action'] === 'create') {
        $_POST['user_id'] = $_SESSION['user_id'];
        $_POST['start_time'] = date('Y-m-d H:i:s');
        $_POST['is_open'] = 1;
    }
    
    // Для закрытия смены устанавливаем время закрытия
    if ($_POST['action'] === 'update' && isset($_POST['close_session'])) {
        $_POST['end_time'] = date('Y-m-d H:i:s');
        $_POST['is_open'] = 0;
    }
    
    $response = CRUDController::handleRequest(
        $tableConfig['table'], 
        $tableConfig['fields'], 
        $tableConfig['id_field']
    );
    
    echo json_encode($response);
    exit;
}

// Получаем список кассовых смен
$sessions = CRUDController::getList($tableConfig['table'], '', [], 'start_time DESC');

// Получаем текущую открытую смену
$openSession = getFromTable('cash_sessions', '*', 'is_open = 1', [], 'LIMIT 1');
$hasOpenSession = !empty($openSession);

renderHeader('Управление кассовыми сменами');
?>

<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $hasOpenSession ? 'Текущая смена' : 'Открыть смену' ?></h5>
            </div>
            <div class="card-body">
                <?php if ($hasOpenSession): 
                    $session = $openSession[0];
                ?>
                    <div class="mb-3">
                        <label class="form-label">Смена открыта</label>
                        <p><?= date('d.m.Y H:i', strtotime($session['start_time'])) ?> пользователем ID: <?= $session['user_id'] ?></p>
                    </div>
                    
                    <div class="mb-3">
                        <label class="form-label">Начальная сумма</label>
                        <p><?= $session['start_amount'] ?> руб.</p>
                    </div>
                    
                    <form id="closeSessionForm">
                        <input type="hidden" name="action" value="update">
                        <input type="hidden" name="id" value="<?= $session['id'] ?>">
                        <input type="hidden" name="close_session" value="1">
                        
                        <div class="mb-3">
                            <label class="form-label">Конечная сумма *</label>
                            <input type="number" step="0.01" class="form-control" name="end_amount" required>
                        </div>
                        
                        <button type="submit" class="btn btn-danger">Закрыть смену</button>
                    </form>
                <?php else: ?>
                    <form id="openSessionForm">
                        <input type="hidden" name="action" value="create">
                        
                        <div class="mb-3">
                            <label class="form-label">Начальная сумма *</label>
                            <input type="number" step="0.01" class="form-control" name="start_amount" required>
                        </div>
                        
                        <button type="submit" class="btn btn-success">Открыть смену</button>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">История смен</h5>
            </div>
            <div class="card-body">
                <div class="table-responsive">
                    <table class="table table-striped">
                        <thead>
                            <tr>
                                <th>Дата открытия</th>
                                <th>Дата закрытия</th>
                                <th>Начальная сумма</th>
                                <th>Конечная сумма</th>
                                <th>Статус</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($sessions as $session): ?>
                            <tr>
                                <td><?= date('d.m.Y H:i', strtotime($session['start_time'])) ?></td>
                                <td><?= $session['end_time'] ? date('d.m.Y H:i', strtotime($session['end_time'])) : '-' ?></td>
                                <td><?= $session['start_amount'] ?> руб.</td>
                                <td><?= $session['end_amount'] ? $session['end_amount'] . ' руб.' : '-' ?></td>
                                <td>
                                    <?php if ($session['is_open']): ?>
                                        <span class="badge bg-success">Открыта</span>
                                    <?php else: ?>
                                        <span class="badge bg-secondary">Закрыта</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    <?php if ($hasOpenSession): ?>
    const closeForm = document.getElementById('closeSessionForm');
    closeForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(closeForm);
        
        fetch('settings_cash.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Ошибка: ' + data.message);
            }
        });
    });
    <?php else: ?>
    const openForm = document.getElementById('openSessionForm');
    openForm.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(openForm);
        
        fetch('settings_cash.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
                location.reload();
            } else {
                alert('Ошибка: ' + data.message);
            }
        });
    });
    <?php endif; ?>
});
</script>

<?php renderFooter(); ?>