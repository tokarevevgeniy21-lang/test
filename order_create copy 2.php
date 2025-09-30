<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'inc/layout.php';
// Функция для получения следующего ID заказа
function getNextOrderId() {
    global $pdo;
    try {
        $stmt = $pdo->query("SELECT MAX(id) as max_id FROM orders");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        return ($result['max_id'] ?? 0) + 1;
    } catch (Exception $e) {
        return 1;
    }
}

global $pdo;

// Получаем следующий номер заказа (будет равен ID)
$nextOrderId = getNextOrderId();

// Получаем данные для формы
try {
    // Категории устройств
    $categories = $pdo->query("SELECT id, name FROM device_categories ORDER BY name")->fetchAll();
    
    // Бренды
    $brands = $pdo->query("SELECT id, name FROM brands ORDER BY name")->fetchAll();
    
    // Модели устройств (для выпадающего списка)
    $models = $pdo->query("SELECT id, name FROM device_models ORDER BY name")->fetchAll();
    
    // Менеджеры
    $managers = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id IN (1,2,3) ORDER BY full_name")->fetchAll();
    
    // Мастера
    $masters = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id = 4 ORDER BY full_name")->fetchAll();
    
    // Курьеры
    $couriers = $pdo->query("SELECT id, full_name as name FROM users WHERE role_id = 6 ORDER BY full_name")->fetchAll();
    
    // Статусы
    $statuses = $pdo->query("SELECT id, name FROM statuses ORDER BY name")->fetchAll();
    
    // Источники
    $sources = $pdo->query("SELECT id, name FROM client_sources ORDER BY name")->fetchAll();
    
    // Возрастные группы
    $ageGroups = $pdo->query("SELECT id, name FROM age_groups ORDER BY name")->fetchAll();
    
} catch (PDOException $e) {
    $_SESSION['error'] = 'Ошибка загрузки данных формы: ' . $e->getMessage();
    $categories = $brands = $models = $managers = $masters = $couriers = $statuses = $sources = $ageGroups = [];
}

// Обработка создания заказа
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Валидация обязательных полей
        $required = ['client_phone', 'client_full_name', 'device_category_id', 'brand_id', 'problem_description', 'manager_id', 'status_id', 'source_id'];
        foreach ($required as $field) {
            if (empty($_POST[$field])) {
                throw new Exception("Заполните обязательное поле: " . $field);
            }
        }

        // Обрабатываем данные клиента
        $clientId = handleClientData($_POST);
        
        if (!$clientId) {
            throw new Exception("Ошибка обработки данных клиента");
        }

        // Определяем модель устройства
        $deviceModelId = null;
        $customDeviceModel = '';
        
        if (!empty($_POST['device_model_id'])) {
            if ($_POST['device_model_id'] === 'custom') {
                // Своя модель - используем custom_device_model
                $customDeviceModel = trim($_POST['custom_device_model'] ?? '');
            } else {
                // Выбрана модель из списка
                $deviceModelId = (int)$_POST['device_model_id'];
                $customDeviceModel = ''; // Очищаем поле своей модели
            }
        }

        // Создаем заказ с правильными полями
        $sql = "INSERT INTO orders (
            client_id, device_category_id, brand_id, device_model_id, custom_device_model,
            problem_description, manager_id, status_id, source_id, created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";
        
        $stmt = $pdo->prepare($sql);
        $params = [
            $clientId,
            (int)$_POST['device_category_id'],
            (int)$_POST['brand_id'],
            $deviceModelId,        // ID модели или NULL
            $customDeviceModel,    // Своя модель или пустая строка
            trim($_POST['problem_description']),
            (int)$_POST['manager_id'],
            (int)$_POST['status_id'],
            (int)$_POST['source_id']
        ];
        
        echo '<p>SQL заказа: ' . $sql . '</p>';
        echo '<p>Параметры: ' . implode(', ', $params) . '</p>';
        
        $result = $stmt->execute($params);
        
        if ($result) {
            $orderId = $pdo->lastInsertId();
            
            $_SESSION['success'] = 'Заказ успешно создан. ID заказа: ' . $orderId;
            $_SESSION['new_order_id'] = $orderId;
            
            header("Location: order_create.php?success=1&id=" . $orderId);
            exit;
        } else {
            $errorInfo = $stmt->errorInfo();
            throw new Exception('Ошибка создания заказа: ' . $errorInfo[2]);
        }
        
    } catch (Exception $e) {
        echo '<p>Поймано исключение: ' . $e->getMessage() . '</p>';
        $_SESSION['error'] = $e->getMessage();
    }
}
// Если заказ успешно создан, показываем кнопки
$success = isset($_GET['success']) && isset($_SESSION['new_order_id']);

renderHeader('Создание нового заказа');
?>

<div class="row">
    <div class="col-md-12">
        <?php if ($success): ?>
        <div class="alert alert-success">
            <h5>Заказ успешно создан!</h5>
            <p>ID заказа: <?= $_SESSION['new_order_id'] ?></p>
            <div class="mt-3">
                <a href="order_view.php?id=<?= $_SESSION['new_order_id'] ?>" class="btn btn-primary">
                    <i class="bi bi-eye"></i> Просмотреть заказ
                </a>
                <a href="order_receipt.php?id=<?= $_SESSION['new_order_id'] ?>" class="btn btn-success" target="_blank">
                    <i class="bi bi-receipt"></i> Квитанция о приёме
                </a>
                <a href="order_create.php" class="btn btn-secondary">
                    <i class="bi bi-plus"></i> Создать новый заказ
                </a>
            </div>
        </div>
        <?php unset($_SESSION['new_order_id']); ?>
        <?php endif; ?>
        
        <div class="card">
            <div class="card-header">
                <h5 class="card-title mb-0">Создание заказа #<?= $nextOrderId ?></h5>
            </div>
            <div class="card-body">
                <?php if (!empty($_SESSION['error'])): ?>
                <div class="alert alert-danger">
                    <strong>Ошибка:</strong> <?= $_SESSION['error'] ?>
                </div>
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
            <input class="form-check-input" type="radio" name="client_type" id="client_type_individual" value="individual" checked>
            <label class="form-check-label" for="client_type_individual">Физическое лицо</label>
        </div>
        <div class="form-check form-check-inline">
            <input class="form-check-input" type="radio" name="client_type" id="client_type_legal" value="legal">
            <label class="form-check-label" for="client_type_legal">Юридическое лицо</label>
        </div>
    </div>
</div>
                            
                            <div class="mb-3">
                                <label class="form-label">Телефон клиента *</label>
                                <input type="text" class="form-control" name="client_phone" id="client_phone" 
                                       placeholder="+7 (999) 999-99-99" required
                                       value="<?= isset($_POST['client_phone']) ? htmlspecialchars($_POST['client_phone']) : '' ?>">
                                <small class="form-text text-muted">Начните ввод телефона для поиска клиента</small>
                            </div>
                            
                            <div id="clientInfo">
                                <div class="mb-3">
                                    <label class="form-label">ФИО клиента *</label>
                                    <input type="text" class="form-control" name="client_full_name" id="client_full_name" 
                                           required value="<?= isset($_POST['client_full_name']) ? htmlspecialchars($_POST['client_full_name']) : '' ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Email</label>
                                    <input type="email" class="form-control" name="client_email" id="client_email"
                                           value="<?= isset($_POST['client_email']) ? htmlspecialchars($_POST['client_email']) : '' ?>">
                                </div>
                                
                                <div class="mb-3 company-fields" style="display: none;">
                                    <label class="form-label">Название компании</label>
                                    <input type="text" class="form-control" name="company_name" 
                                           value="<?= isset($_POST['company_name']) ? htmlspecialchars($_POST['company_name']) : '' ?>">
                                </div>
                                
                                <div class="mb-3 company-fields" style="display: none;">
                                    <label class="form-label">Директор</label>
                                    <input type="text" class="form-control" name="director" 
                                           value="<?= isset($_POST['director']) ? htmlspecialchars($_POST['director']) : '' ?>">
                                </div>
                                
                                <div class="mb-3 company-fields" style="display: none;">
                                    <label class="form-label">ИНН</label>
                                    <input type="text" class="form-control" name="inn" 
                                           value="<?= isset($_POST['inn']) ? htmlspecialchars($_POST['inn']) : '' ?>">
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Адрес</label>
                                    <textarea class="form-control" name="client_address" id="client_address" rows="2"><?= isset($_POST['client_address']) ? htmlspecialchars($_POST['client_address']) : '' ?></textarea>
                                </div>
                                
                                <div class="mb-3">
                                    <label class="form-label">Возрастная группа</label>
                                    <select class="form-select" name="age_group_id">
                                        <option value="">-- Не выбрано --</option>
                                        <?php foreach ($ageGroups as $group): ?>
                                        <option value="<?= $group['id'] ?>" <?= (isset($_POST['age_group_id']) && $_POST['age_group_id'] == $group['id']) ? 'selected' : '' ?>>
                                            <?= htmlspecialchars($group['name']) ?>
                                        </option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Источник заказа *</label>
                                <select class="form-select" name="source_id" required>
                                    <option value="">-- Выберите источник --</option>
                                    <?php foreach ($sources as $source): ?>
                                    <option value="<?= $source['id'] ?>" <?= (isset($_POST['source_id']) && $_POST['source_id'] == $source['id']) ? 'selected' : '' ?>>
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
                                    <option value="repair" <?= (isset($_POST['order_type']) && $_POST['order_type'] == 'repair') ? 'selected' : '' ?>>Ремонт</option>
                                    <option value="diagnostic" <?= (isset($_POST['order_type']) && $_POST['order_type'] == 'diagnostic') ? 'selected' : '' ?>>Диагностика</option>
                                    <option value="service" <?= (isset($_POST['order_type']) && $_POST['order_type'] == 'service') ? 'selected' : '' ?>>Обслуживание</option>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Менеджер *</label>
                                <select class="form-select" name="manager_id" required>
                                    <option value="">-- Выберите менеджера --</option>
                                    <?php foreach ($managers as $manager): ?>
                                    <option value="<?= $manager['id'] ?>" <?= (isset($_POST['manager_id']) && $_POST['manager_id'] == $manager['id']) || $manager['id'] == $_SESSION['user_id'] ? 'selected' : '' ?>>
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
                                    <option value="<?= $master['id'] ?>" <?= (isset($_POST['master_id']) && $_POST['master_id'] == $master['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($master['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Статус *</label>
                                <select class="form-select" name="status_id" required>
                                    <?php foreach ($statuses as $status): ?>
                                    <option value="<?= $status['id'] ?>" <?= (isset($_POST['status_id']) && $_POST['status_id'] == $status['id']) || $status['id'] == 1 ? 'selected' : '' ?>>
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
                                    <option value="<?= $category['id'] ?>" <?= (isset($_POST['device_category_id']) && $_POST['device_category_id'] == $category['id']) ? 'selected' : '' ?>>
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
                                    <option value="<?= $brand['id'] ?>" <?= (isset($_POST['brand_id']) && $_POST['brand_id'] == $brand['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($brand['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Модель устройства *</label>
                                <select class="form-select" name="device_model_id" id="device_model_id" required>
                                    <option value="">-- Выберите модель --</option>
                                    <option value="custom">-- Ввести свою модель --</option>
                                    <?php foreach ($models as $model): ?>
                                    <option value="<?= $model['id'] ?>" <?= (isset($_POST['device_model_id']) && $_POST['device_model_id'] == $model['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($model['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <div class="mb-3 custom-model-field" style="display: none;">
                                <label class="form-label">Своя модель устройства *</label>
                                <input type="text" class="form-control" name="custom_device_model" 
                                       value="<?= isset($_POST['custom_device_model']) ? htmlspecialchars($_POST['custom_device_model']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Серийный номер</label>
                                <input type="text" class="form-control" name="serial_number"
                                       value="<?= isset($_POST['serial_number']) ? htmlspecialchars($_POST['serial_number']) : '' ?>">
                            </div>
                            
                            <div class="mb-3">
                                <label class="form-label">Комплектация</label>
                                <textarea class="form-control" name="accessories" rows="2"><?= isset($_POST['accessories']) ? htmlspecialchars($_POST['accessories']) : '' ?></textarea>
                            </div>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-12">
                            <h6>Описание проблемы *</h6>
                            <textarea class="form-control" name="problem_description" rows="4" required><?= isset($_POST['problem_description']) ? htmlspecialchars($_POST['problem_description']) : '' ?></textarea>
                        </div>
                    </div>

                    <div class="row mt-3">
                        <div class="col-md-6">
                            <h6>Дополнительно</h6>
                            
                            <div class="mb-3">
                                <label class="form-label">Срок диагностики (дней)</label>
                                <input type="number" class="form-control" name="diagnostic_days" value="<?= isset($_POST['diagnostic_days']) ? htmlspecialchars($_POST['diagnostic_days']) : '3' ?>" min="1">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="warranty" id="warrantyCheck" <?= isset($_POST['warranty']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="warrantyCheck">Гарантийный ремонт</label>
                            </div>
                            
                            <div class="mb-3 warranty-fields" style="display: <?= isset($_POST['warranty']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Оригинальный заказ</label>
                                <input type="number" class="form-control" name="warranty_original_order_id" 
                                       placeholder="Номер заказа" value="<?= isset($_POST['warranty_original_order_id']) ? htmlspecialchars($_POST['warranty_original_order_id']) : '' ?>">
                            </div>
                            
                            <div class="mb-3 warranty-fields" style="display: <?= isset($_POST['warranty']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Причина гарантии</label>
                                <input type="text" class="form-control" name="warranty_reason" 
                                       placeholder="Причина гарантийного ремонта" value="<?= isset($_POST['warranty_reason']) ? htmlspecialchars($_POST['warranty_reason']) : '' ?>">
                            </div>
                            
                            <div class="mb-3 form-check">
                                <input type="checkbox" class="form-check-input" name="delivery" id="deliveryCheck" <?= isset($_POST['delivery']) ? 'checked' : '' ?>>
                                <label class="form-check-label" for="deliveryCheck">Доставка</label>
                            </div>
                            
                            <div class="mb-3 delivery-fields" style="display: <?= isset($_POST['delivery']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Адрес доставки</label>
                                <textarea class="form-control" name="delivery_address" rows="2"><?= isset($_POST['delivery_address']) ? htmlspecialchars($_POST['delivery_address']) : '' ?></textarea>
                            </div>
                            
                            <div class="mb-3 delivery-fields" style="display: <?= isset($_POST['delivery']) ? 'block' : 'none' ?>;">
                                <label class="form-label">Курьер</label>
                                <select class="form-select" name="courier_id">
                                    <option value="">-- Не назначен --</option>
                                    <?php foreach ($couriers as $courier): ?>
                                    <option value="<?= $courier['id'] ?>" <?= (isset($_POST['courier_id']) && $_POST['courier_id'] == $courier['id']) ? 'selected' : '' ?>>
                                        <?= htmlspecialchars($courier['name']) ?>
                                    </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="col-md-6">
                            <h6>Комментарий</h6>
                            <textarea class="form-control" name="comment" rows="8" placeholder="Дополнительная информация..."><?= isset($_POST['comment']) ? htmlspecialchars($_POST['comment']) : '' ?></textarea>
                        </div>
                    </div>

                    <div class="mt-4">
    <button type="submit" class="btn btn-primary">Создать заказ</button>
    <a href="orders.php" class="btn btn-secondary">Отмена</a>
    <button type="button" class="btn btn-info" onclick="document.getElementById('debugInfo').style.display = 'block';">Показать отладку</button>
</div>
                </form>
            </div>
        </div>
    </div>
</div>

<script>
// Функция для форматирования телефона
function formatPhone(phone) {
    return phone.replace(/\D/g, '').replace(/^7/, '8');
}

// Поиск клиента по телефону
document.getElementById('client_phone').addEventListener('blur', function() {
    const phone = formatPhone(this.value);
    if (phone.length >= 10) {
        fetch('ajax_find_client.php?phone=' + encodeURIComponent(phone))
            .then(response => response.json())
            .then(data => {
                if (data.exists) {
                    // Клиент найден - заполняем поля
                    document.getElementById('client_full_name').value = data.full_name || '';
                    document.getElementById('client_email').value = data.email || '';
                    document.getElementById('client_address').value = data.address || '';
                    
                    // Заполняем поля компании, если есть
                    if (data.company_name) {
                        document.querySelector('input[name="company_name"]').value = data.company_name || '';
                    }
                    if (data.director) {
                        document.querySelector('input[name="director"]').value = data.director || '';
                    }
                    if (data.inn) {
                        document.querySelector('input[name="inn"]').value = data.inn || '';
                    }
                }
            })
            .catch(error => {
                console.error('Error:', error);
            });
    }
});

// Переключение типа клиента
document.querySelectorAll('input[name="client_type"]').forEach(radio => {
    radio.addEventListener('change', function() {
        const isCompany = this.value === 'legal';
        document.querySelectorAll('.company-fields').forEach(field => {
            field.style.display = isCompany ? 'block' : 'none';
        });
    });
});

// Инициализация при загрузке
document.addEventListener('DOMContentLoaded', function() {
    // Активируем текущий тип клиента
    const clientType = document.querySelector('input[name="client_type"]:checked');
    if (clientType) {
        clientType.dispatchEvent(new Event('change'));
    }
});

// Переключение выбора модели
document.getElementById('device_model_id').addEventListener('change', function() {
    const isCustom = this.value === 'custom';
    document.querySelector('.custom-model-field').style.display = isCustom ? 'block' : 'none';
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


</script>

<?php 


renderFooter(); 
?>