<?php
require_once 'inc/layout.php';

requireAuth();

$action = $_GET['action'] ?? '';
$clientId = $_GET['id'] ?? 0;

if ($action === 'create') {
    requirePermission('clients:create');
    $client = [
        'type' => 'individual',
        'full_name' => '',
        'is_company' => 0,
        'company_name' => '',
        'phone' => '',
        'email' => '',
        'address' => '',
        'age_group_id' => '',
        'source_id' => '',
        'notes' => '',
        'director' => '',
        'inn' => '',
        'bank_account' => ''
    ];
    $title = 'Добавить клиента';
} else {
    requirePermission('clients:edit');
    $client = getFromTable('clients', '*', 'id = ?', [$clientId]);
    if (!$client) {
        $_SESSION['error'] = 'Клиент не найден';
        header('Location: clients.php');
        exit;
    }
    $client = $client[0];
    $title = 'Редактировать клиента';
}

// Получаем справочники
try {
    $sources = $pdo->query("SELECT * FROM client_sources")->fetchAll();
} catch (PDOException $e) {
    $sources = [];
    error_log("Таблица client_sources не найдена: " . $e->getMessage());
}

try {
    $ageGroups = $pdo->query("SELECT * FROM age_groups")->fetchAll();
} catch (PDOException $e) {
    $ageGroups = [];
    error_log("Таблица age_groups не найдена: " . $e->getMessage());
}

// Обработка формы
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = [
        'type' => $_POST['type'],
        'full_name' => trim($_POST['full_name']),
        'is_company' => $_POST['type'] === 'legal' ? 1 : 0,
        'company_name' => trim($_POST['company_name']),
        'phone' => trim($_POST['phone']),
        'email' => trim($_POST['email']),
        'address' => trim($_POST['address']),
        'age_group_id' => !empty($_POST['age_group_id']) ? (int)$_POST['age_group_id'] : null,
        'source_id' => !empty($_POST['source_id']) ? (int)$_POST['source_id'] : null,
        'notes' => trim($_POST['notes']),
        'director' => trim($_POST['director']),
        'inn' => trim($_POST['inn']),
        'bank_account' => trim($_POST['bank_account'])
    ];
    
    try {
        if ($action === 'create') {
            $data['created_at'] = date('Y-m-d H:i:s');
            saveToTable('clients', $data);
            $_SESSION['success'] = 'Клиент успешно создан';
        } else {
            saveToTable('clients', $data, 'id = ?', [$clientId]);
            $_SESSION['success'] = 'Данные клиента обновлены';
        }
        
        header('Location: clients.php');
        exit;
    } catch (Exception $e) {
        $_SESSION['error'] = 'Ошибка при сохранении: ' . $e->getMessage();
    }
}

renderHeader($title);
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0"><?= $title ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <form method="POST">
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Тип клиента *</label>
                                <select class="form-select" name="type" required onchange="toggleCompanyFields()">
                                    <option value="individual" <?= $client['type'] === 'individual' ? 'selected' : '' ?>>Физическое лицо</option>
                                    <option value="legal" <?= $client['type'] === 'legal' ? 'selected' : '' ?>>Юридическое лицо</option>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div id="individualFields">
                        <div class="mb-3">
                            <label class="form-label">ФИО *</label>
                            <input type="text" class="form-control" name="full_name" 
                                   value="<?= htmlspecialchars($client['full_name']) ?>" required>
                        </div>
                    </div>

                    <div id="companyFields" style="display: <?= $client['type'] === 'legal' ? 'block' : 'none' ?>;">
                        <div class="mb-3">
                            <label class="form-label">Название компании <?= $client['type'] === 'legal' ? '*' : '' ?></label>
                            <input type="text" class="form-control" name="company_name" 
                                   value="<?= htmlspecialchars($client['company_name']) ?>" 
                                   <?= $client['type'] === 'legal' ? 'required' : '' ?>>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Директор</label>
                            <input type="text" class="form-control" name="director" 
                                   value="<?= htmlspecialchars($client['director']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">ИНН</label>
                            <input type="text" class="form-control" name="inn" 
                                   value="<?= htmlspecialchars($client['inn']) ?>">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Расчетный счет</label>
                            <input type="text" class="form-control" name="bank_account" 
                                   value="<?= htmlspecialchars($client['bank_account']) ?>">
                        </div>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Телефон</label>
                                <input type="tel" class="form-control" name="phone" 
                                       value="<?= htmlspecialchars($client['phone']) ?>">
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" 
                                       value="<?= htmlspecialchars($variable ?? '') ?>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Адрес</label>
                        <textarea class="form-control" name="address" rows="2"><?= htmlspecialchars($client['address']) ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Возрастная группа</label>
                                <select name="age_group_id" class="form-select">
                                    <option value="">Не указана</option>
                                    <?php foreach ($ageGroups as $group): ?>
                                        <option value="<?= $group['id'] ?>" 
                                                <?= ($client['age_group_id'] == $group['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Источник клиента</label>
                                <select name="source_id" class="form-select">
                                    <option value="">Не указан</option>
                                    <?php foreach ($sources as $source): ?>
                                        <option value="<?= $source['id'] ?>" 
                                                <?= ($client['source_id'] == $source['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($source['name'] ?? '') ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Примечания</label>
                        <textarea class="form-control" name="notes" rows="3"><?= htmlspecialchars($client['notes']) ?></textarea>
                    </div>

                    <div class="d-flex gap-2">
                        <button type="submit" class="btn btn-primary">Сохранить</button>
                        <a href="clients.php" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
function toggleCompanyFields() {
    const type = document.querySelector('select[name="type"]').value;
    const individualFields = document.getElementById('individualFields');
    const companyFields = document.getElementById('companyFields');
    
    if (type === 'legal') {
        individualFields.style.display = 'none';
        companyFields.style.display = 'block';
        document.querySelector('input[name="company_name"]').required = true;
        document.querySelector('input[name="full_name"]').required = false;
    } else {
        individualFields.style.display = 'block';
        companyFields.style.display = 'none';
        document.querySelector('input[name="full_name"]').required = true;
        document.querySelector('input[name="company_name"]').required = false;
    }
}

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', toggleCompanyFields);
</script>

<?php renderFooter(); ?>