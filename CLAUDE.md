# Invoice management – test assignment

Laravel 13 / PHP 8.5 app. The task itself is described in `README.md`; read it before planning anything.

Code is organised as modules under `src/Modules/<Module>/` with `Api`, `Application`, `Domain`,
`Infrastructure` and `Presentation` layers. `Notifications` is a provided mock and is not a reference
for how `Invoices` should be designed.

## Running things

Everything runs in the Sail container (`./start.sh` brings it up):

- Tests: `docker compose exec app php artisan test` (single file: append the path)
- Code style: `docker compose exec app vendor/bin/pint`

## How I want code written

- The design is decided with me first. Don't introduce new modules, layers or abstractions
  that weren't agreed on.
- Keep it as simple as the requirements allow. No factories or config "for later" unless
  there's a concrete reason.
- Interfaces are for boundaries, not for every class. A single-implementation interface is
  fine when it's a port: a contract owned by the `Application` layer or a module's `Api`, with
  the implementation living in `Infrastructure` (e.g. a repository). Elsewhere, use concrete classes.
- `Domain` is plain PHP: no Eloquent, facades or other framework/infrastructure dependencies.
- Comments explain *why*, never *what*. No docblocks that repeat the signature.
- Tests go through public seams (HTTP endpoints, module APIs), not internals.
- Commits: conventional commits (`feat(invoices): ...`), small and self-contained. Don't commit
  unless I ask.
- Decisions and trade-offs go in `docs/decisions.md`, one short section per decision. Not ADR files,
  and not in `README.md`.

## Agent skills

### Issue tracker

Specs and tickets are local markdown files under `.scratch/<feature>/`. See `docs/agents/issue-tracker.md`.
