# Talk Proposals

A talk proposal review app: speakers submit talk proposals, reviewers rate them, administrators decide.
Laravel 13 JSON API with a Vue 3 single-page app, running entirely in Docker through Laravel Sail.

Design decisions, permissions and trade-offs are described in [docs/architecture.md](docs/architecture.md).
Production build and deployment are covered in [docs/deployment.md](docs/deployment.md).

## Features

- **Accounts and roles.** Registration with a role (Speaker, Reviewer, Administrator) and session login through Sanctum in SPA mode.
- **Submission.** Speakers submit a title, description, tags (pick existing or create new) and an optional PDF up to 4 MB, kept on a private disk.
- **Review.** Reviewers see every proposal, rate it from 1 to 5 and leave a comment. One review per reviewer, editable. To review / Reviewed tabs.
- **Decision.** Administrators set the status: pending, approved or rejected. Speakers see the status of their own proposals, not the reviews.
- **List.** Filter by tags (matches any selected tag), search by title, pagination, responsive layout.
- **Search.** Title search through Laravel Scout: Algolia when keys are set, a database fallback otherwise.
- **Real-time.** WebSocket notifications through Laravel Reverb and Echo when a proposal is submitted, reviewed or its status changes. Open pages refresh on their own.
- **API documentation.** OpenAPI generated from the code with Scramble.
- **Tests.** Pest feature and architecture tests with full line coverage.

## Tech stack

| Layer | Tools |
|---|---|
| Backend | PHP 8.5, Laravel 13, MySQL 8.4, Sanctum, Scout + Algolia, Reverb, Scramble |
| Frontend | Vue 3, TypeScript, Vue Router, Pinia, axios, Tailwind CSS 4, Vite, Laravel Echo |
| Tooling | Laravel Sail (Docker), Pest, Pint |

## Requirements

Docker Desktop, or Docker Engine with Compose v2. PHP, Composer and Node run inside the containers, so nothing else is needed on the host.
The shortcuts below use `make`, which ships with Linux and with the Xcode Command Line Tools on macOS; every step also has a plain command.

The app uses these host ports: `80` (web), `3306` (MySQL), `5173` (Vite dev server) and `8080` (Reverb WebSockets).
If one is taken, override it in `.env` with `APP_PORT`, `FORWARD_DB_PORT`, `VITE_PORT` or `REVERB_PORT`.

## Quick start

```bash
git clone <repository-url> talk-proposals
cd talk-proposals
make setup
```

`make setup` copies `.env.example` to `.env`, installs PHP dependencies with a throwaway Composer container (Sail itself lives in `vendor/`), starts the containers, generates the app key, migrates and seeds the database and builds the frontend.
Running it again is safe: it skips the steps that are already done and never re-seeds an existing database.

Without `make`, run the same steps by hand:

```bash
cp .env.example .env
docker run --rm -u "$(id -u):$(id -g)" -v "$(pwd):/var/www/html" -w /var/www/html \
    laravelsail/php84-composer:latest composer install --ignore-platform-reqs
./vendor/bin/sail up -d --wait
./vendor/bin/sail artisan key:generate
./vendor/bin/sail artisan migrate --seed
./vendor/bin/sail npm install
./vendor/bin/sail npm run build
```

Open http://localhost. The first `sail up` builds the application image and takes a few minutes; `--wait` returns once MySQL is ready, so the migration does not race the database start-up.

`sail up` starts four containers:

| Container | Purpose |
|---|---|
| `laravel.test` | the app and API on port 80 |
| `mysql` | the database |
| `reverb` | the WebSocket server on port 8080 |
| `queue` | the queue worker that sends real-time events |

Tip: `alias sail='sh $([ -f sail ] && echo sail || echo vendor/bin/sail)'` lets you type `sail` instead of `./vendor/bin/sail`.

## Demo accounts

Every seeded user has the password `password`.

| Role | Email |
|---|---|
| Speaker | `speaker@example.com` |
| Reviewer | `reviewer@example.com` |
| Administrator | `admin@example.com` |

The seeder also creates a few more speakers and reviewers, 15 proposals, tags and reviews.
To see real-time notifications, log in as two roles in two browser profiles (for example, a normal and a private window).

## Everyday commands

```bash
make up        # start the containers
make dev       # Vite with hot reload; keep using http://localhost
make test      # Pest test suite
make fresh     # reset the demo data
make logs      # watch WebSocket and queue activity
make down      # stop the containers (data is kept in a Docker volume)
make           # list all shortcuts
```

Anything else goes through Sail directly, for example:

```bash
sail test --coverage      # tests with a line coverage report
sail pint                 # code style
```

## Search with Algolia (optional)

Out of the box `SCOUT_DRIVER=database` searches titles with SQL. For typo-tolerant search:

1. Create a free Algolia application and copy its Application ID and Admin API key.
2. Set them in `.env`:
   ```
   SCOUT_DRIVER=algolia
   ALGOLIA_APP_ID=your-app-id
   ALGOLIA_SECRET=your-admin-api-key
   ```
3. Index the existing proposals: `sail artisan scout:import "App\Models\Proposal"`.

New and changed proposals are indexed automatically. Tests always use the in-memory `collection` driver.

## API documentation

With the app running locally, open http://localhost/docs/api for interactive docs, or http://localhost/docs/api.json for the OpenAPI document.
They are available only when `APP_ENV=local`.

The API authenticates with the Laravel session cookie (Sanctum SPA mode): call `POST /login`, then send the `XSRF-TOKEN` cookie back in the `X-XSRF-TOKEN` header on non-GET requests.

## Project layout

```
app/
  Enums/          Role, ProposalStatus
  Events/         broadcast events (submitted, reviewed, status changed)
  Http/           controllers, form requests, API resources
  Models/         User, Proposal, Tag, Review
  Policies/       ProposalPolicy (who can view, create, review, change status)
  Repositories/   query logic: filters, search, pagination
  Services/       business rules: create, review, change status
docs/             architecture and deployment notes
resources/js/     Vue SPA (pages, components, Pinia stores, router, realtime)
routes/           web.php (SPA + session auth), api.php, channels.php
tests/Feature/    Pest tests, including architecture rules
```
