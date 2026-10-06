# NovaMart — E-Commerce Marketplace System

A complete, production-ready PHP/MySQL e-commerce marketplace with customer storefront, seller dashboard, and admin panel.

---

## Features

### Customers
- Browse, search & filter products
- View product details with image gallery
- Add to cart, wishlist
- Apply discount coupons
- Checkout with PayPal Sandbox or Cash on Delivery
- Order history with detailed timeline
- Cancel eligible orders
- Submit & edit product reviews

### Sellers
- Dashboard with sales stats
- Add / edit / archive products
- Upload product images
- Manage inventory (stock)
- View relevant orders

### Administrators
- Full dashboard with revenue & order statistics
- Manage all users (activate / suspend)
- Manage sellers (view stats, suspend)
- Manage categories (add, edit, delete)
- Manage all products (edit, toggle status)
- Manage coupons (create, edit, delete)
- Manage orders (update status, view details)
- Manage reviews (approve, hide, delete)

### Security
- PDO prepared statements (SQL injection prevention)
- CSRF token protection on all forms
- XSS output escaping with `htmlspecialchars()`
- Password hashing with `password_hash()` / `password_verify()`
- Role-based access control (admin / seller / customer)
- Secure file uploads with MIME type & extension validation
- Server-side price & stock validation (never trust client values)
- Database transactions for order creation
- Row-level stock locking to prevent overselling

---

## Technology Stack

| Layer      | Technology                          |
|------------|-------------------------------------|
| Backend    | PHP 8.3+ with PDO                   |
| Database   | MySQL / MariaDB                     |
| Frontend   | HTML5, CSS3, Vanilla JavaScript     |
| Framework  | Bootstrap 5.3 (CDN)                 |
| Icons      | Font Awesome 6.5 (CDN)              |
| Fonts      | Google Fonts (Inter, Poppins)       |
| Payments   | PayPal Sandbox API v2               |

---

## Requirements

- PHP 8.1+ (with `pdo_mysql`, `curl`, `mbstring` extensions)
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` (or nginx with equivalent)
- A web browser

---

## Installation — XAMPP (Local Development)

### Step 1: Install XAMPP
Download and install [XAMPP](https://www.apachefriends.org/) for your OS.

### Step 2: Start Services
Open the XAMPP Control Panel and start **Apache** and **MySQL**.

### Step 3: Copy Project
Copy the entire project folder to:
```
C:\xampp\htdocs\E-Commerce\
```

### Step 4: Create Database
1. Open http://localhost/phpmyadmin
2. Click **New** → Database name: `ecommerce_marketplace`
3. Select **Collation**: `utf8mb4_unicode_ci`
4. Click **Create**
5. Click the database name → **Import** tab
6. Choose file: `database/ecommerce_marketplace.sql`
7. Click **Go**

### Step 5: Configure Database
Edit `config/db.php` if your MySQL credentials differ from defaults:
```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'ecommerce_marketplace');
define('DB_USER', 'root');
define('DB_PASS', '');   // XAMPP default is empty
```

### Step 6: Open the Site
Visit: **http://localhost/E-Commerce/**

---

## cPanel Deployment

1. Upload all project files to your public HTML directory via File Manager or FTP.
2. Create a MySQL database in cPanel.
3. Import `database/ecommerce_marketplace.sql` via phpMyAdmin.
4. Edit `config/db.php` with your cPanel database credentials.
5. Ensure `.htaccess` is present in the root directory.
6. Visit your domain to complete setup.

---

## Default Login Credentials

> ⚠️ **Change these passwords immediately after installation!**

| Role    | Email                      | Password     |
|---------|----------------------------|--------------|
| Admin   | `admin@novamart.com` | `admin123` |
| Customer | `alice@example.com`  | `alice123` |
| Seller   | `ali@novamart.com`   | `ali123` |

---

## PayPal Sandbox Setup

1. Go to https://developer.paypal.com/
2. Log in and create a **Sandbox** account.
3. Go to **Apps & Credentials** → create a **Rest API app**.
4. Copy the **Client ID** and **Client Secret**.
5. Edit `config/paypal.php`:
   ```php
   define('PAYPAL_CLIENT_ID',  'your-sandbox-client-id');
   define('PAYPAL_CLIENT_SECRET', 'your-sandbox-client-secret');
   define('PAYPAL_MODE', 'sandbox');   // or 'live' for production
   ```
6. Create a Sandbox **buyer** account at https://sandbox.paypal.com to test payments.

---

## Email Setup (Optional)

Edit `config/mail.php`:
```php
define('SMTP_ENABLED', true);
define('SMTP_HOST',      'smtp.gmail.com');
define('SMTP_PORT',      587);
define('SMTP_USERNAME',  'your-email@gmail.com');
define('SMTP_PASSWORD',  'your-app-password');
define('SMTP_ENCRYPTION','tls');
```
The application works fine without email — password reset tokens are logged to file instead.

---

## Project Structure

```
E-Commerce/
├── index.php                      # Homepage
├── .htaccess                      # Apache rewrite & security rules
├── README.md
├── config/
│   ├── db.php                     # Database connection
│   ├── app.php                    # App constants & paths
│   ├── paypal.php                 # PayPal credentials
│   └── mail.php                   # SMTP settings
├── includes/
│   ├── functions.php              # Shared helpers (e, redirect, csrf, upload)
│   ├── auth.php                   # Authentication functions
│   ├── csrf.php                   # CSRF token generation
│   ├── header.php                 # Customer page HTML header
│   ├── navbar.php                 # Customer navigation
│   ├── admin_navbar.php           # Admin navigation
│   ├── seller_navbar.php          # Seller navigation
│   ├── footer.php                 # Shared footer
│   └── mail_service.php           # Email notification class
├── public/
│   ├── shop.php                   # Product listing
│   ├── product.php                # Product detail
│   ├── cart.php                   # Shopping cart
│   ├── checkout.php               # Checkout
│   ├── payment.php                # PayPal redirect
│   ├── payment_success.php        # Payment confirmation
│   ├── payment_cancel.php         # Payment cancelled
│   ├── orders.php                 # Order history
│   ├── order_detail.php           # Order details
│   ├── wishlist.php               # Wishlist
│   ├── profile.php                # Customer profile
│   ├── login.php                  # Login
│   ├── register.php               # Registration
│   ├── forgot_password.php        # Password reset request
│   ├── reset_password.php         # Password reset form
│   └── logout.php                 # Logout
├── seller/
│   ├── dashboard.php              # Seller dashboard
│   ├── products.php               # Manage products
│   ├── product_add.php            # Add product
│   ├── product_edit.php           # Edit product
│   ├── orders.php                 # View orders
│   ├── profile.php                # Seller profile
│   └── logout.php                 # Seller logout
├── admin/
│   ├── index.php                  # Redirects to dashboard
│   ├── dashboard.php              # Admin dashboard
│   ├── users.php                  # User management
│   ├── user_view.php              # User details
│   ├── sellers.php                # Seller management
│   ├── categories.php             # Category management
│   ├── products.php               # Product listing
│   ├── product_edit.php           # Add/Edit product
│   ├── orders.php                 # Order listing
│   ├── order_view.php             # Order details
│   ├── coupons.php                # Coupon management
│   ├── reviews.php                # Review management
│   └── logout.php                 # Admin logout
├── assets/
│   ├── css/
│   │   ├── style.css              # Customer storefront styles
│   │   ├── admin.css              # Admin panel styles
│   │   └── seller.css             # Seller panel styles
│   ├── js/
│   │   ├── main.js                # Shared JS (toasts, stars)
│   │   ├── cart.js                # Cart AJAX actions
│   │   ├── checkout.js            # Coupon validation AJAX
│   │   └── admin.js               # Admin sidebar & helpers
│   └── images/
├── uploads/
│   ├── products/                  # Product images
│   └── profiles/                  # Profile pictures
├── logs/                          # Application logs
└── database/
    └── ecommerce_marketplace.sql  # Schema + sample data
```

---

## Testing Checklist

### Authentication
- [ ] Register new customer account
- [ ] Login with valid credentials
- [ ] Login with invalid credentials (shows error)
- [ ] Duplicate email registration rejected
- [ ] Password reset email sent
- [ ] Reset password link works
- [ ] Logout clears session

### Customer
- [ ] Browse products on homepage
- [ ] Search products by name/SKU
- [ ] Filter by category & price range
- [ ] Sort by newest/price/rating
- [ ] View product details
- [ ] Add to cart
- [ ] Update cart quantity
- [ ] Remove from cart
- [ ] Add to wishlist
- [ ] Move wishlist item to cart
- [ ] Apply coupon at checkout
- [ ] Complete COD checkout → order created
- [ ] Complete PayPal checkout → order paid
- [ ] View order history
- [ ] View order details with timeline
- [ ] Cancel pending order → stock restored
- [ ] Submit product review

### Seller
- [ ] Login as seller
- [ ] View seller dashboard stats
- [ ] Add product with image
- [ ] Edit product details
- [ ] Update stock quantity
- [ ] Archive product
- [ ] View orders for own products only
- [ ] Update order status

### Admin
- [ ] Login as admin
- [ ] View dashboard statistics
- [ ] Activate/suspend users
- [ ] View user details
- [ ] Add/edit/delete categories
- [ ] Manage all products
- [ ] Create/edit/delete coupons
- [ ] View all orders
- [ ] Update order status
- [ ] Approve/hide/delete reviews

### Security
- [ ] Access admin page as customer → blocked
- [ ] Access seller page as customer → blocked
- [ ] Seller cannot view another seller's products
- [ ] SQL injection attempted → blocked (prepared statements)
- [ ] XSS attempted in product name → escaped
- [ ] CSRF token missing on POST → rejected
- [ ] Invalid file upload (PHP file) → rejected
- [ ] Price manipulated via POST → server validates from DB

---

## Troubleshooting

### "Database connection failed"
- Ensure MySQL is running in XAMPP.
- Verify database name, user, and password in `config/db.php`.
- Ensure the database was imported correctly.

### "Class MailService not found"
- Email is optional. The app works without it. Set `SMTP_ENABLED` to `false` in `config/mail.php`.

### PayPal payment fails
- Verify sandbox credentials in `config/paypal.php`.
- Ensure your sandbox buyer account has a balance or linked card.
- Check `logs/` directory for error details.

### Images not uploading
- Ensure `uploads/products/` and `uploads/profiles/` directories exist and are writable.
- Check PHP `upload_max_filesize` and `post_max_filesize` in `php.ini`.

### "Invalid security token"
- Ensure cookies are enabled in your browser.
- Clear browser cache and try again.

---

## License

This project is created for educational and developmental purposes.

---

## Support

For issues or questions, please refer to the source code comments or contact the development team.
