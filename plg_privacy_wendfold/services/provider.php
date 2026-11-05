<?php

/**
 * @package     Wendfold.Plugin
 * @subpackage  Privacy.Wendfold
 *
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * DI service provider -- registers the plugin with the Joomla container.
 *
 * @since  1.0.0
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Extension\PluginInterface;
use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Wendfold\Plugin\Privacy\Wendfold\Extension\Wendfold;

return new class implements ServiceProviderInterface
{
	/**
	 * Register the service provider in the DI container.
	 *
	 * @param   Container  $container  The DI container.
	 *
	 * @return  void
	 *
	 * @since   1.0.0
	 */
	public function register(Container $container): void
	{
		$container->set(
			PluginInterface::class,
			function (Container $container) {
				$db         = $container->get(DatabaseInterface::class);
				$app        = Factory::getApplication();
				$pluginData = (array) PluginHelper::getPlugin('privacy', 'wendfold');

				// Joomla 6: construct with config only; PluginHelper injects the dispatcher on import.
				$plugin = new Wendfold($pluginData);
				$plugin->setDatabase($db);
				$plugin->setApplication($app);

				return $plugin;
			}
		);
	}
};
