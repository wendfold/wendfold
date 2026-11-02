<?php

/**
 * Wendfold - PHPUnit bootstrap.
 *
 * Loads helpers and the site router. Joomla is not booted; router tests use
 * the stubs in tests/support.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

\define('_JEXEC', 1);

require dirname(__DIR__) . '/com_wendfold/site/src/Helper/AudienceHelper.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Helper/AttendanceEventAccess.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Helper/OutputEscape.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Helper/ProfileMotorcycleInput.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Helper/GpxFile.php';
require __DIR__ . '/support/joomla-stubs.php';
require __DIR__ . '/support/RegistryStub.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/StateValue.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Helper/SiteTime.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Helper/DateOnly.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MemberStatsQuery.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MemberSaveInput.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/ChapterTransferReview.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/CsvWriter.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/EventTypeBehavior.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MeetingSaveInput.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/MemberIdLabel.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AttendanceMemberLabel.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AttendanceRosterStatus.php';
require dirname(__DIR__) . '/com_wendfold/admin/src/Helper/AdminBrand.php';
require __DIR__ . '/support/RouterDoubles.php';
require dirname(__DIR__) . '/com_wendfold/site/src/Service/Router.php';
