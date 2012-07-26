<?php
/**
 * Class for handeling the setting and db data
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class Data extends Basics {

	/**
	 * Singleton holder.
	 *
	 * @access public
	 * @var object
	 */
	public static $singleton = true;

	/**
	 * The parsed project settings
	 *
	 * @access public
	 * @var object
	 */
	public $projectSettings;

	/**
	 * The absolute basic keys needed in a project settings file
	 *
	 * @access private
	 * @var array
	 */
	private static $s_requiredProjectSettings = array( 'projectOwner', 'host' );

	/**
	 * The parsed global settings
	 *
	 * @access private
	 * @var object
	 */
	private $_globalSettings;

	/**
	 * The parsed DB
	 *
	 * @access private
	 * @var object
	 */
	private $_DB;

	/**
	 * The initiation
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		$this->_get_projectSettings();
	}

	/**
	 * Decodes the global settings from its file and returns the requested value.
	 *
	 * @access public
	 * @param  string $key the key or keypath of the searched value (see Basics::recursive_get())
	 * @return mixed       the requested value or null if not found.
	 */
	public function get_globalSetting( $key ) {
		if( !isset( $this->_globalSettings ) ) {
			if( file_exists( ( $file = dirname(__FILE__) . DS . '..' . DS . 'globalSettings.json' ) ) ) {
				$rawSettings = '';
				foreach ( file( $file ) as $line ) {
					if( !in_array( substr( trim( $line ), 0, 2 ), array( '//', '/*', '* ', '*/' ) ) ) {
						$rawSettings .= trim( $line );
					}
				}
				$this->_globalSettings = @json_decode( $rawSettings );
				unset( $rawSettings );

				if( !is_object( $this->_globalSettings ) ) {
					$this->_exit( 'undefined', 'error in globalSettings.json file.', 10 );
				}
			} else {
				$this->_exit( 'undefined', 'globalSettings.json not existing', 11 );
			}
		}
		return $this->rget( $this->_globalSettings, $key );
	}

	/**
	 * Getter for the project folder name.
	 *
	 * This is used by Cache::_checkTmpArchiveFolder() because github and bitbucket
	 * are naming the project folder containting the commit sha and the user name
	 * and gitlab does not put the project files into a folder in the array.
	 *
	 * @access public
	 * @return string the foldername
	 */
	public function get_folderName() {
		if( isset( $this->projectSettings->folderName ) ) {
			return $this->projectSettings->folderName;
		} else {
			return $this->get_instance( 'Request' )->slug;
		}
	}

	/**
	 * Returns the requested value from database.
	 *
	 * @access public
	 * @param  string $key the key or keypath of the searched value (see Basics::recursive_get())
	 * @return mixed       the requested value or null if not found.
	 */
	public function get_DB( $key ) {
		$this->_initDB();
		return $this->rget( $this->_DB, $key );
	}

	/**
	 * Sets a value in the database.
	 *
	 * @access public
	 * @param  string $key   the key or keypath where the value should be set. (see Basics::recursive_set())
	 * @param  mixed  $value the value
	 * @return object        returns the Data instance for chaining with Data::save()
	 */
	public function set_DB( $key, $value ) {
		$this->_initDB();
		$this->rset( $this->_DB, $key, $value );
		return $this;
	}

	/**
	 * Saves the database.
	 *
	 * @access public
	 * @return object returns itself for methodchaining.
	 */
	public function save_DB() {
		file_put_contents( dirname( __FILE__ ) . DS . '..' . DS . 'db.json', $this->_json_readable_encode( $this->_DB ) );
		return $this;
	}

	/**
	 * Searches for the project settings file using the slug parameter of the request.
	 *
	 * @access private
	 * @return void
	 */
	private function _get_projectSettings() {
		if( file_exists( ( $file = dirname( __FILE__ ) . DS . '..' . DS
			. 'projectSettings' . DS . strtolower( $this->get_instance( 'Request' )->slug ) . '.json' ) ) 
		) {
			$rawSettings = '';
			foreach ( file( $file ) as $line ) {
				if( !in_array( substr( trim( $line ), 0, 2 ), array( '//', '/*', '* ', '*/' ) ) ) {
					$rawSettings .= trim( $line );
				}
			}
			$this->projectSettings = @json_decode( $rawSettings );
			unset( $rawSettings );

			if( !is_object( $this->projectSettings ) ) {
				$this->_exit( 'undefined', 'error in config file.', 6 );
			}

			/*
			 * Gitlab does not need the projectOwner setting but a globalkey because there is no
			 * standard url for gitlab.
			 */
			if( isset( $this->projectSettings->host )
			 && $this->projectSettings->host === 'gitlab'
			) {
				self::$s_requiredProjectSettings = array( 'globalkey' );
				if( !isset( $this->projectSettings->projectOwner ) ) {
					$this->projectSettings->projectOwner = 'MrX';
				}
			}

			foreach( self::$s_requiredProjectSettings as $k ) {
				if( !isset( $this->projectSettings->$k ) ) {
					$this->_exit( 'undefined', 'missing "' . $k . '" in config.', 7 );
				}
			}
		} else {
			$this->_exit( 'undefined', 'Project settings file not existing', 5 );
		}
	}

	/**
	 * Decodes the database from its file or generates an empty one if not found.
	 *
	 * @access private
	 * @return void
	 */
	private function _initDB() {
		if( !is_object( $this->_DB ) ) {
			$this->_DB = @json_decode( file_get_contents( dirname( __FILE__ ) . DS . '..' . DS . 'db.json' ) );
			if( !is_object( $this->_DB ) ) {
				$this->_DB = new stdClass;
			}
		}
	}

	/**
	 * Converts data into a readable json format
	 *
	 * by bohwaz http://www.php.net/manual/de/function.json-encode.php#102091
	 * 
	 * modyfyed codestyle to fit to the rest and logic from function to method
	 * by Hannes Diercks, 2012
	 * 
	 * @access private
	 * @param  mixed   $in the object or array to be converted
	 * @return string      json
	 */
	private function _json_readable_encode( $in, $indent = 0, $from_array = false ) {
	    $_escape = function ( $str ) {
	        return preg_replace( "!([\b\t\n\r\f\"\\'])!", "\\\\\\1", $str );
	    };

	    $out = '';

	    foreach( $in as $key => $value ) {
	        $out .= str_repeat( "\t", $indent + 1 );
	        $out .= "\"" . $_escape( ( string ) $key ) . "\" : ";

	        if( is_object( $value ) || is_array( $value ) ) {
	            $out .= "";
	            $out .= $this->_json_readable_encode( $value, $indent + 1 );
	        } elseif( is_bool( $value ) ) {
	            $out .= $value ? 'true' : 'false';
	        } elseif( is_null( $value ) ) {
	            $out .= 'null';
	        } elseif( is_string( $value ) ) {
	            $out .= "\"" . $_escape( $value ) . "\"";
	        } else {
	            $out .= $value;
	        }

	        $out .= ",\n";
	    }

	    if ( !empty( $out ) ) {
	        $out = substr( $out, 0, -2 );
	    }

	    $out = "{\n" . $out;
	    $out .= "\n" . str_repeat( "\t", $indent ) . "}";

	    return $out;
	}

} ?>