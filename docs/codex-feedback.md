# Codex Feedback Loop

Model Context Polytechnic has two feedback surfaces:

1. Public course learners call `submit-feedback`.
2. The site owner reads raw feedback privately with WP-CLI.

Public MCP clients can also call `get-course-improvement-signals`, but that returns aggregate counts and recommendations only. Raw comments stay out of the public MCP surface.

## Public Aggregate Signals

Codex, Claude, Cursor, or any MCP client can connect to the public course endpoint:

```text
https://joinmcpoly.com/mcp/wordpress-plugin-craft
```

Then call:

```text
model-context-polytechnic-wordpress-plugin-craft-get-course-improvement-signals
```

This is safe to expose publicly because it returns summaries, not raw comments.

## Private Raw Feedback Inbox

On the WordPress server, use WP-CLI:

```bash
wp model-context-polytechnic feedback list --course=wordpress-plugin-craft
```

For Codex-friendly JSON:

```bash
wp model-context-polytechnic feedback list \
  --course=wordpress-plugin-craft \
  --since=30d \
  --limit=100 \
  --format=json
```

For a compact maintainer brief:

```bash
wp model-context-polytechnic feedback digest \
  --course=wordpress-plugin-craft \
  --since=30d \
  --limit=20
```

For aggregate counts:

```bash
wp model-context-polytechnic feedback summary \
  --course=wordpress-plugin-craft \
  --since=30d
```

## Letting Codex Talk To The WordPress Install

There are two sane modes:

- Public mode: connect Codex to `https://joinmcpoly.com/mcp/wordpress-plugin-craft` and let it call public course tools, including aggregate improvement signals.
- Operator mode: give Codex terminal access to run WP-CLI over SSH, then ask it to review the private feedback digest and propose course-pack changes.

Example SSH pattern:

```bash
ssh user@joinmcpoly.com 'cd /path/to/wordpress && wp model-context-polytechnic feedback digest --course=wordpress-plugin-craft --since=30d --limit=20'
```

Do not put raw feedback comments on the public MCP endpoint. The public course should improve from aggregate signals; raw notes are for the maintainer review loop.
