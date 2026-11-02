<?php
/**
 * Shared volunteer section for Event Details and Trip Planner.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$sectionItem = $volunteerSectionItem ?? null;
$sectionRoles = $volunteerSectionRoles ?? [];
$sectionAssignment = $volunteerSectionAssignment ?? null;
$sectionCanManage = !empty($volunteerSectionCanManage);
$sectionAction = $volunteerSectionAction ?? '';
$sectionReturnUrl = $volunteerSectionReturnUrl ?? '';

if (!$sectionItem || (empty($sectionRoles) && empty($sectionAssignment))) {
    return;
}

$assignmentStatusLabels = [
    1 => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_VOLUNTEERED'),
    2 => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED'),
    3 => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_WAITLISTED'),
];
$coverageStateLabels = [
    'open' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_COVERAGE_OPEN'),
    'filled' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_COVERAGE_FILLED'),
    'overfilled' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_COVERAGE_OVERFILLED'),
];
$coverageStateClasses = [
    'open' => 'hc-badge-success',
    'filled' => 'hc-badge-neutral',
    'overfilled' => 'hc-badge-warning',
];
$hasAssignment = $sectionAssignment && in_array((int) ($sectionAssignment->assignment_status ?? 0), [1, 2, 3], true);
$sectionUser = Factory::getApplication()->getIdentity();
?>
<section class="hc-panel hc-event-layout__volunteer" id="hc-volunteer-section">
    <div class="hc-panel__header">
        <div>
            <h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SECTION'); ?></h2>
            <div class="hc-panel__meta"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_MANAGE_NOTE'); ?></div>
        </div>
    </div>

    <div class="hc-panel__body hc-stack">
        <?php if (!empty($sectionItem->volunteer_notes)) : ?>
            <div class="hc-muted"><?php echo nl2br(htmlspecialchars((string) $sectionItem->volunteer_notes, ENT_QUOTES, 'UTF-8')); ?></div>
        <?php endif; ?>

        <?php if ($hasAssignment) : ?>
            <div class="hc-volunteer-summary">
                <div>
                    <div class="hc-volunteer-summary__label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_YOUR_ASSIGNMENT'); ?></div>
                    <div class="hc-volunteer-summary__title"><?php echo htmlspecialchars((string) ($sectionAssignment->role_title ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php if (!empty($sectionAssignment->role_shift_label)) : ?>
                        <div class="hc-muted"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SHIFT'); ?>: <?php echo htmlspecialchars((string) $sectionAssignment->role_shift_label, ENT_QUOTES, 'UTF-8'); ?></div>
                    <?php endif; ?>
                </div>
                <div class="hc-volunteer-summary__meta">
                    <span class="hc-badge hc-badge-neutral"><?php echo htmlspecialchars($assignmentStatusLabels[(int) ($sectionAssignment->assignment_status ?? 0)] ?? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED'), ENT_QUOTES, 'UTF-8'); ?></span>
                </div>
            </div>
        <?php endif; ?>

        <?php foreach ($sectionRoles as $role) : ?>
            <?php
            $filled = (int) ($role->filled_slots ?? 0);
            $slotCount = max(1, (int) ($role->slot_count ?? 1));
            $coverageState = $filled > $slotCount ? 'overfilled' : ($filled === $slotCount ? 'filled' : 'open');
            $isCurrentRole = $hasAssignment && (int) ($sectionAssignment->volunteer_role_id ?? 0) === (int) ($role->id ?? 0);
            $userHasOtherRole = $hasAssignment && !$isCurrentRole;
            $signupClosed = empty($sectionItem->volunteer_signup_open);
            $roleEligible = !empty($role->user_can_self_signup);
            $waitlistReady = $filled >= $slotCount && empty($role->user_is_assigned);
            $selfDisabled = ($waitlistReady && !$roleEligible) || $signupClosed || $userHasOtherRole || (!$waitlistReady && empty($role->user_is_assigned) && !$roleEligible);
            $buttonLabel = !empty($role->user_is_assigned)
                ? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BUTTON_REMOVE')
                : ($waitlistReady ? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BUTTON_WAITLIST') : Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BUTTON_ADD'));
            $eligibilityMessage = match ((string) ($role->user_signup_reason ?? '')) {
                'audience' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SIGNUP_AUDIENCE_ONLY'),
                'road_captain' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SIGNUP_RC_ONLY'),
                'road_crew' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SIGNUP_CREW_ONLY'),
                'profile' => Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SIGNUP_PROFILE_REQUIRED'),
                default => '',
            };
            ?>
            <article class="hc-volunteer-role-card">
                <div class="hc-volunteer-role-card__head">
                    <div>
                        <h3 class="hc-volunteer-role-card__title"><?php echo htmlspecialchars((string) $role->title, ENT_QUOTES, 'UTF-8'); ?></h3>
                        <?php if (!empty($role->description)) : ?>
                            <div class="hc-muted"><?php echo nl2br(htmlspecialchars((string) $role->description, ENT_QUOTES, 'UTF-8')); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="hc-volunteer-role-card__badges">
                        <span class="hc-badge hc-badge-outline"><?php echo Text::sprintf('COM_WENDFOLD_EVENT_VOLUNTEER_SLOTS', $filled, $slotCount); ?></span>
                        <span class="hc-badge <?php echo htmlspecialchars($coverageStateClasses[$coverageState], ENT_QUOTES, 'UTF-8'); ?>"><?php echo htmlspecialchars($coverageStateLabels[$coverageState], ENT_QUOTES, 'UTF-8'); ?></span>
                        <?php if ($filled >= $slotCount) : ?>
                            <span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_WAITLIST_READY'); ?></span>
                        <?php endif; ?>
                    </div>
                </div>
                <div class="hc-volunteer-role-card__meta">
                    <?php if (!empty($role->shift_label)) : ?><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SHIFT'); ?>: <?php echo htmlspecialchars((string) $role->shift_label, ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                    <?php if (!empty($role->requires_road_captain)) : ?><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_REQUIREMENT_RC'); ?></span><?php endif; ?>
                    <?php if (!empty($role->requires_road_crew)) : ?><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_REQUIREMENT_CREW'); ?></span><?php endif; ?>
                    <?php if (!empty($role->members_only)) : ?><span class="hc-badge hc-badge-outline"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_REQUIREMENT_MEMBERS'); ?></span><?php endif; ?>
                    <?php if ($isCurrentRole) : ?><span class="hc-badge hc-badge-neutral"><?php echo htmlspecialchars($assignmentStatusLabels[(int) ($sectionAssignment->assignment_status ?? 0)] ?? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED'), ENT_QUOTES, 'UTF-8'); ?></span><?php endif; ?>
                </div>
                <?php if (!empty($sectionAction)) : ?>
                    <?php if (!empty($sectionItem->volunteer_signup_open)) : ?>
                        <?php if (!empty($sectionUser->id)) : ?>
                            <form action="<?php echo htmlspecialchars($sectionAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-volunteer-role-card__actions">
                                <input type="hidden" name="event_id" value="<?php echo (int) $sectionItem->id; ?>">
                                <input type="hidden" name="role_id" value="<?php echo (int) ($role->id ?? 0); ?>">
                                <input type="hidden" name="return" value="<?php echo htmlspecialchars($sectionReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="<?php echo hcButtonClasses(!empty($role->user_is_assigned) ? 'secondary' : 'action', 'hc-btn-sm'); ?>" <?php echo $selfDisabled ? 'disabled aria-disabled="true"' : ''; ?>><?php echo $buttonLabel; ?></button>
                            </form>
                            <?php if ($userHasOtherRole) : ?>
                                <div class="hc-muted"><?php echo Text::sprintf('COM_WENDFOLD_EVENT_VOLUNTEER_ASSIGNED_ELSEWHERE', htmlspecialchars((string) ($sectionAssignment->role_title ?? ''), ENT_QUOTES, 'UTF-8')); ?></div>
                            <?php elseif (!empty($role->user_is_assigned)) : ?>
                                <div class="hc-muted"><?php echo Text::sprintf('COM_WENDFOLD_EVENT_VOLUNTEER_CURRENT_STATUS', $assignmentStatusLabels[(int) ($role->user_assignment_status ?? 0)] ?? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED')); ?></div>
                            <?php elseif ($waitlistReady && $roleEligible) : ?>
                                <div class="hc-muted"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_WAITLIST_HINT'); ?></div>
                            <?php elseif ($eligibilityMessage !== '') : ?>
                                <div class="hc-muted"><?php echo $eligibilityMessage; ?></div>
                            <?php endif; ?>
                        <?php else : ?>
                            <div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_LOGIN'); ?></div>
                        <?php endif; ?>
                    <?php else : ?>
                        <div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_SIGNUP_CLOSED'); ?></div>
                    <?php endif; ?>
                <?php endif; ?>
            </article>
        <?php endforeach; ?>
    </div>
</section>
