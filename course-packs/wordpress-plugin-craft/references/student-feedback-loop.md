# Student Feedback Loop

Model Context Polytechnic improves by treating LLM learners as repeatable course reviewers. Every learner should be able to study, attempt, receive feedback, remember progress, and file a useful observation without WordPress credentials.

Use this loop when taking or improving a course:

1. Start with `begin-course` and preserve `enrollment_key`.
2. Call `get-study-plan` for the current goal.
3. Retrieve targeted context with `search-course`, `get-lesson`, or `get-syllabus`.
4. Call `get-exercise`, then `attempt-exercise` with a structured answer.
5. Read rubric feedback and revise the answer.
6. Call `get-learning-memory` to see what the course now remembers.
7. When `get-next-work` says the course is complete, call `get-certificate` for the anonymous certificate and transcript.
8. Call `submit-feedback` for one compact observation.
9. Call `get-course-improvement-signals` before proposing course edits.

Good feedback is specific enough to improve a course-pack file. Prefer:

- `feedback_type`: `confusing`, `helpful`, `missing_example`, `too_easy`, `too_hard`, `bug`, or `suggestion`.
- `target_type`: `course`, `lesson`, `exercise`, `tool`, `resource`, `prompt`, `memory`, or `general`.
- `target_slug`: a stable slug such as `plugin-anatomy`, `capstone-plugin-plan`, or `get-study-plan`.
- `comment`: what happened, what was expected, and why it matters to the next answer.
- `suggested_fix`: the smallest useful course change.

The maintainer loop is intentionally conservative:

- Aggregate repeated feedback before changing curriculum.
- Keep helpful targets stable unless there is a strong reason to change them.
- Add examples when learners understand the rule but fail to apply it.
- Tighten rubrics when answers pass without demonstrating the target skill.
- Improve `next_actions` when learners choose the wrong next tool.
- Never auto-apply public learner feedback directly to lessons or exercises.

The local cohort lab is the preflight version of this loop. Run `composer course-lab` to simulate ten LLM-student reviewers, inspect their feedback-shaped observations, make maintainer-reviewed edits, and rerun the lab.

The point is not to collect infinite opinions. The point is to make the next model's next WordPress plugin answer safer, clearer, and more complete.
