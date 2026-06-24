# BFS App API — Full Documentation

> Custom WooCommerce REST API endpoints for the BFS App. Features JWT auth, persistent/syncable user carts, coupons, shipping, cart fees, batch API, checkout, homepage/header/footer content managers, reviews, and Instagram feed integration.

---

## Table of Contents

1. [Installation](#installation)
2. [Authentication](#authentication)
3. [Cart API](#cart-api)
4. [Coupon API](#coupon-api)
5. [Shipping API](#shipping-api)
6. [Fees API](#fees-api)
7. [Checkout API](#checkout-api)
8. [Batch API](#batch-api)
9. [Addresses API](#addresses-api)
10. [Account API](#account-api)
11. [App Content APIs](#app-content-apis)
    - [Header API](#header-api)
    - [Footer API](#footer-api)
    - [Homepage API](#homepage-api)
    - [Instagram Feed API](#instagram-feed-api)
12. [Reviews API](#reviews-api)
13. [Guest Cart Flow](#guest-cart-flow)
14. [Frontend Integration Examples](#frontend-integration-examples)
15. [WordPress Filters & Hooks](#wordpress-filters--hooks)
16. [Troubleshooting](#troubleshooting)
17. [API Reference Summary](#api-reference-summary)

---

## Installation

### Requirements
- WordPress 5.8+
- WooCommerce 6.0+
- PHP 7.4+

### Steps
1. Upload `bfs-woocommerce-app-api/` folder to `/wp-content/plugins/`
2. Activate from **Plugins → Installed Plugins**
3. The plugin automatically checks and creates the custom database table `wp_bfs_carts` on initialization.
4. Ensure WordPress permalinks are set to **Post name** (`/wp-admin/options-permalink.php`).

### WordPress Configuration (`wp-config.php`)
```php
// Make sure AUTH_KEY is set to a strong random value
// This is used as JWT HMAC secret
define('AUTH_KEY', 'your-very-long-random-string-here-at-least-32-chars');
```

---

## Authentication

All user-restricted endpoints use **JWT Bearer tokens**. Send the token in every request:
```
Authorization: Bearer <your_token_here>
```

Guest users use a **cart key** instead for cart state persistence:
```
X-Cart-Key: guest_550e8400-e29b-41d4-a716-446655440000
```

---

### POST /bfsapp/v1/auth/login

**Login and get JWT token.**

```json
// Request
{
  "username": "customer@example.com",
  "password": "mypassword"
}

// Response 200
{
  "token": "eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9...",
  "token_type": "Bearer",
  "expires_at": "2026-05-17T10:00:00+00:00",
  "user": {
    "id": 42,
    "username": "john",
    "email": "customer@example.com",
    "first_name": "John",
    "last_name": "Doe",
    "roles": ["customer"]
  }
}
```

---

### POST /bfsapp/v1/auth/register

**Register new customer account.**

```json
// Request
{
  "username": "newuser",
  "email": "new@example.com",
  "password": "SecurePass123!",
  "first_name": "Rahul",
  "last_name": "Shah"
}

// Response 200 — same as login response
```

---

### GET /bfsapp/v1/auth/me
*Requires: JWT Token*

**Get current user info.**

```json
// Response
{
  "id": 42,
  "username": "john",
  "email": "john@example.com",
  "first_name": "John",
  "last_name": "Doe",
  "display_name": "John Doe",
  "roles": ["customer"],
  "avatar": "https://yoursite.com/wp-content/..."
}
```

---

### POST /bfsapp/v1/auth/guest

**Get a guest cart key (for non-logged-in users).**

```json
// Response
{
  "cart_key": "guest_550e8400-e29b-41d4-a716-446655440000",
  "note": "Send this as X-Cart-Key header with every cart request."
}
```

---

### POST /bfsapp/v1/auth/refresh
*Requires: JWT Token*

**Refresh an expiring token. Returns fresh token with new expiry.**

---

## Cart API

Cart data is **automatically persisted** per user in the database across all devices and syncs with WooCommerce native sessions.
- Logged-in → identified by `user_{id}` (send JWT)
- Guest → identified by `X-Cart-Key` header

---

### GET /bfsapp/v1/cart

**Get full cart with totals.**

```bash
# Logged-in user
curl -H "Authorization: Bearer <token>" https://yoursite.com/wp-json/bfsapp/v1/cart

# Guest
curl -H "X-Cart-Key: guest_uuid" https://yoursite.com/wp-json/bfsapp/v1/cart
```

```json
// Response
{
  "items": [
    {
      "key": "a1b2c3d4e5f6...",
      "product_id": 123,
      "variation_id": 0,
      "name": "Blue Cotton T-Shirt",
      "sku": "SHIRT-BLU-M",
      "quantity": 2,
      "price": "599.00",
      "regular_price": "799.00",
      "sale_price": "599.00",
      "line_total": "1198.00",
      "image": "https://yoursite.com/wp-content/...",
      "permalink": "https://yoursite.com/product/blue-shirt/",
      "stock_status": "instock",
      "stock_quantity": 15,
      "variation": { "attribute_pa_color": "blue" }
    }
  ],
  "item_count": 2,
  "unique_products": 1,
  "coupons": [],
  "fees": [],
  "shipping": null,
  "shipping_address": {},
  "totals": {
    "subtotal": "1198.00",
    "discount": "0.00",
    "fees": "0.00",
    "shipping": "0.00",
    "total": "1198.00",
    "currency": "INR",
    "currency_symbol": "₹",
    "currency_pos": "left",
    "decimals": 2
  }
}
```

---

### POST /bfsapp/v1/cart/add

**Add product to cart.**

```json
// Request
{
  "product_id": 123,
  "quantity": 2,
  "variation_id": 456,       // optional, for variable products
  "variation": { "attribute_pa_color": "blue" } // optional variation attributes
}

// Response — full cart (same as GET /cart)
```

---

### PUT /bfsapp/v1/cart/item/{key}

**Update item quantity. Set quantity to 0 to remove.**

```json
// Request
{ "quantity": 3 }
```

---

### DELETE /bfsapp/v1/cart/item/{key}

**Remove item from cart.**

```bash
curl -X DELETE -H "Authorization: Bearer <token>" \
  https://yoursite.com/wp-json/bfsapp/v1/cart/item/a1b2c3d4e5f6...
```

---

### DELETE /bfsapp/v1/cart/clear

**Remove all items, coupons, fees, and selected shipping from cart.**

---

### POST /bfsapp/v1/cart/transfer
*Requires: JWT Token*

**Merge guest cart into logged-in user's cart (call this after login).**

```json
// Request
{ "cart_key": "guest_550e8400-e29b-41d4-a716-446655440000" }

// Response
{
  "message": "Cart transferred successfully.",
  "cart": { /* merged cart data */ }
}
```

---

## Coupon API

---

### POST /bfsapp/v1/cart/coupon

**Apply a coupon to cart.**

Validates: existence, expiry, usage limits, per-user limits, min/max spend, product/category restrictions, individual use.

```json
// Request
{ "code": "SAVE10" }

// Response 200
{
  "message": "Coupon applied successfully.",
  "coupon": {
    "code": "save10",
    "discount_type": "percent",
    "coupon_amount": 10,
    "discount": "119.80",
    "free_shipping": false,
    "description": "10% off on all orders"
  },
  "cart": { /* updated cart */ }
}
```

---

### DELETE /bfsapp/v1/cart/coupon/{code}

**Remove an applied coupon.**

```bash
curl -X DELETE -H "X-Cart-Key: guest_uuid" \
  https://yoursite.com/wp-json/bfsapp/v1/cart/coupon/SAVE10
```

---

### GET /bfsapp/v1/cart/coupons

**List all coupons applied to current cart.**

---

## Shipping API

---

### GET /bfsapp/v1/cart/shipping

**Get available shipping methods for an address.**

```bash
curl "https://yoursite.com/wp-json/bfsapp/v1/cart/shipping?address[country]=IN&address[state]=GJ&address[postcode]=395001" \
  -H "X-Cart-Key: guest_uuid"
```

```json
// Response
{
  "address": {
    "country": "IN",
    "state": "GJ",
    "postcode": "395001",
    "city": "Surat"
  },
  "rates": [
    {
      "id": "flat_rate:1",
      "method_id": "flat_rate",
      "label": "Standard Delivery",
      "cost": "99.00",
      "cost_with_tax": "99.00",
      "taxes": [],
      "meta_data": {}
    }
  ],
  "free_shipping_via_coupon": false,
  "currently_selected": null
}
```

---

### POST /bfsapp/v1/cart/shipping/select

**Select a shipping method and save to cart.**

```json
// Request
{
  "method_id": "flat_rate:1",
  "address": {
    "country": "IN",
    "state": "GJ",
    "city": "Surat",
    "postcode": "395001",
    "address_1": "123 Ring Road"
  }
}

// Response — updated cart with shipping total
```

---

### GET /bfsapp/v1/cart/shipping/zones
*Requires: Admin JWT*

**List all WooCommerce shipping zones (admin debug tool).**

---

## Fees API

Add custom fees like handling charges, COD fees, rush charges, etc.

By default, fee write endpoints require authentication. Change with filter:
```php
add_filter('bfs_fee_write_permission', '__return_true'); // allow anyone
```

---

### GET /bfsapp/v1/cart/fees

**List fees on current cart.**

---

### POST /bfsapp/v1/cart/fee
*Requires: Auth (configurable)*

**Add a fee to cart.**

```json
// Request
{
  "name": "COD Handling Fee",
  "amount": 50,
  "taxable": false
}

// Response
{
  "message": "Fee added.",
  "fee": {
    "id": "cod-handling-fee-abc123",
    "name": "COD Handling Fee",
    "amount": 50,
    "taxable": false
  },
  "cart": { /* updated cart */ }
}
```

---

### PUT /bfsapp/v1/cart/fee/{id}

**Update an existing fee.**

```json
{ "amount": 75 }
```

---

### DELETE /bfsapp/v1/cart/fee/{id}

**Remove a fee.**

---

## Checkout API

---

### GET /bfsapp/v1/checkout/payment-methods

**Get all available WooCommerce payment gateways.**

```json
// Response
[
  {
    "id": "cod",
    "title": "Cash on Delivery",
    "description": "Pay when your order arrives.",
    "icon": "",
    "supports": ["products"]
  }
]
```

---

### POST /bfsapp/v1/checkout

**Place order. Converts cart → WC Order.**

```json
// Request
{
  "billing": {
    "first_name": "Rahul",
    "last_name": "Shah",
    "email": "rahul@example.com",
    "phone": "9876543210",
    "address_1": "123 Ring Road",
    "city": "Surat",
    "state": "GJ",
    "postcode": "395001",
    "country": "IN"
  },
  "shipping": {
    "first_name": "Rahul",
    "last_name": "Shah",
    "address_1": "123 Ring Road",
    "city": "Surat",
    "state": "GJ",
    "postcode": "395001",
    "country": "IN"
  },
  "payment_method": "cod",
  "order_note": "Please pack carefully.",
  "meta_data": [
    { "key": "source", "value": "mobile_app" }
  ]
}

// Response 200
{
  "order_id": 1001,
  "order_key": "wc_order_abc123xyz",
  "order_number": "#1001",
  "status": "pending",
  "status_label": "Pending payment",
  "total": "1347.00",
  "total_formatted": "₹1,347.00",
  "currency": "INR",
  "payment_method": "cod",
  "pay_url": "https://yoursite.com/checkout/order-pay/1001/?key=wc_order_abc123xyz",
  "thank_you_url": "https://yoursite.com/checkout/order-received/1001/?key=wc_order_abc123xyz",
  "created_at": "2026-04-17T12:30:00+00:00"
}
```

---

### GET /bfsapp/v1/order/{id}
*Requires: JWT Token*

**Get a single order's details.**

---

### GET /bfsapp/v1/orders
*Requires: JWT Token*

**Get current user's order history.**

---

## Batch API

Process multiple operations in **one HTTP request** to reduce network latency.

### POST /bfsapp/v1/batch

```json
// Request — add 2 products + apply coupon in one call
{
  "requests": [
    {
      "method": "POST",
      "path": "/bfsapp/v1/cart/add",
      "body": { "product_id": 101, "quantity": 2 }
    },
    {
      "method": "POST",
      "path": "/bfsapp/v1/cart/coupon",
      "body": { "code": "SAVE10" }
    },
    {
      "method": "GET",
      "path": "/bfsapp/v1/cart"
    }
  ]
}

// Response
{
  "responses": [
    { "index": 0, "status": 200, "body": { /* cart */ } },
    { "index": 1, "status": 200, "body": { "message": "Coupon applied", ... } },
    { "index": 2, "status": 200, "body": { /* final cart */ } }
  ],
  "count": 3,
  "success": 3,
  "failed": 0
}
```

**Limits:** Max 25 requests per batch. Only `/bfsapp/v1/` paths allowed.

---

## Addresses API

---

### GET /bfsapp/v1/addresses
*Requires: JWT Token*

**Get a list of addresses (Billing, Shipping, and custom address book entries) for the user.**

**Header:**
```http
Authorization: Bearer <token>
```

```json
// Response
{
  "success": true,
  "data": {
    "addresses": [
      {
        "id": 1,
        "type": "Billing",
        "name": "Rahul Shah",
        "address_line": "123 Ring Road Near City Mall",
        "city": "Surat",
        "state": "GJ",
        "zip": "395001",
        "country": "IN",
        "phone": "9876543210",
        "email": "rahul@example.com",
        "default": true
      },
      {
        "id": 2,
        "type": "Shipping",
        "name": "Rahul Shah",
        "address_line": "123 Ring Road",
        "city": "Surat",
        "state": "GJ",
        "zip": "395001",
        "country": "IN",
        "phone": "",
        "email": "",
        "default": false
      }
    ]
  }
}
```

---

## Account API

---


### GET /bfsapp/v1/account-details
*Requires: JWT Token*

**Get detailed account profile info.**

**Header:**
```http
Authorization: Bearer <token>
```

```json
// Response
{
  "success": true,
  "data": {
    "id": 42,
    "name": "Rahul Shah",
    "email": "rahul@example.com",
    "phone": "9876543210",
    "avatar": "https://secure.gravatar.com/avatar/..."
  }
}
```

---

### POST /bfsapp/v1/account-details
*Requires: JWT Token*

**Update user name, email, billing phone, and password securely.**

**Header:**
```http
Authorization: Bearer <token>
```

```json
// Request
{
  "name": "Rahul S. Shah",
  "email": "rahul.shah@example.com",
  "phone": "9876543211",
  "current_password": "OldPassword123!",
  "new_password": "NewPassword123!"
}

// Response
{
  "success": true,
  "message": "Account details updated successfully."
}
```

---

## App Content APIs

---

### GET /bfsapp/v1/header

**Get dynamic header config, active menu items, logo, currency metadata, cart, and social configurations.**

```json
// Response
{
  "topbar_text": "FREE shipping on US$39.00+",
  "logo_url": "https://yoursite.com/wp-content/...",
  "site_name": "Styluza",
  "menu_items": [
    {
      "id": 140,
      "title": "Shop",
      "url": "https://yoursite.com/shop/",
      "target": "_self",
      "menu_order": 1,
      "parent": 0
    }
  ],
  "search_status": true,
  "wishlist_status": true,
  "cart_status": true,
  "is_logged_in": false,
  "user_name": "",
  "user_email": "",
  "wishlist_count": 0,
  "cart_count": 0,
  "cart_total": "0.00",
  "currency_symbol": "₹",
  "currency_code": "INR",
  "social_media": {
    "facebook": "https://facebook.com/..."
  }
}
```

---

### GET /bfsapp/v1/footer

**Get footer widgets data, contact details, quick links, and social URLs.**

```json
// Response
{
  "success": true,
  "data": {
    "footer_logo": "https://yoursite.com/...",
    "footer_description": "Custom WooCommerce Styluza site.",
    "quick_links": [],
    "get_to_know_us": [],
    "contact": {
      "address": "123 Main St, City, Country",
      "email": "info@styluza.com",
      "phone": "+1 234 567 890"
    },
    "social_links": {
      "facebook": "https://facebook.com/..."
    }
  }
}
```

---

### GET /bfsapp/v1/homepage

**Get dynamic home page layout blocks: categories, featured, new, best sellers, blog posts, and testimonials.**

```json
// Response
{
  "success": true,
  "data": {
    "hero_section": [
      {
        "id": 1,
        "title": "Summer Collection",
        "subtitle": "Hot Sale",
        "description": "Exquisite products",
        "image": "https://yoursite.com/banner.png",
        "button_text": "SHOP NOW",
        "button_url": "/shop/"
      }
    ],
    "shop_by_category": [],
    "feature_products": [],
    "best_sellers": [],
    "new_products": [],
    "sale_products": [],
    "blog_posts": [],
    "testimonials": []
  }
}
```

---

### GET /bfsapp/v1/instagram-feed

**Get cached Instagram Media items (refreshed every 24 hours).**

```json
// Response
{
  "success": true,
  "data": {
    "instagram_posts": [
      {
        "id": "17894729384729384",
        "caption": "Elegant designs.",
        "media_type": "IMAGE",
        "media_url": "https://scontent.cdninstagram.com/...",
        "thumbnail_url": "",
        "timestamp": "2026-06-23T10:00:00+0000"
      }
    ]
  }
}
```

---

## Reviews API

---

### GET /bfsapp/v1/reviews

**Fetch approved ratings and reviews for a specific product.**

- **Parameters:**
  - `product_id` (required, int)
  - `per_page` (optional, default 10)
  - `page` (optional, default 1)

```json
// Response
{
  "success": true,
  "data": [
    {
      "id": 15,
      "reviewer": "John Doe",
      "reviewer_email": "john@example.com",
      "review": "Excellent quality!",
      "rating": 5,
      "date_created": "2026-06-23T12:00:00",
      "product_id": 20
    }
  ]
}
```

---

### POST /bfsapp/v1/reviews

**Submit a review rating (1-5) for a product.**

```json
// Request
{
  "product_id": 20,
  "reviewer": "John Doe",
  "reviewer_email": "john@example.com",
  "review": "Really liked this product.",
  "rating": 4
}

// Response
{
  "success": true,
  "message": "Review submitted successfully.",
  "review": {
    "id": 16,
    "reviewer": "John Doe",
    "reviewer_email": "john@example.com",
    "review": "Really liked this product.",
    "rating": 4,
    "date_created": "2026-06-23T12:05:00",
    "product_id": 20
  }
}
```

---

## Product API

---

### GET /bfsapp/v1/products

**List products with pagination, sorting, search, and filters.**

- **Parameters:**
  - `page` (optional, default: 1)
  - `per_page` (optional, default: 10)
  - `orderby` (optional, choices: `date`, `price`, `popularity`, `rating`, `title`, default: `date`)
  - `order` (optional, choices: `asc`, `desc`, default: `desc`)
  - `search` (optional, string)
  - `category` (optional, string/slug)
  - `tag` (optional, string/slug)
  - `featured` (optional, boolean)
  - `on_sale` (optional, boolean)
  - `min_price` (optional, float)
  - `max_price` (optional, float)

```json
// Response
{
  "success": true,
  "data": [
    {
      "id": 157,
      "name": "multi rehenga",
      "slug": "multi-rehenga",
      "permalink": "http://localhost:8888/styluza/product/multi-rehenga/",
      "type": "variable",
      "status": "publish",
      "featured": true,
      "description": "...",
      "short_description": "...",
      "sku": "woo-fashion",
      "price": "4000.00",
      "regular_price": "",
      "sale_price": "",
      "on_sale": true,
      "in_stock": true,
      "stock_quantity": null,
      "stock_status": "instock",
      "images": [
        {
          "id": 81,
          "src": "http://localhost:8888/styluza/wp-content/uploads/2026/05/Feature-Products-lehanga-scaled-1.webp",
          "alt": "multi rehenga"
        }
      ],
      "categories": [
        {
          "id": 34,
          "name": "Sarees",
          "slug": "sarees"
        }
      ],
      "tags": [],
      "attributes": [],
      "variations": []
    }
  ],
  "pagination": {
    "page": 1,
    "per_page": 10,
    "total": 6,
    "total_pages": 1
  }
}
```

---

### GET /bfsapp/v1/products/{id}

**Fetch a product by its ID.**

---

### GET /bfsapp/v1/products/{slug}

**Fetch a product by its slug.**

---

## Guest Cart Flow

```
1. App start (no login)
   POST /bfsapp/v1/auth/guest
   → Save cart_key in AsyncStorage / localStorage

2. Browse & add to cart
   POST /bfsapp/v1/cart/add
   Header: X-Cart-Key: guest_uuid
   
3. User registers/logs in
   POST /bfsapp/v1/auth/login  → Get JWT token
   
4. Transfer guest cart to user
   POST /bfsapp/v1/cart/transfer
   Header: Authorization: Bearer <token>
   Body: { "cart_key": "guest_uuid" }
   
5. All subsequent requests use JWT only
   Header: Authorization: Bearer <token>
```

---

## Frontend Integration Examples

### React/Next.js Service

```javascript
const API_BASE = process.env.NEXT_PUBLIC_WP_URL + '/wp-json/bfsapp/v1';

function getHeaders() {
  const token   = localStorage.getItem('bfs_token');
  const cartKey = localStorage.getItem('bfs_cart_key');
  const headers = { 'Content-Type': 'application/json' };
  
  if (token)   headers['Authorization'] = `Bearer ${token}`;
  if (cartKey && !token) headers['X-Cart-Key'] = cartKey;
  
  return headers;
}

export const CartAPI = {
  async initGuest() {
    if (localStorage.getItem('bfs_cart_key') || localStorage.getItem('bfs_token')) return;
    const res  = await fetch(`${API_BASE}/auth/guest`, { method: 'POST' });
    const data = await res.json();
    localStorage.setItem('bfs_cart_key', data.cart_key);
  },

  async login(username, password) {
    const res  = await fetch(`${API_BASE}/auth/login`, {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ username, password }),
    });
    const data = await res.json();
    if (!res.ok) throw new Error(data.message);
    
    localStorage.setItem('bfs_token', data.token);
    
    const guestKey = localStorage.getItem('bfs_cart_key');
    if (guestKey) {
      await this.transfer(guestKey);
      localStorage.removeItem('bfs_cart_key');
    }
    return data;
  },

  async transfer(cartKey) {
    return fetch(`${API_BASE}/cart/transfer`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ cart_key: cartKey }),
    }).then(r => r.json());
  },

  async addToCart(productId, quantity = 1, variationId = 0, variation = {}) {
    const res = await fetch(`${API_BASE}/cart/add`, {
      method: 'POST',
      headers: getHeaders(),
      body: JSON.stringify({ product_id: productId, quantity, variation_id: variationId, variation }),
    });
    return res.json();
  },
};
```

---

## WordPress Filters & Hooks

### Filters

```php
// JWT token lifetime (default: 30 days)
add_filter('bfs_token_ttl', fn() => 7 * DAY_IN_SECONDS);

// Cart session lifetime (default: 30 days)
add_filter('bfs_cart_ttl', fn() => 14 * DAY_IN_SECONDS);

// Rate limit: max requests (default: 100)
add_filter('bfs_rate_limit', fn() => 200);

// Rate limit: window in seconds (default: 60)
add_filter('bfs_rate_window', fn() => 120);

// Disable rate limiting completely
add_filter('bfs_rate_limit_enabled', '__return_false');

// Whitelist user IDs from rate limiting
add_filter('bfs_rate_limit_whitelist', fn() => [1, 2, 3]);

// Allow everyone to add fees (default: auth required)
add_filter('bfs_fee_write_permission', '__return_true');

// Initial order status on checkout (default: pending)
add_filter('bfs_initial_order_status', fn() => 'processing');

// CORS allowed origins
add_filter('bfs_allowed_origins', fn() => [
  'https://myapp.com',
  'http://localhost:3000',
]);
```

### Actions

```php
// After a user logs in via JWT
add_action('bfs_user_logged_in', function(int $user_id) {
    // e.g., log login event
});

// After an order is created via checkout
add_action('bfs_order_created', function(\WC_Order $order, array $cart) {
    // e.g., send custom notification
}, 10, 2);
```

---

## Rate Limiting

All write endpoints are rate-limited by default:
- **100 requests / 60 seconds** per IP (guests) or user ID (logged-in)
- Exceeding the limit returns HTTP 429 with `Retry-After` header.

---

## Troubleshooting

### JWT auth not working
Some Apache servers strip the Authorization header. Add to `.htaccess`:
```apache
RewriteEngine On
RewriteRule .* - [E=HTTP_AUTHORIZATION:%{HTTP:Authorization}]
```
Or add to `wp-config.php`:
```php
$_SERVER['HTTP_AUTHORIZATION'] = $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
```

### CORS errors in browser
Add your frontend domain to allowed origins:
```php
add_filter('bfs_allowed_origins', fn() => ['https://yourfrontend.com', 'http://localhost:3000']);
```

---

## API Reference Summary

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | `/bfsapp/v1/auth/login` | — | Login, get JWT |
| POST | `/bfsapp/v1/auth/register` | — | Register customer |
| GET | `/bfsapp/v1/auth/me` | JWT | Current user profile |
| POST | `/bfsapp/v1/auth/refresh` | JWT | Refresh token |
| POST | `/bfsapp/v1/auth/guest` | — | Get guest cart key |
| GET | `/bfsapp/v1/cart` | JWT/Key | Get cart |
| POST | `/bfsapp/v1/cart/add` | JWT/Key | Add item |
| PUT | `/bfsapp/v1/cart/item/{key}` | JWT/Key | Update quantity |
| DELETE | `/bfsapp/v1/cart/item/{key}` | JWT/Key | Remove item |
| DELETE | `/bfsapp/v1/cart/clear` | JWT/Key | Clear cart |
| POST | `/bfsapp/v1/cart/transfer` | JWT | Merge guest→user |
| POST | `/bfsapp/v1/cart/coupon` | JWT/Key | Apply coupon |
| DELETE | `/bfsapp/v1/cart/coupon/{code}` | JWT/Key | Remove coupon |
| GET | `/bfsapp/v1/cart/coupons` | JWT/Key | List coupons |
| GET | `/bfsapp/v1/cart/shipping` | JWT/Key | Get rates |
| POST | `/bfsapp/v1/cart/shipping/select` | JWT/Key | Select method |
| GET | `/bfsapp/v1/cart/shipping/zones` | Admin JWT | List zones |
| GET | `/bfsapp/v1/cart/fees` | JWT/Key | List fees |
| POST | `/bfsapp/v1/cart/fee` | JWT | Add fee |
| PUT | `/bfsapp/v1/cart/fee/{id}` | JWT | Update fee |
| DELETE | `/bfsapp/v1/cart/fee/{id}` | JWT | Remove fee |
| GET | `/bfsapp/v1/checkout/payment-methods` | — | Payment gateways |
| POST | `/bfsapp/v1/checkout` | JWT/Key | Place order |
| GET | `/bfsapp/v1/order/{id}` | JWT | Get order |
| GET | `/bfsapp/v1/orders` | JWT | Order history |
| POST | `/bfsapp/v1/batch` | JWT/Key | Batch requests |
| GET | `/bfsapp/v1/addresses` | JWT | User Address list |
| GET | `/bfsapp/v1/account-details` | JWT | Detailed account details |
| POST | `/bfsapp/v1/account-details` | JWT | Update account info |
| GET | `/bfsapp/v1/header` | — | Get header config & links |
| GET | `/bfsapp/v1/footer` | — | Get footer widgets & links |
| GET | `/bfsapp/v1/homepage` | — | Get dynamic home page data |
| GET | `/bfsapp/v1/instagram-feed` | — | Get Instagram posts |
| GET | `/bfsapp/v1/reviews` | — | Get product reviews |
| POST | `/bfsapp/v1/reviews` | — | Submit product review |
| GET | `/bfsapp/v1/products` | — | List products with pagination/filters |
| GET | `/bfsapp/v1/products/{id}` | — | Get product by ID |
| GET | `/bfsapp/v1/products/{slug}` | — | Get product by slug |
