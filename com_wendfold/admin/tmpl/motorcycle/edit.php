<?php
/**
 * Wendfold - Chapter Management System
 * Admin Template: Motorcycle Edit
 *
 * Matches the Member/Event edit UX: stacked labels and tidy grid.
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

/** @var \Wendfold\Component\Wendfold\Administrator\View\Motorcycle\HtmlView $this */

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')
    ->useScript('form.validate');

$isNew     = empty($this->item->id);
$profileId = (int) ($this->item->profile_id ?? 0);

// Back link - returns to member edit form on the Motorcycles tab
$backUrl = $profileId > 0
    ? Route::_('index.php?option=com_wendfold&view=member&layout=edit&id=' . $profileId, false)
    : Route::_('index.php?option=com_wendfold&view=members', false);

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

<div class="container-fluid">

    <?php if (!empty($this->memberName)) : ?>
        <div class="alert alert-info mb-3 py-2">
            <span class="icon-user me-1" aria-hidden="true"></span>
            <?php echo Text::_('COM_WENDFOLD_MOTORCYCLE_FOR_MEMBER'); ?>:
            <strong><?php echo htmlspecialchars($this->memberName, ENT_QUOTES, 'UTF-8'); ?></strong>
            &nbsp;&mdash;&nbsp;
            <a href="<?php echo htmlspecialchars($backUrl, ENT_QUOTES, 'UTF-8'); ?>">
                <?php echo Text::_('COM_WENDFOLD_BACK_TO_MEMBER'); ?>
            </a>
        </div>
    <?php endif; ?>

    <form action="<?php echo Route::_('index.php?option=com_wendfold&view=motorcycle&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
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

            <div class="col-md-8">

                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <strong>
                            <?php echo $isNew
                                ? Text::_('COM_WENDFOLD_MOTORCYCLE_NEW_TITLE')
                                : Text::_('COM_WENDFOLD_MOTORCYCLE_EDIT_TITLE'); ?>
                        </strong>
                    </div>
                    <div class="card-body">

                        <div class="row g-3 mb-3">
                            <div class="col-md-3">
                                <?php $renderStackedField('year'); ?>
                            </div>
                            <div class="col-md-9">
                                <?php $renderStackedField('make'); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <?php $renderStackedField('model'); ?>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-6">
                                <?php $renderStackedField('color'); ?>
                            </div>
                            <div class="col-md-6">
                                <?php $renderStackedField('nickname'); ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <?php $renderStackedField('vin'); ?>
                        </div>

                        <div class="mb-3">
                            <?php $renderStackedField('notes'); ?>
                        </div>

                    </div>
                </div>

            </div><!-- col-md-8 -->

            <div class="col-md-4">

                <div class="card mb-3">
                    <div class="card-header bg-primary text-white">
                        <strong><?php echo Text::_('COM_WENDFOLD_MOTORCYCLE_SETTINGS_HEADING'); ?></strong>
                    </div>
                    <div class="card-body">
                        <div class="mb-3">
                            <?php $renderStackedField('is_primary'); ?>
                        </div>
                        <div class="mb-3">
                            <?php $renderStackedField('published'); ?>
                        </div>
                    </div>
                </div>

            </div><!-- col-md-4 -->

        </div><!-- .row -->

        <!-- Hidden fields -->
        <?php echo $this->form->renderField('id'); ?>
        <?php echo $this->form->renderField('profile_id'); ?>

        <input type="hidden" name="task" value="">
        <?php echo HTMLHelper::_('form.token'); ?>

    </form>
</div>
