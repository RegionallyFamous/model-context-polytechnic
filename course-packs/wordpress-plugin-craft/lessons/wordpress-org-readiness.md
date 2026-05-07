The WordPress Plugin Directory has explicit guidelines covering GPL compatibility, readable code, developer responsibility, external services, tracking consent, versioning, and more. A release-ready plugin also has a useful readme, stable version, assets, and support expectations. Sources: https://developer.wordpress.org/plugins/wordpress-org/detailed-plugin-guidelines/ and https://developer.wordpress.org/plugins/wordpress-org/common-issues/

Common failure: treating the readme and release process as afterthoughts. Distribution is part of engineering.

For Composer-backed plugins, decide whether the distributable ZIP includes `vendor/` and document that decision. For plugins that ship data packs, include the pack files, schemas, validation command, and smoke-test checklist. A release should be reproducible by another maintainer, not assembled by memory.
