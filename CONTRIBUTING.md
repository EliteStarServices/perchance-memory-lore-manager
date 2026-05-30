# Contributing

## Workflow

- Work on a feature branch for each change.
- Keep each commit focused on one feature, fix, or cleanup.
- Validate the touched files before merging back to `main`.
- Tag stable checkpoints after a known-good state.

## Branching

- `main` is the integration branch.
- Use short-lived branches for experiments and bug fixes.
- Merge only after review or validation.

## Commit style

- Prefer small, descriptive commits.
- One commit should explain one logical change.
- If a change is risky, split it into smaller commits that are easy to revert.

## Releases

- Use tags such as `v0.1.1`, `v0.2.0`, and so on for stable checkpoints.
- Treat tags as rollback points and release anchors.
