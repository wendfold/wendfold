<?php
/**
 * Wendfold - Chapter Management System
 * Administrator Dues Payment Edit Layout
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

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$isNew = empty($this->item->id);

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

<form action="<?php echo Route::_('index.php?option=com_wendfold&task=duespayment.save&id=' . (int) ($this->item->id ?? 0)); ?>"
      method="post"
      name="adminForm"
      id="adminForm"
      class="form-validate">

    <style>
        .hc-field-stacked > label {
            display: block;
            margin-bottom: .25rem;
            font-weight: 600;
        }
    </style>

    <div class="row g-3">

        <div class="col-lg-9">
            <div class="card mb-3">
                <div class="card-body">

                    <h6 class="fw-semibold border-bottom pb-1 mb-3"><?php echo Text::_('COM_WENDFOLD_FIELDSET_PAYMENT_DETAILS'); ?></h6>

                    <div class="row g-3 mb-3">
                        <div class="col-md-6">
                            <?php $renderStackedField('profile_id'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php $renderStackedField('year'); ?>
                        </div>
                        <div class="col-md-3">
                            <?php $renderStackedField('amount_paid'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php $renderStackedField('payment_date'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php $renderStackedField('payment_method'); ?>
                        </div>
                        <div class="col-md-6">
                            <?php $renderStackedField('receipt_number'); ?>
                        </div>
                        <div class="col-12">
                            <?php $renderStackedField('notes'); ?>
                        </div>
                    </div>

                </div>
            </div>
        </div>

        <div class="col-lg-3">
            <div class="card mb-3">
                <div class="card-header">Settings</div>
                <div class="card-body">
                    <?php if (!$isNew) : ?>
                        <dl class="row mb-2">
                            <dt class="col-sm-4"><?php echo Text::_('COM_WENDFOLD_COL_ID'); ?></dt>
                            <dd class="col-sm-8"><?php echo (int) $this->item->id; ?></dd>
                        </dl>
                    <?php endif; ?>
                </div>
            </div>
        </div>

    </div>

    <?php echo $this->form->renderFieldset('hidden'); ?>

    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>

</form>
