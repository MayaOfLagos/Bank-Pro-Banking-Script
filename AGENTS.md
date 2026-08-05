# Agent coordination board

This file is the shared notice board for all parallel agents working on this
repo. Read it before starting any work. Update your section when you begin and
again when you finish. Merge coordinators check this before touching `main` or
`production`.

---

## Repo ground rules (apply to every agent)

- PHP 7.4 compatibility — no `str_starts_with`, `match` expressions, `readonly`
- PDO prepared statements only — no string-concatenated SQL
- `htmlspecialchars()` on every admin-displayed user input
- No seed/test data in schema migrations; access toggles default closed
- `include/config.php` is gitignored — never commit credentials
- `production` branch is a live deploy — ff-only from `main`, never push without explicit owner permission
- The `user/` directory is untracked reference material — do not delete or overwrite it
- No sweeping deletes without checking all references first
- Do not convert the admin panel to Vue/JS framework

---

## Current `main` tip

`5e8c6c4` — Wire the operator notifications into their trigger points  
Committed: 2026-08-05

### Recent commits

| Commit | Description |
|--------|-------------|
| `5e8c6c4` | Wire the operator notifications into their trigger points |
| `d90f5ee` | Extract the operator alerts into include/admin_alerts.php |
| `e27172d` | Rebuild admin email templates on a shared Laravel-style layout (merge of admin agent branch) |
| `e19dcd0` | Rebuild user email templates + rate-limited login alert |
| `45c792f` | Rebuild admin email templates (admin agent commit — now merged) |
| `eeb3cf4` | Upgrade PHPMailer 6.0.5 → 6.12.0; drop Twilio and object.php |
| `126cfcf` | Add agent coordination board (AGENTS.md) |
| `1a08f8e` | Fix white-flash/stuck boot + type-driven transaction avatars |

### What landed in `e19dcd0` + `e27172d`

| File | What changed |
|------|-------------|
| `include/userClass.php` | Complete rewrite of `emailMessage` — shared `_layout()` engine, 20 methods rebuilt, new `LoginAlert()` method |
| `include/mail_template.php` | New fluent `MailTemplate` builder (Laravel markdown theme) shared by admin emails |
| `admin/include/adminClass.php` | 2 700 → ~1 200 lines; all templates replaced with MailTemplate calls |
| `admin/include/adminFunction.php` | Updated call sites; no behavioural change |
| `admin/include/adminloginFunction.php` | Session hardening before `session_start()`; 5-attempt / 15-min rate limit |
| `api/auth/pin-verify.php` | Rate-limited login alert fires here (post-PIN, 10-min window) |
| `api/auth/login.php` | Removed old password-stage login email |
| `api/user/*.php` (12 files) | `new emailMessage()` → `new emailMessage($settings)` |
| `SQL File/migrations/2026_08_05_01_login_email_rate_limit.sql` | Adds `last_login_email_at` to users table |
| `SQL File/migrations/2026_08_05_01_admin_login_rate_limit.sql` | Adds rate-limit + audit columns to admin_users table |

---

## Active agents

### Agent: main-session (customer portal work — COMPLETE)

- **Branch:** `main` (changes already merged)
- **Worktree:** primary checkout
- **Files owned:**
  - `frontend/user-portal/src/**`
  - `assets/user-app/**`
  - `user-app.php` (boot placeholder only — auth gate untouched)
  - `api/user/dashboard.php`
- **Status:** DONE — commit `1a08f8e` on `main`. No further changes planned for
  this session. Ready for the admin agent to branch from `main`.

---

### Agent: admin-function-email-cleanup (COMPLETE — merged)

- **Branch:** `worktree-admin-notifications-cleanup` (now merged into `main`)
- **Merged as:** commits `45c792f` + `e27172d` on `main`
- **Status:** DONE

---

## Merge queue

| Branch | Owner | Status | Notes |
|--------|-------|--------|-------|
| `main` | — | ✅ current tip `5e8c6c4` | In sync with `origin/main` |
| `production` | owner | ✅ `5e8c6c4` | In sync; ff-only from `main` |

**Pending owner action:** apply migrations via the web migration console —  
`2026_08_05_01_login_email_rate_limit.sql` (users table),  
`2026_08_05_01_admin_login_rate_limit.sql` (admin_users table) and  
`2026_08_04_01_settings_favicon.sql` (settings.favicon column).

Until the first is applied the login-alert email stays silently disabled —
`api/auth/pin-verify.php` now catches the missing column so it can no longer
500 the PIN step. Until the last is applied the favicon uploader reports the
missing column by name instead of failing blank.

---

## Testing

Suites live in `tests/` and are shell-only (the directory carries a deny-all
`.htaccess` because the repo root is the cPanel document root):

| Command | Covers |
|---------|--------|
| `php tests/email_templates.php` | renders all 24 customer templates, asserts chrome/footer/escaping (196 assertions) |
| `php tests/transfer_otp.php` | transfer OTP issue/verify lifecycle against the real schema (13 assertions) |

`tests/transfer_otp.php` writes to `temp_trans` and cleans up after itself —
do not point it at production.
