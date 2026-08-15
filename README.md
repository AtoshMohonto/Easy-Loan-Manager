# EasyLoan

A modular PHP + MySQL app for tracking lenders and borrowers, their transactions, and running balances, with a dashboard, area analytics, financial summary (institution loans), monthly profit reporting, notifications, print/export, role-based login, and Bangla/English UI.

## Requirements

- PHP 8+, MySQL/MariaDB (tested on XAMPP with PHP 8.0.30)
- PDO MySQL extension enabled

## Setup

1. Import the schema (creates the `easy_loan_manager` database and seeds demo data):
   ```
   mysql -u root < database/schema.sql
   ```
2. Adjust DB credentials in `config/config.php` if not using the XAMPP default (`root` / no password / `localhost`).
3. Browse to `http://localhost/Easy-Loan-Manager/`.

## Seeded accounts

| Username | Password    | Role    |
|----------|-------------|---------|
| admin    | admin123    | Admin   |
| manager  | manager123  | Manager |
| viewer   | viewer123   | Viewer  |

**Change these passwords before any real/production use.**

## Roles

- **Admin** — everything: users, locations, all data, settings.
- **Manager** — manage persons/transactions/notifications, reports. No user/location management.
- **Viewer** — read-only: dashboard, statements, reports; can print/export but not create or edit.

## Modules

- `modules/dashboard` — KPI overview (dues, payable, monthly profit, active borrowers/lenders), location filter, profit trend chart.
- `modules/areas` — Area Analytics: loan statuses, dues and collection details per area/location, with a "record payment" marking system (writes an Amount Received / Amount Given transaction and returns to the area view).
- `modules/finance` — Financial Summary / Liabilities & Profit: tracks loans taken from banks and institutions separately (`institution_loans`), records principal/interest payments against them, and calculates net profit by balancing interest income from lending against payments made to those institutions.
- `modules/persons` — lenders & borrowers: add/edit, address, location, running balance, printable statement, CSV export.
- `modules/transactions` — Amount Given / Amount Received / Interest / Expense / Adjustment, each stored with the amount spelled out in both English and Bangla, printable receipt.
- `modules/reports/monthly_profit.php` — year-by-year monthly income/expense/profit breakdown with a chart and optional manual notes/targets per month.
- `modules/notifications` — per-user and broadcast notifications, unread badge in the top bar.
- `modules/locations` — admin-managed list used for filtering across the app.
- `modules/settings` — user management (Admin) and self-service profile/password change.

## Language

Switch between English and Bangla with the globe icon in the top bar (`?lang=en` / `?lang=bn`). The switch preserves the current page's filters and context. Translation strings live in `lang/en.php` and `lang/bn.php`.

## Balance & profit model

Each person carries one `balance`: for a Borrower it's what they owe the business; for a Lender it's what the business owes them. Every transaction's effect on that balance and on monthly profit is derived from `(person_type, transaction_type)` in `includes/functions.php` (`balance_delta()` / `profit_delta()`), and a person's balance is fully recomputed from their transaction history after every write (`recalc_person_balance()`) rather than patched incrementally, so it can't drift out of sync.

Institution loans live in their own tables (`institution_loans`, `institution_payments`) so liabilities to banks/institutions stay separate from individual lenders. The Financial Summary nets interest income from lending against principal/interest payments made to institutions to show the true profit position.
