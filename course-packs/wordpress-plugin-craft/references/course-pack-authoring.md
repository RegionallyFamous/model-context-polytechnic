# Course Pack Authoring Checklist

A course pack is curriculum, not incidental fixture data. Treat it like a public API.

- Keep `course.json` focused on structure: course identity, instructions, modules, ordering, lesson file paths, exercise file paths, and public references.
- Write lessons as Markdown files under `lessons/` so they are reviewable in ordinary diffs.
- Write exercises as JSON files under `exercises/` so prompts, hints, rubrics, passing scores, and expected output schemas stay machine-checkable.
- Keep bibliography in `sources.json`; every serious technical claim should have a credible source trail.
- Give every lesson and exercise a stable lowercase dash slug. Changing slugs breaks stored progress, search results, and future references.
- Prefer deterministic rubrics with `required_terms` and `any_terms`. If a criterion cannot be checked automatically, make that explicit and expect self-review.
- Run the ten-student cohort in `composer course-lab` after significant edits. Treat repeated cohort friction as course evidence, not as automatic permission to rewrite everything.
- Run `composer course-packs:validate` before packaging or release.
- Do not let course files point outside their pack directory. A pack should be portable and boring to install.
