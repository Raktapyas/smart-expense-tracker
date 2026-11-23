# Smart Expense Tracker

Laravel + Blade + Tailwind + Alpine.js. SQLite locally. A Flutter app will
consume a JSON API later, so keep business logic out of controllers and views.

## Rules
- Money: `decimal(14,2)` columns, never float. Format amounts only in Blade components.
- Business logic goes in `app/Actions` and `app/Services`; controllers stay thin.
- Validate with Form Requests. Authorize with Policies; users only see their own data.
- Use PHP enums in `app/Enums` for types (income/expense/transfer, account types).
- Never edit a migration that has already been run; create a new one.
- Do not install packages or change config without asking first.
- Reuse components in `resources/views/components` before writing new markup.

## Design
Modern, premium finance app: dark-friendly, one accent colour, soft cards,
generous spacing, tabular numerals for amounts. Use the CSS variables in
`resources/css/app.css`; never hard-code colours.

## Commands
- Dev: `composer run dev`
- Test: `php artisan test`
- Migrate: `php artisan migrate`
