# Decisions

## Invoices module

Invoices lives in `src/Modules/Invoices` with Domain, Application, Infrastructure and Presentation
layers. Unlike with the Notifications module, the routes are registered within the module's provider itself. 
Notifications knows nothing about Invoices.

## Repository port owned by the Application layer (deliberately)

`InvoiceRepository` is an interface in Application, implemented by `EloquentInvoiceRepository` in
Infrastructure. Classical DDD would put it in Domain. I've deliberately put it in Application so the Domain holds only
business rules and invariants, and the use-case layer owns the persistence contract it needs, as in clean architecture.

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
