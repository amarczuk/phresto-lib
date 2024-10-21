<?php

namespace Phresto;

class Utils {

	public static function autoload( $className ) {
		$base = PHRESTO_ROOT . '/';
		$path = explode( '\\', trim( $className, '\\' ) );
		$modules = Config::getConfig( 'modules' );

		if ( empty( $modules ) ) {
			self::updateModules();
			$modules = Config::getConfig( 'modules' );
		}

		if ( ( empty( $path[0] ) || $path[0] != 'Phresto' ) && ( empty( $path[1] ) || $path[1] != 'Modules' ) ) return;

		foreach ( $modules as $module => $files ) {
			if ( !empty( $path[3] ) && !empty( $files[$path[2]] ) ) {
				if ( in_array( $path[3] . '.php', $files[$path[2]] ) ) {
					$file = $base . '/modules/' . $module . '/' . mb_strtolower( $path[2] ) . '/' . $path[3] . '.php';
					break;
				}
			} else if ( !empty( $files['class'] ) && in_array( $path[2] . '.php', $files['class'] ) ) {
				$file = $base . '/modules/' . $module . '/class/' . $path[2] . '.php';
				break;
			}
		}

		if ( !empty( $file ) && file_exists( $file ) ) {
			require_once( $file );
		}
    }

    public static function libAutoad( $className ) {
        $base = __DIR__ . '/';
        $path = str_replace( 'Phresto\\', '', trim( $className, '\\' ) );
        $path = str_replace( '\\', '/', $path );
        $file = $base . $path . '.php';

        if ( !empty( $file ) && file_exists( $file ) ) {
			require_once( $file );
		}
    }

    public static function registerLibAutoload() {
		spl_autoload_register( 'Phresto\\Utils::libAutoad' );
	}

	public static function registerAutoload() {
		$app = Config::getConfig( 'app' );
		if ( $app['app']['env'] == 'dev' ) {
			Config::delConfig( 'modules' );
            self::updateModules();
		}
		spl_autoload_register( 'Phresto\\Utils::autoload' );
	}

    public static function Redirect( $url, $delay = 0 ) {
        if ( !$delay ) {
            header( "Location:{$url}" );
            die();

        } else {
            header( "Refresh: {$delay}; url={$url}" );
        }
    }

    public static function updateModules() {
    	$getFiles = function( $base, $flag = 0 ) {
			return array_map( function( $elem ) use ( $base, $flag ) { return str_replace( $base, '', $elem ); }, glob( $base . '*', $flag ) );
		};

		$base = PHRESTO_ROOT . '/modules/';
		$modules = $getFiles( $base, GLOB_ONLYDIR );

		$types = ['Controller', 'Model', 'class', 'Interf'];

		$config = [];

		foreach ( $modules as $module ) {
			foreach ( $types as $type ) {
                $files = $getFiles( $base . $module . '/' . strtolower($type) . '/' );
				$config[$module][$type] = $files;
			}
        }

		Config::saveConfig( 'modules', $config );
    }

    public static function is_assoc_array( $arr ) {
    	if ( !isset( $arr ) || !is_array( $arr ) ) return false;
    	return array_keys($arr) !== range(0, count($arr) - 1);
    }

}
