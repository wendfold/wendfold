## Summary

<!-- What does this change do, and why? 2 to 5 sentences. -->

Closes #

## Scope

- [ ] `com_wendfold`
- [ ] `plg_user_wendfold`
- [ ] `plg_task_wendfold`
- [ ] `plg_privacy_wendfold`
- [ ] `pkg_wendfold`
- [ ] Build / release / CI (`build/`, `scripts/`, `.github/`)
- [ ] Documentation

## Type of change

- [ ] Bug fix
- [ ] New feature
- [ ] Refactor (no behavior change)
- [ ] Build, packaging, or release
- [ ] Documentation only

## Checklist

- [ ] CI passes (PHP lint, PHPCS report reviewed, XML checks, build + checksums).
- [ ] Follows Joomla 6 patterns: namespaces, `#__` table placeholders, Web Asset Manager, ASCII-only language files.
- [ ] State-changing requests check CSRF tokens; ACL checks are preserved; input is filtered and output escaped.
- [ ] Schema changes include an update SQL file for the target version.
- [ ] `## Changelog` section below filled in (`CHANGELOG.md` and `updates/changelog.xml` not edited).
- [ ] Docs updated (if behavior, install, or release steps changed).
- [ ] No secrets, credentials, or real member data are included.

## Changelog

<!-- The user-facing changelog line(s) for this change, one bullet each. Do not edit
CHANGELOG.md or updates/changelog.xml in this PR: the release changelog PR collects
this section from every merged PR. Write "None" if the change is not user-facing. -->

None

## Testing

<!-- What did you test, and on which Joomla / PHP / database versions? -->

- [ ] Built ZIPs with `python3 build/build_release.py` and installed `pkg_wendfold` on a clean Joomla 6 site
- [ ] Tested the affected admin and/or front-end workflows
- [ ] Tested permissions and validation/error paths

## Screenshots

<!-- Before/after for UI changes. -->

## Upgrade or release notes

<!-- Anything affecting manifests, SQL, packaging, or existing installs. -->
