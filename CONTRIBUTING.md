# Contributing to Wendfold

Thanks for helping improve Wendfold. This guide covers how to propose changes and what CI checks.

Everyone taking part is expected to follow the [Code of Conduct](CODE_OF_CONDUCT.md).

## Ways to contribute

- **Bugs:** email support@wendfold.com (see [SUPPORT.md](SUPPORT.md)). Include versions and exact steps to reproduce. Once the repository is public, you'll also be able to open an issue with the bug report form.
- **Features:** email support@wendfold.com. Describe the problem before the solution. Once the repository is public, you'll also be able to use the feature request form.
- **Security issues:** don't report them in public. Follow [SECURITY.md](SECURITY.md).
- **Code and docs:** open a pull request. Before you start large changes (architecture, packaging, database schema, permissions), get in touch first (by email, or by issue once the repository is public) so we can agree on scope.

## Repository layout

| Path | Contents |
| --- | --- |
| `com_wendfold/` | Main component (admin and site) |
| `plg_user_wendfold/` | User plugin: Joomla user lifecycle integration |
| `plg_task_wendfold/` | Task plugin: Joomla Scheduled Tasks integration |
| `pkg_wendfold/` | Package installer manifest and script |
| `build/build_release.py` | Builds the installable ZIPs and `SHA256SUMS.txt` |
| `scripts/release/` | Release helpers, such as writing sha256 values into update XML |
| `docs/` | Installation, update, and release documentation |
| `.github/` | CI and release workflows, issue and PR templates |

Wendfold is presented to users as **one product**. `pkg_wendfold` is the only installer that is published. The child ZIPs (`com_wendfold`, `plg_user_wendfold`, `plg_task_wendfold`) are internal build outputs: the package carries them under `constituents/`, and they are never published on their own.

## Development setup

You need:

- PHP 8.3, 8.4, or 8.5 with the `xml`, `dom`, and `simplexml` extensions, at or above the security floor CI enforces: 8.3.36, 8.4.27, or 8.5.12 (CVE-2026-109618 and CVE-2026-109619)
- Python 3.9 or newer, for the build script
- Composer 2, to install the pinned PHP_CodeSniffer and Joomla coding standard (dev only)
- A local Joomla 6.x site for manual testing

Until the repository is public, cloning needs repository access.

```bash
git clone https://github.com/wendfold/wendfold.git
cd wendfold
composer install   # dev tools only, into vendor/ (git-ignored, never shipped)
```

## Checks CI runs

Run these before you push. They're the main steps `.github/workflows/ci.yml` runs on PHP 8.3, 8.4, and 8.5 (jobs `CI (PHP 8.3)`, `CI (PHP 8.4)`, and `CI (PHP 8.5)`). CI also runs a separate `Schema` job (see below).

```bash
# 0. PHP security floor: php -v must show at least 8.3.36, 8.4.27, or 8.5.12.
#    CI updates PHP to the latest patch and fails the job below the floor.
php -v

# 1. PHP syntax
git ls-files -z '*.php' | xargs -0 -n1 php -l

# 2. Unit tests (tests/, see phpunit.xml.dist)
vendor/bin/phpunit --configuration phpunit.xml.dist

# 3. PHPStan, level 5, new findings only (needs JOOMLA_ROOT, see below)
JOOMLA_ROOT=/path/to/joomla-6.2.0 \
  vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G

# 4. Coding standard (Joomla, see phpcs.xml.dist)
#    a) Blocking: the PHP files your branch changes (fails on errors)
git diff -z --name-only --no-renames --diff-filter=ACMR origin/main...HEAD -- '*.php' ':(exclude,glob)**/tmpl/**' ':(exclude)vendor/' \
  | xargs -0 -r vendor/bin/phpcs --standard=phpcs.xml.dist --runtime-set ignore_warnings_on_exit 1 --
#    b) Report-only: the whole tree
vendor/bin/phpcs --runtime-set ignore_errors_on_exit 1 --runtime-set ignore_warnings_on_exit 1

# 5. XML well-formedness of manifests, forms, and update XML
git ls-files -z '*.xml' | xargs -0 -n1 php -r 'libxml_use_internal_errors(true); $d = new DOMDocument(); if (!$d->load($argv[1])) { fwrite(STDERR, "Malformed XML: {$argv[1]}\n"); exit(1); }'

# 6. Build and verify
python3 build/build_release.py
(cd build/dist && sha256sum -c SHA256SUMS.txt)
```

### PHPStan locally

PHPStan needs a stock Joomla 6.2.0 Full Package, because `phpstan/bootstrap.php` loads Joomla's autoloaders from it and refuses to run without it. Download `Joomla_6.2.0-Stable-Full_Package.zip` from the joomla-cms GitHub release, unpack it outside the checkout, and point the `JOOMLA_ROOT` environment variable at that folder. It must be the folder that contains `includes/defines.php` (the Joomla web root), not a parent folder. The Joomla site does not need to be installed or configured: the bootstrap only registers Joomla's autoloaders and the namespaces of core extensions, and never boots Joomla. If `JOOMLA_ROOT` is unset, empty, or has no `includes/defines.php`, PHPStan stops with `PHPStan needs JOOMLA_ROOT set to an unpacked Joomla 6.2.0 Full Package.` CI downloads the same ZIP and checks it against the SHA-256 pinned as `JOOMLA_SHA256` in `.github/workflows/ci.yml`; you can check yours the same way:

```bash
echo "<JOOMLA_SHA256 from ci.yml>  Joomla_6.2.0-Stable-Full_Package.zip" | sha256sum -c --strict
unzip -q Joomla_6.2.0-Stable-Full_Package.zip -d /path/to/joomla-6.2.0
JOOMLA_ROOT=/path/to/joomla-6.2.0 \
  vendor/bin/phpstan analyse -c phpstan.neon.dist --memory-limit=1G
```

`phpstan.neon.dist` sets `phpVersion: 80300`, so results are the same whichever supported PHP runs PHPStan. The baseline (`phpstan-baseline.neon`) was generated against 6.2.0, so another Joomla version can report findings CI does not.

### Schema job

The `Schema` job runs `scripts/ci/schema-check.sh` against MariaDB 11.8 and MySQL 8.4. It loads the install SQL, applies every update SQL file twice, compares the result with a fresh install, and runs the uninstall SQL. To run it locally, start a MySQL-compatible server and list it in `SCHEMA_SERVERS` (`host:port:user:password`, space-separated); you need bash, python3, php, and the mysql client:

```bash
SCHEMA_SERVERS='127.0.0.1:3306:root:secret' bash scripts/ci/schema-check.sh
```

### PHPCS

On a pull request, the `CI (PHP x.y)` jobs run a blocking step, **PHPCS on PHP files changed by this PR**. It checks each PHP file the PR adds, copies, modifies, or renames against `phpcs.xml.dist` and fails on any error. Warnings are listed but don't fail it. Deleted files, files the PR doesn't touch, layout templates under `tmpl/`, and `vendor/` are skipped. If you touch a file that already has findings, fix them in that file; `vendor/bin/phpcbf <file>` fixes most of them. Run step 4a above before you push to see the same result (fetch `origin/main` first).

The full-tree PHPCS step stays report-only, because the existing code still has known findings. Once the codebase is clean, we'll remove its `ignore_*_on_exit` flags so it fails CI too.

## Coding guidelines

- Follow the Joomla coding standard, as configured in `phpcs.xml.dist`. Use the PHP_CodeSniffer from `composer install` (`vendor/bin/phpcs`), not a global PHP_CodeSniffer 4 install: `joomla/coding-standards` needs PHP_CodeSniffer 3.

  > **Coding-standard pins (accepted risk).** The project pins `joomla/coding-standards` **3.0.0-alpha**. It is the newest release (January 2020), but it is still an alpha and is no longer maintained. It requires PHP_CodeSniffer 3.x (pinned to **3.13.6**) and does not support PHP_CodeSniffer 4. We accept this risk. Dependabot ignores PHP_CodeSniffer major updates. Review both pins (`composer.json`, `composer.lock`) if Joomla publishes a stable release or one that supports PHP_CodeSniffer 4.

- Use Joomla 6 patterns: namespaced classes, service providers, `DatabaseInterface::createQuery()`, and `#__` table placeholders in PHP queries. Don't use legacy APIs such as `Factory::getDbo()`.
- Treat all input as untrusted. Filter input, escape output, check CSRF tokens on state-changing requests, and keep ACL checks.
- Keep business logic in the component. Plugins should stay thin adapters.
- Load assets through the Web Asset Manager (`media/joomla.asset.json`).
- Keep language `.ini` files ASCII-only.
- If you change a schema, add an update SQL file under `com_wendfold/admin/sql/updates/mysql/` named for the target version.
- New PHP files should start with the standard header:

  ```php
  /**
   * @package     Wendfold
   * @copyright   Copyright (C) 2026 Adan Lozano
   * @license     GNU General Public License version 2 or later; see LICENSE.txt
   */
  ```

## Branches, commits, and pull requests

- Branch from `main`, using short-lived names like `fix/...`, `feat/...`, `docs/...`, `build/...`, or `refactor/...`.
- Use [Conventional Commits](https://www.conventionalcommits.org/) style messages, such as `fix: keep plugin state on package update`.
- Keep each PR to one change. Fill in the PR template, link related issues, and add screenshots for UI changes.
- Write the user-facing changelog line(s) under the `## Changelog` heading in the PR body (or `None`). Don't edit [CHANGELOG.md](CHANGELOG.md) or `updates/changelog.xml` in feature PRs; one release changelog PR collects these sections before each release (see [RELEASING.md](RELEASING.md)).
- Changes to `main` land through pull requests with passing CI (`CI (PHP 8.3)`, `CI (PHP 8.4)`, and `CI (PHP 8.5)`, each including the PHP security floor step, plus `Schema`), and history stays linear (squash or rebase merge).
- Don't commit generated artifacts (`build/dist/`, `build/staging/`, `pkg_wendfold/constituents/*.zip`), secrets, or real member data.

## Testing your change

If you touched packaging, manifests, or installer code:

- build fresh ZIPs, install `pkg_wendfold` on a clean Joomla 6 site, and confirm the component and both plugins install and the plugins are enabled
- install over an existing install and confirm plugin states are kept
- confirm uninstalling the package removes the whole suite cleanly

If you touched application code, smoke-test the flows you changed and the nearby ones. That includes create, edit, and save, validation errors, permissions, and both logged-in and public views.

## License

Wendfold is licensed under GPL-2.0-or-later. By submitting a contribution, you agree that it's licensed under the same terms (see [LICENSE.txt](LICENSE.txt)). You must have the right to submit it.
