<?php
/*
 * Plugin Name:       Zabbix API Connector
 * Plugin URI:        https://localhost
 * Description:       Provides hooks to Specific Zabbix API (credentials inside)
 * Version:           1.0.0
 * Requires at least: 6.0
 * Requires PHP:      7.4
 * Author:            SKG
 * Author URI:        https://example.com
 * License:           GPL v2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Update URI:        https://example.com/my-plugin/
 * Text Domain:       zabbix-api
 * Domain Path:       /languages
 */

# Exit if accessed directly
defined( 'ABSPATH' ) || die('nani?');

define( 'ZAPIDEB_VERSION', '1.0.0' );
define( 'ZAPIDEB_DOMAIN', 'umm' );

define( 'ZAPIDEB_DIR', plugin_dir_path( __FILE__ ) );
define( 'ZAPIDEB_URL', plugin_dir_url( __FILE__ ) );
define( 'ZAPIDEB_FILE', __FILE__ );
define( 'ZAPIDEB_SLUG', plugin_basename( __FILE__ ) );

final class ZAPIDEB {
	private static $_ran = false;

	public static function run(){
		if ( self::$_ran ) return;

		self::$_ran = true;

		self::_init();
	}

	private static function _init(){
		self::_autoloaders();
		self::_includes();

		self::_hooks();
	}

	###########################################################################

	private static function _autoloaders() {
		# Custom autoload
		spl_autoload_register( array(__CLASS__, 'autoload') );

		# Composer autoloader
		include_once ZAPIDEB_DIR . 'vendor/autoload.php';
	}

	CONST BASE_NS = 'zapideb';

	public static function autoload( $className ) {
		### Autoloader will include files with class' namespace OR Class in specific paths, with specific name prefix. i.e. eigther alogc\pages\base.autoload.php OR crons/ECL_Cron_Something.autoload.php

		# Autoload files with specific prefix and suffix in specific directories
		$ns_prefix   = self::BASE_NS."\\";
		$file_suffix = '.php';

		$dirs = [
			ZAPIDEB_DIR . 'classes/',
		];

		# Remove left backslash from className
		$className = ltrim($className, "\\");

		# Target Filename to search for
		$targetFilename = '';
		
		# Condition for ns prefix is the prefix
		if ( substr($className, 0, strlen($ns_prefix)) == $ns_prefix ) {
			$targetFilename = substr( $className, strlen($ns_prefix) );
			$targetFilename = str_replace('\\', '/', $targetFilename);
		}
		else {
			# Not interested
			return;
		}

		foreach( $dirs as $dir ) {
			if ( file_exists( $dir . $targetFilename. $file_suffix ) ) {
				include_once $dir.$targetFilename.$file_suffix;
				return;
			}
		}
	}

	private static function _includes(){
		# Include required files		
	}

	private static function _hooks(){
		add_action('init', [__CLASS__, 'debug']);

		# Return class to use for Zebbix API
		add_filter( 'zapideb_class', [__CLASS__, 'zapideb_class'], 10, 2 );
	}

	static function zapideb_class( $return, $args=[] ) {
		return '\\zapideb\\ZabbixDebil';
	}

	static function debug() {

		//\zapideb\ZabbixDebil::atest();
		// $r = new ReflectionClass('zapideb\ZabbixDebil');
		// d( $r->getMethods() );

		try {
		    // $Z = apply_filters( 'zapideb_class', null );
			// $r = $Z::addWebScenario();
		    // d( $r );
		}
		catch( Throwable $te ) {
		    //d( $te );
		}
	}
}

ZAPIDEB::run();