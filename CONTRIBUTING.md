# Contributing to You Pinterest

Thank you for your interest in contributing. You Pinterest is intentionally simple — please keep that spirit in mind.

---

## Before you open a PR

- Check existing issues to avoid duplicate work
- For non-trivial changes, open an issue first to discuss the approach
- Keep PRs focused. One feature or fix per PR

## Development setup

```bash
git clone https://github.com/yourname/your-pinterest.git
cd your-pinterest
cp .env.example .env
composer install
php artisan key:generate
php artisan migrate
npm install
npm run dev
```

## Code style

The project uses [Laravel Pint](https://laravel.com/docs/pint) for formatting:

```bash
composer lint        # format
composer lint:check  # check without writing
```

## Static analysis

```bash
composer types:check
```

## Tests

```bash
php artisan test
```

All PRs must pass existing tests. New behaviour should have test coverage.

## What we welcome

- Bug fixes
- Performance improvements
- Accessibility improvements to the public gallery UI
- Documentation improvements
- New storage provider implementations (Dropbox, S3, etc.) — via the `StorageProvider` interface

## What we politely decline

- Theme builders
- Extensive customisation settings
- Marketing landing pages
- Anything that complicates the core photographer → gallery → client workflow

---

## Architecture notes

- Business logic lives in `app/Domain/` — not in controllers or Filament resources
- The `StorageProvider` interface in `app/Domain/Google/Services/` is the only point of contact between the Gallery domain and storage
- Each photographer's data must remain isolated (always scope by `user_id`)
- Access tokens are encrypted at rest — never log or expose them
