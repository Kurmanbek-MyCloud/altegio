<?php
// Отладочная информация (только для разработки)
// echo "=== createpayment.php запущен ===\n";
// echo "SAPI: " . php_sapi_name() . "\n";
// echo "Текущая директория: " . getcwd() . "\n";
// echo "getenv('WEBHOOK_DATA'): " . (getenv('WEBHOOK_DATA') ? 'УСТАНОВЛЕНА' : 'НЕ УСТАНОВЛЕНА') . "\n\n";

// Меняем директорию только если скрипт запущен не из командной строки
// if (php_sapi_name() !== 'cli') {
//     chdir('../../');
// } else {
//     // Если запущен из командной строки, переходим в корневую директорию проекта
//     chdir('../../');
// }

chdir('../../');

ini_set('memory_limit', '512M');

// include_once 'includes/Loader.php';
require_once 'include/utils/utils.php';
require_once 'Logger.php';
require_once 'includes/runtime/BaseModel.php';
require_once 'includes/runtime/Globals.php';
include_once 'includes/runtime/Controller.php';
include_once 'includes/http/Request.php';

global $current_user;
global $adb;
$logger = new CustomLogger('payment_services/optima/createpayment.log');
$current_user = Users::getActiveAdminUser();

$logger->log("Тест");
// echo"Test\n";
// exit;

/**
 * Обработка данных из вебхука
 */
function processWebhookData($data) {
    global $logger;
    global $adb;
    
    // Извлекаем все данные в переменные
    $status = $data['status'] ?? '';
    $sum = $data['sum'] ?? 0;
    $note = $data['note'] ?? '';
    $transactionId = $data['transactionId'] ?? '';
    $transactionProcessedDateTime = $data['transactionProcessedDateTime'] ?? '';
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
    
    // Логируем полученные данные
    $logger->log('Получены данные из вебхука - transactionId: ' . $transactionId . ', status: ' . $status . ', sum: ' . $sum . ', orderId: ' . $orderId . ', currency: ' . $currency);
    $logger->log('Детали платежа - cardMask: ' . $cardMask . ', cardType: ' . $cardType . ', approvalCode: ' . $approvalCode . ', rrn: ' . $rrn . ', stan: ' . $stan);
    $logger->log('Дополнительные данные - responseCode: ' . $responseCode . ', responseMessage: ' . $responseMessage . ', errorCode: ' . $errorCode . ', errorMessage: ' . $errorMessage);
    $logger->log('Полные данные: ' . json_encode($data, JSON_UNESCAPED_UNICODE));
    
    // Здесь ваша логика обработки платежа
    // Например, обновление статуса заказа в базе данных
    
    try {
        // Вызываем функцию create_payments с передачей всех данных
        $result = create_payments($adb, $logger, $data);
        
        if ($result) {
            $logger->log('create_payments выполнена успешно');
        } else {
            $logger->log('Ошибка в create_payments');
        }
        
        // Пример: обновление статуса заказа
        if ($status === 'success') {
            // Логика для успешного платежа
            $logger->log('Обработка успешного платежа - transactionId: ' . $transactionId . ', orderId: ' . $orderId . ', amount: ' . $sum . ', currency: ' . $currency);
            $logger->log('Успешный платеж детали - approvalCode: ' . $approvalCode . ', rrn: ' . $rrn . ', stan: ' . $stan . ', responseCode: ' . $responseCode);
            
            // Здесь добавьте вашу логику
            // Например: обновить статус заказа в базе данных
            
        } elseif ($status === 'failed') {
            // Логика для неуспешного платежа
            $logger->log('Обработка неуспешного платежа - transactionId: ' . $transactionId . ', orderId: ' . $orderId . ', amount: ' . $sum . ', currency: ' . $currency);
            $logger->log('Неуспешный платеж детали - errorCode: ' . $errorCode . ', errorMessage: ' . $errorMessage . ', responseCode: ' . $responseCode);
            
            // Здесь добавьте вашу логику
            // Например: отправить уведомление об ошибке
            
        } else {
            $logger->log('Неизвестный статус платежа - transactionId: ' . $transactionId . ', status: ' . $status);
        }
        
        return true;
        
    } catch (Exception $e) {
        $logger->log('Ошибка при обработке данных вебхука', [
            'transactionId' => $transactionId,
            'error' => $e->getMessage(),
            'trace' => $e->getTraceAsString()
        ]);
        return false;
    }
}

// Проверяем, запущен ли скрипт из командной строки с данными в переменной окружения
if (php_sapi_name() === 'cli') {
    // Пробуем получить данные из переменной окружения
    $encodedData = getenv('WEBHOOK_DATA');
    
    if ($encodedData) {
        // Декодируем данные из base64
        $jsonData = base64_decode($encodedData);
        
        if ($jsonData === false) {
            echo "Ошибка декодирования данных из base64\n";
            exit(1);
        }
        
        $data = json_decode($jsonData, true);
        
        if (json_last_error() === JSON_ERROR_NONE) {
            $result = processWebhookData($data);
            exit($result ? 0 : 1);
        } else {
            echo "Ошибка парсинга JSON: " . json_last_error_msg() . "\n";
            exit(1);
        }
    } else {
        echo "Переменная окружения WEBHOOK_DATA не найдена\n";
        exit(1);
    }
}


function create_payments($adb, $logger, $data) {
    $logger->log("==============================================");
    // Извлекаем все данные в переменные
    $status = $data['status'] ?? '';
    $sum = $data['sum'] ?? 0;
    $note = $data['note'] ?? '';
    $transactionId = $data['transactionId'] ?? '';
    $transactionProcessedDateTime = $data['transactionProcessedDateTime'] ?? '';
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
    
    // Логируем извлеченные переменные
    $logger->log('create_payments - Извлеченные переменные:');
    $logger->log('status: ' . $status);
    $logger->log('sum: ' . $sum);
    $logger->log('note: ' . $note);
    $logger->log('transactionId: ' . $transactionId);
    $logger->log('orderId: ' . $orderId);
    $logger->log('currency: ' . $currency);
    $logger->log('paymentMethod: ' . $paymentMethod);
    $logger->log('cardMask: ' . $cardMask);
    $logger->log('cardType: ' . $cardType);
    $logger->log('approvalCode: ' . $approvalCode);
    $logger->log('rrn: ' . $rrn);
    $logger->log('stan: ' . $stan);
    $logger->log('terminalId: ' . $terminalId);
    $logger->log('merchantName: ' . $merchantName);
    $logger->log('responseCode: ' . $responseCode);
    $logger->log('responseMessage: ' . $responseMessage);
    $logger->log('errorCode: ' . $errorCode);
    $logger->log('errorMessage: ' . $errorMessage);

    // Извлекаем лицевой счет из поля note или дополнительных данных
    $ls = $note;
    
    // Если в note нет лицевого счета, ищем в additionalData
    if (empty($ls) && isset($additionalData['accountNumber'])) {
        $ls = $additionalData['accountNumber'];
    }
    
    // Если все еще нет, ищем в других возможных полях
    if (empty($ls) && isset($additionalData['ls'])) {
        $ls = $additionalData['ls'];
    }
    
    // Валидируем лицевой счет
    if (empty($ls)) {
        $logger->log("ОШИБКА: Не удалось извлечь лицевой счет из данных платежа");
        return false;
    }
    
    $logger->log("Извлечен лицевой счет: $ls");

    // Ищем счет по лицевому счету
    $invoice_data = $adb->pquery("SELECT vi.invoiceid FROM vtiger_invoice vi 
                                    inner join vtiger_crmentity vc on vc.crmid = vi.invoiceid 
                                    WHERE vc.deleted = 0 and vi.invoice_no = ?", array($ls));

    $invoice_id = $adb->query_result($invoice_data, 0, 'invoiceid');
    
    // Проверяем, найден ли счет
    if (empty($invoice_id)) {
        $logger->log("ОШИБКА: Счет с лицевым счетом $ls не найден в базе данных");
        return false;
    }
    
    $logger->log("Найден счет с ID: $invoice_id для лицевого счета: $ls");

    
    
    // Проверяем, не был ли уже обработан этот платеж (по transactionId)
    $existing_payment = $adb->pquery("SELECT paymentid FROM vtiger_payments 
                                     WHERE cf_txnid = ? AND cf_payment_source = 'Optima'", 
                                     array($transactionId));
    
    if ($adb->num_rows($existing_payment) > 0) {
        $logger->log("ПРЕДУПРЕЖДЕНИЕ: Платеж с transactionId $transactionId уже был обработан");
        return true; // Возвращаем true, так как платеж уже обработан
    }
    
    // Здесь ваша логика обработки платежа с использованием переменных
    if ($status === 'success') {
        $logger->log('create_payments - Обработка успешного платежа для orderId: ' . $orderId);

        try {
            $payment = Vtiger_Record_Model::getCleanInstance("Payments");
            $payment->set('cf_paid_object', $invoice_id);
            $payment->set('amount', $sum);
            $payment->set('cf_payment_type', 'Безналичный расчет');
            $payment->set('cf_payment_source', 'Optima');
            $payment->set('cf_txnid', $transactionId);
            $payment->set('cf_pay_date', date('Y-m-d'));
            
            // Добавляем дополнительную информацию
            if (!empty($approvalCode)) {
                $payment->set('cf_approval_code', $approvalCode);
            }
            if (!empty($rrn)) {
                $payment->set('cf_rrn', $rrn);
            }
            if (!empty($cardMask)) {
                $payment->set('cf_card_mask', $cardMask);
            }

            $payment->set('mode', 'create');
            $payment->save();
            $payment_id = $payment->getId();

            if ($payment_id) {
                $logger->log("Платеж успешно создан для счета $invoice_id - ID платежа ". $payment_id);
            } else {
                $logger->log("Ошибка при создании платежа");
                return false;
            }
        } catch (Exception $e) {
            $logger->log("ОШИБКА при создании платежа: " . $e->getMessage());
            return false;
        }

    } elseif ($status === 'failed') {
        $logger->log('create_payments - Обработка неуспешного платежа для orderId: ' . $orderId);
        // Логируем неуспешный платеж для аналитики
        $logger->log("Неуспешный платеж - transactionId: $transactionId, errorCode: $errorCode, errorMessage: $errorMessage");
    } else {
        $logger->log("НЕИЗВЕСТНЫЙ статус платежа: $status для transactionId: $transactionId");
        return false;
    }
    
    return true;
}