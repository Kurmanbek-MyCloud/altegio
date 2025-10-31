<?php
/**
 * Простой тест API endpoint
 */

echo "🧪 Тест API endpoint для создания платежей\n";
echo "==========================================\n\n";

// Данные для тестирования
$testData = [
    'client_id' => 172280244,
    'amount' => 2000.00,
    'description' => 'Тестовый платеж через API endpoint',
    'payment_method' => 'card',
    'currency' => 'RUB'
];

echo "Отправляем данные:\n";
echo json_encode($testData, JSON_PRETTY_PRINT) . "\n\n";

// Отправляем запрос к нашему API
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, 'http://localhost:8000/api_endpoint.php');
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($testData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 30);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

echo "HTTP Code: $httpCode\n";
if ($error) {
    echo "cURL Error: $error\n";
}

echo "Ответ:\n";
echo $response . "\n\n";

// Парсим ответ
$result = json_decode($response, true);

if ($result && $result['success']) {
    echo "✅ УСПЕХ! Платеж создан через API endpoint!\n";
    echo "ID платежа: " . ($result['data']['payment_id'] ?? 'N/A') . "\n";
    echo "Сумма: " . ($result['data']['amount'] ?? 'N/A') . " RUB\n";
    echo "Клиент ID: " . ($result['data']['client_id'] ?? 'N/A') . "\n";
} else {
    echo "❌ Ошибка создания платежа\n";
    if ($result) {
        echo "Ошибка: " . ($result['error'] ?? 'Unknown error') . "\n";
    }
}

echo "\n🏁 Тестирование завершено!\n";
?> 