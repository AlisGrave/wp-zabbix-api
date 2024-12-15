<?php
namespace zapideb;

use ZAPIDEB as PluginMain;
use WP_Error;

use ZabbixApi\Exception;
use ZabbixApi\ZabbixApi;

/**
 * Custom Zabbix connector to Debil instance
 */

class ZabbixDebil {
	# API TOKEN - Someday will have admin setting
	CONST API_TOKEN = '';

	CONST API_URL = 'https://{YOUR ZABBIX URL}/zabbix/api_jsonrpc.php';

	# Hard-coded - reset on zabbix change
	CONST GROUPID_WEBSITES = 22;
	# Hard-coded - template id for ICMP Ping
	CONST TEMPLATE_ICMP = 10564;

	# This is existing host in Zabbix. The default host ID for Zabbix Server
	# No need to use other for now, so it is hard coded
	CONST HOST_ID = '10084';

	# Ping is anonymous, should return Zabbix version - service apiinfoVersion
	static function Ping() {
		$api = null;
		try {
			$api = new ZabbixApi( self::API_URL );

			# No need, apiinfoVersion is anonymous
			//$api->setAuthToken( self::API_TOKEN );
			return $api->apiinfoVersion();
			# Same as above
			//return $api->request('apiinfo.version', NULL, '', FALSE);
		}
		# Exception is ZabbixApi\Exception
		catch( Exception $e ) {
			return $e->getMessage();
		}
	}

	###########################################################################

	/**
	 * Calls httptestCreate - adds web scenario to default host
	 * with some predefined data
	 */
	static function addWebScenario(  ) {

		$arrayKeyProperty = ''; # Return all
		$arrayKeyProperty = 'httptestids'; # Return id only

		$asset_id = 1;
		$name = 'UID['.get_current_user_id().'] Asset['.$asset_id.'] '.time();

		# See https://www.zabbix.com/documentation/7.0/en/manual/api/reference/httptest/object#web-scenario
		$params = [
			//'httptestid' => X # Read only, for update
			# NAME MUST BE UNIQUE or will return error
			'name'		=> $name,
			'hostid'	=> self::HOST_ID,
			//'agent'		=> 'D Monitor',
			'delay'		=> '30s', 		# Default to '1m'
			'status'	=> 0, 			# 0-enabled (default), 1-disabled
			'steps'	=> [
				[
					"name" => "Asset Url",
					"url"  => "https://example.com",
					//"status_codes" => "200",
					"no" => 1,
				]
			],
			'tags'	=> [
				[ 'tag'	=> 'wpuid',   'value' => 'uid_'.get_current_user_id() ],
				[ 'tag'	=> 'assetid', 'value' => 'asset_1' ],
			],
		];

		$res = self::request( 'httptestCreate', $params, $arrayKeyProperty );
		return $res;
	}

	static function removeWebScenario( $id ) {
		return self::request( 'httptestDelete',[
			$id
		]);
	}

	/**
	 * Return list of items filtered by tag 'belongs_to', which is different for each user
	 * second parameter $itemName is the sanitized name given by the user for the item
	 *
	 * @param string $belongs - value of the tag belongs_to
	 * @param string $itemName - value of the tag itemName (can be empty for all)
	 * @param array $args - additional arguments
	 *
	 * @return array|WP_Error - list of items
	 */
	static function getItemsByTags( string $belongs, string $itemName='', $args=[] ) {
		#

		$items = self::request( 'itemGet',
				[
					'hostids' => self::HOST_ID,
					"output" 	=> "extend",
					'webitems'  => 1,
					//'limit'		=> 100, # No limit, should be 6 for each scenario
					'tags'		=> [
						[ 'tag' => 'belongs_to', 'value' => $belongs, 'operator' => 1]
					]
				]
		);
		//d( $items );
		$out = [];
		foreach( $items as $item ) {
			$out[] = [ $item->name, $item->itemid, $item->key_ ];
		}

		d( $out );
	}

	static function getItemHistory( string $itemID ) {
		return self::request( 'historyGet',
				[
					//'hostids' 	=> self::HOST_ID,

					'history'	=> 0, # 0=float
					"output" 	=> "extend",
					'itemids'	=> $itemID,
					'sortfield' => 'clock',
					'sortorder' => 'DESC',

					'limit'		=> 100,
					//'time_from' => strtotime('-1 month'),
				],
				[ 'debug' => 0 ]

		);
	}

	###########################################################################
	# Create Host ICMP Ping
	###########################################################################
	static function createHost_ICMP( $name, $dns, $tags=[] ) {
		# Convert $tags [ '{tagname}' => '{tagvalue}' ] to Zabbix tags
		$z_tags = [];
		foreach ( $tags as $tname => $tvalue ) {
			$z_tags[] = [ 'tag'	=> $tname, 'value' => $tvalue ];
		}
		
		$res = self::request('hostCreate',
			[
				'host'	=> $name,			# MUST BE UNIQUE !
				'interfaces' => [
					'type'	=> 1, # Agent
					'main'	=> 1, 
					'useip' => 0, # Use DNS, not ip
					'ip'	=> '',
					'dns'	=> $dns,
					'port'	=> '10050', 	# Required, default
				],
				'groups' => [
					[ 'groupid'=> self::GROUPID_WEBSITES ], # Web sites
				],
				'tags' => $z_tags,
				'templates' => [
					'templateid' => self::TEMPLATE_ICMP, # ICMP Ping
				]
			],
			[
				'debug'	=> 0,
			]
		);

		//var_dump($res);
		# Expected response is an object with prop hostids, which is an array with single element
		if ( empty($res) || !is_object($res) || empty($res->hostids) || !is_array($res->hostids) || count($res->hostids) != 1 ) {
			return false;
		}
		return $res->hostids[0];
	}
	static function removeHost_ICMP( $hostid ) {
		$res = self::request( 'hostDelete', 
			[
				$hostid
			],
			[
				'debug'	=> 0,
			]
		);
		# Expected response is an object with prop hostids, which is an array with single element
		if ( empty($res) || !is_object($res) || empty($res->hostids) || !is_array($res->hostids) || count($res->hostids) != 1 ) {
			return false;
		}
		return $res->hostids[0];
	}

	static function host_ICMP_item_ping( $hostid ) {
		$res = self::request('itemGet',
			[
				'hostids' => (int)$hostid,
				"search" => [
					"key_" => "icmppingsec",
				]
			],
			[
				'debug'	 => 0,
				'return' => 'itemid'
			]
		);
		# Response is an array with hopefully 1 element object with props
		# Interested in prop 'itemid', but only if 'key_' == 'icmppingsec'
		if ( empty($res) || !is_array($res) ) {
			return false;
		}
		
		foreach( $res as $item ) {
			if ( !empty($item->key_) && $item->key_ == 'icmppingsec' ) {
				return $item->itemid;
			}
		}
		return false;
	}

	static function getHosts() {}

	###########################################################################
	# Wrap request with Try/Catch
	# return WP_Error on exception
	static function request( $method, $args, $opts=[] ) {
		try {
			$api = new ZabbixApi( self::API_URL );
			$api->setAuthToken( self::API_TOKEN );
			//$api->printCommunication(true); # DO NOT THIS WAY

			if ( method_exists($api, $method) ) {
				$debug = isset($opts['debug']) ? !empty($opts['debug']) : false;
				$arrayKeyProperty = isset($opts['return']) ? $opts['return'] : '';

				$ret = call_user_func_array( [$api,$method], [ $args, $arrayKeyProperty ]);

				if ( $debug && function_exists('d') ) d( $api, $ret );

				return $ret;
			}
		}
		# Exception is ZabbixApi\Exception
		catch( Exception $e ) {
			return new WP_Error( 'exception', $e->getMessage() );
		}
	}
}
