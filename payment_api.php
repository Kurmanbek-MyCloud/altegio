<?php
/**
 * API endpoint для обработки запросов от платежных систем
 * Генерирует ссылки на страницу оплаты с диплинками
 */

// Подключаем Endroid QR Code
require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;

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
function logPayment($message) {
    $timestamp = date('Y-m-d H:i:s');
    $logEntry = "[$timestamp] PAYMENT_API: $message" . PHP_EOL;
    file_put_contents('payment_api.log', $logEntry, FILE_APPEND | LOCK_EX);
}

// Функция для отправки ответа
function sendResponse($success, $data = null, $error = null, $httpCode = 200) {
    http_response_code($httpCode);
    
    if ($success && $data) {
        // Если успешно и есть данные, объединяем их
        $response = array_merge(['success' => $success], $data);
    } else {
        $response = [
            'success' => $success,
            'timestamp' => date('Y-m-d H:i:s'),
            'error' => $error
        ];
    }
    
    echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    exit();
}

// Проверяем метод запроса
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    logPayment("Invalid method: " . $_SERVER['REQUEST_METHOD']);
    sendResponse(false, null, 'Only POST method is allowed', 405);
}

// Получаем данные запроса
$input = file_get_contents('php://input');
$requestData = json_decode($input, true);

if (!$requestData) {
    logPayment("Invalid JSON data received");
    sendResponse(false, null, 'Invalid JSON data', 400);
}

logPayment("Received request: " . json_encode($requestData));

try {
    // Обрабатываем запрос в зависимости от типа
    $result = processPaymentRequest($requestData);
    
    if ($result['success']) {
        logPayment("Request processed successfully: " . json_encode($result));
        sendResponse(true, $result, null);
    } else {
        logPayment("Error processing request: " . $result['error']);
        sendResponse(false, null, $result['error'], 400);
    }
    
} catch (Exception $e) {
    logPayment("Exception: " . $e->getMessage());
    sendResponse(false, null, 'Internal server error', 500);
}

/**
 * Обработка платежного запроса
 */
function processPaymentRequest($data) {
    // Проверяем тип запроса
    $requestType = $data['type'] ?? '';
    
    switch ($requestType) {
        case 'altegio_payment':
            return handleAltegioPaymentRequest($data);
            
        case 'subscription_payment':
            return handleSubscriptionPaymentRequest($data);
            
        case 'custom_payment':
            return handleCustomPaymentRequest($data);
            
        default:
            return ['success' => false, 'error' => 'Unknown request type: ' . $requestType];
    }
}

/**
 * Обработка запроса от Altegio
 */
function handleAltegioPaymentRequest($data) {
    $comment = $data['comment'] ?? '';
    $amount = $data['amount'] ?? 0;
    $numberClient = $data['numberClient'] ?? '';
    
    if (!$amount || !$numberClient) {
        return ['success' => false, 'error' => 'Missing required parameters: amount and numberClient'];
    }
    
    logPayment("Processing Altegio payment request: amount=$amount, numberClient=$numberClient, comment=$comment");
    
    // Генерируем QR-код через Optima API
    $qrCodeData = generateOptimaQR($amount, $comment, $numberClient);
    
    // Создаем ссылку на страницу оплаты с полной ссылкой Optima для генерации QR-кода
    $paymentPageUrl = createPaymentPageUrl([
        'amount' => $amount,
        'comment' => $comment,
        'numberClient' => $numberClient,
        'type' => 'altegio_payment',
        'qr_code' => $qrCodeData['qr_code'] ?? '' // Передаем полную ссылку Optima
    ]);
    
    return [
        'success' => true,
        'payment_page_url' => $paymentPageUrl,
        'qr_code' => $qrCodeData['qr_code'],
        'qr_hash' => $qrCodeData['qr_hash'] ?? '',
        'qr_source' => $qrCodeData['success'] ? 'Optima API' : 'Fallback (QR Server)'
    ];
}

/**
 * Обработка запроса на оплату абонемента
 */
function handleSubscriptionPaymentRequest($data) {
    $amount = $data['amount'] ?? 0;
    $phone = $data['phone'] ?? '';
    $subscriptionId = $data['subscription_id'] ?? '';
    $clientId = $data['client_id'] ?? '';
    $type = $data['type'] ?? 'payment';
    
    if (!$amount || !$phone || !$subscriptionId || !$clientId) {
        return ['success' => false, 'error' => 'Missing required parameters'];
    }
    
    logPayment("Processing subscription payment: amount=$amount, subscription=$subscriptionId");
    
    // Создаем ссылку на страницу оплаты
    $paymentPageUrl = createPaymentPageUrl([
        'amount' => $amount,
        'phone' => $phone,
        'subscription_id' => $subscriptionId,
        'client_id' => $clientId,
        'type' => $type
    ]);
    
    // Создаем базовые диплинки
    $deeplinks = createPaymentDeeplinks('', $subscriptionId);
    
    return [
        'success' => true,
        'data' => [
            'payment_page_url' => $paymentPageUrl,
            'deeplinks' => $deeplinks,
            'amount' => $amount,
            'phone' => $phone,
            'subscription_id' => $subscriptionId,
            'client_id' => $clientId,
            'type' => $type
        ]
    ];
}

/**
 * Обработка кастомного платежного запроса
 */
function handleCustomPaymentRequest($data) {
    $amount = $data['amount'] ?? 0;
    $phone = $data['phone'] ?? '';
    $description = $data['description'] ?? '';
    $qrCode = $data['qr_code'] ?? '';
    
    if (!$amount || !$phone) {
        return ['success' => false, 'error' => 'Missing required parameters'];
    }
    
    logPayment("Processing custom payment: amount=$amount, description=$description");
    
    // Создаем ссылку на страницу оплаты
    $paymentPageUrl = createPaymentPageUrl([
        'amount' => $amount,
        'phone' => $phone,
        'description' => $description,
        'qr_code' => $qrCode,
        'type' => 'custom'
    ]);
    
    // Создаем диплинки
    $deeplinks = createPaymentDeeplinks($qrCode, 'CUSTOM');
    
    return [
        'success' => true,
        'data' => [
            'payment_page_url' => $paymentPageUrl,
            'deeplinks' => $deeplinks,
            'amount' => $amount,
            'phone' => $phone,
            'description' => $description,
            'qr_code' => $qrCode
        ]
    ];
}

/**
 * Создание URL страницы оплаты
 */
function createPaymentPageUrl($params) {
    $baseUrl = 'https://nbfit.mycloud.kg/payment_page.php';
    
    // Фильтруем пустые параметры и правильно кодируем
    $cleanParams = [];
    foreach ($params as $key => $value) {
        if (!empty($value) || $value === '0') {
            $cleanParams[$key] = $value;
        }
    }
    
    // Создаем URL вручную для лучшего контроля
    $queryString = '';
    foreach ($cleanParams as $key => $value) {
        if ($queryString !== '') {
            $queryString .= '&';
        }
        $queryString .= urlencode($key) . '=' . urlencode($value);
    }
    
    return $baseUrl . '?' . $queryString;
}

/**
 * Генерация QR-кода через Optima Business API
 */
function generateOptimaQR($amount, $comment, $numberClient) {
    // $apiUrl = 'https://test-ob.optimabank.kg/api/v1/generate/qr';
    // $apiKey = '62fcde26-7df6-47a8-a2eb-53e6f5006449';
    $apiUrl = 'https://api.optimabusiness.kg/api/v1/generate/qr';
    $apiKey = '5de46987-77c6-4065-b174-1e6aee2a15dc';
    
    // $requestData = [
    //     'account' => 1091820936130183,
    //     'sum' => $amount,
    //     'note' => $numberClient,
    //     'qrType' => 'png',
    //     'qrSize' => 300
    // ];

    $requestData = [
        'account' => 1090806734810172,
        'sum' => $amount,
        'note' => $numberClient,
        'qrType' => 'png',
        'qrSize' => 300
    ];
    
    // Логируем запрос к Optima API
    $logMessage = "=== OPTIMA QR API REQUEST ===\n";
    $logMessage .= "URL: $apiUrl\n";
    $logMessage .= "API Key: $apiKey\n";
    $logMessage .= "Request Data: " . json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
    $logMessage .= "Timestamp: " . date('Y-m-d H:i:s') . "\n";
    $logMessage .= "===============================\n";
    file_put_contents('generate_qr_optima.log', $logMessage, FILE_APPEND | LOCK_EX);
    
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $apiUrl);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($requestData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'X-API-KEY: ' . $apiKey,
        'Content-Type: application/json'
    ]);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_VERBOSE, true);
    
    // Логируем cURL детали
    $verbose = fopen('php://temp', 'w+');
    curl_setopt($ch, CURLOPT_STDERR, $verbose);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    $curlInfo = curl_getinfo($ch);
    curl_close($ch);
    
    // Логируем ответ от Optima API
    $logMessage = "=== OPTIMA QR API RESPONSE ===\n";
    $logMessage .= "HTTP Code: $httpCode\n";
    $logMessage .= "cURL Error: " . ($curlError ?: 'None') . "\n";
    $logMessage .= "Response: $response\n";
    $logMessage .= "cURL Info: " . json_encode($curlInfo, JSON_PRETTY_PRINT) . "\n";
    
    // Логируем verbose cURL информацию
    rewind($verbose);
    $verboseLog = stream_get_contents($verbose);
    $logMessage .= "Verbose cURL Log:\n$verboseLog\n";
    
    $logMessage .= "===============================\n\n";
    file_put_contents('generate_qr_optima.log', $logMessage, FILE_APPEND | LOCK_EX);
    fclose($verbose);
    
    if ($httpCode === 200 && $response) {
        // Проверяем, не содержит ли ответ HTML ошибки
        if (strpos($response, '<br />') !== false || strpos($response, '<b>') !== false) {
            $errorLog = "❌ ERROR: Optima API returned HTML error instead of JSON\n";
            $errorLog .= "Response contains HTML tags - API error\n\n";
            file_put_contents('generate_qr_optima.log', $errorLog, FILE_APPEND | LOCK_EX);
            
            // Возвращаем fallback QR-код как URL
            return [
                'success' => false,
                'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PAYMENT_$numberClient",
                'qr_hash' => "PAYMENT_$numberClient"
            ];
        }
        
        $responseData = json_decode($response, true);
        
        // Логируем полный ответ для отладки
        $debugLog = "🔍 DEBUG: Full response data:\n";
        $debugLog .= json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";
        file_put_contents('generate_qr_optima.log', $debugLog, FILE_APPEND | LOCK_EX);
        
        // Проверяем разные возможные поля для QR-кода
        if ($responseData && isset($responseData['qrUrl'])) {
            // Логируем успешный QR-код
            $successLog = "✅ SUCCESS: QR code generated from Optima API (qrUrl)\n";
            $successLog .= "QR URL: " . $responseData['qrUrl'] . "\n\n";
            file_put_contents('generate_qr_optima.log', $successLog, FILE_APPEND | LOCK_EX);
            
            // Проверяем, является ли qrUrl уже полной ссылкой
            $qrUrl = $responseData['qrUrl'];
            if (str_starts_with($qrUrl, 'http')) {
                // Если это уже полная ссылка, используем как есть
                $fullOptimaUrl = $qrUrl;
                // Извлекаем хеш для диплинков
                $hashIndex = strpos($qrUrl, '#');
                $qrHash = $hashIndex !== false ? substr($qrUrl, $hashIndex + 1) : $qrUrl;
            } else {
                // Если это только хеш, формируем полную ссылку
                $fullOptimaUrl = "https://optimabank.kg/index.php?lang=ru#" . $qrUrl;
                $qrHash = $qrUrl;
            }
            
            return [
                'success' => true,
                'qr_code' => $fullOptimaUrl, // Возвращаем URL, а не base64
                'qr_hash' => $qrHash // Хеш для диплинков
            ];
        } elseif ($responseData && isset($responseData['qr_code'])) {
            // Логируем успешный QR-код
            $successLog = "✅ SUCCESS: QR code generated from Optima API (qr_code)\n";
            $successLog .= "QR Code: " . $responseData['qr_code'] . "\n\n";
            file_put_contents('generate_qr_optima.log', $successLog, FILE_APPEND | LOCK_EX);
            
            // Проверяем, является ли это уже полным URL
            $qrData = $responseData['qr_code'];
            if (!str_starts_with($qrData, 'http')) {
                // Если это только хеш, формируем полный URL
                $fullOptimaUrl = "https://optimabank.kg/index.php?lang=ru#" . $qrData;
            } else {
                $fullOptimaUrl = $qrData;
            }
            
            return [
                'success' => true,
                'qr_code' => $fullOptimaUrl, // Возвращаем URL, а не base64
                'qr_hash' => $qrData // Хеш для диплинков
            ];
        } elseif ($responseData && isset($responseData['qrBase64'])) {
            // Логируем, что qrBase64 найден, но не используем его
            $successLog = "⚠️ WARNING: qrBase64 found but not used (as requested)\n";
            $successLog .= "QR Base64 length: " . strlen($responseData['qrBase64']) . "\n";
            $successLog .= "Using fallback instead\n\n";
            file_put_contents('generate_qr_optima.log', $successLog, FILE_APPEND | LOCK_EX);
            
            // Не используем qrBase64, возвращаем fallback
            return [
                'success' => false,
                'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PAYMENT_$numberClient",
                'qr_hash' => "PAYMENT_$numberClient"
            ];
        }
        
        // Добавляем детальную отладку для понимания структуры ответа
        $detailedDebug = "🔍 DETAILED DEBUG: Response structure analysis:\n";
        $detailedDebug .= "Response type: " . gettype($response) . "\n";
        $detailedDebug .= "Response length: " . strlen($response) . "\n";
        $detailedDebug .= "First 200 chars: " . substr($response, 0, 200) . "\n";
        $detailedDebug .= "Last 200 chars: " . substr($response, -200) . "\n";
        $detailedDebug .= "JSON decode result: " . (json_last_error() === JSON_ERROR_NONE ? 'SUCCESS' : 'ERROR: ' . json_last_error_msg()) . "\n";
        if (is_array($responseData)) {
            $detailedDebug .= "ResponseData keys: " . implode(', ', array_keys($responseData)) . "\n";
            foreach ($responseData as $key => $value) {
                $detailedDebug .= "  $key: " . (is_string($value) ? substr($value, 0, 100) : gettype($value)) . "\n";
            }
        }
        $detailedDebug .= "\n";
        file_put_contents('generate_qr_optima.log', $detailedDebug, FILE_APPEND | LOCK_EX);
        
        // Добавляем дополнительную отладку
        $debugLog2 = "🔍 DEBUG: Checking responseData structure:\n";
        $debugLog2 .= "responseData is array: " . (is_array($responseData) ? 'YES' : 'NO') . "\n";
        if (is_array($responseData)) {
            $debugLog2 .= "Available keys: " . implode(', ', array_keys($responseData)) . "\n";
            $debugLog2 .= "qrUrl exists: " . (isset($responseData['qrUrl']) ? 'YES' : 'NO') . "\n";
            $debugLog2 .= "qr_code exists: " . (isset($responseData['qr_code']) ? 'YES' : 'NO') . "\n";
        }
        $debugLog2 .= "\n";
        file_put_contents('generate_qr_optima.log', $debugLog2, FILE_APPEND | LOCK_EX);
    }
    
    // Если не удалось получить QR-код, логируем ошибку и возвращаем fallback
    $errorLog = "❌ FAILED: Could not get QR code from Optima API\n";
    $errorLog .= "Using fallback QR code\n\n";
    file_put_contents('generate_qr_optima.log', $errorLog, FILE_APPEND | LOCK_EX);
    
            // Возвращаем fallback QR-код как URL
        return [
            'success' => false,
            'qr_code' => "https://api.qrserver.com/v1/create-qr-code/?size=200x200&data=PAYMENT_$numberClient",
            'qr_hash' => "PAYMENT_$numberClient"
        ];
}

/**
 * Создание диплинков для платежных систем
 */
function createPaymentDeeplinks($qrCode, $subscriptionId) {
    $deeplinks = [
        'mbank' => [
            'name' => 'MBank',
            'url' => "https://app.mbank.kg/qr/#" . ($qrCode ?: "PAYMENT_$subscriptionId"),
            'icon' => '🏦',
            'color' => '#1E3A8A'
        ],
        'odengi' => [
            'name' => 'ODengi',
            'url' => "https://api.dengi.o.kg/#" . ($qrCode ?: "PAYMENT_$subscriptionId"),
            'icon' => '💰',
            'color' => '#059669'
        ],
        'balance' => [
            'name' => 'Balance',
            'url' => "https://balance.kg/payment_qr/#" . ($qrCode ?: "PAYMENT_$subscriptionId"),
            'icon' => '⚖️',
            'color' => '#DC2626'
        ],
        'optima' => [
            'name' => 'Optima Bank',
            'url' => "https://optimabank.kg/index.php?lang=ru#" . ($qrCode ?: "PAYMENT_$subscriptionId"),
            'icon' => '🏛️',
            'color' => '#7C3AED'
        ]
    ];
    
    return $deeplinks;
}

/**
 * Пример использования API
 */
function getUsageExamples() {
    return [
        'altegio_payment' => [
            'description' => 'Запрос от Altegio на создание платежа',
            'example' => [
                'type' => 'altegio_payment',
                'comment' => 'Оплата абонемента #12345',
                'amount' => 2000,
                'numberClient' => '67890'
            ]
        ],
        'subscription_payment' => [
            'description' => 'Запрос на оплату абонемента',
            'example' => [
                'type' => 'subscription_payment',
                'amount' => 1500,
                'phone' => '+79001234567',
                'subscription_id' => '12345',
                'client_id' => '67890',
                'type' => 'renewal'
            ]
        ]
    ];
}

// Если запрос GET, показываем примеры использования
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $examples = getUsageExamples();
    sendResponse(true, [
        'message' => 'Payment API is working',
        'usage_examples' => $examples,
        'endpoints' => [
            'POST /payment_api.php' => 'Process payment requests'
        ]
    ]);
}
?>