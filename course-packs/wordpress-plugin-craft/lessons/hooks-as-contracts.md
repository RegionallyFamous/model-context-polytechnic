Actions and filters are WordPress extension contracts. A plugin should hook into WordPress deliberately and expose its own hooks only where extension is useful. Hook names should be unique, predictable, and version-stable. Callback signatures should be documented because third-party code may come to depend on them. Source: https://developer.wordpress.org/plugins/plugin-basics/

Common failure: custom hooks that expose half-built internal arrays. Prefer small, stable payloads and keep internal implementation details private.
