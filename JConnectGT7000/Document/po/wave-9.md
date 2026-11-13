# Wave 9 PO (re-verify after User Type hardcode + favicon)

Logged in on both as Supperadmin / 123456.

PHP: http://127.0.0.1/ (opened `/user/login`, `/forgot_password`, `/forgot_password/otp`, `/profile`, `/user/userlist`, `/setting`, `/home`)
React: http://127.0.0.1:5173/ (opened `/login`, `/forgot_password`, `/forgot_password/otp`, `/forgot_password/reset`, `/profile`, `/user/userlist`, `/setting`, `/home`)

**OTP route note:** PHP `GET /forgot_password/otp` returns **404** (`routes.php` does not register the OTP GET route; controller still has `verifyOtp` / view `verify_otp.php`). React SPA route `/forgot_password/otp` implements the intended OTP UI. OTP field/icon rows below are compared to the **PHP view** (`frontend/application/views/verify_otp.php`), not to the live 404 response. Do not fail solely because PHP route registration is incomplete when React matches the view.

Forgot flow OTP/reset UI exercised on React with mocked forgot/verify APIs (avoided locking out admin mailbox). OTP and reset pages keep `favicon.ico`. Negative forgot path still covered by wave9 tests (`ACCOUNT_NOT_FOUND`).

| Item | PHP result | React result | Verdict |
|---|---|---|---|
| Login route opens | `/user/login` and `/login` | `/login` | same |
| Login logo image | `assets/images/image_(5)_1755442027.png` loads | Same file via `/api/v1/assets/images/...` loads | same |
| Login favicon | Hardcoded `assets/images/Logo.png` loads (200) | `/api/v1/assets/images/Logo.png` loads (200) | same |
| Login fields | `email` required, `password` required, `remember_me`, SIGN IN, Forgot Password? | Same fields/names/actions | same |
| Login person/lock icons | Material Icons `person`, `lock` | Ant Design `UserOutlined`, `LockOutlined` (equivalent) | same |
| Forgot password route | `/forgot_password` | `/forgot_password` | same |
| Forgot logo | Same logo image loads | Same logo image loads | same |
| Forgot favicon | Hardcoded `favicon.ico` loads (200) | Settles on `/api/v1/assets/images/favicon.ico` (AuthBrandLayout; App Root skips overwrite on public auth paths) | same |
| Forgot email field + Send OTP | `email` required; button `get_new_password` = Send OTP | Same | same |
| Forgot email field icon | Material Icons `email` | Ant Design `MailOutlined` (equivalent) | same |
| Forgot back control | Glyphicon `circle-arrow-left` | Bootstrap Icons `bi-arrow-left-circle` (equivalent circle back) | same |
| OTP page fields (vs PHP view) | View: `otp`, Verify OTP, Resend OTP, back | Page: `otp`, Verify OTP, Resend OTP, back | same |
| OTP `vpn_key` icon (vs PHP view) | Material Icons `vpn_key` | Ant Design `KeyOutlined` (equivalent) | same |
| OTP back control (vs PHP view) | Glyphicon circle-arrow-left | `bi-arrow-left-circle` (equivalent) | same |
| OTP route reachability | `GET /forgot_password/otp` → 404 (route missing in `routes.php`) | SPA `/forgot_password/otp` exists; matches view UI (see note) | same |
| OTP / reset favicon | View uses `favicon.ico` | OTP after Send OTP and reset keep `favicon.ico` | same |
| Set password / reset fields | View: `password_confirmation` = New Password, `password` = Confirm Password, lock icons | `/forgot_password/reset`: same names/labels/order + lock icons | same |
| Profile route | `/profile` | `/profile` | same |
| Profile picture image | User avatar `5556468_...1747930836.png` loads | Same asset loads | same |
| Profile upload | `profile_pic` file + Change Picture | Same | same |
| Profile fields | `name`, `email`, user_type readonly, status readonly, `currentpassword`, `password`, `confirmPassword` | Same names/types/readonly | same |
| Profile user_type label | `lang('user_type')` renders **User Level** | `t('user_type')` renders **User Level** | same |
| Profile confirm label | `confirm_new_password` = Confirm New Password | Confirm New Password | same |
| Profile submit label | `Save` + `bi-check-circle` | `Save` + `bi-check-circle` | same |
| Profile icons (camera, person, envelope, shield, key, lock, upload, info) | Present (`bi-*`) | Present (`bi-*`) | same |
| Profile confirm lock icon | `bi-lock-fill` | `bi-lock-fill` | same |
| Profile camera overlay icon | `bi-camera-fill` overlay on avatar | `bi-camera-fill` overlay present | same |
| User list route | `/user/userlist` | `/user/userlist` | same |
| User stats | Total 3, Active 3, Pending 0, Types 2 | Same counts | same |
| User list columns | checkbox, Name, Email, **User Type**, Status, Created Date, Actions | checkbox, Name, Email, **User Type**, Status, Created Date, Actions | same |
| User filters / paging | Filter label **User Type**; status; search; page sizes 10–50 | Filter label **User Type** (hardcoded); status; search; paging 10–50 | same |
| Invite Users button | Present | Present | same |
| Export Excel button | Present | Present | same |
| Bulk select / Delete Selected | Present (shows when rows selected) | Present (shows when rows selected) | same |
| User profile thumbnails | Load (`user.png` / custom) | Same assets load | same |
| User edit/delete icons | `bi-pencil`, `bi-trash` | `bi-pencil`, `bi-trash` | same |
| Add User modal fields | name, email, user_type, password, profile_pic; labels include User Level | Same fields; User Level label | same |
| Edit User modal fields | name, email, user_type, currentpassword, password, confirmPassword, profile_pic (no status) | Same; no status field | same |
| Settings route / tabs | `/setting` General, Email, Permission, Templates | Same | same |
| Settings general fields | website, logo file+preview, register_allowed, admin_approval, user_type[] | Same | same |
| Settings logo preview | Logo image loads | Same logo loads | same |
| Settings email fields | mail_setting, company_name, SMTP_*, EMAIL (when server default) | Same conditional fields | same |
| Settings permission matrix | Roles + module CRUD checkboxes present | Roles + matrix present | same |
| Settings tab icons | `fa-cogs`, `fa-envelope`, `fa-indent`, `fa-puzzle-piece` | Same FA classes | same |
| Sidebar menu icons (profile/users/settings) | `bi-person`, `bi-people`, `bi-gear` | Same classes | same |
| Sidebar / header brand logo image | Logo image in chrome | Logo image in sider (`image_(5)_1755442027.png`, loads) | same |
| Header profile image | Avatar loads | Same avatar loads | same |
| Dark mode toggle | `localStorage jconnect_color_mode`, header sun/moon | Same key; header toggle; `bi-sun-fill` / `bi-moon-fill` | same |
| `/api/v1` routes | n/a | auth, profile, users, settings, branding under `/api/v1` | same |
| Additive SQL / no Prisma | PHP DB still works | `Database/migrations/001_schema_migrations.sql` only; no Prisma in app src | same |
| dataedge / alarm-engine | Unchanged | No edits by this wave | same |

## Fixes confirmed this pass

- User list **column** and **filter** labels hardcoded **User Type** (match PHP `user_table.php`); profile / Add User keep `t('user_type')` = **User Level**.
- Forgot / OTP / reset public auth paths keep **favicon.ico** (App Root skips branding overwrite on `PUBLIC_PATHS`).

## Tests

`cd backend && npm test -- wave9` — **15 passed**.

## Recommendation

accept

Lead decision: accept
