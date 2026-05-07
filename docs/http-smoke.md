# HTTP MCP Smoke Test

The local labs prove the course logic without WordPress. The HTTP smoke test proves the public MCP transport, initialize session, tool discovery, enrollment, exercise attempt, memory retrieval, and spoiler-safe model answer path against a real WordPress site.

Run it after activating the plugin and flushing permalinks:

```bash
composer http-course-smoke -- --url=https://yoursite.com/mcp/wordpress-plugin-craft
```

JSON output is useful for CI logs:

```bash
composer http-course-smoke -- --url=https://yoursite.com/mcp/wordpress-plugin-craft --json
```

If a staging site requires an extra header, pass it explicitly:

```bash
composer http-course-smoke -- --url=https://staging.example.com/mcp/wordpress-plugin-craft --header="X-Staging-Key: value"
```

The smoke test performs these MCP JSON-RPC calls:

1. `initialize` and capture `Mcp-Session-Id`.
2. `notifications/initialized`.
3. `tools/list` and verify the public learning tools are exposed.
4. `resources/list` and verify the syllabus plus public references are exposed.
5. `tools/call` for `begin-course`.
6. `tools/call` for `get-exercise`.
7. `tools/call` for `attempt-exercise`.
8. `tools/call` for `get-learning-memory`.
9. `tools/call` for `get-exercise` with `include_model_answer=true`.

The endpoint is POST-based. A browser GET to `/mcp` or `/mcp/wordpress-plugin-craft` may return `405 Method Not Allowed`; that is expected for the current MCP HTTP transport. Use an MCP client or the smoke script to prove the endpoint.

Public course endpoints do not require WordPress credentials. Internally, the plugin creates a plugin-owned anonymous WordPress user so the upstream MCP adapter can store HTTP session IDs in user meta. Public session requests are briefly serialized with a plugin-owned lock to avoid concurrent user-meta session races. That user is not a learner account, is deleted on uninstall, and cannot authorize private write tools.
