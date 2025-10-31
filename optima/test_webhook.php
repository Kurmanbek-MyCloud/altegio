<?php
/**
 * Тестовый скрипт для проверки работы вебхука
 */

// URL вашего вебхука
$webhookUrl = 'https://zentara.billing.mycloud.kg/payment_services/optima/webhook.php'; // Замените на ваш URL

// Тестовые данные для успешного платежа
$successData = [
    'status' => 'success',
    'sum' => 1500.50,
    'note' => '373933', // Лицевой счет для тестирования
    'transactionId' => 'TXN_' . time(),
    'transactionProcessedDateTime' => date('Y-m-d\TH:i:s\Z'),
    'merchantId' => 'MERCH001',
    'orderId' => 'ORDER_' . time(),
    'currency' => 'KG',
    'paymentMethod' => 'card',
    'cardMask' => '****1234',
    'cardType' => 'VISA',
    'approvalCode' => 'APP' . rand(100000, 999999),
    'rrn' => 'RRN' . rand(100000000, 999999999),
    'stan' => rand(100000, 999999),
    'terminalId' => 'TERM001',
    'merchantName' => 'Test Merchant',
    'merchantCategoryCode' => '5411',
    'acquirerId' => 'ACQ001',
    'issuerId' => 'ISS001',
    'responseCode' => '00',
    'responseMessage' => 'Approved',
    'additionalData' => [
        'customerEmail' => 'test@example.com',
        'customerPhone' => '+77001234567',
        'accountNumber' => '373933' // Дублируем лицевой счет в дополнительных данных
    ]
];

// Тестовые данные для неуспешного платежа
$failedData = [
    'status' => 'failed',
    'sum' => 500.00,
    'note' => '373934', // Лицевой счет для тестирования неуспешного платежа
    'transactionId' => 'TXN_' . (time() + 1),
    'transactionProcessedDateTime' => date('Y-m-d\TH:i:s\Z'),
    'merchantId' => 'MERCH001',
    'orderId' => 'ORDER_' . (time() + 1),
    'currency' => 'KG',
    'paymentMethod' => 'card',
    'cardMask' => '****5678',
    'cardType' => 'MASTERCARD',
    'terminalId' => 'TERM001',
    'merchantName' => 'Test Merchant',
    'merchantCategoryCode' => '5411',
    'acquirerId' => 'ACQ001',
    'issuerId' => 'ISS001',
    'responseCode' => '51',
    'responseMessage' => 'Insufficient funds',
    'errorCode' => '51',
    'errorMessage' => 'Недостаточно средств на карте',
    'additionalData' => [
        'customerEmail' => 'test2@example.com',
        'customerPhone' => '+77001234568',
        'accountNumber' => '373934' // Дублируем лицевой счет в дополнительных данных
    ]
];

/**
 * Отправка тестового запроса
 */
function sendTestRequest($url, $data, $testName) {
    echo "=== Тест: $testName ===\n";
    
    // Настройки Basic Auth
    $username = 'optima_webhook';
    $password = 'Opt1m@W3bh00k9w2!h';
    
    // Подготавливаем cURL
    $ch = curl_init();
    
    curl_setopt_array($ch, [
        CURLOPT_URL => $url,
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => json_encode($data),
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HTTPHEADER => [
            'Content-Type: application/json',
            'Authorization: Basic ' . base64_encode("$username:$password")
        ],
        CURLOPT_TIMEOUT => 30,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => false
    ]);
    
    // Выполняем запрос
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    
    curl_close($ch);
    
    // Выводим результат
    echo "HTTP код: $httpCode\n";
    
    if ($error) {
        echo "Ошибка cURL: $error\n";
    } else {
        echo "Ответ сервера:\n";
        $responseData = json_decode($response, true);
        if ($responseData) {
            echo json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n";
        } else {
            echo $response . "\n";
        }
    }
    
    echo "\n";
}

// Запускаем тесты
echo "Начинаем тестирование вебхука...\n\n";

// Тест успешного платежа
sendTestRequest($webhookUrl, $successData, 'Успешный платеж');

// Небольшая пауза между запросами
sleep(2);

// Тест неуспешного платежа
sendTestRequest($webhookUrl, $failedData, 'Неуспешный платеж');

echo "Тестирование завершено!\n";
echo "Проверьте файлы webhook.log и createpayment.log для детальной информации.\n";
?> 