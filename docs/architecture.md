# Architecture

How the app is put together, and why. For setup see the [README](../README.md).

## Overview

One Laravel application serves both the JSON API and the Vue single-page app, from the same origin.

```
Browser ── GET /anything ──────────► web.php catch-all ──► app.blade.php (Vite bundle)
   │
   ├── POST /register, /login, /logout ──► web.php (session + CSRF)
   ├── /api/* ───────────────────────────► api.php (auth:sanctum)
   ├── /broadcasting/auth ───────────────► channels.php (private channel checks)
   └── ws://:8080 ───────────────────────► Reverb ◄── queue worker ◄── broadcast events
```

- `routes/web.php` renders the same Blade view for every path except `api/` and `docs/`. Vue Router takes over in the browser.
- Same origin means no CORS, no proxy and no tokens in JavaScript: the session cookie does the work.

## Backend layers

A request goes **Controller → FormRequest → Service → Repository → Model** and leaves through an **API Resource**.

| Layer | Responsibility | Example |
|---|---|---|
| Route | Authentication and authorization (`auth:sanctum`, `->can(...)`) | `->can('review', 'proposal')` |
| FormRequest | Input validation | `StoreProposalRequest`: PDF up to 4 MB, at most 10 tags |
| Controller | Thin glue: validated input in, Resource out, HTTP status codes | `ProposalController@store` returns 201 |
| Service | Business rules and writes | `ProposalService::create`: file, transaction, tags, event |
| Repository | Read queries: filters, search, pagination | `ProposalRepository::paginate` |
| Model | Relations, casts, defaults, the search index shape | `Proposal::toSearchableArray()` indexes only the title |
| Resource | The JSON contract, including what a role may see | `reviews_count` only for reviewers and admins |

Why split reads and writes this way:

- Every filter of the proposal list lives in one query builder chain, so it is easy to review and to test.
- Writes are short Eloquent calls; wrapping them in a repository would add a layer without removing any code.
- There are no repository interfaces. There is one implementation and no plan to swap the ORM, so an interface would only add a file.

`tests/Feature/ArchitectureTest.php` enforces the boundaries:

- repositories are used only by services, and services only by controllers;
- controllers never use the `DB` facade;
- form requests, policies, resources and enums follow Laravel naming;
- `dd`, `ddd` and `env()` are not used in application code (config files read the environment, code reads config);
- Pest's `php` and `security` presets ban functions such as `var_dump`, `eval` and `md5`.

`Model::shouldBeStrict()` is on outside production, so a lazy-loading N+1 query or a typo in an attribute name fails loudly in development and tests.

## Data model

| Table | Notes |
|---|---|
| `users` | `role` is a string cast to the `Role` enum: `speaker`, `reviewer`, `admin` |
| `proposals` | Belongs to a speaker. `status` is cast to `ProposalStatus` and defaults to `pending` in both the migration and the model. `file_path` is nullable |
| `tags` | Unique `name`. Created on demand with `firstOrCreate` |
| `proposal_tag` | Composite primary key, so a tag cannot be attached twice |
| `reviews` | Unique `(proposal_id, user_id)`: one review per reviewer per proposal, enforced by the database and not only by code |

Foreign keys cascade on delete. The maximum rating lives in `config/reviews.php` (5); the validation rule and the SPA both read it from there.

## Authentication

Sanctum in SPA mode: a session cookie plus CSRF protection, no API tokens.

- **Why not tokens.** A token in `localStorage` can be read by any script injected through XSS. An `HttpOnly` session cookie cannot.
- **CSRF.** Laravel sets the `XSRF-TOKEN` cookie, and axios sends it back in the `X-XSRF-TOKEN` header. The session cookie is `SameSite=Lax`.
- **Where the auth routes live.** `POST /register`, `/login` and `/logout` are in `web.php`, not `api.php`. The web group always has a session. `/api/*` gets one from Sanctum only when the request carries a `Referer` or `Origin` of the SPA domain, so API clients without it (Postman, curl) failed on login.
- **Session hygiene.** Login and register regenerate the session ID, which prevents session fixation. Logout invalidates the session and rotates the CSRF token.
- **Brute force.** Register and login are limited to 6 requests per minute per IP (`throttle:6,1`).
- **Registration.** The role is picked on the registration form, as the brief asks. In a real system administrator accounts would be invite-only.

## Authorization

All rules are in `ProposalPolicy` and are attached to routes with `->can()`. Controllers never check roles by hand.

| Action | Speaker | Reviewer | Admin |
|---|---|---|---|
| List proposals | own only | all | all |
| View a proposal and download its PDF | own only | all | all |
| Submit a proposal | ✓ | | |
| See reviews and the review count | | ✓ | ✓ |
| Rate and comment | | ✓ | |
| Change the status | | | ✓ |

- Speakers see the status of their proposals but not the reviews. Like real CFP systems, this keeps reviewers honest and anonymous, and the decision belongs to the admin.
- Admins decide but do not rate, so the scores stay the reviewers' opinion.
- The list is scoped in `ProposalService::listFor`, not filtered after loading, so pagination counts are correct for each role.
- A speaker opening someone else's proposal gets 403. Hiding the ID would not add protection: the policy is the protection.
- The Vue Router guards (`meta.guest`, `meta.roles`) only improve the UX. The API enforces every rule on its own.

## Validation and errors

- Every input goes through a FormRequest. Enums are validated with `Rule::enum`.
- The file rule is `File::types(['pdf'])->max(4 * 1024)`. The size is in kilobytes, and the `'4mb'` string shortcut means 4000 KB, not 4096; a test covers the boundary.
- `bootstrap/app.php` renders exceptions as JSON for `api/*` and for requests that expect JSON, so the SPA never receives an HTML error page.
- On the frontend:
  - `422` errors appear next to the fields (`validationErrors()` in `http.ts`);
  - one axios interceptor in `app.ts` handles the rest: `401`/`419` (session expired) sends the user to login, `429` and `5xx` or a network failure show a banner.

## Files

- PDFs are stored on the `local` disk (`storage/app/private`), which is not web-accessible. Its `serve` option is off, so Laravel does not expose signed file routes either.
- `GET /api/proposals/{id}/file` runs the same `view` policy as the proposal page. It streams the file under a readable name such as `12-my-talk.pdf`.
- A missing file returns 404, not 500.
- `ProposalService::create` stores the file first, then creates the proposal and its tags in one transaction. If the transaction fails, the file is deleted, so no orphan files are left behind.

## Search and filtering

- **Title search** uses Laravel Scout. `Proposal::search($q)->take(1000)->keys()` returns matching IDs, and `whereKey(...)` feeds them into the normal Eloquent query.
  - The other filters, role scoping, eager loading and pagination therefore stay in SQL, the same for every search driver.
  - `take(1000)` is needed because Algolia returns 20 hits by default.
- **Drivers:**
  - `database` works out of the box (`LIKE`);
  - `algolia` gives typo-tolerant search when keys are set;
  - tests use `collection`.
- **Tag filter:** a proposal matches if it has **any** of the selected tags.
- **Reviewer tabs** All / To review / Reviewed filter by whether the current reviewer has already reviewed the proposal (`whereHas` / `whereDoesntHave`).
- The list is ordered newest first and paginated by 10, and `withQueryString()` keeps the filters in the pagination links.

## Real-time updates

Three events implement `ShouldBroadcast`. They go through the queue (the `queue` container), and the queue sends them to Reverb, which pushes them to browsers over WebSockets.

| Event | Fired when | Private channel | Who listens |
|---|---|---|---|
| `ProposalSubmitted` | a speaker submits a proposal | `reviewers` | reviewers |
| `ProposalReviewed` | a review is created or actually changed | `admins` | admins |
| `ProposalStatusChanged` | the status actually changes | `App.Models.User.{id}` | the owning speaker |

- **Channel access.** `routes/channels.php` decides who may join a channel. Echo asks `/broadcasting/auth`, Laravel checks the session, and the server signs the subscription with the Reverb secret.
- **No duplicate events.** None is sent when nothing changed: `wasChanged('status')`, or `wasRecentlyCreated || wasChanged()` for reviews.
- **Small payloads.** An event carries only an ID, the title and a name. The browser shows a toast and refetches the open page through the normal API, so policies still decide what each user sees and private data never travels in a broadcast.
- **Why queued, not `ShouldBroadcastNow`.** Saving a review should not wait on, or fail because of, the WebSocket server.

## Frontend

```
resources/js/
  app.ts          bootstrap: Pinia, axios interceptor, load the current user, mount
  router.ts       routes and guards
  http.ts         axios instance, global error banner, 422 helper
  realtime.ts     Echo connection per role and an onProposalChange() hook for pages
  stores/         auth (current user) and notifications (toasts)
  pages/          one component per route
  components/     TagInput, ReviewForm, Pagination, StatusBadge, TextInput, NotificationToasts
```

- Vue 3 Composition API with `<script setup>` and TypeScript. Types for the API shapes are in `types.ts`.
- The current user is loaded once (`GET /api/me`) before the app mounts, so guards never see a half-loaded state.
- `<RouterView :key="route.path">` remounts a page when only the ID in the URL changes, for example after clicking a notification for another proposal.
- The list page ignores out-of-order responses: a fast search followed by a slow one cannot show stale results.
- Tailwind CSS with a mobile-first layout. Tables and the segmented controls stay usable at phone width.

## Testing

- **Feature tests** go through HTTP, as the SPA does. They cover auth, every policy rule per role, validation edge cases, file storage, search, tag filtering, review upsert, status changes, the seeder, event payloads and channel authorization.
- **Architecture tests** check the layering rules above.
- **Environment.** A separate MySQL database `testing` with `RefreshDatabase`, the Scout `collection` driver and the `null` broadcaster. Tests never touch Algolia or Reverb.
- **Coverage.** 100% line coverage of `app/` (`sail test --coverage`).

## Deliberately left out

Outside the brief, and each would add code without adding much to the review:

- Editing or deleting a proposal after submission, and withdrawing a review.
- Email verification, password reset, and admin management of users and roles.
- A tag management screen: tags are created on demand.
- Notification history: toasts are live only and are not stored.
- Frontend unit tests: the behaviour is covered at the API level, and the UI was checked by hand in the browser.
