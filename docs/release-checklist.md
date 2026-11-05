# Release checklist

This checklist is for publishing a clean, package-only public release of Wendfold. [`RELEASING.md`](../RELEASING.md) has the full maintainer process; this list is the release-day check.

The only published artifact is the suite package `pkg_wendfold_vX.Y.Z.zip`, with its checksum file `SHA256SUMS-vX.Y.Z.txt`:

- **Primary download:** `https://wendfold.com/downloads/pkg_wendfold_v1.0.0.zip` and `https://wendfold.com/downloads/SHA256SUMS-v1.0.0.txt`, linked from <https://wendfold.com/download>.
- **Mirror:** the GitHub Release, once the repository is public. The release workflow attaches exactly four assets: `pkg_wendfold_v1.0.0.zip`, `SHA256SUMS-v1.0.0.txt`, `pkg_wendfold.xml` (with `<sha256>` filled in), and `wendfold-site-bundle-v1.0.0.zip`.

The child ZIPs (`com_wendfold_*`, `plg_user_wendfold_*`, `plg_task_wendfold_*`, `plg_privacy_wendfold_*`) and the build's `SHA256SUMS.txt` are internal build outputs. They are never published on their own, anywhere. The release workflow's guard step fails the job if one is staged as a release asset.

## 1. Confirm release inputs

Before building, confirm:

- all extension manifests still use version `1.0.0`
- installer and uninstall behavior are in the known-good state
- no stabilized front-end workflow is being changed as part of release prep
- the public docs still describe `pkg_wendfold` as the only install path
- the `<update>` entry for `1.0.0` in `updates/pkg_wendfold.xml` has a `<downloadurl>` of exactly `https://wendfold.com/downloads/pkg_wendfold_v1.0.0.zip` (the release workflow fails otherwise)

## 2. Build the release artifacts locally

From the repository root, run:

```bash
python3 build/build_release.py
(cd build/dist && sha256sum -c SHA256SUMS.txt)
```

Expected outputs in `build/dist/`:

- `pkg_wendfold_v1.0.0.zip`: the package, the only file that is published
- `com_wendfold_v1.0.0.zip`, `plg_user_wendfold_v1.0.0.zip`, `plg_task_wendfold_v1.0.0.zip`, `plg_privacy_wendfold_v1.0.0.zip`: internal build outputs, also packed into the package under `constituents/`
- `SHA256SUMS.txt`: internal build checksums for all five ZIPs

## 3. Sanity-check the artifacts

Confirm:

- each ZIP opens normally
- each ZIP has its Joomla manifest at ZIP root
- `pkg_wendfold_v1.0.0.zip` contains the expected child ZIPs in `constituents/`
- `SHA256SUMS.txt` was regenerated for the current files

## 4. Prepare public-facing copy

Before publishing, confirm the public copy still matches the release artifact:

- the release notes preview from `scripts/release/generate-changelog.py --notes-out` (`RELEASING.md` section 2b), which the workflow uses for the draft release
- the wendfold.com download page (<https://wendfold.com/download>); its copy is maintained with the website, not in this repository

Make sure they all present `pkg_wendfold_v1.0.0.zip` as the only installer, and `SHA256SUMS-v1.0.0.txt` as its checksum file. No public copy offers or links a child ZIP.

## 5. Tag and review the workflow output

Tag `v1.0.0` as described in `RELEASING.md`. The release workflow then produces:

- the site-upload bundle, as the artifact `wendfold-site-bundle-v1.0.0` and as the draft-release asset `wendfold-site-bundle-v1.0.0.zip`, with exactly:
  - `downloads/pkg_wendfold_v1.0.0.zip`
  - `downloads/SHA256SUMS-v1.0.0.txt`
  - `updates/pkg_wendfold.xml` (with `<sha256>` filled in)
  - `updates/changelog.xml`
- a draft GitHub release named `Wendfold v1.0.0` with exactly four assets: `pkg_wendfold_v1.0.0.zip`, `SHA256SUMS-v1.0.0.txt`, `pkg_wendfold.xml`, and `wendfold-site-bundle-v1.0.0.zip`

The bundle is what goes to wendfold.com.

## 6. Review and publish the GitHub Release mirror

Before publishing the draft, confirm:

- it has exactly the four assets above, and no child ZIP or `SHA256SUMS.txt`
- `sha256sum -c SHA256SUMS-v1.0.0.txt` passes on the downloaded `pkg_wendfold_v1.0.0.zip`
- the notes match the `CHANGELOG.md` section for the version, without `<!-- internal -->` bullets (`RELEASING.md` section 4, step 9)

Then publish the draft (`RELEASING.md` section 5). While the repository is private, a published release is visible only to people with repository access, so wendfold.com stays the only public download.

## 7. Upload to wendfold.com (after the maintainer's approval)

Follow `RELEASING.md` section 6. Upload `downloads/` first and `updates/pkg_wendfold.xml` last. Never overwrite an existing file in `/downloads`.

## 8. Keep the release package-only

In the release body and anywhere else users will copy instructions from:

- present `pkg_wendfold_v1.0.0.zip` as the only installer
- do not list or link child ZIPs
- do not describe the suite as three equal products
- do not point users to GitHub's automatic source archives as installable Joomla packages

## 9. Post-publish verification

After publishing, verify:

- `https://wendfold.com/downloads/pkg_wendfold_v1.0.0.zip` downloads, and its sha256 matches `SHA256SUMS-v1.0.0.txt`
- the GitHub Release has exactly the four assets, and its `pkg_wendfold_v1.0.0.zip` and `SHA256SUMS-v1.0.0.txt` match the wendfold.com files
- release notes render cleanly
- support docs and README links still make sense
- the website download page shows only the package ZIP

## 10. Live update deployment

The only update feed is `updates/pkg_wendfold.xml`. The bundle's copy, whose `<sha256>` the release workflow has filled in, goes to `https://wendfold.com/updates/pkg_wendfold.xml`, with `updates/changelog.xml` beside it.

Separately verify:

- that update XML is the file Joomla fetches, its `<downloadurl>` is `https://wendfold.com/downloads/pkg_wendfold_v1.0.0.zip`, and its `<sha256>` matches that ZIP
- update detection works on a Joomla 6 site that has the package installed
- sites that previously registered the per-child Wendfold update URLs show only the package update site after this package is installed or updated
