# Model Context Polytechnic

Model Context Polytechnic is a WordPress plugin that exposes a public Model Context Protocol learning server over HTTP. WordPress supplies the runtime, REST routing, rewrite alias, database lifecycle, and plugin activation hooks.

The conceit is simple: WordPress is the campus, each MCP server is a course of study, and Claude or another MCP client can act as the faculty member that helps configure a new server before students arrive. The server does not need to be about WordPress content.

## MCP Spec Alignment

This plugin targets the current MCP 2025-11-25 shape through `wordpress/mcp-adapter` and `wordpress/php-mcp-schema`.

- The server exposes MCP tools, resources, and prompts.
- The initialize response includes server instructions, capabilities, and protocol negotiation.
- Tools use explicit input and output JSON schemas.
- Resources include MCP metadata such as `mcp.uri` and MIME type.
- Learner operations are public; optional authoring operations are hidden from the default MCP surface and protected at the ability permission layer when a host site enables them.

## Institutional Voice

Model Context Polytechnic should sound like a venerable technical school, not a SaaS dashboard.

- Voice: venerable, precise, warmly professorial, lightly witty, never corporate.
- Metaphor: MCP servers are courses of study; abilities are coursework; setup is enrollment and syllabus design.
- Style: make setup feel a little ceremonial, but keep technical instructions exact and plain.
- Avoid: startup hype, fake Latin, overexplaining WordPress when the server subject is not WordPress, and jokes that slow down the work.

The same voice guidance is exposed to MCP clients through initialize instructions and the `model-context-polytechnic/voice-guide` resource.

## Learning Model

The plugin does not train model weights. It schools the active AI session by giving it structured context, exercises, rubric feedback, anonymous enrollment, and retrievable learning memory.

Each published course can include:

- Modules: syllabus sections.
- Lessons: instructional material and objectives.
- Exercises: prompts the AI can attempt.
- Rubrics: deterministic criteria for feedback and pass/fail scoring.
- Model answers: spoiler-safe exemplars the AI can request after an attempt for calibration and revision.
- Progress: anonymous attempt history keyed by an `enrollment_key`.
- Improvement signals: privacy-safe tool telemetry plus anonymous learner feedback, so the course can see what is confusing, helpful, brittle, or missing an example.

Public course endpoints expose learning tools without login. The LLM should call `begin-course` first; the server returns an anonymous `enrollment_key` and first recommended work. Attempts without a key still work and automatically issue one unless `remember=false`. Treat `enrollment_key` as a lightweight enrollment card, not a WordPress password.

The MCP HTTP adapter stores protocol sessions against a WordPress user. To keep public learning genuinely no-login, the plugin creates a plugin-owned anonymous subscriber used only for public MCP session IDs and briefly serializes public session requests to avoid user-meta races. That internal user is not a learner account, cannot authorize private write tools, and is removed on uninstall.

The public registrar is LLM-first. If a model is unsure what to do, it should call `model-context-polytechnic-orient` or read the `model-context-polytechnic/llm-interface` resource. Course responses prefer stable handles, MCP-ready `next_actions`, `tool_calls`, and explicit recovery notes so the model does not have to infer workflow from a loose tool list.

The bundled flagship course is **WordPress Plugin Craft**. It is seeded automatically on activation and updated idempotently when the bundled course-pack fingerprint changes. Learner attempts and enrollment memory are not deleted by reseeding.

Bundled courses live in `course-packs/{course-slug}/` instead of large PHP arrays:

```text
course-packs/wordpress-plugin-craft/
├── course.json
├── sources.json
├── lessons/*.md
├── exercises/*.json
└── references/*.md
```

`course.json` defines metadata, modules, lesson ordering, exercise file references, and public reference files. Lessons are Markdown so they can be edited and reviewed like curriculum. Exercises are JSON so rubrics, required terms, hints, and expected output schemas remain machine-checkable. `sources.json` is the source bibliography used to seed the public bibliography resource.

Tradeoff-heavy exercises can include `model_answer` exemplars with the expected `summary`, `work`, and `checks` fields plus calibration notes. Normal `get-exercise` calls do not reveal the model answer; learners request it with `include_model_answer=true` after attempting or when revising a failed answer.

The course-pack loader validates packs before seeding. A malformed pack will not be silently converted into a half-course. Bundled courses are reseeded from a content fingerprint, so edits to Markdown lessons, JSON exercises, references, or bibliography files are picked up without hand-bumping a PHP array version.

Validate course packs locally with:

```bash
composer course-packs:validate
```

The validator checks required metadata, duplicate slugs, readable in-pack file paths, JSON validity, lesson/exercise references, rubric criteria, deterministic grading terms, and bibliography URLs.

The repository also includes reference schemas for course-pack authors:

```text
schemas/course-pack.schema.json
schemas/exercise.schema.json
```

The built-in validator is the enforcement path used by the plugin; the schemas are there so editors and external tooling can catch mistakes before the registrar sees them.

Run the local foundation check before packaging:

```bash
composer foundation:check
```

That check runs PHP syntax checks for non-vendor plugin files, parses non-vendor JSON, validates course packs, and confirms Jetpack Autoloader output exists.

You can also simulate the first course flow without a WordPress runtime:

```bash
composer course-flow:simulate
```

For repeated LLM-student review, run the Course Lab:

```bash
composer course-lab
composer cohort-lab
composer extended-cohort-lab
composer stress-lab
php bin/course-lab.php --students=10
php bin/course-lab.php --students=20
php bin/course-lab.php --agent-brief
```

The lab checks public enrollment, stable handles, practice density, exercise schemas, feedback loops, and course-improvement signals. It also runs deterministic student cohorts across orientation, memory, security, storage, blocks, performance, release, course-authoring, LLM-interface, capstone-maintainer, privacy, admin UX, PHP architecture, testing, hooks, diagnostics, review cadence, namespace safety, lifecycle, and remote-service lenses. The stress lab adds fixture-driven friction scenarios plus golden weak/strong exam answers scored against real rubrics. Use the cohort report first; use stress scenarios for regressions; use the agent brief for larger read-only parallel review; then fold repeated findings back into course-pack files.

To prove the same loop over the real MCP HTTP transport on a WordPress site, run:

```bash
composer http-course-smoke -- --url=https://yoursite.com/mcp/wordpress-plugin-craft
composer http-course-completion-smoke -- --url=https://yoursite.com/mcp/wordpress-plugin-craft
```

The smoke tests post MCP JSON-RPC requests to prove first-day enrollment, exercise attempts, memory retrieval, certificate readiness, and full-course certificate issuance. Browser GET requests to MCP endpoints may return `405 Method Not Allowed`; the transport is POST-based. See [http-smoke.md](docs/http-smoke.md).

## Requirements

- WordPress 6.9+
- PHP 8.1+
- Composer

## Install

```bash
cd /path/to/wp-content/plugins/model-context-polytechnic
composer install
```

Activate the plugin in WordPress, then flush rewrite rules by saving **Settings > Permalinks**.

Deactivation keeps courses, enrollments, attempts, and tokens. Uninstall removes plugin-owned tables, schema options, and rate-limit transients.

## Release

Before packaging a distributable build, run the same gates that CI uses:

```bash
composer install --no-dev --optimize-autoloader
composer lint
composer test
composer release:check
```

Build the installable ZIP and checksum locally with:

```bash
composer release:build -- --version=1.0.0
```

The release builder creates `dist/model-context-polytechnic-1.0.0.zip` with a top-level `model-context-polytechnic/` folder. It includes `vendor/`, `course-packs/`, `schemas/`, `includes/`, the bootstrap file, `README.md`, `CHANGELOG.md`, `composer.json`, `composer.lock`, and `uninstall.php`; local labs, docs, tests, temporary files, and GitHub workflow files are left out of the install artifact.

Publishing a stable release is tag-driven:

```bash
git tag v1.0.0
git push origin v1.0.0
```

The GitHub Actions release workflow reruns `composer release:check`, builds the ZIP, writes a `.sha256` checksum, and attaches both files to the GitHub release. See [release-checklist.md](docs/release-checklist.md) for the complete release and smoke-test checklist.

## Authentication

Learner MCP calls are public. Optional write-capable authoring abilities are hidden by default; if a host site enables them, they require a bearer token at the ability permission layer. Mint write tokens with WP-CLI:

```bash
wp model-context-polytechnic token mint --email=user@example.com --label=claude-desktop
```

Or mint a token and print ready-to-paste MCP client config in one command:

```bash
wp model-context-polytechnic auth config --client=claude --transport=proxy --label=claude-desktop
```

Options:

```bash
wp model-context-polytechnic auth config --access=read --client=generic --transport=direct
wp model-context-polytechnic auth config --access=write --client=cursor --transport=proxy --site-url=https://yoursite.com
```

The plugin also has a public `model-context-polytechnic-client-config` tool. Pass `course_slug` to generate a config for a published course endpoint instead of the private registrar endpoint.

The plaintext token is shown once. Store only the plaintext token in the MCP client config; the plugin stores only a SHA-256 hash.

Useful token commands:

```bash
wp model-context-polytechnic token list
wp model-context-polytechnic token revoke --id=123
wp model-context-polytechnic token revoke --token=mcpoly_plaintext_token
```

Write tools can also authorize a normal WordPress user request. For LLM/MCP clients, use a WordPress Application Password rather than the account's main password. Application Passwords are a WordPress core REST API authentication method and can be revoked independently from the user's login password.

## MCP Endpoint

For read-only clients that support HTTP MCP, use the vanity endpoint directly without headers:

```json
{
  "mcpServers": {
    "model-context-polytechnic": {
      "url": "https://yoursite.com/mcp"
    }
  }
}
```

For clients that need to call write tools, include the bearer header:

```json
{
  "mcpServers": {
    "model-context-polytechnic": {
      "url": "https://yoursite.com/mcp",
      "headers": {
        "Authorization": "Bearer mcpoly_replace_with_token_from_wp_cli"
      }
    }
  }
}
```

Alternatively, write tools can use a WordPress Application Password. Direct HTTP clients need a Basic auth header containing `base64(username:application_password)`:

```json
{
  "mcpServers": {
    "model-context-polytechnic": {
      "url": "https://yoursite.com/mcp",
      "headers": {
        "Authorization": "Basic base64(wordpress_username:application_password_from_user_profile)"
      }
    }
  }
}
```

For read-only clients that expect a local command, use Automattic's current remote proxy:

```json
{
  "mcpServers": {
    "model-context-polytechnic": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/mcp",
        "OAUTH_ENABLED": "false"
      }
    }
  }
}
```

For write-enabled proxy clients, pass the bearer header through `CUSTOM_HEADERS`:

```json
{
  "mcpServers": {
    "model-context-polytechnic": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/mcp",
        "OAUTH_ENABLED": "false",
        "CUSTOM_HEADERS": "{\"Authorization\":\"Bearer mcpoly_replace_with_token_from_wp_cli\"}"
      }
    }
  }
}
```

For write-enabled proxy clients using a WordPress Application Password, pass the WordPress username and application password through the proxy environment:

```json
{
  "mcpServers": {
    "model-context-polytechnic": {
      "command": "npx",
      "args": ["-y", "@automattic/mcp-wordpress-remote@latest"],
      "env": {
        "WP_API_URL": "https://yoursite.com/mcp",
        "OAUTH_ENABLED": "false",
        "WP_API_USERNAME": "wordpress_username",
        "WP_API_PASSWORD": "application_password_from_user_profile"
      }
    }
  }
}
```

The canonical REST endpoint also works:

```text
https://yoursite.com/wp-json/model_context_polytechnic/mcp
```

## Course Registry

Model Context Polytechnic can host many public MCP servers from one WordPress install. Each saved server is a course.

The plugin ships with a published course:

```text
https://yoursite.com/mcp/wordpress-plugin-craft
https://yoursite.com/wp-json/model_context_polytechnic/courses/wordpress-plugin-craft
```

WordPress Plugin Craft covers plugin lifecycle, architecture, hooks, security, storage, REST APIs, admin UX, JavaScript and blocks, performance, testing, distribution, and advanced PHP/JavaScript judgment.

The main `/mcp` server is the public registrar. By default it lists catalog and diagnostic abilities only; it does not advertise course-authoring tools to public MCP clients.

Host sites can opt into the legacy authoring surface with the `model_context_polytechnic_authoring_tools_enabled` filter. When enabled, these private tools still require `Auth::require_write_access`:

- `model-context-polytechnic/create-course`
- `model-context-polytechnic/update-course`
- `model-context-polytechnic/add-module`
- `model-context-polytechnic/add-lesson`
- `model-context-polytechnic/add-exercise`
- `model-context-polytechnic/set-rubric`
- `model-context-polytechnic/describe-syllabus`
- `model-context-polytechnic/add-ability`
- `model-context-polytechnic/add-content`
- `model-context-polytechnic/publish-course`
- `model-context-polytechnic/describe-course`

Published courses get their own public endpoint:

```text
https://yoursite.com/mcp/{course-slug}
https://yoursite.com/wp-json/model_context_polytechnic/courses/{course-slug}
```

Public users connect to the course endpoint without logging in. Course setup is not part of the default public learner flow.

If the plugin is already active when new storage code is added, the auth, registry, and learning tables are installed automatically on the next WordPress `init` request. Re-saving **Settings > Permalinks** still flushes the `/mcp/{course-slug}` rewrite alias.

Course servers and their ability lists are assembled when the MCP server is registered for a request. After a host site changes bundled or database-backed coursework, reconnect or refresh the MCP client to see the new endpoint/tool list.

Published course endpoints automatically include these public learning tools. WordPress ability IDs allow one namespace slash, so course handles are folded into the ability name:

- `model-context-polytechnic/{course-slug}-begin-course`
- `model-context-polytechnic/{course-slug}-get-study-plan`
- `model-context-polytechnic/{course-slug}-search-course`
- `model-context-polytechnic/{course-slug}-get-syllabus`
- `model-context-polytechnic/{course-slug}-get-lesson`
- `model-context-polytechnic/{course-slug}-get-exercise`
- `model-context-polytechnic/{course-slug}-attempt-exercise`
- `model-context-polytechnic/{course-slug}-get-next-work`
- `model-context-polytechnic/{course-slug}-get-progress`
- `model-context-polytechnic/{course-slug}-get-learning-memory`
- `model-context-polytechnic/{course-slug}-get-certificate`
- `model-context-polytechnic/{course-slug}-submit-feedback`
- `model-context-polytechnic/{course-slug}-get-course-improvement-signals`

MCP clients see sanitized tool names with the slash converted to a dash, such as `model-context-polytechnic-wordpress-plugin-craft-begin-course`. Tool suggestions returned inside `tool_calls`, `next_actions`, and `next_tool` already use the MCP-ready dashed names.

The public learning flow is intentionally light:

1. Connect an MCP client to `https://yoursite.com/mcp/{course-slug}`.
2. Call `begin-course`.
3. Keep the returned `enrollment_key` in the conversation, client notes, or project memory.
4. Use `get-study-plan` when you have a goal and want a route through the course.
5. Use `get-next-work` for the next recommended lesson, exercise, and exact tool arguments.
6. Use `search-course` to retrieve targeted lessons, exercises, and references.
7. Pass `enrollment_key` to `attempt-exercise`, `get-progress`, and `get-learning-memory`.
8. When `get-next-work` reports `complete=true`, call `get-certificate` with the same `enrollment_key`.
9. Call `submit-feedback` when a lesson, exercise, tool response, or next action is confusing, helpful, stale, or missing an example.
10. Call `get-course-improvement-signals` before proposing course changes so recommendations are based on accumulated evidence.

The enrollment key is anonymous. The plugin stores only a SHA-256 hash of it. If an attempt is submitted without an enrollment key, `attempt-exercise` will evaluate the work, create a new enrollment key, store the attempt against it, and return the key so future calls can remember the work.

Completion is also anonymous. `get-certificate` issues or retrieves a certificate only after every published exercise has a passing attempt for that enrollment key. The certificate includes a deterministic certificate ID, verification code, completion statement, and optional transcript. It proves completion for that anonymous learner record inside this WordPress-hosted MCP server; it is not a WordPress login, password, or human identity credential.

Public learning data is bounded. Exercise answers are capped at 20 KB, feedback comments are capped at 6 KB, public learning reads/writes are rate-limited, and old anonymous attempts, feedback, and telemetry events are pruned by a daily cleanup job. Certificate records store hashed enrollment identity plus completion metadata, not answers. The default retention window is 180 days and can be changed with the `model_context_polytechnic_learning_retention_days` filter.

Every public course tool call records a privacy-safe learning event: tool slug, target handle when known, result status, duration, and an input fingerprint. Enrollment keys are hashed and large fields such as answers or comments are hashed by fingerprint rather than stored in telemetry. This does not auto-edit the course; it creates aggregate improvement signals. The public `get-course-improvement-signals` tool returns counts, hotspots, exercise pass-rate patterns, and recommendations without returning raw feedback comments.

They also include a syllabus resource:

- `model-context-polytechnic/{course-slug}-resource-syllabus`

Rubric criteria support deterministic grading with `required_terms` or `any_terms`:

```json
{
  "criteria": [
    {
      "name": "Names the operating model",
      "points": 1,
      "required_terms": ["public read", "private write"]
    },
    {
      "name": "Uses institutional metaphor",
      "points": 1,
      "any_terms": ["syllabus", "registrar", "course"]
    }
  ]
}
```

The first registry implementation is intentionally no-code: saved abilities are static or templated tools/resources/prompts. Response templates support:

```text
{{input}}
{{input.field_name}}
{{course.name}}
{{course.slug}}
{{ability.name}}
{{ability.slug}}
```

For tool responses, if the rendered template is valid JSON, it is returned as structured data. Otherwise it is returned as text with the original input.

## Public Read Abilities

- `model-context-polytechnic/server-status`
- `model-context-polytechnic/client-config`
- `model-context-polytechnic/orient`
- `model-context-polytechnic/connection-playbook`
- `model-context-polytechnic/echo-schema`
- `model-context-polytechnic/server-manifest`
- `model-context-polytechnic/llm-interface`
- `model-context-polytechnic/voice-guide`
- `model-context-polytechnic/troubleshoot-connection`
- `model-context-polytechnic/course-catalog`

WordPress ability IDs use dashes because WordPress 6.9 validates ability names as lowercase alphanumeric, dash, and slash characters.

## Private Write Tools

Write-capable abilities should use `ModelContextPolytechnic\Mcp\Auth::require_write_access` as their `permission_callback`. That accepts either a valid plugin bearer token or a WordPress-authenticated user with the configured capability. Ability callbacks can read the validated plugin token row from:

```php
$GLOBALS['model_context_polytechnic_mcp_token']
```

Use that token id to scope records in custom tables when writes need per-client ownership or auditing.

Example write ability permission:

```php
'permission_callback' => [ ModelContextPolytechnic\Mcp\Auth::class, 'require_write_access' ],
```

The default WordPress capability for Application Password-authenticated writes is `edit_posts`. Change it with the `model_context_polytechnic_write_capability` filter.
