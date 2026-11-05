<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Site\Helper\DateOnly;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Wendfold\Component\Wendfold\Site\Helper\SiteTime;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$token = $this->token;
$event = $this->event;
$user = $this->user;
$showEventDetails = !empty($event->showDetails);

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_CHECKIN_TITLE'),
];

if ($event && $showEventDetails) {
	$date = DateOnly::format($event->event_date, 'D, M j, Y');
	$ksu = !empty($event->ksu_time) ? date('g:i A', strtotime((string) $event->ksu_time)) : '';
	$locName = $event->ksu_location_name ?: $event->ksu_location_custom ?: '';

	$hcPageHeader['title'] = (string) $event->title;
	$hcPageHeader['subtitle'] = Text::_('COM_WENDFOLD_CHECKIN_HEADER_CHAPTER');
	$hcPageHeader['metaItems'] = array_values(array_filter([
		$date,
		$ksu !== '' ? [
			'html' => OutputEscape::termWithTooltipAndValue(
				Text::_('COM_WENDFOLD_EVENT_KSU'),
				Text::_('COM_WENDFOLD_EVENT_KSU_DESC'),
				$ksu
			),
		] : '',
		$locName,
	]));
}
?>

<div class="hc-page hc-page--checkin hc-page--narrow hc-checkin-page">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<div class="hc-page__main hc-stack">
		<?php if (!$event) : ?>
			<section class="hc-panel hc-checkin-card hc-checkin-invalid">
				<div class="hc-panel__body text-center">
					<div class="hc-checkin-icon"></div>
					<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_INVALID_LINK_TITLE'); ?></h2>
					<p><?php echo Text::_('COM_WENDFOLD_CHECKIN_INVALID_LINK_DESC'); ?></p>
					<a href="<?php echo htmlspecialchars(RouteHelper::events(), ENT_QUOTES, 'UTF-8'); ?>" class="<?php echo hcButtonClasses('action'); ?>">
						<?php echo Text::_('COM_WENDFOLD_CHECKIN_VIEW_EVENTS'); ?>
					</a>
				</div>
			</section>

		<?php elseif (!$user->id) : ?>
			<?php $return = RouteHelper::encodeReturn(RouteHelper::checkinInternal($token)); ?>
			<section class="hc-panel hc-checkin-card hc-checkin-login">
				<div class="hc-panel__body text-center">
					<div class="hc-checkin-icon"></div>
					<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_LOGIN_REQUIRED_TITLE'); ?></h2>
					<p><?php echo Text::_('COM_WENDFOLD_CHECKIN_LOGIN_REQUIRED_DESC'); ?></p>
					<?php echo $showEventDetails
						? '<div class="hc-checkin-event-preview"><strong>'
							. htmlspecialchars((string) $event->title, ENT_QUOTES, 'UTF-8')
							. '</strong><br><span>'
							. DateOnly::format($event->event_date, 'D, M j, Y')
							. '</span></div>'
						: ''; ?>
					<a href="<?php echo htmlspecialchars(RouteHelper::login(RouteHelper::checkinInternal($token)), ENT_QUOTES, 'UTF-8'); ?>"
						class="<?php echo hcButtonClasses('action'); ?>">
						<?php echo Text::_('COM_WENDFOLD_CHECKIN_LOGIN_BUTTON'); ?>
					</a>
				</div>
			</section>

		<?php elseif (!$this->profile) : ?>
			<section class="hc-panel hc-checkin-card hc-checkin-noprofile">
				<div class="hc-panel__body text-center">
					<div class="hc-checkin-icon"></div>
					<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_NO_PROFILE_TITLE'); ?></h2>
					<p class="mb-0"><?php echo Text::_('COM_WENDFOLD_CHECKIN_NO_PROFILE_DESC'); ?></p>
				</div>
			</section>

		<?php else : ?>
			<?php
			$today = SiteTime::fromApplication()->today();
			$eventStatus = (int) ($event->status ?? 1);
			?>

			<?php if ($eventStatus === 2) : ?>
				<section class="hc-panel hc-checkin-card hc-checkin-success">
					<div class="hc-panel__body text-center">
						<div class="hc-checkin-icon"></div>
						<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_EVENT_COMPLETED'); ?></h2>
					</div>
				</section>

			<?php elseif ($eventStatus === 3) : ?>
				<section class="hc-panel hc-checkin-card hc-checkin-invalid">
					<div class="hc-panel__body text-center">
						<div class="hc-checkin-icon"></div>
						<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_EVENT_CANCELLED'); ?></h2>
					</div>
				</section>

			<?php elseif (!empty($event->event_date) && (string) $event->event_date !== $today) : ?>
				<section class="hc-panel hc-checkin-card hc-checkin-login">
					<div class="hc-panel__body text-center">
						<div class="hc-checkin-icon"></div>
						<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ERROR_NOT_TODAY'); ?></h2>
					</div>
				</section>

			<?php elseif ($this->isCheckedIn) : ?>
				<section class="hc-panel hc-checkin-card hc-checkin-success">
					<div class="hc-panel__body text-center">
						<div class="hc-checkin-icon"></div>
						<h2 class="hc-checkin-card-title"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ALREADY_TITLE'); ?></h2>
						<p><?php echo OutputEscape::sprintfHtml(Text::_('COM_WENDFOLD_CHECKIN_WELCOME_FULL'), (string) $user->name); ?></p>
						<p class="hc-checkin-sub"><?php echo Text::_('COM_WENDFOLD_CHECKIN_ALREADY_DESC'); ?></p>
						<?php if ($this->profile->member_number) : ?>
							<div class="hc-checkin-memberno">Member # <?php echo htmlspecialchars((string) $this->profile->member_number, ENT_QUOTES, 'UTF-8'); ?></div>
						<?php endif; ?>
					</div>
				</section>

			<?php else : ?>
				<section class="hc-panel hc-checkin-card hc-checkin-form-card">
					<div class="hc-panel__body text-center">
						<div class="hc-checkin-icon"></div>
						<h2 class="hc-checkin-card-title"><?php echo OutputEscape::sprintfHtml(Text::_('COM_WENDFOLD_CHECKIN_WELCOME_NAME'), explode(' ', (string) $user->name)[0]); ?></h2>
						<p><?php echo Text::_('COM_WENDFOLD_CHECKIN_TAP_TO_CHECKIN'); ?></p>
						<?php if ($this->profile->member_number) : ?>
							<div class="hc-checkin-memberno">Member # <?php echo htmlspecialchars((string) $this->profile->member_number, ENT_QUOTES, 'UTF-8'); ?></div>
						<?php endif; ?>

						<form action="<?php echo htmlspecialchars(RouteHelper::task('checkin.confirm'), ENT_QUOTES, 'UTF-8'); ?>"
							method="post" class="hc-checkin-form">
							<input type="hidden" name="token" value="<?php echo htmlspecialchars($token, ENT_QUOTES, 'UTF-8'); ?>">
							<input type="hidden" name="event_id" value="<?php echo (int) $event->id; ?>">
							<?php echo HTMLHelper::_('form.token'); ?>
							<button type="submit" class="<?php echo hcButtonClasses('action'); ?> hc-btn-checkin">
								<?php echo Text::_('COM_WENDFOLD_CHECKIN_BUTTON_CHECKIN'); ?>
							</button>
						</form>
					</div>
				</section>
			<?php endif; ?>

			<?php if ($showEventDetails && !empty($this->attendance)) : ?>
				<section class="hc-panel">
					<div class="hc-panel__header">
						<div>
							<h2 class="hc-panel__title"><?php echo Text::sprintf('COM_WENDFOLD_CHECKIN_ROSTER_HEADING', count($this->attendance)); ?></h2>
						</div>
					</div>

					<div class="hc-panel__body">
						<div class="hc-roster-grid">
							<?php foreach ($this->attendance as $a) : ?>
								<div class="hc-roster-item">
									<?php if (!empty($a->photo)) : ?>
										<img src="<?php echo htmlspecialchars(Uri::root() . $a->photo, ENT_QUOTES, 'UTF-8'); ?>"
											class="hc-roster-photo" alt="<?php echo htmlspecialchars((string) $a->member_name, ENT_QUOTES, 'UTF-8'); ?>">
									<?php else : ?>
										<div class="hc-roster-photo hc-roster-photo-placeholder"></div>
									<?php endif; ?>
									<div class="hc-roster-info">
										<div class="hc-roster-name"><?php echo htmlspecialchars((string) $a->member_name, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php if ($a->member_number) : ?>
											<div class="hc-roster-memberno"><?php echo htmlspecialchars((string) $a->member_number, ENT_QUOTES, 'UTF-8'); ?></div>
										<?php endif; ?>
									</div>
								</div>
							<?php endforeach; ?>
						</div>
					</div>
				</section>
			<?php endif; ?>
		<?php endif; ?>
	</div>
</div>
