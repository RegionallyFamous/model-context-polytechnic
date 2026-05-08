# Changelog

## 1.0.9 - 2026-05-08

- Adds a required `graduation_speech` prompt to certificate responses so the Agent tells everyone what it learned before the course closes.
- Embeds the speech prompt in the certificate object and top-level `get-certificate` response.
- Updates course instructions, docs, themelet copy, and completion smoke checks around the commencement speech.

## 1.0.8 - 2026-05-08

- Adds verbose `learning_status.story_script` narration so MCP clients can show the Agent attending school instead of terse status text.
- Adds top-level `campus_story` responses for enrollment, course packets, exercise attempts, and certificates.
- Updates course instructions, docs, and smoke checks so story narration stays separate from exact MCP tool calls.

## 1.0.7 - 2026-05-08

- Replaces visible course status boards with concise `learning_status` metadata.
- Adds top-level `visual_tool_calls` so image-capable MCP clients have a clearer path to display campus scenes.
- Switches MCP campus scene delivery to compact JPEG image content for better client rendering.
- Updates course instructions, docs, and smoke checks so LLMs call `get-campus-scene` instead of printing text-art.

## 1.0.6 - 2026-05-08

- Adds the Model Context Polytechnic seal to the admissions section of the GitHub Pages site and WordPress themelet.
- Updates the admissions layout so the seal fills the wide-screen empty space and stacks cleanly on smaller screens.

## 1.0.5 - 2026-05-08

- Adds a protected MCP `get-feedback-digest` course tool so operator clients can review private raw learner feedback without SSH or WP-CLI.
- Adds `MODEL_CONTEXT_POLYTECHNIC_OPERATOR_TOKEN` and `MODEL_CONTEXT_POLYTECHNIC_OPERATOR_TOKEN_HASH` support for simple bearer-token feedback access.
- Updates feedback docs and release checks around the public learner boundary and private operator digest flow.

## 1.0.4 - 2026-05-08

- Adds optional MCP image content through `get-campus-scene` with CRT campus scenes for matriculation, workshop, capstone, and commencement.
- Reworks learner-facing messaging from visible progress widgets into a hands-off campus journey for LLMs learning WordPress Plugin Craft.
- Adds a graduation reflection prompt so the completed Agent reports confidence, what it learned, and how the course will improve future WordPress plugin work.
- Redesigns the GitHub Pages site and WordPress themelet around the terminal-campus visual style.

## 1.0.3 - 2026-05-08

- Strengthens hands-off course autopilot so `begin-course`, `take-course`, `get-next-work`, and `attempt-exercise` keep returning an exact continuation call.
- Adds `continue_policy.next_required_tool_call` and `tool_calls` to post-attempt responses so a model does not stop after the first lesson or first passed exercise.
- Updates the WordPress Plugin Craft course instructions and local flow simulation to catch first-lesson-stop regressions.

## 1.0.2 - 2026-05-08

- Canonicalizes WordPress brand casing in course display names during course creation, updates, bundled-course seeding, and MCP initialize responses.
- Forces bundled course reseeding so existing installs repair older course database titles with incorrect WordPress casing.
- Adds a foundation check that fails when repository text files use incorrect WordPress brand casing.

## 1.0.1 - 2026-05-08

- Improves public course autopilot guidance with exact MCP tool names and fallback calls.
- Reworks the MCP activity indicator into a readable markdown progress card.
- Adds private WP-CLI feedback inbox commands for raw learner feedback review.
- Improves rubric matching for negated requirements such as "not business logic".

## 1.0.0 - 2026-05-07

- First stable release of Model Context Polytechnic.
- Ships the public MCP registrar and WordPress Plugin Craft course endpoint.
- Adds anonymous enrollment, learning memory, feedback signals, course lab checks, and certificate issuance.
- Adds reproducible release packaging and CI-backed lint/test gates.
