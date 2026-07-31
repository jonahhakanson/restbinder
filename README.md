# RestBinder Framework

RestBinder is a small PHP + vanilla JavaScript framework for building state-aware web resources. A RestBinder Resource is a stable, schema-driven data identity whose properties change through protocol-driven mutations.

The framework is intentionally minimal. It contains:

- PHP resource core
- JSON schema-like validation
- Protocol mutation engine
- Hashable resource history
- USRB-style envelopes
- File-backed storage for local prototyping
- Optional SQL schema for MySQL persistence
- Vanilla JS client binder
- Widget and Dandelion examples

## Concept

A resource is not just data, and it is not just a view model. It is a durable identity with a schema, a current state, a mutation protocol, and a history.

```txt
Resource
├── identity
├── schema
├── protocol
├── state
├── history
└── hash
```

The same object can be displayed as a blue diamond, then later as a green pentagon, without becoming a different object. The same identity persists while its properties mutate through accepted protocol commands.

## Quick start

```bash
cd public
php -S localhost:8080
```

Then open:

- `http://localhost:8080/` for the demo page
- `http://localhost:8080/api.php?resource=widget:demo` for the Widget resource
- `http://localhost:8080/api.php?resource=dandelion:yard-bed-04:0001` for the Dandelion resource

## Example mutation

```bash
curl -X POST http://localhost:8080/api.php \
  -H 'Content-Type: application/json' \
  -d '{"resource":"dandelion:yard-bed-04:0001","command":"germinate","input":{"moisture":true,"temperature_above_minimum":true}}'
```

## Project layout

```txt
public/                 Demo web root
src/Core/               Resource, schema, protocol, mutation engine
src/Store/              Storage adapters
src/Http/               Minimal API controller
src/Examples/           Resource definitions
config/                 App config
database/               Optional MySQL schema
docs/                   Specification and Codex prompt
examples/               Standalone examples
tests/                  Smoke tests
```

## License

MIT for this prototype unless replaced by the project owner.
