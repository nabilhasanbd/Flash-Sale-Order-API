Here's the complete list of all API endpoints and test data for the Flash-Sale Order API:

---

## **API Endpoints**

### **Public (No Authentication)**

| Method | Endpoint | Description |
|--------|----------|-------------|
| `POST` | `/api/register` | Register a new customer account |
| `POST` | `/api/login` | Login (rate limited: 5/min) |
| `GET` | `/api/products` | List available flash-sale products |
| `GET` | `/api/products/{product}` | View product details |

### **Authenticated (Token Required)**

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| `POST` | `/api/logout` | Logout user | - |
| `GET` | `/api/me` | Get current user profile | - |
| `GET` | `/api/wallet` | Get wallet balance | - |
| `GET` | `/api/wallet/transactions` | Get wallet transaction history | - |

### **Customer Order APIs** (Authenticated + Customer Role)

| Method | Endpoint | Description | Rate Limit |
|--------|----------|-------------|------------|
| `POST` | `/api/orders` | Create order (requires `Idempotency-Key` header) | 10/min |
| `GET` | `/api/orders` | List customer's orders | - |
| `GET` | `/api/orders/{order}` | View order details | - |

### **Admin APIs** (Authenticated + Admin Role)

| Method | Endpoint | Description |
|--------|----------|-------------|
| `GET` | `/api/admin/products` | List all products (admin) |
| `POST` | `/api/admin/products` | Create product (admin) |
| `GET` | `/api/admin/products/{product}` | View product (admin) |
| `PUT` | `/api/admin/products/{product}` | Update product (admin) |
| `DELETE` | `/api/admin/products/{product}` | Delete product (admin) |
| `GET` | `/api/admin/orders` | List all orders (admin) |
| `GET` | `/api/admin/orders/{order}` | View order details (admin) |

---

## **Test Data Serially**

### **1. Authentication**

#### **Register Payload**
```json
{
  "name": "John Doe",
  "email": "john@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```
**Response (201):**
```json
{
  "success": true,
  "message": "User registered successfully.",
  "token": "...",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "customer"
  }
}
```

#### **Login Payload**
```json
{
  "email": "john@example.com",
  "password": "password123"
}
```
**Response (200):**
```json
{
  "success": true,
  "message": "Login successful.",
  "token": "...",
  "data": {
    "id": 1,
    "name": "John Doe",
    "email": "john@example.com",
    "role": "customer"
  }
}
```

---

### **2. Products (Customer)**

#### **List Products (GET `/api/products`)**
**Response (200):**
```json
{
  "success": true,
  "message": "Products retrieved successfully.",
  "data": [
    {
      "id": 1,
      "name": "iPhone 16",
      "description": "Latest Apple Phone",
      "price": "1200.00",
      "available_stock": 10,
      "flash_sale_start": "2026-07-22 14:00:00",
      "flash_sale_end": "2026-07-22 16:00:00",
      "status": "active",
      "flash_sale_max_quantity_per_order": 3
    }
  ]
}
```

---

### **3. Products (Admin)**

#### **Create Product Payload**
```json
{
  "name": "iPhone 16",
  "description": "Latest Apple Phone",
  "price": 1200,
  "available_stock": 100,
  "flash_sale_start": "2026-07-22 14:00:00",
  "flash_sale_end": "2026-07-22 16:00:00",
  "status": "active",
  "flash_sale_max_quantity_per_order": 3
}
```
**Response (201):**
```json
{
  "success": true,
  "message": "Product created successfully.",
  "data": {
    "id": 1,
    "name": "iPhone 16",
    "description": "Latest Apple Phone",
    "price": "1200.00",
    "available_stock": 100,
    "flash_sale_start": "2026-07-22 14:00:00",
    "flash_sale_end": "2026-07-22 16:00:00",
    "status": "active",
    "flash_sale_max_quantity_per_order": 3
  }
}
```

---

### **4. Orders**

#### **Create Order Payload**
**Headers:**
```
Authorization: Bearer <token>
Idempotency-Key: unique-key-123
```
**Body:**
```json
{
  "product_id": 1,
  "quantity": 2,
  "coupon_code": "SAVE50"
}
```
**Success Response (201):**
```json
{
  "success": true,
  "message": "Order placed successfully.",
  "data": {
    "id": 1,
    "user_id": 1,
    "product_id": 1,
    "coupon_id": 1,
    "subtotal": "2400.00",
    "discount": "1200.00",
    "total": "1200.00",
    "status": "completed",
    "payment_status": "paid",
    "created_at": "2026-07-22T15:00:00.000000Z",
    "updated_at": "2026-07-22T15:00:00.000000Z",
    "order_items": [
      {
        "id": 1,
        "order_id": 1,
        "product_id": 1,
        "quantity": 2,
        "unit_price": "1200.00",
        "subtotal": "2400.00"
      }
    ],
    "payment_transaction": {
      "id": 1,
      "reference": "TXN-20260722-0001AB",
      "amount": "1200.00",
      "status": "success",
      "wallet_transactions": [
        {
          "id": 1,
          "wallet_id": 1,
          "amount": "1200.00",
          "type": "debit",
          "balance_before": "10000.00",
          "balance_after": "8800.00"
        },
        {
          "id": 2,
          "wallet_id": 2,
          "amount": "1200.00",
          "type": "credit",
          "balance_before": "0.00",
          "balance_after": "1200.00"
        }
      ]
    }
  }
}
```

**Pending Response (202 - on transient failure):**
```json
{
  "success": true,
  "message": "Order is being processed.",
  "data": {
    "id": 1,
    "status": "pending",
    "payment_status": "pending"
  }
}
```

**Idempotent Replay Response (200):**
```json
{
  "success": true,
  "message": "Order already processed.",
  "data": { ... }
}
```

---

### **5. Wallet**

#### **Get Wallet Balance (GET `/api/wallet`)**
**Response (200):**
```json
{
  "success": true,
  "message": "Wallet retrieved successfully.",
  "data": {
    "id": 1,
    "user_id": 1,
    "balance": "8800.00"
  }
}
```

#### **Wallet Transactions (GET `/api/wallet/transactions`)**
**Response (200):**
```json
{
  "success": true,
  "message": "Wallet transactions retrieved successfully.",
  "data": [
    {
      "id": 1,
      "amount": "1200.00",
      "type": "debit",
      "balance_before": "10000.00",
      "balance_after": "8800.00",
      "reference": "WTX-20260722-ABCDEFGHIJ",
      "description": "Flash Sale Purchase - Order #1",
      "created_at": "2026-07-22T15:00:00.000000Z"
    }
  ]
}
```

---

## **Test Factories (Pre-populated Data)**

### **User Factory**
| Field | Default |
|-------|---------|
| `role` | `customer` |
| `name` | Random name |
| `email` | Unique email |
| `password` | Hashed |

**States:**
- `admin()` → `role = 'admin'`
- `merchant()` → `role = 'merchant'`

### **Product Factory**
| Field | Default |
|-------|---------|
| `status` | `active` |
| `available_stock` | 10 |
| `flash_sale_start` | `now()->subHour()` (past) |
| `flash_sale_end` | `now()->addHour()` (future) |
| `price` | Random (e.g., 100.00) |
| `flash_sale_max_quantity_per_order` | 3 |

### **Wallet Factory**
| Field | Default |
|-------|---------|
| `balance` | 0.00 |
| `user_id` | Provided |

### **Coupon Factory**
| Field | Default |
|-------|---------|
| `code` | Unique |
| `status` | `true` (active) |
| `expires_at` | `now()->addDay()` |
| `usage_limit` | 100 |
| `used_count` | 0 |
| `minimum_purchase` | 0 |

**Factory Methods:**
- `percentage(25, 0)` → 25% discount, 0 minimum purchase
- `fixed(75, 0)` → $75 fixed discount, 0 minimum purchase

---

## **Rate Limits**
| Endpoint | Limit |
|----------|-------|
| `/api/login` | 5 requests/minute per email/IP |
| `/api/orders` (POST) | 10 requests/minute per user/IP |

---

## **Common HTTP Status Codes**
| Code | Meaning |
|------|---------|
| 200 | Success |
| 201 | Created |
| 202 | Accepted (async processing) |
| 401 | Unauthorized |
| 403 | Forbidden (wrong role) |
| 404 | Not found |
| 409 | Conflict (idempotency key used, duplicate order) |
| 422 | Validation error |
| 429 | Too many requests (rate limit) |
| 500 | Server error (rare, on unexpected exceptions) |