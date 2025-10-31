<?php
/**
 * Простой тест передачи данных между скриптами
 */

// Тестовые данные
$testData = [
    'status' => 'success',
    'sum' => 100.50,
    'note' => '373933',
    'transactionId' => 'TEST_' . time(),
    'transactionProcessedDateTime' => date('Y-m-d\TH:i:s\Z'),
    'currency' => 'KG'
];

echo "=== Тест передачи данных ===\n";
echo "Исходные данные: " . json_encode($testData, JSON_UNESCAPED_UNICODE) . "\n\n";

// Кодируем данные
$jsonData = json_encode($testData, JSON_UNESCAPED_UNICODE);
$encodedData = base64_encode($jsonData);

echo "Закодированные данные: $encodedData\n\n";

// Тестируем команду
$command = "WEBHOOK_DATA=" . escapeshellarg($encodedData) . " php createpayment.php";

echo "Команда: $command\n\n";

// Выполняем команду
$output = [];
$returnCode = 0;
exec($command . " 2>&1", $output, $returnCode);

echo "Код возврата: $returnCode\n";
echo "Вывод:\n";
foreach ($output as $line) {
    echo "  $line\n";
}

echo "\n=== Тест завершен ===\n";
?> 