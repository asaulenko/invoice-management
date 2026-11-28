# Invoice API

A Laravel 13 / PHP 8.5 API for creating invoices and tracking notification delivery.

The main architectural decisions and trade-offs can be found [here](docs/decisions.md).

## Setup

Requirements: Docker with Docker Compose, PHP 8.5 and Composer.

```bash
composer install
./start.sh
```

The initial Composer install supplies the Sail runtime needed to build the container. `start.sh` creates `.env` from `.env.example` if missing, creates the SQLite database file, starts the application and Redis, installs dependencies, and runs migrations. **Each run resets the database** (`migrate:fresh --seed`).

The application is available at `http://localhost` (override `APP_PORT` in `.env` if needed). SQLite stores invoices.

```bash
docker compose exec app bash               # Open a container shell
docker compose exec app php artisan test   # Run the test suite
docker compose down                       # Stop the services
```

## API

All API routes use the `/api` prefix and require no authentication. Send `Accept: application/json`; JSON request bodies also require `Content-Type: application/json`.

### Create an invoice — `POST /api/invoices`

Creates a `draft` invoice with an automatically generated UUID.

```bash
curl -X POST http://localhost/api/invoices \
  -H 'Accept: application/json' \
  -H 'Content-Type: application/json' \
  -d '{
    "customer_name": "Jane Doe",
    "customer_email": "jane@example.com",
    "product_lines": [
      {"product_name": "Keyboard", "quantity": 2, "unit_price": 150}
    ]
  }'
```

Returns **201 Created**:

```json
{
  "id": "550e8400-e29b-41d4-a716-446655440000",
  "status": "draft",
  "customer_name": "Jane Doe",
  "customer_email": "jane@example.com",
  "product_lines": [
    {
      "product_name": "Keyboard",
      "quantity": 2,
      "unit_price": 150,
      "total_unit_price": 300
    }
  ],
  "total_price": 300
}
```

`customer_name` and a valid `customer_email` are required (maximum 255 characters each). `product_lines` is optional and may be an empty array. Each line requires `product_name` (maximum 255 characters), plus `quantity` and `unit_price` as JSON integers from 1 to 2,147,483,647. Totals are calculated automatically; an empty invoice totals 0. Invalid input or a total exceeding PHP's integer range returns **422**.

Use the returned `id` for the remaining examples:

```bash
INVOICE_ID=550e8400-e29b-41d4-a716-446655440000 # Replace with the actual UUID
```

### View an invoice — `GET /api/invoices/{id}`

```bash
curl "http://localhost/api/invoices/$INVOICE_ID" \
  -H 'Accept: application/json'
```

Returns **200 OK** with the same invoice structure shown above and its current status: `draft`, `sending`, or `sent-to-client`. An unknown or malformed UUID returns **404**.

### Send an invoice — `POST /api/invoices/{id}/send`

```bash
curl -X POST "http://localhost/api/invoices/$INVOICE_ID/send" \
  -H 'Accept: application/json'
```

No request body is needed. A draft invoice with at least one product line moves to `sending` and invokes the notification provider. Returns **200 OK** with the full invoice structure and `"status": "sending"`.

| Status | Meaning |
| --- | --- |
| 404 | Unknown or malformed invoice UUID. |
| 409 | Invoice is already `sending` or `sent-to-client`. |
| 422 | Invoice has no product lines. |
| 503 | Notification failed; the application attempts to restore `draft` status. |

The configured Dummy notification driver sends no actual email and does not trigger delivery automatically. Call the webhook below to simulate delivery.

### Delivery webhook — `GET /api/notification/hook/{action}/{reference}`

`action` must be `delivered`; `reference` is the invoice UUID.

```bash
curl -i "http://localhost/api/notification/hook/delivered/$INVOICE_ID" \
  -H 'Accept: application/json'
```

Returns **204 No Content** and changes a `sending` invoice to `sent-to-client`. Duplicate deliveries, unknown invoice UUIDs, and deliveries for draft invoices are ignored and also return **204**. An unsupported action or malformed UUID returns **404**.
