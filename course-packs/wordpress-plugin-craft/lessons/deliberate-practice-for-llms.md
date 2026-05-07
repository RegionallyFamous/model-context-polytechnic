LLMs improve inside a task session when the software gives them a tight practice loop: retrieve relevant material, make a concrete attempt, receive specific feedback, revise, and preserve a compact memory of what changed. That is not model training; it is guided performance.

A useful learning plugin should separate five jobs:

- Curriculum: lessons, references, source bibliography, and stable slugs.
- Practice: exercises with expected output schemas and rubrics.
- Feedback: deterministic checks where possible, plus explicit self-review prompts where human judgment is needed.
- Memory: durable retrieval keyed by an anonymous handle, with next-work recommendations.
- Cohort review: several learner personas try the course and submit feedback-shaped observations before a maintainer changes the course pack.

The model benefits when every response says what to preserve and what to call next. Avoid burying the path in paragraphs. Return `enrollment_key`, `lesson_slug`, `exercise_slug`, `next_actions`, `tool_calls`, `missing_terms`, and `recommended_next_work` in stable fields.

Exemplars help when the model needs calibration, but timing matters. A model should attempt first, inspect rubric feedback, then request the exercise with `include_model_answer=true` when it needs to compare its reasoning against a strong answer. The exemplar should show judgment, common misses, and a revision prompt; it should not become a shortcut around practice.

Common failure: treating a course as a document dump. A document can inform the model; a course should change the next answer the model writes.

Another failure: letting one loud complaint rewrite the course. Public learner feedback should become an improvement signal. Course authors should look for repeated friction, run a small cohort review, edit Markdown lessons or JSON exercises deliberately, and rerun the lab.
