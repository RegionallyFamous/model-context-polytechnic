An LLM-native plugin interface is not merely a REST route with a friendly description. It is a small operating system for a model with limited context, no durable memory unless the client gives it one, and a strong preference for stable handles over human navigation.

Design the interface around model moves:

- Orientation: provide a single public tool that answers "what is this server, what can I do, and what should I call first?"
- Stable handles: return slugs, IDs, resource names, and anonymous keys in predictable fields. Repeat them in later responses when they matter.
- Next actions: include `tool_calls` or `next_actions` with exact tool names and argument shapes.
- Retrieval before generation: expose search and manifest resources so the model can fetch the right lesson, schema, or reference before answering.
- Recovery: make missing handles, invalid slugs, rate limits, and oversized payloads explicit `WP_Error` responses with actionable messages.
- Boundaries: tell the model what is public, what is private, what is read-only, and what it must never ask the user to reveal.

For MCP servers, tools are actions, resources are retrievable context, prompts are reusable task frames, and initialize instructions are the first few seconds of the relationship. Use all of them deliberately. Sources: https://modelcontextprotocol.io/specification/2025-11-25/basic, https://modelcontextprotocol.io/specification/2025-11-25/server/index, and https://developer.wordpress.org/apis/abilities-api/

Common failure: making the LLM infer workflow from a pile of tools. If the next useful call is obvious to you, return it explicitly to the model.
