# Releasing Wendfold

This is the maintainer process for cutting a release. A release is a **signed annotated git tag** `vX.Y.Z`. The tag triggers `.github/workflows/release.yml`, which builds the ZIPs, checks their checksums, builds the **site-upload bundle** for wendfold.com, and creates a **draft** GitHub release. The maintainer reviews the draft. After the maintainer's approval, the maintainer uploads the bundle to wendfold.com and verifies it with Joomla's update user agent. Only then is the draft published by hand and the JED listing submitted. The workflow never publishes or uploads on its own.

Releases are **package-only**. The only installer that is published is `pkg_wendfold_vX.Y.Z.zip`, with its checksum file `SHA256SUMS-vX.Y.Z.txt`:

- **wendfold.com is the primary download:** `https://wendfold.com/downloads/pkg_wendfold_vX.Y.Z.zip` and `https://wendfold.com/downloads/SHA256SUMS-vX.Y.Z.txt`.
- **GitHub Releases is a mirror** once the repository is public. The draft release carries exactly four assets: `pkg_wendfold_vX.Y.Z.zip`, `SHA256SUMS-vX.Y.Z.txt`, `pkg_wendfold.xml` (with `<sha256>` filled in), and `wendfold-site-bundle-vX.Y.Z.zip`.

The child ZIPs (`com_wendfold_*`, `plg_user_wendfold_*`, `plg_task_wendfold_*`) and the build's `SHA256SUMS.txt` are internal build outputs. They are never published on their own, anywhere; the release workflow's guard step fails the job if one is staged as a release asset.

## 1. Choose the version (Semantic Versioning)

Wendfold follows [SemVer 2.0.0](https://semver.org/). All four extensions always share one version.

| Change | Bump | Example |
| --- | --- | --- |
| Backward-incompatible change (removed feature, breaking schema or config, dropped Joomla or PHP version) | MAJOR | 1.4.2 → 2.0.0 |
| New backward-compatible functionality, including new schema migrations | MINOR | 1.4.2 → 1.5.0 |
| Backward-compatible bug or security fix | PATCH | 1.4.2 → 1.4.3 |

Pre-releases use `-rc.N`, for example `v1.5.0-rc.1`. The workflow marks those drafts as pre-releases.

## 2. Prepare the release branch

```bash
git switch main && git pull --ff-only
git switch -c release/vX.Y.Z
```

### 2a. Bump the manifest versions

Set `<version>X.Y.Z</version>`, and update `<creationDate>` to the release date, in **all five** manifests:

- `pkg_wendfold/pkg_wendfold.xml`
- `com_wendfold/wendfold.xml`
- `plg_user_wendfold/wendfold.xml`
- `plg_task_wendfold/wendfold.xml`
- `plg_privacy_wendfold/wendfold.xml`

`build/build_release.py` stops if they don't match, and the release workflow stops if they don't match the tag.

If the database schema changed, add `com_wendfold/admin/sql/updates/mysql/X.Y.Z.sql`.

### 2b. Update the CHANGELOG

Feature PRs don't edit `CHANGELOG.md` or `updates/changelog.xml`; each PR body has a `## Changelog` section with its user-facing line(s), or `None`. Before the RC or release tag, collect those sections from every PR merged since the last tag:

```bash
LAST_TAG=$(git describe --tags --abbrev=0)
SINCE=$(git log -1 --format=%cI "$LAST_TAG")
gh pr list --state merged --base main --limit 200 \
  --search "merged:>$SINCE" --json number,title,body \
  --jq '.[] | "#\(.number) \(.title)\n\(.body | capture("(?s)(^|\\n)## Changelog[ \\t]*\\r?\\n(?<c>.*?)(\\r?\\n## |\\z)").c // "MISSING")\n"'
```

If there is no tag yet, set `SINCE` by hand to the date the previous changelog was last collected (YYYY-MM-DD). Skip `None` entries, follow up on any `MISSING` (the pattern only matches a `## Changelog` heading at the start of a line, not the checklist text that mentions it), and write the lines into `CHANGELOG.md` as below. Make the `CHANGELOG.md` and `updates/changelog.xml` changes together in **one** release changelog PR, and merge it before creating any tag.

In `CHANGELOG.md`, rename `## [Unreleased]` to `## [X.Y.Z] - YYYY-MM-DD`, using the real release date. Then add a new empty `## [Unreleased]` section above it. If a `## [X.Y.Z]` section already exists (as it does for 1.0.0), move any `[Unreleased]` entries into it and set the date instead, so there is only one `## [X.Y.Z]` heading. The changelog has no link references at the bottom, so there are no links to update.

Tag maintainer-only bullets (CI, release tooling, repository docs) with `<!-- internal -->` at the end of the bullet. They stay in `CHANGELOG.md` but are left out of everything public: `updates/changelog.xml`, the wendfold.com changelog page, and the draft release notes, which the release workflow builds from this section with the same script (section 4, step 9).

Then regenerate the public changelog from that section and **commit its output before tagging**. The release workflow copies `updates/changelog.xml` from the tree into the site bundle, so whatever is committed is what gets published:

```bash
python3 scripts/release/generate-changelog.py --changelog CHANGELOG.md \
  --section X.Y.Z --version X.Y.Z --date YYYY-MM-DD \
  --manifest pkg_wendfold/pkg_wendfold.xml --update-xml updates/pkg_wendfold.xml \
  --page-out /tmp/wendfold-changelog-page.md --xml-out updates/changelog.xml \
  --notes-out /tmp/wendfold-release-notes.md
git add CHANGELOG.md updates/changelog.xml
```

The page text (`/tmp/wendfold-changelog-page.md`) is for <https://wendfold.com/changelog>, and `/tmp/wendfold-release-notes.md` is a preview of the draft release notes. Neither is committed. Run the script again before tagging: `git diff --exit-code updates/changelog.xml` must show no change.

### 2c. Add the update XML entry

In `updates/pkg_wendfold.xml`, the package update server served at `https://wendfold.com/updates/pkg_wendfold.xml`, add or update the `<update>` entry for `X.Y.Z` with its `<version>` and a `<downloadurl>` of exactly `https://wendfold.com/downloads/pkg_wendfold_vX.Y.Z.zip`. wendfold.com is the primary download host. GitHub Releases is only a mirror, once the repository is public. The release workflow fails if the URL is anything else. Keep `<sha256>SHA256_PLACEHOLDER</sha256>` (or leave that element empty). That text is not a real hash. `.github/workflows/release.yml` copies the file and `scripts/release/set-update-sha256.py` replaces the `<sha256>` element with the sha256 of `pkg_wendfold_vX.Y.Z.zip`. The edited XML is a release asset and is not committed back, because the hash only exists once the ZIP is built.

### 2d. Verify locally

```bash
python3 build/build_release.py
(cd build/dist && sha256sum -c SHA256SUMS.txt)
```

Open a pull request, wait for green CI, then merge to `main` (squash or rebase).

## 3. Tag the release (signed, annotated)

Tag the merge commit on `main`:

```bash
git switch main && git pull --ff-only
git tag -s vX.Y.Z -m "Wendfold vX.Y.Z"
git tag -v vX.Y.Z            # confirm the signature
git push origin vX.Y.Z
```

- You need GPG or SSH signing set up (`git config gpg.format ssh` plus `user.signingkey`), and the public key added to your GitHub account so GitHub shows the tag as **Verified**.
- The workflow **fails on lightweight tags** and **warns** on annotated tags that GitHub can't verify.
- Never move or reuse a published tag. If something is wrong, release a new patch version.

## 4. What the release workflow does

On a pushed tag matching `v*.*.*`:

1. Runs the full CI workflow: the `CI (PHP 8.3)`, `CI (PHP 8.4)`, and `CI (PHP 8.5)` jobs (PHP security floor, lint, PHPUnit, PHPStan, PHPCS report, XML checks, build, checksum and layout checks) and the `Schema` job. The draft release job needs it to pass.
2. Checks the tag format, that the tag is annotated, and that all manifest versions match the tag.
3. Checks that the `<downloadurl>` of the matching `<update>` entry in `updates/pkg_wendfold.xml` is exactly `https://wendfold.com/downloads/pkg_wendfold_vX.Y.Z.zip`. If not, it fails with the expected and actual values.
4. Runs `python3 build/build_release.py`, then `sha256sum -c SHA256SUMS.txt`.
5. Prints the sha256 of every ZIP to the job log and the job summary.
6. Copies `updates/pkg_wendfold.xml` and writes `<sha256>` for the package ZIP into the matching `<update>` entry with `scripts/release/set-update-sha256.py`. **The step fails if `updates/pkg_wendfold.xml` doesn't exist** or has no entry for the version. The edited XML becomes a release asset and is **not** committed back to `main`.
7. Builds the **site-upload bundle** for wendfold.com and checks that it has exactly this layout (any other file fails the job):

   ```
   downloads/pkg_wendfold_vX.Y.Z.zip
   downloads/SHA256SUMS-vX.Y.Z.txt
   updates/pkg_wendfold.xml      <- <sha256> filled in
   updates/changelog.xml         <- only if updates/changelog.xml exists in the tree
   ```

   It also runs `sha256sum -c SHA256SUMS-vX.Y.Z.txt` inside `downloads/` and confirms the `<sha256>` in the bundled XML matches the ZIP. The bundle has no child ZIPs and no unversioned `SHA256SUMS.txt`. It's uploaded as the workflow artifact `wendfold-site-bundle-vX.Y.Z`, with `downloads/` and `updates/` at the top.
8. Stages the release assets and checks them. The staging step copies exactly four files and runs `sha256sum -c` on the checksum file:
   - `pkg_wendfold_vX.Y.Z.zip`
   - `SHA256SUMS-vX.Y.Z.txt` (lists only the package ZIP; the same file as `downloads/` in the bundle)
   - `pkg_wendfold.xml` (with `<sha256>` filled in)
   - `wendfold-site-bundle-vX.Y.Z.zip` (the same bundle, zipped)

   A guard step then fails the job if any staged file is a child ZIP (`com_*`, `plg_*`), is not on that allowlist, or is not a plain file; if an allowlisted file is missing; if the checksum file lists anything but the package ZIP; or if the bundle ZIP contains a child ZIP.
9. Creates a **draft** release named `Wendfold vX.Y.Z` (the GitHub mirror) with exactly those four assets. Its notes are the public part of the `CHANGELOG.md` section for the version, printed by `scripts/release/generate-changelog.py --notes-out`: the section's headings and bullets as written, without the bullets tagged `<!-- internal -->` and without any HTML comment. The wendfold.com download link, the install step, and `sha256sum -c SHA256SUMS-vX.Y.Z.txt` follow. If the section is missing, the job warns and the notes have only those three lines. If the script fails (for example, the version doesn't match the manifest, or the section has no public bullets), or an HTML comment is left in the notes, the job fails.

The workflow never uploads anything to wendfold.com.

The job has `contents: write` and no other permissions. Re-running it only replaces assets on a release that is still a draft: it deletes any asset that is not one of the four (for example, child ZIPs from an older workflow version) and uploads the four again. It refuses to touch a published release.

## 5. Review the draft

1. Open the draft under **Releases**. Check that it has exactly the four assets listed in section 4, step 8, and check the notes.
2. Download `pkg_wendfold_vX.Y.Z.zip` and `SHA256SUMS-vX.Y.Z.txt` and run `sha256sum -c SHA256SUMS-vX.Y.Z.txt`.
3. Install the package on a clean Joomla 6 test site, and also as an update over the previous release.
4. **Do not publish the draft yet.** It is published in section 6, step 7, after the files are live on wendfold.com and pass the re-test.

## 6. Publish on wendfold.com

wendfold.com is the primary download host and serves the Joomla update feed. The maintainer uploads the files and verifies them.

Nothing goes on the site before the maintainer's explicit approval, even if the GitHub draft looks finished.

### Files and exact target paths

The site bundle (`wendfold-site-bundle-vX.Y.Z.zip` on the draft release, or the `wendfold-site-bundle-vX.Y.Z` workflow artifact, kept for 90 days) holds exactly these files. Upload each one to the matching path under the site's web root, so it is served at the URL shown:

| Bundle file | Served at | Expected Content-Type | On each release |
|---|---|---|---|
| `downloads/pkg_wendfold_vX.Y.Z.zip` | `https://wendfold.com/downloads/pkg_wendfold_vX.Y.Z.zip` | `application/zip` | new file, never overwritten |
| `downloads/SHA256SUMS-vX.Y.Z.txt` | `https://wendfold.com/downloads/SHA256SUMS-vX.Y.Z.txt` | `text/plain` | new file, never overwritten |
| `updates/changelog.xml` | `https://wendfold.com/updates/changelog.xml` | `application/xml` or `text/xml` | replaced |
| `updates/pkg_wendfold.xml` | `https://wendfold.com/updates/pkg_wendfold.xml` | `application/xml` or `text/xml` | replaced, **last** |

These URLs must stay exactly as they are. They are the update server URL (`https://wendfold.com/updates/pkg_wendfold.xml`), which lives only in the `<updateservers>` entry in `pkg_wendfold/pkg_wendfold.xml`, and the `<downloadurl>` and `<changelogurl>` in the feed. The download landing page `https://wendfold.com/download` is website content and links to the ZIP above.

### Steps

1. The maintainer gets the bundle and checks it:

   ```bash
   gh release download vX.Y.Z -p 'wendfold-site-bundle-*.zip'
   unzip wendfold-site-bundle-vX.Y.Z.zip -d bundle && cd bundle
   (cd downloads && sha256sum -c SHA256SUMS-vX.Y.Z.txt)
   grep -o '<sha256>[^<]*</sha256>' updates/pkg_wendfold.xml          # must equal the hash above
   grep -o '<downloadurl[^>]*>[^<]*</downloadurl>' updates/pkg_wendfold.xml
   # must be https://wendfold.com/downloads/pkg_wendfold_vX.Y.Z.zip
   ```

2. The maintainer uploads with the hosting file manager or SFTP. Credentials and host details are kept outside this repository and never go in it. Upload as binary (SFTP does this; in a file manager, upload the files as they are and don't edit them in the browser). Upload in this order, so Joomla sites never see an update whose ZIP isn't there yet:
   1. the ZIP and the checksum file to `/downloads/`
   2. `changelog.xml` to `/updates/`
   3. `pkg_wendfold.xml` to `/updates/` last. This replaces the previous feed.
3. **Earlier files in `/downloads/` are kept and never overwritten or deleted.** If a file with the new name already exists on the site, stop: something is wrong, and the fix is a new patch version, not an overwrite. Only the two files under `/updates/` are replaced on each release.
4. The maintainer checks each URL's status and Content-Type with Joomla's update user agent. That agent is `Mozilla/5.0 Joomla!/<Joomla version> Joomla`, from `Version::getUserAgent('Joomla', true, false)`, which Joomla 6 uses for the feed, the changelog and the package download.

   ```bash
   UA='Mozilla/5.0 Joomla!/6.2.0 Joomla'
   for p in updates/pkg_wendfold.xml updates/changelog.xml \
            downloads/pkg_wendfold_vX.Y.Z.zip downloads/SHA256SUMS-vX.Y.Z.txt; do
     curl -s -o "/tmp/${p##*/}" -A "$UA" -w "%{http_code} %{content_type} $p\n" "https://wendfold.com/$p"
   done
   (cd /tmp && sha256sum -c "$OLDPWD/downloads/SHA256SUMS-vX.Y.Z.txt")
   cmp /tmp/pkg_wendfold.xml updates/pkg_wendfold.xml && cmp /tmp/changelog.xml updates/changelog.xml \
     && cmp /tmp/SHA256SUMS-vX.Y.Z.txt downloads/SHA256SUMS-vX.Y.Z.txt
   ```

   Every line must show `200` and the Content-Type from the table, and the downloaded files must match the bundle byte for byte. A `text/html` response is wrong even with status 200: it is an error or security-challenge page, not the file. If a type is wrong (for example the ZIP served as `text/plain`), the maintainer fixes the site's MIME type setting and checks again.
5. The maintainer runs the full re-test: 50 requests to each of the four URLs with Joomla's update user agent, recording status, Content-Type, the body hash compared with the bundle, and any HTML or challenge page.
   - **Pass:** 100% of requests return 200 with the right Content-Type and a body hash that matches the bundle.
   - **Fail:** any challenge page (including one served with 200), any 404, or any hash mismatch. A failure blocks publishing and the JED submission. The fallback is moving the downloads and feed to a dedicated static host.
6. The maintainer records the result (pass or fail, with counts).
7. Only after a pass: publish the GitHub draft (section 5) and go ahead with the JED submission. While the repository is private, a published release is visible only to people with repository access, so wendfold.com is the only public download.

If you want the hash recorded in the repository too, open a normal pull request that copies the `<sha256>` line into `updates/pkg_wendfold.xml`. The workflow never pushes to `main`.

To insert a hash by hand, for example for a hotfix asset:

```bash
python3 scripts/release/set-update-sha256.py \
  --xml updates/pkg_wendfold.xml --version X.Y.Z \
  --zip build/dist/pkg_wendfold_vX.Y.Z.zip
```

### Rollback

- **Wrong feed uploaded:** the maintainer uploads the previous release's `pkg_wendfold.xml` (attached to that release's draft or bundle) back to `/updates/pkg_wendfold.xml`. Joomla sites then stop offering the bad version.
- **Broken ZIP already live:** don't overwrite it. Roll the feed back as above, then release X.Y.(Z+1).

## 7. After the release

- Confirm Joomla sites on the previous version see the update, and that it installs. Joomla checks `<sha256>` against the downloaded ZIP.
- If a security fix shipped, publish the GitHub Security Advisory (see `SECURITY.md`).
