# Mobile API Specification

Project: PMPBDT E-commerce Backend
Date: 2026-05-11

## Overview

This document lists the currently available API endpoints for mobile app integration.

### Base URL

Use the `public/api/*.php` endpoints exposed by the current PHP backend.

Example base path:

```text
https://your-domain.com/public/api
```

### Common Response Format

Most endpoints return JSON in this shape:

```json
{
  "success": true,
  "message": "OK",
  "data": {}
}
```

### Auth

For mobile, use Bearer token when available:

```http
Authorization: Bearer <token>
```

If no token is sent, some endpoints still fallback to web session cookies.

### Important Notes

- `auth.php?action=login` returns a JWT token.
- `auth.php?action=me` validates Bearer token.
- `profile.php`, `checkout.php`, `order.php`, `payment.php` accept Bearer token via shared auth helper.
- Webhooks do not require user auth.
- `checkout.php` and `payment.php` still rely on server-side session values such as `selected_items` and `applied_voucher` for the current checkout flow.

---

## 1. Auth API

### 1.1 Login

**Endpoint**

`POST /api/auth.php?action=login`

**Request body**

```json
{
  "username": "john_doe",
  "password": "12345678"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đăng nhập thành công.",
  "data": {
    "user": {
      "user_id": 12,
      "fullname": "John Doe",
      "username": "john_doe",
      "role": "customer",
      "email": "john@example.com"
    },
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 2592000
  }
}
```

**Response 401**

```json
{
  "success": false,
  "message": "Tên đăng nhập hoặc mật khẩu không đúng.",
  "data": []
}
```

---

### 1.2 Register

**Endpoint**

`POST /api/auth.php?action=register`

**Request body**

```json
{
  "fullname": "John Doe",
  "phone": "0901234567",
  "email": "john@example.com",
  "username": "john_doe",
  "password": "12345678",
  "confirm_password": "12345678"
}
```

**Response 201**

```json
{
  "success": true,
  "message": "Đăng ký thành công!",
  "data": {
    "user_id": 15
  }
}
```

---

### 1.3 Logout

**Endpoint**

`POST /api/auth.php?action=logout`

**Request body**

```json
{}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đăng xuất thành công.",
  "data": []
}
```

---

### 1.4 Forgot password - send OTP

**Endpoint**

`POST /api/auth.php?action=forgot-password-send-otp`

**Request body**

```json
{
  "email": "john@example.com"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "OTP đã được gửi đến email của bạn.",
  "data": []
}
```

---

### 1.5 Forgot password - reset with OTP

**Endpoint**

`POST /api/auth.php?action=forgot-password-reset`

**Request body**

```json
{
  "email": "john@example.com",
  "otp": "123456",
  "new_password": "newpass123",
  "confirm_password": "newpass123"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đặt lại mật khẩu thành công! Vui lòng đăng nhập lại.",
  "data": []
}
```

---

### 1.6 2FA verify

**Endpoint**

`POST /api/auth.php?action=two-factor-verify`

**Request body**

```json
{
  "otp_code": "123456"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Xác minh 2FA thành công.",
  "data": {
    "user": {
      "user_id": 12,
      "fullname": "John Doe",
      "username": "john_doe",
      "role": "customer",
      "email": "john@example.com"
    },
    "token": "eyJhbGciOiJIUzI1NiIs...",
    "token_type": "Bearer",
    "expires_in": 2592000
  }
}
```

---

### 1.7 Current token user

**Endpoint**

`GET /api/auth.php?action=me`

**Headers**

```http
Authorization: Bearer <token>
```

**Response 200**

```json
{
  "success": true,
  "message": "Lấy thông tin phiên đăng nhập thành công.",
  "data": {
    "user": {
      "user_id": 12,
      "fullname": "John Doe",
      "username": "john_doe",
      "role": "customer",
      "email": "john@example.com",
      "iat": 1715400000,
      "exp": 1717992000
    }
  }
}
```

---

## 2. Profile API

### 2.1 Get profile + orders

**Endpoint**

`GET /api/profile.php?page=1&limit=5`

**Headers**

```http
Authorization: Bearer <token>
```

**Response 200**

```json
{
  "success": true,
  "message": "Lấy thông tin hồ sơ thành công.",
  "data": {
    "user": {
      "id": 12,
      "fullname": "John Doe",
      "username": "john_doe",
      "email": "john@example.com"
    },
    "orders": [],
    "pagination": {
      "total_orders": 0,
      "total_pages": 0,
      "current_page": 1,
      "limit": 5
    }
  }
}
```

---

### 2.2 Update profile

**Endpoint**

`POST /api/profile.php`

**Headers**

```http
Authorization: Bearer <token>
Content-Type: application/json
```

**Request body**

```json
{
  "action": "update_profile",
  "fullname": "John Doe",
  "username": "john_doe",
  "email": "john@example.com",
  "phone": "0901234567",
  "address": "Hà Nội"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Cập nhật thông tin thành công!",
  "data": []
}
```

---

### 2.3 Change password

**Endpoint**

`POST /api/profile.php`

**Request body**

```json
{
  "action": "change_password",
  "current_password": "12345678",
  "new_password": "newpass123",
  "confirm_password": "newpass123"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Thay đổi mật khẩu thành công!",
  "data": []
}
```

---

### 2.4 Enable 2FA

**Endpoint**

`POST /api/profile.php`

**Request body**

```json
{
  "action": "enable_2fa"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã gửi mã OTP 2FA đến email của bạn.",
  "data": []
}
```

---

### 2.5 Verify 2FA enrollment

**Endpoint**

`POST /api/profile.php`

**Request body**

```json
{
  "action": "verify_2fa_enable",
  "otp_code": "123456"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã bật bảo mật 2 lớp bằng Gmail OTP thành công!",
  "data": []
}
```

---

### 2.6 Disable 2FA

**Endpoint**

`POST /api/profile.php`

**Request body**

```json
{
  "action": "disable_2fa"
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã tắt bảo mật 2 lớp.",
  "data": []
}
```

---

## 3. Catalog API

### 3.1 Product list

**Endpoint**

`GET /api/catalog.php?action=products&cat_id=0&brand_id=0&keyword=&min_price=0&max_price=0&page=1&limit=12`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy danh sách sản phẩm thành công.",
  "data": {
    "products": [],
    "total_products": 0,
    "total_pages": 0,
    "current_page": 1
  }
}
```

---

### 3.2 Product detail

**Endpoint**

`GET /api/catalog.php?action=product-detail&id=123`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy chi tiết sản phẩm thành công.",
  "data": {
    "product": {
      "id": 123,
      "name": "Samsung TV",
      "price": 10000000
    },
    "related_products": [],
    "cross_sell_products": []
  }
}
```

---

### 3.3 Related products

**Endpoint**

`GET /api/catalog.php?action=related&product_id=123&limit=6`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy sản phẩm liên quan thành công.",
  "data": {
    "items": []
  }
}
```

---

### 3.4 Same brand products

**Endpoint**

`GET /api/catalog.php?action=same-brand&product_id=123&limit=6`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy sản phẩm cùng thương hiệu thành công.",
  "data": {
    "items": []
  }
}
```

---

### 3.5 Suggested products

**Endpoint**

`GET /api/catalog.php?action=suggested&cat_id=0&brand_id=0&limit=12&offset=0`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy sản phẩm đề xuất thành công.",
  "data": {
    "items": []
  }
}
```

---

### 3.6 Categories

**Endpoint**

`GET /api/catalog.php?action=categories`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy danh mục thành công.",
  "data": {
    "items": []
  }
}
```

---

### 3.7 Brands

**Endpoint**

`GET /api/catalog.php?action=brands`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy thương hiệu thành công.",
  "data": {
    "items": []
  }
}
```

---

## 4. Cart API

### 4.1 View cart

**Endpoint**

`GET /api/cart.php?action=view`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy giỏ hàng thành công.",
  "data": {
    "items": [],
    "cart_count": 0
  }
}
```

---

### 4.2 Add item

**Endpoint**

`POST /api/cart.php?action=add`

**Request body**

```json
{
  "product_id": 123,
  "quantity": 1
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã thêm sản phẩm vào giỏ hàng.",
  "data": {
    "cart_count": 3
  }
}
```

---

### 4.3 Update item

**Endpoint**

`POST /api/cart.php?action=update`

**Request body**

```json
{
  "cart_id": 10,
  "quantity": 2
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã cập nhật số lượng sản phẩm.",
  "data": {
    "cart_count": 3
  }
}
```

---

### 4.4 Delete item

**Endpoint**

`POST /api/cart.php?action=delete`

**Request body**

```json
{
  "cart_id": 10
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã xóa sản phẩm khỏi giỏ hàng.",
  "data": {
    "cart_count": 2
  }
}
```

---

### 4.5 Increase item

**Endpoint**

`POST /api/cart.php?action=increase`

**Request body**

```json
{
  "cart_id": 10
}
```

---

### 4.6 Decrease item

**Endpoint**

`POST /api/cart.php?action=decrease`

**Request body**

```json
{
  "cart_id": 10
}
```

---

### 4.7 Cart count

**Endpoint**

`GET /api/cart.php?action=count`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy số lượng giỏ hàng thành công.",
  "data": {
    "cart_count": 2
  }
}
```

---

### 4.8 Clear cart

**Endpoint**

`POST /api/cart.php?action=clear`

**Response 200**

```json
{
  "success": true,
  "message": "Đã xóa toàn bộ giỏ hàng.",
  "data": {
    "cart_count": 0
  }
}
```

---

## 5. Checkout API

### 5.1 Summary

**Endpoint**

`GET /api/checkout.php?action=summary`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy thông tin thanh toán thành công.",
  "data": {
    "user": {},
    "items": [],
    "subtotal": 0,
    "bundle_discount": 0,
    "bundle_message": "",
    "voucher": null,
    "final_total": 0
  }
}
```

---

### 5.2 Create order

**Endpoint**

`POST /api/checkout.php?action=create_order`

**Request body**

```json
{
  "fullname": "John Doe",
  "phone": "0901234567",
  "address": "Hà Nội",
  "note": "Giao giờ hành chính",
  "payment_method": "cod"
}
```

**Response 201**

```json
{
  "success": true,
  "message": "Tạo đơn hàng thành công.",
  "data": {
    "order_id": 1001,
    "payment_method": "cod",
    "redirect_to": "track_order.php"
  }
}
```

If payment method is `qr`:

```json
{
  "success": true,
  "message": "Tạo đơn hàng thành công.",
  "data": {
    "order_id": 1001,
    "payment_method": "qr",
    "redirect_to": "payment.php?order_id=1001"
  }
}
```

---

### 5.3 Apply voucher

**Endpoint**

`POST /api/checkout.php?action=apply_voucher`

**Request body**

```json
{
  "code": "SALE10",
  "total_price": 1000000,
  "bundle_discount": 0
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Áp dụng mã thành công!",
  "data": {
    "discount_amount": 100000,
    "new_total": 900000,
    "discount_text": "Giảm 10%"
  }
}
```

---

## 6. Order API

### 6.1 List orders

**Endpoint**

`GET /api/order.php?action=list&limit=20&offset=0`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy danh sách đơn hàng thành công.",
  "data": {
    "items": []
  }
}
```

---

### 6.2 Order detail

**Endpoint**

`GET /api/order.php?action=detail&id=1001`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy chi tiết đơn hàng thành công.",
  "data": {
    "order": {},
    "items": []
  }
}
```

---

### 6.3 Order status

**Endpoint**

`GET /api/order.php?action=status&id=1001`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy trạng thái thành công.",
  "data": {
    "status": "pending"
  }
}
```

---

## 7. Payment API

### 7.1 Payment details

**Endpoint**

`GET /api/payment.php?action=details&order_id=1001`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy thông tin thanh toán thành công.",
  "data": {
    "order": {},
    "bank": {
      "bank_id": "MB",
      "account_no": "31220066649668",
      "account_name": "NGUYEN ANH QUY"
    },
    "amount": 1000000,
    "transfer_content": "DMPRO1001",
    "qr_url": "https://img.vietqr.io/image/...",
    "payos_enabled": true
  }
}
```

---

### 7.2 Confirm manual payment

**Endpoint**

`POST /api/payment.php?action=confirm_manual`

**Request body**

```json
{
  "order_id": 1001
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Đã xác nhận thanh toán thủ công.",
  "data": {
    "order_id": 1001
  }
}
```

---

### 7.3 Payment status

**Endpoint**

`GET /api/payment.php?action=status&order_id=1001`

**Response 200**

```json
{
  "success": true,
  "message": "Lấy trạng thái đơn hàng thành công.",
  "data": {
    "status": "processing"
  }
}
```

---

### 7.4 Create PayOS checkout URL

**Endpoint**

`POST /api/payment.php?action=payos_create`

**Request body**

```json
{
  "order_id": 1001
}
```

**Response 200**

```json
{
  "success": true,
  "message": "Tạo link thanh toán PayOS thành công.",
  "data": {
    "checkout_url": "https://my.payos.vn/..."
  }
}
```

---

## 8. Webhooks

### 8.1 PayOS webhook

**Endpoint**

`POST /api/webhook_payos.php`

**Request body example**

```json
{
  "data": {
    "orderCode": 1001,
    "status": "PAID"
  }
}
```

**Response 200**

```json
{
  "success": true,
  "message": "OK",
  "data": []
}
```

---

### 8.2 SePay webhook

**Endpoint**

`POST /api/webhook_sepay.php`

**Request body example**

```json
{
  "order_id": 1001
}
```

**Response 200**

```json
{
  "success": true,
  "message": "OK",
  "data": []
}
```

---

## 9. Error format examples

### 401 Unauthorized

```json
{
  "success": false,
  "message": "Chưa đăng nhập.",
  "data": []
}
```

### 422 Validation error

```json
{
  "success": false,
  "message": "Vui lòng nhập đầy đủ thông tin nhận hàng.",
  "data": []
}
```

### 404 Not found

```json
{
  "success": false,
  "message": "Không tìm thấy đơn hàng.",
  "data": []
}
```

---

## 10. Suggested implementation order for mobile

1. `auth.php`
2. `profile.php`
3. `catalog.php`
4. `cart.php`
5. `checkout.php`
6. `order.php`
7. `payment.php`
8. Webhooks

---

## 11. Notes for dev mobile

- Use `token` returned by login for all authenticated requests.
- Some checkout/payment flows still depend on server session state.
- If you build a pure mobile flow, you may want a future refactor to move `selected_items` and `applied_voucher` from session into explicit request payloads.
- Response payloads may include extra fields from repositories; handle them defensively on the mobile side.
