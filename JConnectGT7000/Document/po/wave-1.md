# Wave 1

PHP: http://127.0.0.1/user/login and http://127.0.0.1/home
React: http://127.0.0.1:5173/login and http://127.0.0.1:5173/home

Logged in on both as Supperadmin / 123456. React was driven in headless Chrome at 1440×900. PHP menu text was taken from the signed-in home page before and after `LanguageSwitcher/switchLang/thai`.

| Function | PHP result | React result | Verdict |
|---|---|---|---|
| Sign in with name Supperadmin | Redirects to `/home` | `POST /api/v1/auth/login` returns the user, then the app opens `/home` | same |
| Wrong password | Stays on login with invalid details | Stays on login and shows "Invalid details" | same |
| Sign out | Returns to the login page | Returns to `/login` and a reload stays signed out | same |
| Menu for admin | Home, Dashboard, Devices, Notification, Profile, Users, Event Logs, Alarm Logs, Reports, Device Template, Settings | Same eleven labels, in the same order | same |
| Switch to Thai | Sidebar becomes หน้าหลัก, แผงควบคุม, อุปกรณ์, การแจ้งแตือน, โปรไฟล์, ผู้ใช้, ประวัติเหตุการณ์, ประวัติการแจ้งเตือน, รายงาน, แม่แบบอุปกรณ์, การตั้งค่า | Same Thai labels | same |
| Switch back to English | English menu labels | English menu item is present and restores English labels | same |
| Remember me | Checkbox on the login form | Checkbox on the login form | same |
| Restricted menu | Non-admin users only see modules with `own_read` or `all_read` | Login response omits modules that are not granted. Admin still receives every item. Covered by `backend/test/auth.test.ts` | same |
| Stack rules | PHP on port 80 still signs in | Routes are under `/api/v1`. Access token stays in memory. Refresh token is `HttpOnly` and `SameSite=Lax`. No Prisma. No edits to `dataedge` or `alarm-engine` | same |

Tests: `npm test` in `backend` — 8 passed.

Recommendation: accept

Lead decision: accept
