<?php

/**
 * Wendfold - administrator CSV exports assign every variable they read.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\TestCase;

/**
 * The member status export used $app before anything assigned it.
 */
final class ExportCsvAssignmentTest extends TestCase
{
    public function testEachExportAssignsVariablesBeforeReadingThem(): void
    {
        $root = dirname(__DIR__) . '/com_wendfold/admin/src/Controller';
        $files = [
            $root . '/StatusreportController.php',
            $root . '/ActivityreportController.php',
            $root . '/AttendancetrendsController.php',
        ];

        foreach ($files as $file) {
            self::assertSame([], self::readsBeforeAssignment($file, 'exportcsv'), $file);
        }
    }

    public function testTheMemberStatusExportUsesTheControllerApplication(): void
    {
        $source = (string) file_get_contents(
            dirname(__DIR__) . '/com_wendfold/admin/src/Controller/StatusreportController.php'
        );
        $body = self::methodBody($source, 'exportcsv');

        self::assertMatchesRegularExpression('/\$app\s*=\s*\$this->app\s*;/', $body);
    }

    /**
     * @return list<string> Variable names read before they are assigned.
     */
    private static function readsBeforeAssignment(string $path, string $method): array
    {
        $body = self::methodBody((string) file_get_contents($path), $method);
        $tokens = token_get_all('<?php ' . $body);
        $assigned = [];
        $missing = [];
        $count = count($tokens);

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token) || $token[0] !== T_VARIABLE || $token[1] === '$this') {
                continue;
            }

            $name = $token[1];
            $previous = self::significant($tokens, $i, -1);

            if (is_array($previous) && in_array($previous[0], [T_AS, T_DOUBLE_ARROW], true)) {
                $assigned[$name] = true;
                continue;
            }

            $next = self::significant($tokens, $i, 1);

            if ($next === '=') {
                $assigned[$name] = true;
                continue;
            }

            if (!isset($assigned[$name])) {
                $missing[] = $name;
            }
        }

        return array_values(array_unique($missing));
    }

    private static function methodBody(string $source, string $method): string
    {
        $tokens = token_get_all($source);
        $count = count($tokens);
        $start = null;

        for ($i = 0; $i < $count; $i++) {
            $token = $tokens[$i];

            if (!is_array($token) || $token[0] !== T_FUNCTION) {
                continue;
            }

            for ($j = $i + 1; $j < $count; $j++) {
                $next = $tokens[$j];

                if (!is_array($next)) {
                    if ($next === '(') {
                        break;
                    }

                    continue;
                }

                if ($next[0] === T_STRING) {
                    if ($next[1] === $method) {
                        $start = $j;
                    }

                    break;
                }
            }

            if ($start !== null) {
                break;
            }
        }

        self::assertNotNull($start, $method);

        $open = $start;

        while ($open < $count && $tokens[$open] !== '{') {
            $open++;
        }

        $depth = 0;
        $body = '';

        for ($k = $open; $k < $count; $k++) {
            $token = $tokens[$k];
            $text = is_array($token) ? $token[1] : $token;

            if ($text === '{') {
                $depth++;
            }

            if ($text === '}') {
                $depth--;

                if ($depth === 0) {
                    break;
                }
            }

            $body .= $text;
        }

        return $body;
    }

    /**
     * @param array<int, array{0: int, 1: string, 2: int}|string> $tokens
     */
    private static function significant(array $tokens, int $from, int $step): mixed
    {
        for ($i = $from + $step; $i >= 0 && $i < count($tokens); $i += $step) {
            $token = $tokens[$i];

            if (is_array($token) && in_array($token[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)) {
                continue;
            }

            return $token;
        }

        return null;
    }
}
