<?php
require_once 'inc/layout.php';

requireAuth();
requirePermission('settings:manage');

// Загружаем данные компании (предполагаем, что у нас только одна запись)
$company = getFromTable('company_settings', '*', '', [], 'LIMIT 1');
if ($company) {
    $company = $company[0];
} else {
    // Создаем пустую запись, если данных нет
    $company = [
        'id' => 0,
        'name' => '',
        'address' => '',
        'inn' => '',
        'kpp' => '',
        'bank_name' => '',
        'bank_account' => '',
        'corr_account' => '',
        'bik' => '',
        'phone' => '',
        'email' => '',
        'director' => ''
    ];
}

// Обработка сохранения реквизитов
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'save_company') {
    header('Content-Type: application/json');
    
    try {
        $data = [
            'name' => $_POST['name'] ?? '',
            'address' => $_POST['address'] ?? '',
            'inn' => $_POST['inn'] ?? '',
            'kpp' => $_POST['kpp'] ?? '',
            'bank_name' => $_POST['bank_name'] ?? '',
            'bank_account' => $_POST['bank_account'] ?? '',
            'corr_account' => $_POST['corr_account'] ?? '',
            'bik' => $_POST['bik'] ?? '',
            'phone' => $_POST['phone'] ?? '',
            'email' => $_POST['email'] ?? '',
            'director' => $_POST['director'] ?? ''
        ];
        
        if ($company['id']) {
            // Обновляем существующую запись
            saveToTable('companies', $data, 'id = ?', [$company['id']]);
        } else {
            // Создаем новую запись
            saveToTable('companies', $data);
        }
        
        echo json_encode(['success' => true, 'message' => 'Данные компании сохранены']);
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Ошибка: ' . $e->getMessage()]);
    }
    exit;
}

renderHeader('Реквизиты компании');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Реквизиты компании</h5>
            </div>
            <div class="card-body">
                <form id="companyForm">
                    <input type="hidden" name="action" value="save_company">
                    
                    <div class="row">
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Наименование компании *</label>
                                <input type="text" class="form-control" name="name" value="<?= safe($company['name']) ?>" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Адрес</label>
                                <input type="text" class="form-control" name="address" value="<?= safe($company['address']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ИНН</label>
                                <input type="text" class="form-control" name="inn" value="<?= safe($company['inn']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">КПП</label>
                                <input type="text" class="form-control" name="kpp" value="<?= safe($company['kpp']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Директор</label>
                                <input type="text" class="form-control" name="director" value="<?= safe($company['director']) ?>">
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <div class="mb-3">
                                <label class="form-label">Банк</label>
                                <input type="text" class="form-control" name="bank_name" value="<?= safe($company['bank_name']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Расчетный счет</label>
                                <input type="text" class="form-control" name="bank_account" value="<?= safe($company['bank_account']) ?>">
                            </div>
                            

                            
                            <div class="mb-3">
                                <label class="form-label">БИК</label>
                                <input type="text" class="form-control" name="bik" value="<?= safe($company['bik']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Телефон</label>
                                <input type="text" class="form-control" name="phone" value="<?= safe($company['phone']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="email" value="<?= safe($company['email']) ?>">
                            </div>
                        </div>
                    </div>
                    
                    <button type="submit" class="btn btn-primary">Сохранить</button>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const form = document.getElementById('companyForm');
    
    form.addEventListener('submit', function(e) {
        e.preventDefault();
        
        const formData = new FormData(form);
        
        fetch('settings_company.php', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                alert(data.message);
            } else {
                alert('Ошибка: ' + data.message);
            }
        });
    });
});
</script>

<?php renderFooter(); ?>