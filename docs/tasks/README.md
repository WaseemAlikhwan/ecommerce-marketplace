# Short Task Workflow

Large implementation prompts are stored here once and executed as small slices.

## Active tasks

| File | Status |
|------|--------|
| `cart-c1.md` | READY — planning only; implement C1-A…D only when explicitly requested |
| `storefront-s8c-recovery.md` | DONE — S8C accepted |

## Rules

1. Keep permanent decisions in the existing ADR, architecture, business-rule, and development-plan documents.
2. Keep only temporary execution details in `docs/tasks/`.
3. Split work so one slice has one primary outcome and its focused tests.
4. Do not defer tests for a slice to a later slice.
5. Run the full Docker suite and browser matrix only in the final gate slice.
6. Mark interrupted work `IN PROGRESS`; never document it as complete.
7. Archive or delete a task file after its final gate is accepted.

## Short Prompt Template

```text
Implement only <SLICE> from @docs/tasks/<task>.md.
Read its referenced ADR/rules and inspect the current code.
Run the focused checks listed for this slice.
Do not start the next slice, commit, or push.
Return changed files, test counts, blockers, and the next slice name.
```
