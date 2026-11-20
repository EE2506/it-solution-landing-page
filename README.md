# Build your project & estimate

A quote builder for an independent software developer serving Filipino business owners. Visitors choose a service, shape the scope, and request a quote without an account. The owner reviews saved requests and tracks both email deliveries.

## Local setup

Requirements are PHP 8.4, Composer 2, Node.js 22.12 or newer, npm, and MariaDB. Enable the PHP OpenSSL, PDO MySQL, mbstring, fileinfo, and zip extensions. PDO SQLite is also needed for the test suite.

1. Run `composer install` and `npm ci`.
2. Copy `.env.example` to `.env`, configure an empty MariaDB database and its credentials, and run `php artisan key:generate`.
3. Run `php artisan migrate` and `php artisan quotes:create-owner owner@example.com`, using your own email when creating the account.
4. Run `npm run build` and `php artisan serve`. Visit the local homepage for the builder and `/owner` for the dashboard. Use `npm run dev` for frontend development.

The original HTML is the visual reference. The live builder is served by Laravel, Vue 3, and Inertia.

## Catalog and pricing

Edit [config/quote.php](config/quote.php) for services, installations, inclusions, add-ons, recommendation rules, and rates. Amounts use integer centavos. Keep existing catalog IDs stable and replace the demo rates before launch. The rush percentage applies only to eligible one-time work. If config is cached, rebuild it with `php artisan config:cache` after edits. Saved requests keep their original scope and amounts.

## Gmail and background delivery

Set private environment variables for the sender mailbox and app password. Use the authenticated Gmail address for `MAIL_FROM_ADDRESS`; set `QUOTE_OWNER_EMAIL` to the inbox that should receive requests.

```dotenv
MAIL_MAILER=smtp
MAIL_SCHEME=smtps
MAIL_HOST=smtp.gmail.com
MAIL_PORT=465
MAIL_USERNAME=sender@example.com
MAIL_PASSWORD=
MAIL_FROM_ADDRESS=sender@example.com
QUOTE_OWNER_EMAIL=owner@example.com
```

Google requires 2-Step Verification for app passwords. Some account policies disable them. [Google app password help](https://support.google.com/mail/answer/185833)

Run `php artisan quotes:deliver` to process pending mail once. On the host, run `php artisan schedule:run` every minute or supervise `php artisan schedule:work`. The worker retries transport failures with backoff. The owner can retry a failed recipient from the dashboard. Confirm receipt of both copies using a test submission. Local array mail transport discards messages after the process exits.

Serve `public/` over HTTPS, set `APP_DEBUG=false` and `SESSION_SECURE_COOKIE=true`, and retain private backups of the app key and database. For stable email lookup across encryption key rotations, set a separate random `QUOTE_LOOKUP_KEY` before collecting quotes and retain it privately. Keep SMTP secrets and API keys in deployment environment settings.

## Checks

Run `php artisan test`, `vendor/bin/pint --test`, `npm run typecheck`, and `npm run build`. Run the build before the tests if you have no Vite manifest. The feature tests use an in-memory SQLite database and an array mail transport.
