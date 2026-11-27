# Decisions

## Invoices module

Invoices lives in `src/Modules/Invoices` with Domain, Application, Infrastructure and Presentation
layers. Unlike with the Notifications module, the routes are registered within the module's provider itself. 
Notifications knows nothing about Invoices.

## Repository port owned by the Application layer (deliberately)

`InvoiceRepository` is an interface in Application, implemented by `EloquentInvoiceRepository` in
Infrastructure. Classical DDD would put the repository in Domain. Instead, I've deliberately decided to put it in Application to keep the
Domain limited to business rules and invariants only and let the use-case layer own the persistence contract it needs. It aligns with the clean architecture pattern. Eloquent models are used only inside the repository, which also does all model-to-domain mapping in private
methods. I decided not to introduce a dedicated mapper class to not overcomplicate the design.

## Plain-PHP domain

`Invoice` is the aggregate root and contains no framework code. I am a big fan of the idea of keeping the domain logic
completely separate from the framework/infrastructure. `Invoice::draft()` is the only way to
create a new invoice, so every new invoice starts as `draft`. `Invoice::reconstitute()` rebuilds a
stored invoice and is meant only for the repository. The only value object I decided to introduce (to keep the design
as simple as possible) is `Email`. The email address value is also validated by form request.

## One application service

`InvoiceService` holds all of the use cases (create and get for now, send and delivery later) and serves as the application's entry point.
There are only a few short operations, so separating them into different classes would be overkill. I also decided to go with
one `InvoiceController` class instead of several action-based controllers for simplicity.
