# Flash Sale Order API - Test Documentation

This document contains all API endpoints and their test JSONs. You can test the APIs step by step by following this guide.

## Base URL
```
http://localhost:8000/api
```

---

## 1. User Registration

### Endpoint: `POST /register`

### Request JSON:
```json
{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "password_confirmation": "password123"
}
```

### Response (201 Created):
```json
{
    "success": true,
    "message": "User registered successfully.",
    "token": "1|randomtoken123...",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "customer",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 2. User Login

### Endpoint: `POST /login`

### Request JSON:
```json
{
    "email": "john@example.com",
    "password": "password123"
}
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "Login successful.",
    "token": "1|randomtoken123...",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "customer",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 3. User Logout (Authentication Required)

### Endpoint: `POST /logout`

### Headers:
```
Authorization: Bearer 1|randomtoken123...
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "Logged out successfully."
}
```

---

## 4. View User Profile (Authentication Required)

### Endpoint: `GET /me`

### Headers:
```
Authorization: Bearer 1|randomtoken123...
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "User profile retrieved successfully.",
    "data": {
        "id": 1,
        "name": "John Doe",
        "email": "john@example.com",
        "role": "customer",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 5. View Product List (Customer)

### Endpoint: `GET /products`

### Query Parameters (Optional):
- `search` - Search by product name
- `min_price` - Filter by minimum price
- `max_price` - Filter by maximum price
- `per_page` - Number of products to display per page (default: 15)

### Example: `GET /products?search=phone&min_price=100&max_price=1000&per_page=10`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Products retrieved successfully.",
    "data": [
        {
            "id": 1,
            "name": "Smartphone",
            "description": "Latest smartphone",
            "price": "500.00",
            "available_stock": 100,
            "flash_sale_start": "2026-07-16T10:00:00.000000Z",
            "flash_sale_end": "2026-07-16T18:00:00.000000Z",
            "status": "active",
            "created_at": "2026-07-16T06:58:16.000000Z",
            "updated_at": "2026-07-16T06:58:16.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100
    }
}
```

---

## 6. View Product Details (Customer)

### Endpoint: `GET /products/{id}`

### Example: `GET /products/1`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Product retrieved successfully.",
    "data": {
        "id": 1,
        "name": "Smartphone",
        "description": "Latest smartphone",
        "price": "500.00",
        "available_stock": 100,
        "flash_sale_start": "2026-07-16T10:00:00.000000Z",
        "flash_sale_end": "2026-07-16T18:00:00.000000Z",
        "status": "active",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 7. View Wallet Balance (Authentication Required)

### Endpoint: `GET /wallet`

### Headers:
```
Authorization: Bearer 1|randomtoken123...
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "Wallet balance retrieved successfully.",
    "data": {
        "balance": "1000.00"
    }
}
```

---

## 8. View Wallet Transaction History (Authentication Required)

### Endpoint: `GET /wallet/transactions`

### Headers:
```
Authorization: Bearer 1|randomtoken123...
```

### Query Parameters (Optional):
- `per_page` - Number of transactions to display per page (default: 15)

### Example: `GET /wallet/transactions?per_page=10`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Wallet transactions retrieved successfully.",
    "data": [
        {
            "id": 1,
            "type": "credit",
            "amount": "500.00",
            "balance_after": "1000.00",
            "description": "Wallet recharge",
            "created_at": "2026-07-16T06:58:16.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 50
    }
}
```

---

## 9. View Order History (Customer, Authentication Required)

### Endpoint: `GET /orders`

### Headers:
```
Authorization: Bearer 1|randomtoken123...
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "Order history retrieved successfully.",
    "data": [
        {
            "id": 1,
            "customer_id": 1,
            "product_id": 1,
            "quantity": 1,
            "total_amount": "500.00",
            "status": "completed",
            "payment_status": "paid",
            "created_at": "2026-07-16T06:58:16.000000Z",
            "updated_at": "2026-07-16T06:58:16.000000Z",
            "product": {
                "id": 1,
                "name": "Smartphone",
                "price": "500.00"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 10
    }
}
```

---

## 10. নির্দিষ্ট অর্ডার দেখা (Customer, Authentication Required)

### Endpoint: `GET /orders/{id}`

### Headers:
```
Authorization: Bearer 1|randomtoken123...
```

### Example: `GET /orders/1`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Order retrieved successfully.",
    "data": {
        "id": 1,
        "customer_id": 1,
        "product_id": 1,
        "quantity": 1,
        "total_amount": "500.00",
        "status": "completed",
        "payment_status": "paid",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z",
        "product": {
            "id": 1,
            "name": "Smartphone",
            "price": "500.00"
        }
    }
}
```

### Response (404 Not Found - If order is not found):
```json
{
    "success": false,
    "message": "Order not found."
}
```

---

## 11. Create Product (Admin Only, Authentication Required)

### Endpoint: `POST /admin/products`

### Headers:
```
Authorization: Bearer admin_token...
```

### Request JSON:
```json
{
    "name": "Laptop",
    "description": "High performance laptop",
    "price": 1200.50,
    "available_stock": 50,
    "flash_sale_start": "2026-07-16 10:00:00",
    "flash_sale_end": "2026-07-16 18:00:00",
    "status": "active"
}
```

### Response (201 Created):
```json
{
    "success": true,
    "message": "Product created successfully.",
    "data": {
        "id": 2,
        "name": "Laptop",
        "description": "High performance laptop",
        "price": "1200.50",
        "available_stock": 50,
        "flash_sale_start": "2026-07-16T10:00:00.000000Z",
        "flash_sale_end": "2026-07-16T18:00:00.000000Z",
        "status": "active",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 12. Update Product (Admin Only, Authentication Required)

### Endpoint: `PUT /admin/products/{id}`

### Headers:
```
Authorization: Bearer admin_token...
```

### Request JSON (All fields are optional):
```json
{
    "name": "Gaming Laptop",
    "price": 1500.00,
    "available_stock": 30,
    "status": "active"
}
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "Product updated successfully.",
    "data": {
        "id": 2,
        "name": "Gaming Laptop",
        "description": "High performance laptop",
        "price": "1500.00",
        "available_stock": 30,
        "flash_sale_start": "2026-07-16T10:00:00.000000Z",
        "flash_sale_end": "2026-07-16T18:00:00.000000Z",
        "status": "active",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 13. Delete Product (Admin Only, Authentication Required)

### Endpoint: `DELETE /admin/products/{id}`

### Headers:
```
Authorization: Bearer admin_token...
```

### Response (200 OK):
```json
{
    "success": true,
    "message": "Product deleted successfully."
}
```

---

## 14. View All Products List (Admin, Authentication Required)

### Endpoint: `GET /admin/products`

### Headers:
```
Authorization: Bearer admin_token...
```

### Query Parameters (Optional):
- `search` - Search by product name
- `status` - Filter by status (active/inactive)
- `per_page` - Number of products to display per page (default: 15)

### Example: `GET /admin/products?status=active&per_page=20`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Products retrieved successfully.",
    "data": [
        {
            "id": 1,
            "name": "Smartphone",
            "description": "Latest smartphone",
            "price": "500.00",
            "available_stock": 100,
            "flash_sale_start": "2026-07-16T10:00:00.000000Z",
            "flash_sale_end": "2026-07-16T18:00:00.000000Z",
            "status": "active",
            "created_at": "2026-07-16T06:58:16.000000Z",
            "updated_at": "2026-07-16T06:58:16.000000Z"
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100
    }
}
```

---

## 15. View Specific Product (Admin, Authentication Required)

### Endpoint: `GET /admin/products/{id}`

### Headers:
```
Authorization: Bearer admin_token...
```

### Example: `GET /admin/products/1`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Product retrieved successfully.",
    "data": {
        "id": 1,
        "name": "Smartphone",
        "description": "Latest smartphone",
        "price": "500.00",
        "available_stock": 100,
        "flash_sale_start": "2026-07-16T10:00:00.000000Z",
        "flash_sale_end": "2026-07-16T18:00:00.000000Z",
        "status": "active",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z"
    }
}
```

---

## 16. View All Orders (Admin, Authentication Required)

### Endpoint: `GET /admin/orders`

### Headers:
```
Authorization: Bearer admin_token...
```

### Query Parameters (Optional):
- `customer_id` - Filter by customer ID
- `product_id` - Filter by product ID
- `payment_status` - Payment status (pending/paid/failed)
- `status` - Order status (pending/completed/cancelled)
- `date_from` - Start date (YYYY-MM-DD)
- `date_to` - End date (YYYY-MM-DD)
- `search` - Search by order ID

### Example: `GET /admin/orders?payment_status=paid&status=completed&date_from=2026-07-01&date_to=2026-07-31`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Orders retrieved successfully.",
    "data": [
        {
            "id": 1,
            "customer_id": 1,
            "product_id": 1,
            "quantity": 1,
            "total_amount": "500.00",
            "status": "completed",
            "payment_status": "paid",
            "created_at": "2026-07-16T06:58:16.000000Z",
            "updated_at": "2026-07-16T06:58:16.000000Z",
            "customer": {
                "id": 1,
                "name": "John Doe",
                "email": "john@example.com"
            },
            "product": {
                "id": 1,
                "name": "Smartphone",
                "price": "500.00"
            }
        }
    ],
    "meta": {
        "current_page": 1,
        "per_page": 15,
        "total": 100
    }
}
```

---

## 17. View Specific Order (Admin, Authentication Required)

### Endpoint: `GET /admin/orders/{id}`

### Headers:
```
Authorization: Bearer admin_token...
```

### Example: `GET /admin/orders/1`

### Response (200 OK):
```json
{
    "success": true,
    "message": "Order retrieved successfully.",
    "data": {
        "id": 1,
        "customer_id": 1,
        "product_id": 1,
        "quantity": 1,
        "total_amount": "500.00",
        "status": "completed",
        "payment_status": "paid",
        "created_at": "2026-07-16T06:58:16.000000Z",
        "updated_at": "2026-07-16T06:58:16.000000Z",
        "customer": {
            "id": 1,
            "name": "John Doe",
            "email": "john@example.com"
        },
        "product": {
            "id": 1,
            "name": "Smartphone",
            "price": "500.00"
        }
    }
}
```

### Response (404 Not Found - If order is not found):
```json
{
    "success": false,
    "message": "Order not found."
}
```

---

## Testing Tips

1. **You can test using Postman or Insomnia**
2. **Authorization Header must be set for Authentication Required APIs**
3. **Admin role user is required for admin endpoints**
4. **Date format must be correct (Y-m-d H:i:s format)**
5. **You can use per_page parameter for pagination**

## Access Control

- **Customer Role**: Registration, Login, View Products, View Own Orders, View Wallet
- **Admin Role**: All Access + Product CRUD + All Order Management

## Status Codes

- **200**: Success
- **201**: Successfully Created
- **401**: Authentication Failed
- **403**: Permission Denied
- **404**: Resource Not Found
- **422**: Validation Error