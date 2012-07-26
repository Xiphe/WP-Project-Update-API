<?php
/**
 * Extension of the basic api for bitbucket
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class BitbucketApi extends Api {

	/**
	 * Singleton holder.
	 *
	 * @access public
	 * @var object
	 */
	public static $singleton = true;

	/**
	 * Gets the latest commits sha from the current branch at current host.
	 *
	 * @access public
	 * @return mixed the shar string or false if error.
	 */
	public function get_currentCommitSha() {
		extract( $this->get_userData_() );

		$url = $this->get_url_( 'commits' );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_USERPWD, $user . ':' . $pass );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$r = @json_decode( curl_exec( $curl ) );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if( $status !== 200 || !is_object( $r ) ) {
			$this->_exit( 'error', 'Unable to connect to bitbucket - login error or project or branch not found.', 28 );
		}

		return $r->raw_node;
	}

	/**
	 * Overwrites the Api::realy_get_file_() method.
	 * This adds userdata into the request and checkes if they are valid.
	 *
	 * @access protected
	 * @param  string $fileUrl the files url
	 * @return string          the file content
	 */
	protected function realy_get_file_( $fileUrl ) {
		extract( $this->get_userData_() );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $fileUrl );
		curl_setopt( $curl, CURLOPT_USERPWD, $user . ':' . $pass );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$r = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if(  $status !== 200 ) {
			$this->_exit( 'unknown', 'Unable to connect to bitbucket - login error or project or branch not found.' , 14 );
		}

		return $r;
	}

	/**
	 * Overwrites the Api::get_archive_() method.
	 * This adds userdata into the request and checkes if they are valid.
	 *
	 * @access protected
	 * @return string the archive content
	 */
	protected function get_archive_() {
		extract( $this->get_userData_() );

		$curl = curl_init();

		curl_setopt( $curl, CURLOPT_URL, $this->get_url_( 'archive' ) );
		curl_setopt( $curl, CURLOPT_USERPWD, $user . ':' . $pass );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$e = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if(  $status !== 200 ) {
			$this->_exit( 'unknown', 'Unable to connect to bitbucket - login error or project or branch not found.' , 19 );
		}

		return $e;
	}
} ?>