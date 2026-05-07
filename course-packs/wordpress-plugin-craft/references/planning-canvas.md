# Plugin Planning Canvas

1. Purpose: what problem does the plugin solve, and for whom?
2. Surfaces: admin screens, frontend output, REST routes, blocks, shortcodes, cron, WP-CLI.
3. Actors: anonymous visitors, subscribers, editors, admins, external services, cron.
4. Permissions: capabilities, nonces, REST permission callbacks, token/application-password needs.
5. Data: options, metadata, CPTs, custom tables, transients, logs, retention, uninstall.
6. Architecture: bootstrap, namespaces, services, hooks, dependencies, extension points.
7. Security: validate, sanitize, escape, prepare SQL, protect secrets, rate-limit public writes.
8. Performance: asset loading, remote calls, cache invalidation, query/index plan, background work.
9. Testing: syntax, standards, Plugin Check, unit/integration/browser smoke tests.
10. Release: readme, license, version, stable tag, screenshots/assets, support and update path.
