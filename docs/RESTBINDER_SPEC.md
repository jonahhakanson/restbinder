# RestBinder Specification Draft

## 1. Definition

A RestBinder Resource is a schema-driven data identity with protocol-driven property mutations.

A Resource must have:

- a stable resource identifier
- a resource type
- a schema describing allowed properties
- a protocol describing valid mutation commands
- a current state
- an append-only history of accepted mutations
- a canonical hash representing the resource definition, state, protocol version, and history

## 2. Resource identity

Identity is separate from representation. A Resource can change visual form, lifecycle state, display component, or data shape while remaining the same object.

Example:

```txt
widget:demo
  state: blue diamond
  mutation: reshape
  state: green pentagon
```

The blue diamond and green pentagon are not separate objects. They are representational states of the same Resource.

## 3. Resource envelope

RestBinder uses a USRB-style envelope to move Resource information between client and server.

```json
{
  "usrb": "1.0",
  "resource": {
    "id": "widget:demo",
    "type": "Widget",
    "schema_version": "1.0.0",
    "protocol_version": "1.0.0",
    "state": {},
    "history": [],
    "hash": "..."
  },
  "commands": [],
  "display": {}
}
```

## 4. Schema

A schema defines the properties that may exist on a Resource and the validation rules for those properties.

This prototype uses a deliberately small schema format:

```json
{
  "properties": {
    "life_stage": {"type":"string", "enum":["seed","sprout"]},
    "leaf_count": {"type":"integer", "min":0}
  },
  "required": ["life_stage"]
}
```

## 5. Protocol

A protocol defines allowed mutations. A mutation has:

- command name
- allowed source state or states
- target state if applicable
- required input conditions
- changes to apply

```json
{
  "germinate": {
    "from": "seed",
    "to": "germinating",
    "requires": ["moisture", "temperature_above_minimum"],
    "changes": {
      "root_depth_mm": {"op":"add", "value":2},
      "display_shape": {"op":"set", "value":"split-seed-with-root"}
    }
  }
}
```

## 6. History and hash

Each accepted mutation appends a history entry. After the mutation is applied, the Resource hash is recalculated from a canonical representation of:

- identity
- type
- schema version
- protocol version
- state
- history

History is not merely logging. It is part of Resource integrity.

## 7. Display binding

Display is downstream from Resource state. The display layer may render the Resource as a card, form, SVG, list item, control panel, or full page, but it does not own the Resource identity.

## 8. Security model note

RestBinder does not authorize a mutation merely because a client submits a command. Server-side code must verify:

- authenticated session
- resource visibility
- command permission
- protocol validity
- schema validity
- concurrency/hash expectations when required

The client binder is convenience tooling only.
