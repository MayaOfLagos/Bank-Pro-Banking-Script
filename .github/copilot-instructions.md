# BankPro Banking Script – Project Instructions

## Architecture boundary

BankPro intentionally uses a hybrid architecture with three zones sharing one MySQL database through PDO:

- Public marketing pages remain PHP (`index.php`, `p/`, and related public files).
- Customer authentication and customer portal pages are Vue 3 routes.
- The administration panel remains PHP (`admin/`) and must not depend on Vue.

Do not add new customer-facing pages under `user/`. New customer UI belongs in `frontend/user-portal/src/`, and its server data belongs in `api/auth/` or `api/user/`.

## Customer route flow

- `user-app.php` is the single PHP shell for every Vue auth and portal route.
- `.htaccess` owns the Apache route handoff.
- `router.php` mirrors the same route list for PHP's built-in development server.
- Legacy root customer URLs are redirected to canonical clean routes by `.htaccess` and `router.php`; do not recreate customer wrapper files such as `login.php` or `dashboard.php`.
- `frontend/user-portal/src/router/index.js` owns client-side navigation and route guards.
- Vue guards are a user-experience layer only; PHP API endpoints must enforce authorization independently.

## Frontend workflow

Run frontend commands from `frontend/user-portal/`:

```powershell
npm ci
npm run dev
npm run build
```

The production build goes to `assets/user-app/`. `npm run build` verifies canonical routing, API targets, and deployable output. `frontend/user-portal/dist/` is obsolete and ignored.

## API conventions

- Auth endpoints use `api/auth/_bootstrap.php` and `auth_json()`.
- Authenticated customer endpoints use `api/user/_bootstrap.php` and `api_json()`.
- Unsafe methods require the session-bound `X-CSRF-Token` header.
- Use prepared PDO statements and enforce ownership in every query.
- Return JSON with an `ok` boolean, an optional human-readable `message`, and an optional `data` value.
- Never return passwords, PINs, reset tokens, full card numbers, or card security codes.
- Validate and authorize on the server even when Vue already validates the form.

## Existing PHP areas

The admin panel continues to use its existing PHP layouts, helpers, and session guard. Changes to shared database semantics must be checked for admin compatibility before migration.

Legacy files under `user/` remain as source-only migration reference. Requests beneath `/user` redirect to Vue, their old theme assets are intentionally absent, and no runtime code may depend on them. Do not extend them with new customer features.

## Configuration and dependencies

- Local configuration is `include/config.php`; the committed template is `include/config.example.php`.
- Database access uses `dbConnect()` and PDO. There is no ORM.
- PHPMailer is bundled under `include/vendor/phpmailer/`.
- SMTP configuration should come from environment variables.

## Safety priorities

This is financial software under active modernization. Preserve server-side authorization, use database transactions for balance-changing operations, validate uploads by content, avoid logging secrets, and never commit customer uploads or database exports.
