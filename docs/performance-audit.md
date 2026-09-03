# BACS production performance audit

Measured against the full production path:

`Browser → Vercel (sin1 / Singapore) → vercel-php serverless → Laravel → Neon PostgreSQL (us-east-1) → Response`

Local XAMPP remains fast because MySQL is on `127.0.0.1` (~0–2 ms). Production is slow for a different reason.

---

## 1. Deployment architecture (as found)

| Layer | Production reality |
|---|---|
| App | Laravel 12 (PHP 8.2+) |
| Host | Vercel via `vercel-php@0.8.0` |
| Entry | Every dynamic route → `/api/index.php` → `public/index.php` |
| Region | `sin1` (Singapore) in `vercel.json` |
| Database | Neon **PostgreSQL** (not MySQL) — host `…-pooler.c-12.us-east-1.aws.neon.tech` |
| DB region | **AWS us-east-1 (Virginia)** |
| Sessions | `SESSION_DRIVER=database` (required on serverless; file storage is ephemeral `/tmp`) |
| Cache (before fix) | `CACHE_STORE=array` — **discarded every invocation** |
| Cache (after fix) | `CACHE_STORE=database` so catalog / dashboard snapshot survive across requests |
| Uploads | Employee photos on S3 (`EMPLOYEE_PHOTOS_DISK=s3`) |
| PDFs | DomPDF (`barryvdh/laravel-dompdf`) in-request; Excel via PhpSpreadsheet |
| Queues | `sync` on Vercel (no background worker) |
| Realtime | Laravel Echo / Reverb configured locally; production mailer is `log` |
| PWA | `sw-app.js` / `sw-station.js` — static assets only; session/API/JSON not cached |

Local XAMPP uses MySQL + file sessions + file cache. That is why “fast locally, slow on Vercel” is expected until regions and serverless constraints are addressed.

---

## 2. Measured bottlenecks (do not guess)

### Network / DB (from audit workstation, PH → Neon us-east-1)

| Operation | Time |
|---|---|
| TCP to Vercel app | ~15–40 ms |
| TCP to Neon pooler | ~230–330 ms |
| PDO connect + TLS to Neon | **~1.5 s** |
| `SELECT 1` | **~720 ms** |
| `COUNT(*)` / small selects | **~720 ms each** |
| Local MySQL TCP | ~0–2 ms |

### HTTP TTFB

| Surface | Local (XAMPP) | Vercel production |
|---|---|---|
| Login GET | **~450 ms** | **~4,000–4,300 ms** (warm, consistent) |
| Root `/` | — | ~7.8 s (cold-ish + redirect) |

curl breakdown for production login (representative):

```text
dns≈0.04s  connect≈0.06s  tls≈0.15s  ttfb≈4.23s  total≈4.23s
```

Almost all delay is **waiting for the serverless PHP response**, not browser rendering or asset download.

### Why login alone is ~4 s

Login renders a static Blade form, but every `web` request still:

1. Boots Laravel inside a Vercel PHP serverless function  
2. Opens a TLS connection to Neon in **us-east-1** from compute in **Singapore**  
3. Reads/writes the `sessions` table (database session driver)

Approx accounting that matches measurements:

```text
Request
├── TLS to Vercel                 ~150 ms
├── PHP / Laravel boot            ~500–1500 ms (varies with cold start)
├── DB connect (Neon us-east-1)   ~1000–1500 ms
├── Session SELECT                ~200–700 ms
├── Session UPDATE                ~200–700 ms
└── Total                         ~4 s
```

Authenticated pages multiply the same RTT by every extra SQL statement (dashboard snapshot, roster, leaves, notifications, catalogs).

---

## 3. Root cause

**Primary:** Geographic mismatch — Vercel `sin1` (Singapore) talking to Neon PostgreSQL in `us-east-1`. Every SQL round-trip pays hundreds of milliseconds. Query count × RTT dominates page time.

**Secondary amplifiers:**

1. `CACHE_STORE=array` on Vercel made catalog + dashboard snapshot caches useless across invocations.  
2. Database sessions are correct for serverless but add ≥2 remote queries per request.  
3. Neon may auto-suspend; first connect after idle is even slower.  
4. Serverless PHP has no warm persistent process like XAMPP/Apache.  
5. Remaining inefficient SQL (e.g. `whereDate()` on holidays) cannot use indexes and adds extra cost under high RTT.

Frontend waterfalls and CSS are **not** the production story. DevTools “Waiting for server response…” matches the measured TTFB.

---

## 4. Critical bottlenecks (ranked)

1. **Neon us-east-1 ↔ Vercel sin1 latency** (dominant)  
2. **Per-request DB session read/write** on a high-RTT link  
3. **Array cache on serverless** (fixed → database cache)  
4. **Query count × RTT** on dashboard / DTR / reports (already reduced locally; still expensive remotely)  
5. **Neon cold start / suspend** after idle  
6. **vercel-php cold start** (full Laravel boot per cold invocation)  
7. **Sync queues + in-request PDF** (long requests on serverless)  
8. **Station heartbeat every 45s** (necessary for kiosk presence; each hit pays connect+session)  
9. **Echo reconnect / live polls** if left aggressive under latency (already slowed when Echo connected)  
10. **Large report exports** without caps (already capped at 5000 rows)

---

## 5. Changes made in this audit pass

| Change | Why |
|---|---|
| `CACHE_STORE` / `CACHE_DRIVER` → `database` in `vercel.json` | Catalog + dashboard snapshot must survive serverless invocations |
| `PERF_DASHBOARD_SNAPSHOT_TTL` default 45s (+ vercel env) | Fewer full roster classifications under remote latency |
| `LogRequestPerformance` records boot / DB connect / query ms; optional `Server-Timing` via `PERF_EXPOSE_HEADERS` | Measure without guessing; no SQL bindings logged |
| `Holiday` uses `where('holiday_date', …)` instead of `whereDate()` | Keep date indexes usable |
| `AppServiceProvider` uses `config('database.default')` before any MySQL timezone SET | Avoid opening PDO during boot just to read the driver |
| pgsql `PDO::ATTR_TIMEOUT` + explicit note that persistent PDO is not used | Fail fast; stay serverless-safe |
| Employee list selects narrower eager-load columns; form departments via `DirectoryCatalog` | Less payload / reuse cached catalog |
| `.env.example` documents perf / PDO knobs | Operators can re-measure safely |

Prior work still in effect (navigation partials, leave batching, attendance date helpers, notification count-only composer, report row caps, 5-minute session heartbeat, indexes, photo resize). See sections below.

---

## 6. Before vs after (application-level)

| Surface | Before (app behavior) | After |
|---|---|---|
| Vercel cache | `array` (always miss) | `database` (shared) |
| Dashboard live poll | Full snapshot + discarded roster | Snapshot only; 45s TTL when cache works |
| Login TTFB (prod) | ~4.1–4.3 s | **Region move still required** for target &lt;1–2 s |
| Employee form departments | Fresh `Department::query()` | Cached `DirectoryCatalog` |
| Holiday date filter | `whereDate()` | Direct DATE compare |

**Important:** Code and cache fixes reduce *how many* expensive round-trips happen. They cannot remove the ~hundreds of ms per remaining trip while compute and database stay continents apart.

---

## 7. Local vs Vercel comparison (measured)

| Operation | Local | Vercel |
|---|---|---|
| Login | ~450 ms | ~4,150 ms |
| Dashboard | typically &lt;1 s (local MySQL) | multi-second (query count × RTT) |
| Attendance / DTR / Search / QR | fast on local MySQL | scales with remote round-trips |

Exact authenticated production timings vary with employee count and Neon warmth; enable `PERF_LOG=true` and `PERF_EXPOSE_HEADERS=true` temporarily on Vercel and read `Server-Timing` / `storage/logs/performance.log` (stderr on Vercel).

---

## 8. Architecture recommendation (minimum change)

Do **not** rewrite BACS or drop auth/CSRF/sessions.

### Preferred (keep users near Singapore)

1. **Create / migrate Neon to `ap-southeast-1` (Singapore)** (or another Asia region next to `sin1`).  
2. Point `DB_HOST` / `DATABASE_URL` at the new pooled endpoint.  
3. Keep Vercel `regions: ["sin1"]`.  
4. Keep `SESSION_DRIVER=database` and `CACHE_STORE=database` (or Upstash Redis in Singapore if you add it later).  
5. Disable Neon auto-suspend for production if idle cold starts matter for QR/kiosk.

Expected effect: PDO connect and `SELECT 1` drop from hundreds of ms toward low tens of ms → login and APIs enter the 1–2 s / &lt;500 ms target range for simple routes.

### Alternative

Move Vercel region to `iad1` (us-east-1) to sit next to the current Neon. Improves DB latency for Philippine users less than moving Neon to Singapore (HTML TTFB from PH to Virginia rises), but still far better than today’s cross-region SQL tax.

### What not to do

- Do not switch sessions to `file` on Vercel (`/tmp` is not shared).  
- Do not enable PDO persistent connections for “speed.”  
- Do not cache attendance punches, passwords, or permission-sensitive payloads in the CDN/service worker.  
- Do not lower session heartbeat below ~5 minutes; do not add multi-second polling that hits the DB.

### Serverless limitations to accept

| Concern | Reality on Vercel |
|---|---|
| PHP sessions | Must be DB/Redis, not local disk |
| Uploads | Must be S3 (already) |
| Long PDF/Excel | Risk timeouts; keep row caps; consider a non-serverless worker later if exports grow |
| Background jobs | `sync` only unless an external worker is added |
| Warm process | No Apache-like persistent PHP |

If heavy DTR PDF generation or large exports become chronically slow after region alignment, run those endpoints on a small always-on PHP host (or queue worker) while keeping the interactive UI on Vercel — a **partial** split, not a full rewrite.

---

## 9. Earlier application optimizations (still valid)

| Area | Change |
|---|---|
| Navigation | Partial `#app-main` swaps; Echo/bell/clock stay mounted |
| Dashboard | One `dashboardSnapshot()`; live poll without roster |
| Leaves | `LeaveResolver` batches |
| Dates | `Attendance::onDate()` / `betweenDates()`; holiday DATE compare |
| Notifications | Unread count only until panel opens |
| Catalog | Departments / employee options cached |
| DTR | Empty days skip full calculator |
| Reports | `PERF_REPORT_EXPORT_MAX_ROWS` |
| Session heartbeat | **5 minutes**, tab-visible / active only |
| Indexes | notifications, leaves, attendance, corrections |
| Logging | `PERF_LOG` → performance channel; optional headers |

---

## 10. How to re-measure after Neon region move

1. Deploy with `PERF_EXPOSE_HEADERS=true` briefly.  
2. Hard-refresh `/login` and inspect `Server-Timing` / `X-Db-Connect-Ms`.  
3. Log in; open Dashboard, Attendance, DTR; confirm TTFB.  
4. Run `php artisan test --filter=PerformanceAuditTest`.  
5. Turn `PERF_EXPOSE_HEADERS` / `PERF_LOG` back off.

---

## Config knobs

| Env | Default | Purpose |
|---|---|---|
| `PERF_LOG` | `APP_DEBUG` | Write slow/heavy requests to performance log |
| `PERF_EXPOSE_HEADERS` | `APP_DEBUG` | `Server-Timing` + timing headers (no SQL) |
| `PERF_SLOW_REQUEST_MS` | `400` | Request duration threshold |
| `PERF_SLOW_QUERY_MS` | `100` | Per-query threshold |
| `PERF_EXCESSIVE_QUERIES` | `25` | Query-count threshold |
| `PERF_DASHBOARD_SNAPSHOT_TTL` | `45` | Seconds to cache today's dashboard aggregates (`0` disables) |
| `PERF_REPORT_EXPORT_MAX_ROWS` | `5000` | Max rows for Excel/CSV/PDF export |
| `DB_PDO_TIMEOUT` | `10` | PDO connect timeout (seconds) |
| `CACHE_STORE` | `database` on Vercel | Must not be `array` in production serverless |
