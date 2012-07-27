<?php
/**
 * Basic class to be extended.
 *
 * Provides singleton logic, the result/exit/error mechanics
 * and some handy methods.
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class Basics {

	/**
	 * The response which is serialized and printed on Basics::exit().
	 *
	 * @access private
	 * @var array
	 */
	private static $s_r = array(
		'status' => 'error',
		'msg' => 'nothing happened.',
		'errorCode' => -1
	);

	/**
	 * Setter for the response array.
	 *
	 * @access public
	 * @param string $key
	 * @param mixed $value
	 * @return void.
	 */
	final public function set_r( $key, $value ) {
		self::$s_r[$key] = $value;
	}

	/**
	 * The construction
	 *
	 * @access public
	 * @return object
	 */
	final public function __construct() {
	}

	/**
	 * Fall-back for classes that does not need the init function
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		return null;
	}

	/**
	 * Killer function stops the script and echoes the serialized response
	 *
	 * @access public
	 * @param  string $status    short string describing the status (error|ok|...)
	 * @param  string $msg       longer description of the status, error msg etc.
	 * @param  int    $errorCode unique number for the error.
	 * @return void
	 */
	final public function _exit( $status = null, $msg = null, $errorCode = null ) {
		$slug = $this->get_instance( 'Request' )->slug;
		foreach( array( 'status', 'msg', 'errorCode', 'slug' ) as $k ) {
			if( $$k !== null ) {
				self::$s_r[$k] = $$k;
			}
		}
		
		echo serialize( self::$s_r );
		exit;
	}

	/**
	 * Handy function to dive into an array or object without knowing what it is
	 * nested keys can be separated by a pipe so it's possible to get a deeper
	 * key with only one call
	 *
	 * @access public
	 * @param  mixed $obj   array or object
	 * @param  string $path the key or key-path (foo|bar)
	 * @return mixed        the keys value or null if not found
	 */
	final public function recursive_get( $obj, $path ) {	
		foreach( explode( '|', $path ) as $key ) {
			if( is_object( $obj ) ) {
				if( isset( $obj->$key ) )
					$obj = $obj->$key;
				else
					return null;
			}
			elseif( isset( $obj[$key] ) )
				$obj = $obj[$key];
			else {
				return null;
			}
		}
		return $obj;
	}

	/**
	 * Shorthand for Basics::recursive_get()
	 *
	 * @access public
	 * @param  mixed $obj   array or object
	 * @param  string $path the key or key-path (foo|bar)
	 * @return mixed        the keys value or null if not found
	 */
	final public function rget( $obj, $path ) {
		return $this->recursive_get( $obj, $path );
	}

	/**
	 * Handy function to insert deeper data into an array or object.
	 * Functionality is similar to recursive_get.
	 * keys will be generated if they do not exist and $format == 'stdClass' || 'array'
	 *
	 * @access public
	 * @param  mixed  &$obj   the array or object in which the data should be inserted
	 * @param  string $path   the key or key-path (foo|bar|some|thing)
	 * @param  mixed  $value  the variable to set to the end of the path
	 * @param  string $type   the type of the new generated sub-keys set to something other
	 *                        than array or stdClass to prevent generation
	 * @return mixed          the object with new data
	 */
	final public function recursive_set( &$obj, $path, $value, $type = 'stdClass' ) {
		foreach( explode( '|', $path ) as $key ) {
			if( is_object( $obj ) ) {
				if( !isset( $obj->$key ) ) {
					if( $type = 'stdClass' ) {
						$obj->$key = new stdClass;
					} elseif( $type = 'array' ) {
						$obj->$key = array();;
					} else {
						return false;
					}
				}
				$obj = &$obj->$key;
			} else {
				if( !isset( $obj[$key] ) ) {
					if( $type = 'stdClass' ) {
						$obj[$key] = new stdClass;
					} elseif( $type = 'array' ) {
						$obj[$key] = array();;
					} else {
						return false;
					}
				}
				$obj = &$obj[$key];
			}	
			
		}
		return $obj = $value;
	}

	/**
	 * Shorthand for Basics::recursive_set().
	 *
	 * @access public
	 * @param  mixed  &$obj   the array or object in which the data should be inserted
	 * @param  string $path   the key or key-path (foo|bar|some|thing)
	 * @param  mixed  $value  the variable to set to the end of the path
	 * @param  string $type   the type of the new generated sub-keys set to something other
	 *                        than array or stdClass to prevent generation
	 * @return mixed          the object with new data
	 */
	final public function rset( &$obj, $path, $value, $type = 'stdClass' ) {
		$this->recursive_set( $obj, $path, $value, $type );
	}

	/**
	 * getter for other instances.
	 *
	 * @access public
	 * @param  string $name the class name
	 * @return object       new instance of class
	 */
	final public static function get_instance( $name ) {
		$name = str_replace( '-', '', $name );

		if( !class_exists( $name ) ) {
			$file = 'class.' . preg_replace( '/[^A-Za-z0-9_]/', '', strtolower( $name ) ) . '.php';
			include_once( dirname( __FILE__ ) . DS . $file );
		}

		if( isset( $name::$singleton ) && is_object( $name::$singleton ) ) {
			return $name::$singleton;
		} else {
			$obj = new $name;
			if( isset( $name::$singleton ) ) {
				$name::$singleton = $obj;
			}
			$obj->init();
			return $obj;
		}
	}

	/**
	 * remove potential slash from the end of the string.
	 *
	 * @access public
	 * @param  string $str the string to be unslashed
	 * @param  string $slash the slash can be set to backslash or whatever here.
	 * @return string      the unslashed string
	 */
	final public function unslash( $str, $slash = '/' ) {
		if( substr( $str, strlen( $str ) - 1, strlen( $str ) ) === $slash ) {
			return substr( $str, 0, strlen( $str ) - 1 );
		}
		return $str;
	}

	/**
	 * add slash to the end of the string.
	 *
	 * @access public
	 * @param  string $str   the string to be slashed
	 * @param  string $slash the slash can be set to backslash or whatever here.
	 * @return string        the slashed string
	 */
	final public function slash( $str, $slash = '/' ) {
		if( substr( $str, strlen( $str ) - 1, strlen( $str ) ) !== $slash ) {
			return $str . $slash;
		}
		return $str;
	}

} ?>