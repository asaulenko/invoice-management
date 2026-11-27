# Decisions

## Invoices module

Invoices lives in `src/Modules/Invoices` with Domain, Application (ports, inbound, use cases), Infrastructure (outbound adapters) 
and Presentation (controllers, form requests, event listeners) layers. Unlike with the Notifications module, the routes are 
registered within the module's provider itself. Notifications knows nothing about Invoices.

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

`InvoiceService` holds all of the use cases (create and get for now, send and delivery later) and serves as the application's entry point.
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
guard, so it's the one place covered by a unit test (`ProductLineTest`), along with the totals.

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
