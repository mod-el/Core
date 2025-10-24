<?php namespace Model\Core\Providers;

use Model\Router\AbstractRouterProvider;

class RouterProvider extends AbstractRouterProvider
{
	public static function getRoutes(): array
	{
		return [
			[
				'pattern' => '/zk',
				'controller' => 'Zk',
			],
		];
	}
}
