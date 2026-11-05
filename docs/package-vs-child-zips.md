# Package ZIP vs child ZIPs

## The short version

Use `pkg_wendfold_v1.0.0.zip`. It is the only installer that is published.

Wendfold is one suite, and the package ZIP installs all of it.

## Why multiple ZIPs exist at all

Joomla uses different extension types for components, plugins, modules, packages, and other installables.

Wendfold follows that Joomla-native structure:

- `com_wendfold` is the main component
- `plg_user_wendfold` is the user plugin adapter
- `plg_task_wendfold` is the Scheduled Tasks plugin adapter
- `plg_privacy_wendfold` is the privacy plugin adapter for Joomla privacy requests
- `pkg_wendfold` is the suite package that installs the full set cleanly

That means the child ZIPs exist because Joomla expects real extension packages for each technical piece.
They do not change the product posture: publicly, Wendfold is still one product.

## What to download

Download from <https://wendfold.com/download>:

- `pkg_wendfold_v1.0.0.zip`
- `SHA256SUMS-v1.0.0.txt`, to verify it

The child ZIPs (`com_wendfold_v1.0.0.zip`, `plg_user_wendfold_v1.0.0.zip`, `plg_task_wendfold_v1.0.0.zip`, `plg_privacy_wendfold_v1.0.0.zip`) are internal build outputs. The package carries them inside it. They are never published on their own, on wendfold.com or on GitHub.

## What the package ZIP does for you

The package installer:

- installs the child extensions in the correct order
- enables the companion plugins automatically on a fresh suite install
- preserves existing plugin enabled or disabled states when installing over an existing site
- keeps uninstall behavior suite-oriented instead of leaving confusing leftovers

## What the child ZIPs are not

The child ZIPs are not separate products, and they are not a download.
They exist inside the package because Joomla installs each extension type from its own ZIP.

## Important distinction from GitHub source archives

GitHub also publishes automatic repository snapshot downloads.
Those source archives are useful for source review, but they are not installable Joomla packages.
