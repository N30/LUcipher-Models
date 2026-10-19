# Phase 7 — Release

**Status:** prepared 2026-10-19; **waiting on Neo** for every step marked 🔴 · **Depends on:** phase 6.

## What is ready

- `CHANGELOG.md` carries the full 4.0.0 section (phases 2–6) under "unreleased (tag pending)", with the
  breaking changes named at the top; 3.0.1 and 3.0.0 below it; `UPGRADE.md` walks 2.x → 4.0.
- `composer.json`: `lucipher/models`, `^12`, `authors`, `homepage`, `keywords`, `proprietary`, no `version`
  field (the tag is the version), `composer validate --strict` clean.
- The branch `claude/lumodels-production-01cqya` is green on CI at every commit since phase 1; `master`
  still points at 3.0.0 plus whatever `fix/3.0.1-security` merged.

## Semver

4.0.0, not 3.1: Laravel 11 is dropped, the API envelope changes, `Livewire\Master` and the `*FromData`
hooks are removed, `lorisleiva/laravel-actions` leaves `require`. Everything deprecated (the action-shaped
API routes, the Verbs adapters, `Events\Index`/`Read`, the misspelled `$allow_any_unencrypred_access`) stays
through 4.x and goes in 5.0. The `2.x` branch is untouched.

## The release, step by step (none of it runs without the 🔴 answer)

1. 🔴 **Merge to `master`.** Open a pull request from `claude/lumodels-production-01cqya` into `master`
   (one squash or a merge commit, Neo's call), review, merge once CI is green. Default: a merge commit, so
   `docs/plans/` keeps its history per slice.
2. Set the date: `## 4.0.0 — 2026-MM-DD` in `CHANGELOG.md`, one commit on `master`.
3. 🔴 **Tag `v4.0.0`** on that commit. Default: an annotated tag whose message is the breaking-changes
   paragraph from the changelog.
4. 🔴 **Distribution.** Default (phase 2): a private VCS repository in the consuming apps' `composer.json`
   (`"type": "vcs", "url": "https://github.com/N30/LUcipher-Models"`), no Packagist. Private Packagist or
   Satis only if Neo wants `composer require` without the repository entry.
5. 🔴 **Visibility.** The repository stays private. Making it public needs a license decision
   (`proprietary` today) and a pass over `docs/plans/` and the study references for anything internal.
6. After the tag: `3.0.x` receives security fixes only (branch `3.0.x` from the 3.0.1 commit if one is
   needed); `2.x` as before.

## 🔴 Questions for Neo, batched (defaults in force; nothing irreversible happens without you)

- 🔴 Merge `claude/lumodels-production-01cqya` into `master` now? Default: yes, via a pull request you
  approve; I open it on your word.
- 🔴 Tag `v4.0.0` after the merge? Default: yes, annotated.
- 🔴 Distribution: private VCS repository (default) or private Packagist?
- 🔴 Keep the repository private (default) or make it public? If public: which license?
- 🔴 `composer.json` now names you as the author (neo@all1web.com) and the GitHub repository as homepage;
  keep, or change?
- 🔴 Reinstall into `products`: out of scope by the earlier default; say the word and the next phase is
  the `products` integration (its `composer.json` points at the private repository, `lu:install`,
  the policies it already has).
