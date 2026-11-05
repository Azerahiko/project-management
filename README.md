# Project Management Application

A modern project management application built with **Laravel 13**, **Inertia.js v3**, **Vue 3**, and **TypeScript**. Features include project/task management, team collaboration, role-based access control, real-time notifications, webhooks, and comprehensive onboarding flow.

---

## 📋 Technology Stack

| Layer               | Technology                              | Version      |
| ------------------- | --------------------------------------- | ------------ |
| **Backend**         | Laravel Framework                       | ^13.17       |
| **PHP**             | PHP                                     | ^8.3         |
| **Frontend**        | Vue 3 + TypeScript                      | ^3.5 / ^5.2  |
| **SPA Bridge**      | Inertia.js (SSR)                        | ^3.0         |
| **Authentication**  | Laravel Fortify + Sanctum               | ^1.41 / ^4.3 |
| **Authorization**   | Spatie Laravel Permission               | ^8.3         |
| **Styling**         | Tailwind CSS                            | ^4.1         |
| **Build Tool**      | Vite (via vite-plus)                    | ^1.3         |
| **Testing**         | Pest PHP                                | ^5.3         |
| **Static Analysis** | PHPStan (Larastan)                      | ^3.9         |
| **Code Style**      | Laravel Pint                            | ^1.27        |
| **Database**        | PostgreSQL (primary) / SQLite (testing) | -            |
| **Queue**           | Database driver                         | -            |
| **Cache**           | Database driver                         | -            |
| **Mail**            | Log driver (development)                | -            |

---

## 🔧 Prerequisites

Before you begin, ensure you have the following installed:

| Software       | Minimum Version         | Installation Guide                                     |
| -------------- | ----------------------- | ------------------------------------------------------ |
| **PHP**        | 8.3+                    | [php.net](https://www.php.net/downloads)               |
| **Composer**   | 2.x                     | [getcomposer.org](https://getcomposer.org/download/)   |
| **Node.js**    | 20+ (LTS)               | [nodejs.org](https://nodejs.org/)                      |
| **npm**        | 11+ (bundled with Node) | -                                                      |
| **PostgreSQL** | 14+                     | [postgresql.org](https://www.postgresql.org/download/) |
| **Git**        | 2.x                     | [git-scm.com](https://git-scm.com/downloads)           |

> **Note**: The project uses SQLite for testing (configured in `phpunit.xml`). PostgreSQL is the recommended production database as defined in `.env.example`.

---

## 📦 Installation

Follow these steps in order after cloning the repository:

### 1. Clone the Repository

```bash
git clone <repository-url>
cd project-management
```

### 2. Install PHP Dependencies

```bash
composer install
```

This installs all Laravel backend dependencies and runs post-install scripts (including `.env` creation and key generation).

### 3. Install Node.js Dependencies

```bash
npm install
```

This installs all frontend dependencies including Vue, Inertia, Tailwind CSS, and build tools.

---

## ⚙️ Environment Configuration

### 1. Create Environment File

The `composer install` script automatically copies `.env.example` to `.env` if it doesn't exist. If needed, run manually:

```bash
cp .env.example .env
```

### 2. Generate Application Key

```bash
php artisan key:generate
```

This sets `APP_KEY` in `.env` — required for encryption, sessions, and signed URLs.

### 3. Configure Database Connection

Edit `.env` and update the database credentials:

```env
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=project_management
DB_USERNAME=project_management
DB_PASSWORD=your_secure_password
```

> **Development Alternative**: For quick local setup without PostgreSQL, you can use SQLite:
>
> ```env
> DB_CONNECTION=sqlite
> DB_DATABASE=database/database.sqlite
> ```
>
> Then create the file: `touch database/database.sqlite`

### 4. Configure Other Services (Optional)

| Service     | Variables                                                                                      | Notes                                               |
| ----------- | ---------------------------------------------------------------------------------------------- | --------------------------------------------------- |
| **Mail**    | `MAIL_MAILER`, `MAIL_HOST`, `MAIL_PORT`, `MAIL_USERNAME`, `MAIL_PASSWORD`, `MAIL_FROM_ADDRESS` | Defaults to `log` driver (writes to `storage/logs`) |
| **Redis**   | `REDIS_HOST`, `REDIS_PORT`, `REDIS_PASSWORD`                                                   | Used for caching/queues if configured               |
| **Queue**   | `QUEUE_CONNECTION`                                                                             | Defaults to `database`                              |
| **Cache**   | `CACHE_STORE`                                                                                  | Defaults to `database`                              |
| **Session** | `SESSION_DRIVER`                                                                               | Defaults to `database`                              |

### 5. Frontend URL (Vite)

The `VITE_APP_NAME` variable is used in the frontend. It's pre-configured to use `APP_NAME`.

```env
VITE_APP_NAME="${APP_NAME}"
```

---

## 🗄️ Database Setup

### 1. Create Database (PostgreSQL)

```bash
# Connect to PostgreSQL
psql -U postgres

# Create database and user
CREATE DATABASE project_management;
CREATE USER project_management WITH ENCRYPTED PASSWORD 'your_secure_password';
GRANT ALL PRIVILEGES ON DATABASE project_management TO project_management;
\q
```

### 2. Run Migrations

```bash
php artisan migrate
```

This creates all 19 migration tables including:

- `users`, `projects`, `project_members`, `tasks`
- `task_comments`, `task_activities`, `notifications`
- `announcements`, `webhooks`, `invitations`, `passkeys`
- Spatie permission tables (`roles`, `permissions`, `model_has_roles`, etc.)

### 3. Seed Database (Optional but Recommended)

```bash
php artisan db:seed
```

This runs all seeders including:

- `RolePermissionSeeder` — Creates roles (`administrator`, `project_manager`) and permissions
- `AdminUserSeeder` — Creates an admin user (`admin@example.com` / `password`)
- `UserSeeder`, `ProjectSeeder`, `DummyDataSeeder` — Sample data for development

> **Default Admin Credentials** (after seeding):
>
> - Email: `admin@example.com`
> - Password: `password`

---

## 🎨 Frontend Setup & Asset Compilation

### Development (with Hot Module Replacement)

Run both the Laravel dev server and Vite dev server concurrently:

```bash
composer run dev
```

This runs:

- `php artisan dev` — Laravel server on `http://localhost:8000`
- `npm run dev` — Vite dev server on `http://localhost:5173` with HMR

### Production Build

Compile and optimize assets for production:

```bash
npm run build
```

This runs `vp build` (vite-plus) which:

- Compiles TypeScript and Vue SFCs
- Processes Tailwind CSS
- Generates optimized bundles in `public/build/`
- Creates SSR bundle for Inertia server-side rendering

### Type Checking

```bash
npm run types:check
```

Runs `vue-tsc --noEmit` for TypeScript type validation.

### Linting (Frontend)

```bash
npm run check        # Check for issues
npm run check:fix    # Auto-fix issues
```

Uses `vp check` (vite-plus built-in linter).

---

## 🚀 Running the Application

### Development Mode

```bash
composer run dev
```

Access the application at **http://localhost:8000**

### Production Mode

1. Build assets: `npm run build`
2. Configure web server (Nginx/Apache) to serve `public/` directory
3. Set `APP_ENV=production` and `APP_DEBUG=false` in `.env`
4. Run: `php artisan config:cache && php artisan route:cache && php artisan view:cache`

> **Important**: In production, ensure `APP_KEY` is set and kept secret. Run `php artisan key:generate --force` if needed.

---

## ⚡ Queue Workers & Background Services

The application uses the **database queue driver** for:

- Notification delivery (via `NotificationSender` service)
- Webhook dispatching (via `WebhookDispatcher` service)
- Other async jobs

### Start Queue Worker

```bash
php artisan queue:work
```

For production, use a process manager (Supervisor, systemd, etc.):

```bash
# Example Supervisor config
[program:laravel-queue]
command=php artisan queue:work --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/log/laravel-queue.log
```

### Other Useful Queue Commands

| Command                       | Description                   |
| ----------------------------- | ----------------------------- |
| `php artisan queue:listen`    | Listen for jobs (development) |
| `php artisan queue:failed`    | List failed jobs              |
| `php artisan queue:retry all` | Retry all failed jobs         |
| `php artisan queue:clear`     | Clear all jobs                |

---

## 🧪 Running Tests

### Full Test Suite

```bash
php artisan test
```

Or via Composer script (includes lint + static analysis):

```bash
composer test
```

This runs:

1. `php artisan config:clear`
2. `pint --parallel --test` (code style check)
3. `phpstan analyse` (static analysis)
4. `php artisan test` (Pest tests)

### Test Types

| Command                                                    | Description            |
| ---------------------------------------------------------- | ---------------------- |
| `php artisan test --filter=Feature`                        | Run feature tests only |
| `php artisan test --filter=Unit`                           | Run unit tests only    |
| `php artisan test tests/Feature/ProjectManagementTest.php` | Run specific test file |
| `php artisan test --compact`                               | Compact output format  |

### Testing Database

Tests use **SQLite in-memory** database (configured in `phpunit.xml` with `APP_ENV=testing`). No separate test database setup required.

---

## ✅ Code Quality Checks

### PHP Code Style (Laravel Pint)

```bash
# Check for style violations
composer run lint:check
# or
vendor/bin/pint --parallel --test

# Auto-fix style violations
composer run lint
# or
vendor/bin/pint --parallel
```

### Static Analysis (PHPStan / Larastan)

```bash
composer run types:check
# or
vendor/bin/phpstan analyse
```

Configuration in `phpstan.neon` (if present) or default Larastan rules.

### Frontend Linting & Types

```bash
npm run check        # Lint check
npm run check:fix    # Lint auto-fix
npm run types:check  # TypeScript type check
```

### Pre-commit / CI Checks

The `ci:check` script runs the full validation pipeline:

```bash
composer run ci:check
```

---

## 🛠️ Optional: Laravel Boost

Laravel Boost is installed as a development dependency (`laravel/boost ^2.2`) and provides AI-assisted development tools for Laravel projects.

### Installation (Optional)

```bash
php artisan boost:install
```

- This step is **optional** and not required to run the application.
- It configures Boost's MCP server integration for enhanced AI-assisted development.
- The `composer.json` includes a `post-update-cmd` hook that runs `php artisan boost:update` automatically after dependency updates.

> **Note**: Laravel Boost is a developer productivity tool. The application runs fully without it.

---

## 🐛 Troubleshooting

### Common Setup Issues

| Issue                                                  | Solution                                                                                                                                                           |
| ------------------------------------------------------ | ------------------------------------------------------------------------------------------------------------------------------------------------------------------ |
| **"No application encryption key has been specified"** | Run `php artisan key:generate`                                                                                                                                     |
| **Database connection refused**                        | Verify PostgreSQL is running and credentials in `.env` are correct. Check `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME`, `DB_PASSWORD`.                       |
| **Migration fails: "relation already exists"**         | Database may have existing tables. Run `php artisan migrate:fresh` (⚠️ destroys data) or manually drop tables.                                                     |
| **Vite dev server not connecting**                     | Ensure port 5173 is free. Check `vite.config.ts` server config. Try `npm run dev` separately to see errors.                                                        |
| **"npm run dev" fails with "vp: not found"**           | Run `npm install` first. `vp` is provided by `vite-plus` package.                                                                                                  |
| **TypeScript errors in IDE but not in build**          | Restart TypeScript server in your IDE. Run `npm run types:check` to verify.                                                                                        |
| **Pest tests fail with "database locked"**             | Ensure no other process is using the SQLite test database. Tests run in parallel by default; the project disables parallel execution in `pint.json` / test config. |
| **Permission denied on storage/ or bootstrap/cache/**  | Run: `chmod -R 775 storage bootstrap/cache` and ensure web server user owns these directories.                                                                     |
| **Mail not sending in production**                     | Configure `MAIL_MAILER=smtp` (or your provider) with valid credentials in `.env`.                                                                                  |
| **Webhook delivery fails**                             | Check `webhooks` table for `last_delivery_error`. Ensure target URL is accessible and accepts POST with JSON.                                                      |
| **Inertia SSR not working**                            | Ensure `npm run build` was run (creates SSR bundle). Check `resources/js/ssr.ts` exists.                                                                           |

### Useful Debug Commands

```bash
# Clear all caches
php artisan optimize:clear

# View routes
php artisan route:list

# View config
php artisan config:show

# Check environment
php artisan about

# Tinker (REPL)
php artisan tinker

# View logs
tail -f storage/logs/laravel.log
```

---

## 📁 Project Structure Overview

```
project-management/
├── app/
│   ├── Actions/Fortify/       # Fortify authentication actions
│   ├── Enums/                 # PHP enums (ProjectStatus, TaskPriority, etc.)
│   ├── Http/
│   │   ├── Controllers/       # Web + API controllers
│   │   ├── Middleware/        # Custom middleware
│   │   ├── Requests/          # Form requests (validation + auth)
│   │   ├── Resources/         # API resources (JSON transformers)
│   │   └── Responses/         # Custom HTTP responses
│   ├── Mail/                  # Mailables
│   ├── Models/                # Eloquent models
│   ├── Notifications/         # Notification classes
│   ├── Policies/              # Authorization policies
│   ├── Providers/             # Service providers
│   ├── Services/              # Business logic services
│   └── Support/               # Helper classes
├── bootstrap/
├── config/                    # Configuration files
├── database/
│   ├── factories/             # Model factories
│   ├── migrations/            # Database migrations
│   └── seeders/               # Database seeders
├── public/                    # Web root (entry point)
├── resources/
│   ├── css/                   # Global styles
│   ├── js/
│   │   ├── components/        # Vue components (UI, layout, features)
│   │   ├── composables/       # Vue composables (useProjects, useToast, etc.)
│   │   ├── layouts/           # Inertia layouts
│   │   ├── lib/               # Utilities (api client, helpers)
│   │   ├── pages/             # Inertia page components (routes)
│   │   ├── routes/            # Wayfinder-generated route helpers
│   │   ├── types/             # TypeScript interfaces
│   │   ├── app.ts             # Vue app entry
│   │   └── ssr.ts             # SSR entry
│   └── views/                 # Blade templates (minimal)
├── routes/
│   ├── web.php                # Web routes (Inertia)
│   ├── api.php                # API routes (Sanctum)
│   └── console.php            # Artisan commands
├── storage/                   # Logs, cache, uploads
├── tests/
│   ├── Feature/               # Feature tests
│   └── Pest.php               # Pest configuration
├── vendor/                    # Composer dependencies
├── .env.example               # Environment template
├── composer.json              # PHP dependencies
├── package.json               # Node dependencies
├── phpunit.xml                # Test configuration
├── pint.json                  # Pint configuration
├── vite.config.ts             # Vite configuration
└── README.md                  # This file
```

---

## 📄 License

This project is open-sourced software licensed under the **MIT License**.

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch: `git checkout -b feature/your-feature`
3. Make your changes
4. Run quality checks: `composer run ci:check`
5. Commit: `git commit -m "feat: your feature description"`
6. Push: `git push origin feature/your-feature`
7. Open a Pull Request

Please ensure all tests pass and code style checks pass before submitting.
