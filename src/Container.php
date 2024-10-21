<?php

namespace Phresto;

class Container {
	public static $cacheOn = false;
	private static $objectCache = [];

	public static function _reset() {
		self::$objectCache = [];
	}

	public static function _getCacheName( $name, $arguments ) {
		return md5( $name . serialize( $arguments ) );
	}

	public static function _register( $name, $value ) {
		if ( self::$objectCache[$name] ) {
			unset( self::$objectCache[$name] );
		}

		self::$objectCache[$name] = $value;
	}

	public static function __callStatic( $name, $arguments ) {

		if ( mb_strpos( $name, '\\' ) === false ) {
			$name = __NAMESPACE__ . '\\' . $name;
		}

		$cacheName = self::_getCacheName( $name, $arguments );
		if ( self::$cacheOn && self::$objectCache[$cacheName] ) {
			return self::$objectCache[$cacheName];
		}

		$reflection_class = new \ReflectionClass( $name );
		$objectInstance = $reflection_class->newInstanceArgs( $arguments );
		if ( self::$cacheOn ) {
			self::$objectCache[$cacheName] = $objectInstance;
		}

		return $objectInstance;
	}

}
