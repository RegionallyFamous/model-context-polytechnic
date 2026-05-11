# HTTP MCP Smoke Test

The local labs prove the course logic without WordPress. The HTTP smoke test proves the public MCP transport, initialize session, tool discovery, enrollment, exercise attempt, memory retrieval, certificate readiness, and spoiler-safe model answer path against a real WordPress site.

Run it after activating the plugin and flushing permalinks:

```bash
composer http-course-smoke -- --url=https://joinmcpoly.com/mcp/wordpress-plugin-craft
```

For a full graduation rehearsal, run the completion smoke. It attempts every bundled exercise over MCP HTTP with the model answers, confirms `get-next-work` reports `complete=true`, and verifies `get-certificate` returns a certificate ID, verification code, transcript, `graduation_speech`, 0-100 `completion_percent`, 0-1 `completion_ratio`, and post-certificate memory/reflection next steps:

```bash
composer http-course-completion-smoke -- --url=https://joinmcpoly.com/mcp/wordpress-plugin-craft
```

JSON output is useful for CI logs:

```bash
composer http-course-smoke -- --url=https://joinmcpoly.com/mcp/wordpress-plugin-craft --json
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
6. `tools/call` for the exact MCP-ready `take-course` tool returned by `begin-course` and verify an autopilot material packet, verbose `learning_status.story_script`, and optional `get-campus-scene` visual call are returned.
7. `tools/call` for `get-campus-scene` and verify it returns `display_markdown` plus a public `image_url`; optionally call `get-campus-scene-image` only when the client visibly renders raw MCP image content blocks.
8. `tools/call` for `get-exercise`.
9. Verify `get-exercise` exposes `attempt_preflight.required_exact_vocabulary`, `rubric_vocabulary.required_terms`, and `rubric_vocabulary.accepted_aliases`.
10. `tools/call` for `attempt-exercise`; use `response_mode=gradebook` when the client wants compact scoring instead of the full campus story.
11. Verify attempts separate `this_attempt_result` from course-wide `global_next_unpassed_work`.
12. `tools/call` for `get-learning-memory` and verify `completion_percent` is 0-100 while `completion_ratio` is 0-1.
13. `tools/call` for `get-certificate` and confirm an unfinished enrollment gets remaining work.
14. `tools/call` for `get-exercise` with `include_model_answer=true`.

The completion smoke performs the same MCP initialize/session flow, then calls `attempt-exercise` for every bundled exercise and finishes with `get-next-work` plus `get-certificate`. The certificate response includes `graduation_speech`, which tells the Agent to stand at the podium and tell everyone what it learned before closing the course. It also includes `certificate.diploma`, a dynamic diploma artifact with a reusable PNG template URL, render fields, SVG markup, and an SVG data URI that clients can display or export. After certificate issuance, the returned next work must point to `get-learning-memory` and reflection/feedback instead of asking for `get-certificate` again. The live course also exposes an MCP-ready `take-course` tool, which is the LLM autopilot entry point for reading course packets without asking a human to advance lesson by lesson.

The endpoint is POST-based. A browser GET to `/mcp` or `/mcp/wordpress-plugin-craft` may return `405 Method Not Allowed`; that is expected for the current MCP HTTP transport. Use an MCP client or the smoke script to prove the endpoint.

Public course endpoints do not require WordPress credentials. Internally, the plugin creates a plugin-owned anonymous WordPress user so the upstream MCP adapter can store HTTP session IDs in user meta. Public session requests are briefly serialized with a plugin-owned lock to avoid concurrent user-meta session races. That user is not a learner account, is deleted on uninstall, and cannot authorize private write tools.
