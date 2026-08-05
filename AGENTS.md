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

`1a08f8e` — Fix white-flash/stuck boot + type-driven transaction avatars  
Committed: 2026-08-05

### What landed in that commit (customer portal only)

| Area | What changed |
|------|-------------|
| `api/client.js` | 30 s global timeout; `UPLOAD_TIMEOUT` (120 s) for file posts |
| `main.js` | `siteStore.load()` kicked but not awaited; `router.isReady()` stays blocking |
| `stores/site.js` | New `settled` ref (set in `finally`) so AuthShell can gate on "tried" not "succeeded" |
| `AuthShell.vue` | Holds brand block until `settled`; reserves 4.5 rem to prevent layout shift |
| `LoginView.vue` | Footer held until `settled` to avoid flashing a registration link on banks with it disabled |
| `index.html` + `user-app.php` | CSS-only boot placeholder: themed background + delayed spinner; `<noscript>` fallback |
| `TransactionAvatar.vue` | New shared component keyed off ledger `source` + direction — no merchant-name matching |
| `TransactionItem.vue` | Removed hardcoded avatar logic; now uses `TransactionAvatar` |
| `TransactionDetailView.vue` | Same removal; `isCounterparty` computed prevents "To: Wire Transfer" bug |
| `api/user/dashboard.php` | Exposes `source` + `record_id` for the recent-transaction list |
| `assets/user-app/` | Rebuilt bundle (entry `app-a8DEObhV.js`) |

**Admin files touched in `1a08f8e`: none.** All admin paths are clean for the
new agent to work on.

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

### Agent: admin-function-email-cleanup (IN PROGRESS)

- **Branch:** to be created from `main` tip (`1a08f8e`)
- **Worktree:** `.claude/worktrees/admin-notifications-cleanup` (currently locked)
- **Files in scope:**
  - `admin/include/adminFunction.php`
  - `admin/include/adminloginFunction.php`
  - `admin/include/adminClass.php` (email template methods)
  - Any admin page that should trigger a notification email but currently does not
- **Tasks:**
  1. Audit `adminFunction.php` and `adminloginFunction.php` — remove dead code,
     wire any useful-but-unconnected logic, improve what's kept
  2. Inventory all `public function` email methods in `adminClass.php`
  3. Replace email template bodies with clean, Laravel-markdown-style HTML
     (single-column card, header/body/footer, responsive)
  4. Identify missing notification hooks across all admin pages and add them
  5. Pass a PHP syntax check (`php -l`) and a smoke-read of every changed file
     before marking done
- **Do not touch:**
  - Anything under `frontend/` or `assets/user-app/`
  - `user-app.php` auth gate
  - `api/user/**` (customer API layer)
  - `include/config.php`
- **Status:** PENDING — update this line when work begins and when done

---

## Merge queue

| Branch | Owner | Status | Notes |
|--------|-------|--------|-------|
| `main` | main-session | ✅ current | Boot fix + avatars landed |
| `worktree-admin-notifications-cleanup` | admin-function-email-cleanup | ⏳ pending | Branch from `1a08f8e` |

When the admin agent marks its branch READY: verify no conflicts with `main`,
run `php -l` across changed files, then ff-merge to `main` before promoting
`production`.
