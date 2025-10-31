<?php
/**
 * Webhook handler for Altegio API - Financial Transactions
 * Обработчик вебхуков для API Altegio - Финансовые транзакции
 */

// Настройки
$config = [
    'webhook_secret' => 'YOUR_WEBHOOK_SECRET', // Замените на ваш секретный ключ
    'log_file' => 'webhook_log.txt',
    'debug' => true
];

// Функция для логирования
function logMessage($message) {
    global $config;
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] $message" . PHP_EOL;
    
    if ($config['debug']) {
        echo $logEntry;
    }
    
    file_put_contents($config['log_file'], $logEntry, FILE_APPEND | LOCK_EX);
}

// Функция для отправки ответа
function sendResponse($status, $message = '') {
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode(['status' => $status, 'message' => $message]);
    exit;
}

// Проверка метода запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logMessage('Invalid request method: ' . $_SERVER['REQUEST_METHOD']);
    sendResponse(405, 'Method not allowed');
}

// Получение тела запроса
$input = file_get_contents('php://input');
$headers = getallheaders();

logMessage('Received webhook request');
logMessage('Headers: ' . json_encode($headers));
logMessage('Body: ' . $input);

// Проверка наличия данных
if (empty($input)) {
    logMessage('Empty request body');
    sendResponse(400, 'Empty request body');
}

// Декодирование JSON
$data = json_decode($input, true);
if (json_last_error() !== JSON_ERROR_NONE) {
    logMessage('Invalid JSON: ' . json_last_error_msg());
    sendResponse(400, 'Invalid JSON');
}

// Обработка различных форматов вебхуков Altegio
logMessage("Processing Altegio webhook");

try {
    // Формат 1: События приложения (uninstall, install)
    if (isset($data['event'])) {
        handleAppEvent($data);
    }
    // Формат 2: События ресурсов (record, visit, client)
    elseif (isset($data['resource']) && isset($data['status'])) {
        handleResourceEvent($data);
    }
    // Формат 3: Наш кастомный формат
    elseif (isset($data['event_type']) && isset($data['data'])) {
        handleCustomEvent($data);
    }
    else {
        logMessage("Unknown webhook format: " . json_encode($data));
        sendResponse(400, 'Unknown webhook format');
    }
    
    logMessage("Webhook processed successfully");
    sendResponse(200, 'Webhook processed successfully');
    
} catch (Exception $e) {
    logMessage('Error processing webhook: ' . $e->getMessage());
    sendResponse(500, 'Internal server error');
}

/**
 * Обработка событий приложения
 */
function handleAppEvent($data) {
    $event = $data['event'] ?? 'unknown';
    logMessage("App event: $event");
    
    switch ($event) {
        case 'uninstall':
            logMessage('Application uninstalled from salon: ' . ($data['salon_id'] ?? 'N/A'));
            sendNotification("❌ Приложение отключено", [
                'ID салона' => $data['salon_id'] ?? 'N/A',
                'ID приложения' => $data['application_id'] ?? 'N/A'
            ]);
            break;
            
        case 'install':
            logMessage('Application installed to salon: ' . ($data['salon_id'] ?? 'N/A'));
            sendNotification("✅ Приложение подключено", [
                'ID салона' => $data['salon_id'] ?? 'N/A',
                'ID приложения' => $data['application_id'] ?? 'N/A'
            ]);
            break;
            
        default:
            logMessage("Unknown app event: $event");
            break;
    }
}

/**
 * Обработка событий ресурсов
 */
function handleResourceEvent($data) {
    $resource = $data['resource'] ?? 'unknown';
    $status = $data['status'] ?? 'unknown';
    $resourceId = $data['resource_id'] ?? 'N/A';
    
    logMessage("Resource event: $resource.$status (ID: $resourceId)");
    
    switch ($resource) {
        case 'record':
            handleRecordEvent($data);
            break;
            
        case 'visit':
            handleVisitEvent($data);
            break;
            
        case 'client':
            handleClientEvent($data);
            break;
            
        case 'transaction':
            handleTransactionEvent($data);
            break;
            
        default:
            logMessage("Unknown resource: $resource");
            break;
    }
}

/**
 * Обработка событий записей
 */
function handleRecordEvent($data) {
    $status = $data['status'] ?? 'unknown';
    $recordData = $data['data'] ?? [];
    
    logMessage("Record event: $status - " . json_encode($recordData));
    
    switch ($status) {
        case 'create':
            handleRecordCreated($recordData);
            break;
            
        case 'update':
            handleRecordUpdated($recordData);
            break;
            
        case 'delete':
            handleRecordDeleted($recordData);
            break;
            
        default:
            logMessage("Unknown record status: $status");
            break;
    }
}

/**
 * Обработка создания записи
 */
function handleRecordCreated($data) {
    logMessage('Record created: ' . json_encode($data));
    
    // Извлекаем данные из записи
    $recordId = $data['id'] ?? 'N/A';
    $clientName = $data['client']['display_name'] ?? 'N/A';
    $clientPhone = $data['client']['phone'] ?? 'N/A';
    $serviceName = $data['services'][0]['title'] ?? 'N/A';
    $staffName = $data['staff']['name'] ?? 'N/A';
    $datetime = $data['datetime'] ?? 'N/A';
    $cost = $data['services'][0]['cost'] ?? 0;
    
    // Сохраняем в файл
    $recordInfo = [
        'id' => $recordId,
        'client_name' => $clientName,
        'client_phone' => $clientPhone,
        'service_name' => $serviceName,
        'staff_name' => $staffName,
        'datetime' => $datetime,
        'cost' => $cost,
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents('records.json', json_encode($recordInfo, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    // Отправляем уведомление
    sendNotification("📅 Создана новая запись", [
        'ID записи' => $recordId,
        'Клиент' => $clientName,
        'Телефон' => $clientPhone,
        'Услуга' => $serviceName,
        'Сотрудник' => $staffName,
        'Дата' => $datetime,
        'Стоимость' => $cost . ' RUB'
    ]);
    
    // Если есть стоимость, создаем платеж
    if ($cost > 0) {
        createPaymentForRecord($data);
    }
}

/**
 * Обработка обновления записи
 */
function handleRecordUpdated($data) {
    logMessage('Record updated: ' . json_encode($data));
    
    sendNotification("🔄 Запись обновлена", [
        'ID записи' => $data['id'] ?? 'N/A',
        'Клиент' => $data['client']['display_name'] ?? 'N/A',
        'Дата' => $data['datetime'] ?? 'N/A'
    ]);
}

/**
 * Обработка удаления записи
 */
function handleRecordDeleted($data) {
    logMessage('Record deleted: ' . json_encode($data));
    
    sendNotification("❌ Запись отменена", [
        'ID записи' => $data['id'] ?? 'N/A',
        'Клиент' => $data['client']['display_name'] ?? 'N/A'
    ]);
}

/**
 * Обработка событий визитов
 */
function handleVisitEvent($data) {
    $status = $data['status'] ?? 'unknown';
    $visitData = $data['data'] ?? [];
    
    logMessage("Visit event: $status - " . json_encode($visitData));
    
    switch ($status) {
        case 'create':
            handleVisitCreated($visitData);
            break;
            
        case 'update':
            handleVisitUpdated($visitData);
            break;
            
        case 'complete':
            handleVisitCompleted($visitData);
            break;
            
        default:
            logMessage("Unknown visit status: $status");
            break;
    }
}

/**
 * Обработка создания визита
 */
function handleVisitCreated($data) {
    logMessage('Visit created: ' . json_encode($data));
    
    sendNotification("✅ Визит создан", [
        'ID визита' => $data['id'] ?? 'N/A',
        'Клиент' => $data['client']['display_name'] ?? 'N/A'
    ]);
}

/**
 * Обработка завершения визита
 */
function handleVisitCompleted($data) {
    logMessage('Visit completed: ' . json_encode($data));
    
    // Автоматическое создание платежа при завершении визита
    if (isset($data['services']) && !empty($data['services'])) {
        $totalCost = 0;
        foreach ($data['services'] as $service) {
            $totalCost += $service['cost'] ?? 0;
        }
        
        if ($totalCost > 0) {
            createPaymentForVisit($data);
        }
    }
    
    sendNotification("✅ Визит завершен", [
        'ID визита' => $data['id'] ?? 'N/A',
        'Клиент' => $data['client']['display_name'] ?? 'N/A',
        'Сумма' => $totalCost . ' RUB'
    ]);
}

/**
 * Обработка событий клиентов
 */
function handleClientEvent($data) {
    $status = $data['status'] ?? 'unknown';
    $clientData = $data['data'] ?? [];
    
    logMessage("Client event: $status - " . json_encode($clientData));
    
    switch ($status) {
        case 'create':
            handleClientCreated($clientData);
            break;
            
        case 'update':
            handleClientUpdated($clientData);
            break;
            
        default:
            logMessage("Unknown client status: $status");
            break;
    }
}

/**
 * Обработка создания клиента
 */
function handleClientCreated($data) {
    logMessage('Client created: ' . json_encode($data));
    
    sendNotification("👤 Создан новый клиент", [
        'ID клиента' => $data['id'] ?? 'N/A',
        'Имя' => $data['display_name'] ?? 'N/A',
        'Телефон' => $data['phone'] ?? 'N/A'
    ]);
}

/**
 * Обработка событий транзакций
 */
function handleTransactionEvent($data) {
    $status = $data['status'] ?? 'unknown';
    $transactionData = $data['data'] ?? [];
    
    logMessage("Transaction event: $status - " . json_encode($transactionData));
    
    switch ($status) {
        case 'create':
            handleTransactionCreated($transactionData);
            break;
            
        case 'update':
            handleTransactionUpdated($transactionData);
            break;
            
        default:
            logMessage("Unknown transaction status: $status");
            break;
    }
}

/**
 * Обработка кастомных событий (наш формат)
 */
function handleCustomEvent($data) {
    $eventType = $data['event_type'];
    $eventData = $data['data'];
    
    logMessage("Custom event: $eventType");
    
    switch ($eventType) {
        case 'transaction.created':
            handleTransactionCreated($eventData);
            break;
            
        case 'payment.received':
            handlePaymentReceived($eventData);
            break;
            
        case 'payment.failed':
            handlePaymentFailed($eventData);
            break;
            
        default:
            logMessage("Unknown custom event: $eventType");
            break;
    }
}

/**
 * Создание платежа для записи
 */
function createPaymentForRecord($recordData) {
    global $config;
    
    // Используем API Altegio для создания платежа
    require_once 'altegio_payments.php';
    $payments = new AltegioPayments(
        $config['api']['user_token'], 
        $config['api']['partner_token'], 
        $config['api']['company_id']
    );
    
    // Создаем платеж через API
    $result = $payments->createPaymentForRecord($recordData);
    
    if ($result['success']) {
        logMessage('Payment created in Altegio for record: ' . json_encode($result['data']));
        return $result['data'];
    } else {
        logMessage('Failed to create payment in Altegio for record: ' . json_encode($result));
        return false;
    }
}

/**
 * Создание платежа для визита
 */
function createPaymentForVisit($visitData) {
    global $config;
    
    // Используем API Altegio для создания платежа
    require_once 'altegio_payments.php';
    $payments = new AltegioPayments(
        $config['api']['user_token'], 
        $config['api']['partner_token'], 
        $config['api']['company_id']
    );
    
    // Создаем платеж через API
    $result = $payments->createPaymentForVisit($visitData);
    
    if ($result['success']) {
        logMessage('Payment created in Altegio for visit: ' . json_encode($result['data']));
        return $result['data'];
    } else {
        logMessage('Failed to create payment in Altegio for visit: ' . json_encode($result));
        return false;
    }
}

/**
 * Обработчики событий для финансовых транзакций (оставляем для совместимости)
 */
function handleTransactionCreated($data) {
    logMessage('Transaction created: ' . json_encode($data));
    
    $transactionData = [
        'id' => $data['id'] ?? null,
        'client_id' => $data['client_id'] ?? null,
        'amount' => $data['amount'] ?? 0,
        'currency' => $data['currency'] ?? 'RUB',
        'payment_method' => $data['payment_method'] ?? '',
        'status' => $data['status'] ?? 'pending',
        'created_at' => date('Y-m-d H:i:s')
    ];
    
    file_put_contents('transactions.json', json_encode($transactionData, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
    
    sendNotification("💰 Создана новая транзакция", [
        'ID транзакции' => $transactionData['id'],
        'Сумма' => $transactionData['amount'] . ' ' . $transactionData['currency'],
        'Метод оплаты' => $transactionData['payment_method'],
        'Статус' => $transactionData['status']
    ]);
}

function handleTransactionUpdated($data) {
    logMessage('Transaction updated: ' . json_encode($data));
    
    sendNotification("🔄 Транзакция обновлена", [
        'ID транзакции' => $data['id'] ?? 'N/A',
        'Новый статус' => $data['status'] ?? 'N/A',
        'Сумма' => ($data['amount'] ?? 0) . ' ' . ($data['currency'] ?? 'RUB')
    ]);
}

function handlePaymentReceived($data) {
    logMessage('Payment received: ' . json_encode($data));
    
    sendNotification("✅ Платеж получен", [
        'ID транзакции' => $data['transaction_id'] ?? 'N/A',
        'Сумма' => ($data['amount'] ?? 0) . ' ' . ($data['currency'] ?? 'RUB'),
        'Метод оплаты' => $data['payment_method'] ?? 'N/A'
    ]);
}

function handlePaymentFailed($data) {
    logMessage('Payment failed: ' . json_encode($data));
    
    sendNotification("❌ Платеж не прошел", [
        'ID транзакции' => $data['transaction_id'] ?? 'N/A',
        'Ошибка' => $data['error_message'] ?? 'Неизвестная ошибка',
        'Сумма' => ($data['amount'] ?? 0) . ' ' . ($data['currency'] ?? 'RUB')
    ]);
}

/**
 * Отправка уведомления
 */
function sendNotification($title, $data) {
    $message = $title . "\n\n";
    foreach ($data as $key => $value) {
        $message .= "$key: $value\n";
    }
    
    logMessage("Notification: $message");
    
    file_put_contents('notifications.txt', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND | LOCK_EX);
}
?>
