<?php 
/**
 * Class for handeling the request data
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
 class Request extends Basics {

	/**
	 * flag to prevent Request::checkRequest() to execute twice.
	 *
	 * @access private
	 * @var boolean
	 */
	private static $s_checkedRequest = false;

	/**
	 * Singleton holder.
	 *
	 * @access public
	 * @var object
	 */
	public static $singleton = true;

	/**
	 * The current action.
	 *
	 * @access public
	 * @var string
	 */
	public $action;

	/**
	 * The current apikey.
	 *
	 * @access public
	 * @var string
	 */
	public $apikey;

	/**
	 * The current project slug.
	 *
	 * @access public
	 * @var string
	 */
	public $slug;

	/**
	 * The current version of the installed project.
	 *
	 * @access public
	 * @var string
	 */
	public $version;

	/**
	 * The targeted branch.
	 *
	 * @access public
	 * @var string
	 */
	public $branch;

	/**
	 * The type of the project.
	 *
	 * @access public
	 * @var string
	 */
	public $type;

	/**
	 * The initiation
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		$this->_checkRequest();
	}

	/**
	 * Checks if the Request is authorized by comparing the apikey and ip-address
	 * with the data from project settings.
	 *
	 * @access public
	 * @return bool true if authorized false if not.
	 */
	public function validateRequest() {
		$config = $this->get_instance( 'Data' )->projectSettings;

		if( $config->allowed === '*' ) return true;

		elseif( is_array( $config->allowed ) ) {
			foreach( $config->allowed as $allowed ) {
				$r = false;

				if( isset( $allowed->ip ) ) {
					if( is_array( $allowed->ip )
					 && in_array( $_SERVER['REMOTE_ADDR'], $allowed->ip )
					) {
						$r = true;
					} elseif( $allowed->ip === $_SERVER['REMOTE_ADDR'] ) {
						$r = true;
					} else {
						$r = false;
						continue;
					}
				}

				if( isset( $allowed->apikey ) ) {
					if( is_array( $allowed->apikey )
					 && in_array( $this->apikey, $allowed->apikey )
					) {
						$r = true;
					} elseif( $allowed->apikey === $this->apikey ) {
						$r = true;
					} else {
						$r = false;
						continue;
					}
				}

				if( isset( $allowed->branch ) ) {
					if( is_array( $allowed->branch )
					 && in_array( $this->branch, $allowed->branch )
					) {
						$r = true;
					} elseif( $allowed->branch === $this->branch ) {
						$r = true;
					} else {
						$r = false;
						continue;
					}
				}

				if( $r === true ) {
					return true;
				}
			}
			$msg = 'No access. This can be because: 1. Your ip address (' . $_SERVER['REMOTE_ADDR'] . ') is not registered.'
				. ' Or 2. your api key (' . $this->apikey . ') is invalid.'
				. ' Or 3. you do not have access to the requested branch (' . $this->branch . ').';
				
			$this->_exit( 'bad access', $msg, 17 );
		} 

		return false;
	}

	/**
	 * First checking function that determines if all needed keys are given in the request.
	 * Deletes the Request after instance variables are set.
	 *
	 * @access private
	 * @return void
	 */
	private function _checkRequest() {
		if( self::$s_checkedRequest === false ) {
			// Just take action, request & api-key ignore all other request values
			foreach( array( 
				'action',
				'apikey',
				'slug',
				( isset( $_REQUEST['action'] ) && $_REQUEST['action'] == 'basic_check' 
					? 'version' : null )
			) as $k ) {
				if( $k === null ) continue;

				if( !isset( $_REQUEST[ $k ] )
				 && ( $k !== 'apikey' || !isset( $_REQUEST[ ( $k = 'api-key' ) ] ) )
				) {
					$this->_r['missing'] = $k;
					$this->_exit( 'undefined', 'missing input', 8 );
					// MINIMUM ONE KEY IS MISSING
				} else {
					if( $k === 'apikey' || $k === 'api-key' ) {
						$this->apikey = $_REQUEST[ $k ];
					} else {
						$this->$k = preg_replace( '/[^A-Za-z0-9-_\.!]/', '', $_REQUEST[ $k ] );
					}
				}
			}

			if( isset( $_REQUEST['branch'] ) ) {
				$this->branch = preg_replace( '/[^A-Za-z0-9-_\.!]/', '', $_REQUEST['branch'] );
			} else {
				$this->branch = 'master';
			}

			if( isset( $_REQUEST['type'] ) && $_REQUEST['type'] === 'theme' ) {
				$this->type = 'theme';
			} else {
				$this->type = 'plugin';
			}

			unset( $_REQUEST );
			unset( $_POST );
			unset( $_GET );

			self::$s_checkedRequest = true;
		}
	}
} ?>