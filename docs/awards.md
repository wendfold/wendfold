# Awards

Wendfold can grant awards to members automatically. You set up each award once. Wendfold then checks members against its rules and records who has earned it.

## Award definitions

An award definition describes one award. In the administrator, open **Awards** to list them, and use **New Award** to add one.

Each definition has these fields:

- **Title** and **Description**. The description is optional and is shown on the site catalog.
- **Badge image**. Optional image for the award badge.
- **Period**. How the eligibility window is calculated. See [Period types](#period-types).
- **Match mode**. Whether every criterion or any criterion must pass.
- **Repeat mode**. **Once per period**, or **Tiered** as the threshold grows.
- **Max tiers**. Optional cap on tiered grants. Leave blank for unlimited.
- **Streak periods**. Consecutive calendar years required. Leave blank for no streak.
- **Revoke policy**. What recompute does when a grant is no longer supported.
- **Criteria**. The rules that decide whether a member earns the award.

## Criteria

Every award needs at least one criterion. Each criterion has a **Criterion type** and a **Threshold**. The threshold must be at least 1.

The criterion types are:

- **Metric total**. Totals the published entries of the chosen **Metric** within the award's period.
- **Membership years**. Counts whole anniversary years of membership.
- **Holds another award**. The member must already hold the chosen **Required award**.

An award cannot require itself through a chain of other awards. Wendfold rejects prerequisites that would create a cycle. You cannot delete an award that another award uses as a prerequisite.

### Tiered awards

With **Repeat mode** set to **Tiered**, exactly one criterion must set a **Tier step**. The tier step is the extra amount needed for each further tier. It must be greater than zero.

## Period types

- **Lifetime**. Counts everything, with no date limits.
- **Calendar year**. Checks each calendar year on its own. A member can earn the award once for each year.
- **Rolling days**. Counts the last number of days set in **Rolling days**, ending today. The value must be at least 1.
- **Custom date range**. Counts from **Period start** to **Period end**. Both dates are required. The start must be on or before the end.

## Match mode

- **All criteria**. The member must pass every criterion.
- **Any criterion**. The member must pass at least one criterion.

## Prerequisites and calendar-year awards

When a calendar-year award requires another award, how far back it can be granted depends on the prerequisite's period type. A lifetime prerequisite counts for every year, so with 'match any' the calendar-year award is granted for every year in the recompute window (the current calendar year and up to 50 previous calendar years, 51 in total) as soon as the member holds the lifetime award. A rolling prerequisite counts only for the current calendar year. A custom-period prerequisite counts only for the calendar years its start and end dates overlap. Unpublishing a prerequisite award does not change this; its existing grants still count.

## Recompute and grants

Recompute checks members against each published award. It runs in three ways:

- The **Wendfold: Award Recompute** scheduled task. It needs no extra settings. Results go to the Wendfold Sync Log.
- The **Recalculate** button on the **Member Awards** screen.
- Automatically after metric data changes.

Recompute is safe to run again. It adds grants that are now earned. When a member no longer meets an automatic grant, the **Revoke policy** decides what happens:

- **Revoke automatically**. The grant is marked **Revoked**. It is not deleted.
- **Flag for review**. The grant is flagged for an administrator to check.

If an award's settings are invalid, recompute skips it and leaves its existing grants alone.

### Member Awards

The **Member Awards** screen lists every grant with its award, member, period, tier, state, source and dates. The state is **Granted**, **Revoked** or **Needs review**. The source is **Automatic** or **Manual**.

On this screen you can:

- **Grant** an award by hand. Pick a member, a published award, a **Period key** and a **Tier**. Calendar-year awards need a four-digit period key (YYYY). Only tiered awards can use a tier greater than 1. A manual grant is locked.
- **Lock** or **Unlock** a grant. Recompute never changes a locked grant.
- Save a **Presented** date as YYYY-MM-DD, or leave it blank.
