# Release Checklist

Model Context Polytechnic is a WordPress plugin and a course-pack distribution. A release should prove both halves are intact.

## Before Packaging

- Run `composer install --no-dev --optimize-autoloader`.
- Run `composer foundation:check`.
- Run `composer course-packs:validate`.
- Run `composer course-lab`.
- Run `composer stress-lab`.
- Confirm `vendor/autoload_packages.php` exists.
- Confirm the bundled course loads with the expected lesson and exercise counts.
- Confirm `README.md` names the public endpoint and public learner flow.
- Confirm no real tokens, enrollment keys, Authorization headers, or local site URLs are committed.

## WordPress Smoke Test

- Install the plugin folder in `wp-content/plugins/model-context-polytechnic`.
- Activate the plugin on WordPress 6.9+ with PHP 8.1+.
- Flush permalinks.
- Visit `/wp-json/model_context_polytechnic/mcp`.
- Visit `/mcp`.
- Connect an MCP client to `/mcp`.
- Call `orient`.
- Connect an MCP client to `/mcp/wordpress-plugin-craft`.
- Call `begin-course`.
- Preserve the returned `enrollment_key`.
- Call `get-study-plan`.
- Call `get-next-work`.
- Call `get-lesson`.
- Call `get-exercise`.
- Call `attempt-exercise`.
- Call `get-learning-memory`.
- Call `submit-feedback`.
- Call `get-course-improvement-signals`.

## Public Boundary Review

- Public learner tools do not require WordPress login.
- Public learner tools do not expose secrets or raw Authorization headers.
- Public improvement summaries do not expose raw feedback comments.
- Write/authoring tools remain hidden unless `model_context_polytechnic_authoring_tools_enabled` is enabled.
- Write/authoring tools require `Auth::require_write_access`.
- Anonymous attempt payloads are capped.
- Anonymous feedback payloads are capped.
- Tool telemetry stores hashed/fingerprinted input metadata, not plaintext enrollment keys.
- Retention cleanup is scheduled.

## Packaging Notes

- Include `vendor/` in distributable ZIPs after Composer install.
- Include `course-packs/` and `schemas/`.
- Exclude local logs, Git metadata, temporary files, and generated ZIPs.
- Deactivation must keep data.
- Uninstall is the explicit data removal path.
