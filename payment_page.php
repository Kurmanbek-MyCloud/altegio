<?php
/**
 * Страница оплаты с диплинками на платежные системы
 * Обрабатывает данные из webhook'а и создает ссылки для оплаты
 */

// Подключаем Endroid QR Code
require_once 'vendor/autoload.php';

use Endroid\QrCode\QrCode;
use Endroid\QrCode\Writer\PngWriter;
use Endroid\QrCode\Color\Color;
use Endroid\QrCode\ErrorCorrectionLevel;

// Получаем данные из GET параметров
$amount = $_GET['amount'] ?? 0;
$comment = $_GET['comment'] ?? '';
$numberClient = $_GET['numberClient'] ?? '';
$type = $_GET['type'] ?? 'payment';

// Форматируем данные
$amount = floatval($amount);
$formattedAmount = number_format($amount, 0, '.', ' ');
$comment = htmlspecialchars($comment);
$numberClient = htmlspecialchars($numberClient);
$type = htmlspecialchars($type);

// Определяем тип устройства
function isMobile() {
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    return preg_match('/(android|iphone|ipad|mobile|tablet)/i', $userAgent);
}

$isMobile = isMobile();

// Получаем QR-код и хеш из параметров (если передан от API)
$qrCode = $_GET['qr_code'] ?? '';
$qrHash = $_GET['qr_hash'] ?? '';

// Отладочная информация
echo "<!-- DEBUG: Полученный qrCode: " . htmlspecialchars($qrCode) . " -->";
echo "<!-- DEBUG: Полученный qrHash: " . htmlspecialchars($qrHash) . " -->";
echo "<!-- DEBUG: Тип устройства: " . ($isMobile ? 'Мобильное' : 'Десктоп') . " -->";

// Если QR-код не передан, генерируем его из данных клиента
if (empty($qrCode)) {
    // Создаем QR-код из данных клиента
    $qrData = "PAYMENT_$numberClient";
    
    try {
        $qrCodeObj = new QrCode(
            data: $qrData,
            size: 180,
            margin: 5,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );
        
        $writer = new PngWriter();
        $result = $writer->write($qrCodeObj);
        $qrCode = $result->getDataUri();
        $qrHash = "PAYMENT_$numberClient";
    } catch (Exception $e) {
        // Если не удалось сгенерировать, оставляем пустым
        $qrCode = '';
        $qrHash = "PAYMENT_$numberClient";
    }
} elseif (strpos($qrCode, 'http') === 0) {
    // Если QR-код - это URL, генерируем QR-код из него
    try {
        // Отладочная информация - показываем, из какой ссылки генерируется QR
        echo "<!-- DEBUG: Генерируем QR-код из ссылки: " . htmlspecialchars($qrCode) . " -->";
        
        // Извлекаем хеш из URL для диплинков (делаем это ДО генерации QR-кода)
        $hashIndex = strpos($qrCode, '#');
        if ($hashIndex !== false) {
            $qrHash = substr($qrCode, $hashIndex + 1);
            echo "<!-- DEBUG: Извлеченный хеш для диплинков: " . htmlspecialchars($qrHash) . " -->";
        }
        
        $qrCodeObj = new QrCode(
            data: $qrCode,
            size: 300, // Увеличиваем размер для лучшего сканирования
            margin: 10, // Увеличиваем отступы
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            foregroundColor: new Color(0, 0, 0),
            backgroundColor: new Color(255, 255, 255)
        );
        
        $writer = new PngWriter();
        $result = $writer->write($qrCodeObj);
        $qrCode = $result->getDataUri();
    } catch (Exception $e) {
        // Если не удалось сгенерировать, оставляем URL как есть
        echo "<!-- DEBUG: Ошибка генерации QR-кода: " . htmlspecialchars($e->getMessage()) . " -->";
    }
}

// Генерируем диплинки для платежных систем с QR-кодом
// Создаем зашифрованные ссылки для защиты от инспектора
$paymentLinks = [
    'optima' => [
        'name' => 'Optima Bank',
        'url' => base64_encode("https://optimabank.kg/index.php?lang=ru#$qrHash"),
        'icon' => 'image_pay_system/optima.png',
        'color' => '#EF4444'
    ],
    'mbank' => [
        'name' => 'MBank',
        'url' => base64_encode("https://app.mbank.kg/qr/#$qrHash"),
        'icon' => 'image_pay_system/mbank.jpeg',
        'color' => '#10B981'
    ],
    'odengi' => [
        'name' => 'ODengi',
        'url' => base64_encode("https://api.dengi.o.kg/#$qrHash"),
        'icon' => 'image_pay_system/odengi.png',
        'color' => '#EC4899'
    ],
    'balance' => [
        'name' => 'Balance',
        'url' => base64_encode("https://balance.kg/payment_qr/#$qrHash"),
        'icon' => 'image_pay_system/balance_kg.png',
        'color' => '#F59E0B'
    ],
    'bakai' => [
        'name' => 'BakaiBank',
        'url' => base64_encode("https://bakai24.app/#$qrHash"),
        'icon' => 'image_pay_system/bakai_bank.png',
        'color' => '#3B82F6'
    ],
    'demir' => [
        'name' => 'Demir Bank',
        'url' => base64_encode("https://apps.demirbank.kg/ib//#$qrHash"),
        'icon' => 'image_pay_system/demir_bank.png',
        'color' => '#7C2D12'
    ],
    'kicb' => [
        'name' => 'KICB',
        'url' => base64_encode("https://bank.kicb.net/#$qrHash"),
        'icon' => 'image_pay_system/KICB.png',
        'color' => '#1E40AF'
    ]
];
?>
<!DOCTYPE html>
<html lang="ru">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Оплата абонемента</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: url('image_pay_system/background.png') center center / cover no-repeat fixed;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.8) 0%, rgba(118, 75, 162, 0.8) 100%);
            z-index: -1;
        }
        
        .container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            box-shadow: 0 20px 40px rgba(0,0,0,0.2);
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header {
            background: linear-gradient(135deg, #1a1a2e 0%, #16213e 50%, #0f3460 100%);
            color: white;
            padding: 40px 30px;
            text-align: center;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(45deg, rgba(255,255,255,0.1) 0%, transparent 50%, rgba(0,0,0,0.1) 100%);
            pointer-events: none;
        }
        
        .header h1 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 600;
            position: relative;
            z-index: 1;
        }
        
        .header .subtitle {
            font-size: 16px;
            opacity: 0.9;
            position: relative;
            z-index: 1;
        }
        
        .payment-info {
            padding: 30px;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .amount {
            font-size: 48px;
            font-weight: 700;
            color: #1e293b;
            text-align: center;
            margin-bottom: 20px;
        }
        
        .amount .currency {
            font-size: 24px;
            color: #64748b;
        }
        

        

        
        .qr-section {
            padding: 30px;
            text-align: center;
            background: white;
            border-bottom: 1px solid #e2e8f0;
        }
        
        .qr-code {
            width: 200px;
            height: 200px;
            margin: 0 auto 20px;
            background: #f1f5f9;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 2px solid #cbd5e1;
        }
        
        .qr-code-text {
            font-family: monospace;
            font-size: 12px;
            color: #1e293b;
            text-align: center;
            word-break: break-all;
            padding: 10px;
            background: white;
            border-radius: 8px;
            border: 1px solid #e2e8f0;
            max-width: 180px;
        }
        
        .qr-text {
            font-size: 14px;
            color: #64748b;
            margin-bottom: 20px;
        }
        
        .payment-methods {
            padding: 30px;
        }
        
        .payment-methods h3 {
            font-size: 20px;
            color: #1e293b;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .payment-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        
        .payment-method {
            display: flex;
            align-items: center;
            padding: 20px;
            background: white;
            border: 2px solid #e2e8f0;
            border-radius: 16px;
            text-decoration: none;
            color: inherit;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }
        
        .payment-method.primary {
            border: 3px solid #EF4444;
            box-shadow: 0 0 0 1px rgba(239, 68, 68, 0.2);
        }
        
        .payment-method:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            border-color: var(--color);
        }
        
        .payment-method.primary:hover {
            border-color: #DC2626;
            box-shadow: 0 10px 25px rgba(239, 68, 68, 0.3);
        }
        
        .payment-method::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 3px;
            background: var(--color);
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .payment-method:hover::before {
            transform: scaleX(1);
        }
        
        .payment-icon {
            font-size: 32px;
            margin-right: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            width: 32px;
            height: 32px;
        }
        
        .payment-info {
            flex: 1;
        }
        
        .payment-name {
            font-size: 16px;
            font-weight: 600;
            color: #1e293b;
            margin-bottom: 2px;
        }
        
        .payment-desc {
            font-size: 12px;
            color: #64748b;
        }
        
        .footer {
            padding: 20px 30px;
            background: #f8fafc;
            text-align: center;
            border-top: 1px solid #e2e8f0;
        }
        
        .footer-text {
            font-size: 12px;
            color: #64748b;
        }
        
        @media (max-width: 768px) {
            .container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header {
                padding: 30px 20px;
            }
            
            .header h1 {
                font-size: 24px;
            }
            
            .amount {
                font-size: 36px;
            }
            
            .details {
                grid-template-columns: 1fr;
                gap: 15px;
            }
            
            .payment-grid {
                grid-template-columns: 1fr;
            }
            
            .payment-method {
                padding: 15px;
            }
        }
        
        .copy-button {
            background: #667eea;
            color: white;
            border: none;
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 12px;
            cursor: pointer;
            margin-top: 10px;
            transition: background 0.3s ease;
        }
        
        .copy-button:hover {
            background: #5a67d8;
        }
        
        .copy-success {
            background: #10b981 !important;
        }
        
        .download-qr-btn {
            background: linear-gradient(135deg, #64748b 0%, #475569 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 15px;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(100, 116, 139, 0.2);
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        
        .download-qr-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(100, 116, 139, 0.3);
            background: linear-gradient(135deg, #475569 0%, #334155 100%);
        }
        
        .download-qr-btn:active {
            transform: translateY(0);
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Заголовок -->
        <div class="header">
            <div style="text-align: center; margin-bottom: 20px;">
                <div style="display: inline-block; background: rgba(255, 255, 255, 0.15); padding: 15px; border-radius: 15px; margin-bottom: 15px;">
                    <img src="image_pay_system/nbfit_logo.png" alt="NBFit" style="width: 80px; height: 80px; object-fit: contain;">
                </div>
            </div>
            <h1 style="margin: 0; font-size: 28px; font-weight: 600;">
                Оплата абонемента
            </h1>
            <div class="subtitle">Выберите удобный способ оплаты</div>
        </div>
        
        <!-- Информация о платеже -->
        <div class="payment-info">
            <div class="amount">
                <?= $formattedAmount ?> <span class="currency">сом</span>
            </div>
            

        </div>
        
        <!-- QR-код -->
        <div class="qr-section">
            <div class="qr-code" id="qrCode">
                <?php if (!empty($qrCode)): ?>
                    <?php if (strpos($qrCode, 'data:image/') === 0): ?>
                        <!-- Отображаем QR-код как base64 изображение -->
                        <img src="<?= $qrCode ?>" alt="QR-код для оплаты" style="width: 300px; height: 300px; display: block; margin: 0 auto; border: 1px solid #ccc; border-radius: 5px;">
                    <?php elseif (strpos($qrCode, 'http') === 0): ?>
                        <!-- Если QR-код - это URL, показываем ссылку -->
                        <div class="qr-code-text">
                            <a href="<?= $qrCode ?>" target="_blank" style="color: #667eea; text-decoration: none;">
                                Открыть QR-код в Optima Bank
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Если QR-код - это хеш, показываем сообщение -->
                        <div class="qr-code-text">QR-код: <?= htmlspecialchars($qrCode) ?></div>
                    <?php endif; ?>
                <?php else: ?>
                    <!-- Если QR-код не сгенерирован -->
                    <div class="qr-code-text">QR-код не найден</div>
                <?php endif; ?>
            </div>
            <div class="qr-text">
                QR-код для оплаты
            </div>
            
            <!-- Кнопка скачивания QR-кода -->
            <?php if (!empty($qrCode) && strpos($qrCode, 'data:image/') === 0): ?>
                <button class="download-qr-btn" onclick="downloadQR()">
                    📱 Скачать QR-код
                </button>
            <?php endif; ?>
        </div>
        
        <!-- Способы оплаты -->
        <div class="payment-methods">
            <h3>Выберите способ оплаты</h3>
            
            <div class="payment-grid">
                <?php if ($isMobile): ?>
                    <!-- На мобильных устройствах - рабочие диплинки -->
                    <?php foreach ($paymentLinks as $key => $payment): ?>
                        <div class="payment-method <?= $key === 'optima' ? 'primary' : '' ?>" data-url="<?= $payment['url'] ?>" style="--color: <?= $payment['color'] ?>; cursor: pointer;">
                            <div class="payment-icon">
                                <?php if (strpos($payment['icon'], 'image_pay_system/') === 0): ?>
                                    <img src="<?= $payment['icon'] ?>" alt="<?= $payment['name'] ?>" style="width: 32px; height: 32px; object-fit: contain;">
                                <?php else: ?>
                                    <?= $payment['icon'] ?>
                                <?php endif; ?>
                            </div>
                            <div class="payment-info">
                                <div class="payment-name"><?= $payment['name'] ?></div>
                                <div class="payment-desc">Быстрая оплата</div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php else: ?>
                    <!-- На десктопе - только иконки с инструкцией -->
                    <div class="desktop-notice" style="text-align: center; padding: 20px; color: #64748b;">
                        <div style="font-size: 18px; margin-bottom: 20px; color: #1e293b;">
                            💻 Для оплаты откройте эту страницу на телефоне
                        </div>
                        <div style="text-align: center; margin-top: 20px;">
                            <table style="margin: 0 auto; border-collapse: separate; border-spacing: 15px;">
                                <tr>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['optima']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['optima']['icon'] ?>" alt="Optima Bank" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['optima']['color'] ?>;">
                                            Optima
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['mbank']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['mbank']['icon'] ?>" alt="MBank" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['mbank']['color'] ?>;">
                                            MBank
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['odengi']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['odengi']['icon'] ?>" alt="ODengi" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['odengi']['color'] ?>;">
                                            ODengi
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['balance']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['balance']['icon'] ?>" alt="Balance" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['balance']['color'] ?>;">
                                            Balance
                                        </div>
                                    </td>
                                </tr>
                                <tr>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['bakai']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['bakai']['icon'] ?>" alt="BakaiBank" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['bakai']['color'] ?>;">
                                            BakaiBank
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['demir']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['demir']['icon'] ?>" alt="Demir Bank" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['demir']['color'] ?>;">
                                            Demir Bank
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: top;">
                                        <div style="background: rgba(255, 255, 255, 0.15); padding: 12px; border-radius: 12px; border: 2px solid <?= $paymentLinks['kicb']['color'] ?>; display: flex; align-items: center; justify-content: center; width: 70px; height: 70px;">
                                            <img src="<?= $paymentLinks['kicb']['icon'] ?>" alt="KICB" style="width: 42px; height: 42px; object-fit: contain;">
                                        </div>
                                        <div style="margin-top: 8px; font-size: 12px; font-weight: 600; color: <?= $paymentLinks['kicb']['color'] ?>;">
                                            KICB
                                        </div>
                                    </td>
                                    <td style="text-align: center; vertical-align: top;">
                                        <!-- Пустая ячейка для симметрии -->
                                    </td>
                                </tr>
                            </table>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Футер -->
        <div class="footer">
            <div class="footer-text">
                Безопасная оплата через проверенные платежные системы
            </div>
        </div>
    </div>
    
    <script>
        // Функция для копирования ссылки
        function copyToClipboard(text) {
            navigator.clipboard.writeText(text).then(function() {
                console.log('Ссылка скопирована');
            }, function(err) {
                console.error('Ошибка копирования:', err);
            });
        }
        
        // Функция для скачивания QR-кода
        function downloadQR() {
            const qrImg = document.querySelector('#qrCode img');
            if (!qrImg) {
                console.error('QR-код не найден');
                alert('QR-код не найден');
                return;
            }
            
            try {
                // Получаем base64 данные
                const base64Data = qrImg.src;
                
                // Создаем canvas для конвертации
                const canvas = document.createElement('canvas');
                const ctx = canvas.getContext('2d');
                
                // Устанавливаем размеры canvas
                canvas.width = qrImg.naturalWidth || 300;
                canvas.height = qrImg.naturalHeight || 300;
                
                // Рисуем изображение на canvas
                ctx.drawImage(qrImg, 0, 0);
                
                // Конвертируем canvas в blob
                canvas.toBlob(function(blob) {
                    // Создаем URL для blob
                    const url = URL.createObjectURL(blob);
                    
                    // Создаем ссылку для скачивания
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = `qr_code_<?= $numberClient ? '_' . $numberClient : '' ?>_<?= date('Y-m-d') ?>.png`;
                    
                    // Добавляем ссылку в DOM, кликаем и удаляем
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    
                    // Освобождаем память
                    URL.revokeObjectURL(url);
                    
                    console.log('QR-код скачан');
                }, 'image/png');
                
            } catch (error) {
                console.error('Ошибка при скачивании:', error);
                alert('Ошибка при скачивании QR-кода');
            }
        }
        
        // Добавляем обработчики только для мобильных устройств
        <?php if ($isMobile): ?>
        document.querySelectorAll('.payment-method').forEach(link => {
            link.addEventListener('click', function(e) {
                // Получаем зашифрованную ссылку
                const encodedUrl = this.getAttribute('data-url');
                
                // Расшифровываем base64
                try {
                    const decodedUrl = atob(encodedUrl);
                    
                    // Добавляем небольшую задержку для корректного открытия ссылки
                    setTimeout(() => {
                        // Открываем расшифрованную ссылку в новой вкладке
                        window.open(decodedUrl, '_blank');
                        
                        // Можно добавить аналитику или логирование
                        console.log('Переход на оплату:', decodedUrl);
                    }, 100);
                } catch (error) {
                    console.error('Ошибка расшифровки ссылки:', error);
                }
            });
        });
        <?php endif; ?>
        
        // Анимация появления элементов
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.payment-method, .qr-code');
            elements.forEach((el, index) => {
                setTimeout(() => {
                    el.style.opacity = '0';
                    el.style.transform = 'translateY(20px)';
                    el.style.transition = 'all 0.5s ease';
                    
                    setTimeout(() => {
                        el.style.opacity = '1';
                        el.style.transform = 'translateY(0)';
                    }, 100);
                }, index * 100);
            });
        });
    </script>
</body>
</html>
