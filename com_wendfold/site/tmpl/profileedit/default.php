<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

// Database column names are snake_case on profile rows.
// phpcs:disable Joomla.NamingConventions.ValidVariableName.MemberNotCamelCaps

use Wendfold\Component\Wendfold\Administrator\Helper\LookupTitle;
use Wendfold\Component\Wendfold\Site\Helper\OutputEscape;
use Wendfold\Component\Wendfold\Site\Helper\ProfileMotorcycleInput;
use Wendfold\Component\Wendfold\Site\Helper\RouteHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

require_once dirname(__DIR__) . '/_includes/button_helper.php';

$profile = $this->profile;
$motorcycles = \is_array($this->motorcycles ?? null) ? $this->motorcycles : [];
$earnedAwards = $this->earnedAwards ?? [];
$shirtSizes = ['XS', 'S', 'M', 'L', 'XL', '2XL', '3XL', '4XL'];
$dashboardUrl = RouteHelper::dashboard();
$saveUrl = RouteHelper::task('profileedit.save');
$minYear = ProfileMotorcycleInput::YEAR_MIN;
$maxYear = ProfileMotorcycleInput::yearMax();

$name = $profile ? (string) ($profile->name ?? '') : '';

$photoUrl = '';
if ($profile && !empty($profile->photo)) {
	$photoUrl = Uri::root() . ltrim((string) $profile->photo, '/');
}

$initial = '';
if (trim($name) !== '') {
	$firstChar = \function_exists('mb_substr') ? mb_substr(trim($name), 0, 1) : substr(trim($name), 0, 1);
	$initial = strtoupper((string) $firstChar);
}

$headerMeta = [];
if ($name !== '') {
	$headerMeta[] = $name;
}
if (!empty($profile->member_number)) {
	$headerMeta[] = 'Member #' . (string) $profile->member_number;
}

$hcPageHeader = [
	'title' => Text::_('COM_WENDFOLD_PROFILEEDIT_TITLE'),
	'metaItems' => $headerMeta,
];

// Existing rows plus one blank row for a new motorcycle.
$motoRows = $motorcycles;
$motoRows[] = (object) [
	'id' => 0,
	'year' => '',
	'make' => '',
	'model' => '',
	'color' => '',
	'nickname' => '',
	'vin' => '',
];
?>

<div class="hc-page hc-page--profileedit hc-profileedit">
	<?php require dirname(__DIR__) . '/_includes/page_header.php'; ?>

	<form method="post" action="<?php echo OutputEscape::html($saveUrl); ?>" enctype="multipart/form-data" class="hc-page__layout hc-page__layout--sidebar hc-profileedit-layout">
		<section class="hc-panel hc-profileedit-layout__photo">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SECTION_PHOTO'); ?></h2>
				</div>
			</div>

			<div class="hc-panel__body">
				<div class="hc-photo-stack">
					<?php if ($photoUrl) : ?>
						<img
							class="hc-avatar-lg"
							src="<?php echo OutputEscape::html($photoUrl); ?>"
							alt="<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_CURRENT'); ?>"
							width="96"
							height="96"
							loading="lazy"
							decoding="async"
						>
					<?php else : ?>
						<div class="hc-avatar-ph-lg" aria-hidden="true"><?php echo OutputEscape::html($initial !== '' ? $initial : '?'); ?></div>
					<?php endif; ?>

					<div class="flex-grow-1 min-w-0">
						<label for="photo_upload" class="form-label fw-semibold mb-1">
							<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_LABEL'); ?>
						</label>
						<input
							type="file"
							id="photo_upload"
							name="photo_upload"
							accept="image/*"
							class="form-control"
						>
						<div class="form-text"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_HINT'); ?></div>
						<div class="form-text"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_PHOTO_SAVE_NOTE'); ?></div>
					</div>
				</div>
			</div>
		</section>

		<section class="hc-panel hc-profileedit-layout__emergency">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SECTION_EMERGENCY'); ?></h2>
				</div>
			</div>

			<div class="hc-panel__body">
				<div class="row g-3">
					<div class="col-12 col-md-4">
						<label for="emergency_name" class="form-label fw-semibold mb-1">
							<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_EMERGENCY_NAME_LABEL'); ?>
						</label>
						<input type="text" id="emergency_name" name="emergency_name" class="form-control"
							value="<?php echo OutputEscape::html((string) ($profile->emergency_name ?? '')); ?>">
					</div>

					<div class="col-12 col-md-4">
						<label for="emergency_phone" class="form-label fw-semibold mb-1">
							<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_EMERGENCY_PHONE_LABEL'); ?>
						</label>
						<input type="tel" id="emergency_phone" name="emergency_phone" class="form-control"
							value="<?php echo OutputEscape::html((string) ($profile->emergency_phone ?? '')); ?>">
					</div>

					<div class="col-12 col-md-4">
						<label for="emergency_relation" class="form-label fw-semibold mb-1">
							<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_EMERGENCY_RELATION_LABEL'); ?>
						</label>
						<input type="text" id="emergency_relation" name="emergency_relation" class="form-control"
							value="<?php echo OutputEscape::html((string) ($profile->emergency_relation ?? '')); ?>">
					</div>
				</div>

				<div class="alert alert-light border small mb-0 mt-3">
					<?php echo Text::_('COM_WENDFOLD_EMERGENCY_NOTE'); ?>
				</div>
			</div>
		</section>

		<section class="hc-panel hc-profileedit-layout__moto">
			<div class="hc-panel__header">
				<div>
					<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SECTION_MOTO'); ?></h2>
				</div>
			</div>

			<div class="hc-panel__body">
				<?php foreach ($motoRows as $index => $moto) : ?>
					<?php
					$motoId = (int) ($moto->id ?? 0);
					$modelYear = (string) ($moto->{'model_year'} ?? '');
					$yearValue = (string) ($moto->year ?? '');

					if ($yearValue === '')
					{
						$yearValue = $modelYear;
					}

					$prefix = 'motorcycles[' . (int) $index . ']';
					$yearId = 'moto_year_' . (int) $index;
					$makeId = 'moto_make_' . (int) $index;
					$modelId = 'moto_model_' . (int) $index;
					$colorId = 'moto_color_' . (int) $index;
					$nickId = 'moto_nickname_' . (int) $index;
					$vinId = 'moto_vin_' . (int) $index;
					$removeId = 'moto_remove_' . (int) $index;
					?>
					<div class="hc-profileedit-moto-row<?php echo $index > 0 ? ' mt-4 pt-4 border-top' : ''; ?>">
						<input type="hidden" name="<?php echo OutputEscape::html($prefix); ?>[id]" value="<?php echo $motoId; ?>">

						<div class="row g-3">
							<div class="col-12 col-md-4">
								<label for="<?php echo OutputEscape::html($yearId); ?>" class="form-label fw-semibold mb-1"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_YEAR_LABEL'); ?></label>
								<input type="number" id="<?php echo OutputEscape::html($yearId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[year]" class="form-control" min="<?php echo (int) $minYear; ?>" max="<?php echo (int) $maxYear; ?>"
									value="<?php echo OutputEscape::html($yearValue); ?>">
							</div>

							<div class="col-12 col-md-4">
								<label for="<?php echo OutputEscape::html($makeId); ?>" class="form-label fw-semibold mb-1"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_MAKE_LABEL'); ?></label>
								<input type="text" id="<?php echo OutputEscape::html($makeId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[make]" class="form-control"
									value="<?php echo OutputEscape::html((string) ($moto->make ?? '')); ?>">
							</div>

							<div class="col-12 col-md-4">
								<label for="<?php echo OutputEscape::html($modelId); ?>" class="form-label fw-semibold mb-1"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_MODEL_LABEL'); ?></label>
								<input type="text" id="<?php echo OutputEscape::html($modelId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[model]" class="form-control"
									placeholder="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_FIELD_MOTO_MODEL_HINT')); ?>"
									value="<?php echo OutputEscape::html((string) ($moto->model ?? '')); ?>">
							</div>

							<div class="col-12 col-md-4">
								<label for="<?php echo OutputEscape::html($colorId); ?>" class="form-label fw-semibold mb-1"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_COLOR_LABEL'); ?></label>
								<input type="text" id="<?php echo OutputEscape::html($colorId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[color]" class="form-control"
									value="<?php echo OutputEscape::html((string) ($moto->color ?? '')); ?>">
							</div>

							<div class="col-12 col-md-4">
								<label for="<?php echo OutputEscape::html($nickId); ?>" class="form-label fw-semibold mb-1"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_NICKNAME_LABEL'); ?></label>
								<input type="text" id="<?php echo OutputEscape::html($nickId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[nickname]" class="form-control"
									placeholder="<?php echo OutputEscape::html(Text::_('COM_WENDFOLD_FIELD_MOTO_NICKNAME_HINT')); ?>"
									value="<?php echo OutputEscape::html((string) ($moto->nickname ?? '')); ?>">
							</div>

							<div class="col-12 col-md-4">
								<label for="<?php echo OutputEscape::html($vinId); ?>" class="form-label fw-semibold mb-1"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_VIN_LABEL'); ?></label>
								<input type="text" id="<?php echo OutputEscape::html($vinId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[vin]" class="form-control" maxlength="<?php echo (int) ProfileMotorcycleInput::VIN_MAX_LENGTH; ?>" autocomplete="off"
									value="<?php echo OutputEscape::html((string) ($moto->vin ?? '')); ?>">
							</div>

							<?php if ($motoId > 0) : ?>
								<div class="col-12 col-md-8 d-flex align-items-end">
									<div class="form-check mb-2">
										<input class="form-check-input" type="checkbox" value="1" id="<?php echo OutputEscape::html($removeId); ?>" name="<?php echo OutputEscape::html($prefix); ?>[remove]">
										<label class="form-check-label" for="<?php echo OutputEscape::html($removeId); ?>">
											<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_REMOVE'); ?>
										</label>
									</div>
								</div>
							<?php endif; ?>
						</div>
					</div>
				<?php endforeach; ?>

				<div class="alert alert-light border small mb-0 mt-3">
					<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_MOTO_BLANK_NOTE'); ?>
				</div>
			</div>
		</section>

		<aside class="hc-page__aside hc-profileedit-layout__shirt">
			<section class="hc-panel">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SECTION_SHIRT'); ?></h2>
					</div>
				</div>

				<div class="hc-panel__body">
					<label for="shirt_size" class="form-label fw-semibold mb-1">
						<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SHIRT_LABEL'); ?>
					</label>
					<select id="shirt_size" name="shirt_size" class="form-select hc-form-width-sm">
						<option value=""><?php echo Text::_('COM_WENDFOLD_FIELD_SHIRT_SIZE_SELECT'); ?></option>
						<?php foreach ($shirtSizes as $size) : ?>
							<option value="<?php echo OutputEscape::html($size); ?>" <?php echo (($profile->shirt_size ?? '') === $size) ? 'selected' : ''; ?>>
								<?php echo OutputEscape::html($size); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>
			</section>

			<section class="hc-panel mt-3">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SECTION_REMINDERS'); ?></h2>
					</div>
				</div>

				<div class="hc-panel__body">
					<?php $optOutField = 'renewal_reminders_optout'; ?>
					<input type="hidden" name="<?php echo OutputEscape::html($optOutField); ?>" value="0">
					<div class="form-check">
						<input
							type="checkbox"
							class="form-check-input"
							id="<?php echo OutputEscape::html($optOutField); ?>"
							name="<?php echo OutputEscape::html($optOutField); ?>"
							value="1"
							<?php echo !empty($profile->$optOutField) ? 'checked' : ''; ?>
						>
						<label class="form-check-label" for="<?php echo OutputEscape::html($optOutField); ?>">
							<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_RENEWAL_REMINDERS_OPTOUT_LABEL'); ?>
						</label>
					</div>
					<div class="form-text">
						<?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_RENEWAL_REMINDERS_OPTOUT_DESC'); ?>
					</div>
				</div>
			</section>

			<section class="hc-panel mt-3">
				<div class="hc-panel__header">
					<div>
						<h2 class="hc-panel__title"><?php echo Text::_('COM_WENDFOLD_AWARDS_PROFILE_TITLE'); ?></h2>
					</div>
				</div>
				<div class="hc-panel__body">
					<?php if (empty($earnedAwards)) : ?>
						<p class="hc-muted mb-0"><?php echo Text::_('COM_WENDFOLD_AWARDS_PROFILE_EMPTY'); ?></p>
					<?php else : ?>
						<ul class="list-unstyled mb-0">
							<?php foreach ($earnedAwards as $award) : ?>
								<li class="mb-2">
									<div class="fw-semibold"><?php echo OutputEscape::html(LookupTitle::display((string) $award->title)); ?></div>
									<div class="small text-muted">
										<?php echo Text::sprintf('COM_WENDFOLD_AWARDS_EARNED_ON', HTMLHelper::_('date', (string) $award->earned_at, 'M j, Y')); ?>
									</div>
								</li>
							<?php endforeach; ?>
						</ul>
					<?php endif; ?>
				</div>
			</section>
		</aside>

		<div class="hc-page__actions hc-page__actions--footer hc-profileedit-layout__actions">
			<button type="submit" class="<?php echo hcButtonClasses('action'); ?>"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_SAVE'); ?></button>
			<a class="<?php echo hcButtonClasses('back'); ?>" href="<?php echo OutputEscape::html($dashboardUrl); ?>"><?php echo Text::_('COM_WENDFOLD_PROFILEEDIT_CANCEL'); ?></a>
		</div>

		<input type="hidden" name="profile_id" value="<?php echo (int) $profile->id; ?>">
		<?php echo HTMLHelper::_('form.token'); ?>
	</form>
</div>
