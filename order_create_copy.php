<?php
// Включим максимальное отображение ошибок
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'inc/layout.php';

requireAuth();
requirePermission('orders:create');

global $pdo;

// Логируем начало процесса
error_log("=== ORDER CREATE PROCESS STARTED ===");

// Получаем следующий номер заказа
$nextOrderNumber = getNextOrderNumber();
error_log("Next order number: " . $nextOrderNumber);

// Получаем данные для формы (без is_active чтобы избежать ошибок)
try {
    // Клиенты
    $clients = $pdo->query("SELECT id, full_name, phone FROM clients ORDER BY full_name")->fetchAll();
    
    // Категории устройств
    $categories = $pdo->query("SELECT id, name FROM device_categories ORDER BY name")->fetchAll();
    
    // Бренды
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
    
    // Менеджеры (роли 1,2,3) - ИСПРАВЛЕНО: full_name вместо name
    $managers = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id IN (1,2,3) ORDER BY full_name")->fetchAll();
    
    // Мастера (роль 4) - ИСПРАВЛЕНО: full_name вместо name
    $masters = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id = 4 ORDER BY full_name")->fetchAll();
    
    // Курьеры (роль 6) - ИСПРАВЛЕНО: full_name вместо name
    $couriers = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id = 6 ORDER BY full_name")->fetchAll();
    
    // Статусы
    $statuses = $pdo->query("SELECT id, name FROM statuses ORDER BY name")->fetchAll();
    
    // Источники
    $sources = $pdo->query("SELECT id, name FROM client_sources ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    error_log("Error loading form data: " . $e->getMessage());
    $_SESSION['error'] = 'Ошибка загрузки данных формы: ' . $e->getMessage();
    $clients = $categories = $brands = $managers = $masters = $couriers = $statuses = $sources = [];
}

// Обработка создания заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    error_log("POST request received");
    error_log("POST data: " . print_r($_POST, true));
    
    try {
        // Валидация обязательных полей
        $required = ['client_id', 'device_category_id', 'brand_id', 'device_model', 'problem_description', 'manager_id', 'status_id', 'source_id'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Заполните обязательное поле: " . $field);
            }
        }

        $orderData = [
            'order_type' => $_POST['order_type'] ?? 'repair',
            'client_id' => (int)$_POST['client_id'],
            'device_category_id' => (int)$_POST['device_category_id'],
            'brand_id' => (int)$_POST['brand_id'],
            'device_model' => trim($_POST['device_model']),
            'serial_number' => trim($_POST['serial_number'] ?? ''),
            'problem_description' => trim($_POST['problem_description']),
            'accessories' => trim($_POST['accessories'] ?? ''),
            'manager_id' => (int)$_POST['manager_id'],
            'master_id' => !empty($_POST['master_id']) ? (int)$_POST['master_id'] : null,
            'courier_id' => !empty($_POST['courier_id']) ? (int)$_POST['courier_id'] : null,
            'warranty' => isset($_POST['warranty']) ? 1 : 0,
            'warranty_original_order_id' => !empty($_POST['warranty_original_order_id']) ? (int)$_POST['warranty_original_order_id'] : null,
            'warranty_reason' => trim($_POST['warranty_reason'] ?? ''),
            'delivery' => isset($_POST['delivery']) ? 1 : 0,
            'delivery_address' => trim($_POST['delivery_address'] ?? ''),
            'status_id' => (int)$_POST['status_id'],
            'source_id' => (int)$_POST['source_id'],
            'diagnostic_days' => (int)($_POST['diagnostic_days'] ?? 3)
        ];

        // Устанавливаем deadline диагностики
        if ($orderData['diagnostic_days'] > 0) {
            $orderData['diagnostic_deadline'] = date('Y-m-d', strtotime("+{$orderData['diagnostic_days']} days"));
        }

        error_log("Processed order data: " . print_r($orderData, true));

        // Создаем заказ
        $orderId = createOrder($orderData);
        
        if ($orderId) {
            // Добавляем комментарий о создании
            addOrderComment($orderId, $_SESSION['user_id'], "Заказ создан");
            
            $_SESSION['success'] = 'Заказ успешно создан';
            error_log("Redirecting to order view: $orderId");
            header("Location: order_view.php?id=$orderId");
            exit;
        } else {
            throw new Exception('Ошибка при создании заказа (check error logs)');
        }
        
    } catch (Exception $e) {
        error_log("Exception in POST handler: " . $e->getMessage());
        $_SESSION['error'] = $e->getMessage();
    }
}

renderHeader('Создание нового заказа');
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Создание заказа #<?= $nextOrderNumber ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                <?php unset($_SESSION['error']); ?>
                <?php endif; ?>
                
                <form method="POST" id="orderForm">
                    <div class="row">
                        <!-- Левая колонка -->
                        <div class="col-md-6">
                            <h6>Информация о клиенте</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Тип заказа *</label>
                                <select class="form-select" name="order_type" required>
                                    <option value="repair">Ремонт</option>
                                    <option value="diagnostic">Диагностика</option>
                                    <option value="service">Обслуживание</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Клиент *</label>
                                <select class="form-select" name="client_id" required>
                                    <option value="">-- Выберите клиента --</option>
                                    <?php foreach ($clients as $client): ?>
                                    <option value="<?= $client['id'] ?>">
                                        <?= htmlspecialchars($client['full_name']) ?> 
                                        <?= !empty($client['phone']) ? ' - ' . $client['phone'] : '' ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                                <a href="client_create.php" class="btn btn-sm btn-outline-primary mt-1">+ Новый клиент</a>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Источник заказа *</label>
                                <select class="form-select" name="source_id" required>
                                    <option value="">-- Выберите источник --</option>
                                    <?php foreach ($sources as $source): ?>
                                    <option value="<?= $source['id'] ?>"><?= htmlspecialchars($source['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <h6 class="mt-4">Информация об устройстве</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Категория устройства *</label>
                                <select class="form-select" name="device_category_id" required>
                                    <option value="">-- Выберите категорию --</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>"><?= htmlspecialchars($category['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Бренд *</label>
                                <select class="form-select" name="brand_id" required>
                                    <option value="">-- Выберите бренд --</option>
                                    <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>"><?= htmlspecialchars($brand['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Модель устройства *</label>
                                <input type="text" class="form-control" name="device_model" required>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Серийный номер</label>
                                <input type="text" class="form-control" name="serial_number">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комплектация</label>
                                <textarea class="form-control" name="accessories" rows="2"></textarea>
                            </div>
                        </div>

                        <!-- Правая колонка -->
                        <div class="col-md-6">
                            <h6>Персонал</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Менеджер *</label>
                                <select class="form-select" name="manager_id" required>
                                    <option value="">-- Выберите менеджера --</option>
                                    <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>" <?= $manager['id'] == $_SESSION['user_id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($manager['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Мастер</label>
                                <select class="form-select" name="master_id">
                                    <option value="">-- Не назначен --</option>
                                    <?php foreach ($masters as $master): ?>
                                    <option value="<?= $master['id'] ?>"><?= htmlspecialchars($master['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Статус *</label>
                                <select class="form-select" name="status_id" required>
                                    <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= $status['id'] == 1 ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <h6 class="mt-4">Дополнительно</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Срок диагностики (дней)</label>
                                <input type="number" class="form-control" name="diagnostic_days" value="3" min="1">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="warranty" id="warrantyCheck">
                                <label class="form-check-label" for="warrantyCheck">Гарантийный ремонт</label>
                            </div>
                            
                            <div class="mb-3 warranty-fields" style="display: none;">
                                <label class="form-label">Оригинальный заказ</label>
                                <input type="number" class="form-control" name="warranty_original_order_id" placeholder="Номер заказа">
                            </div>
                            
                            <div class="mb-3 warranty-fields" style="display: none;">
                                <label class="form-label">Причина гарантии</label>
                                <input type="text" class="form-control" name="warranty_reason" placeholder="Причина гарантийного ремонта">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="delivery" id="deliveryCheck">
                                <label class="form-check-label" for="deliveryCheck">Доставка</label>
                            </div>
                            
                            <div class="mb-3 delivery-fields" style="display: none;">
                                <label class="form-label">Адрес доставки</label>
                                <textarea class="form-control" name="delivery_address" rows="2"></textarea>
                            </div>
                            
                            <div class="mb-3 delivery-fields" style="display: none;">
                                <label class="form-label">Курьер</label>
                                <select class="form-select" name="courier_id">
                                    <option value="">-- Не назначен --</option>
                                    <?php foreach ($couriers as $courier): ?>
                                    <option value="<?= $courier['id'] ?>"><?= htmlspecialchars($courier['name']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Описание проблемы *</h6>
                            <textarea class="form-control" name="problem_description" rows="4" required></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Создать заказ</button>
                        <a href="orders.php" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Показ/скрытие полей гарантии и доставки
document.getElementById('warrantyCheck').addEventListener('change', function() {
    document.querySelectorAll('.warranty-fields').forEach(function(el) {
        el.style.display = this.checked ? 'block' : 'none';
    }.bind(this));
});

document.getElementById('deliveryCheck').addEventListener('change', function() {
    document.querySelectorAll('.delivery-fields').forEach(function(el) {
        el.style.display = this.checked ? 'block' : 'none';
    }.bind(this));
});
</script>

<?php renderFooter(); ?>