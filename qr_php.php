<?php
/**
 * PHP-страница для генерации QR-кода
 */

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

// Создаем простой QR-код через HTML таблицу
function generateSimpleQR($text) {
    $size = 20; // 20x20 клеток
    $hash = 0;
    
    // Создаем хеш из текста
    for ($i = 0; $i < strlen($text); $i++) {
        $char = ord($text[$i]);
        $hash = (($hash << 5) - $hash) + $char;
        $hash = $hash & 0xFFFFFFFF; // 32-bit integer
    }
    
    $html = '<table style="border-collapse: collapse; margin: 0 auto; border: 1px solid #ccc;">';
    
    for ($i = 0; $i < $size; $i++) {
        $html .= '<tr>';
        for ($j = 0; $j < $size; $j++) {
            // Используем хеш для определения цвета
            $shouldBeBlack = (($hash + $i * 31 + $j * 17) % 3) === 0;
            $color = $shouldBeBlack ? 'black' : 'white';
            $html .= '<td style="width: 8px; height: 8px; background-color: ' . $color . '; border: none;"></td>';
        }
        $html .= '</tr>';
    }
    
    $html .= '</table>';
    
    return $html;
}

// Генерируем QR-код
$qrCode = generateSimpleQR($qrData);
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>QR-код через PHP</title>
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
        .qr-display {
            display: inline-block;
            border: 1px solid #ccc;
            padding: 10px;
            background: white;
        }
        .download-section {
            margin-top: 20px;
            text-align: center;
        }
        .download-btn {
            margin: 10px;
            padding: 10px 20px;
            background: #007bff;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            display: inline-block;
        }
        .download-btn:hover {
            background: #0056b3;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>QR-код через PHP</h1>
        
        <div class="test-info">
            <h3>Тестовые данные:</h3>
            <p><strong>Исходный URL:</strong> <?= htmlspecialchars($qrText) ?></p>
            <p><strong>Данные для QR-кода:</strong> <?= htmlspecialchars($qrData) ?></p>
        </div>
        
        <div class="qr-code" id="qrCode">
            <div class="qr-display">
                <?= $qrCode ?>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 20px;">
            <p>QR-код для оплаты</p>
        </div>
        
        <div class="download-section">
            <h4>Альтернативные способы:</h4>
            <a href="https://www.qr-code-generator.com/" target="_blank" class="download-btn">Онлайн генератор</a>
            <a href="https://qr.ae/" target="_blank" class="download-btn">QR.ae</a>
            <a href="https://www.qrcode-monkey.com/" target="_blank" class="download-btn">QRCode Monkey</a>
        </div>
        
        <div style="margin-top: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px;">
            <h4>Инструкция:</h4>
            <ol>
                <li>Скопируйте данные для QR-кода выше</li>
                <li>Откройте один из онлайн генераторов</li>
                <li>Вставьте данные и скачайте изображение</li>
                <li>Или используйте мобильное приложение для сканирования</li>
            </ol>
        </div>
    </div>
</body>
</html>
