# Release Checklist

Model Context Polytechnic is a WordPress plugin and a course-pack distribution. A release should prove both halves are intact.

## Before Packaging

- Run `composer install --no-dev --optimize-autoloader`.
- Run `composer lint`.
- Run `composer test`.
- Run `composer release:check`.
- Run `composer extended-cohort-lab` before major curriculum releases.
- Against the public WordPress test site, run `composer http-course-smoke -- --url=https://joinmcpoly.com/mcp/wordpress-plugin-craft`.
- For graduation-path releases, run `composer http-course-completion-smoke -- --url=https://joinmcpoly.com/mcp/wordpress-plugin-craft`.
- Confirm `vendor/autoload_packages.php` exists.
- Confirm the plugin header, `MODEL_CONTEXT_POLYTECHNIC_VERSION`, and `Server::SERVER_VERSION` match the release tag.
- Confirm the bundled course loads with the expected lesson and exercise counts.
- Confirm `README.md` names the public endpoint and public learner flow.
- Confirm docs frame the learner flow as a hands-off school journey with verbose `learning_status.story_script` narration and visible `get-campus-scene` markdown image packets, not visible text-art status boards.
- Confirm docs mention `get-campus-scene-image` as optional raw MCP image content for clients that visibly render image blocks.
- Confirm graduation language asks the Agent to deliver `graduation_speech`, state what it learned, report confidence, and submit reflection feedback.
- Confirm exercise responses expose `rubric_vocabulary.required_terms` so important WordPress terms are visible before grading.
- Confirm `attempt-exercise` supports `response_mode=student_theater` and `response_mode=gradebook`.
- Confirm progress fields use `completion_percent` as 0-100 and `completion_ratio` as 0-1.
- Confirm `attempt-exercise` separates `this_attempt_result` from `global_next_unpassed_work`.
- Confirm successful `get-certificate` responses point to post-certificate memory/reflection work, not another `get-certificate` call.
- Confirm `server-status` returns a public `health` object with adapter and route-registration smoke status.
- Confirm exemplar `model_answer` content is present for first-work and tradeoff-heavy exercises without being returned by default.
- Confirm the protected `get-feedback-digest` tool returns 401 without an operator bearer token and returns private raw feedback only with one.
- Confirm the protected `get-course-stats` tool returns 401 without an operator bearer token and returns private enrollment, attempt, completion, certificate, feedback, and activity counts with one.
- Confirm the README and feedback docs describe the no-WP-CLI operator-token flow.
- Confirm no real tokens, enrollment keys, Authorization headers, or local site URLs are committed.
- Confirm every shown public URL uses `joinmcpoly.com`.

## WordPress Smoke Test

- Install the plugin folder in `wp-content/plugins/model-context-polytechnic`.
- Activate the plugin on WordPress 6.9+ with PHP 8.1+.
- Flush permalinks.
- POST an MCP `initialize` request to `/wp-json/model_context_polytechnic/mcp`.
- POST an MCP `initialize` request to `/mcp`.
- Know that browser GET requests can return `405 Method Not Allowed`; the HTTP transport is POST-based.
- Connect an MCP client to `/mcp`.
- Call `orient`.
- Connect an MCP client to `/mcp/wordpress-plugin-craft`.
- Call `begin-course`.
- Preserve the returned `enrollment_key`.
- Call the exact MCP-ready `take-course` tool returned by `begin-course` with `mode=module_batch` and confirm it returns an autopilot packet, verbose `learning_status.story_script`, campus scene metadata, and valid follow-up `tool_calls`.
- Confirm the learner flow can proceed hands-off through returned `tool_calls` without using a visible progress widget as the main framing device.
- Call `get-campus-scene` and confirm it returns `display_markdown` plus `image_url`.
- Call `get-campus-scene-image` and confirm clients that visibly render MCP image content receive an optional campus postcard image block.
- Call `get-exercise` and confirm `rubric_vocabulary.required_terms` is present.
- Call `attempt-exercise` with `response_mode=gradebook` and confirm the response omits campus story fields while returning score, matched terms, missing terms, and next tool calls.
- Confirm `attempt-exercise` returns `this_attempt_result` for the submitted exercise and `global_next_unpassed_work` for the course-wide next recommendation.
- Call `get-study-plan`.
- Call `get-next-work`.
- Call `get-lesson`.
- Call `get-exercise`.
- Call `attempt-exercise`.
- Call `get-learning-memory`.
- Call `get-certificate` before completion and confirm it returns remaining work.
- In a full-course completion rehearsal or `http-course-completion-smoke`, call `get-certificate` after every exercise is passed and confirm it returns a certificate ID, verification code, transcript, and `graduation_speech`.
- Confirm completed progress shows `completion_percent=100` and `completion_ratio=1`, and post-certificate next work goes to learning memory/reflection rather than another certificate call.
- After certificate issuance, deliver the graduation speech, then submit confidence and reflection feedback about how the course will improve future WordPress plugin work.
- Call `submit-feedback`.
- Call `get-course-improvement-signals`.
- Call protected `get-feedback-digest` without an operator token and confirm 401.
- Call protected `get-feedback-digest` with `Authorization: Bearer <operator-token>` and confirm raw feedback is private.
- Call protected `get-course-stats` without an operator token and confirm 401.
- Call protected `get-course-stats` with `Authorization: Bearer <operator-token>` and confirm private stats include enrollments, attempts, completion, feedback, and daily activity.

## Public Boundary Review

- Public learner tools do not require WordPress login.
- The plugin-owned anonymous public session user exists after activation and cannot pass write-capability checks.
- Public learner tools do not expose secrets or raw Authorization headers.
- Public improvement summaries do not expose raw feedback comments.
- Private feedback digest and course stats require `Auth::require_operator_access` and accept only an operator bearer token or an explicitly minted stored bearer token.
- Public certificates do not expose answers, plaintext enrollment hashes, tokens, or WordPress user identity.
- Write/authoring tools remain hidden unless `model_context_polytechnic_authoring_tools_enabled` is enabled.
- Write/authoring tools require `Auth::require_write_access`.
- Anonymous attempt payloads are capped.
- Anonymous feedback payloads are capped.
- Tool telemetry stores hashed/fingerprinted input metadata, not plaintext enrollment keys.
- Retention cleanup is scheduled.

## Packaging Notes

- Bump versions first with `composer version:bump -- --version=x.y.z`.
- Build the installable artifact with `composer release:build -- --version=x.y.z`.
- Confirm the ZIP contains one top-level `model-context-polytechnic/` folder.
- Include `vendor/` in distributable ZIPs after Composer install.
- Include `assets/`, `course-packs/`, `schemas/`, `includes/`, `README.md`, `CHANGELOG.md`, `composer.json`, `composer.lock`, the bootstrap file, and `uninstall.php`.
- Exclude local labs, docs, tests, logs, Git metadata, temporary files, workflow files, and generated ZIPs.
- Publish by pushing a version tag such as `v1.0.14`; the GitHub release workflow rebuilds the ZIP and checksum from the tag.
- Deactivation must keep data.
- Uninstall is the explicit data removal path, including removal of the plugin-owned anonymous public session user.
