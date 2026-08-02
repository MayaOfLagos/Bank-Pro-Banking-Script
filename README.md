# BankPro Banking Script

BankPro is a hybrid PHP and Vue application:

- Public marketing pages and the admin panel remain server-rendered PHP.
- Customer authentication and customer portal pages are rendered by Vue 3.
- PHP JSON endpoints remain the source of truth for sessions, authorization, and banking data.

## Route ownership

| Area | Owner | Entry point |
| --- | --- | --- |
| Public website | PHP | `index.php`, `p/`, and existing public pages |
| Customer auth and portal | Vue + PHP APIs | `user-app.php`, `frontend/user-portal/`, `api/auth/`, `api/user/` |
| Administration | PHP | `admin/` |

`user-app.php` is the only server-rendered shell for Vue routes. Apache uses `.htaccess`, while PHP's local server uses `router.php`, to send the same explicit auth and customer routes to that shell. Retired URLs such as `/dashboard.php`, `/p/forgot-password.php`, and `/user/card.php` are redirected to their canonical Vue routes; no customer-page wrapper files are required. The admin panel does not pass through the Vue application.

The `user/` directory is retained only as source reference while remaining customer behavior is migrated. It is not a live PHP frontend: requests beneath `/user` are redirected to Vue, and new code must not depend on its removed theme assets.

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

## Frontend builds

The production build is written to `assets/user-app/`. That directory is deployed with the PHP application and intentionally committed because shared hosting may not run Node.js.

`npm run build` also verifies route ownership, Vue-to-PHP API targets, and the generated HTML, JavaScript, and CSS. The route check prevents retired root customer wrappers or `.php` aliases from being reintroduced. Do not restore or deploy the old `frontend/user-portal/dist/` directory.

## Security boundary

- Browser authentication uses the existing PHP session cookie.
- Vue route guards improve navigation, but every PHP API still performs server-side authorization.
- State-changing Vue API requests require a session-bound CSRF token.
- Card APIs return masked card data and never return the card security code.
- Admin authentication and admin pages remain independent of the Vue router.

## Repository safety

Machine-local configuration, dependency directories, database exports, backup archives, uploaded IDs, profile photos, and deposit receipts are intentionally excluded from Git.

Never commit production credentials, customer information, authentication tokens, or unsanitized database dumps.

## Important

This project is under active modernization and is not yet production-ready. The next priorities are transaction-level consistency, stricter upload inspection, login throttling, secure PIN migration with admin compatibility, migration of remaining customer behavior using `user/` only as reference, and automated integration tests against a sanitized database.
