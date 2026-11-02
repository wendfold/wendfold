<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Location Edit Layout
 *
 * Matches the Member/Event edit UX: stacked labels and organized sections.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

/** @var \Wendfold\Component\Wendfold\Administrator\View\Location\HtmlView $this */

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$isNew = empty($this->item->id);

// Render a field with label above input and optional description below.
$renderStackedField = function (string $name): void
{
    $field = $this->form->getField($name);

    if (!$field)
    {
        return;
    }

    echo '<div class="hc-field-stacked">';
    echo $this->form->getLabel($name);
    echo $this->form->getInput($name);

    if (($description = (string) $field->getAttribute('description')) !== '')
    {
		$descId = htmlspecialchars((string) $field->__get('id') . '-desc', ENT_QUOTES, 'UTF-8');
        echo '<div class="form-text" id="' . $descId . '">' . Text::_($description) . '</div>';
    }

    echo '</div>';
};
?>

<form action="<?php echo Route::_('index.php?option=com_wendfold&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
      method="post"
      name="adminForm"
      id="adminForm"
      class="form-validate"
      enctype="multipart/form-data">

    <style>
        .hc-field-stacked > label {
            display: block;
            margin-bottom: .25rem;
            font-weight: 600;
        }
        /* Hide verbose 'Current file' helper line from media field UI in this view */
        .hc-media-field .field-media-current,
        .hc-media-field [class*="field-media-current"] {
            display: none !important;
        }
    </style>

    <div class="row g-3">

        <!-- ===== MAIN COLUMN ===== -->
        <div class="col-lg-9">

            <div class="card mb-3">
                <div class="card-body">

                    <div class="alert alert-info py-2 mb-3">
                        <span class="icon-info-circle" aria-hidden="true"></span>
                        <?php echo Text::_('COM_WENDFOLD_LOCATION_TYPE_NOTE'); ?>
                    </div>

                    <!-- Basic details -->
                    <h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_DETAILS'); ?></h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <?php $renderStackedField('name'); ?>
                        </div>

                        <div class="col-12">
                            <div class="hc-media-field">
                                <?php echo $this->form->getInput('image'); ?>
                                <?php $desc = $this->form->getFieldAttribute('image', 'description'); ?>
                                <?php if (!empty($desc)) : ?>
                                    <div class="form-text"><?php echo Text::_($desc); ?></div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="col-md-6">
                            <?php $renderStackedField('location_type'); ?>
                        </div>

                        <div class="col-md-6">
                            <?php $renderStackedField('published'); ?>
                        </div>
                    </div>

                    <!-- Address -->
                    <h6 class="fw-semibold border-bottom pb-1 mb-3">Address</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-12">
                            <?php $renderStackedField('address'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php $renderStackedField('city'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php $renderStackedField('state'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php $renderStackedField('zip'); ?>
                        </div>
                    </div>

                    <!-- GPS -->
                    <h6 class="fw-semibold border-bottom pb-1 mb-3">GPS Coordinates</h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <?php $renderStackedField('lat'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php $renderStackedField('lng'); ?>
                        </div>
                    </div>

                    <!-- Notes -->
                    <h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELD_NOTES_LABEL'); ?></h6>

                    <div class="mb-3">
                        <?php $renderStackedField('notes'); ?>
                    </div>

                    <!-- Settings -->
                    <h6 class="fw-semibold border-bottom pb-1 mb-3">Settings</h6>

                    <div class="row g-3">
                        <div class="col-md-6">
                            <?php $renderStackedField('is_default'); ?>
                        </div>
                    </div>

                </div>
            </div>

        </div>

        <!-- ===== SIDEBAR ===== -->
        <div class="col-lg-3">

            <div class="card mb-3">
                <div class="card-header"><?php echo Text::_('COM_WENDFOLD_LOCATION_DETAILS'); ?></div>
                <div class="card-body">
                    <?php if (!$isNew) : ?>
                        <dl class="row mb-2">
                            <dt class="col-sm-5"><?php echo Text::_('COM_WENDFOLD_COL_ID'); ?></dt>
                            <dd class="col-sm-7"><?php echo (int) $this->item->id; ?></dd>
                        </dl>
                    <?php endif; ?>

                    <div class="small text-muted">
                        <?php echo Text::_('COM_WENDFOLD_LOCATION_GPS_NOTE'); ?>
                    </div>
                </div>
            </div>

        </div>

    </div>

    <?php echo $this->form->renderFieldset('hidden'); ?>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
