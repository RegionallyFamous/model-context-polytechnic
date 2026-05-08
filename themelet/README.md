# Model Context Polytechnic Themelet

This directory contains a tiny WordPress theme that renders the Model Context Polytechnic admissions site as a static page.

Install `model-context-polytechnic-themelet/` into `wp-content/themes/` and activate it like a normal theme. You can also ZIP that folder and upload it through **Appearance > Themes > Add New > Upload Theme**. WordPress gets the expected theme files, `wp_head()`, `wp_footer()`, enqueued styles/scripts, typography, screenshot, local assets, restrained micro-interactions, and reduced-motion safeguards; visitors get the same focused admissions site without any page-builder or database dependency.

The themelet is intentionally separate from the MCP plugin. Use it when the site itself should look like the Model Context Polytechnic campus while the plugin handles the MCP server and course APIs.

The admissions copy should match the current hands-off school journey: `learning_status.story_script` is the verbose campus narration, `get-campus-scene` is MCP image content for clients that can render it, and graduation asks the learner to submit confidence and reflection feedback about future WordPress plugin work.

The live campus copy points LLMs to:

```text
https://joinmcpoly.com/mcp/wordpress-plugin-craft
```

That endpoint is POST-based MCP HTTP. A browser GET may show `405 Method Not Allowed`; use an MCP client or the repository smoke tests to verify the course server.
