# Laravel 13 Starter

Laravel 13 Starter is a backend-first Laravel application template for building admin panels and API-driven products with Filament v5, Livewire v4, Tailwind CSS v4, and a broad set of production-ready integrations.

## Highlights

- Filament admin panel mounted at `/admin`
- Custom Filament login experience
- Global search, database notifications, and dark-mode defaults in the admin panel
- Octane, Horizon, Scout, Cashier, Socialite, and AI support
- Media, settings, tags, backup, activity log, and response cache integrations
- Payment and messaging building blocks for Stripe, Alipay, WeChat Pay, and QR code workflows

## Tech Stack

- PHP 8.5
- Laravel 13
- Filament v5
- Livewire v4
- Tailwind CSS v4
- Pest v4
- Laravel Boost

## Requirements

- PHP 8.5 or newer
- Composer
- Node.js and npm
- PostgreSQL by default

## Installation

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate
npm install
```

## Environment

The default environment file is configured for local development:

- `APP_URL=http://localhost`
- `DB_CONNECTION=pgsql`
- `SESSION_DRIVER=database`
- `QUEUE_CONNECTION=database`
- `CACHE_STORE=database`

Optional integrations are also present in `.env.example` for:

- Stripe
- Alipay
- WeChat Pay
- Garage / S3-compatible storage
- IPInfo

## Running the App

Start the standard local stack:

```bash
composer run dev
```

Start the RoadRunner / Octane stack:

```bash
composer run dev:rr
```

Build frontend assets:

```bash
npm run build
```

## Testing

Run the application test suite:

```bash
composer run test
```

Run k6 smoke or mini-load checks against a running local server:

```bash
pnpm run k6:smoke
pnpm run k6:mini-load
make k6-root-smoke
make k6-root-load
```

k6 由 `tests/Performance/k6/run.mjs` 使用 Node.js 加载 `tests/Performance/k6/.env` 后执行，目标地址读取 `K6_BASE_URL`。

## Project Structure

- `app/Filament/` - Filament resources, pages, and widgets
- `app/Helpers/` - application and panel configurators
- `app/Providers/Filament/` - Filament-specific service providers
- `tests/` - Pest test suite
- `tests/Performance/k6/` - k6 smoke and mini-load performance tests

## Architecture Notes

The project uses a configuration-separation approach to keep framework bootstrap files and panel providers small:

- `AppConfigurator` manages application routing, middleware, exception handling, and scheduling
- `FilamentConfigurator` centralizes panel settings, plugins, navigation, and search behavior
- `ComponentDefaultsProvider` sets global Filament component defaults

See [ARCHITECTURE.md](ARCHITECTURE.md) for details.

## Development Workflow

1. Generate framework files with `php artisan make:*`
2. Write tests before implementation when possible
3. Format PHP code with `vendor/bin/pint`
4. Keep changes small and focused

## Contributing

Contributions are welcome. Please keep pull requests focused, tested, and aligned with the existing code style.

## License

MIT
