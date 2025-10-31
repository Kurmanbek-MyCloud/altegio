<?php
/**
 * API Endpoint для создания платежей в Altegio
 * 
 * Клиент → Наш API → Altegio
 */

// Подключаем конфигурацию
$config = require_once 'config.php';

// Подключаем класс для работы с Altegio API
require_once 'altegio_payments.php';

// Устанавливаем заголовки для API
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Обработка preflight запросов
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Функция для логирования
function logApiRequest($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] API: $message" . PHP_EOL;
    file_put_contents('api_endpoint.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Функция для отправки ответа
function sendResponse($success, $data = null, $error = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    $response = [
        'success' => $success,
        'timestamp' => date('Y-m-d H:i:s'),
        'data' => $data,
        'error' => $error
    ];
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logApiRequest("Invalid method: " . $_SERVER['REQUEST_METHOD']);
    sendResponse(false, null, 'Only POST method is allowed', 405);
}

// Получаем данные запроса
$input = file_get_contents('php://input');
$requestData = json_decode($input, true);

if (!$requestData) {
    logApiRequest("Invalid JSON data received");
    sendResponse(false, null, 'Invalid JSON data', 400);
}

logApiRequest("Received request: " . json_encode($requestData));

// Валидация обязательных полей
$requiredFields = ['client_id', 'amount'];
foreach ($requiredFields as $field) {
    if (!isset($requestData[$field])) {
        logApiRequest("Missing required field: $field");
        sendResponse(false, null, "Missing required field: $field", 400);
    }
}

// Валидация суммы
if (!is_numeric($requestData['amount']) || $requestData['amount'] <= 0) {
    logApiRequest("Invalid amount: " . $requestData['amount']);
    sendResponse(false, null, 'Amount must be a positive number', 400);
}

// Валидация client_id
if (!is_numeric($requestData['client_id']) || $requestData['client_id'] <= 0) {
    logApiRequest("Invalid client_id: " . $requestData['client_id']);
    sendResponse(false, null, 'Client ID must be a positive number', 400);
}

try {
    // Инициализируем класс для работы с Altegio API
    $payments = new AltegioPayments(
        $config['api']['user_token'],
        $config['api']['partner_token'],
        $config['api']['company_id']
    );
    
    // Подготавливаем данные для создания платежа
    $paymentData = [
        'client_id' => (int)$requestData['client_id'],
        'amount' => (float)$requestData['amount'],
        'description' => $requestData['description'] ?? 'Платеж через API',
        'payment_method' => $requestData['payment_method'] ?? 'cash',
        'currency' => $requestData['currency'] ?? 'RUB'
    ];
    
    // Добавляем дополнительные поля если они есть
    if (isset($requestData['master_id'])) {
        $paymentData['master_id'] = (int)$requestData['master_id'];
    }
    
    if (isset($requestData['comment'])) {
        $paymentData['comment'] = $requestData['comment'];
    }
    
    logApiRequest("Creating payment with data: " . json_encode($paymentData));
    
    // Создаем платеж в Altegio
    $result = $payments->createPayment($paymentData);
    
    if ($result['success']) {
        logApiRequest("Payment created successfully: " . json_encode($result['data']));
        
        // Отправляем успешный ответ
        sendResponse(true, [
            'payment_id' => $result['data']['id'] ?? $result['data']['data']['id'] ?? 'N/A',
            'amount' => abs($result['data']['amount'] ?? $result['data']['data']['amount'] ?? $paymentData['amount']),
            'client_id' => $result['data']['client_id'] ?? $result['data']['data']['client_id'] ?? $paymentData['client_id'],
            'status' => 'created',
            'created_at' => $result['data']['date'] ?? $result['data']['data']['date'] ?? date('Y-m-d H:i:s'),
            'altegio_response' => $result['data']
        ]);
        
    } else {
        logApiRequest("Failed to create payment: " . json_encode($result));
        sendResponse(false, null, $result['error'] ?? 'Failed to create payment in Altegio', 500);
    }
    
} catch (Exception $e) {
    logApiRequest("Exception: " . $e->getMessage());
    sendResponse(false, null, 'Internal server error: ' . $e->getMessage(), 500);
}

// Если дошли до сюда, что-то пошло не так
logApiRequest("Unexpected end of script");
sendResponse(false, null, 'Unexpected error', 500);
?> 