<?php

/**
 * Wendfold - Chapter Management System
 *
 * Shared edit screen for one lookup row.
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;

HTMLHelper::_('behavior.formvalidator');
HTMLHelper::_('behavior.keepalive');

$task = (string) ($this->lookup['itemTask'] ?? '');
$isNew = empty($this->item->id);
?>
<form action="<?php echo Route::_('index.php?option=com_wendfold&layout=edit&id=' . (int) ($this->item->id ?? 0)); ?>"
	method="post" name="adminForm" id="adminForm" class="form-validate">
	<div class="row">
		<div class="col-lg-8">
			<div class="card">
				<div class="card-body">
					<?php echo $this->form->renderFieldset('details'); ?>
				</div>
			</div>
		</div>
		<div class="col-lg-4">
			<div class="card">
				<div class="card-body">
					<p class="text-muted"><?php echo Text::_('COM_WENDFOLD_LOOKUP_EDIT_NOTE'); ?></p>
					<?php if (!$isNew) : ?>
						<p><?php echo Text::_('COM_WENDFOLD_COL_ID'); ?>: <?php echo (int) $this->item->id; ?></p>
					<?php endif; ?>
				</div>
			</div>
		</div>
	</div>
	<input type="hidden" name="task" value="">
	<?php echo HTMLHelper::_('form.token'); ?>
</form>
