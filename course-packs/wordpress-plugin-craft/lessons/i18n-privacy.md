Internationalization is not garnish; it is part of WordPress citizenship. Use translation functions for user-facing strings and escape translated output in context. Privacy matters whenever a plugin stores personal data, sends data to third parties, logs identifiers, or creates exports/erasers. Sources: https://developer.wordpress.org/plugins/internationalization/ and https://developer.wordpress.org/plugins/privacy/

Common failure: hard-coded admin strings and silent tracking. Both are avoidable.

Anonymous public learning data still deserves a policy. If a plugin stores attempts, prompts, logs, generated content, or opaque learner handles, say what is stored, whether plaintext secrets are stored, how long records are retained, and how the operator can change that retention. Prefer hashes for bearer-like handles, cap payload sizes, and prune stale public data on a schedule.
