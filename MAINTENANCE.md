# Maintenance Workflow

This repository is maintained with a simple git-first workflow so changes can be reviewed, reverted, and released safely.

## Branches

- `main` is the working integration branch.
- Create a feature branch for each change or bugfix.
- Merge back to `main` only after the change is validated.

## Commit cadence

- Commit after each coherent feature addition, bugfix, or cleanup.
- Keep commits focused on one purpose.
- If a change is larger, split it into smaller commits that can be reverted independently.
- Tag a stable checkpoint after a known-good set of changes.

## Releases and rollback

- Use annotated tags such as `v0.1.1`, `v0.2.0`, and so on for stable checkpoints.
- If a change causes trouble, compare against the previous tag or revert the specific commit.
- Treat tags as safe rollback points and GitHub releases as published milestones.

## Practical maintenance rules

- Make the smallest safe change that fixes the issue.
- Validate the touched files before committing.
- Prefer branches for experiments and risky fixes.
- Keep generated artifacts out of version control unless they are intentionally part of the release.

## How this repo is currently used

- The plugin source lives in this repository.
- The existing `v0.1.0` tag is the first stable checkpoint.
- Future feature work should land as separate commits and get tagged when the result is stable.
