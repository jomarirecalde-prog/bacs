# BACS Payroll — Production Checklist

Use this once per environment (staging/production) and again when onboarding a new payroll administrator.

---

## 1. Deploy & database

- [ ] Run migrations: `php artisan migrate`
- [ ] **Demo payroll (30 employees):** after master seed, run `php artisan db:seed --class=PayrollSampleDataSeeder` (or set `SEED_PAYROLL_SAMPLE=true` / `SEED_SAMPLE_DATA=true`). See `docs/payroll-sample-data.csv` (salary, de minimis, SSS, PhilHealth, HDMF, tax, sample loans). **ALU** (absence/late/UT) is driven by sample DTR, not the CSV.
- [ ] Confirm payroll tables exist (designations, periods, `payroll_employees`, premium rules, SSS/tax brackets, OT tables).
- [ ] Run tests (optional smoke): `php artisan test --filter=Payroll`
- [ ] `.env` mail settings configured if payslip email is used (`MAIL_*`).

### Optional `.env` defaults (overridden by Admin → Payroll configuration when saved)

| Variable | Purpose |
|----------|---------|
| `PAYROLL_WORKING_DAYS_BASIS` | Daily rate derivation (default 22) |
| `PAYROLL_OT_REQUIRES_APPROVAL` | Only approved OT counts in payroll |
| `PAYROLL_OT_CENTRAL_APPROVAL` | OT uses central workflow (Settings → Approval Workflow) |
| `PAYROLL_OT_MULTIPLIER` | OT pay multiplier (default 1.25) |
| `PAYROLL_SSS_FROM_BRACKETS` | Auto SSS from bracket table |
| `PAYROLL_TAX_FROM_BRACKETS` | Auto withholding tax from bracket table |
| `PAYROLL_PHILHEALTH_RATE` | % of gross comp. when no employee PhilHealth line |
| `PAYROLL_HDMF_AMOUNT` | Fixed HDMF per period when no employee HDMF line |
| `PAYROLL_BLOCK_FINALIZE_ON_WARNINGS` | Require acknowledging warnings before finalize |
| `PAYROLL_EMAIL_ON_PAID` | Email payslip links when period marked **Paid** |
| `PAYROLL_UNWORKED_HOLIDAY_PAY` | Pay unworked regular holidays (enable premium rule too) |

---

## 2. Master data (before first run)

- [ ] **Designations** — Admin → Payroll → Designations (classification only; salary is per employee).
- [ ] **Employees** — Each active employee has `designation_id`, work schedule, department.
- [ ] **Salary history** — Admin → Employee → Salary / benefits / deductions (or payroll salary screen):
  - Semi-monthly (or daily/hourly) effective on or before period end.
  - Recurring deductions (SSS, loans, etc.) if not using auto SSS/tax.
  - De minimis benefits if applicable.
- [ ] **DTR** — Attendance for the cutoff is complete (11–25 / 26–10 aligned with DTR periods).
- [ ] **Holidays** — Calendar/holiday master matches actual non-working days.

---

## 3. System settings

### Company & approval (Admin → Settings)

- [ ] Company name/address (used on payslips/PDF).
- [ ] **Approval Workflow** tabs configured:
  - Leave / Pardon / Travel Order (existing).
  - **Overtime Request** — endorsers and final approver (required if central OT is on).

### Payroll configuration (Admin → Payroll → Payroll configuration)

- [ ] Overtime multiplier, holiday pay mode (premium only vs full multiplier).
- [ ] **Require OT approval** — turn on for controlled OT pay.
- [ ] **Route OT through central approval** — only if OT approval is on and workflow assignees exist.
- [ ] Auto **SSS** / **tax** from bracket tables — or leave off and use fixed employee deduction lines.
- [ ] PhilHealth % / HDMF fixed amount (auto when employee has no active line).
- [ ] **Block finalize until warnings acknowledged** (recommended: on).
- [ ] **Email payslip links on Mark as paid** (optional).
- [ ] **Unworked regular holiday pay** (optional; activate **Regular holiday (unworked)** premium rule).
- [ ] Review **Holiday & rest-day premium rules** multipliers vs company policy.

### Bracket tables (when auto statutory is enabled)

- [ ] Review seeded **SSS** brackets (`payroll_sss_brackets`) — update when SSS tables change.
- [ ] Review seeded **withholding tax** brackets (`payroll_tax_brackets`) — update when BIR tables change.

---

## 4. Roles & access

| Role | Access |
|------|--------|
| **Admin** | Full payroll: periods, compute, finalize, settings, designations, salary, adjustments, admin OT screen. |
| **Supervisor** | Read-only: dashboard, periods, register, payslip detail/PDF (no compute/finalize/settings). |
| **Central approvers** | Overtime endorsement/final via sidebar (when configured). |
| **Employee** | **My Payroll** — payslips only for **Finalized** or **Paid** periods (own records only). |

---

## 5. First payroll period (walkthrough)

1. [ ] **Create period** — Admin → Payroll → New payroll period (dates match DTR cutoff).
2. [ ] **Compute attendance only** — Pulls DTR summaries into the period; fix DTR warnings first.
3. [ ] **Overtime** — If OT approval is on:
   - Review Admin → Period → **Overtime approval** and/or **Overtime Endorsement/Final** in approver inbox.
   - Deny or approve minutes before payroll compute.
4. [ ] **Adjustments** (if any) — Admin → Period → Adjustments (retro/other earning or deduction).
5. [ ] **Compute full payroll** — Builds register rows and line items.
6. [ ] **Review register** — Admin → Period → **Payroll register**; spot-check ALU, OT, holiday/premium, net.
7. [ ] **Exports** — CSV / Excel / PDF / **BACS reconciliation Excel** (`format=reconciliation`) vs `docx/PAYROLL-BACS.xlsx`.
8. [ ] **Pre-finalize checklist** on period page — resolve **blocking** items; acknowledge **warnings** if policy requires.
9. [ ] **Status flow:** Computed → For Review → **Approved** → **Finalize payroll**.
10. [ ] **Mark as paid** (optional) — Triggers payslip emails if enabled.
11. [ ] **Email payslips** — Manual button on period if needed after Finalized/Paid.
12. [ ] **Employee self-service** — Employees open **My Payroll** and download PDF/print.

### Reopening a finalized period (exception only)

- [ ] Admin sets status **Finalized → Approved** (unlocks recompute), fix data, recompute, approve, finalize again.
- [ ] **Paid** periods cannot change status.

---

## 6. Ongoing cut-off routine

Each semi-monthly cycle:

1. Close DTR for the cutoff.
2. Create or open the matching payroll period.
3. Compute attendance → clear OT pending (if required) → compute payroll.
4. HR/payroll review register + reconciliation export.
5. Approve → finalize → pay → notify employees.

---

## 7. Audit & support

- [ ] Payroll actions log to **Audit logs** (status changes include finalize validation metadata where applicable).
- [ ] Immutability: **Finalized/Paid** periods do not recompute attendance/payroll until reopened to **Approved**.

---

## 8. Quick route reference

| Area | Path |
|------|------|
| Payroll dashboard | `/admin/payroll` |
| Period detail | `/admin/payroll/periods/{id}` |
| Register | `/admin/payroll/periods/{id}/register` |
| Register export | `.../register/export?format=csv\|excel\|pdf\|reconciliation` |
| Payroll settings | `/admin/payroll/settings` |
| Central OT approvals | `/overtime/approvals` |
| Employee payslips | `/employee/payroll` |

---

## 9. Troubleshooting

| Symptom | Check |
|---------|--------|
| Zero net / error on employee | Salary missing for period end date; computation status on register detail. |
| OT not in pay | OT approval off vs on; pending OT; central workflow stuck. |
| Cannot finalize | Status not **Approved**; blocking checklist; warnings not acknowledged. |
| Mark as paid fails | Period must be **Finalized** first. |
| Employee cannot see payslip | Period not **Finalized/Paid**; wrong user/employee link. |
| SSS/tax wrong | Bracket tables vs manual deductions double-counting — use one approach per employee. |

---

*Last updated: aligns with BACS payroll module migrations through `2026_10_07_150000`.*
