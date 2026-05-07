# Plugin Review Checklist

- Contract: plugin header, version, text domain, load guard, lifecycle hooks.
- Architecture: namespacing/prefixing, clear hook boundaries, no global collisions.
- Security: capabilities, nonces where appropriate, REST permission callbacks, validation, sanitization, late escaping.
- Data: correct storage API, schema versioning, indexes, retention, uninstall policy.
- REST/HTTP: schemas, WP_Error failures, auth split, timeout and cache for upstream calls.
- Admin UX: focused screens, accessible labels, clear notices, no dashboard hijacking.
- JavaScript/blocks: block.json, scoped assets, accessible editor UI, no unnecessary frontend packages.
- Performance: conditional enqueues, bounded queries, cached remote calls, cron cleanup.
- Quality: php -l, PHPCS/WPCS, Plugin Check, PHPUnit/integration/browser tests as risk requires.
- Distribution: GPL-compatible license, readme, stable tag, changelog, privacy/external service disclosures.
