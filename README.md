# Check-in with History

A single Flarum 1.x extension for daily check-in, automatic check-in, the profile history calendar, supplementary check-in, and check-in cards.

Package: `primexcalibur/checkin-with-history`
Extension ID: `primexcalibur-checkin-with-history`
Requires PHP 8.0+, Flarum 1.8+, and `huoxin/money-with-history` 1.2.4+ (1.x).

## Install

Add the GitHub repository to Composer and install the release:

```sh
composer config repositories.checkin-with-history vcs git@github.com:primexcalibur/flarum-ext-checkin-with-history.git
composer require primexcalibur/checkin-with-history:^1.0 -W
php flarum extension:enable huoxin-money-with-history
php flarum extension:enable primexcalibur-checkin-with-history
php flarum migrate
php flarum assets:publish
php flarum cache:clear
```

The settings page includes daily rewards, timezone, automatic check-in and prompts, supplementary rules, card issuance, calendar colors, and the existing optional calendar CDN configuration. FullCalendar assets are bundled locally by default.

## Upgrade from the original extensions

Back up the forum database. Disable installed legacy extensions, **without using their Purge button**: their original rollback migrations delete shared tables or columns.

```sh
php flarum extension:disable mattoid-daily-check-in-history
php flarum extension:disable ziiven-daily-check-in
composer remove ziiven/flarum-daily-check-in mattoid/flarum-daily-check-in-history --no-update
```

Omit commands/packages for a legacy extension that is not installed. Then follow the installation commands above. The merged package replaces both old Composer packages, so their PHP classes and frontend bundles are not loaded twice. Sites still using the original Money extension should follow Money with History's migration instructions first.

The migration supports fresh installations, daily-check-in-only installations, and installations with both extensions. It preserves:

- `users.total_checkin_count`, `users.total_continuous_checkin_count`, `users.last_checkin_time`, and `users.checkin_card`.
- Existing `user_checkin_history` rows, their IDs and types, including legacy duplicates.
- Existing `ziven-forum-checkin.*` and `mattoid-forum-checkin.*` settings and `checkin.*` permissions.

If no daily history was recorded, only the known last check-in is imported. Earlier daily records cannot be reconstructed from aggregate counts. Existing history timestamps are left unchanged. New history entries store the operation time in UTC; check-in dates and the legacy user timestamp follow the configured forum offset.

The merged migration is repeatable and its rollback preserves shared data. Legacy migration ownership rows remain intact. Do not reinstall and purge an old extension after migrating: its own purge code can still delete shared data.

## Behavior and compatibility

Normal and supplementary check-in lock the same user row. Card consumption, balance changes, counters, and daily history are committed together. A date can be counted only once across both entry points. Repairing an old streak updates connected history without replacing a newer, separate current streak.

Existing API routes remain available:

| Method | Route | Purpose |
|---|---|---|
| POST | `/api/checkin` | Normal check-in |
| GET | `/api/checkin/history` | History by user and date range |
| POST | `/api/supplement/checkin` | Supplement a date |
| POST | `/api/give/checkin/card` | Issue cards |

## Architecture

All PHP code lives under `Primexcalibur\Checkin`. Daily, automatic and supplementary check-in enter `Service\CheckInService`, which owns the user lock and transaction, creates a `Model\CheckinRecord`, applies balance/card changes and updates totals and streaks. The history API reads those records. `Service\CheckinCardService` handles card issuance separately.

Both successful operations dispatch `Event\CheckedIn` after their transaction succeeds. The event contains `user`, `record` and `supplementCount` (zero for normal check-in). Third-party PHP integrations should use this event; the old extensions' PHP classes and events are not exported. The HTTP routes and stored data remain compatible.

The forum and admin each have one initializer. Both check-in actions update Flarum's shared user and check-in record models, so the button, profile and calendar reflect the same state. The admin has one settings page. Legacy database columns and settings keys are intentionally retained; aliases preserve descriptions already stored in the money ledger.

## Updating the earlier combined package

If `primexcalibur/checkin-with-history` is already installed from the previous repository, update the Composer repository URL using the installation command above and require `^1.0`. Its extension ID and migration ID remain unchanged, so existing records, settings and migration ownership are retained. Then run `php flarum migrate`, `php flarum assets:publish` and `php flarum cache:clear`.

## Development

```sh
composer install
composer test:setup
composer test
cd js
npm ci
npm run format-check
npm run check-typings
npm run build
```

Before `test:setup`, configure `DB_HOST`, `DB_PORT`, `DB_DATABASE`, `DB_USERNAME` and `DB_PASSWORD` for a **dedicated test database**. Flarum's standard setup clears that database. Tests use `flarum/testing` and MySQL, including API flows and upgrades from legacy schemas. Commit rebuilt `js/dist` files when frontend sources change.

## Sources and licenses

This package incorporates:

- [invites-fun/flarum-daily-check-in](https://github.com/invites-fun/flarum-daily-check-in), revision `d78651d`, originally by Ziven, under [MIT](LICENSE.md).
- [huoxin233/flarum-daily-check-in-history](https://github.com/huoxin233/flarum-daily-check-in-history/tree/huoxin/architecture-refactor), revision `41d1b36`, originally by mattoid and refactored by huoxin, under [LPL-1.02](LICENSE-HISTORY.md).

Both licenses and the original author attribution are retained; see [NOTICE](NOTICE). Bundled FullCalendar files retain their own MIT notices.
