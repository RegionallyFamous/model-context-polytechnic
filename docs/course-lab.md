# Course Lab

The Course Lab is the repeatable testing loop for making Model Context Polytechnic more useful to LLM learners.

It checks whether a course can be taken like an MCP student:

1. Connect to the course endpoint.
2. Call `begin-course`.
3. Preserve `enrollment_key`.
4. Retrieve next work.
5. Read the lesson.
6. Attempt the exercise.
7. Retrieve learning memory.
8. Submit feedback.
9. Inspect improvement signals before proposing edits.

## Run The Lab

```bash
composer course-lab
composer cohort-lab
composer stress-lab
```

Useful variants:

```bash
php bin/course-lab.php --passes=12
php bin/course-lab.php --students=10
php bin/course-lab.php --json
php bin/course-lab.php --agent-brief
php bin/course-lab.php --fail-on=warning
```

The default lab fails only on critical findings. Warnings and notices are improvement signals.

## Ten-Student Cohort

The default lab also sends ten deterministic LLM-student profiles through the course:

- First-day orientation.
- Memory recovery.
- Security review.
- Storage and migration.
- Blocks and JavaScript.
- Performance and reliability.
- Release readiness.
- Course-pack authoring.
- LLM-native interface design.
- Capstone maintainer judgment.

Each student returns feedback-shaped output: `feedback_type`, `target_type`, `target_slug`, `rating`, `comment`, and `suggested_fix`. This mirrors what a real learner would send through `submit-feedback`, but it is safe to run locally before publishing a course edit.

Use the cohort to answer one question: "Would a different kind of LLM learner know what to do next, and would its next WordPress plugin answer improve?"

## Stress Lab

The stress lab adds two sharper feedback sources:

- Scenario students in `tests/course-scenarios/*.json`.
- Golden exams in `tests/golden-exams/*.json`.

Scenario students test friction instead of simple coverage: forgotten `enrollment_key`, wrong slugs, skipped lessons, bad answers, oversized answers, public/private boundary confusion, unsafe write paths, release readiness, course-authoring feedback, and capstone planning.

Golden exams compare weak and strong answers against the course's real exercise rubrics. The weak answer should fail, the strong answer should pass, and the score delta should stay large enough to prove the rubric can tell the difference.

Run it with:

```bash
composer stress-lab
php bin/stress-lab.php --json
```

Add a new stress case when a real learner gets confused, when a tool response changes, or when an exercise rubric is too easy to game.

## Parallel Student-Reviewer Loop

When making large course changes, run one local cohort lab and one parallel reviewer:

1. Run `composer course-lab`.
2. Copy the brief from `php bin/course-lab.php --agent-brief`.
3. Spawn a read-only parallel student-reviewer with that brief.
4. While the reviewer studies, make non-overlapping improvements locally.
5. Integrate repeated or high-severity reviewer findings.
6. Run `composer release:check`.

The reviewer should not edit files. Its job is to take the course repeatedly and report friction.

## Improvement Rule

The course should improve from evidence, not from one dramatic complaint.

Prefer changes when at least one is true:

- The lab reports a critical issue.
- Multiple student-reviewer passes report the same confusion.
- Feedback points to a missing example, unclear schema, weak rubric, or broken next action.
- Exercise outcomes show repeated low pass rates.

Do not auto-apply public learner feedback directly to course content. Feedback should become a signal, then a maintainer-reviewed course-pack change.
