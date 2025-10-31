<?php
/**
 * Тестовый файл для проверки исправлений QR-кода
 */

// Подключаем основной файл API
require_once 'payment_api.php';

// Тестовые данные
$testData = [
    'type' => 'subscription_payment',
    'amount' => 2000,
    'comment' => 'Оплата абонемента #12345',
    'numberClient' => '67890',
    'phone' => '+996700123456'
];

echo "Тестируем API для генерации QR-кода...\n";
echo "Данные: " . json_encode($testData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

// Вызываем функцию напрямую
$result = handleSubscriptionPaymentRequest($testData);

echo "Результат:\n";
echo json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n\n";

if ($result['success']) {
    echo "✅ QR-код успешно сгенерирован!\n";
    echo "QR-код: " . $result['data']['qr_code'] . "\n";
    
    // Проверяем, является ли QR-код изображением
    if (strpos($result['data']['qr_code'], 'data:image/') === 0) {
        echo "✅ QR-код в формате base64 изображения\n";
    } elseif (strpos($result['data']['qr_code'], 'http') === 0) {
        echo "✅ QR-код в формате URL\n";
    } else {
        echo "❌ Неизвестный формат QR-кода\n";
    }
} else {
    echo "❌ Ошибка генерации QR-кода: " . $result['error'] . "\n";
}
?>
