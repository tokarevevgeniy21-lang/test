<?php
// receipt_builder.php
require_once 'inc/layout.php';
requireAuth();

// Получаем шаблоны
$stmt = $pdo->query("SELECT * FROM print_templates ORDER BY is_default DESC, name ASC");
$templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

$defaultTemplate = null;
foreach ($templates as $template) {
    if ($template['is_default']) {
        $defaultTemplate = $template;
        break;
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $type = $_POST['type'];
    $html = $_POST['content'];
    $css = $_POST['css_styles'];
    $isDefault = isset($_POST['is_default']) ? 1 : 0;
    
    try {
        // Если устанавливаем как шаблон по умолчанию, снимаем флаг с других
        if ($isDefault) {
            $pdo->exec("UPDATE print_templates SET is_default = 0");
        }
        
        if (isset($_POST['template_id']) && !empty($_POST['template_id'])) {
            // Обновляем существующий шаблон
            $stmt = $pdo->prepare("UPDATE print_templates SET name = ?, type = ?, content = ?, css_styles = ?, is_default = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$name, $type, $html, $css, $isDefault, $_POST['template_id']]);
            $message = "Шаблон обновлен";
        } else {
            // Создаем новый шаблон
            $stmt = $pdo->prepare("INSERT INTO print_templates (name, type, content, css_styles, is_default, created_at, updated_at) VALUES (?, ?, ?, ?, ?, NOW(), NOW())");
            $stmt->execute([$name, $type, $html, $css, $isDefault]);
            $message = "Шаблон создан";
        }
        
        $_SESSION['success'] = $message;
        header("Location: receipt_builder.php");
        exit;
        
    } catch (Exception $e) {
        $error = "Ошибка сохранения: " . $e->getMessage();
    }
}

// Удаление шаблона
if (isset($_GET['delete'])) {
    $templateId = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM print_templates WHERE id = ? AND is_default = 0");
    $stmt->execute([$templateId]);
    $_SESSION['success'] = "Шаблон удален";
    header("Location: receipt_builder.php");
    exit;
}

// Редактирование шаблона
$editTemplate = null;
if (isset($_GET['edit'])) {
    $stmt = $pdo->prepare("SELECT * FROM print_templates WHERE id = ?");
    $stmt->execute([$_GET['edit']]);
    $editTemplate = $stmt->fetch(PDO::FETCH_ASSOC);
}

renderHeader('Конструктор квитанций');
?>

<div class="container">
    <h1>Конструктор квитанций</h1>
    
    <?php if (isset($error)): ?>
        <div class="alert alert-danger"><?= $error ?></div>
    <?php endif; ?>
    
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success"><?= $_SESSION['success'] ?></div>
        <?php unset($_SESSION['success']); ?>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">
                    <h3><?= $editTemplate ? 'Редактирование шаблона' : 'Создание нового шаблона' ?></h3>
                </div>
                <div class="card-body">
                    <form method="POST">
                        <?php if ($editTemplate): ?>
                            <input type="hidden" name="template_id" value="<?= $editTemplate['id'] ?>">
                        <?php endif; ?>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Название шаблона</label>
                                    <input type="text" name="name" class="form-control" 
                                           value="<?= $editTemplate ? htmlspecialchars($editTemplate['name']) : '' ?>" required>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3">
                                    <label class="form-label">Тип шаблона</label>
                                    <input type="text" name="type" class="form-control" 
                                           value="<?= $editTemplate ? htmlspecialchars($editTemplate['type']) : 'receipt' ?>" 
                                           placeholder="receipt, invoice, act...">
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">HTML шаблон</label>
                            <textarea name="content" class="form-control" rows="15" 
                                      placeholder="Используйте переменные: {{order_id}}, {{client_name}}, {{client_phone}}, {{device_info}}, {{problem_description}}, {{date}}"><?= $editTemplate ? htmlspecialchars($editTemplate['content']) : ($defaultTemplate ? htmlspecialchars($defaultTemplate['content']) : '') ?></textarea>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">CSS стили</label>
                            <textarea name="css_styles" class="form-control" rows="10"><?= $editTemplate ? htmlspecialchars($editTemplate['css_styles']) : ($defaultTemplate ? htmlspecialchars($defaultTemplate['css_styles']) : '') ?></textarea>
                        </div>
                        
                        <div class="mb-3 form-check">
                            <input type="checkbox" name="is_default" class="form-check-input" id="is_default" 
                                   <?= ($editTemplate && $editTemplate['is_default']) ? 'checked' : '' ?>>
                            <label class="form-check-label" for="is_default">Шаблон по умолчанию</label>
                        </div>
                        
                        <button type="submit" class="btn btn-primary">Сохранить шаблон</button>
                        <a href="receipt_builder.php" class="btn btn-secondary">Отмена</a>
                    </form>
                </div>
            </div>
        </div>
        
        <div class="col-md-4">
            <div class="card">
                <div class="card-header">
                    <h3>Доступные шаблоны</h3>
                </div>
                <div class="card-body">
                    <?php foreach ($templates as $template): ?>
                        <div class="d-flex justify-content-between align-items-center mb-2 p-2 border rounded">
                            <div>
                                <strong><?= htmlspecialchars($template['name']) ?></strong>
                                <?php if ($template['is_default']): ?>
                                    <span class="badge bg-primary">по умолчанию</span>
                                <?php endif; ?>
                                <br>
                                <small class="text-muted">Тип: <?= htmlspecialchars($template['type']) ?></small>
                            </div>
                            <div>
                                <a href="receipt_builder.php?edit=<?= $template['id'] ?>" class="btn btn-sm btn-outline-primary">✏️</a>
                                <?php if (!$template['is_default']): ?>
                                    <a href="receipt_builder.php?delete=<?= $template['id'] ?>" class="btn btn-sm btn-outline-danger" 
                                       onclick="return confirm('Удалить шаблон?')">🗑️</a>
                                <?php endif; ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div class="card mt-3">
                <div class="card-header">
                    <h3>Доступные переменные</h3>
                </div>
                <div class="card-body">
                    <ul class="list-unstyled">
                        <li><code>{{order_id}}</code> - номер заказа</li>
                        <li><code>{{client_name}}</code> - имя клиента</li>
                        <li><code>{{client_phone}}</code> - телефон клиента</li>
                        <li><code>{{device_category}}</code> - категория устройства</li>
                        <li><code>{{device_brand}}</code> - бренд устройства</li>
                        <li><code>{{device_model}}</code> - модель устройства</li>
                        <li><code>{{device_info}}</code> - полная информация об устройстве</li>
                        <li><code>{{problem_description}}</code> - описание проблемы</li>
                        <li><code>{{accessories}}</code> - комплектация</li>
                        <li><code>{{appearance}}</code> - внешний вид</li>
                        <li><code>{{date}}</code> - текущая дата</li>
                        <li><code>{{time}}</code> - текущее время</li>
                        <li><code>{{manager_name}}</code> - имя менеджера</li>
                        <li><code>{{master_name}}</code> - имя мастера</li>
                    </ul>
                </div>
            </div>

            <div class="card mt-3">
                <div class="card-header">
                    <h3>Быстрые шаблоны</h3>
                </div>
                <div class="card-body">
                    <button type="button" class="btn btn-outline-primary btn-sm mb-2" onclick="loadTemplate('basic')">Базовая квитанция</button>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-2" onclick="loadTemplate('detailed')">Подробная квитанция</button>
                    <button type="button" class="btn btn-outline-primary btn-sm mb-2" onclick="loadTemplate('compact')">Компактная квитанция</button>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
function loadTemplate(type) {
    const templates = {
        'basic': {
            content: `<div class="receipt">
    <div class="header">
        <h1>Приемная квитанция №{{order_id}}</h1>
        <p>от {{date}}</p>
    </div>
    
    <table class="client-info">
        <tr><td><strong>Клиент:</strong></td><td>{{client_name}}, {{client_phone}}</td></tr>
        <tr><td><strong>Устройство:</strong></td><td>{{device_info}}</td></tr>
        <tr><td><strong>Неисправность:</strong></td><td>{{problem_description}}</td></tr>
    </table>
    
    <div class="signatures">
        <div class="manager">
            <p>Менеджер: ________________</p>
            <p>Дата: {{date}}</p>
        </div>
        <div class="client">
            <p>Заказчик: ________________</p>
            <p>{{client_name}}</p>
        </div>
    </div>
</div>`,
            css: `.receipt { 
    width: 210mm; 
    margin: 0 auto; 
    padding: 15mm; 
    font-family: Arial, sans-serif; 
    font-size: 12pt;
}
.header { 
    text-align: center; 
    margin-bottom: 20px; 
    border-bottom: 2px solid #000;
    padding-bottom: 10px;
}
.client-info { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 20px 0;
}
.client-info td { 
    padding: 10px; 
    border: 1px solid #000; 
    vertical-align: top;
}
.client-info td:first-child { 
    width: 30%; 
    background: #f5f5f5;
}
.signatures { 
    margin-top: 60px; 
    display: flex; 
    justify-content: space-between; 
}
.manager, .client {
    text-align: center;
}`
        },
        'detailed': {
            content: `<div class="receipt">
    <div class="header">
        <h1>Приемная квитанция №{{order_id}}</h1>
        <h2>от {{date}}</h2>
        <p><strong>ON Сервис, Заревый пр, д2</strong><br>+7 993 898 33 40</p>
    </div>
    
    <table class="info-table">
        <tr><td><strong>Клиент:</strong></td><td>{{client_name}}</td></tr>
        <tr><td><strong>Телефон:</strong></td><td>{{client_phone}}</td></tr>
        <tr><td><strong>Устройство:</strong></td><td>{{device_category}}, {{device_brand}} {{device_model}}</td></tr>
        <tr><td><strong>Внешний вид:</strong></td><td>{{appearance}}</td></tr>
        <tr><td><strong>Комплектация:</strong></td><td>{{accessories}}</td></tr>
        <tr><td><strong>Неисправность:</strong></td><td>{{problem_description}}</td></tr>
    </table>
    
    <div class="conditions">
        <p><strong>Условия оказания услуг:</strong></p>
        <ol>
            <li>Диагностика бесплатна (2-5 рабочих дней)</li>
            <li>Гарантия на ремонт - 6 месяцев</li>
            <li>Хранение устройства - до 2 месяцев</li>
        </ol>
    </div>
    
    <div class="signatures">
        <div class="left">
            <p>Менеджер: __________________</p>
            <p>С условиями ознакомлен и согласен</p>
        </div>
        <div class="right">
            <p>Заказчик: __________________</p>
            <p>{{client_name}}</p>
        </div>
    </div>
</div>`,
            css: `.receipt { 
    width: 210mm; 
    height: 297mm; 
    margin: 0 auto; 
    padding: 15mm; 
    font-family: Arial, sans-serif; 
    font-size: 10pt;
    box-sizing: border-box;
}
.header { 
    text-align: center; 
    margin-bottom: 15px; 
}
.info-table { 
    width: 100%; 
    border-collapse: collapse; 
    margin: 10px 0;
}
.info-table td { 
    padding: 8px; 
    border: 1px solid #000; 
}
.info-table td:first-child { 
    width: 25%; 
    background: #f0f0f0; 
    font-weight: bold;
}
.conditions { 
    margin-top: 15px; 
    font-size: 9pt;
}
.conditions ol { 
    margin: 5px 0; 
    padding-left: 15px;
}
.signatures { 
    margin-top: 40px; 
    display: flex; 
    justify-content: space-between;
}`
        }
    };

    if (templates[type]) {
        document.querySelector('textarea[name="content"]').value = templates[type].content;
        document.querySelector('textarea[name="css_styles"]').value = templates[type].css;
    }
}
</script>

<?php renderFooter(); ?>