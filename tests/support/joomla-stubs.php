<?php

/**
 * Wendfold - Joomla type stubs for the router tests.
 *
 * Stand-ins for the CMS classes the site router typehints, so build() and
 * parse() can run without installing Joomla.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

namespace Joomla\CMS\Application;

/**
 * Application contract the router typehints.
 *
 * @since  1.0.0
 */
interface CMSApplicationInterface
{
}

namespace Joomla\CMS\Categories;

/**
 * Category factory contract the router constructor typehints.
 *
 * @since  1.0.0
 */
interface CategoryFactoryInterface
{
}

namespace Joomla\CMS\Component\Router;

/**
 * Component router contract.
 *
 * @since  1.0.0
 */
interface RouterInterface
{
	/**
	 * Prepare a query before it is built.
	 *
	 * @param   array  $query  URL query variables.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function preprocess($query);

	/**
	 * Build path segments from a query.
	 *
	 * @param   array  $query  URL query variables.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function build(&$query);

	/**
	 * Read path segments back into query variables.
	 *
	 * @param   array  $segments  Remaining path segments, updated in place.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function parse(&$segments);
}

namespace Joomla\CMS\Filter;

/**
 * URL slug helper used when Joomla is not installed.
 *
 * @since  1.0.0
 */
class OutputFilter
{
	/**
	 * Build a URL slug from a title.
	 *
	 * ASCII titles are enough for the router tests. Hyphens in the source
	 * are treated as spaces, then every run of spaces or other non-letters
	 * becomes a single hyphen.
	 *
	 * @param   string  $string    Raw text.
	 * @param   string  $language  Unused language code.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function stringURLSafe($string, $language = '')
	{
		$str = str_replace('-', ' ', (string) $string);
		$str = trim(strtolower($str));
		$str = preg_replace('/(\s|[^A-Za-z0-9\-])+/', '-', $str);

		return trim((string) $str, '-');
	}
}

namespace Joomla\CMS\Menu;

/**
 * Menu contract the router typehints.
 *
 * @since  1.0.0
 */
abstract class AbstractMenu
{
	/**
	 * Load one menu item.
	 *
	 * @param   integer  $id  Menu item id.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	abstract public function getItem($id);

	/**
	 * Load menu items that match the given attributes.
	 *
	 * @param   string|array  $attributes  Attribute name or names.
	 * @param   mixed         $values      Attribute value or values.
	 * @param   boolean       $firstonly   Return only the first match.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	abstract public function getItems($attributes, $values, $firstonly = false);

	/**
	 * Load the active menu item.
	 *
	 * @return  object|null
	 *
	 * @since   1.0.0
	 */
	abstract public function getActive();
}

namespace Joomla\Database;

/**
 * Bound-parameter types used by metric writes.
 *
 * @since  1.0.0
 */
class ParameterType
{
	public const BOOLEAN = 'boolean';
	public const INTEGER = 'int';
	public const LARGE_OBJECT = 'lob';
	public const NULL = 'null';
	public const STRING = 'string';
}

/**
 * Database contract the router uses to read event titles.
 *
 * @since  1.0.0
 */
interface DatabaseInterface
{
	/**
	 * Create a new query object.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function createQuery();

	/**
	 * Start a query.
	 *
	 * @param   boolean  $new  Unused. Present so the call shape matches Joomla.
	 *
	 * @return  object
	 *
	 * @since   1.0.0
	 */
	public function getQuery($new = false);

	/**
	 * Quote an identifier.
	 *
	 * @param   string  $name  Column name.
	 * @param   string  $as    Optional alias.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function quoteName($name, $as = null);

	/**
	 * Store the query that the next load will run.
	 *
	 * @param   object   $query   Query to run.
	 * @param   integer  $offset  Unused row offset.
	 * @param   integer  $limit   Unused row limit.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function setQuery($query, $offset = 0, $limit = 0);

	/**
	 * Return the rows the test prepared.
	 *
	 * @param   string  $key  Unused row key.
	 *
	 * @return  array
	 *
	 * @since   1.0.0
	 */
	public function loadObjectList($key = null);
}

namespace Joomla\CMS\Date;

/**
 * Stand-in for Joomla's Date.
 *
 * format() without the local flag prints UTC, matching the CMS class, so a
 * fixed clock can be checked without installing Joomla.
 *
 * @since  1.0.0
 */
class Date extends \DateTime
{
	/**
	 * Zone the date was constructed in.
	 *
	 * @var    \DateTimeZone
	 * @since  1.0.0
	 */
	private \DateTimeZone $siteZone;

	/**
	 * @param   string  $date  Date string, or "now".
	 * @param   mixed   $tz    Zone name or DateTimeZone. Null is UTC.
	 *
	 * @since   1.0.0
	 */
	public function __construct($date = 'now', $tz = null)
	{
		if (!($tz instanceof \DateTimeZone))
		{
			$tz = new \DateTimeZone(\is_string($tz) && $tz !== '' ? $tz : 'UTC');
		}

		$this->siteZone = $tz;

		parent::__construct($date, $tz);
	}

	/**
	 * Format the instant.
	 *
	 * @param   string   $format     PHP date format.
	 * @param   boolean  $local      True prints the construction zone. False prints UTC.
	 * @param   boolean  $translate  Unused. Present so the call shape matches Joomla.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function format($format, $local = false, $translate = true): string
	{
		$active = $this->getTimezone();

		if (!$local)
		{
			parent::setTimezone(new \DateTimeZone('UTC'));
		}
		else
		{
			parent::setTimezone($this->siteZone);
		}

		$formatted = parent::format((string) $format);
		parent::setTimezone($active);

		return $formatted;
	}

	/**
	 * UTC timestamp string, matching Joomla Date::toSql().
	 *
	 * @param   boolean  $local  Unused.
	 * @param   mixed    $db     Unused.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public function toSql($local = false, $db = null): string
	{
		return $this->format('Y-m-d H:i:s');
	}

	/**
	 * Move the zone used when format() is asked for local time.
	 *
	 * The instant stays the same. This matches Joomla Date::setTimezone(),
	 * which stores the zone for a later local format.
	 *
	 * @param   \DateTimeZone|string  $tz  Zone.
	 *
	 * @return  \DateTime
	 *
	 * @since   1.0.0
	 */
	public function setTimezone($tz): \DateTime
	{
		if (!($tz instanceof \DateTimeZone))
		{
			$tz = new \DateTimeZone((string) $tz);
		}

		$this->siteZone = $tz;

		return parent::setTimezone($tz);
	}
}

namespace Joomla\CMS\HTML;

/**
 * Enough of HTMLHelper::date to format a calendar day without Joomla.
 *
 * The third argument matches Joomla 6. true and false both move a UTC
 * midnight into the process zone (user zone, or the site offset). null
 * leaves the UTC calendar day alone.
 *
 * @since  1.0.0
 */
class HTMLHelper
{
	/**
	 * Language keys this double can turn into a PHP date format.
	 *
	 * DATE_FORMAT_LC3 matches the English Joomla string.
	 *
	 * @var    array<string, string>
	 * @since  1.0.0
	 */
	private const FORMATS = [
		'DATE_FORMAT_LC3' => 'd F Y',
	];

	/**
	 * Dispatch HTMLHelper::_() without declaring a method named underscore.
	 *
	 * @param   string  $name       Called method. Only the date key is implemented.
	 * @param   array   $arguments  Key, then the arguments HTMLHelper::date accepts.
	 *
	 * @return  mixed
	 *
	 * @since   1.0.0
	 */
	public static function __callStatic(string $name, array $arguments)
	{
		if ($name !== '_' || ($arguments[0] ?? '') !== 'date')
		{
			return null;
		}

		return self::date(...array_slice($arguments, 1));
	}

	/**
	 * Format a date the way Joomla's date helper chooses a zone.
	 *
	 * @param   string        $input      Date text. Parsed as UTC.
	 * @param   string|null   $format     PHP date format or a known language key.
	 * @param   mixed         $tz         True, false, null, or an IANA zone name.
	 * @param   boolean       $gregorian  Unused. Present so the call shape matches Joomla.
	 *
	 * @return  string
	 *
	 * @since   1.0.0
	 */
	public static function date($input = 'now', $format = null, $tz = true, $gregorian = false): string
	{
		unset($gregorian);

		if ($format === null || $format === '')
		{
			$format = 'DATE_FORMAT_LC1';
		}

		$format = self::FORMATS[$format] ?? (string) $format;
		$utc    = new \DateTimeZone('UTC');
		$date   = new \DateTimeImmutable((string) $input, $utc);

		if ($tz === null)
		{
			$zone = $utc;
		}
		elseif ($tz === true || $tz === false)
		{
			$zone = new \DateTimeZone(date_default_timezone_get());
		}
		elseif (\is_string($tz) && $tz !== '')
		{
			$zone = new \DateTimeZone($tz);
		}
		else
		{
			$zone = $utc;
		}

		return $date->setTimezone($zone)->format($format);
	}
}

namespace Joomla\CMS\Log;

/**
 * Enough of Log for model catch blocks that record a category message.
 *
 * @since  1.0.0
 */
class Log
{
	public const ALL = 30719;

	public const ERROR = 8;

	public const WARNING = 16;

	public const INFO = 64;

	public const DEBUG = 128;

	/**
	 * Messages recorded by add(), oldest first.
	 *
	 * @var    list<array{0: string, 1: integer, 2: string}>
	 * @since  1.0.0
	 */
	public static array $messages = [];

	/**
	 * Logger options passed to addLogger(), oldest first.
	 *
	 * @var    list<array{0: array, 1: integer, 2: array}>
	 * @since  1.0.0
	 */
	public static array $loggers = [];

	/**
	 * Record one log line for assertions.
	 *
	 * @param   string   $message   Detail text.
	 * @param   integer  $priority  Priority constant.
	 * @param   string   $category  Log category.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function add($message, $priority = 16, $category = 'jerror'): void
	{
		self::$messages[] = [(string) $message, (int) $priority, (string) $category];
	}

	/**
	 * Record a logger registration the way the component provider does.
	 *
	 * @param   array    $options     Logger options.
	 * @param   integer  $levels      Priority mask.
	 * @param   array    $categories  Category list.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public static function addLogger(array $options, $levels = self::ALL, array $categories = []): void
	{
		self::$loggers[] = [$options, (int) $levels, $categories];
	}
}

namespace Joomla\CMS\Installer;

/**
 * Installer adapter stand-in for script.php loads in unit tests.
 *
 * @since  1.0.0
 */
class InstallerAdapter
{
}

/**
 * Installer script contract for Joomla 6 service-provider scripts.
 *
 * @since  1.0.0
 */
interface InstallerScriptInterface
{
}

namespace Joomla\DI;

/**
 * DI container stand-in for installer service providers.
 *
 * @since  1.0.0
 */
class Container
{
}

/**
 * Service provider contract for installer script.php returns.
 *
 * @since  1.0.0
 */
interface ServiceProviderInterface
{
	/**
	 * @param   Container  $container  Container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function register(Container $container): void;
}
