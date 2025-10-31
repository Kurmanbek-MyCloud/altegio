#!/bin/bash

# Пример cURL запроса для получения списка клиентов из Altegio API
# POST /company/{company_id}/clients/search

echo "🔍 Пример cURL запроса для получения списка клиентов"
echo "=================================================="

# Конфигурация
COMPANY_ID="729142"
USER_TOKEN="c1d3041f2185df70f5c341f0926adb44"
PARTNER_TOKEN="gbkp3f4ynkd5jpejjsxp"
BASE_URL="https://api.alteg.io/api/v1"

echo ""
echo "1️⃣ Простой запрос (первые 25 клиентов):"
echo "----------------------------------------"

curl -X POST "$BASE_URL/company/$COMPANY_ID/clients/search" \
  -H "Content-Type: application/json" \
  -H "Accept: application/vnd.api.v2+json" \
  -H "Authorization: Bearer $PARTNER_TOKEN, User $USER_TOKEN" \
  -d '{
    "page": 1,
    "page_size": 25,
    "order_by": "id",
    "order_by_direction": "DESC"
  }' | jq '.'

echo ""
echo "2️⃣ Запрос с фильтрацией по имени:"
echo "---------------------------------"

curl -X POST "$BASE_URL/company/$COMPANY_ID/clients/search" \
  -H "Content-Type: application/json" \
  -H "Accept: application/vnd.api.v2+json" \
  -H "Authorization: Bearer $PARTNER_TOKEN, User $USER_TOKEN" \
  -d '{
    "page": 1,
    "page_size": 10,
    "order_by": "name",
    "order_by_direction": "ASC",
    "filters": [
      {
        "field": "name",
        "operation": "LIKE",
        "value": "А"
      }
    ]
  }' | jq '.'

echo ""
echo "3️⃣ Запрос с выборкой полей:"
echo "---------------------------"

curl -X POST "$BASE_URL/company/$COMPANY_ID/clients/search" \
  -H "Content-Type: application/json" \
  -H "Accept: application/vnd.api.v2+json" \
  -H "Authorization: Bearer $PARTNER_TOKEN, User $USER_TOKEN" \
  -d '{
    "page": 1,
    "page_size": 5,
    "order_by": "last_visit_date",
    "order_by_direction": "DESC",
    "fields": ["id", "name", "phone", "email", "last_visit_date", "visit_count"]
  }' | jq '.'

echo ""
echo "4️⃣ Запрос с множественными фильтрами:"
echo "-------------------------------------"

curl -X POST "$BASE_URL/company/$COMPANY_ID/clients/search" \
  -H "Content-Type: application/json" \
  -H "Accept: application/vnd.api.v2+json" \
  -H "Authorization: Bearer $PARTNER_TOKEN, User $USER_TOKEN" \
  -d '{
    "page": 1,
    "page_size": 10,
    "order_by": "sold_amount",
    "order_by_direction": "DESC",
    "operation": "AND",
    "filters": [
      {
        "field": "visit_count",
        "operation": ">=",
        "value": 1
      },
      {
        "field": "sold_amount",
        "operation": ">",
        "value": 0
      }
    ]
  }' | jq '.'

echo ""
echo "📝 Примечания:"
echo "=============="
echo "• Установите jq для красивого вывода JSON: brew install jq"
echo "• Замените COMPANY_ID, USER_TOKEN, PARTNER_TOKEN на ваши данные"
echo "• Доступные поля для сортировки: id, name, phone, email, discount, first_visit_date, last_visit_date, sold_amount, visit_count"
echo "• Доступные операции фильтрации: =, !=, >, >=, <, <=, LIKE, IN, NOT_IN"
echo "• Максимальный размер страницы: 200" 