<?php
/**
 * Страница с QR-кодом через библиотеку Endroid QR Code
 */

require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;

// Получаем данные для QR-кода
$qrText = 'https://optimabank.kg/index.php?lang=ru#00020101021132650011QR.Optima.2010321310161091820936130183110717608581202121302123336003207b42c7763694d0eb6692b89602ba48f52049999530341754062000005907ZENTARA63041A6B';

// Извлекаем данные из URL если это Optima
if (strpos($qrText, 'optimabank.kg') !== false && strpos($qrText, '#') !== false) {
    $hashIndex = strpos($qrText, '#');
    if ($hashIndex !== false) {
        $qrData = substr($qrText, $hashIndex + 1);
    } else {
        $qrData = $qrText;
    }
} else {
    $qrData = $qrText;
}

// Создаем QR-код с параметрами в конструкторе
$qrCode = new QrCode(
    data: $qrData,
    size: 200,
    margin: 10,
    errorCorrectionLevel: ErrorCorrectionLevel::High,
    foregroundColor: new Color(0, 0, 0),
    backgroundColor: new Color(255, 255, 255)
);

// Создаем writer для PNG
$writer = new PngWriter();

// Генерируем QR-код
$result = $writer->write($qrCode);

// Получаем Data URL для отображения
$dataUri = $result->getDataUri();
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-код через Endroid</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            padding: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .qr-code {
            text-align: center;
            margin: 20px 0;
            padding: 20px;
            border: 2px dashed #ccc;
            border-radius: 10px;
        }
        .qr-code-text {
            color: #666;
            font-size: 14px;
        }
        .test-info {
            background: #e3f2fd;
            padding: 15px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
        .qr-image {
            width: 200px;
            height: 200px;
            border: 1px solid #ccc;
            margin: 0 auto;
            display: block;
        }
        .download-btn {
            margin-top: 15px;
            padding: 10px 20px;
            background: #28a745;
            color: white;
            border: none;
            border-radius: 5px;
            cursor: pointer;
            text-decoration: none;
            display: inline-block;
        }
        .download-btn:hover {
            background: #218838;
        }
        .info-section {
            margin-top: 20px;
            padding: 15px;
            background: #f8f9fa;
            border-radius: 5px;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>QR-код через Endroid QR Code</h1>
        
        <div class="test-info">
            <h3>Тестовые данные:</h3>
            <p><strong>Исходный URL:</strong> <?= htmlspecialchars($qrText) ?></p>
            <p><strong>Данные для QR-кода:</strong> <?= htmlspecialchars($qrData) ?></p>
        </div>
        
        <div class="qr-code" id="qrCode">
            <div id="qrCanvas">
                <img src="<?= $dataUri ?>" alt="QR-код для оплаты" class="qr-image">
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <p>QR-код для оплаты</p>
            <a href="<?= $dataUri ?>" download="qr-code.png" class="download-btn">Скачать QR-код</a>
        </div>
        
        <div class="info-section">
            <h4>Информация о QR-коде:</h4>
            <ul>
                <li><strong>Размер:</strong> 200x200 пикселей</li>
                <li><strong>Отступы:</strong> 10 пикселей</li>
                <li><strong>Уровень коррекции ошибок:</strong> Высокий</li>
                <li><strong>Формат:</strong> PNG</li>
                <li><strong>Цвета:</strong> Черный на белом</li>
            </ul>
        </div>
    </div>
</body>
</html>
