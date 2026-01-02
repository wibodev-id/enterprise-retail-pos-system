# API Documentation

## Overview

RESTful API documentation for the Enterprise Retail POS system. All endpoints require authentication unless specified otherwise.

## Authentication

The API uses Laravel Sanctum for token-based authentication.

### Login
```http
POST /api/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password"
}
```

**Response:**
```json
{
  "success": true,
  "token": "1|abc123...",
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com",
    "roles": ["cashier"]
  }
}
```

---

## Products

### Search Product by Barcode
```http
POST /api/products/search
Authorization: Bearer {token}
Content-Type: application/json

{
  "barcode": "1234567890123"
}
```

**Response (Success):**
```json
{
  "success": true,
  "product": {
    "id": 1,
    "barcode": "1234567890123",
    "title": "Product Name",
    "price": 25000,
    "category": "Electronics",
    "available_stock": 50,
    "image_url": "/storage/products/image.jpg"
  }
}
```

**Response (Not Found):**
```json
{
  "success": false,
  "message": "Product not found"
}
```

### List Products
```http
GET /api/products?page=1&per_page=20&search=keyword&category_id=1
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "data": [
    {
      "id": 1,
      "barcode": "1234567890123",
      "title": "Product Name",
      "price": 25000,
      "category": { "id": 1, "name": "Electronics" },
      "stocks": [
        { "location_id": 1, "quantity": 50 }
      ]
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 5,
    "per_page": 20,
    "total": 100
  }
}
```

---

## Cart Operations

### Get Current Cart
```http
GET /api/cart
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "cart": {
    "items": [
      {
        "id": 1,
        "product_id": 1,
        "product": {
          "title": "Product Name",
          "price": 25000,
          "barcode": "1234567890123"
        },
        "quantity": 2,
        "subtotal": 50000
      }
    ],
    "total_items": 2,
    "total_amount": 50000
  }
}
```

### Add to Cart
```http
POST /api/cart/add
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 1,
  "quantity": 1,
  "location_id": 1
}
```

**Response (Success):**
```json
{
  "success": true,
  "message": "Product added to cart",
  "cart_item": {
    "id": 1,
    "product_id": 1,
    "quantity": 1,
    "subtotal": 25000
  }
}
```

**Response (Insufficient Stock):**
```json
{
  "success": false,
  "message": "Only 5 units available",
  "available": 5
}
```

### Update Cart Quantity
```http
PUT /api/cart/{cart_id}
Authorization: Bearer {token}
Content-Type: application/json

{
  "quantity": 3
}
```

### Remove from Cart
```http
DELETE /api/cart/{cart_id}
Authorization: Bearer {token}
```

### Clear Cart
```http
POST /api/cart/clear
Authorization: Bearer {token}
```

---

## Transactions

### Checkout / Create Transaction
```http
POST /api/transactions/store
Authorization: Bearer {token}
Content-Type: application/json

{
  "location_id": 1,
  "cash_received": 100000,
  "customer_id": null,
  "notes": "Optional notes"
}
```

**Response:**
```json
{
  "success": true,
  "transaction": {
    "id": 1,
    "invoice_number": "INV-20260102-ABC12",
    "total": 75000,
    "cash_received": 100000,
    "change": 25000,
    "created_at": "2026-01-02T15:30:00Z",
    "details": [
      {
        "product_id": 1,
        "product_name": "Product Name",
        "quantity": 3,
        "price": 25000,
        "subtotal": 75000
      }
    ]
  }
}
```

### Get Transaction History
```http
GET /api/transactions?start_date=2026-01-01&end_date=2026-01-31&location_id=1
Authorization: Bearer {token}
```

### Print Receipt
```http
GET /api/transactions/{id}/print
Authorization: Bearer {token}
```

---

## Stock Management

### Get Stock by Product
```http
GET /api/stocks?product_id=1
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "stocks": [
    {
      "location_id": 1,
      "location_name": "Main Store",
      "quantity": 50,
      "status": "approved"
    },
    {
      "location_id": 2,
      "location_name": "Branch A",
      "quantity": 30,
      "status": "approved"
    }
  ],
  "total_available": 80
}
```

### Input Stock
```http
POST /api/stocks/input
Authorization: Bearer {token}
Content-Type: application/json

{
  "product_id": 1,
  "location_id": 1,
  "quantity": 100,
  "notes": "New shipment"
}
```

**Response:**
```json
{
  "success": true,
  "message": "Stock input submitted for approval",
  "stock": {
    "id": 1,
    "status": "pending"
  }
}
```

---

## Approval Workflow

### Get Pending Approvals
```http
GET /api/approvals/pending
Authorization: Bearer {token}
```

**Response:**
```json
{
  "success": true,
  "approvals": {
    "product_edits": [
      {
        "id": 1,
        "product": { "id": 1, "title": "Old Name" },
        "changes": { "title": "New Name", "price": 30000 },
        "requested_by": { "id": 2, "name": "John" },
        "created_at": "2026-01-02T10:00:00Z"
      }
    ],
    "stock_adjustments": [],
    "transaction_deletes": []
  }
}
```

### Approve Request
```http
POST /api/approvals/{type}/{id}/approve
Authorization: Bearer {token}
```

**Types:** `product-edit`, `stock-adjustment`, `transaction-delete`

### Reject Request
```http
POST /api/approvals/{type}/{id}/reject
Authorization: Bearer {token}
Content-Type: application/json

{
  "reason": "Price change too drastic"
}
```

---

## Reports

### Sales Report
```http
GET /api/reports/sales?start_date=2026-01-01&end_date=2026-01-31&group_by=daily
Authorization: Bearer {token}
```

### Export to Excel
```http
GET /api/reports/sales/export?format=xlsx&start_date=2026-01-01&end_date=2026-01-31
Authorization: Bearer {token}
```

### Export to PDF
```http
GET /api/reports/sales/export?format=pdf&start_date=2026-01-01&end_date=2026-01-31
Authorization: Bearer {token}
```

---

## Error Responses

All errors follow consistent format:

```json
{
  "success": false,
  "message": "Error description",
  "errors": {
    "field_name": ["Validation error message"]
  }
}
```

### HTTP Status Codes

| Code | Description |
|------|-------------|
| 200 | Success |
| 201 | Created |
| 400 | Bad Request |
| 401 | Unauthorized |
| 403 | Forbidden |
| 404 | Not Found |
| 422 | Validation Error |
| 429 | Too Many Requests |
| 500 | Server Error |

---

## Rate Limiting

- **Login endpoint:** 5 requests per minute
- **General API:** 60 requests per minute
- **Search endpoints:** 30 requests per minute
