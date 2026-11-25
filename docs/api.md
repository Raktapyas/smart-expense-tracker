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

## Auth

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| POST | `/register` | `name, email, password, password_confirmation, device_name` | 201 `{ user, token }` |
| POST | `/login` | `email, password, device_name` | 200 `{ user, token }` |
| POST | `/logout` | none | 204, revokes this device's token |
| GET | `/user` | none | 200 `{ data: { id, name, email } }` |

Store the token securely on the device (for example flutter_secure_storage).

## Categories (read-only)

| Method | Path | Result |
| --- | --- | --- |
| GET | `/categories` | `{ data: [{ id, name, type, color, icon, parent_id, is_custom }] }` |

## Accounts

| Method | Path | Body | Result |
| --- | --- | --- | --- |
| GET | `/accounts` | | `{ data: [...], meta: { total_balance } }` (active accounts only in the total) |
| POST | `/accounts` | `name, type, opening_balance, color?` | 201 account |
| PUT | `/accounts/{id}` | same as POST | account |
| PATCH | `/accounts/{id}/archive` | none | account (toggles archived) |

Account types: `cash`, `bank`, `card`, `wallet`, `savings`.
Account fields: `id, name, type, type_label, currency, opening_balance, balance, color, icon, is_archived`.

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
=== END FILE ===
