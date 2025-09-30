<?php
// order_receipt.php
require_once 'inc/layout.php';

requireAuth();

if (!isset($_GET['id'])) {
    header("Location: orders.php");
    exit;
}

$orderId = (int)$_GET['id'];

// Получаем данные заказа
try {
    $stmt = $pdo->prepare("
        SELECT o.*, c.full_name as client_name, c.phone as client_phone, c.address as client_address,
               cat.name as category_name, b.name as brand_name, s.name as status_name,
               u1.full_name as manager_name, u2.full_name as master_name,
               dm.name as model_name
        FROM orders o 
        LEFT JOIN clients c ON o.client_id = c.id 
        LEFT JOIN device_categories cat ON o.device_category_id = cat.id 
        LEFT JOIN brands b ON o.brand_id = b.id 
        LEFT JOIN statuses s ON o.status_id = s.id 
        LEFT JOIN users u1 ON o.manager_id = u1.id 
        LEFT JOIN users u2 ON o.master_id = u2.id 
        LEFT JOIN device_models dm ON o.device_model_id = dm.id 
        WHERE o.id = ?
    ");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$order) {
        throw new Exception("Заказ не найден");
    }
} catch (Exception $e) {
    $_SESSION['error'] = $e->getMessage();
    header("Location: orders.php");
    exit;
}

// Если это прямой вызов для печати - показываем квитанцию и сразу печатаем
if (isset($_GET['print'])) {
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Квитанция №<?= $orderId ?></title>
        <style>
            @media print {
                body { 
                    margin: 0;
                    padding: 0;
                    font-family: Arial, sans-serif;
                    font-size: 10pt;
                    background: white;
                    color: black;
                }
                .no-print {
                    display: none !important;
                }
                .receipt-page {
                    width: 210mm;
                    height: 297mm;
                    page-break-after: always;
                    padding: 15mm;
                    box-sizing: border-box;
                }
            }
            @media screen {
                body {
                    background: #f5f5f5;
                    padding: 20px;
                    font-family: Arial, sans-serif;
                }
                .receipt-page {
                    width: 210mm;
                    min-height: 297mm;
                    background: white;
                    margin: 0 auto;
                    padding: 20px;
                    box-shadow: 0 0 10px rgba(0,0,0,0.1);
                    box-sizing: border-box;
                }
            }
            .receipt-copy {
                margin-bottom: 20mm;
            }
            .receipt-copy:last-child {
                margin-bottom: 0;
            }
            .copy-label {
                text-align: center;
                font-weight: bold;
                margin-bottom: 10px;
                font-size: 11pt;
                border-bottom: 2px solid #000;
                padding-bottom: 5px;
            }
            .receipt-header {
                text-align: center;
                margin-bottom: 10px;
            }
            .receipt-header h1 {
                margin: 5px 0;
                font-size: 14pt;
                font-weight: bold;
            }
            .receipt-table {
                width: 100%;
                border-collapse: collapse;
                margin: 8px 0;
                font-size: 9pt;
            }
            .receipt-table td {
                padding: 4px 6px;
                border: 1px solid #000;
                vertical-align: top;
            }
            .receipt-table td:first-child {
                font-weight: bold;
                width: 25%;
                background: #f0f0f0;
            }
            .conditions {
                font-size: 7pt;
                margin-top: 10px;
                line-height: 1.1;
            }
            .conditions ol {
                padding-left: 12px;
                margin: 5px 0;
            }
            .conditions li {
                margin-bottom: 3px;
                text-align: justify;
            }
            .signature-area {
                margin-top: 15px;
                font-size: 9pt;
            }
            .signature-line {
                display: flex;
                justify-content: space-between;
                margin-bottom: 8px;
            }
            .signature-box {
                width: 48%;
            }
            .footer-note {
                font-size: 8pt;
                margin-top: 10px;
                border-top: 1px solid #000;
                padding-top: 5px;
            }
            .problem-box {
                border: 1px solid #000;
                padding: 4px;
                margin: 3px 0;
                min-height: 20px;
                font-size: 9pt;
            }
            .master-table {
                width: 100%;
                border-collapse: collapse;
                margin: 8px 0;
                font-size: 9pt;
            }
            .master-table td {
                padding: 5px;
                border: 1px solid #000;
                text-align: center;
            }
            .master-table td:first-child {
                width: 33%;
            }
            .no-print {
                text-align: center;
                margin: 20px 0;
            }
            .btn {
                padding: 10px 20px;
                margin: 0 10px;
                background: #007bff;
                color: white;
                border: none;
                border-radius: 4px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
            }
        </style>
    </head>
    <body>
    
    <div class="no-print">
        <button onclick="window.print()" class="btn">🖨️ Печатать</button>
        <button onclick="window.close()" class="btn" style="background: #6c757d;">✖ Закрыть</button>
        <p style="margin-top: 10px; color: #666;">Квитанция будет автоматически отправлена на печать</p>
    </div>

    <!-- Один лист А4 с двумя экземплярами -->
    <div class="receipt-page">
        <!-- Экземпляр для сервиса -->
        <div class="receipt-copy">
            <div class="copy-label">ЭКЗЕМПЛЯР ДЛЯ СЕРВИСА</div>
            
            <div class="receipt-header">
                <h1>Приемная квитанция №<?= $orderId ?> от <?= date('d.m.Y', strtotime($order['created_at'])) ?></h1>
            </div>
            
            <table class="receipt-table">
                <tr>
                    <td>Клиент</td>
                    <td><?= htmlspecialchars($order['client_name']) ?>, <?= htmlspecialchars($order['client_phone']) ?></td>
                </tr>
                <tr>
                    <td>Устройство</td>
                    <td>
                        <?= htmlspecialchars($order['category_name']) ?>, 
                        <?= htmlspecialchars($order['brand_name']) ?>
                        <?php if (!empty($order['model_name']) || !empty($order['custom_device_model'])): ?>
                        <?= htmlspecialchars(!empty($order['custom_device_model']) ? $order['custom_device_model'] : $order['model_name']) ?>,
                        <?php endif; ?>
                        -
                    </td>
                </tr>
                <tr>
                    <td>Внешний вид</td>
                    <td>б/у, оплетка повреждена</td>
                </tr>
                <tr>
                    <td>Комплектация</td>
                    <td><?= !empty($order['accessories']) ? htmlspecialchars($order['accessories']) : 'парогенератор (голубой)' ?></td>
                </tr>
                <tr>
                    <td>Неисправность со слов клиента</td>
                    <td>
                        <div class="problem-box"><?= nl2br(htmlspecialchars($order['problem_description'])) ?></div>
                    </td>
                </tr>
            </table>
            
            <div class="conditions">
                <p><strong>Условия оказания услуг</strong></p>
                <ol>
                    <li>Диагностика является отдельной, необходимой для проведения ремонта услугой. Диагностика производится полностью бесплатно, даже в случае отказа от дальнейшего ремонта. Срок диагностики аппарата составляет от 2 до 5 рабочих дней, который может быть увеличен в зависимости от ее сложности или необходимости применения специального оборудования.</li>
                    <li>СЦ имеет право ОТКАЗАТЬ клиенту в ремонте аппарата, при невозможности ремонта, отсутствии необходимых запчастей или оборудования, или выявленных в процессе ремонта дополнительных неисправностей, или обнаружения следов жизнедеятельности.</li>
                    <li>ГАРАНТИЙНОЕ ОБСЛУЖИВАНИЕ распространяется только на ремонт заявленной неисправности и при наличии неповреждённого гарантийного стикера. Гарантия на ремонт аппаратов, попавших под воздействие агрессивной среды (воды и т.д.), имеющие внешние, внутренние повреждения, или видимые деформации корпуса, составляет 3 дня (на проверку). Стоимость РАСШИРЕНИЯ ГАРАНТИИ до трех месяцев составляет 20% от стоимости ремонта.</li>
                    <li>При проведении ремонта с использованием запчасти, ЗАМЕНЕННАЯ ЗАПЧАСТЬ предоставляется клиенту по требованию заранее, до проведения ремонта (если замененная запчасть не разрушилась при снятии).</li>
                    <li>Срок хранения аппарата в мастерской составляет до 2 месяцев с момента сдачи в ремонт, после которого отправляется на склад платного временного хранения, где может быть утилизирована либо реализована с целью компенсации расходов на хранение.</li>
                    <li>Данная квитанция является гарантийным талоном и СОБСТВЕННОСТЬЮ сервисного центра ВЫДАЕТСЯ в случае проведенного ремонта.</li>
                    <li>В связи с отсутствием полной проверки функционала сдаваемого в ремонт аппарата, КЛИЕНТ ОЗНАКОМЛЕН и СОГЛАСЕН с возможностью выявления ДОПОЛНИТЕЛЬНЫХ НЕИСПРАВНОСТЕЙ, обнаруженных в ходе или после ремонта.</li>
                    <li>Выдача техники производится по квитанции сервисного центра. При отсутствии квитанции технику получает владелец, указанный в квитанции, по паспорту.</li>
                </ol>
            </div>
            
            <div class="signature-area">
                <div class="signature-line">
                    <div class="signature-box">
                        <p><strong>Менеджер:</strong> __________________</p>
                        <p><strong>С условиями оказания услуг ознакомлен и согласен</strong></p>
                    </div>
                    <div class="signature-box">
                        <p><strong>Заказчик:</strong> __________________</p>
                        <p><?= htmlspecialchars(explode(' ', $order['client_name'])[0]) ?></p>
                    </div>
                </div>
            </div>
            
            <div class="footer-note">
                <p><strong>Статус заказа</strong></p>
                <p><strong>Устройство получил(а), претензий по ремонту не имею, комплектацию и внешний вид проверил(а)</strong></p>
                <p>Дата: <?= date('d.m.Y') ?> <strong>Заказчик:</strong> __________________ <?= htmlspecialchars(explode(' ', $order['client_name'])[0]) ?></p>
                <p style="text-align: right;"><?= date('H:i') ?></p>
            </div>
        </div>

        <!-- Экземпляр для клиента -->
        <div class="receipt-copy">
            <div class="copy-label">ЭКЗЕМПЛЯР ДЛЯ КЛИЕНТА</div>
            
            <div class="receipt-header">
                <h1>Приемная квитанция №<?= $orderId ?> от <?= date('d.m.Y', strtotime($order['created_at'])) ?></h1>
                <p><strong>ON Сервис, Заревый пр, д2</strong><br>+7 993 898 33 40</p>
            </div>
            
            <table class="master-table">
                <tr>
                    <td><strong>Мастер</strong></td>
                    <td><strong>Выполненные работы</strong></td>
                    <td><strong>Сумма ремонта</strong></td>
                </tr>
                <tr>
                    <td>__________________</td>
                    <td>__________________</td>
                    <td>__________________</td>
                </tr>
            </table>
            
            <table class="receipt-table">
                <tr>
                    <td>Клиент</td>
                    <td><?= htmlspecialchars($order['client_name']) ?>, <?= htmlspecialchars($order['client_phone']) ?></td>
                </tr>
                <tr>
                    <td>Устройство</td>
                    <td>
                        <?= htmlspecialchars($order['category_name']) ?>, 
                        <?= htmlspecialchars($order['brand_name']) ?>
                        <?php if (!empty($order['model_name']) || !empty($order['custom_device_model'])): ?>
                        <?= htmlspecialchars(!empty($order['custom_device_model']) ? $order['custom_device_model'] : $order['model_name']) ?>
                        <?php endif; ?>,
                        -
                    </td>
                </tr>
            </table>
            
            <div style="margin: 5px 0; font-size: 9pt;">
                <p><strong>Комплектация</strong></p>
                <div class="problem-box"><?= !empty($order['accessories']) ? htmlspecialchars($order['accessories']) : 'стандартная' ?></div>
            </div>
            
            <div style="margin: 5px 0; font-size: 9pt;">
                <p><strong>Неисправность со слов клиента</strong></p>
                <div class="problem-box"><?= nl2br(htmlspecialchars($order['problem_description'])) ?></div>
            </div>
            
            <div class="signature-area">
                <div class="signature-line">
                    <div class="signature-box">
                        <p><strong>Менеджер:</strong> __________________</p>
                        <p><strong>Дата:</strong> <?= date('d.m.Y H:i') ?></p>
                    </div>
                </div>
                
                <div class="signature-line" style="margin-top: 8px;">
                    <div class="signature-box">
                        <p><strong>С условиями оказания услуг ознакомлен и согласен</strong></p>
                    </div>
                    <div class="signature-box">
                        <p><strong>Заказчик:</strong> __________________</p>
                    </div>
                </div>
            </div>
            
            <div class="footer-note">
                <p><strong>Устройство получил(а), претензий по ремонту не имею, комплектацию и внешний вид проверил(а)</strong></p>
                <p><strong>Заказчик:</strong> __________________ <?= htmlspecialchars(explode(' ', $order['client_name'])[0]) ?></p>
            </div>
        </div>
    </div>

    <script>
    // Автоматически открываем диалог печати
    window.onload = function() {
        setTimeout(function() {
            window.print();
        }, 500);
    };

    // Закрываем окно после печати (если поддерживается)
    window.onafterprint = function() {
        setTimeout(function() {
            window.close();
        }, 1000);
    };
    </script>

    </body>
    </html>
    <?php
    exit;
}

// Обычный HTML вывод со ссылкой на печать
renderHeader('Квитанция о приёме заказа #' . $orderId);
?>

<div style="text-align: center; margin: 50px;">
    <h2>Квитанция №<?= $orderId ?> готова к печати</h2>
    <a href="order_receipt.php?id=<?= $orderId ?>&print=1" target="_blank" class="btn" style="display: inline-block; padding: 15px 30px; background: #007bff; color: white; text-decoration: none; border-radius: 5px; font-size: 16px;">
        🖨️ Печать квитанции
    </a>
    <p style="margin-top: 20px; color: #666;">
        Откроется новое окно с квитанцией для печати<br>
        После печати окно закроется автоматически
    </p>
</div>

<?php renderFooter(); ?>