# BACS performance audit

Measured against the Blade + Laravel request path (Frontend → controller → database → HTML/JSON → Alpine).
The slow page-switch feeling was **not** a CSS issue. It came from full document reloads plus duplicated backend work.

## Bottlenecks found (initial + follow-up)

### 1. Full page reloads on every module switch
Every sidebar click reloaded HTML, CSS, JS, Bunny fonts, the clock, and Laravel Echo. Echo then fired `echo-connected`, which re-fetched the notification feed **and** (on calendar pages) the live calendar HTML — data the server had just rendered.

### 2. Dashboard live poll that did not update the UI
`adminLive()` hit `/admin/dashboard/live` every 15–30s. That endpoint rebuilt the full roster summary and loaded 50 employee rows, then the browser only updated summary cards (rows were discarded).

### 3. N+1 leave queries
`Leave::approvedOn()` ran inside `AttendanceService::classify()` (once per employee with no attendance row) and inside `AttendanceCalculator` (once per empty day in monthly DTR). A 100-employee dashboard could issue 100+ leave queries.

### 4. Duplicate dashboard loads
`dashboardSummary()` and `departmentSummaries()` each loaded every active employee and every attendance row for the day, then classified them separately.

### 5. `whereDate()` on DATE columns
`whereDate('attendance_date', $date)` compiles to `DATE(attendance_date) = ?`, which cannot use the `(attendance_date, status)` index on PostgreSQL.

### 6. Notification history on every page
The layout composer loaded unread count **and** the latest 8 notifications on every request. Echo reconnect then loaded 20 more.

### 7. Unbounded employee dropdowns and attendance history
DTR, reports, and the dashboard loaded every employee model for `<select>` filters. Employee attendance history had no default date window, so the first open scanned the full table (paginated, but still a wide index range).

### 8. `WorkSchedule::defaultSchedule()` per employee
Employees without a schedule each queried the default row. Tiny table, but repeated inside classification loops.

### 9. Monthly DTR empty-day calculator
For each day without a punch, `rangeDtr()` ran the full `AttendanceCalculator` path even when the only outcome was leave / holiday / rest / absent.

### 10. Report exports without a row ceiling
Excel/CSV/PDF exports called `->get()` with no cap, which could load tens of thousands of attendance rows into memory.

### 11. Large profile photos
Uploaded employee photos were stored at original resolution and served in list views.

## Optimizations

| Area | Change |
|---|---|
| Navigation | Same-origin GET clicks/forms fetch an `X-BACS-Partial` fragment and swap `#app-main`. Echo, the bell, and the clock stay mounted. In-flight navigations abort. |
| Dashboard | One `dashboardSnapshot()` pass; short-lived (15s) cache for today's aggregates, flushed on every punch/edit; live poll updates stats + department table only (no roster rebuild), pauses when the tab is hidden, slows to 60s when Echo is connected. |
| Leaves | `LeaveResolver` batches by date or by employee-month. |
| Dates | `Attendance::onDate()` / `betweenDates()` compare the DATE column directly. Reports no longer use `whereMonth`/`whereYear`. |
| Notifications | Composer loads unread count only. History loads when the panel opens. Echo reconnect syncs the count, not the list. |
| Catalog | Departments and employee dropdowns cached 10 minutes; flushed on employee/department save. |
| Attendance | Employee history defaults to the current month. Dashboard / DTR maps select only needed columns. |
| DTR | Empty past days resolve status via leave/holiday/rest checks (no punch calculator). Admin filters use `whereIn` subqueries instead of correlated `whereHas`. |
| Reports | Export capped at `PERF_REPORT_EXPORT_MAX_ROWS` (default 5000). Department filter uses indexed subquery. |
| Search | Approver picker: min 2 chars, 300ms debounce, aborts in-flight fetches. |
| Photos | New uploads resized to max 512px JPEG (~quality 82). List/header images use `loading="lazy"`. |
| JS | QR library is dynamically imported. Server time is session-cached 30s. Duplicate GETs are deduped. Axios timeout 15s. Page timers unsubscribe on `bacs:pagehide`. Session heartbeat every 5 minutes when the tab is active. |
| Indexes | `app_notifications (user_id, created_at)`, `leaves (employee_id, status, start_date, end_date)`, `attendance (attendance_date, employee_id)`, correction `(employee_id, attendance_date, status)`. Existing attendance unique + date/status indexes were already correct. |
| Logging | `storage/logs/performance.log` (enable with `PERF_LOG=true`). Debug responses include `X-Request-Time-Ms` and `X-Query-Count`. No SQL bindings are logged. |

## What was intentionally not cached

Attendance rows, DTR calculations, and unread notification **content** stay live. Only directories, settings, the default schedule, the calendar-events schema flag, and a **15-second** dashboard aggregate snapshot are cached. Punch writes flush the snapshot immediately.

## Before / after (representative)

| Surface | Before | After |
|---|---|---|
| Admin dashboard live poll | Full snapshot + 50-row roster every 30s | Snapshot only (often cache hit); no roster |
| Monthly DTR empty days | Calculator per missing day | Status lookup via preloaded leave/holiday maps |
| Employee dashboard | Double `todayFor` query | Single attendance read for today + next action |
| Report export | Unbounded `get()` | Hard cap + user-facing error when exceeded |
| Approver search | Any length, overlapping fetches | Min 2 chars, abort previous |

Exact timings depend on host and data volume. With `PERF_LOG=true`, compare `ms` / `queries` in `storage/logs/performance.log`.

## How to verify

1. Open DevTools → Network, click Dashboard → Attendance → DTR → Calendar. Document requests should be partial HTML, not a full reload. Echo should stay connected.
2. On the admin dashboard, confirm `/admin/dashboard/live` updates the summary cards and department table **without** a `rows` payload, and that it stops when you switch modules.
3. With `PERF_LOG=true`, watch `storage/logs/performance.log` for requests over 400ms or 25 queries.
4. Run `php artisan test --filter=PerformanceAuditTest`.

## Config knobs

| Env | Default | Purpose |
|---|---|---|
| `PERF_LOG` | `APP_DEBUG` | Write slow/heavy requests to `performance.log` |
| `PERF_SLOW_REQUEST_MS` | `400` | Request duration threshold |
| `PERF_SLOW_QUERY_MS` | `100` | Per-query threshold |
| `PERF_EXCESSIVE_QUERIES` | `25` | Query-count threshold |
| `PERF_DASHBOARD_SNAPSHOT_TTL` | `15` | Seconds to cache today's dashboard aggregates (`0` disables) |
| `PERF_REPORT_EXPORT_MAX_ROWS` | `5000` | Max rows for Excel/CSV/PDF export |
