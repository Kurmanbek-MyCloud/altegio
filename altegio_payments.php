<?php
/**
 * Altegio Payments API - Работа с платежами через API
 * Создание, обновление и управление платежами в Altegio
 */

class AltegioPayments {
    private $userToken;
    private $partnerToken;
    private $baseUrl = 'https://api.alteg.io/api/v1/';
    private $logFile = 'payments_api.log';
    private $companyId = null;
    
    public function __construct($userToken, $partnerToken, $companyId = null) {
        $this->userToken = $userToken;
        $this->partnerToken = $partnerToken;
        $this->companyId = $companyId;
    }
    
    /**
     * Логирование API запросов
     */
    private function log($message) {
        $timestamp = date('Y-m-d H:i:s');
        $logEntry = "[$timestamp] $message" . PHP_EOL;
        file_put_contents($this->logFile, $logEntry, FILE_APPEND | LOCK_EX);
    }
    
    /**
     * Отправка запроса к API Altegio
     */
    private function makeRequest($endpoint, $method = 'GET', $data = null) {
        $url = $this->baseUrl . ltrim($endpoint, '/');
        
        $headers = [
            'Content-Type: application/json',
            'Accept: application/vnd.api.v2+json',
            'Authorization: Bearer ' . $this->partnerToken . ', User ' . $this->userToken
        ];
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
        
        if ($method === 'POST') {
            curl_setopt($ch, CURLOPT_POST, true);
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'PUT') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'PUT');
            if ($data) {
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
            }
        } elseif ($method === 'DELETE') {
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'DELETE');
        }
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);
        curl_close($ch);
        
        $this->log("API Request: $method $url - HTTP $httpCode");
        
        if ($error) {
            $this->log("cURL Error: $error");
            return ['success' => false, 'error' => $error];
        }
        
        $responseData = json_decode($response, true);
        
        if ($httpCode >= 200 && $httpCode < 300) {
            $this->log("API Success: " . json_encode($responseData));
            return ['success' => true, 'data' => $responseData, 'http_code' => $httpCode];
        } else {
            $this->log("API Error: HTTP $httpCode - " . $response);
            return ['success' => false, 'error' => $responseData, 'http_code' => $httpCode];
        }
    }
    
    /**
     * Создание платежа для записи
     */
    public function createPaymentForRecord($recordData) {
        $this->log("Creating payment for record: " . json_encode($recordData));
        
        // Извлекаем данные из записи
        $clientId = $recordData['client']['id'] ?? null;
        $recordId = $recordData['id'] ?? null;
        $services = $recordData['services'] ?? [];
        
        if (!$clientId || !$recordId) {
            $this->log("Missing required data: client_id or record_id");
            return ['success' => false, 'error' => 'Missing required data'];
        }
        
        // Рассчитываем общую стоимость
        $totalAmount = 0;
        $serviceNames = [];
        
        foreach ($services as $service) {
            $cost = $service['cost'] ?? 0;
            $totalAmount += $cost;
            $serviceNames[] = $service['title'] ?? '';
        }
        
        if ($totalAmount <= 0) {
            $this->log("No payment needed - total amount is 0");
            return ['success' => false, 'error' => 'No payment needed'];
        }
        
        // Создаем данные для платежа
        $paymentData = [
            'client_id' => $clientId,
            'record_id' => $recordId,
            'amount' => $totalAmount,
            'currency' => 'RUB',
            'payment_method' => 'cash', // Можно изменить на card, online и т.д.
            'description' => 'Оплата за услуги: ' . implode(', ', $serviceNames),
            'status' => 'pending'
        ];
        
        return $this->createPayment($paymentData);
    }
    
    /**
     * Создание платежа для визита
     */
    public function createPaymentForVisit($visitData) {
        $this->log("Creating payment for visit: " . json_encode($visitData));
        
        $clientId = $visitData['client']['id'] ?? null;
        $visitId = $visitData['id'] ?? null;
        $services = $visitData['services'] ?? [];
        
        if (!$clientId || !$visitId) {
            $this->log("Missing required data: client_id or visit_id");
            return ['success' => false, 'error' => 'Missing required data'];
        }
        
        // Рассчитываем общую стоимость
        $totalAmount = 0;
        $serviceNames = [];
        
        foreach ($services as $service) {
            $cost = $service['cost'] ?? 0;
            $totalAmount += $cost;
            $serviceNames[] = $service['title'] ?? '';
        }
        
        if ($totalAmount <= 0) {
            $this->log("No payment needed - total amount is 0");
            return ['success' => false, 'error' => 'No payment needed'];
        }
        
        // Создаем данные для платежа
        $paymentData = [
            'client_id' => $clientId,
            'visit_id' => $visitId,
            'amount' => $totalAmount,
            'currency' => 'RUB',
            'payment_method' => 'cash',
            'description' => 'Оплата за услуги: ' . implode(', ', $serviceNames),
            'status' => 'pending'
        ];
        
        return $this->createPayment($paymentData);
    }
    
    /**
     * Создание платежа
     */
    public function createPayment($paymentData) {
        $this->log("Creating payment: " . json_encode($paymentData));
        
        // Валидация данных
        $required = ['client_id', 'amount'];
        foreach ($required as $field) {
            if (!isset($paymentData[$field])) {
                $this->log("Missing required field: $field");
                return ['success' => false, 'error' => "Missing required field: $field"];
            }
        }
        
        if ($paymentData['amount'] <= 0) {
            $this->log("Invalid amount: " . $paymentData['amount']);
            return ['success' => false, 'error' => 'Invalid amount'];
        }
        
        // Подготавливаем данные для API с правильными параметрами
        $apiData = [
            'client_id' => $paymentData['client_id'],
            'amount' => $paymentData['amount'],
            'account_id' => 2665932, // Правильный ID кассы
            'expense_id' => 1, // Тип транзакции
            'date' => date('Y-m-d H:i:s'), // Правильный формат даты
            'comment' => $paymentData['description'] ?? $paymentData['comment'] ?? 'Платеж через API'
        ];
        
        // Добавляем master_id если указан
        if (isset($paymentData['master_id'])) {
            $apiData['master_id'] = $paymentData['master_id'];
        }
        
        // Используем правильный URL
        $result = $this->makeRequest("finance_transactions/{$this->companyId}", 'POST', $apiData);
        
        if ($result['success']) {
            // Сохраняем информацию о платеже
            $this->savePaymentInfo($result['data']);
            
            // Отправляем уведомление
            $this->sendPaymentNotification('created', $result['data']);
        }
        
        return $result;
    }
    
    /**
     * Получение информации о финансовой транзакции
     */
    public function getPayment($paymentId) {
        $this->log("Getting financial transaction info: $paymentId");
        return $this->makeRequest("financial-transactions/$paymentId/");
    }
    
    /**
     * Обновление финансовой транзакции
     */
    public function updatePayment($paymentId, $updateData) {
        $this->log("Updating financial transaction $paymentId: " . json_encode($updateData));
        return $this->makeRequest("financial-transactions/$paymentId/", 'PUT', $updateData);
    }
    
    /**
     * Отмена финансовой транзакции
     */
    public function cancelPayment($paymentId, $reason = '') {
        $this->log("Cancelling financial transaction $paymentId: $reason");
        $cancelData = [
            'status' => 'cancelled',
            'cancellation_reason' => $reason
        ];
        return $this->makeRequest("financial-transactions/$paymentId/", 'PUT', $cancelData);
    }
    
    /**
     * Подтверждение финансовой транзакции
     */
    public function confirmPayment($paymentId) {
        $this->log("Confirming financial transaction $paymentId");
        $confirmData = [
            'status' => 'confirmed',
            'confirmed_at' => date('Y-m-d H:i:s')
        ];
        return $this->makeRequest("financial-transactions/$paymentId/", 'PUT', $confirmData);
    }
    
    /**
     * Получение списка финансовых транзакций клиента
     */
    public function getClientPayments($clientId, $limit = 50, $offset = 0) {
        $this->log("Getting financial transactions for client $clientId");
        $params = http_build_query([
            'client_id' => $clientId,
            'limit' => $limit,
            'offset' => $offset
        ]);
        return $this->makeRequest("financial-transactions/?$params");
    }
    
    /**
     * Получение статистики финансовых транзакций
     */
    public function getPaymentsStats($dateFrom = null, $dateTo = null) {
        $this->log("Getting financial transactions statistics");
        $params = [];
        if ($dateFrom) $params['date_from'] = $dateFrom;
        if ($dateTo) $params['date_to'] = $dateTo;
        
        $queryString = http_build_query($params);
        return $this->makeRequest("financial-transactions/stats/" . ($queryString ? "?$queryString" : ''));
    }
    
    /**
     * Создание возврата средств
     */
    public function createRefund($paymentId, $amount, $reason = '') {
        $this->log("Creating refund for financial transaction $paymentId: $amount");
        $refundData = [
            'transaction_id' => $paymentId,
            'amount' => $amount,
            'reason' => $reason,
            'refund_method' => 'original' // или 'cash', 'card' и т.д.
        ];
        return $this->makeRequest('financial-transactions/refunds/', 'POST', $refundData);
    }
    
    /**
     * Сохранение информации о платеже в локальный файл
     */
    private function savePaymentInfo($paymentData) {
        $paymentInfo = [
            'id' => $paymentData['id'] ?? null,
            'client_id' => $paymentData['client_id'] ?? null,
            'amount' => $paymentData['amount'] ?? 0,
            'currency' => $paymentData['currency'] ?? 'RUB',
            'payment_method' => $paymentData['payment_method'] ?? '',
            'status' => $paymentData['status'] ?? 'pending',
            'description' => $paymentData['description'] ?? '',
            'created_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents('payments.json', json_encode($paymentInfo, JSON_PRETTY_PRINT) . PHP_EOL, FILE_APPEND | LOCK_EX);
        $this->log("Payment info saved: " . json_encode($paymentInfo));
    }
    
    /**
     * Отправка уведомления о платеже
     */
    private function sendPaymentNotification($action, $paymentData) {
        $notifications = [
            'created' => "💰 Создан новый платеж",
            'updated' => "🔄 Платеж обновлен",
            'confirmed' => "✅ Платеж подтвержден",
            'cancelled' => "❌ Платеж отменен",
            'refunded' => "💸 Создан возврат средств"
        ];
        
        $title = $notifications[$action] ?? "Платеж $action";
        
        $notificationData = [
            'ID платежа' => $paymentData['id'] ?? 'N/A',
            'Клиент ID' => $paymentData['client_id'] ?? 'N/A',
            'Сумма' => ($paymentData['amount'] ?? 0) . ' ' . ($paymentData['currency'] ?? 'RUB'),
            'Метод оплаты' => $paymentData['payment_method'] ?? 'N/A',
            'Статус' => $paymentData['status'] ?? 'N/A',
            'Описание' => $paymentData['description'] ?? 'N/A'
        ];
        
        $message = $title . "\n\n";
        foreach ($notificationData as $key => $value) {
            $message .= "$key: $value\n";
        }
        
        file_put_contents('notifications.txt', date('Y-m-d H:i:s') . " - $message\n", FILE_APPEND | LOCK_EX);
        $this->log("Payment notification sent: $title");
    }
    
    /**
     * Тестирование API подключения
     */
    public function testConnection() {
        $this->log("Testing API connection");
        
        // Сначала попробуем получить список компаний
        $result = $this->makeRequest('companies/');
        if ($result['success']) {
            $this->log("Connection successful - companies endpoint works");
            
            // Если company_id не указан, попробуем получить первую компанию
            if (!$this->companyId && isset($result['data']['data']) && !empty($result['data']['data'])) {
                $this->companyId = $result['data']['data'][0]['id'] ?? null;
                $this->log("Auto-detected company ID: " . $this->companyId);
            }
            
            return $result;
        }
        
        // Если companies не работает, попробуем другие эндпоинты
        $endpoints = ['clients/', 'entries/', 'services/'];
        
        foreach ($endpoints as $endpoint) {
            $result = $this->makeRequest($endpoint);
            if ($result['success']) {
                $this->log("Connection successful via endpoint: $endpoint");
                return $result;
            }
        }
        
        // Если ни один эндпоинт не работает, вернем ошибку
        $this->log("All endpoints failed");
        return ['success' => false, 'error' => 'No working endpoints found'];
    }
}

// Пример использования
if (basename(__FILE__) === basename($_SERVER['SCRIPT_NAME'])) {
    // Токен из конфигурации
    $token = 'c1d3041f2185df70f5c341f0926adb44'; // Замените на ваш токен
    
    $payments = new AltegioPayments($token);
    
    // Тест подключения
    $testResult = $payments->testConnection();
    echo "API Connection Test: " . json_encode($testResult, JSON_PRETTY_PRINT) . "\n";
    
    // Пример создания платежа
    $paymentData = [
        'client_id' => 123,
        'amount' => 1500,
        'currency' => 'RUB',
        'payment_method' => 'card',
        'description' => 'Оплата за стрижку',
        'status' => 'pending'
    ];
    
    $result = $payments->createPayment($paymentData);
    echo "Create Payment Result: " . json_encode($result, JSON_PRETTY_PRINT) . "\n";
}
?> 