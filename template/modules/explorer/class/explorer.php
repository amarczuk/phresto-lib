<?php

namespace Phresto\Modules;
use Phresto\Controller;
use Phresto\ModelController;
use Phresto\Config;

class explorer {

	const CLASSNAME = __CLASS__;

	public static function getRoutes( $all = false ) {
		$modules = Config::getConfig( 'modules' );
		$endpoints = [];
		$controllers = [];

		foreach ( $modules as $modname => $module ) {
			if ( isset( $module['Controller'] ) && is_array( $module['Controller'] ) ) {
				foreach ( $module['Controller'] as $file ) {
					$name = str_replace( '.php', '', $file );
					if ( in_array( $name, $endpoints ) ) continue;

					array_push( $endpoints, $name );
					$class = '\\Phresto\\Modules\\Controller\\' . $name;
					if ( !class_exists( $class ) ) continue;

					$controllers = array_merge( $controllers, $class::discover( $all ) );
				}
			}

			if ( isset( $module['Model'] ) && is_array( $module['Model'] ) ) {
				foreach ( $module['Model'] as $file ) {
					$name = str_replace( '.php', '', $file );
					if ( in_array( $name, $endpoints ) ) continue;

					array_push( $endpoints, $name );
					$class = '\\Phresto\\Modules\\Model\\' . $name;
					if ( !class_exists( $class ) ) continue;

					$controllers = array_merge( $controllers, ModelController::discover( $all, $class ) );
				}
			}
		}

		return $controllers;
	}
}
