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

| Method | Path | Purpose |
| --- | --- | --- |
| GET, POST | `/api/categories` | List or create categories |
| GET, PATCH, DELETE | `/api/categories/{id}` | Read, change, or delete a category |
| GET, POST | `/api/books` | List or create books |
| GET, PATCH, DELETE | `/api/books/{id}` | Read, change, or delete a book |
| GET | `/api/loans` | List books currently on loan |
| POST | `/api/loans` | Borrow one copy |
| GET | `/api/loans/{id}` | Read a loan, including returned loans |
| POST | `/api/loans/{id}/return` | Return a borrowed copy |

Create a category with `name` and optional `description`. Create a book with `category_id`, `title`, `author`, `isbn`, and `total_copies` (at least 1). The API sets `available_copies` automatically and adjusts it when total inventory changes.

## Try the borrowing flow

After migrating a fresh database, run these requests in order. The IDs are `1` in a fresh database; use the IDs returned by the API if you already have records.

```bash
curl -X POST http://127.0.0.1:8000/api/categories \
  -H 'Content-Type: application/json' \
  -d '{"name":"Software"}'

curl -X POST http://127.0.0.1:8000/api/books \
  -H 'Content-Type: application/json' \
  -d '{"category_id":1,"title":"Clean Code","author":"Robert Martin","isbn":"9780132350884","total_copies":1}'

curl -X POST http://127.0.0.1:8000/api/loans \
  -H 'Content-Type: application/json' \
  -d '{"book_id":1,"borrower_name":"Rina","borrower_email":"rina@example.com"}'

curl http://127.0.0.1:8000/api/loans
curl -X POST http://127.0.0.1:8000/api/loans/1/return
```

Borrowing with no available copy, returning an already returned loan, reducing inventory below the borrowed count, deleting a book with active loans, or deleting a category with books returns `409 Conflict`. Invalid input returns `422 Unprocessable Content`. Deleting a book after its loans are returned archives it; loan history stays readable and its ISBN remains reserved.

Authentication was left out because it is optional in the assignment. Public responses omit borrower identity, but these endpoints still allow unauthenticated changes and are intended for the technical exercise rather than a public production service.
