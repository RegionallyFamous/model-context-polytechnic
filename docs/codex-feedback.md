# Codex Feedback Loop

Model Context Polytechnic has three feedback surfaces:

1. Public course learners call `submit-feedback`.
2. Public maintainers call `get-course-improvement-signals` for aggregate counts and recommendations.
3. Trusted operators call `get-feedback-digest` with a bearer token to read private raw feedback and graduation reflections.
4. Trusted operators call `get-course-stats` with the same bearer token to read private enrollment, completion, feedback, and activity counts.

Public learners never need a WordPress account, password, token, or setup. The operator token is only for the private registrar drawer.

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

## Private Feedback Digest Without WP-CLI

Add a long operator secret to `wp-config.php`:

```php
define( 'MODEL_CONTEXT_POLYTECHNIC_OPERATOR_TOKEN', 'replace-with-a-long-random-operator-secret' );
```

For a stricter setup, store only a hash:

```php
define( 'MODEL_CONTEXT_POLYTECHNIC_OPERATOR_TOKEN_HASH', 'sha256-hash-of-the-operator-secret' );
```

Connect the operator MCP client to the same course endpoint, but include the bearer header:

```json
{
  "mcpServers": {
    "mcpoly-feedback": {
      "url": "https://joinmcpoly.com/mcp/wordpress-plugin-craft",
      "headers": {
        "Authorization": "Bearer replace-with-a-long-random-operator-secret"
      }
    }
  }
}
```

Then ask Codex to call:

```text
model-context-polytechnic-wordpress-plugin-craft-get-feedback-digest
model-context-polytechnic-wordpress-plugin-craft-get-course-stats
```

Useful input:

```json
{
  "window_days": 30,
  "limit": 20
}
```

The digest includes recent raw feedback comments, graduation reflections, aggregate signals, exercise outcome patterns, and maintainer-facing recommendations. Treat it as evidence for a reviewed course-pack patch, not as automatic instructions to rewrite the course.

Useful stats input:

```json
{
  "window_days": 30,
  "daily_days": 14,
  "exercise_limit": 8
}
```

The private stats response answers questions like how many anonymous learners enrolled, how many attempted labs, how many became completion-eligible, how many certificates were issued, how much feedback arrived, and where recent exercise outcomes look rough. `completion_eligible_learners` means an enrollment has passed every currently published exercise; `certificates_issued` means the learner also called `get-certificate`.

## Host Notes

Some hosts strip `Authorization` before WordPress sees it. If the digest tool returns 401 even with the right secret, confirm the header reaches PHP. Apache installs often need an environment forwarding rule such as:

```apache
SetEnvIf Authorization "(.*)" HTTP_AUTHORIZATION=$1
```

WP-CLI feedback commands still exist for server administrators, but they are no longer the required Codex workflow. The clean loop is: public learners submit feedback, public signals show patterns, private stats show adoption and completion, and the protected MCP digest lets an operator LLM inspect the raw notes when you explicitly connect it with the bearer token.
