<?php
// ДОБАВЬТЕ БУФЕРИЗАЦИЮ В САМОМ НАЧАЛЕ
ob_start();
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'inc/layout.php';

// УБЕРИТЕ include ОТСЮДА - он будет ПОСЛЕ обработки POST
// include 'modals/order_modals.php'; 

requireAuth();
requirePermission('orders:edit');

global $pdo;
$orderId = (int)$_GET['id'];

// Получаем данные заказа с правильными полями
try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.full_name as client_name, c.phone as client_phone, c.email as client_email, 
               c.address as client_address, c.company_name, c.director, c.inn, c.age_group_id
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        $_SESSION['error'] = 'Заказ не найден';
        header('Location: orders.php');
        exit;
    }
} catch (Exception $e) {
    $_SESSION['error'] = 'Ошибка загрузки заказа: ' . $e->getMessage();
    header('Location: orders.php');
    exit;
}

// Запрещаем редактирование мастерам
if ($_SESSION['user_role_id'] == 4) {
    $_SESSION['error'] = 'Недостаточно прав для редактирования заказов';
    header('Location: orders.php');
    exit;
}

// Получаем данные для формы
try {
    // ... ваш код получения категорий, брендов и т.д. ...
    $categories = $pdo->query("SELECT id, name FROM device_categories ORDER BY name")->fetchAll();
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
    $models = $pdo->query("SELECT id, name FROM device_models ORDER BY name")->fetchAll();
    $managers = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id IN (1,2,3) ORDER BY full_name")->fetchAll();
    $masters = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id = 4 ORDER BY full_name")->fetchAll();
    $couriers = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id = 6 ORDER BY full_name")->fetchAll();
    $statuses = $pdo->query("SELECT id, name FROM statuses ORDER BY name")->fetchAll();
    $sources = $pdo->query("SELECT id, name FROM client_sources ORDER BY name")->fetchAll();
    $ageGroups = $pdo->query("SELECT id, name FROM age_groups ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Ошибка загрузки данных формы: ' . $e->getMessage();
    $categories = $brands = $models = $managers = $masters = $couriers = $statuses = $sources = $ageGroups = [];
}

// Функция для безопасного вывода значений
function safeValue($value) {
    return $value !== null ? htmlspecialchars($value) : '';
}

// ОБРАБОТКА СОХРАНЕНИЯ ИЗМЕНЕНИЙ - ДО ЛЮБОГО ВЫВОДА!
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Определяем модель устройства
        $customDeviceModel = '';
        $deviceModelId = null;
        
        if ($_POST['device_model_id'] === 'custom' && !empty($_POST['custom_device_model'])) {
            $customDeviceModel = trim($_POST['custom_device_model']);
        } elseif (!empty($_POST['device_model_id']) && $_POST['device_model_id'] !== 'custom') {
            $deviceModelId = (int)$_POST['device_model_id'];
        }

        $orderData = [
            'order_type' => $_POST['order_type'],
            'device_category_id' => (int)$_POST['device_category_id'],
            'brand_id' => (int)$_POST['brand_id'],
            'device_model_id' => $deviceModelId,
            'custom_device_model' => $customDeviceModel, // ИСПРАВЛЕНО
            'serial_number' => trim($_POST['serial_number']),
            'problem_description' => trim($_POST['problem_description']),
            'accessories' => trim($_POST['accessories']),
            'manager_id' => (int)$_POST['manager_id'],
            'master_id' => !empty($_POST['master_id']) ? (int)$_POST['master_id'] : null,
            'courier_id' => !empty($_POST['courier_id']) ? (int)$_POST['courier_id'] : null,
            'status_id' => (int)$_POST['status_id'],
            'source_id' => (int)$_POST['source_id'],
            'diagnostic_days' => (int)$_POST['diagnostic_days'],
            'warranty' => isset($_POST['warranty']) ? 1 : 0,
            'warranty_original_order_id' => !empty($_POST['warranty_original_order_id']) ? (int)$_POST['warranty_original_order_id'] : null,
            'warranty_reason' => trim($_POST['warranty_reason']),
            'delivery' => isset($_POST['delivery']) ? 1 : 0,
            'delivery_address' => trim($_POST['delivery_address'])
        ];

        // Обновляем заказ
        $sql = "UPDATE orders SET 
            order_type = ?, device_category_id = ?, brand_id = ?, device_model_id = ?, 
            custom_device_model = ?, serial_number = ?, problem_description = ?, accessories = ?, 
            manager_id = ?, master_id = ?, courier_id = ?, status_id = ?, source_id = ?, 
            diagnostic_days = ?, warranty = ?, warranty_original_order_id = ?, 
            warranty_reason = ?, delivery = ?, delivery_address = ?, updated_at = NOW() 
            WHERE id = ?";

        $stmt = $pdo->prepare($sql);
        $params = [
            $orderData['order_type'],
            $orderData['device_category_id'],
            $orderData['brand_id'],
            $orderData['device_model_id'],
            $orderData['custom_device_model'] ?? '',
            $orderData['serial_number'],
            $orderData['problem_description'],
            $orderData['accessories'],
            $orderData['manager_id'],
            $orderData['master_id'],
            $orderData['courier_id'],
            $orderData['status_id'],
            $orderData['source_id'],
            $orderData['diagnostic_days'],
            $orderData['warranty'],
            $orderData['warranty_original_order_id'],
            $orderData['warranty_reason'],
            $orderData['delivery'],
            $orderData['delivery_address'],
            $orderId
        ];

        // УБЕРИТЕ ОТЛАДОЧНУЮ ИНФОРМАЦИЮ - она вызывает вывод до header()
        // echo "<pre>Количество параметров в SQL: 20";
        // echo "Количество параметров в массиве: " . count($params) . "\n";
        // print_r($params);
        // echo "</pre>";
        
        if ($stmt->execute($params)) {
            // Обновляем данные клиента
            $clientSql = "UPDATE clients SET 
                full_name = ?, phone = ?, email = ?, address = ?, company_name = ?, 
                director = ?, inn = ?, age_group_id = ? 
                WHERE id = ?";
            
            $clientStmt = $pdo->prepare($clientSql);
            $clientStmt->execute([
                trim($_POST['client_full_name']),
                trim($_POST['client_phone']),
                trim($_POST['client_email']),
                trim($_POST['client_address']),
                trim($_POST['company_name']),
                trim($_POST['director']),
                trim($_POST['inn']),
                !empty($_POST['age_group_id']) ? (int)$_POST['age_group_id'] : null,
                $order['client_id']
            ]);
            
            // Добавляем комментарий об изменении
            addOrderComment($orderId, $_SESSION['user_id'], "Информация о заказе изменена");
            
            $_SESSION['success'] = 'Заказ успешно обновлен';
            header("Location: order_view.php?id=$orderId");
            exit; // ВАЖНО: немедленный выход
        } else {
            throw new Exception('Ошибка при обновлении заказа');
        }
        
    } catch (Exception $e) {
        $_SESSION['error'] = $e->getMessage();
    }
}

// ТОЛЬКО ПОСЛЕ ОБРАБОТКИ POST - РЕНДЕРИМ СТРАНИЦУ
renderHeader('Редактирование заказа #' . $order['id']);
?>

<div class="row">
    <div class="col-md-12">
        <div class="card">
            <div class="card-header d-flex justify-content-between align-items-center">
                <h5 class="card-title mb-0">Редактирование заказа #<?= $order['id'] ?></h5>
                <div>
                    <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-secondary btn-sm">← Назад</a>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#printModal">
                        🖨️ Печать
                    </button>
                </div>
            </div>
            <div class="card-body">
                <?php if (isset($_SESSION['error'])): ?>
                    <div class="alert alert-danger"><?= $_SESSION['error'] ?></div>
                    <?php unset($_SESSION['error']); ?>
                <?php endif; ?>

                <form method="POST" id="orderForm">
                    <div class="row">
                        <!-- Левая колонка -->
                        <div class="col-md-6">
                            <h6>Информация о клиенте</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Тип клиента</label>
                                <div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="client_type" id="client_type_individual" 
                                               value="individual" <?= empty($order['company_name']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="client_type_individual">Физическое лицо</label>
                                    </div>
                                    <div class="form-check form-check-inline">
                                        <input class="form-check-input" type="radio" name="client_type" id="client_type_legal" 
                                               value="legal" <?= !empty($order['company_name']) ? 'checked' : '' ?>>
                                        <label class="form-check-label" for="client_type_legal">Юридическое лицо</label>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Телефон клиента *</label>
                                <input type="text" class="form-control" name="client_phone" id="client_phone" 
                                       placeholder="+7 (999) 999-99-99" required
                                       value="<?= safeValue($order['client_phone']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">ФИО клиента *</label>
                                <input type="text" class="form-control" name="client_full_name" id="client_full_name" 
                                       required value="<?= safeValue($order['client_name']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Email</label>
                                <input type="email" class="form-control" name="client_email" id="client_email"
                                       value="<?= safeValue($order['client_email']) ?>">
                            </div>
                            
                            <div class="mb-3 company-fields" style="display: <?= !empty($order['company_name']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Название компании</label>
                                <input type="text" class="form-control" name="company_name" 
                                       value="<?= safeValue($order['company_name']) ?>">
                            </div>
                            
                            <div class="mb-3 company-fields" style="display: <?= !empty($order['company_name']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Директор</label>
                                <input type="text" class="form-control" name="director" 
                                       value="<?= safeValue($order['director']) ?>">
                            </div>
                            
                            <div class="mb-3 company-fields" style="display: <?= !empty($order['company_name']) ? 'block' : 'none' ?>;">
                                <label class="form-label">ИНН</label>
                                <input type="text" class="form-control" name="inn" 
                                       value="<?= safeValue($order['inn']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Адрес</label>
                                <textarea class="form-control" name="client_address" id="client_address" rows="2"><?= safeValue($order['client_address']) ?></textarea>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Возрастная группа</label>
                                <select class="form-select" name="age_group_id">
                                    <option value="">-- Не выбрано --</option>
                                    <?php foreach ($ageGroups as $group): ?>
                                    <option value="<?= $group['id'] ?>" <?= ($order['age_group_id'] ?? 0) == $group['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($group['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Источник заказа *</label>
                                <select class="form-select" name="source_id" required>
                                    <option value="">-- Выберите источник --</option>
                                    <?php foreach ($sources as $source): ?>
                                    <option value="<?= $source['id'] ?>" <?= ($order['source_id'] ?? 0) == $source['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($source['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <!-- Правая колонка -->
                        <div class="col-md-6">
                            <h6>Персонал</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Тип заказа *</label>
                                <select class="form-select" name="order_type" required>
                                    <option value="repair" <?= ($order['order_type'] ?? '') == 'repair' ? 'selected' : '' ?>>Ремонт</option>
                                    <option value="diagnostic" <?= ($order['order_type'] ?? '') == 'diagnostic' ? 'selected' : '' ?>>Диагностика</option>
                                    <option value="service" <?= ($order['order_type'] ?? '') == 'service' ? 'selected' : '' ?>>Обслуживание</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Менеджер *</label>
                                <select class="form-select" name="manager_id" required>
                                    <option value="">-- Выберите менеджера --</option>
                                    <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>" <?= ($order['manager_id'] ?? 0) == $manager['id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= $master['id'] ?>" <?= ($order['master_id'] ?? 0) == $master['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($master['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Статус *</label>
                                <select class="form-select" name="status_id" required>
                                    <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= ($order['status_id'] ?? 0) == $status['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($status['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>

                            <h6 class="mt-4">Информация об устройстве</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Категория устройства *</label>
                                <select class="form-select" name="device_category_id" required>
                                    <option value="">-- Выберите категорию --</option>
                                    <?php foreach ($categories as $category): ?>
                                    <option value="<?= $category['id'] ?>" <?= ($order['device_category_id'] ?? 0) == $category['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($category['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Бренд *</label>
                                <select class="form-select" name="brand_id" required>
                                    <option value="">-- Выберите бренд --</option>
                                    <?php foreach ($brands as $brand): ?>
                                    <option value="<?= $brand['id'] ?>" <?= ($order['brand_id'] ?? 0) == $brand['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Модель устройства *</label>
                                <select class="form-select" name="device_model_id" id="device_model_id" required>
                                    <option value="">-- Выберите модель --</option>
                                    <option value="custom" <?= empty($order['device_model_id']) ? 'selected' : '' ?>>-- Ввести свою модель --</option>
                                    <?php foreach ($models as $model): ?>
                                    <option value="<?= $model['id'] ?>" <?= ($order['device_model_id'] ?? 0) == $model['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($model['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3 custom-model-field" style="display: <?= empty($order['device_model_id']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Своя модель устройства *</label>
                                <input type="text" class="form-control" name="custom_device_model" 
                                       value="<?= safeValue($order['custom_device_model']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Серийный номер</label>
                                <input type="text" class="form-control" name="serial_number"
                                       value="<?= safeValue($order['serial_number']) ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комплектация</label>
                                <textarea class="form-control" name="accessories" rows="2"><?= safeValue($order['accessories']) ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Описание проблемы *</h6>
                            <textarea class="form-control" name="problem_description" rows="4" required><?= safeValue($order['problem_description']) ?></textarea>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Дополнительно</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Срок диагностики (дней)</label>
                                <input type="number" class="form-control" name="diagnostic_days" value="<?= $order['diagnostic_days'] ?? 3 ?>" min="1">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="warranty" id="warrantyCheck" <?= ($order['warranty'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="warrantyCheck">Гарантийный ремонт</label>
                            </div>
                            
                            <div class="mb-3 warranty-fields" style="display: <?= ($order['warranty'] ?? 0) ? 'block' : 'none' ?>;">
                                <label class="form-label">Оригинальный заказ</label>
                                <input type="number" class="form-control" name="warranty_original_order_id" 
                                       placeholder="Номер заказа" value="<?= safeValue($order['warranty_original_order_id']) ?>">
                            </div>
                            
                            <div class="mb-3 warranty-fields" style="display: <?= ($order['warranty'] ?? 0) ? 'block' : 'none' ?>;">
                                <label class="form-label">Причина гарантии</label>
                                <input type="text" class="form-control" name="warranty_reason" 
                                       placeholder="Причина гарантийного ремонта" value="<?= safeValue($order['warranty_reason']) ?>">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="delivery" id="deliveryCheck" <?= ($order['delivery'] ?? 0) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="deliveryCheck">Доставка</label>
                            </div>
                            
                            <div class="mb-3 delivery-fields" style="display: <?= ($order['delivery'] ?? 0) ? 'block' : 'none' ?>;">
                                <label class="form-label">Адрес доставки</label>
                                <textarea class="form-control" name="delivery_address" rows="2"><?= safeValue($order['delivery_address']) ?></textarea>
                            </div>
                            
                            <div class="mb-3 delivery-fields" style="display: <?= ($order['delivery'] ?? 0) ? 'block' : 'none' ?>;">
                                <label class="form-label">Курьер</label>
                                <select class="form-select" name="courier_id">
                                    <option value="">-- Не назначен --</option>
                                    <?php foreach ($couriers as $courier): ?>
                                    <option value="<?= $courier['id'] ?>" <?= ($order['courier_id'] ?? 0) == $courier['id'] ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($courier['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Комментарий</h6>
                            <textarea class="form-control" name="comment" rows="8" placeholder="Дополнительная информация..."></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
                        <button type="submit" class="btn btn-primary">Сохранить изменения</button>
                        <a href="order_view.php?id=<?= $orderId ?>" class="btn btn-secondary">Отмена</a>
                    </div>
                </form>
            </div>
        </div>
    </div>
</div>
<?php 
// ПОДКЛЮЧАЕМ МОДАЛЬНЫЕ ОКНА ПОСЛЕ ОСНОВНОГО КОНТЕНТА
include 'modals/order_modals.php'; 
?>
<script>
// Переключение типа клиента
document.querySelectorAll('input[name="client_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const isCompany = this.value === 'legal';
        document.querySelectorAll('.company-fields').forEach(field => {
            field.style.display = isCompany ? 'block' : 'none';
        });
    });
});

// Переключение выбора модели
document.getElementById('device_model_id').addEventListener('change', function() {
    const isCustom = this.value === 'custom';
    document.querySelector('.custom-model-field').style.display = isCustom ? 'block' : 'none';
    
    // Делаем поле обязательным только если выбрана своя модель
    const customModelInput = document.querySelector('input[name="custom_device_model"]');
    if (isCustom) {
        customModelInput.required = true;
    } else {
        customModelInput.required = false;
        customModelInput.value = ''; // Очищаем поле если выбрана модель из списка
    }
});

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
// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    const modelSelect = document.getElementById('device_model_id');
    if (modelSelect) {
        modelSelect.dispatchEvent(new Event('change'));
    }
});
</script>
<?php 
renderFooter(); 
?>