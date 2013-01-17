<?php 
/*
 Project Update Api for Wordpress
 Copyright (C) 2012 Hannes Diercks

 This program is free software; you can redistribute it and/or modify
 it under the terms of the GNU General Public License as published by
 the Free Software Foundation; either version 2 of the License, or
 (at your option) any later version.

 This program is distributed in the hope that it will be useful,
 but WITHOUT ANY WARRANTY; without even the implied warranty of
 MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 GNU General Public License for more details.
 
 You should have received a copy of the GNU General Public License along
 with this program; if not, write to the Free Software Foundation, Inc.,
 51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.
*/

/*******
 Inspired by 
	 "automatic-theme-plugin-update" (https://github.com/jeremyclark13/automatic-theme-plugin-update)
		 by Kaspars Dambis (kaspars@konstruktors.com)
		 Modified by Jeremy Clark http://clark-technet.com
		 Donate Link: https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=73N8G8UPGDG2Q

	"BitBucket API Library" (https://bitbucket.org/steinerd/bitbucket-api-library/overview)
		by Anthony Steiner (http://steinerd.com)
*******/

/**
 * Project Update Api for Wordpress
 *
 * Handles Wordpress plugin and theme update requests by
 * comparing the request data with files hosted on github,
 * bitbucket or gitlab.
 * See https://github.com/Xiphe/WP-Project-Update-API/wiki
 * for detailed information and documentation.
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class ProjectUpdates extends Basics {
	// ERROR COUNTER: 33 - Just to know the last used error number.
	
	/**
	 * The baseUrl of this script. Used to generate download- and detail-view links.
	 * set in globalConfigs.json
	 * 
	 * @access public
	 * @var string
	 */
	public static $sBaseUrl;

	/**
	 * Flag if the remote files should be cached.
	 * Required for converting the gitlab archives from .tar.gz to .zip
	 * and folder renaming.
	 * set in globalConfigs.json
	 * 
	 * @access public
	 * @var bool
	 */
	public static $sUseCache;

	/**
	 * Singleton holder.
	 * 
	 * @access public
	 * @var object
	 */
	public static $singleton = true;

	/**
	 * The currently used API.
	 * 
	 * @access public
	 * @var object
	 */
	public $Api;

	/**
	 * Initiation
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		$Eggs = $this->get_instance( 'Easter' );

		/* 
		 * At first check if all required arguments are passed by the request,
		 */
		$Request = $this->get_instance( 'Request' );

		if( $Request->action === 'clean_cacheandtemp' ) {
			$this->get_instance( 'Cache' )->cleanCacheAndTemp();
		}

		/*
		 * Initiate the Data Object. The initiation gets the current project settings.
		 */
		$Data = $this->get_instance( 'Data' );

		/*
		 * Set BaseUrl and CacheFlag from global config.
		 */
		self::$sBaseUrl = $Data->get_globalSetting( 'baseUrl' );
		self::$sUseCache = $Data->get_globalSetting( 'useCache' );

		/*
		 * Check if the Request is valid and authorized.
		 */
		if( $Request->validateRequest() ) {
			/*
			 * Initiates the API for the current host.
			 */
			$this->get_api();

			/*
			 * Initiate the Cache Object. The initiation checks if cache should be used
			 * and compares the local commit id with the database to check if local files
			 * can be used.
			 */
			$this->get_instance( 'Cache' );

			/*
			 * Let the API do it's job.
			 */			
			$this->Api->do_action();
		}

		/*
		 * Fall-back if the previous methods have not killed the script.
		 * should print the serialized "nothing happened" error array.
		 */
		$this->_exit();
	}

	/**
	 * Determines if a specific API exist for the current host and loads it
	 * otherwise fall-back to the default API.
	 *
	 * @access public
	 * @return object the API
	 */
	public function get_api() {
		if( !is_object( $this->Api ) ) {
			$apiName = ucfirst(
				preg_replace(
					'/[-]/',
					'', 
					$this->get_instance( 'Data' )->projectSettings->host . 'Api'
				)
			);

			$file = dirname( __FILE__ ) . DS . 'class.' . strtolower( $apiName ) . '.php';

			$this->Api = $this->get_instance( 'Api' );

			if( file_exists( $file ) ) {
				$this->Api = $this->get_instance( $apiName );
			}
		}
		return $this->Api;
	}

	/**
	 * Checks if the global "pluginCheckerBench" was set and prints the
	 * run time of the script on destruction.
	 * 
	 * @access public
	 * @return void
	 */
	public function __destruct() {
		if( isset( $GLOBALS['pluginCheckerBench'] ) ) {
			$time = microtime(true) - $GLOBALS['pluginCheckerBench'];

			echo "<br /><br />Handled request in " . $time . " seconds.";
		}
	}
}
?>