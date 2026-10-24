# Bookstore API

REST API for a small book lending catalog, built with Laravel 13 and PHP 8.3+.

## Run locally

```bash
composer install
cp .env.example .env
php artisan key:generate
touch database/database.sqlite
php artisan migrate
php artisan serve
```

The supplied `.env.example` already selects SQLite. Use `php artisan test --compact` to run the tests. No frontend build is needed for the API.

## Endpoints

All endpoints use the `/api` prefix and return JSON. List endpoints are paginated (15 records per page).

The interactive API reference is available at `/docs`. The machine-readable OpenAPI specification is at `/openapi.json`.

| Method | Path | Access | Purpose |
| --- | --- | --- | --- |
| POST | `/api/auth/register` | Public | Register with the `user` role |
| POST | `/api/auth/login` | Public | Get a bearer token |
| GET | `/api/auth/me` | Bearer token | Read the current account |
| POST | `/api/auth/logout` | Bearer token | Revoke the current token |
| GET | `/api/categories`, `/api/categories/{id}` | Public | Browse categories |
| POST | `/api/categories` | Admin | Create a category |
| PUT, PATCH, DELETE | `/api/categories/{id}` | Admin | Change or delete a category |
| GET | `/api/books`, `/api/books/{id}` | Public | Browse books |
| POST | `/api/books` | Admin | Create a book |
| PUT, PATCH, DELETE | `/api/books/{id}` | Admin | Change or archive a book |
| GET | `/api/loans` | Bearer token | List your active loans; admins see all |
| POST | `/api/loans` | Bearer token | Borrow one copy |
| GET | `/api/loans/{id}` | Owner or admin | Read a loan, including returned loans |
| POST | `/api/loans/{id}/return` | Owner or admin | Return a borrowed copy |

Create a category with `name` and optional `description`. Create a book with `category_id`, `title`, `author`, `isbn`, and `total_copies` (at least 1). The API sets `available_copies` automatically and adjusts it when total inventory changes.

## Try the borrowing flow

After migrating a fresh database, run these commands in order. Registration always creates a `user`. Promote the catalog manager locally; clients cannot assign their own role. Tokens expire after seven days and logout revokes the current token.

```bash
DEMO_PASSWORD=$(php -r 'echo bin2hex(random_bytes(16));')
ADMIN_TOKEN=$(curl -fsS -X POST http://127.0.0.1:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d "{\"name\":\"Admin\",\"email\":\"admin@example.com\",\"password\":\"$DEMO_PASSWORD\",\"password_confirmation\":\"$DEMO_PASSWORD\"}" \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"];')
php artisan users:set-role admin@example.com admin --no-interaction

USER_TOKEN=$(curl -fsS -X POST http://127.0.0.1:8000/api/auth/register \
  -H 'Content-Type: application/json' \
  -d "{\"name\":\"Rina\",\"email\":\"rina@example.com\",\"password\":\"$DEMO_PASSWORD\",\"password_confirmation\":\"$DEMO_PASSWORD\"}" \
  | php -r 'echo json_decode(stream_get_contents(STDIN), true)["token"];')

curl -X POST http://127.0.0.1:8000/api/categories \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"name":"Software"}'

curl -X POST http://127.0.0.1:8000/api/books \
  -H "Authorization: Bearer $ADMIN_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"category_id":1,"title":"Clean Code","author":"Robert Martin","isbn":"9780132350884","total_copies":1}'

curl -X POST http://127.0.0.1:8000/api/loans \
  -H "Authorization: Bearer $USER_TOKEN" \
  -H 'Content-Type: application/json' \
  -d '{"book_id":1}'

curl -H "Authorization: Bearer $USER_TOKEN" http://127.0.0.1:8000/api/loans
curl -X POST -H "Authorization: Bearer $USER_TOKEN" http://127.0.0.1:8000/api/loans/1/return
```

The IDs are `1` in a fresh database; use IDs from the responses if you already have records. Borrower name and email are taken from the authenticated account, not the request. An ordinary user cannot view or return another user's loan; the API responds with `404` for those requests. Existing loans without an owner remain visible to admins.

Borrowing with no available copy, returning an already returned loan, reducing inventory below the borrowed count, deleting a book with active loans, or deleting a category with books returns `409 Conflict`. Invalid input returns `422 Unprocessable Content`. Deleting a book after its loans are returned archives it; loan history stays readable and its ISBN remains reserved.
