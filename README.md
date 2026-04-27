# MemoShot Setup Guide

This README collects the special setup and operational notes for the MemoShot Laravel app, especially the pieces that are easy to miss during local setup.

## Projects

- Laravel app: `D:\Memoshot Web\memoshot`
- Docker stack: `D:\Memoshot Web\memodocker`
- Frontend sibling app: `D:\Memoshot Web\memoshot-frontend`

## Local URLs

- Central app: `http://memoshot.test`
- Tenant example: `http://tenant1.memoshot.test`
- MailHog: `http://127.0.0.1:8025`

## Start The Stack

Run Docker from the sibling `memodocker` folder:

```bash
cd "D:\Memoshot Web\memodocker"
docker compose up --build
```

The local stack is expected to include:

- `caddy`
- `memoshot-app`
- `mailhog`

Laravel container startup is expected to run:

```bash
php artisan storage:link
php artisan migrate --force
```

## Local Domain Setup

The app is designed for:

- `memoshot.test`
- `*.memoshot.test`

This matters because the app is multi-tenant and tenant routes live on subdomains.

### Windows Note

Windows `hosts` files do not support wildcard domains. That means this will not work:

```txt
127.0.0.1 *.memoshot.test
```

To use tenant subdomains locally on Windows, install a local DNS resolver such as Acrylic DNS Proxy and point `*.memoshot.test` to `127.0.0.1`.

Example mappings you want working locally:

- `memoshot.test` -> `127.0.0.1`
- `tenant1.memoshot.test` -> `127.0.0.1`
- `acme.memoshot.test` -> `127.0.0.1`

## Required Environment Settings

At minimum, review these values in [`.env`](D:/Memoshot%20Web/memoshot/.env):

```env
APP_URL=http://memoshot.test
TENANT_BASE_DOMAIN=memoshot.test

DB_CONNECTION=sqlite
DB_DATABASE=database/database.sqlite

MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025

INVOICE_DEPOSIT_PERCENTAGE=30
TRAVEL_FREE_KILOMETERS=0
TRAVEL_FEE_PER_KILOMETER=0
VITE_GOOGLE_MAPS_API_KEY=
```

### Important

The current [`.env.example`](D:/Memoshot%20Web/memoshot/.env.example) may still show placeholder local domains. When using the Docker/Caddy setup described above, your local `.env` should use `memoshot.test`.

## Storage And Uploaded Files

Catalog and add-on images are served through Laravel's public storage symlink.

If images return `403` or `404`, check that this exists:

```bash
php artisan storage:link
```

This is already expected to run on container startup.

## Windows Task Runner

For Windows, the project includes a PowerShell Makefile-style task runner in:

- `make.ps1`
- `make.cmd`
- `Makefile.windows`

Run commands from the Laravel project folder:

```powershell
cd "D:\path\to\photobooth-crm"
.\make.cmd help
```

Useful commands:

```powershell
.\make.cmd shell
.\make.cmd artisan migrate
.\make.cmd artisan route:list
.\make.cmd phpstan
.\make.cmd deploy-build
.\make.cmd deploy-ssh
.\make.cmd deploy-ssh-public
.\make.cmd deploy-all
```

If GNU Make is installed on Windows, you can also run:

```powershell
make -f Makefile.windows phpstan
make -f Makefile.windows deploy-all
```

For Windows deployment, set `DEPLOY_LOCAL_DIR` in `.env.deploy` to your Windows project path, for example:

```env
DEPLOY_LOCAL_DIR=D:/path/to/photobooth-crm
```

What these do:

- `shell`: opens a shell inside the Laravel container
- `artisan ...`: forwards any custom artisan command
- `phpstan`: runs `./vendor/bin/phpstan analyse --memory-limit=2G`
- `deploy-*`: mirrors the Mac/Linux Makefile deployment tasks using `.env.deploy`

## MailHog

Local email delivery is routed to MailHog instead of a real SMTP service.

Use these settings:

```env
MAIL_MAILER=smtp
MAIL_HOST=mailhog
MAIL_PORT=1025
```

Open captured mail here:

- `http://127.0.0.1:8025`

This is where you can verify:

- booking confirmation emails
- admin booking notifications
- invoice emails

## Booking Flow Notes

Booking creation currently supports:

- customer details
- package selection
- add-on selection
- booking email notifications
- PDF attachment containing package and add-on details

The booking page also includes:

- a fixed header summary showing the selected package and running total
- package and add-on detail popups
- native calendar date inputs

## Packages, Equipment, And Add-Ons

Packages can contain both:

- equipment
- add-ons

Add-Ons are managed through the Add-Ons admin area and are stored in the same inventory system as equipment.

Bookings can currently select:

- one package
- multiple add-ons

## Invoice And Installment Flow

Invoices are created from the admin booking popup.

Current behavior:

1. Admin creates an invoice from a booking.
2. The first installment is labeled `Deposit`.
3. The deposit defaults from the environment percentage setting.
4. Remaining balance is split across the remaining installments.
5. Admin can send the invoice by email to the customer and CC the admin.
6. The customer pays an installment through Stripe Checkout.
7. Stripe webhook confirms the payment.
8. MemoShot marks the installment as paid and recalculates invoice status.

## Deposit Percentage Setting

Default deposit percentage is controlled by [config/invoicing.php](D:/Memoshot%20Web/memoshot/config/invoicing.php), which reads:

```env
INVOICE_DEPOSIT_PERCENTAGE=30
```

If you want a different default deposit, change that value in [`.env`](D:/Memoshot%20Web/memoshot/.env).

Examples:

- `INVOICE_DEPOSIT_PERCENTAGE=20`
- `INVOICE_DEPOSIT_PERCENTAGE=50`

## Stripe Setup

Stripe is used for customer installment payments and platform subscription payments.

Set platform subscription Stripe values in [`.env`](D:/Memoshot%20Web/memoshot/.env):

```env
PLATFORM_STRIPE_SECRET=sk_test_...
PLATFORM_STRIPE_WEBHOOK_SECRET=whsec_...
PLATFORM_STRIPE_CURRENCY=usd
PLATFORM_SUBSCRIPTION_AUTO_CHARGE=true
PLATFORM_SUBSCRIPTION_REMINDER_DAYS=5
PLATFORM_SUBSCRIPTION_RETRY_DAYS=5
PLATFORM_SUBSCRIPTION_SCHEDULE_TIME=09:00
PLATFORM_SUBSCRIPTION_ADMIN_EMAIL=admin@example.com
```

What each setting does:

- `PLATFORM_STRIPE_SECRET`: creates platform subscription Checkout Sessions
- `PLATFORM_STRIPE_WEBHOOK_SECRET`: verifies platform subscription webhook signatures
- `PLATFORM_STRIPE_CURRENCY`: default platform subscription currency
- `PLATFORM_SUBSCRIPTION_AUTO_CHARGE`: enables automatic platform subscription deductions
- `PLATFORM_SUBSCRIPTION_REMINDER_DAYS`: sends the upcoming charge email this many days before the due date
- `PLATFORM_SUBSCRIPTION_RETRY_DAYS`: retry delay after a failed platform subscription payment
- `PLATFORM_SUBSCRIPTION_SCHEDULE_TIME`: daily Laravel scheduler time for platform subscription processing
- `PLATFORM_SUBSCRIPTION_ADMIN_EMAIL`: fallback/admin email for platform subscription billing notices

Platform subscription automation uses the saved payment method from the tenant's first successful platform Checkout payment. The daily billing command sends reminders, charges due monthly/quarterly/yearly subscriptions, sends thank-you emails after successful payments, and sends failure emails before retrying after the configured retry window.

Run it manually:

```bash
/opt/alt/php84/usr/bin/php artisan platform-subscriptions:process
```

Hostinger cron should call Laravel's scheduler once per minute:

```bash
cd /home/u838520432/domains/memoshot.com/laravel_app && /opt/alt/php84/usr/bin/php artisan schedule:run >> /dev/null 2>&1
```

Tenant/customer invoice payments do not use shared `.env` Stripe secrets. Each tenant must configure its own Stripe secret, webhook secret, and currency in Workspace Settings so customer payments go directly to the tenant owner's Stripe account.

Stripe config is loaded from [config/services.php](D:/Memoshot%20Web/memoshot/config/services.php).

## Stripe Payment Behavior

There are two customer payment entry points:

- invoice email link
- `Pay This Installment` button on the customer invoice page

Both now create a Stripe Checkout Session.

The app does not trust the browser redirect by itself as proof of payment. Webhooks are the primary confirmation path, and successful return URLs also verify the Checkout Session directly with Stripe before marking any payment as paid.

## Stripe Webhook

Tenant/customer invoice webhook route:

```txt
POST /stripe/webhook
```

Platform subscription webhook route:

```txt
POST /platform/stripe/webhook
```

Relevant files:

- [web.php](D:/Memoshot%20Web/memoshot/routes/web.php)
- [StripeWebhookController.php](D:/Memoshot%20Web/memoshot/app/Http/Controllers/StripeWebhookController.php)

Current webhook event handled by both endpoints:

- `checkout.session.completed`

Tenant invoice Stripe session metadata is expected to include:

- `invoice_id`
- `installment_id`

Platform subscription Stripe session metadata is expected to include:

- `tenant_subscription_charge_id`

Without a working webhook, Stripe can still collect payment, but MemoShot will not automatically mark the installment as paid.

## Stripe Dashboard Or CLI Setup

Create a webhook endpoint in Stripe that points to your app.

Tenant invoice production example:

```txt
https://your-domain/stripe/webhook
```

Platform subscription production example:

```txt
https://your-domain/platform/stripe/webhook
```

For local development, use a tunnel or Stripe CLI.

Stripe CLI example:

```bash
stripe listen --forward-to http://memoshot.test/stripe/webhook
```

Platform Stripe CLI example:

```bash
stripe listen --forward-to http://memoshot.test/platform/stripe/webhook
```

After starting Stripe CLI, copy the webhook signing secret it prints and place it into:

```env
PLATFORM_STRIPE_WEBHOOK_SECRET=whsec_...
```

For tenant invoice payments, save the tenant-specific webhook secret in that tenant's Workspace Settings instead of `.env`.

Recommended Stripe event subscription:

- `checkout.session.completed`

Official references:

- [Stripe Checkout Sessions API](https://docs.stripe.com/api/checkout/sessions/create?lang=php)
- [How Checkout works](https://docs.stripe.com/payments/checkout/how-checkout-works)
- [Checkout fulfillment and webhooks](https://docs.stripe.com/checkout/fulfillment)

## Google Address Autocomplete

Address and location fields can use Google address suggestions through the Maps JavaScript API Places library.

Set this value in [`.env`](D:/Memoshot%20Web/memoshot/.env):

```env
VITE_GOOGLE_MAPS_API_KEY=your_google_maps_javascript_api_key
TRAVEL_FREE_KILOMETERS=20
TRAVEL_FEE_PER_KILOMETER=2.50
```

This powers autocomplete on:

- public booking event location
- admin booking event location
- lead event location
- workspace address in Settings

Travel pricing notes:

- `TRAVEL_FEE_PER_KILOMETER` is used on the public booking page
- `TRAVEL_FREE_KILOMETERS` is the one-way free allowance before travel fees are charged
- when the customer enters the event location, the app calculates the driving distance from the workspace address in Settings
- travel is charged as a round trip: for example, 50 km each way with 20 km free each way means 100 km total minus 40 km free, so 60 km is chargeable
- that travel fee is added into the booking total, invoice total, and deposit calculation
- the workspace address must be saved in Settings for travel pricing to work

Google setup notes:

- enable the Maps JavaScript API
- enable Places API / Places library for your project
- enable the Geocoding API for distance calculation from address to address
- restrict the key for web usage

Official reference:

- [Place Autocomplete Widget](https://developers.google.com/maps/documentation/javascript/place-autocomplete-new)

## Customer Invoice Page

Route pattern:

```txt
/invoices/{invoice_token}
```

This page currently shows:

- booking details
- invoice total
- amount paid
- balance due
- installment schedule
- Stripe payment button for unpaid installments

## Main Billing Files

- [InvoiceController.php](D:/Memoshot%20Web/memoshot/app/Http/Controllers/InvoiceController.php)
- [StripeWebhookController.php](D:/Memoshot%20Web/memoshot/app/Http/Controllers/StripeWebhookController.php)
- [StripeCheckoutLinkGenerator.php](D:/Memoshot%20Web/memoshot/app/Support/StripeCheckoutLinkGenerator.php)
- [InvoiceIssuedMail.php](D:/Memoshot%20Web/memoshot/app/Mail/InvoiceIssuedMail.php)
- [issued.blade.php](D:/Memoshot%20Web/memoshot/resources/views/emails/invoices/issued.blade.php)
- [show.blade.php](D:/Memoshot%20Web/memoshot/resources/views/invoices/show.blade.php)
- [services.php](D:/Memoshot%20Web/memoshot/config/services.php)
- [invoicing.php](D:/Memoshot%20Web/memoshot/config/invoicing.php)

## Useful Commands

Run migrations:

```bash
cd "D:\Memoshot Web\memodocker"
docker compose exec memoshot-app php artisan migrate --force
```

Run tests:

```bash
cd "D:\Memoshot Web\memodocker"
docker compose exec memoshot-app php artisan test
```

Run the invoice test suite only:

```bash
cd "D:\Memoshot Web\memodocker"
docker compose exec memoshot-app php artisan test tests/Feature/InvoiceTest.php
```

## Feature Test Files

- [BookingTest.php](D:/Memoshot%20Web/memoshot/tests/Feature/BookingTest.php)
- [BookingNotificationTest.php](D:/Memoshot%20Web/memoshot/tests/Feature/BookingNotificationTest.php)
- [CatalogAdminTest.php](D:/Memoshot%20Web/memoshot/tests/Feature/CatalogAdminTest.php)
- [InvoiceTest.php](D:/Memoshot%20Web/memoshot/tests/Feature/InvoiceTest.php)

## Recent Feature Migrations

- [2026_03_23_000007_create_booking_inventory_item_table.php](D:/Memoshot%20Web/memoshot/database/migrations/2026_03_23_000007_create_booking_inventory_item_table.php)
- [2026_03_24_000008_create_inventory_item_package_table.php](D:/Memoshot%20Web/memoshot/database/migrations/2026_03_24_000008_create_inventory_item_package_table.php)
- [2026_03_24_000009_create_invoices_table.php](D:/Memoshot%20Web/memoshot/database/migrations/2026_03_24_000009_create_invoices_table.php)
- [2026_03_24_000010_create_invoice_installments_table.php](D:/Memoshot%20Web/memoshot/database/migrations/2026_03_24_000010_create_invoice_installments_table.php)

## Shared Hosting Manual Deployment

This project supports a Hostinger-style shared hosting layout where the Laravel app folder is a sibling of `public_html`.

Expected server layout:

```txt
/home/u838520432/domains/memoshot.com/laravel_app
/home/u838520432/domains/memoshot.com/public_html
```

Upload the full Laravel app into:

```txt
/home/u838520432/domains/memoshot.com/laravel_app
```

Only the files from Laravel's `public/` folder should be uploaded into:

```txt
/home/u838520432/domains/memoshot.com/public_html
```

Do not upload `node_modules`. Run `npm run build` locally and upload the generated `public/build` files instead.

### Local Deploy Settings

Copy the deploy environment template:

```bash
cp .env.deploy.example .env.deploy
```

Then edit `.env.deploy` with the hosting SSH details:

```env
SSH_HOST=157.173.209.64
SSH_USER=u838520432
SSH_PORT=65002
SSH_REMOTE_APP_DIR=/home/u838520432/domains/memoshot.com/laravel_app
SSH_REMOTE_PUBLIC_DIR=/home/u838520432/domains/memoshot.com/public_html
DEPLOY_SERVER_APP_PATH=/home/u838520432/domains/memoshot.com/laravel_app
DEPLOY_LOCAL_DIR=/Users/junedelacruz/Desktop/aaa-project/photobooth-crm
```

Notes:

- `SSH_HOST`, `SSH_USER`, and `SSH_PORT` should match the SSH command that works from your terminal.
- `SSH_REMOTE_APP_DIR` is the full server path for the Laravel application.
- `SSH_REMOTE_PUBLIC_DIR` is the full server path for the domain web root.
- `DEPLOY_SERVER_APP_PATH` is the absolute server filesystem path used inside the generated `public_html/index.php`.
- `.env.deploy` is ignored by git and must not be committed.

### Rsync Deploy Commands

Build production dependencies and assets locally:

```bash
make deploy-build
```

Upload the Laravel app folder to `laravel_app` with rsync over SSH:

```bash
make deploy-ssh
```

Upload only Laravel's public files to `public_html` and generate the shared-hosting `index.php`:

```bash
make deploy-ssh-public
```

Run the full build and upload flow:

```bash
make deploy-all
```

Upload both the app and public files without rebuilding:

```bash
make deploy-ssh-all
```

Use any delete variant only after confirming the remote paths are correct:

```bash
make deploy-ssh-delete
make deploy-ssh-public-delete
```

The delete targets mirror local files to the remote folder and remove remote files that no longer exist locally.

### Generated `public_html/index.php`

The `make deploy-ssh-public` target builds a temporary `.deploy/public_html` folder and generates `index.php` from:

```txt
deploy/hostinger-shared/index.php.stub
```

The generated file points to:

```txt
DEPLOY_SERVER_APP_PATH=/home/u838520432/domains/memoshot.com/laravel_app
```

You do not need to edit Laravel's `bootstrap/app.php`.

### Production `.env`

Create the production `.env` on the server at:

```txt
/home/u838520432/domains/memoshot.com/laravel_app/.env
```

Important production values:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://memoshot.com
TENANT_BASE_DOMAIN=memoshot.com
```

Set the production database, mail, Stripe, and API keys in this server `.env`. Do not upload the local `.env`.

### Storage Symlink

On Hostinger or a similar shared-hosting layout, point `public_html/storage` to Laravel's `storage/app/public` using absolute paths:

```bash
rm -f /home/u838520432/domains/memoshot.com/public_html/storage
ln -s /home/u838520432/domains/memoshot.com/laravel_app/storage/app/public /home/u838520432/domains/memoshot.com/public_html/storage
```

Verify it:

```bash
ls -l /home/u838520432/domains/memoshot.com/public_html/storage
readlink -f /home/u838520432/domains/memoshot.com/public_html/storage
```

Expected result:

```txt
/home/u838520432/domains/memoshot.com/public_html/storage -> /home/u838520432/domains/memoshot.com/laravel_app/storage/app/public
/home/u838520432/domains/memoshot.com/laravel_app/storage/app/public
```

If `public_html/storage` already exists as a real directory, inspect it first:

```bash
ls -la /home/u838520432/domains/memoshot.com/public_html/storage
```

If it is safe to replace:

```bash
rm -rf /home/u838520432/domains/memoshot.com/public_html/storage
ln -s /home/u838520432/domains/memoshot.com/laravel_app/storage/app/public /home/u838520432/domains/memoshot.com/public_html/storage
```

Do not expose the entire Laravel `storage` folder. Only expose `storage/app/public`.

### Server Post-Deploy Commands

SSH into Hostinger:

```bash
ssh -p 65002 u838520432@157.173.209.64
```

Go to the Laravel app folder:

```bash
cd /home/u838520432/domains/memoshot.com/laravel_app
```

Hostinger may have multiple PHP versions available, so use the explicit PHP 8.4 binary when running Artisan:

```bash
/opt/alt/php84/usr/bin/php artisan migrate
/opt/alt/php84/usr/bin/php artisan migrate --force
/opt/alt/php84/usr/bin/php artisan optimize:clear
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
```

For a normal production deployment, run this sequence:

```bash
cd /home/u838520432/domains/memoshot.com/laravel_app
/opt/alt/php84/usr/bin/php artisan migrate --force
/opt/alt/php84/usr/bin/php artisan optimize:clear
/opt/alt/php84/usr/bin/php artisan config:cache
/opt/alt/php84/usr/bin/php artisan route:cache
/opt/alt/php84/usr/bin/php artisan view:cache
```

## Current Limitations

- Webhook handling currently focuses on `checkout.session.completed`
- No refund flow yet
- No Stripe customer portal yet
- No automated overdue reminders yet
- Local wildcard tenant routing still depends on Windows DNS setup outside this repo

## Hostinger Auto Deploy

This repo now includes [deploy-hostinger.yml](D:/Memoshot%20Web/memoshot/.github/workflows/deploy-hostinger.yml), which deploys automatically whenever `main` is pushed.

Required GitHub repository secrets:

- `HOSTINGER_HOST`
- `HOSTINGER_PORT`
- `HOSTINGER_USERNAME`
- `HOSTINGER_SSH_KEY`
- `HOSTINGER_APP_PATH`

Expected server setup:

- the Laravel app already exists at `HOSTINGER_APP_PATH`
- the production [`.env`](D:/Memoshot%20Web/memoshot/.env) is already on the server
- PHP and Composer are installed on the server
- `storage` and `bootstrap/cache` are writable

Deploy flow:

- GitHub builds the Vite assets
- the workflow syncs the project to Hostinger over SSH
- Hostinger runs:
  - `composer install --no-dev`
  - `php artisan migrate --force`
  - `php artisan optimize:clear`
  - `php artisan config:cache`
  - `php artisan route:cache`
  - `php artisan view:cache`



/opt/alt/php84/usr/bin/php artisan migrate
/opt/alt/php84/usr/bin/php artisan db:seed --class=MemoShotSeeder --force
