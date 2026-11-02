<?php

/**
 * Wendfold - installer layout check.
 *
 * The package ZIP stores the schema guard at admin/src/Helper. The installed
 * copy stores script.php beside src/Helper. Uninstall loads the installed
 * copy and must not fatal on the package path. Install and update preflight
 * must find the guard in whichever layout is running.
 *
 * @package     Wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

declare(strict_types=1);

$root = dirname(__DIR__, 2);
$script = $root . '/com_wendfold/script.php';
$guard = $root . '/com_wendfold/admin/src/Helper/SchemaGuard.php';

if (!is_file($script) || !is_file($guard))
{
	fwrite(STDERR, "installer files are missing\n");
	exit(1);
}

$failures = 0;

foreach (['installed', 'package'] as $layout)
{
	$dir = sys_get_temp_dir() . '/wendfold-' . $layout . '-' . bin2hex(random_bytes(4));

	try
	{
		if (!stageLayout($dir, $script, $guard, $layout))
		{
			$failures++;
			continue;
		}

		$result = runHarness($dir, $layout);
		echo $result['stdout'];

		if ($result['exit'] !== 0)
		{
			fwrite(STDERR, $result['stderr']);
			$failures++;
		}
	}
	finally
	{
		removeTree($dir);
	}
}

exit($failures === 0 ? 0 : 1);

/**
 * Copy script.php into one layout.
 *
 * @param   string  $dir     Temporary component root.
 * @param   string  $script  Path to com_wendfold/script.php.
 * @param   string  $guard   Path to SchemaGuard.php.
 * @param   string  $layout  "installed" or "package".
 *
 * @return  boolean
 *
 * @since   1.0.0
 */
function stageLayout(string $dir, string $script, string $guard, string $layout): bool
{
	$guardDir = $layout === 'installed'
		? $dir . '/src/Helper'
		: $dir . '/admin/src/Helper';

	if (!mkdir($guardDir, 0777, true) && !is_dir($guardDir))
	{
		fwrite(STDERR, "could not create {$layout} layout\n");

		return false;
	}

	$options = dirname($guard) . '/ComponentOptions.php';

	if (!is_file($options))
	{
		fwrite(STDERR, "component options helper is missing\n");

		return false;
	}

	if (!copy($script, $dir . '/script.php') || !copy($guard, $guardDir . '/SchemaGuard.php') || !copy($options, $guardDir . '/ComponentOptions.php'))
	{
		fwrite(STDERR, "could not copy installer files for {$layout}\n");

		return false;
	}

	$absent = $layout === 'installed'
		? $dir . '/admin/src/Helper/SchemaGuard.php'
		: $dir . '/src/Helper/SchemaGuard.php';

	if (is_file($absent))
	{
		fwrite(STDERR, "{$layout} layout has the other path\n");

		return false;
	}

	return true;
}

/**
 * Resolve the staged script via its ServiceProvider and run uninstall, then install preflight.
 *
 * @param   string  $dir     Temporary component root.
 * @param   string  $layout  "installed" or "package".
 *
 * @return  array{exit: int, stdout: string, stderr: string}
 *
 * @since   1.0.0
 */
function runHarness(string $dir, string $layout): array
{
	$harness = $dir . '/harness.php';
	file_put_contents($harness, harnessSource());

	$pipes = [];
	$process = proc_open(
		[PHP_BINARY, $harness, $dir, $layout],
		[
			1 => ['pipe', 'w'],
			2 => ['pipe', 'w'],
		],
		$pipes
	);

	if (!\is_resource($process))
	{
		return ['exit' => 1, 'stdout' => '', 'stderr' => "could not start {$layout} harness\n"];
	}

	$stdout = stream_get_contents($pipes[1]);
	$stderr = stream_get_contents($pipes[2]);
	fclose($pipes[1]);
	fclose($pipes[2]);

	return [
		'exit' => proc_close($process),
		'stdout' => $stdout === false ? '' : $stdout,
		'stderr' => $stderr === false ? '' : $stderr,
	];
}

/**
 * Child script. Stubs the installer types so uninstall can run.
 *
 * @return  string
 *
 * @since   1.0.0
 */
function harnessSource(): string
{
	return <<<'PHP'
<?php

declare(strict_types=1);

namespace Joomla\CMS\Installer
{
	class InstallerAdapter
	{
	}

	interface InstallerScriptInterface
	{
	}
}

namespace Joomla\DI
{
	interface ServiceProviderInterface
	{
		public function register(Container $container): void;
	}

	/**
	 * Minimal Container stub with the set/share/get/has signatures used by
	 * Joomla\DI\Container and by Wendfold installer service providers.
	 */
	class Container
	{
		/**
		 * @var    array<string, array{value: mixed, shared: bool, protected: bool, instance: mixed}>
		 */
		private array $resources = [];

		public function set($key, $value, $shared = false, $protected = false)
		{
			$this->resources[(string) $key] = [
				'value'     => $value,
				'shared'    => (bool) $shared,
				'protected' => (bool) $protected,
				'instance'  => null,
			];

			return $this;
		}

		public function share($key, $value, $protected = false)
		{
			return $this->set($key, $value, true, $protected);
		}

		public function has($resourceName): bool
		{
			return \array_key_exists((string) $resourceName, $this->resources);
		}

		public function get($resourceName)
		{
			$key = (string) $resourceName;

			if (!$this->has($key))
			{
				throw new \RuntimeException(
					\sprintf("Resource '%s' has not been registered with the container.", $key)
				);
			}

			$entry = &$this->resources[$key];
			$value = $entry['value'];

			if (!\is_callable($value))
			{
				return $value;
			}

			if ($entry['shared'] && $entry['instance'] !== null)
			{
				return $entry['instance'];
			}

			// Match Joomla\DI\ContainerResource: factories receive the container.
			$instance = $value($this);

			if ($entry['shared'])
			{
				$entry['instance'] = $instance;
			}

			return $instance;
		}
	}
}

namespace Joomla\CMS\Language
{
	class Text
	{
		public static function _($string)
		{
			return (string) $string;
		}

		public static function sprintf($string, ...$args)
		{
			return (string) $string;
		}
	}
}

namespace Joomla\CMS
{
	class Factory
	{
		public static function getContainer()
		{
			return new \WendfoldLayoutContainer();
		}

		public static function getApplication()
		{
			return new \WendfoldLayoutApp();
		}
	}
}

namespace Joomla\Database
{
	interface DatabaseInterface
	{
	}
}

namespace
{
class WendfoldLayoutContainer
{
	public function get($id)
	{
		return new WendfoldLayoutDb();
	}
}

class WendfoldLayoutApp
{
	public function enqueueMessage($message, $type = 'message'): void
	{
	}
}

class WendfoldLayoutQuery
{
	public function select($columns)
	{
		return $this;
	}

	public function from($table)
	{
		return $this;
	}

	public function where($condition)
	{
		return $this;
	}

	public function delete($table)
	{
		return $this;
	}
}

class WendfoldLayoutDb implements Joomla\Database\DatabaseInterface
{
	public function getServerType(): string
	{
		return 'mysql';
	}

	public function replacePrefix(string $table): string
	{
		return $table;
	}

	public function createQuery()
	{
		return new WendfoldLayoutQuery();
	}

	public function getQuery($new = false)
	{
		return $this->createQuery();
	}

	public function quoteName($name, $as = null): string
	{
		return (string) $name;
	}

	public function quote($text): string
	{
		return "'" . (string) $text . "'";
	}

	public function setQuery($query, $offset = 0, $limit = 0)
	{
		return $this;
	}

	public function loadResult()
	{
		return null;
	}

	public function execute()
	{
		return true;
	}
}

define('_JEXEC', 1);
define('JVERSION', '6.2.0');

$layoutDir = $argv[1] ?? '';
$layout = $argv[2] ?? '';

try
{
	$provider = require $layoutDir . '/script.php';

	if (!$provider instanceof Joomla\DI\ServiceProviderInterface)
	{
		fwrite(STDERR, "script.php must return a ServiceProviderInterface\n");
		exit(1);
	}

	$container = new Joomla\DI\Container();
	$provider->register($container);

	if (!$container->has(Joomla\CMS\Installer\InstallerScriptInterface::class))
	{
		fwrite(STDERR, "InstallerScriptInterface was not registered\n");
		exit(1);
	}

	$script = $container->get(Joomla\CMS\Installer\InstallerScriptInterface::class);
	$adapter = new Joomla\CMS\Installer\InstallerAdapter();

	if ($layout === 'installed')
	{
		$script->uninstall($adapter);
		echo "installed-layout uninstall ok\n";

		if ($script->preflight('install', $adapter) !== true)
		{
			fwrite(STDERR, "installed preflight returned false\n");
			exit(1);
		}

		echo "installed-layout preflight ok\n";
		exit(0);
	}

	if ($script->preflight('install', $adapter) !== true)
	{
		fwrite(STDERR, "package preflight returned false\n");
		exit(1);
	}

	echo "package-layout preflight ok\n";
	$script->uninstall($adapter);
	echo "package-layout uninstall ok\n";
}
catch (Throwable $error)
{
	fwrite(STDERR, $error::class . ': ' . $error->getMessage() . "\n");
	exit(1);
}
}
PHP;
}

/**
 * Delete a temporary layout.
 *
 * @param   string  $dir  Directory to remove.
 *
 * @return  void
 *
 * @since   1.0.0
 */
function removeTree(string $dir): void
{
	if (!is_dir($dir))
	{
		return;
	}

	$items = scandir($dir);

	if ($items === false)
	{
		return;
	}

	foreach ($items as $item)
	{
		if ($item === '.' || $item === '..')
		{
			continue;
		}

		$path = $dir . '/' . $item;

		if (is_dir($path))
		{
			removeTree($path);
			continue;
		}

		unlink($path);
	}

	rmdir($dir);
}
