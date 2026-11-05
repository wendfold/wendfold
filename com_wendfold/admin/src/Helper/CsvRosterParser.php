<?php

/**
 * @package     Wendfold
 * @subpackage  com_wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Wendfold\Component\Wendfold\Administrator\Helper;

use Wendfold\Component\Wendfold\Site\Helper\SiteTime;

defined('_JEXEC') or die;

/**
 * Parse a member-roster CSV into validated rows.
 *
 * Column names are configurable. Matching ignores case, spaces, underscores,
 * and hyphens. The identifier column defaults to "Member ID" and also accepts
 * member_id, memberid, member number, and membernumber.
 *
 * The escape argument is passed explicitly to fgetcsv() and str_getcsv()
 * because PHP 8.4 deprecates relying on the default. An empty escape follows
 * the common CSV rule that quotes are escaped by doubling them.
 *
 * @since  1.0.0
 */
final class CsvRosterParser
{
	public const MAX_BYTES = 5242880;

	public const MAX_DATA_ROWS = 5000;

	public const MAX_COLUMNS = 100;

	public const MAX_ERRORS = 100;

	public const MEMBER_ID_MAX = 20;

	/**
	 * Default header text for each field. Keys are the param suffixes
	 * csv_col_<field> in the component options.
	 *
	 * @var  array<string, string>
	 */
	public const DEFAULT_COLUMNS = [
		'member_id'       => 'Member ID',
		'first_name'      => 'First Name',
		'last_name'       => 'Last Name',
		'email'           => 'Email',
		'phone'           => 'Phone',
		'address'         => 'Address',
		'city'            => 'City',
		'state'           => 'State',
		'postal_code'     => 'Postal Code',
		'country'         => 'Country',
		'join_date'       => 'Join Date',
		'expiry_date'     => 'Expiry Date',
		'membership_type' => 'Membership Type',
		'life_member'     => 'Life Member',
		'status'          => 'Status',
		'chapter'         => 'Chapter',
	];

	/**
	 * Extra header aliases used when the configured header is still the
	 * default, plus the identifier aliases which are always accepted.
	 *
	 * @var  array<string, array<int, string>>
	 */
	private const ALIASES = [
		'member_id'       => ['memberid', 'membernumber'],
		'first_name'      => ['firstname', 'givenname'],
		'last_name'       => ['lastname', 'surname', 'familyname'],
		'email'           => ['email', 'emailaddress'],
		'phone'           => ['phone', 'phonenumber', 'telephone', 'mobile'],
		'address'         => ['address', 'street', 'streetaddress', 'address1'],
		'city'            => ['city', 'town'],
		'state'           => ['state', 'stateprovince', 'province', 'region'],
		'postal_code'     => ['postalcode', 'zip', 'zipcode', 'postcode'],
		'country'         => ['country'],
		'join_date'       => ['joindate', 'membersince', 'datejoined'],
		'expiry_date'     => ['expirydate', 'renewaldate', 'expirationdate', 'exp'],
		'membership_type' => ['membershiptype', 'type'],
		'life_member'     => ['lifemember'],
		'status'          => ['status', 'membershipstatus'],
		'chapter'         => ['chapter', 'chapternumber', 'chapterid'],
	];

	private const TEXT_LIMITS = [
		'first_name'  => 150,
		'last_name'   => 150,
		'phone'       => 30,
		'address'     => 255,
		'city'        => 100,
		'state'       => 100,
		'postal_code' => 20,
		'country'     => 100,
		'chapter'     => 20,
	];

	/**
	 * Parse a member-roster CSV into validated rows.
	 *
	 * @param   string  $path               Absolute path of the CSV file.
	 * @param   array   $configuredColumns  Field names mapped to header text.
	 *
	 * @return  array  Parsed file, rows, and mapping details.
	 *
	 * @since   1.0.0
	 */
	public static function parse(string $path, array $configuredColumns): array
	{
		$result = self::emptyResult();
		$configured = self::normalizeConfigured($configuredColumns);
		$result['configured'] = $configured;

		if (!is_file($path) || !is_readable($path))
		{
			$result['file_error'] = 'unreadable';

			return $result;
		}

		$bytes = filesize($path);

		if ($bytes === false || $bytes > self::MAX_BYTES)
		{
			$result['file_error'] = 'too_large';

			return $result;
		}

		$raw = file_get_contents($path);

		if ($raw === false)
		{
			$result['file_error'] = 'unreadable';

			return $result;
		}

		if ($raw === '')
		{
			$result['file_error'] = 'empty';

			return $result;
		}

		if (str_starts_with($raw, "\xFF\xFE") || str_starts_with($raw, "\xFE\xFF"))
		{
			$result['file_error'] = 'encoding';

			return $result;
		}

		if (str_contains($raw, "\0"))
		{
			$result['file_error'] = 'binary';

			return $result;
		}

		if (str_starts_with($raw, "\xEF\xBB\xBF"))
		{
			$result['bom'] = true;
			$raw = substr($raw, 3);
		}

		if (trim($raw) === '')
		{
			$result['file_error'] = 'empty';

			return $result;
		}

		if (preg_match('//u', $raw) !== 1)
		{
			$result['file_error'] = 'encoding';

			return $result;
		}

		$delimiter = self::detectDelimiter($raw);
		$result['delimiter'] = $delimiter;
		$records = self::readRecords($raw, $delimiter);

		if ($records === [])
		{
			$result['file_error'] = 'empty';

			return $result;
		}

		$headerIndex = self::findHeaderIndex($records, $configured);

		if ($headerIndex === null)
		{
			$guess = self::widestRecordIndex($records);
			$headerInfo = self::indexHeaders($records[$guess] ?? []);
			$result['header_line'] = $guess + 1;
			$result['headers'] = $headerInfo['labels'];
			$result['mapping'] = self::mappingReport($configured, $headerInfo['index']);
			$result['unmapped'] = $headerInfo['unmapped'];
			$result['file_error'] = 'missing_id_column';
			$result['file_error_detail'] = $configured['member_id'];

			return $result;
		}

		$headerInfo = self::indexHeaders($records[$headerIndex]);

		if (count($headerInfo['labels']) > self::MAX_COLUMNS)
		{
			$result['file_error'] = 'too_many_columns';

			return $result;
		}

		$resolved = self::resolveAll($configured, $headerInfo['index']);

		if ($resolved['member_id'] === null)
		{
			$result['header_line'] = $headerIndex + 1;
			$result['headers'] = $headerInfo['labels'];
			$result['mapping'] = self::mappingReport($configured, $headerInfo['index']);
			$result['unmapped'] = $headerInfo['unmapped'];
			$result['file_error'] = 'missing_id_column';
			$result['file_error_detail'] = $configured['member_id'];

			return $result;
		}

		$result['header_line'] = $headerIndex + 1;
		$result['headers'] = $headerInfo['labels'];
		$result['mapping'] = self::mappingReport($configured, $headerInfo['index']);
		$result['unmapped'] = self::unmappedHeaders($headerInfo, $resolved);

		$seenIds = [];
		$dataRows = 0;

		for ($i = $headerIndex + 1, $count = count($records); $i < $count; $i++)
		{
			$record = $records[$i];

			if (self::isEmptyRecord($record))
			{
				continue;
			}

			$dataRows++;

			if ($dataRows > self::MAX_DATA_ROWS)
			{
				$result['file_error'] = 'too_many_rows';
				$result['rows'] = [];
				$result['processed'] = $dataRows;

				return $result;
			}

			$line = $i + 1;
			$built = self::buildRow($record, $resolved, $delimiter, $line, $seenIds);

			if (count($result['sample']) < 5)
			{
				$result['sample'][] = $built['sample'];
			}

			foreach ($built['errors'] as $error)
			{
				if (count($result['row_errors']) < self::MAX_ERRORS)
				{
					$result['row_errors'][] = $error;
				}
				else
				{
					$result['truncated_errors'] = true;
				}
			}

			if ($built['errors'] === [] && $built['row'] !== null)
			{
				$memberId = $built['row']['member_id'];
				$seenIds[strtoupper($memberId)] = $line;
				$result['rows'][] = $built['row'];
			}
		}

		$result['processed'] = $dataRows;

		if ($result['row_errors'] !== [])
		{
			$result['rows'] = [];
		}

		return $result;
	}

	/**
	 * Fill blank mapping entries with the default header text.
	 *
	 * @param   array  $configuredColumns  Field names mapped to header text.
	 *
	 * @return  array  Field names mapped to header text.
	 *
	 * @since   1.0.0
	 */
	private static function normalizeConfigured(array $configuredColumns): array
	{
		$configured = [];

		foreach (self::DEFAULT_COLUMNS as $field => $default)
		{
			$value = trim((string) ($configuredColumns[$field] ?? ''));
			$configured[$field] = $value !== '' ? $value : $default;
		}

		return $configured;
	}

	/**
	 * Return the parser result before any file is read.
	 *
	 * @return  array  Empty parser result.
	 *
	 * @since   1.0.0
	 */
	private static function emptyResult(): array
	{
		return [
			'file_error'        => null,
			'file_error_detail' => '',
			'row_errors'        => [],
			'truncated_errors'  => false,
			'rows'              => [],
			'processed'         => 0,
			'delimiter'         => ',',
			'bom'               => false,
			'header_line'       => 0,
			'headers'           => [],
			'mapping'           => [],
			'sample'            => [],
			'unmapped'          => [],
			'configured'        => self::DEFAULT_COLUMNS,
		];
	}

	/**
	 * Choose a comma, semicolon, or tab from the start of the file.
	 *
	 * @param   string  $sample  File contents used to detect the separator.
	 *
	 * @return  string  Detected separator.
	 *
	 * @since   1.0.0
	 */
	private static function detectDelimiter(string $sample): string
	{
		$sample = substr($sample, 0, 8192);
		$lines = preg_split("/\r\n|\n|\r/", $sample) ?: [];
		$lines = array_values(
			array_filter(
				$lines,
				static function ($line) {
					return trim((string) $line) !== '';
				}
			)
		);
		$lines = array_slice($lines, 0, 20);

		$best = ',';
		$bestScore = -1;

		foreach ([',', ';', "\t"] as $delimiter)
		{
			$counts = [];

			foreach ($lines as $line)
			{
				$cells = str_getcsv($line, $delimiter, '"', '');
				$counts[] = count($cells);
			}

			if ($counts === [])
			{
				continue;
			}

			$freq = array_count_values($counts);
			arsort($freq);
			$mode = (int) array_key_first($freq);

			if ($mode < 2)
			{
				continue;
			}

			$score = ($mode * 100) + (int) $freq[$mode];

			if ($score > $bestScore)
			{
				$bestScore = $score;
				$best = $delimiter;
			}
		}

		return $best;
	}

	/**
	 * Split the file into records.
	 *
	 * @param   string  $raw        File contents without a leading BOM.
	 * @param   string  $delimiter  Field separator.
	 *
	 * @return  array  Records, without empty trailing lines.
	 *
	 * @since   1.0.0
	 */
	private static function readRecords(string $raw, string $delimiter): array
	{
		$handle = fopen('php://temp', 'r+');

		if ($handle === false)
		{
			return [];
		}

		fwrite($handle, $raw);
		rewind($handle);

		$records = [];

		while (($record = fgetcsv($handle, null, $delimiter, '"', '')) !== false)
		{
			$records[] = self::cleanRecord($record);
		}

		fclose($handle);

		while ($records !== [] && self::isEmptyRecord($records[count($records) - 1]))
		{
			array_pop($records);
		}

		return $records;
	}

	/**
	 * Trim one record and drop a leftover BOM.
	 *
	 * @param   array  $record  Fields from fgetcsv().
	 *
	 * @return  array  Trimmed field values.
	 *
	 * @since   1.0.0
	 */
	private static function cleanRecord(array $record): array
	{
		$clean = [];

		foreach ($record as $value)
		{
			$text = (string) $value;
			$text = preg_replace('/^\xEF\xBB\xBF/', '', $text) ?? $text;
			$clean[] = trim($text);
		}

		return $clean;
	}

	/**
	 * Report whether every field in a record is blank.
	 *
	 * @param   array  $record  Field values.
	 *
	 * @return  boolean  True when the record has no text.
	 *
	 * @since   1.0.0
	 */
	private static function isEmptyRecord(array $record): bool
	{
		foreach ($record as $value)
		{
			if (trim((string) $value) !== '')
			{
				return false;
			}
		}

		return true;
	}

	/**
	 * Find the header record that matches the most mapped fields.
	 *
	 * @param   array  $records     Records from the file.
	 * @param   array  $configured  Field names mapped to header text.
	 *
	 * @return  integer|null  Zero-based header index, or null when no identifier column exists.
	 *
	 * @since   1.0.0
	 */
	private static function findHeaderIndex(array $records, array $configured): ?int
	{
		$bestIndex = null;
		$bestScore = 0;

		foreach ($records as $index => $record)
		{
			if ($index > 15)
			{
				break;
			}

			$headerInfo = self::indexHeaders($record);
			$resolved = self::resolveAll($configured, $headerInfo['index']);

			if ($resolved['member_id'] === null)
			{
				continue;
			}

			$score = 0;

			foreach ($resolved as $hit)
			{
				if ($hit !== null)
				{
					$score++;
				}
			}

			if ($score > $bestScore)
			{
				$bestScore = $score;
				$bestIndex = $index;
			}
		}

		return $bestIndex;
	}

	/**
	 * Find the fullest record near the start of the file.
	 *
	 * @param   array  $records  Records from the file.
	 *
	 * @return  integer  Zero-based index of the fullest early record.
	 *
	 * @since   1.0.0
	 */
	private static function widestRecordIndex(array $records): int
	{
		$best = 0;
		$bestCount = -1;

		foreach ($records as $index => $record)
		{
			if ($index > 15)
			{
				break;
			}

			$count = 0;

			foreach ($record as $value)
			{
				if (trim((string) $value) !== '')
				{
					$count++;
				}
			}

			if ($count > $bestCount)
			{
				$bestCount = $count;
				$best = $index;
			}
		}

		return $best;
	}

	/**
	 * Index header labels by their normalized name.
	 *
	 * @param   array  $record  Header field values.
	 *
	 * @return  array  Normalized index, display labels, and duplicate labels.
	 *
	 * @since   1.0.0
	 */
	private static function indexHeaders(array $record): array
	{
		$index = [];
		$labels = [];
		$unmapped = [];

		foreach ($record as $position => $label)
		{
			$display = self::displayValue((string) $label);

			if ($display === '')
			{
				continue;
			}

			$labels[] = $display;
			$norm = self::normalizeHeader($display);

			if ($norm === '')
			{
				continue;
			}

			if (isset($index[$norm]))
			{
				$unmapped[] = $display;
				continue;
			}

			$index[$norm] = [
				'index' => $position,
				'label' => $display,
			];
		}

		return [
			'index'    => $index,
			'labels'   => $labels,
			'unmapped' => $unmapped,
		];
	}

	/**
	 * Normalize a header for comparison.
	 *
	 * @param   string  $header  Header text from the file or the options.
	 *
	 * @return  string  Lower case letters and digits only.
	 *
	 * @since   1.0.0
	 */
	public static function normalizeHeader(string $header): string
	{
		$header = preg_replace('/^\xEF\xBB\xBF/', '', $header) ?? $header;
		$header = strtolower(trim($header));
		$header = preg_replace('/[^a-z0-9]+/', '', $header) ?? '';

		return $header;
	}

	/**
	 * Resolve every mapped field against the header index.
	 *
	 * @param   array  $configured   Field names mapped to header text.
	 * @param   array  $headerIndex  Normalized header names.
	 *
	 * @return  array  Field name mapped to the matched column, or null.
	 *
	 * @since   1.0.0
	 */
	private static function resolveAll(array $configured, array $headerIndex): array
	{
		$resolved = [];

		foreach (self::DEFAULT_COLUMNS as $field => $default)
		{
			$resolved[$field] = self::resolveField($field, $configured[$field] ?? $default, $headerIndex);
		}

		return $resolved;
	}

	/**
	 * Resolve one field against the header index.
	 *
	 * @param   string  $field        Internal field name.
	 * @param   string  $configured   Configured header text.
	 * @param   array   $headerIndex  Normalized header names.
	 *
	 * @return  array|null  Matched column, or null when the field is absent.
	 *
	 * @since   1.0.0
	 */
	private static function resolveField(string $field, string $configured, array $headerIndex): ?array
	{
		$norm = self::normalizeHeader($configured);

		if ($norm !== '' && isset($headerIndex[$norm]))
		{
			return [
				'index' => $headerIndex[$norm]['index'],
				'label' => $headerIndex[$norm]['label'],
				'via'   => 'configured',
			];
		}

		$defaultNorm = self::normalizeHeader(self::DEFAULT_COLUMNS[$field]);
		$allowAlias = $field === 'member_id' || $norm === $defaultNorm;

		if (!$allowAlias)
		{
			return null;
		}

		foreach (self::ALIASES[$field] as $alias)
		{
			if (isset($headerIndex[$alias]))
			{
				return [
					'index' => $headerIndex[$alias]['index'],
					'label' => $headerIndex[$alias]['label'],
					'via'   => 'alias',
				];
			}
		}

		return null;
	}

	/**
	 * Describe how each field was matched.
	 *
	 * @param   array  $configured   Field names mapped to header text.
	 * @param   array  $headerIndex  Normalized header names.
	 *
	 * @return  array  One row per mapped field.
	 *
	 * @since   1.0.0
	 */
	private static function mappingReport(array $configured, array $headerIndex): array
	{
		$report = [];
		$resolved = self::resolveAll($configured, $headerIndex);

		foreach (self::DEFAULT_COLUMNS as $field => $default)
		{
			$hit = $resolved[$field];
			$report[] = [
				'field'      => $field,
				'configured' => self::displayValue($configured[$field] ?? $default),
				'resolved'   => $hit['label'] ?? '',
				'matched'    => $hit !== null,
				'via'        => $hit['via'] ?? '',
			];
		}

		return $report;
	}

	/**
	 * List header labels that did not match a field.
	 *
	 * @param   array  $headerInfo  Indexed header labels.
	 * @param   array  $resolved    Resolved field columns.
	 *
	 * @return  array  Unmatched header labels.
	 *
	 * @since   1.0.0
	 */
	private static function unmappedHeaders(array $headerInfo, array $resolved): array
	{
		$used = [];

		foreach ($resolved as $hit)
		{
			if ($hit !== null)
			{
				$used[self::normalizeHeader($hit['label'])] = true;
			}
		}

		$unmapped = $headerInfo['unmapped'];

		foreach ($headerInfo['labels'] as $label)
		{
			$norm = self::normalizeHeader($label);

			if ($norm === '' || isset($used[$norm]))
			{
				continue;
			}

			$unmapped[] = $label;
		}

		return array_values(array_unique($unmapped));
	}

	/**
	 * Validate one data record.
	 *
	 * @param   array    $record     Field values.
	 * @param   array    $resolved   Resolved field columns.
	 * @param   string   $delimiter  Field separator, used to choose date formats.
	 * @param   integer  $line       One-based line number.
	 * @param   array    $seenIds    Member identifiers already accepted.
	 *
	 * @return  array  Validated row, row errors, and a preview sample.
	 *
	 * @since   1.0.0
	 */
	private static function buildRow(array $record, array $resolved, string $delimiter, int $line, array $seenIds): array
	{
		$errors = [];
		$badFields = [];
		$values = [];
		$row = self::blankRow($line);

		$memberRaw = self::cell($record, $resolved['member_id']);
		$values['member_id'] = self::displayValue($memberRaw);

		if ($memberRaw === '')
		{
			$errors[] = self::rowError($line, 'empty_member_id', 'member_id', '');
			$badFields[] = 'member_id';
		}
		else
		{
			$memberId = self::filterMemberId($memberRaw);

			if ($memberId === null)
			{
				$errors[] = self::rowError($line, 'invalid_member_id', 'member_id', '');
				$badFields[] = 'member_id';
			}
			elseif (isset($seenIds[strtoupper($memberId)]))
			{
				$errors[] = self::rowError($line, 'duplicate_member_id', 'member_id', '');
				$badFields[] = 'member_id';
			}
			else
			{
				$row['member_id'] = $memberId;
			}
		}

		foreach (['first_name', 'last_name', 'address', 'city', 'state', 'postal_code', 'country', 'chapter'] as $field)
		{
			if ($resolved[$field] === null)
			{
				continue;
			}

			$raw = self::cell($record, $resolved[$field]);
			$values[$field] = self::displayValue($raw);

			if ($raw === '')
			{
				continue;
			}

			$filtered = self::filterText($raw, self::TEXT_LIMITS[$field]);

			if ($filtered === null)
			{
				$code = self::textFailureCode($raw, self::TEXT_LIMITS[$field]);
				$errors[] = self::rowError($line, $code, $field, '');
				$badFields[] = $field;
				continue;
			}

			$row[$field] = $filtered;
		}

		if ($row['first_name'] !== '' && $row['last_name'] !== '')
		{
			$row['has_name'] = true;
			$row['full_name'] = trim($row['first_name'] . ' ' . $row['last_name']);
		}

		if ($resolved['chapter'] !== null && $row['chapter'] !== '')
		{
			$row['has_chapter'] = true;
		}

		if ($resolved['email'] !== null)
		{
			$raw = self::cell($record, $resolved['email']);
			$values['email'] = self::displayValue($raw);

			if ($raw !== '')
			{
				$email = self::filterEmail($raw);

				if ($email === null)
				{
					$errors[] = self::rowError($line, 'invalid_email', 'email', '');
					$badFields[] = 'email';
				}
				else
				{
					$row['email'] = $email;
					$row['has_email'] = true;
				}
			}
		}

		if ($resolved['phone'] !== null)
		{
			$raw = self::cell($record, $resolved['phone']);
			$values['phone'] = self::displayValue($raw);

			if ($raw !== '')
			{
				$phone = self::filterPhone($raw);

				if ($phone === null)
				{
					$errors[] = self::rowError($line, 'invalid_phone', 'phone', '');
					$badFields[] = 'phone';
				}
				else
				{
					$row['phone'] = $phone;
				}
			}
		}

		foreach (['join_date' => 'has_join', 'expiry_date' => 'has_expiry'] as $field => $flag)
		{
			if ($resolved[$field] === null)
			{
				continue;
			}

			$raw = self::cell($record, $resolved[$field]);
			$values[$field] = self::displayValue($raw);

			if ($raw === '')
			{
				continue;
			}

			$date = self::parseDate($raw, $delimiter);

			if ($date === null)
			{
				$errors[] = self::rowError($line, 'invalid_date', $field, '');
				$badFields[] = $field;
				continue;
			}

			$row[$field] = $date;
			$row[$flag] = true;
			$values[$field] = $date;
		}

		$life = false;

		if ($resolved['life_member'] !== null)
		{
			$raw = self::cell($record, $resolved['life_member']);
			$values['life_member'] = self::displayValue($raw);

			if ($raw !== '')
			{
				$parsedLife = self::parseLifeMember($raw);

				if ($parsedLife === null)
				{
					$errors[] = self::rowError($line, 'invalid_type', 'life_member', '');
					$badFields[] = 'life_member';
				}
				else
				{
					$life = $parsedLife;
				}
			}
		}

		if ($life)
		{
			$row['membership_type'] = \Wendfold\Component\Wendfold\Administrator\Helper\MembershipImport::LIFE_FLAG;
			$row['has_type'] = true;
		}
		elseif ($resolved['membership_type'] !== null)
		{
			$raw = self::cell($record, $resolved['membership_type']);
			$values['membership_type'] = self::displayValue($raw);

			if ($raw !== '')
			{
				$type = self::parseMembershipType($raw);

				if ($type === null)
				{
					$errors[] = self::rowError($line, 'invalid_type', 'membership_type', '');
					$badFields[] = 'membership_type';
				}
				else
				{
					$row['membership_type'] = $type;
					$row['has_type'] = true;
				}
			}
		}

		if ($resolved['status'] !== null)
		{
			$raw = self::cell($record, $resolved['status']);
			$values['status'] = self::displayValue($raw);

			if ($raw !== '')
			{
				$row['status'] = self::displayValue($raw);
				$row['has_status'] = true;
			}
		}

		if ($row['has_expiry'])
		{
			$row['national_verified'] = $row['expiry_date'] >= SiteTime::fromApplication()->today() ? 1 : 0;
		}
		elseif ($row['has_status'])
		{
			$verified = self::parseStatus($row['status']);

			if ($verified === null)
			{
				$errors[] = self::rowError($line, 'invalid_status', 'status', '');
				$badFields[] = 'status';
			}
			else
			{
				$row['national_verified'] = $verified;
			}
		}

		$sample = [
			'line'       => $line,
			'values'     => $values,
			'bad_fields' => $badFields,
		];

		if ($errors !== [] || $row['member_id'] === '')
		{
			return [
				'row'    => null,
				'errors' => $errors,
				'sample' => $sample,
			];
		}

		return [
			'row'    => $row,
			'errors' => $errors,
			'sample' => $sample,
		];
	}

	/**
	 * Return a data row with empty values.
	 *
	 * @param   integer  $line  One-based line number.
	 *
	 * @return  array  Empty data row.
	 *
	 * @since   1.0.0
	 */
	private static function blankRow(int $line): array
	{
		return [
			'line'              => $line,
			'member_id'         => '',
			'first_name'        => '',
			'last_name'         => '',
			'full_name'         => '',
			'email'             => '',
			'phone'             => '',
			'address'           => '',
			'city'              => '',
			'state'             => '',
			'postal_code'       => '',
			'country'           => '',
			'join_date'         => null,
			'expiry_date'       => null,
			'membership_type'   => null,
			'status'            => '',
			'chapter'           => '',
			'has_name'          => false,
			'has_email'         => false,
			'has_join'          => false,
			'has_expiry'        => false,
			'has_type'          => false,
			'has_status'        => false,
			'has_chapter'       => false,
			'national_verified' => null,
		];
	}

	/**
	 * Read one mapped cell.
	 *
	 * @param   array       $record  Field values.
	 * @param   array|null  $hit     Resolved column, or null when the field is absent.
	 *
	 * @return  string  Trimmed cell text.
	 *
	 * @since   1.0.0
	 */
	private static function cell(array $record, ?array $hit): string
	{
		if ($hit === null)
		{
			return '';
		}

		return trim((string) ($record[$hit['index']] ?? ''));
	}

	/**
	 * Build one row error.
	 *
	 * @param   integer  $line    One-based line number.
	 * @param   string   $code    Stable error code.
	 * @param   string   $field   Internal field name.
	 * @param   string   $detail  Optional extra detail.
	 *
	 * @return  array  Line, code, field, and detail.
	 *
	 * @since   1.0.0
	 */
	private static function rowError(int $line, string $code, string $field, string $detail): array
	{
		return [
			'line'   => $line,
			'code'   => $code,
			'field'  => $field,
			'detail' => self::displayValue($detail),
		];
	}

	/**
	 * Accept a member identifier or reject it.
	 *
	 * @param   string  $raw  Raw cell text.
	 *
	 * @return  string|null  Accepted identifier, or null.
	 *
	 * @since   1.0.0
	 */
	private static function filterMemberId(string $raw): ?string
	{
		$value = trim($raw);

		if ($value === '' || strlen($value) > self::MEMBER_ID_MAX)
		{
			return null;
		}

		if (preg_match('/[\x00-\x1F\x7F]/', $value) === 1)
		{
			return null;
		}

		if (preg_match('/[<>\'"`\\\\]/', $value) === 1)
		{
			return null;
		}

		if (preg_match('/^[A-Za-z0-9][A-Za-z0-9 ._\-\/]{0,19}$/', $value) !== 1)
		{
			return null;
		}

		return $value;
	}

	/**
	 * Accept plain text within a length limit.
	 *
	 * @param   string   $raw  Raw cell text.
	 * @param   integer  $max  Maximum number of characters.
	 *
	 * @return  string|null  Accepted text, or null.
	 *
	 * @since   1.0.0
	 */
	private static function filterText(string $raw, int $max): ?string
	{
		if (preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', $raw) === 1)
		{
			return null;
		}

		if (str_contains($raw, '<') || str_contains($raw, '>'))
		{
			return null;
		}

		$value = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $raw);
		$value = trim(preg_replace('/\s+/u', ' ', $value) ?? '');

		if ($value === '')
		{
			return null;
		}

		if (self::stringLength($value) > $max)
		{
			return null;
		}

		return $value;
	}

	/**
	 * Choose the error code for rejected text.
	 *
	 * @param   string   $raw  Raw cell text.
	 * @param   integer  $max  Maximum number of characters.
	 *
	 * @return  string  Error code too_long or illegal_characters.
	 *
	 * @since   1.0.0
	 */
	private static function textFailureCode(string $raw, int $max): string
	{
		$flattened = str_replace(["\r\n", "\r", "\n", "\t"], ' ', $raw);
		$flattened = trim(preg_replace('/\s+/u', ' ', $flattened) ?? '');

		if ($flattened !== '' && self::stringLength($flattened) > $max)
		{
			return 'too_long';
		}

		return 'illegal_characters';
	}

	/**
	 * Accept a single email address.
	 *
	 * @param   string  $raw  Raw cell text.
	 *
	 * @return  string|null  Accepted address, or null.
	 *
	 * @since   1.0.0
	 */
	private static function filterEmail(string $raw): ?string
	{
		$value = trim($raw);

		if ($value === '' || strlen($value) > 100)
		{
			return null;
		}

		if (preg_match('/[\s<>]/', $value) === 1)
		{
			return null;
		}

		if (filter_var($value, FILTER_VALIDATE_EMAIL) === false)
		{
			return null;
		}

		return $value;
	}

	/**
	 * Accept a phone number made of digits and common separators.
	 *
	 * @param   string  $raw  Raw cell text.
	 *
	 * @return  string|null  Accepted phone text, or null.
	 *
	 * @since   1.0.0
	 */
	private static function filterPhone(string $raw): ?string
	{
		$value = trim($raw);

		if (preg_match('/^[0-9+().\-\s]{3,30}$/', $value) !== 1)
		{
			return null;
		}

		return $value;
	}

	/**
	 * Parse a calendar date.
	 *
	 * @param   string  $raw        Raw cell text.
	 * @param   string  $delimiter  Field separator, used to choose the date order.
	 *
	 * @return  string|null  Date as Y-m-d, or null.
	 *
	 * @since   1.0.0
	 */
	private static function parseDate(string $raw, string $delimiter): ?string
	{
		$raw = trim($raw);
		$raw = preg_replace('/[ T]\d{2}:\d{2}:\d{2}$/', '', $raw) ?? $raw;
		$raw = trim($raw);

		if ($raw === '')
		{
			return null;
		}

		$formats = $delimiter === ';'
			? ['Y-m-d', 'Y-n-j', 'Y/m/d', 'd/m/Y', 'j/n/Y', 'd.m.Y', 'j.n.Y', 'd-m-Y', 'j-n-Y', 'd/m/y', 'j/n/y']
			: ['Y-m-d', 'Y-n-j', 'Y/m/d', 'm/d/Y', 'n/j/Y', 'm-d-Y', 'n-j-Y', 'm/d/y', 'n/j/y'];

		foreach ($formats as $format)
		{
			$date = \DateTimeImmutable::createFromFormat('!' . $format, $raw);
			$errors = \DateTimeImmutable::getLastErrors();

			if (!$date instanceof \DateTimeImmutable)
			{
				continue;
			}

			if (is_array($errors) && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
			{
				continue;
			}

			$year = (int) $date->format('Y');

			if ($year < 1900 || $year > 2100)
			{
				continue;
			}

			return $date->format('Y-m-d');
		}

		return null;
	}

	/**
	 * Parse a life-member flag.
	 *
	 * @param   string  $raw  Raw cell text.
	 *
	 * @return  boolean|null  True, false, or null when the value is not recognized.
	 *
	 * @since   1.0.0
	 */
	private static function parseLifeMember(string $raw): ?bool
	{
		$value = strtolower(trim($raw));

		if (in_array($value, ['1', 'y', 'yes', 'true', 'life'], true))
		{
			return true;
		}

		if (in_array($value, ['0', 'n', 'no', 'false'], true))
		{
			return false;
		}

		return null;
	}

	/**
	 * Parse a membership type.
	 *
	 * @param   string  $raw  Raw cell text.
	 *
	 * @return  string|null  Import code, or null when the cell is empty or too long.
	 *
	 * @since   1.0.0
	 */
	private static function parseMembershipType(string $raw): ?string
	{
		return \Wendfold\Component\Wendfold\Administrator\Helper\MembershipImport::canonicalCode($raw);
	}

	/**
	 * Parse a membership status into the verified flag.
	 *
	 * @param   string  $raw  Raw cell text.
	 *
	 * @return  integer|null  1 when verified, 0 when not, or null.
	 *
	 * @since   1.0.0
	 */
	private static function parseStatus(string $raw): ?int
	{
		$key = preg_replace('/[^a-z0-9]+/', '', strtolower(trim($raw))) ?? '';

		if (in_array($key, ['active', 'current', 'goodstanding'], true))
		{
			return 1;
		}

		if (in_array($key, ['expired', 'lapsed', 'inactive', 'cancelled', 'canceled', 'terminated'], true))
		{
			return 0;
		}

		return null;
	}

	/**
	 * Make a cell safe to show in the preview.
	 *
	 * @param   string  $value  Raw cell text.
	 *
	 * @return  string  Short single-line text.
	 *
	 * @since   1.0.0
	 */
	private static function displayValue(string $value): string
	{
		$value = str_replace(["\0", "\r", "\n", "\t"], ' ', $value);
		$value = trim(strip_tags($value));
		$value = preg_replace('/\s+/u', ' ', $value) ?? '';

		return self::limit($value, 80);
	}

	/**
	 * Count characters, using UTF-8 when mbstring is available.
	 *
	 * @param   string  $value  Text to measure.
	 *
	 * @return  integer  Character count.
	 *
	 * @since   1.0.0
	 */
	private static function stringLength(string $value): int
	{
		if (function_exists('mb_strlen'))
		{
			return mb_strlen($value, 'UTF-8');
		}

		return strlen($value);
	}

	/**
	 * Shorten text to a maximum number of characters.
	 *
	 * @param   string   $value  Text to shorten.
	 * @param   integer  $max    Maximum number of characters.
	 *
	 * @return  string  Text no longer than the limit.
	 *
	 * @since   1.0.0
	 */
	private static function limit(string $value, int $max): string
	{
		if (self::stringLength($value) <= $max)
		{
			return $value;
		}

		if (function_exists('mb_substr'))
		{
			return mb_substr($value, 0, $max, 'UTF-8');
		}

		return substr($value, 0, $max);
	}
}
