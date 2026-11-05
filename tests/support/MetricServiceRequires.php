<?php

/**
 * Wendfold - shared requires for MetricService under PHPUnit.
 *
 * Component classes are not on Composer PSR-4. Tests that load MetricService
 * need the award stack too because fromDatabase() wires AwardEngine by default.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2) . '/com_wendfold/admin/src/Service';

require_once $root . '/MetricSource.php';
require_once $root . '/MetricPeriod.php';
require_once $root . '/MetricValue.php';
require_once $root . '/MetricEntryStore.php';
require_once $root . '/SqlMetricEntryStore.php';
require_once $root . '/AwardState.php';
require_once $root . '/AwardGrantSource.php';
require_once $root . '/AwardCriterionType.php';
require_once $root . '/AwardPeriodType.php';
require_once $root . '/AwardMatchMode.php';
require_once $root . '/AwardRepeatMode.php';
require_once $root . '/AwardRevokePolicy.php';
require_once $root . '/AwardStore.php';
require_once $root . '/AwardChangeSink.php';
require_once $root . '/NullAwardChangeSink.php';
require_once $root . '/AwardLockTimeoutException.php';
require_once $root . '/SqlAwardStore.php';
require_once $root . '/AwardRecomputer.php';
require_once $root . '/AwardEngine.php';
require_once $root . '/MetricService.php';
