<?php
/**
 * Webhook endpoint для приема callback'ов от Optima Bank
 * URL: https://yourdomain/api/v1/callback
 * Метод: POST
 * Аутентификация: Basic Auth
 */

// Подключаем конфигурацию
if (file_exists('config.php')) {
    require_once 'config.php';
}

// Включаем логирование
ini_set('log_errors', 1);
ini_set('error_log', 'webhook.log');

// Устанавливаем заголовки для JSON
header('Content-Type: application/json; charset=utf-8');

// Настройки аутентификации из конфигурации
$WEBHOOK_USERNAME = function_exists('getWebhookConfig') ? getWebhookConfig()['username'] : 'optima_webhook';
$WEBHOOK_PASSWORD = function_exists('getWebhookConfig') ? getWebhookConfig()['password'] : 'Opt1m@W3bh00k9w2!h';

/**
 * Логирование событий
 */
function logWebhook($message, $data = null) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[{$timestamp}] {$message}";
    
    if ($data) {
        $logEntry .= " | Data: " . json_encode($data, JSON_UNESCAPED_UNICODE);
    }
    
    error_log($logEntry . PHP_EOL, 3, 'webhook.log');
}

/**
 * Проверка Basic Auth
 */
function checkBasicAuth() {
    global $WEBHOOK_USERNAME, $WEBHOOK_PASSWORD;
    
    // Проверяем наличие заголовка Authorization
    if (!isset($_SERVER['HTTP_AUTHORIZATION'])) {
        logWebhook('ERROR: Отсутствует заголовок Authorization');
        return false;
    }
    
    $authHeader = $_SERVER['HTTP_AUTHORIZATION'];
    
    // Проверяем формат Basic Auth
    if (!preg_match('/^Basic\s+(.+)$/', $authHeader, $matches)) {
        logWebhook('ERROR: Неверный формат заголовка Authorization');
        return false;
    }
    
    // Декодируем credentials
    $credentials = base64_decode($matches[1]);
    
    if ($credentials === false) {
        logWebhook('ERROR: Не удалось декодировать credentials');
        return false;
    }
    
    // Проверяем логин и пароль
    if ($credentials !== "{$WEBHOOK_USERNAME}:{$WEBHOOK_PASSWORD}") {
        logWebhook('ERROR: Неверные credentials', [
            'received' => $credentials,
            'expected' => "{$WEBHOOK_USERNAME}:{$WEBHOOK_PASSWORD}"
        ]);
        return false;
    }
    
    return true;
}

/**
 * Валидация данных callback
 */
function validateCallbackData($data) {
    $requiredFields = ['status', 'sum', 'note', 'transactionProcessedDateTime', 'transactionId'];
    $errors = [];
    
    // Проверяем обязательные поля
    foreach ($requiredFields as $field) {
        if (!isset($data[$field])) {
            $errors[] = "Отсутствует обязательное поле: {$field}";
        }
    }
    
    // Проверяем тип данных
    if (isset($data['sum']) && !is_numeric($data['sum'])) {
        $errors[] = "Поле 'sum' должно быть числом";
    }
    
    if (isset($data['status']) && !is_string($data['status'])) {
        $errors[] = "Поле 'status' должно быть строкой";
    }
    
    if (isset($data['note']) && !is_string($data['note'])) {
        $errors[] = "Поле 'note' должно быть строкой";
    }
    
    if (isset($data['transactionId']) && !is_string($data['transactionId'])) {
        $errors[] = "Поле 'transactionId' должно быть строкой";
    }
    
    // Проверяем формат даты
    if (isset($data['transactionProcessedDateTime'])) {
        $date = DateTime::createFromFormat('Y-m-d\TH:i:s\Z', $data['transactionProcessedDateTime']);
        if (!$date) {
            $errors[] = "Неверный формат даты в поле 'transactionProcessedDateTime'";
        }
    }
    
    return $errors;
}

/**
 * Обработка callback данных
 */
function processCallback($data) {
    // Извлекаем ВСЕ данные из callback в переменные
    $status = $data['status'] ?? '';
    $sum = $data['sum'] ?? 0;
    $note = $data['note'] ?? '';
    $transactionId = $data['transactionId'] ?? '';
    $transactionProcessedDateTime = $data['transactionProcessedDateTime'] ?? '';
    
    // Извлекаем все остальные поля, которые могут прийти в запросе
    $merchantId = $data['merchantId'] ?? '';
    $orderId = $data['orderId'] ?? '';
    $currency = $data['currency'] ?? '';
    $paymentMethod = $data['paymentMethod'] ?? '';
    $cardMask = $data['cardMask'] ?? '';
    $cardType = $data['cardType'] ?? '';
    $approvalCode = $data['approvalCode'] ?? '';
    $rrn = $data['rrn'] ?? '';
    $stan = $data['stan'] ?? '';
    $terminalId = $data['terminalId'] ?? '';
    $merchantName = $data['merchantName'] ?? '';
    $merchantCategoryCode = $data['merchantCategoryCode'] ?? '';
    $acquirerId = $data['acquirerId'] ?? '';
    $issuerId = $data['issuerId'] ?? '';
    $responseCode = $data['responseCode'] ?? '';
    $responseMessage = $data['responseMessage'] ?? '';
    $errorCode = $data['errorCode'] ?? '';
    $errorMessage = $data['errorMessage'] ?? '';
    $additionalData = $data['additionalData'] ?? [];
    
    // Логируем извлеченные данные
    logWebhook('INFO: Извлеченные данные из callback', [
        'status' => $status,
        'sum' => $sum,
        'note' => $note,
        'transactionId' => $transactionId,
        'processedAt' => $transactionProcessedDateTime,
        'merchantId' => $merchantId,
        'orderId' => $orderId,
        'currency' => $currency,
        'paymentMethod' => $paymentMethod,
        'cardMask' => $cardMask,
        'cardType' => $cardType,
        'approvalCode' => $approvalCode,
        'rrn' => $rrn,
        'stan' => $stan,
        'terminalId' => $terminalId,
        'merchantName' => $merchantName,
        'merchantCategoryCode' => $merchantCategoryCode,
        'acquirerId' => $acquirerId,
        'issuerId' => $issuerId,
        'responseCode' => $responseCode,
        'responseMessage' => $responseMessage,
        'errorCode' => $errorCode,
        'errorMessage' => $errorMessage,
        'additionalData' => $additionalData
    ]);
    
    // Передаем данные в createpayment.php
    $result = callCreatePayment($data);
    
    if ($result) {
        logWebhook('SUCCESS: Данные успешно переданы в createpayment.php', [
            'transactionId' => $transactionId,
            'status' => $status
        ]);
    } else {
        logWebhook('ERROR: Ошибка при передаче данных в createpayment.php', [
            'transactionId' => $transactionId,
            'status' => $status
        ]);
    }
    
    return $result;
}

/**
 * Вызов createpayment.php с передачей всех данных через переменные окружения
 */
function callCreatePayment($data) {
    try {
        // Кодируем данные в base64 для безопасной передачи через переменные окружения
        $jsonData = json_encode($data, JSON_UNESCAPED_UNICODE);
        $encodedData = base64_encode($jsonData);
        
        // Логируем попытку передачи данных
        logWebhook('INFO: Попытка передачи данных через переменные окружения', [
            'dataSize' => strlen($jsonData),
            'encodedSize' => strlen($encodedData)
        ]);
        
        // Подготавливаем команду для вызова createpayment.php с данными в переменной окружения
        $command = "WEBHOOK_DATA=" . escapeshellarg($encodedData) . " php createpayment.php";
        
        // Логируем команду для отладки
        logWebhook('INFO: Выполняемая команда', [
            'command' => $command,
            'dataSize' => strlen($encodedData)
        ]);
        
        // Выполняем команду
        $output = [];
        $returnCode = 0;
        exec($command . " 2>&1", $output, $returnCode);
        
        // Логируем результат выполнения
        logWebhook('INFO: Результат выполнения createpayment.php', [
            'returnCode' => $returnCode,
            'output' => $output
        ]);
        
        return $returnCode === 0;
        
    } catch (Exception $e) {
        logWebhook('ERROR: Исключение при вызове createpayment.php', [
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

// Основная логика обработки
try {
    // Проверяем метод запроса
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        http_response_code(405);
        echo json_encode([
            'error' => 'Метод не разрешен',
            'details' => 'Поддерживается только POST метод'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Проверяем IP-адрес (если включена проверка)
    if (function_exists('isAllowedIP')) {
        $clientIP = $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? '';
        if (!isAllowedIP($clientIP)) {
            logWebhook('ERROR: Доступ запрещен с IP', ['ip' => $clientIP]);
            http_response_code(403);
            echo json_encode([
                'error' => 'Доступ запрещен',
                'details' => 'IP-адрес не разрешен'
            ], JSON_UNESCAPED_UNICODE);
            exit;
        }
    }
    
    // Проверяем аутентификацию
    if (!checkBasicAuth()) {
        http_response_code(401);
        echo json_encode([
            'error' => 'Ошибка аутентификации',
            'details' => 'Неверные credentials'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Получаем JSON данные
    $input = file_get_contents('php://input');
    $data = json_decode($input, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        logWebhook('ERROR: Неверный JSON формат', ['input' => $input]);
        http_response_code(400);
        echo json_encode([
            'error' => 'Неверный формат данных',
            'details' => 'Ожидается JSON формат'
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Валидируем данные
    $validationErrors = validateCallbackData($data);
    
    if (!empty($validationErrors)) {
        logWebhook('ERROR: Ошибки валидации', $validationErrors);
        http_response_code(400);
        echo json_encode([
            'error' => 'Неверный формат данных',
            'details' => implode(', ', $validationErrors)
        ], JSON_UNESCAPED_UNICODE);
        exit;
    }
    
    // Обрабатываем callback
    if (processCallback($data)) {
        // Успешный ответ
        http_response_code(200);
        echo json_encode([
            'message' => 'Callback успешно обработан',
            'transactionId' => $data['transactionId'],
            'receivedAt' => date('Y-m-d\TH:i:s\Z')
        ], JSON_UNESCAPED_UNICODE);
        
        logWebhook('SUCCESS: Callback обработан', [
            'transactionId' => $data['transactionId'],
            'status' => $data['status']
        ]);
    } else {
        // Ошибка обработки
        http_response_code(500);
        echo json_encode([
            'error' => 'Ошибка обработки',
            'details' => 'Не удалось обработать callback'
        ], JSON_UNESCAPED_UNICODE);
        
        logWebhook('ERROR: Ошибка обработки callback', $data);
    }
    
} catch (Exception $e) {
    logWebhook('ERROR: Исключение', ['message' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
    
    http_response_code(500);
    echo json_encode([
        'error' => 'Внутренняя ошибка сервера',
        'details' => 'Произошла непредвиденная ошибка'
    ], JSON_UNESCAPED_UNICODE);
}
?>
