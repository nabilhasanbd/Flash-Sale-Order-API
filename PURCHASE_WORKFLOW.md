# Customer Product Purchase Workflow

## API Workflow

### 1. Customer Authentication
```bash
POST /api/login
{
  "email": "customer@example.com",
  "password": "password"
}

Response:
{
  "success": true,
  "message": "Login successful.",
  "token": "2|...",
  "data": {
    "id": 1,
    "name": "Customer Name",
    "email": "customer@example.com",
    "role": "customer"
  }
}
```

### 2. Browse Products
```bash
GET /api/products?status=active&search=iphone

Response:
{
  "data": [
    {
      "id": 1,
      "name": "iPhone 16",
      "price": "900.00",
      "available_stock": 50,
      "flash_sale_start": "2026-07-15T10:00:00+06:00",
      "flash_sale_end": "2026-07-15T18:00:00+06:00",
      "status": "active"
    }
  ],
  "current_page": 1,
  "total": 1
}
```

### 3. Place Order
```bash
POST /api/orders
Headers: Authorization: Bearer {token}
Body:
{
  "product_id": 1,
  "quantity": 2,
  "coupon_code": "SAVE10" // optional
}

Response:
{
  "success": true,
  "message": "Order placed successfully.",
  "data": {
    "id": 15,
    "user_id": 1,
    "subtotal": "1800.00",
    "discount": "180.00",
    "total": "1620.00",
    "payment_status": "paid",
    "status": "completed",
    "created_at": "2026-07-15T10:30:45+06:00"
  }
}
```

## Application Workflow

```
Customer → OrderController → OrderService → DB Transaction
     ↓                 ↓              ↓
  Browse          Validate       Lock Resources
  Products        Stock         Deduct Balance
                    Balance       Create Order
                    Coupon        Debit Stock
                                  Create Transaction
                    ↓              ↓
               After Commit → Dispatch Event
                                Send Notification
```

## Database Workflow

### Transaction Flow (Atomic - All or Nothing)

```sql
BEGIN TRANSACTION;

-- 1. Lock Product Row
SELECT * FROM products WHERE id = 1 FOR UPDATE;

-- 2. Check Available Stock
-- If available_stock < quantity, ROLLBACK;

-- 3. Lock Wallet Row  
SELECT * FROM wallets WHERE user_id = 1 FOR UPDATE;

-- 4. Check Wallet Balance
-- If balance < total, ROLLBACK;

-- 5. Check for Duplicate Order
SELECT * FROM orders WHERE user_id = 1 AND product_id = 1;
-- If exists, ROLLBACK;

-- 6. Create Order
INSERT INTO orders (
  user_id, coupon_id, subtotal, discount, total,
  payment_status, status, created_at, updated_at
) VALUES (
  1, NULL, '1800.00', '0.00', '1800.00',
  'paid', 'completed', NOW(), NOW()
);

-- 7. Create Order Item
INSERT INTO order_items (
  order_id, product_id, quantity, unit_price, subtotal
) VALUES (
  15, 1, 2, '900.00', '1800.00'
);

-- 8. Update Product Stock
UPDATE products 
SET available_stock = available_stock - 2
WHERE id = 1;

-- 9. Update Wallet Balance
UPDATE wallets 
SET balance = balance - 1800.00
WHERE id = 1;

-- 10. Create Wallet Transaction
INSERT INTO wallet_transactions (
  wallet_id, order_id, type, amount, 
  balance_before, balance_after, reference, description,
  created_at, updated_at
) VALUES (
  1, 15, 'debit', '1800.00', 
  '5000.00', '3200.00', 'WTX-20260715-12345', 'Flash Sale Purchase',
  NOW(), NOW()
);

-- 11. Update Coupon Usage (if applicable)
UPDATE coupons 
SET used_count = used_count + 1
WHERE id = 1;

-- 12. Create Coupon Usage Record
INSERT INTO coupon_usages (
  user_id, coupon_id, order_id, used_at
) VALUES (
  1, 1, 15, NOW()
);

COMMIT;

-- After Commit
-- Dispatch OrderPlaced Event
-- Queue Notification Job
-- Send Database Notification
```

## Step-by-Step Process

### Phase 1: Validation (Before Transaction)

1. **Product Validation**
   - Product exists?
   - Product is active?
   - Flash sale is running?
   - Stock available?

2. **Coupon Validation** (if provided)
   - Coupon code valid?
   - Coupon active?
   - Coupon not expired?
   - Usage limit not exceeded?
   - Minimum purchase met?

### Phase 2: Transaction Execution

3. **Lock Resources**
   - Lock product row (FOR UPDATE)
   - Lock wallet row (FOR UPDATE)

4. **Re-validate Business Rules**
   - Stock still available?
   - Wallet still sufficient?
   - No duplicate orders?

5. **Execute Business Logic**
   - Create order record
   - Create order items
   - Deduct product stock
   - Update wallet balance
   - Create transaction record
   - Update coupon usage

### Phase 3: Post-Transaction

6. **Event Dispatch** (After Commit)
   - Dispatch OrderPlaced event
   - Load order relationships

7. **Notification** (Async)
   - Queue SendNotificationJob
   - Create database notification
   - Log notification sent

## Database State Changes

### Before Purchase

**Users:**
| id | name | email | role |
|----|------|-------|------|
| 1  | John | john@example.com | customer |

**Wallets:**
| id | user_id | balance |
|----|---------|---------|
| 1  | 1       | 5000.00 |

**Products:**
| id | name | price | available_stock | status |
|----|------|-------|-----------------|--------|
| 1  | iPhone 16 | 900.00 | 50 | active |

**Coupons:**
| id | code | value | used_count | status |
|----|------|-------|------------|--------|
| 1  | SAVE10 | 10 | 0 | true |

### After Purchase

**Orders:**
| id | user_id | product | subtotal | discount | total | payment_status | status |
|----|---------|---------|----------|----------|-------|----------------|--------|
| 15 | 1       | iPhone 16 | 1800.00 | 180.00 | 1620.00 | paid | completed |

**Order Items:**
| id | order_id | product_id | quantity | unit_price | subtotal |
|----|----------|------------|----------|------------|----------|
| 1  | 15       | 1          | 2        | 900.00     | 1800.00 |

**Wallets:**
| id | user_id | balance |
|----|---------|---------|
| 1  | 1       | 3380.00 |

**Wallet Transactions:**
| id | wallet_id | order_id | type | amount | balance_before | balance_after | reference |
|----|-----------|----------|------|--------|----------------|---------------|-----------|
| 1  | 1         | 15       | debit | 1800.00 | 5000.00 | 3200.00 | WTX-20260715-12345 |

**Products:**
| id | name | price | available_stock | status |
|----|------|-------|-----------------|--------|
| 1  | iPhone 16 | 900.00 | 48 | active |

**Coupons:**
| id | code | value | used_count | status |
|----|------|-------|------------|--------|
| 1  | SAVE10 | 10 | 1 | true |

**Coupon Usages:**
| id | user_id | coupon_id | order_id | used_at |
|----|---------|-----------|----------|---------|
| 1  | 1       | 1         | 15       | 2026-07-15 10:30:45 |

**Notifications:**
| id | type | notifiable_type | notifiable_id | data |
|----|------|-----------------|---------------|------|
| uuid | App\Notifications\OrderPlacedNotification | App\Models\User | 1 | {"title":"Order Successful",...} |

## Error Handling

### Transaction Rollback Scenarios

1. **Product Not Found**
   - Transaction: ROLLBACK
   - Response: 404 Not Found

2. **Insufficient Stock**
   - Transaction: ROLLBACK  
   - Response: 400 Bad Request (InsufficientStockException)

3. **Insufficient Balance**
   - Transaction: ROLLBACK
   - Response: 400 Bad Request (InsufficientBalanceException)

4. **Duplicate Purchase**
   - Transaction: ROLLBACK
   - Response: 400 Bad Request (DuplicatePurchaseException)

5. **Invalid Coupon**
   - Transaction: ROLLBACK
   - Response: 400 Bad Request (CouponExpiredException/UsageLimitExceededException)

### Post-Transaction Failures

6. **Notification Failure**
   - Transaction: COMMITTED
   - Notification: Queued for retry
   - Order: Still successful

## Key Features

✅ **Atomic Transactions** - All-or-nothing database operations  
✅ **Row Locking** - Prevents race conditions  
✅ **Duplicate Prevention** - One purchase per product  
✅ **Real-time Stock** - Immediate inventory update  
✅ **Secure Balance** - Wallet locking prevents overdrafts  
✅ **Async Notifications** - Non-blocking user experience  
✅ **Event-Driven** - Extensible notification system  
✅ **Audit Trail** - Complete transaction history  

## Security Considerations

1. **Authentication Required** - Bearer token validation
2. **Idempotency** - Prevent duplicate order submissions
3. **Resource Locking** - Prevents concurrent purchase conflicts
4. **Balance Validation** - Ensures sufficient funds before debit
5. **Stock Validation** - Prevents overselling
6. **Coupon Validation** - Secure discount application

## Performance Optimizations

1. **Indexed Queries** - Fast lookups on user_id, product_id
2. **Row Locking** - Minimal lock duration
3. **Bulk Operations** - Single transaction for all updates
4. **Async Notifications** - Non-blocking order processing
5. **Cached Data** - Product metadata in notifications

This workflow ensures a complete, secure, and efficient purchasing experience for customers.