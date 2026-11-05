<?php
/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * Admin template: Email Blast
 * admin/tmpl/emailblast/default.php
 *
 * Phase 12: White-label param support.
 *   - Organization short name in audience description driven by $groupName param.
 *   - "Chapter" in labels driven by $chapterLabel param.
 *
 * Color conventions:
 *   - Card header:          bg-primary text-white
 *   - Send button:          btn-danger (intentional friction - confirms before sending)
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Session\Session;

// ------------------------------------------------------------
// Phase 12: White-label component params
// ------------------------------------------------------------
$wlParams     = ComponentHelper::getParams('com_wendfold');
$groupName    = (string) $wlParams->get('group_name',    'Chapter');
$chapterLabel = (string) $wlParams->get('chapter_label', 'Chapter');
$formAction   = htmlspecialchars(
	Route::_('index.php?option=com_wendfold&task=emailblast.send', false),
	ENT_QUOTES,
	'UTF-8'
);
$previewUrl   = Route::_('index.php?option=com_wendfold&task=emailblast.previewcount&format=json', false);
$formToken    = Session::getFormToken();
?>

<div class="container-fluid">

    <div class="row mb-3 align-items-center">
        <div class="col">
            <h2 class="mb-0"><?php echo Text::_('COM_WENDFOLD_EMAILBLAST_TITLE'); ?></h2>
        </div>
    </div>

    <div class="row g-3">
        <div class="col-md-8">

            <!-- Compose card -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong><?php echo Text::_('COM_WENDFOLD_EMAILBLAST_COMPOSE_HEADING'); ?></strong>
                </div>
                <div class="card-body">

					<form action="<?php echo $formAction; ?>"
                          method="post"
                          id="emailBlastForm">

                        <!-- Audience -->
                        <div class="mb-3">
                            <label class="form-label" for="blast_audience">
                                <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_AUDIENCE_LABEL'); ?>
                            </label>
                            <select name="audience" id="blast_audience" class="form-select">
                                <!-- "All {groupName} Members" (terminology param) -->
                                <option value="all">
                                    <?php
                                    echo sprintf(
                                        Text::_('COM_WENDFOLD_EMAILBLAST_AUDIENCE_ALL'),
                                        htmlspecialchars($groupName, ENT_QUOTES, 'UTF-8')
                                    );
                                    ?>
                                </option>
                                <option value="dues_paid">
                                    <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_AUDIENCE_DUES_PAID'); ?>
                                </option>
                                <option value="dues_unpaid">
                                    <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_AUDIENCE_DUES_UNPAID'); ?>
                                </option>
                                <option value="expiring_60">
                                    <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_AUDIENCE_EXPIRING_60'); ?>
                                </option>
                                <option value="officers">
                                    <!-- Phase 12: "Chapter Officers" -> $chapterLabel Officers -->
                                    <?php echo htmlspecialchars($chapterLabel, ENT_QUOTES, 'UTF-8'); ?>
                                    <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_AUDIENCE_OFFICERS_SUFFIX'); ?>
                                </option>
                            </select>
                        </div>

                        <!-- Preview count -->
                        <div class="mb-3">
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm"
                                    id="previewCountBtn">
                                <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_PREVIEW_COUNT_BTN'); ?>
                            </button>
                            <span id="previewCountResult" class="ms-2 text-muted small"></span>
                        </div>

                        <!-- Subject -->
                        <div class="mb-3">
                            <label class="form-label" for="blast_subject">
                                <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_SUBJECT_LABEL'); ?>
                            </label>
                            <input type="text"
                                   name="subject"
                                   id="blast_subject"
                                   class="form-control"
                                   maxlength="200"
                                   required
                            />
                        </div>

                        <!-- Body -->
                        <div class="mb-3">
                            <label class="form-label" for="blast_body">
                                <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_BODY_LABEL'); ?>
                            </label>
                            <textarea name="body"
                                      id="blast_body"
                                      class="form-control"
                                      rows="10"
                                      required></textarea>
                            <div class="form-text">
                                <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_BODY_HINT'); ?>
                            </div>
                        </div>

                        <!-- Send button: intentional btn-danger for friction -->
						<button type="submit" class="btn btn-danger" id="emailBlastSend">
                            <span class="icon-envelope" aria-hidden="true"></span>
                            <?php echo Text::_('COM_WENDFOLD_EMAILBLAST_SEND_BTN'); ?>
                        </button>

                        <?php echo HTMLHelper::_('form.token'); ?>
                    </form>

                </div>
            </div>

        </div><!-- col-md-8 -->

        <div class="col-md-4">

            <!-- Tips card -->
            <div class="card mb-3">
                <div class="card-header bg-primary text-white">
                    <strong><?php echo Text::_('COM_WENDFOLD_EMAILBLAST_TIPS_HEADING'); ?></strong>
                </div>
                <div class="card-body">
                    <ul class="mb-0 ps-3 text-muted small">
                        <li><?php echo Text::_('COM_WENDFOLD_EMAILBLAST_TIP_1'); ?></li>
                        <li><?php echo Text::_('COM_WENDFOLD_EMAILBLAST_TIP_2'); ?></li>
                        <li><?php echo Text::_('COM_WENDFOLD_EMAILBLAST_TIP_3'); ?></li>
                    </ul>
                </div>
            </div>

        </div><!-- col-md-4 -->
    </div><!-- .row -->

</div>

<script>
(function () {
	var form   = document.getElementById('emailBlastForm');
    var btn    = document.getElementById('previewCountBtn');
    var result = document.getElementById('previewCountResult');
	var token  = <?php echo json_encode($formToken); ?>;
	var url    = <?php echo json_encode($previewUrl); ?>;
	var recipientsLabel = <?php echo json_encode(Text::_('COM_WENDFOLD_EMAILBLAST_PREVIEW_RECIPIENTS')); ?>;
	var previewError    = <?php echo json_encode(Text::_('COM_WENDFOLD_EMAILBLAST_PREVIEW_ERROR')); ?>;
	var sendConfirm     = <?php echo json_encode(Text::_('COM_WENDFOLD_EMAILBLAST_SEND_CONFIRM')); ?>;

	if (form)
	{
		form.addEventListener('submit', function (event) {
			if (!window.confirm(sendConfirm))
			{
				event.preventDefault();
			}
		});
	}

    if (!btn) { return; }

    btn.addEventListener('click', function () {
        var audience = document.getElementById('blast_audience').value;
		var body = new URLSearchParams();
        result.textContent = '...';
		body.set('audience', audience);
		body.set(token, '1');

		fetch(url, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
				'X-CSRF-Token': token
			},
			body: body.toString()
		})
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && typeof data.count !== 'undefined')
                {
					result.textContent = data.count + ' ' + recipientsLabel;
                }
                else
                {
					result.textContent = previewError;
                }
            })
            .catch(function () {
				result.textContent = previewError;
            });
    });
}());
</script>
