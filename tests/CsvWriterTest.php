<?php

/**
 * Wendfold - Administrator CSV export writer.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

namespace Wendfold\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Wendfold\Component\Wendfold\Administrator\Helper\CsvWriter;

/**
 * Locks formula neutralisation, numeric cells, and a quiet fputcsv() call.
 */
final class CsvWriterTest extends TestCase
{
    /**
     * @return array<string, array{0: mixed, 1: int|float|string}>
     */
    public static function formulaProvider(): array
    {
        return [
            'equals' => ['=HYPERLINK("x")', "\t=HYPERLINK(\"x\")"],
            'plus formula' => ['+1+1', "\t+1+1"],
            'minus formula' => ['-2+3+cmd', "\t-2+3+cmd"],
            'at' => ['@SUM(A1)', "\t@SUM(A1)"],
            'tab' => ["\t=1", "\t\t=1"],
            'carriage return' => ["\r=1", "\t\r=1"],
            'line feed' => ["\n=1", "\t\n=1"],
            'full-width equals' => ['＝1+2', "\t＝1+2"],
            'full-width plus' => ['＋1', "\t＋1"],
            'full-width minus' => ['－1', "\t－1"],
            'full-width at' => ['＠A1', "\t＠A1"],
            'bare equals' => ['=', "\t="],
            'bare minus' => ['-', "\t-"],
        ];
    }

    /**
     * @return array<string, array{0: mixed, 1: int|float|string}>
     */
    public static function plainProvider(): array
    {
        return [
            'name' => ['Ada Lovelace', 'Ada Lovelace'],
            'empty' => ['', ''],
            'null' => [null, ''],
            'date' => ['2026-10-25', '2026-10-25'],
            'currency' => ['$10.00', '$10.00'],
            'leading space' => [' O\'Brien', ' O\'Brien'],
            'true' => [true, '1'],
            'false' => [false, '0'],
        ];
    }

    /**
     * @return array<string, array{0: mixed, 1: int|float|string}>
     */
    public static function numericProvider(): array
    {
        return [
            'int' => [10, 10],
            'zero' => [0, 0],
            'negative int' => [-4, -4],
            'float' => [2.5, 2.5],
            'negative float' => [-12.5, -12.5],
            'decimal string' => ['2100.50', '2100.50'],
            'negative decimal string' => ['-12.50', '-12.50'],
            'signed decimal string' => ['+8', '+8'],
            'leading zero string' => ['00123', '00123'],
            'fraction' => ['.5', '.5'],
            'negative fraction' => ['-.5', '-.5'],
        ];
    }

    #[DataProvider('formulaProvider')]
    public function testFormulaTextIsPrefixedWithATab(mixed $value, int|float|string $expected): void
    {
        self::assertSame($expected, CsvWriter::formatCell($value));
    }

    #[DataProvider('plainProvider')]
    public function testPlainTextIsUnchanged(mixed $value, int|float|string $expected): void
    {
        self::assertSame($expected, CsvWriter::formatCell($value));
    }

    #[DataProvider('numericProvider')]
    public function testNumericValuesStayNumeric(mixed $value, int|float|string $expected): void
    {
        self::assertSame($expected, CsvWriter::formatCell($value));
    }

    public function testEqualsCellMatchesTheOwaspExcelResistantForm(): void
    {
        self::assertSame("\"\t=1+2\"\n", self::raw(['=1+2']));
    }

    public function testAQuotedBreakoutStaysInOneCell(): void
    {
        $payload = "=1+2\";=1+2";
        $raw = self::raw([$payload, 'safe']);

        self::assertSame("\"\t=1+2\"\";=1+2\",safe\n", $raw);

        $parsed = self::readBack($raw);

        self::assertCount(2, $parsed);
        self::assertSame("\t" . $payload, $parsed[0]);
        self::assertSame('safe', $parsed[1]);
    }

    public function testANegativeNumberIsNotPrefixedWhenWritten(): void
    {
        $raw = self::raw([-4, -12.5, '-12.50', '=1+1', 'Ada']);

        self::assertSame("-4,-12.5,-12.50,\"\t=1+1\",Ada\n", $raw);

        $parsed = self::readBack($raw);

        self::assertSame(['-4', '-12.5', '-12.50', "\t=1+1", 'Ada'], $parsed);
    }

    public function testWriteEmitsNoDeprecationUnderAllErrors(): void
    {
        $previous = error_reporting(E_ALL);
        $seen = [];
        set_error_handler(static function (int $severity, string $message) use (&$seen): bool {
            $seen[] = $severity . ':' . $message;

            return true;
        });

        try {
            $raw = self::raw(['=1+2', -12.5, 4, "\t@SUM(A1)", 'Ada', null]);
        } finally {
            restore_error_handler();
            error_reporting($previous);
        }

        self::assertSame([], $seen);
        self::assertStringStartsWith("\"\t=1+2\",-12.5,4,", $raw);
    }

    public function testEscapeMatchesTheRosterParser(): void
    {
        self::assertSame(',', CsvWriter::SEPARATOR);
        self::assertSame('"', CsvWriter::ENCLOSURE);
        self::assertSame('', CsvWriter::ESCAPE);
    }

    /**
     * @param   array<int, mixed>  $row  Cells to write.
     */
    private static function raw(array $row): string
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);

        CsvWriter::writeRow($stream, $row);
        rewind($stream);
        $raw = stream_get_contents($stream);
        fclose($stream);
        self::assertIsString($raw);

        return $raw;
    }

    /**
     * @return array<int, string|null>
     */
    private static function readBack(string $raw): array
    {
        $stream = fopen('php://memory', 'w+');
        self::assertIsResource($stream);
        fwrite($stream, $raw);
        rewind($stream);
        $parsed = fgetcsv($stream, null, CsvWriter::SEPARATOR, CsvWriter::ENCLOSURE, CsvWriter::ESCAPE);
        fclose($stream);
        self::assertIsArray($parsed);

        return $parsed;
    }
}
