# Updating

## Recommended update path

For normal updates, use the latest suite package ZIP for Wendfold.

That keeps the product updated as one suite while still allowing Joomla to install the component and plugins in their native extension types.

## Why package-first updating is preferred

Using the package ZIP:

- keeps the suite presented as one product
- reduces the chance of partial updates across child extensions
- preserves existing plugin enabled or disabled states when updating over an existing install
- matches the intended public release and support path

## Typical update steps

1. Back up the Joomla site and database.
2. Download the new `pkg_wendfold` release ZIP from <https://wendfold.com/download>, or use Joomla's update system, which reads the feed at wendfold.com/updates and downloads the package from the GitHub release.
3. In Joomla administrator, go to `System -> Install -> Extensions`.
4. Upload the new package ZIP.
5. Confirm the component and plugins remain in the expected state.
6. Review Wendfold configuration and Scheduled Tasks after update.

## No separate child updates

The component and plugins are updated only through the package. Their ZIPs are internal build outputs and are never published on their own, so there is no child-extension update path.

## Live update note

Wendfold has one update server, on the package: `https://wendfold.com/updates/pkg_wendfold.xml`.
The feed in this repository is `updates/pkg_wendfold.xml`. Child manifests do not register update servers.

Each update's download is `https://github.com/wendfold/wendfold/releases/download/vX.Y.Z/pkg_wendfold_vX.Y.Z.zip`. Releases and support are package-only; there is no second update channel.

## Good practice after updating

After any update, confirm:

- the Wendfold component opens normally
- the `User - Wendfold` plugin state is what you expect
- the `Task - Wendfold` plugin state is what you expect
- Scheduled Task definitions still look correct
- front-end and administrator workflows still open without errors
