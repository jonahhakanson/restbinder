# Codex Prompt: Reset RestBinder as Its Own Project

You are working on a fresh project named `restbinder-framework`.

Build and improve a small PHP + vanilla JavaScript framework called RestBinder. RestBinder resources are schema-driven data identities with protocol-driven property mutations. A Resource has stable identity, schema, protocol, current state, mutation history, hash integrity, and display bindings. A Resource can visibly change shape or lifecycle stage without becoming a new object.

Important constraints:

- Use PHP 8+.
- Use vanilla JavaScript, HTML, and CSS.
- Do not use React, Tailwind, or a Node build chain.
- Keep the framework easy to understand and suitable for LAMP/LEMP deployment.
- Provide file-backed storage for prototyping and a MySQL schema for production direction.
- Keep client code as a binder, not the authority.
- All authoritative validation and mutation must happen server-side.

Implement or preserve these project areas:

1. Resource core
   - `ResourceIdentity`
   - `ResourceDefinition`
   - `ResourceSchema`
   - `ResourceProtocol`
   - `MutationEngine`
   - `ResourceHasher`
   - `ResourceEnvelope`

2. Storage
   - File store adapter for local demos.
   - SQL schema for eventual MySQL storage.
   - Ensure resource history is append-only at the conceptual level.

3. HTTP API
   - `GET /api.php?resource=<id>` returns a USRB-style envelope.
   - `POST /api.php` accepts `{ resource, command, input, expect_hash? }`.
   - Server validates command, applies mutation, recalculates hash, and returns the updated envelope.

4. Client binder
   - Vanilla JS module that loads resources from `data-rb-resource`.
   - Renders state into cards.
   - Supports command buttons using `data-rb-command`.
   - Refreshes displayed state after mutation.

5. Examples
   - Widget example: blue diamond mutates into green pentagon.
   - Dandelion example: seed → germinating → sprout → vegetative → flowering → seeding.

6. Documentation
   - Define Resource identity vs state vs display.
   - Define schema-driven data identity.
   - Define protocol-driven mutation.
   - Explain why history and hash integrity matter.
   - Include the dandelion example as a teaching case.

Do not over-engineer. The goal is a clean conceptual and executable seed project that can grow into a full framework.
