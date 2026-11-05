<?php
/**
 * Shared organizer volunteer board markup.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

$boardItem = $volunteerBoardItem ?? null;
$boardData = $volunteerBoardData ?? [];
$boardRoles = $boardData['roles'] ?? [];
$boardActiveCount = (int) ($boardData['active_assignment_count'] ?? 0);
$boardWaitlistCount = (int) ($boardData['waitlist_count'] ?? 0);
$boardManageAction = $volunteerBoardManageAction ?? '';
$boardReturnUrl = $volunteerBoardReturnUrl ?? '';
$boardEditable = !empty($volunteerBoardEditable);

if (!$boardItem) {
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

$renderMemberFlags = static function ($member): string {
    $parts = [];

    if (!empty($member->is_officer)) {
        $parts[] = '<span class="hc-badge hc-badge-neutral">' . htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_FLAG_OFFICER'), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    if (!empty($member->is_road_captain)) {
        $parts[] = '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_REQUIREMENT_RC'), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    if (!empty($member->is_road_crew)) {
        $parts[] = '<span class="hc-badge hc-badge-outline">' . htmlspecialchars(Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_REQUIREMENT_CREW'), ENT_QUOTES, 'UTF-8') . '</span>';
    }

    return implode('', $parts);
};

$canMemberTakeRole = static function ($member, $role): bool {
    if (!$member || !$role) {
        return false;
    }

    if (!empty($role->requires_road_captain) && empty($member->is_road_captain)) {
        return false;
    }

    if (!empty($role->requires_road_crew) && empty($member->is_road_crew)) {
        return false;
    }

    return true;
};

$renderRoleOptions = static function (array $roles, int $selectedRoleId, $member) use ($canMemberTakeRole): string {
    $html = '';

    foreach ($roles as $roleOption) {
        $roleId = (int) ($roleOption->id ?? 0);
        $selected = $roleId === $selectedRoleId ? ' selected' : '';
        $disabled = ($roleId !== $selectedRoleId && !$canMemberTakeRole($member, $roleOption)) ? ' disabled' : '';
        $html .= '<option value="' . $roleId . '"' . $selected . $disabled . '>'
            . htmlspecialchars((string) ($roleOption->title ?? ''), ENT_QUOTES, 'UTF-8')
            . '</option>';
    }

    return $html;
};
?>
<section class="hc-panel hc-volunteer-board-panel">
    <div class="hc-panel__header">
        <div>
            <h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_TITLE'); ?></h2>
            <div class="hc-panel__meta"><?php echo Text::sprintf('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_META', $boardActiveCount, $boardWaitlistCount); ?></div>
        </div>
    </div>

    <div class="hc-panel__body hc-stack">
        <?php if (!$boardEditable) : ?>
            <div class="alert alert-secondary mb-0"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_VIEW_ONLY'); ?></div>
        <?php endif; ?>

        <?php if (empty($boardRoles)) : ?>
            <div class="hc-empty"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_EMPTY_ROLES'); ?></div>
        <?php else : ?>
            <div class="hc-volunteer-board__grid">
                <?php foreach ($boardRoles as $boardRole) : ?>
                    <?php
                    $filled = (int) ($boardRole->filled_slots ?? 0);
                    $slotCount = max(1, (int) ($boardRole->slot_count ?? 1));
                    $coverageState = $filled > $slotCount ? 'overfilled' : ($filled === $slotCount ? 'filled' : 'open');
                    $activeAssignments = $boardRole->active_assignments ?? [];
                    $waitlistAssignments = $boardRole->waitlist_assignments ?? [];
                    $availableMembers = $boardRole->available_members ?? [];
                    $disabledAttr = $boardEditable ? '' : ' disabled aria-disabled="true"';
                    ?>
                    <article class="hc-volunteer-board__role">
                        <div class="hc-volunteer-board__role-header">
                            <div>
                                <h3 class="hc-volunteer-board__role-title"><?php echo htmlspecialchars((string) ($boardRole->title ?? ''), ENT_QUOTES, 'UTF-8'); ?></h3>
                                <?php if (!empty($boardRole->description)) : ?>
                                    <div class="hc-muted"><?php echo nl2br(htmlspecialchars((string) $boardRole->description, ENT_QUOTES, 'UTF-8')); ?></div>
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

                        <div class="hc-volunteer-board__section">
                            <div class="hc-volunteer-board__section-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_ACTIVE'); ?></div>
                            <?php if (empty($activeAssignments)) : ?>
                                <div class="hc-volunteer-board__empty"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_EMPTY_ACTIVE'); ?></div>
                            <?php else : ?>
                                <div class="hc-volunteer-board__list">
                                    <?php foreach ($activeAssignments as $member) : ?>
                                        <article class="hc-volunteer-member">
                                            <div class="hc-volunteer-member__main">
                                                <div class="hc-strong"><?php echo htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="hc-volunteer-member__meta">
                                                    <span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars($assignmentStatusLabels[(int) ($member->assignment_status ?? 0)] ?? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php echo $renderMemberFlags($member); ?>
                                                </div>
                                            </div>
                                            <div class="hc-volunteer-member__actions">
                                                <form action="<?php echo htmlspecialchars($boardManageAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-volunteer-form-inline">
                                                    <input type="hidden" name="event_id" value="<?php echo (int) $boardItem->id; ?>">
                                                    <input type="hidden" name="profile_id" value="<?php echo (int) ($member->profile_id ?? 0); ?>">
                                                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($boardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="volunteer_action" value="save">
                                                    <select name="role_id" class="form-select form-select-sm"<?php echo $disabledAttr; ?>>
                                                        <?php echo $renderRoleOptions($boardRoles, (int) ($boardRole->id ?? 0), $member); ?>
                                                    </select>
                                                    <select name="assignment_status" class="form-select form-select-sm"<?php echo $disabledAttr; ?>>
                                                        <?php foreach ($assignmentStatusLabels as $statusValue => $statusLabel) : ?>
                                                            <option value="<?php echo (int) $statusValue; ?>"<?php echo (int) ($member->assignment_status ?? 0) === (int) $statusValue ? ' selected' : ''; ?>><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php echo HTMLHelper::_('form.token'); ?>
                                                    <button type="submit" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>"<?php echo $disabledAttr; ?>><?php echo Text::_('JSAVE'); ?></button>
                                                </form>
                                                <form action="<?php echo htmlspecialchars($boardManageAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-volunteer-form-inline hc-volunteer-form-inline--compact">
                                                    <input type="hidden" name="event_id" value="<?php echo (int) $boardItem->id; ?>">
                                                    <input type="hidden" name="assignment_id" value="<?php echo (int) ($member->id ?? 0); ?>">
                                                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($boardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="volunteer_action" value="remove">
                                                    <?php echo HTMLHelper::_('form.token'); ?>
                                                    <button type="submit" class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?>"<?php echo $disabledAttr; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_REMOVE'); ?></button>
                                                </form>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="hc-volunteer-board__section">
                            <div class="hc-volunteer-board__section-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_WAITLIST'); ?></div>
                            <?php if (empty($waitlistAssignments)) : ?>
                                <div class="hc-volunteer-board__empty"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_EMPTY_WAITLIST'); ?></div>
                            <?php else : ?>
                                <div class="hc-volunteer-board__list">
                                    <?php foreach ($waitlistAssignments as $member) : ?>
                                        <article class="hc-volunteer-member">
                                            <div class="hc-volunteer-member__main">
                                                <div class="hc-strong"><?php echo htmlspecialchars((string) ($member->member_name ?? ''), ENT_QUOTES, 'UTF-8'); ?></div>
                                                <div class="hc-volunteer-member__meta">
                                                    <span class="hc-badge hc-badge-outline"><?php echo htmlspecialchars($assignmentStatusLabels[(int) ($member->assignment_status ?? 0)] ?? Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_WAITLISTED'), ENT_QUOTES, 'UTF-8'); ?></span>
                                                    <?php echo $renderMemberFlags($member); ?>
                                                </div>
                                            </div>
                                            <div class="hc-volunteer-member__actions">
                                                <form action="<?php echo htmlspecialchars($boardManageAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-volunteer-form-inline">
                                                    <input type="hidden" name="event_id" value="<?php echo (int) $boardItem->id; ?>">
                                                    <input type="hidden" name="profile_id" value="<?php echo (int) ($member->profile_id ?? 0); ?>">
                                                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($boardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="volunteer_action" value="save">
                                                    <select name="role_id" class="form-select form-select-sm"<?php echo $disabledAttr; ?>>
                                                        <?php echo $renderRoleOptions($boardRoles, (int) ($boardRole->id ?? 0), $member); ?>
                                                    </select>
                                                    <select name="assignment_status" class="form-select form-select-sm"<?php echo $disabledAttr; ?>>
                                                        <?php foreach ($assignmentStatusLabels as $statusValue => $statusLabel) : ?>
                                                            <option value="<?php echo (int) $statusValue; ?>"<?php echo (int) ($member->assignment_status ?? 0) === (int) $statusValue ? ' selected' : ''; ?>><?php echo htmlspecialchars($statusLabel, ENT_QUOTES, 'UTF-8'); ?></option>
                                                        <?php endforeach; ?>
                                                    </select>
                                                    <?php echo HTMLHelper::_('form.token'); ?>
                                                    <button type="submit" class="<?php echo hcButtonClasses('secondary', 'hc-btn-sm'); ?>"<?php echo $disabledAttr; ?>><?php echo Text::_('JSAVE'); ?></button>
                                                </form>
                                                <form action="<?php echo htmlspecialchars($boardManageAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-volunteer-form-inline hc-volunteer-form-inline--compact">
                                                    <input type="hidden" name="event_id" value="<?php echo (int) $boardItem->id; ?>">
                                                    <input type="hidden" name="assignment_id" value="<?php echo (int) ($member->id ?? 0); ?>">
                                                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($boardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                                    <input type="hidden" name="volunteer_action" value="remove">
                                                    <?php echo HTMLHelper::_('form.token'); ?>
                                                    <button type="submit" class="<?php echo hcButtonClasses('danger', 'hc-btn-sm'); ?>"<?php echo $disabledAttr; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_REMOVE'); ?></button>
                                                </form>
                                            </div>
                                        </article>
                                    <?php endforeach; ?>
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="hc-volunteer-board__footer">
                            <div class="hc-volunteer-board__section-label"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_ADD_MEMBER'); ?></div>
                            <?php if (empty($availableMembers)) : ?>
                                <div class="hc-volunteer-board__empty"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_EMPTY_ADD'); ?></div>
                            <?php else : ?>
                                <form action="<?php echo htmlspecialchars($boardManageAction, ENT_QUOTES, 'UTF-8'); ?>" method="post" class="hc-volunteer-form-inline hc-volunteer-form-inline--add">
                                    <input type="hidden" name="event_id" value="<?php echo (int) $boardItem->id; ?>">
                                    <input type="hidden" name="role_id" value="<?php echo (int) ($boardRole->id ?? 0); ?>">
                                    <input type="hidden" name="return" value="<?php echo htmlspecialchars($boardReturnUrl, ENT_QUOTES, 'UTF-8'); ?>">
                                    <input type="hidden" name="volunteer_action" value="save">
                                    <select name="profile_id" class="form-select form-select-sm"<?php echo $disabledAttr; ?>>
                                        <option value="0"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_SELECT_MEMBER'); ?></option>
                                        <?php foreach ($availableMembers as $availableMember) : ?>
                                            <option value="<?php echo (int) ($availableMember->value ?? 0); ?>"><?php echo htmlspecialchars((string) ($availableMember->text ?? ''), ENT_QUOTES, 'UTF-8'); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                    <select name="assignment_status" class="form-select form-select-sm"<?php echo $disabledAttr; ?>>
                                        <option value="2"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_ASSIGNED'); ?></option>
                                        <option value="3"><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_STATUS_WAITLISTED'); ?></option>
                                    </select>
                                    <?php echo HTMLHelper::_('form.token'); ?>
                                    <button type="submit" class="<?php echo hcButtonClasses('action', 'hc-btn-sm'); ?>"<?php echo $disabledAttr; ?>><?php echo Text::_('COM_WENDFOLD_EVENT_VOLUNTEER_BOARD_ASSIGN'); ?></button>
                                </form>
                            <?php endif; ?>
                        </div>
                    </article>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>
    </div>
</section>
