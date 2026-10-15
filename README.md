# Air Courier System

YGN ↔ SG two-branch international courier POS built with Laravel 13, PHP 8.4+, Blade and a relational database. Local CSS/JavaScript and server-rendered pages need no frontend build or third-party CDN.

## Setup

Requirements: a maintained PHP 8.4+ release with BCMath, PDO/SQLite (or PDO/MySQL), mbstring, XML, curl, ctype, fileinfo, openssl and tokenizer, plus Composer 2. SQLite is the default for local testing. MySQL 8.4/InnoDB is recommended for production.

```sh
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan db:seed
php artisan courier:admin you@example.com --name="Branch Admin"
php artisan serve --host=127.0.0.1 --port=8010
```

Open http://127.0.0.1:8010. The admin command prompts for a password without placing it in shell history. Configure company information and assign staff to YGN or SG before booking. New branch bookings use kg, a manually entered charge, and a choice of MMK or SGD. Legacy destination rates remain available for historic rated workflows. Add customers and staff through the app. The normal seeder creates no user accounts.

On the prepared VS Code machine, a private development PHP runtime and Composer are in the ignored `.tools` directory. No system PATH change is necessary:

```sh
./bin/setup
./bin/serve
./bin/test
```

VS Code → Terminal → Run Task includes **Courier: serve** and **Courier: tests**. Other machines should install PHP/Composer normally; `.tools` and `vendor` are local dependencies. The server binds to localhost only.

## Connected YGN and SG workflow

Use one centrally hosted Laravel application and database for both locations. Both branches open the same HTTPS URL and sign in with their own branch-assigned accounts. Running independent local SQLite databases does not connect the branches; this repository's localhost server is for local review.

After pulling the upgrade, run `./bin/php artisan migrate` (or `php artisan migrate`). The additive migration creates YGN and SG, assigns existing staff to YGN, and preserves existing parcels and financial entries as legacy records with no inferred route. Administrators can change staff branch assignments in System settings and inspect either branch in Branch operations.

New booking: origin branch → opposite destination branch, total kg, sender/recipient, manual MMK or SGD charge. The currency and charge are locked for that parcel. Payments use that same currency; there is no automatic exchange conversion or mixed-currency settlement.

Received → Processing → Ready for Dispatch → dispatch with flight number and departure date/time → physical handover → In Transit (optional) → Arrived at Branch → Ready for Collection → Collected. Dispatches cannot mix directions. Enter departure time in the origin branch's timezone (Asia/Yangon or Asia/Singapore); it is stored consistently and shown with its timezone. Destination staff record arrival and collection. Collection requires a settled balance and collector name; staff, time, and optional confirmation reference remain in the audit trail.

Bookkeeping filters one currency at a time. Charges and current balances belong to the origin branch, while payments belong to the branch that actually received them. Refund corrections retain the original receiving branch. Reconciliation is per receiving branch, currency, date and method; there is no branch remittance or exchange-rate accounting.

For fictional two-branch samples, only on a local/testing database:

```sh
./bin/php artisan db:seed --class=BranchDemoSeeder
```

This idempotent seeder adds sample transfers in both directions and branch demo staff: `ygn@courier.test` and `sg@courier.test`, password `CourierDemo2026!`. It does not replace existing accounts or records. These are local test accounts only.

## Fictional sample data

Only in local/testing environments, on a separate local database:

```sh
php artisan db:seed --class=DemoSeeder
```

Or `.tools/php artisan db:seed --class=DemoSeeder` on the prepared machine.

| Role | Email | Local demo password |
| --- | --- | --- |
| Admin | admin@courier.test | CourierDemo2026! |
| Staff | staff@courier.test | CourierDemo2026! |

Includes fictional customers, sample rates, bookings, partial/full payments and a handed-over dispatch. Rates are illustrative. DemoSeeder refuses other environments and skips an existing demo administrator. For live operations start with a fresh database and the normal seeder; never use demo accounts/data.

## Implementation phases

1. Records and booking: authentication, admin/staff roles, customers, configurable rates, unique tracking/booking keys, exact fees and historical price/sender snapshots.
2. Payments and books: full/partial payments, idempotent submissions, admin refunds, append-only history, daily/monthly summaries, CSV exports and reconciliation.
3. Warehouse and dispatch: receipt, processing, readiness, locked parcel assignment, manifests, handover and tracking through delivery/exception.
4. Operations and verification: settings, staff accounts, audit history, printable receipts/labels, sample data, backup command and automated checks.

This initial MVP is ready for local review and acceptance testing. It does not deploy a production service.

## Rules and workflows

- **Permissions:** staff manage customers, bookings, tracking, payments, dispatches, reports/exports and reconciliation. Admins additionally manage settings, rates, users, audit history and refunds. There is no public registration.
- **Authentication:** hashed passwords, throttled login, server-side authorization and CSRF protection. Deactivated accounts are ejected on subsequent requests. Password updates invalidate database sessions.
- **Money:** SQL DECIMAL amounts and integer minor-unit arithmetic; BCMath handles fractional weight. Individual amounts support up to 999,999,999.99. Currency always uses two decimal places. Currency and kg/lb unit lock after the first booking to prevent mixed-currency/unit reports. No currency conversion.
- **Fees:** max(minimum, base + round-up-to-cent(total weight × rate)) + additional charge. Enter total actual weight for all pieces. Quantity is descriptive; dimensional weight is out of scope. The server confirms the authoritative price from the current rate at submission and saves the rate snapshot.
- **History:** sender contact/address, destination name, rate and final price are snapshotted. Subsequent customer/rate edits affect future bookings. Booking prices cannot be edited. Cancel an incorrect booking with a reason and rebook; retain financial entries on the original.
- **Payments:** unique submission keys make retries idempotent. Reusing a key with different details fails. Zero/negative amounts, overpayments and refunds above the original unrefunded payment are rejected. Bank references are optional and need not be unique, because one reference may cover multiple shipments.
- **Corrections:** admin records a refund against the original payment with a reason, then records the correct replacement payment. Originals remain unchanged. Refunds use the original payment method. Cancellations retain payment history and flag retained payments for admin review. Cancelled unpaid bookings remain in the explicitly labeled outstanding review list but are excluded from non-cancelled booked-charge totals.
- **Status:** Booked → Received → Processing → Ready for Dispatch → Dispatched → In Transit → Delivered. Only physical dispatch handover creates Dispatched. Exceptions/cancellations require notes. Resuming transit/delivery requires a handover. Delivered/Cancelled are terminal. Draft dispatch parcels must be unassigned before returning to warehouse stages or cancelling.
- **Dispatch:** remove parcels from a draft to correct its carrier/airline/flight by creating a corrected draft. The empty original and its audit trail remain. Handed-over records cannot be undone through the app.
- **Reports:** branch-local dates (APP_TIMEZONE=Asia/Yangon); booked charges use shipment date, payments/refunds use payment date. Outstanding is a current all-dates view, including cancelled bookings awaiting review, not historical accounts receivable. Date ranges support up to 366 days. Exports are Excel-compatible UTF-8 CSV with a BOM and formula protection.
- **Reconciliation:** actual net receipts per day and method are compared with payments less refunds. Variances require notes; negative actuals support refund-only days. Snapshots never change. Later ledger entries, including zero-net additions, mark prior snapshots for review; save a new snapshot after review.
- **Financial meaning:** booked charges, receipts, refunds and net receipts are separate. The system does not calculate revenue recognition or net profit without cost/expense records.
- **Audit limits:** application history is append-only. Database administrators can still modify records directly; restrict database access and protect backups. Audit records include shipment/contact details and are admin-only.

## Verification

```sh
php artisan test --compact
vendor/bin/pint --format agent
php artisan view:cache
php artisan route:list --except-vendor
```

Or use `./bin/test`. Tests use an isolated in-memory SQLite database by default and never use the demo database. Coverage includes booking, fee rounding, historical prices, duplicate/partial/full/invalid payments, refunds, permissions, tracking, dispatch, date-filtered reports, CSV formula protection, reconciliation, accounts and page rendering. CI runs SQLite and MySQL variants.

## Backups and recovery

### SQLite

```sh
php artisan courier:backup
```

Creates a consistent snapshot with VACUUM INTO, verifies PRAGMA integrity_check and stores a timestamped file in private `storage/app/private/backups` with restrictive permissions. Keep daily snapshots and copy them to protected off-device storage. Protect `.env` and APP_KEY separately. Regularly restore into an isolated test installation to verify recovery.

For recovery, stop the server and all workers. Preserve the damaged database and any `-wal`/`-shm` files together as a recovery set. Rename those files out of the way, then copy a verified backup to the database path configured in `.env` (default `database/database.sqlite`). Restore matching configuration/APP_KEY and file ownership, then run:

```sh
php artisan optimize:clear
php artisan migrate:status
```

Verify users, shipment counts, payment totals and sample receipts before resuming. If recovering onto newer code, back up the restored database before applying pending migrations. Never run migrate:fresh against business data.

### MySQL production

Use InnoDB and a dedicated least-privilege application user. Set DB_CONNECTION=mysql, DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD; replace any SQLite DB_DATABASE path. Use maintained PHP/MySQL versions, APP_ENV=production, APP_DEBUG=false, HTTPS, SESSION_SECURE_COOKIE=true, a protected `.env`, an appropriate APP_URL and a web root exposing only `public/`.

Install with `composer install --no-dev --optimize-autoloader`, migrate with `php artisan migrate --force`, run only `php artisan db:seed --force`, then create your admin with courier:admin. Do not use DemoSeeder.

A database operator should take daily consistent backups, using a restricted option file to keep credentials out of process arguments/history:

```sh
mysqldump --defaults-extra-file=/secure/mysql-backup.cnf --single-transaction --routines --triggers --no-tablespaces courier > /secure/backups/courier.sql
```

Check the exit status, encrypt/protect backups and copy them off-device. The application backup command deliberately refuses MySQL. Restore first into an isolated empty recovery database using the MySQL client and a restricted credentials option file. Verify schema, foreign keys, counts, ledger/audit history and report totals before a planned maintenance switch to the recovered database. Preserve the old database and matching application release/APP_KEY for rollback. Agree on retention and recovery objectives before launch.

## Scope and release

No external courier/airline APIs, payment gateway, SMS/messaging, public booking portal, mobile app, branches beyond the YGN/SG pair, payroll or profit accounting. Develop and test on demo; promote a reviewed and backed-up release to prod after branch acceptance testing.
