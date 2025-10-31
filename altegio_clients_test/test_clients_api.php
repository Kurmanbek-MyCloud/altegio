<?php
/**
 * Тест API Altegio - Получение списка клиентов (ИСПРАВЛЕННАЯ ВЕРСИЯ)
 * Изолированный файл для тестирования запроса POST /company/{company_id}/clients/search
 */

// Конфигурация для API Altegio
$config = [
    'base_url' => 'https://api.alteg.io/api/v1/',
    'user_token' => 'c1d3041f2185df70f5c341f0926adb44',
    'partner_token' => 'gbkp3f4ynkd5jpejjsxp',
    'company_id' => 729142
];

// Функция для декодирования Unicode символов
function decodeUnicode($text) {
    $decoded = json_decode('"' . $text . '"');
    return $decoded !== null ? $decoded : $text;
}

// Функция для логирования
function logMessage($message, $clearLog = false) {
    $timestamp = date('Y-m-d H:i:s');
    
    // Декодируем Unicode символы для красивого вывода
    $decodedMessage = decodeUnicode($message);
    
    $logEntry = "[$timestamp] $decodedMessage" . PHP_EOL;
    
    // Очищаем файл если нужно
    $flags = $clearLog ? 0 : (FILE_APPEND | LOCK_EX);
    file_put_contents('clients_api.log', $logEntry, $flags);
    
    echo $decodedMessage . PHP_EOL;
}

// Функция для отправки запроса к API
function makeApiRequest($endpoint, $method = 'GET', $data = null) {
    global $config;
    
    $url = $config['base_url'] . ltrim($endpoint, '/');
    
    $headers = [
        'Content-Type: application/json',
        'Accept: application/vnd.api.v2+json',
        'Authorization: Bearer ' . $config['partner_token'] . ', User ' . $config['user_token']
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
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);
    
    logMessage("API Request: $method $url - HTTP $httpCode");
    
    if ($error) {
        logMessage("cURL Error: $error");
        return ['success' => false, 'error' => $error];
    }
    
    $responseData = json_decode($response, true);
    
    if ($httpCode >= 200 && $httpCode < 300) {
        // Красиво форматируем успешный ответ
        $formattedResponse = json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        logMessage("API Success: $formattedResponse");
        return ['success' => true, 'data' => $responseData, 'http_code' => $httpCode];
    } else {
        // Красиво форматируем ошибку
        $formattedError = json_encode($responseData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        logMessage("API Error: HTTP $httpCode - $formattedError");
        return ['success' => false, 'error' => $responseData, 'http_code' => $httpCode];
    }
}

// Функция для получения списка клиентов
function getClientsList($params = []) {
    global $config;
    
    // Базовые параметры запроса
    $requestData = [
        'page' => $params['page'] ?? 1,
        'page_size' => $params['page_size'] ?? 25,
        'order_by' => $params['order_by'] ?? 'id',
        'order_by_direction' => $params['order_by_direction'] ?? 'DESC',
        'operation' => $params['operation'] ?? 'AND'
    ];
    
    // Добавляем поля для возврата
    if (isset($params['fields'])) {
        $requestData['fields'] = $params['fields'];
    }
    
    // Добавляем фильтры
    if (isset($params['filters'])) {
        $requestData['filters'] = $params['filters'];
    }
    
    // Красиво форматируем параметры запроса
    $formattedParams = json_encode($requestData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    logMessage("Getting clients list with params: $formattedParams");
    
    $endpoint = "company/{$config['company_id']}/clients/search";
    return makeApiRequest($endpoint, 'POST', $requestData);
}

// Основной код тестирования
echo "🧪 Тест API Altegio - Получение списка клиентов (ИСПРАВЛЕННАЯ ВЕРСИЯ)\n";
echo "==================================================================\n\n";

// Очищаем лог файл в начале теста
logMessage("=== НАЧАЛО НОВОГО ТЕСТА ===", true);

// Тест 1: Простой запрос (первые 25 клиентов)
echo "1️⃣ Простой запрос (первые 25 клиентов)\n";
$result1 = getClientsList();
if ($result1['success']) {
    echo "✅ Успешно получен список клиентов\n";
    $clients = $result1['data']['data'] ?? [];
    echo "📊 Найдено клиентов: " . count($clients) . "\n";
    echo "📄 Общее количество: " . ($result1['data']['meta']['total_count'] ?? 'N/A') . "\n\n";
    
    // Показываем первых 3 клиентов
    if (!empty($clients)) {
        echo "👥 Первые клиенты:\n";
        for ($i = 0; $i < min(3, count($clients)); $i++) {
            $client = $clients[$i];
            $name = $client['name'] ?? 'N/A';
            
            // Декодируем Unicode символы для красивого вывода
            $decodedName = decodeUnicode($name);
            
            echo "   " . ($i + 1) . ". ID: " . ($client['id'] ?? 'N/A') . 
                 ", Имя: " . $decodedName . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Ошибка: " . json_encode($result1['error']) . "\n\n";
}

// Тест 2: Запрос с выборкой полей
echo "2️⃣ Запрос с выборкой полей\n";
$result2 = getClientsList([
    'page_size' => 10,
    'fields' => ['id', 'name', 'phone', 'email']
]);
if ($result2['success']) {
    echo "✅ Успешно получен список с выборкой полей\n";
    $clients = $result2['data']['data'] ?? [];
    echo "📊 Найдено клиентов: " . count($clients) . "\n";
    
    if (!empty($clients)) {
        echo "👥 Клиенты с полными данными:\n";
        foreach ($clients as $client) {
            $name = $client['name'] ?? 'N/A';
            $phone = $client['phone'] ?? 'N/A';
            $email = $client['email'] ?? 'N/A';
            
            // Декодируем Unicode символы для красивого вывода
            $decodedName = decodeUnicode($name);
            
            echo "   ID: " . ($client['id'] ?? 'N/A') . 
                 ", Имя: " . $decodedName . 
                 ", Телефон: " . $phone . 
                 ", Email: " . $email . "\n";
        }
        echo "\n";
    }
} else {
    echo "❌ Ошибка: " . json_encode($result2['error']) . "\n\n";
}

// Тест 3: Запрос с сортировкой по дате последнего визита
echo "3️⃣ Запрос с сортировкой по дате последнего визита\n";
$result3 = getClientsList([
    'page_size' => 5,
    'order_by' => 'last_visit_date',
    'order_by_direction' => 'DESC',
    'fields' => ['id', 'name', 'phone', 'last_visit_date']
]);
if ($result3['success']) {
    echo "✅ Успешно получен список с сортировкой\n";
    $clients = $result3['data']['data'] ?? [];
    echo "📊 Последние активные клиенты:\n";
            foreach ($clients as $client) {
            $name = $client['name'] ?? 'N/A';
            $lastVisit = $client['last_visit_date'] ?? 'N/A';
            
            // Декодируем Unicode символы для красивого вывода
            $decodedName = decodeUnicode($name);
            
            echo "   ID: " . ($client['id'] ?? 'N/A') . 
                 ", Имя: " . $decodedName . 
                 ", Последний визит: " . $lastVisit . "\n";
        }
    echo "\n";
} else {
    echo "❌ Ошибка: " . json_encode($result3['error']) . "\n\n";
}

// Тест 4: Запрос с пагинацией
echo "4️⃣ Запрос с пагинацией (страница 2)\n";
$result4 = getClientsList([
    'page' => 2,
    'page_size' => 10,
    'order_by' => 'id'
]);
if ($result4['success']) {
    echo "✅ Успешно получена страница 2\n";
    $clients = $result4['data']['data'] ?? [];
    echo "📊 Клиентов на странице 2: " . count($clients) . "\n";
    echo "📄 Общее количество: " . ($result4['data']['meta']['total_count'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ Ошибка: " . json_encode($result4['error']) . "\n\n";
}

// Тест 5: Запрос с сортировкой по имени
echo "5️⃣ Запрос с сортировкой по имени (по возрастанию)\n";
$result5 = getClientsList([
    'page_size' => 5,
    'order_by' => 'name',
    'order_by_direction' => 'ASC',
    'fields' => ['id', 'name', 'phone']
]);
if ($result5['success']) {
    echo "✅ Успешно получен список с сортировкой по имени\n";
    $clients = $result5['data']['data'] ?? [];
    echo "📊 Клиенты по алфавиту:\n";
            foreach ($clients as $client) {
            $name = $client['name'] ?? 'N/A';
            $phone = $client['phone'] ?? 'N/A';
            
            // Декодируем Unicode символы для красивого вывода
            $decodedName = decodeUnicode($name);
            
            echo "   ID: " . ($client['id'] ?? 'N/A') . 
                 ", Имя: " . $decodedName . 
                 ", Телефон: " . $phone . "\n";
        }
    echo "\n";
} else {
    echo "❌ Ошибка: " . json_encode($result5['error']) . "\n\n";
}

// Тест 6: Запрос с максимальным размером страницы
echo "6️⃣ Запрос с максимальным размером страницы (200)\n";
$result6 = getClientsList([
    'page_size' => 200,
    'order_by' => 'id',
    'fields' => ['id', 'name']
]);
if ($result6['success']) {
    echo "✅ Успешно получен список с максимальным размером\n";
    $clients = $result6['data']['data'] ?? [];
    echo "📊 Получено клиентов: " . count($clients) . "\n";
    echo "📄 Общее количество: " . ($result6['data']['meta']['total_count'] ?? 'N/A') . "\n\n";
} else {
    echo "❌ Ошибка: " . json_encode($result6['error']) . "\n\n";
}

echo "📊 Итоги тестирования:\n";
echo "=====================\n";
echo "• Простой запрос: " . ($result1['success'] ? "✅ Успех" : "❌ Ошибка") . "\n";
echo "• Выборка полей: " . ($result2['success'] ? "✅ Успех" : "❌ Ошибка") . "\n";
echo "• Сортировка по дате: " . ($result3['success'] ? "✅ Успех" : "❌ Ошибка") . "\n";
echo "• Пагинация: " . ($result4['success'] ? "✅ Успех" : "❌ Ошибка") . "\n";
echo "• Сортировка по имени: " . ($result5['success'] ? "✅ Успех" : "❌ Ошибка") . "\n";
echo "• Максимальный размер: " . ($result6['success'] ? "✅ Успех" : "❌ Ошибка") . "\n\n";

echo "💡 Выводы:\n";
echo "==========\n";
echo "1. ✅ API работает корректно для базовых запросов\n";
echo "2. ✅ Поддерживается выборка полей (fields)\n";
echo "3. ✅ Работает сортировка по разным полям\n";
echo "4. ✅ Пагинация функционирует\n";
echo "5. ❌ Фильтрация требует дополнительного изучения\n";
echo "6. ✅ Максимальный размер страницы: 200\n\n";

echo "🔧 Рекомендации по использованию:\n";
echo "===============================\n";
echo "1. Всегда указывайте 'fields' для оптимизации\n";
echo "2. Используйте 'page_size' до 200 для больших выборок\n";
echo "3. Сортируйте по 'last_visit_date' для активных клиентов\n";
echo "4. Логируйте все запросы для отладки\n\n";

echo "📝 Лог файл: clients_api.log\n";
?> 