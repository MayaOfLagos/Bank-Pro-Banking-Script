# BankPro Banking Script

BankPro is a hybrid PHP and Vue application:

- Public marketing pages and the admin panel are rendered with PHP.
- Customer authentication and dashboard pages are implemented in Vue 3.
- PHP JSON endpoints provide session-based authentication and customer data.

## Local setup

1. Copy `include/config.example.php` to `include/config.php`.
2. Configure the database and application values through environment variables or the local config file.
3. Import a sanitized schema or apply the project migrations when they are available.
4. Install and build the customer frontend:

   ```powershell
   cd frontend/user-portal
   npm ci
   npm run build
   ```

5. From the project root, start PHP's development server:

   ```powershell
   php -S localhost:3000 router.php
   ```

The Vite development server runs on port 5173 and proxies API requests to `http://localhost:3000` by default.

## Repository safety

Machine-local configuration, dependency directories, database exports, backup archives, uploaded IDs, profile photos, and deposit receipts are intentionally excluded from Git.

Never commit production credentials, customer information, authentication tokens, or unsanitized database dumps.

## Important

This project is under active modernization. Review authentication, authorization, transaction consistency, CSRF protection, upload validation, and deployment configuration before using it in a production environment.
