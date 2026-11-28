# Decisions

## Invoices module

Invoices lives in `src/Modules/Invoices` with Domain, Application (ports, inbound, use cases), Infrastructure (outbound adapters) 
and Presentation (controllers, form requests, event listeners) layers. Unlike with the Notifications module, the routes are 
registered within the module's provider itself. Notifications knows nothing about Invoices.

Notifications module was not changed.

## Repository port owned by the Application layer (deliberately)

`InvoiceRepository` is an interface in Application, implemented by `EloquentInvoiceRepository` in
Infrastructure. Classical DDD would put it in Domain. I've deliberately put it in Application so the Domain holds only
business rules and invariants, and the use-case layer owns the persistence contract it needs, as in clean architecture.
Ports live in `Application/Ports/`, so the folder tree mirrors the adapter folders under Infrastructure.

## Eloquent stays inside the repository, no mapper class

Eloquent models are used only inside `EloquentInvoiceRepository`, which also maps between models and the domain
(`toDomain()` for reads, inline in `save()` for writes). A dedicated mapper class would overcomplicate the design.

## Plain-PHP domain

`Invoice` is the aggregate root and contains no framework code: I want the domain logic completely separate from the
framework and infrastructure. `Invoice::draft()` is the only way to create a new invoice, so every new invoice starts
as `draft`. `Invoice::reconstitute()` rebuilds a stored invoice and is meant only for the repository.

## `Email` is the only value object

To keep the design simple, `Email` is the only value object. The form request validates the address too (with the
same `filter` check), so bad input gets a 422, while `Email` guards the invariant inside the domain.

## One application service

`InvoiceService` holds all of the use cases and serves as the application's entry point.
There are only a few short operations, so separating them into different classes would be overkill. I also decided to go with
one `InvoiceController` class instead of several action-based controllers for simplicity.

## Product lines live inside the aggregate

`ProductLine` is an entity inside `Invoice` with its own UUID. Its constructor rejects a quantity or unit price below 1, 
so an invalid line can't exist even if it never went through HTTP. Lines come only
with the invoice: the repository saves an invoice by replacing all of its lines in one transaction, so it never has
to work out which lines changed.

**IMPORTANT!** The requirements state that invoice lines can have only positive values for   
quantity and unit price. Later, it is said that to be sent, an invoice must contain product lines with both quantity   
and unit price as positive integers greater than zero. IMO, it is a tiny redundancy in the requirements. This invariant is maintained   
at invoice creation (well, at least in my implementation).

## Totals are computed, never stored

`ProductLine::totalUnitPrice()` and `Invoice::totalPrice()` are derived on read. Storing them would mean two sources
of truth for the same number. An invoice without lines totals 0.

## Line order comes from UUIDv7 IDs

Lines are returned in the order they were created. The schema has no position column, and lines saved together share
the same `created_at`, so ordering by timestamp is ambiguous. Instead, line IDs are UUIDv7, which are time-ordered
(and monotonic within a millisecond in ramsey/uuid), and lines are loaded ordered by `id`. This needs no schema change.

## Line validation in the request and the domain

The form request validates each line (`product_name` required, max 255; `quantity` and `unit_price` integers ≥ 1),
so clients get field-level 422s. As with `Email`, the domain repeats the positive-number rule. HTTP can't reach that
guard, so only `ProductLineTest` covers it.

## Sending: the invoice guards its own transition

`Invoice::send()` requires `draft` status and at least one product line, then moves to `sending`. The two violations
are distinct domain exceptions, because they mean different things to a client: a wrong status is a conflict with the
invoice's current state (409), an empty invoice is a request that can never succeed as it stands (422). The service
never checks a status itself; it loads, calls `send()`, saves, then notifies. The controller maps the exceptions to
HTTP codes, so the domain stays unaware of HTTP.

## Notifications behind an outbound port

`InvoiceService` doesn't call the Notifications module itself. It calls `InvoiceNotifier`, an outbound port in Application
that takes an `Invoice`, and `NotificationsInvoiceNotifier` in Infrastructure implements it by wrapping
`NotificationFacadeInterface`. Same reasoning as the repository: the use-case layer depends only on contracts it owns,
and the coupling to another module (including building its `NotifyData`) stays in Infrastructure.

## Send order: lock, commit, then notify

`InvoiceService::send()` loads the invoice with `findForUpdate()` (`lockForUpdate`) inside a transaction, calls
`send()`, saves and commits, and only then notifies. Two concurrent sends queue on the row lock, so the second one sees
`sending` and gets a 409, and only one email goes out. Because `sending` is committed before the provider is called,
a delivery webhook can never arrive for an invoice that still looks like a draft. The service holds the transaction
(through Laravel's `ConnectionInterface`) instead of a dedicated transaction port, since that would be an abstraction
with a single obvious implementation.

## Notification failure: revert to draft and 503

If `notify()` throws, `Invoice::revertToDraft()` (allowed only from `sending`) moves the invoice back to `draft`, it is
saved, and the API returns 503, so the client can safely retry. If the revert itself fails, the error is logged and the
invoice stays in `sending` (see the limitations below).

## Delivery webhook edge cases

Because the listener runs inside the provider's webhook request, a thrown exception would become a 500 and trigger retries.
Some cases cannot be fixed with a retry, so instead of throwing the listener does the following:
- unknown invoice ID: a warning is logged (Notifications is shared, so the ID may belong to another resource);
- invoice already `sent-to-client`: a duplicate webhook, ignored silently;
- invoice still `draft`: a warning is logged, since the send order should make this impossible.

To tell the last two apart without the listener checking the status itself, `InvalidStatusTransitionException`
carries the status that rejected the transition. Unexpected errors (e.g. the database being down) are deliberately
not caught: a 500 makes the provider retry, which is exactly what we want for a delivery we failed to record.

## Testing strategy: the aggregate and the HTTP API

Tests use only two public seams, so they check behaviour and survive refactoring:
- **Domain unit tests** (`InvoiceTest`, `ProductLineTest`) verify the core business rules
  directly at the domain level. They cover all valid and invalid status transitions
  (using data providers), ensure that an invoice cannot be sent without product lines,
  validate positive amounts, and verify total calculations. These are plain PHPUnit
  tests that run without booting the framework.
- **HTTP feature tests** (`InvoiceEndpointsTest`) verify that the application components
  work correctly together. They cover request validation, mapping errors to the
  appropriate HTTP status codes (404/409/422/503), data persistence, preservation
  of product line order when creating and viewing invoices, and the invoice sending
  and delivery workflow. Delivery is tested through the actual Notifications webhook
  endpoint, ensuring that the entire flow, from event dispatching to updating the
  invoice status, works correctly across module boundaries. 

The database is real (SQLite, refreshed per test), and the only double is `FakeNotificationFacade` at the Notifications
module's public API. It records what would be sent and can be made to fail, which is how the revert-to-draft + 503 path
is tested. The service, repository, controller and listener have no tests of their own: the feature tests already
cover them, and isolated tests would mostly assert on mocks. Assertions go through API responses, never Eloquent
models, so the storage can change without touching the tests.

## Known limitations

- If notifying fails and the revert also fails (e.g. the database is down), the invoice is stuck in `sending`.
- If `notify()` throws after the provider actually sent the email (e.g. a timeout), the invoice is reverted to
  `draft`, and sending it again sends a duplicate email.
- The production fix would be a queued send with retries after commit, plus a check that finds invoices stuck in
  `sending`. Redis is already in `docker-compose.yml` and could back that queue. I didn't use it here, because a
  queued send brings its own problems (idempotent retries, failed jobs) that the task doesn't ask for.
- The row lock works on MySQL and Postgres. SQLite serialises writes but ignores the lock, so the concurrency guard
  isn't covered by a test; faking one would prove nothing.
