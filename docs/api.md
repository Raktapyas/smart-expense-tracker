# Smart Expense Tracker API (v1)

Base URL: `http://<host>/api/v1`

## Conventions

- JSON in, JSON out. Send `Accept: application/json` and `Content-Type: application/json`.
- Authenticate with `Authorization: Bearer <token>` (Laravel Sanctum personal access token).
- Money is always a **string** with two decimals, for example `"450.50"`. Dates are `YYYY-MM-DD`.
- Currency is NPR.
- Validation errors: HTTP 422 with `{ "message": "...", "errors": { "field": ["..."] } }`.
- `401` not signed in, `403` not yours, `404` not found, `429` too many requests.
- Rate limits: register and login 10 per minute; everything else 120 per minute.
- Resources are returned as `{ "data": ... }`. Lists may add `meta` and `totals`.

## Auth

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| POST | `/register` | `name, email, password, password_confirmation, device_name` | 201 `{ user, token }` |
| POST | `/login` | `email, password, device_name` | 200 `{ user, token }` |
| POST | `/logout` | none | 204, revokes this device's token |
| GET | `/user` | none | `{ data: { id, name, email } }` |

Store the token securely on the device (for example flutter_secure_storage).

## Dashboard

`GET /dashboard` returns `{ data }` with: `month_label, total_balance, income, expense, saved,
accounts[], category_breakdown[{name,color,total,percent}], trend[{label,income,expense}] (6 months, oldest first),
recent[] (transactions), budgets[] (top 4 by usage), over_budget_count, insights[]`.
It also creates any recurring transactions that are due.

## Insights

`GET /insights` returns `{ data: { period_label, insights[], comparison[] } }`.
An insight is `{ tone: good|info|warn|bad, icon, text, action: null | { label, target: "budgets"|"rules" } }`.
A comparison row is `{ name, current, previous, change }` (`change` is a whole percent, or null).

## Categories

| Method | Path | Result |
| --- | --- | --- |
| GET | `/categories` | `{ data: [{ id, name, type, color, icon, parent_id, is_custom }] }` |
| GET | `/categorise?merchant=...&type=income\|expense` | `{ category_id, category_name, source: rule\|history }` (nulls when no suggestion) |

## Accounts

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| GET | `/accounts` | | `{ data: [...], meta: { total_balance } }` (active accounts only in the total) |
| POST | `/accounts` | `name, type, opening_balance, color?` | 201 account |
| PUT | `/accounts/{id}` | same as POST | account |
| PATCH | `/accounts/{id}/archive` | none | account (toggles archived) |

Types: `cash`, `bank`, `card`, `wallet`, `savings`.
Fields: `id, name, type, type_label, currency, opening_balance, balance, color, icon, is_archived`.

## Transactions

| Method | Path | Body or query | Result |
| --- | --- | --- | --- |
| GET | `/transactions` | `range, type, account_id, category_id, q, per_page, page` | paginated `{ data, links, meta, totals }` |
| GET | `/transactions/{id}` | | `{ data }` |
| POST | `/transactions` | see below | 201 `{ data, meta: { auto_categorised } }` |
| PUT | `/transactions/{id}` | same as POST | `{ data }` |
| DELETE | `/transactions/{id}` | | 204 |

- `range`: `this_month` (default), `last_month`, `last_30_days`, `this_year`, `all`.
- `type`: `income`, `expense`, `transfer`. `per_page`: 1 to 100 (default 20).
- `totals` holds `income` and `expense` for the whole filtered set; transfers are not counted.
- Body: `type, account_id, amount, occurred_on, merchant?, note?, category_id?` and, for transfers,
  `transfer_account_id`. A new income or expense without `category_id` is categorised automatically from
  the user's rules and history (`meta.auto_categorised` tells you).

## Budgets (monthly, current calendar month)

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| GET | `/budgets` | | `{ data: [...], meta: { month } }` most-used first |
| POST | `/budgets` | `category_id?, amount` | 201 budget (no `category_id` = overall budget) |
| PUT | `/budgets/{id}` | same as POST | budget |
| DELETE | `/budgets/{id}` | | 204 |

Budget fields: `id, category_id, name, color, limit, spent, remaining, percent, status (ok|warning|over)`.
`warning` starts at 80% used. One budget per category and one overall budget.

## Smart rules

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| GET | `/rules` | | `{ data: [{ id, keyword, category_id, category, created_at }] }` |
| POST | `/rules` | `keyword, category_id, apply_existing?` | 201 rule, `meta.applied_to_existing` |
| DELETE | `/rules/{id}` | | 204 |

## Recurring transactions

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| GET | `/recurring` | | `{ data: [...] }` |
| POST | `/recurring` | `type, account_id, amount, frequency, starts_on, ends_on?, merchant?, note?, category_id?, transfer_account_id?` | 201 `{ data, meta: { transactions_created } }` |
| PUT | `/recurring/{id}` | `account_id, amount, ends_on?, merchant?, note?, category_id?, transfer_account_id?` | `{ data }` |
| PATCH | `/recurring/{id}/toggle` | none | `{ data }` (pause or resume) |
| DELETE | `/recurring/{id}` | | 204, transactions already created are kept |

`frequency`: `weekly`, `monthly`, `yearly`. Type, frequency and start date cannot be edited.
A start date today or earlier creates the missed transactions immediately.
Status is `Active`, `Paused` or `Ended`. Resuming an ended schedule returns 422 until `ends_on` is changed.
