<?php

/**
 * Wendfold - Chapter Management System
 * Administrator Service Provider
 *
 * @package     Wendfold
 * @subpackage  com_wendfold
 * @copyright   Copyright (C) 2026 Adan Lozano
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Component\Router\RouterFactoryInterface;
use Joomla\CMS\Dispatcher\ComponentDispatcherFactoryInterface;
use Joomla\CMS\Extension\ComponentInterface;
use Joomla\CMS\Extension\Service\Provider\ComponentDispatcherFactory;
use Joomla\CMS\Extension\Service\Provider\MVCFactory;
use Joomla\CMS\Extension\Service\Provider\RouterFactory;
use Joomla\CMS\MVC\Factory\MVCFactoryInterface;
use Joomla\DI\Container;
use Joomla\DI\ServiceProviderInterface;
use Wendfold\Component\Wendfold\Administrator\Extension\WendfoldComponent;
use Wendfold\Component\Wendfold\Administrator\Helper\WendfoldLog;

return new class implements ServiceProviderInterface
{
	public function register(Container $container): void
	{
		// Register component logger once (shared with CLI / task plugin path).
		WendfoldLog::ensureCategory();

		$container->registerServiceProvider(
			new MVCFactory('\\Wendfold\\Component\\Wendfold')
		);

		$container->registerServiceProvider(
			new ComponentDispatcherFactory('\\Wendfold\\Component\\Wendfold')
		);

		$container->registerServiceProvider(
			new RouterFactory('\\Wendfold\\Component\\Wendfold')
		);

		$container->set(
			ComponentInterface::class,
			function (Container $container) {
				$component = new WendfoldComponent(
					$container->get(ComponentDispatcherFactoryInterface::class)
				);

				$component->setMVCFactory($container->get(MVCFactoryInterface::class));
				$component->setRouterFactory($container->get(RouterFactoryInterface::class));

				return $component;
			}
		);
	}
};
