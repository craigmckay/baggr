# Branching and releases

`main` is the only long-lived branch. It always holds work that has passed the tests and is ready to deploy.

## The cycle for each change

1. **Spec it.** Write a GitHub issue, checked against the code first. `docs/SPEC.md` is the source for what and why.
2. **Branch from `main`** as `feature/<short-name>`, for example `feature/landing-page`. One branch per issue.
3. **Build and commit.** The commit that finishes the issue ends with `Closes #n`. Use `refs #n` for commits that only contribute to it.
4. **Open a pull request into `main`.** The Actions workflow (`.github/workflows/tests.yml`) runs the tests on every push and pull request. Merge only when it passes.
5. **Delete the branch** once it's merged, locally and on GitHub.

## Releases

A release is a tag on `main`, not a branch.

- When `main` is deployed to baggr.co.uk, tag that commit `release/N`, with N counting up from the last release.
- A release can include several merged pull requests.
- The tag message is a short summary of what's new, and is the basis for the version history.
- Deployment is manual, by zip. See `deploy/README.md`.

## History

- `release/1` started as a branch. Its last commit (`6bf86e7`, the hill browser and per-hill pages) was tagged `release/1` on 16 Nov 2026 and the branch was deleted. No new `release/` branches should be created.
- `release/2` (`bcc3db7`) added the Geograph hill photos.
