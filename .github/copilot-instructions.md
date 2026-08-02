# BankPro Banking Script – Copilot Instructions

## Project Overview
Raw PHP + HTML online banking script (no framework). Three distinct zones share the same MySQL database via PDO:
- **Public/front** (`/p/`, `index.php`, `login.php`) – marketing pages & auth
- **User portal** (`/user/`) – authenticated customer dashboard & transactions
- **Admin panel** (`/admin/`) – bank management (users, transactions, settings)

## Setup
1. Import `SQL File/database.sql` into MySQL.
2. Edit `include/config.php`: set DB credentials and `WEB_URL` (no trailing slash).
3. Configure SMTP via environment variables (`SMTP_HOST`, `SMTP_USERNAME`, `SMTP_PASSWORD`, `SMTP_PORT`, `SMTP_SECURE`) or edit the fallback values in `include/config.php`. SMTP values of `"#"` disable outbound mail silently.
4. No build step – deploy PHP files directly to a web server.

## Architecture & Data Flow

### Database Access
All DB access uses `PDO` via `dbConnect()` in `include/config.php`. Pages call `dbConnect()` at the top; the `$conn` variable is passed or used globally. **No ORM, no query builder.**

### Include/Dependency Chain
- **User pages** (`user/layouts/header.php`): loads `../session.php` → `../include/loginFunction.php` → `../include/userClass.php`
- **Admin pages** (`admin/layout/header.php`): loads `./include/adminloginFunction.php` → `./include/adminregFunction.php` → `./include/session.php` → `./include/adminClass.php`
- Every page starts with `include_once("layouts/header.php")` or `include_once("./layout/header.php")` which bootstraps everything.

### Session Auth Guards
- **User**: `if(!$_SESSION['acct_no']) { header("location:../login.php"); die; }` — checked in `user/layouts/header.php` and repeated per-page.
- **Admin**: `if (!$_SESSION['admin']) { header("location:./login.php"); die; }` — checked in `admin/layout/header.php`.
- Session hardening (httponly, SameSite, strict mode, 1100 s timeout) lives in `session.php`.

### Key Classes
| Class | File | Role |
|---|---|---|
| `USER` | `include/userClass.php` | Transaction inserts, email template methods |
| `message` | `include/smtp.php` | PHPMailer wrapper; `send_mail($email, $html, $subject)` |
| `emailMessage` | `include/userClass.php` | HTML email body generators (withdrawal, wire, etc.) |
| `pageTitle` | `include/userClass.php` | Page title constants |
| Admin helpers | `admin/include/adminFunction.php` | `getCardStatus()`, `getCardType()`, `toast_alert()` |

### Alert/Toast Pattern
- **User zone**: `toast_alert($type, $msg)` outputs a SweetAlert2 `swal()` call inline; `notify_alert($msg, $colorType, $duration)` outputs a Snackbar — both defined in `include/userFunction.php`.
- **Admin zone**: `toast_alert()` in `admin/include/adminFunction.php` queues toasts via `window.__toasts` array (avoids duplicate-script issues).

## Project-Specific Conventions
- **Input sanitization**: always use `inputValidation($value)` from `include/config.php` (`trim(htmlspecialchars(htmlentities($value)))`).
- **Currency helper**: `currency($row)` reads `$row['acct_currency']` (`'USD'` → `"$"`, `'EUR'` → `"&euro;"`).
- **Card type detection** in `admin/include/adminFunction.php` `getCardType()`: uses first 2 digits of card number (52=Master, 40=Visa, 37=Amex, etc.).
- **Settings table**: bank name, phone, URL stored in the `settings` table row `id=1`; loaded in every header via `SELECT * FROM settings WHERE id='1'`.
- **`ob_start()`** is called at the top of every layout header to allow `header()` redirects after output has started.

## Key Database Tables
`users`, `transactions`, `deposit`, `wire_transfer`, `domestic_transfer`, `withdrawal`, `loan`, `cards`, `crypto`, `settings`, `admin`

## External Dependencies
- **PHPMailer** (`include/vendor/phpmailer/`) – autoloaded via `include/vendor/autoload.php`
- **Twilio** (`include/twilioController.php`) – SMS OTP (optional)
- Frontend: Bootstrap, AdminLTE (admin), jQuery, SweetAlert2, Snackbar.js, Owl Carousel
