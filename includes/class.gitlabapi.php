<?php
/**
 * Extension of the basic api for gitlab
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class GitlabApi extends Api {

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

		if( !isset( $token ) ) {
			$this->_exit( 'undefined', 'missing token for gitlab user.', 24 );
		}
		$url = str_replace( ':token', $token, $this->get_url_( 'commits' ) );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$r = @json_decode( curl_exec( $curl ) );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if( $status !== 200 ) {
			$this->_checkResponseStatus( $status );
			$this->_exit( 'error', 'Unable to connect to gitlab - login error or project or branch does not exitst.' , 29 );
		}

		$requestedBranch = $this->get_instance( 'Request' )->branch;

		if( is_array( $r ) ) {
			foreach( $r as $branch ) {
				if( $branch->name === $requestedBranch ) {
					$sha = $branch->commit->id;
				}
			}
		}

		if( !isset( $sha ) ) {
			$this->_exit( 'error', 'Project or Branch not found.', 25 );
		}

		return $sha;
	}

	/**
	 * Overwrites the Api::realy_get_file_() method.
	 * Tryes to login to gitlab and adds cookies to the request.
	 *
	 * @access protected
	 * @param  string $fileUrl the fileUrl
	 * @return string          the file content
	 */
	protected function realy_get_file_( $fileUrl ) {
		$this->_connect_upGitlab();

		extract( $this->get_userData_() );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $fileUrl );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_COOKIEFILE, $this->_get_cookieFileName() );
		curl_setopt( $curl, CURLOPT_COOKIEJAR, $this->_get_cookieFileName() );
		$r = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if( $status !== 200 ) {
			$this->_checkResponseStatus( $status );
			$this->_exit( 'error', 'Unable to connect to gitlab - login error or project or branch does not exitst.' , 14 );
		}

		return $r;
	}

	/**
	 * Overwrites the Api::get_archive_() method.
	 * Tryes to login to gitlab and adds cookies to the request.
	 *
	 * @access protected
	 * @return string the archive content
	 */
	protected function get_archive_() {
		$this->_connect_upGitlab();

		extract( $this->get_userData_() );

		$url = $this->get_url_( 'archive' );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_COOKIEFILE, $this->_get_cookieFileName() );
		curl_setopt( $curl, CURLOPT_COOKIEJAR, $this->_get_cookieFileName() );
		$e = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		
		if( $status !== 200 ) {
			$this->_checkResponseStatus( $status );
			$this->_exit( 'error', 'Unable to connect to gitlab - login error or project or branch does not exitst.' , 20 );
		}

		return $e;
	}

	/**
	 * Gets the unique cookie file path for the current host.
	 *
	 * @access private
	 * @return string path to the cookiefile
	 */
	private function _get_cookieFileName() {
		return dirname( dirname( __FILE__ ) ) . DS . 'cookies' . DS . $this->_get_ID() . '.cookie';
	}

	/**
	 * Generates a unique string for the current host.
	 *
	 * @access private
	 * @return string the hostID
	 */
	private function _get_ID() {
		return preg_replace( '/[^a-z0-9-_]/', '', strtolower( $this->get_instance( 'Data' )->projectSettings->globalkey ) );
	}

	/**
	 * checks if the response status is a rederection and resets the gitlab login.
	 * 
	 * @param  int  $status the response status
	 * @return void
	 */
	private function _checkResponseStatus( $status ) {
		if( $status === 302 ) {
			if( file_exists( $this->_get_cookieFileName() ) ) {
				unlink( $this->_get_cookieFileName() );
			}
			$this->get_instance( 'Data' )
				->set_DB( 'lastGitlabConnections|' . $this->_get_ID(), '0' )
				->save_DB();
		}
	}

	/**
	 * Tryes to connect to gitlab by programaticaly login to the normal frontend.
	 *
	 * @access private
	 * @param  integer $i counter for the login attempts
	 * @return void
	 */
	private function _connect_upGitlab( $i = 1 ) {
		/*
		 * Check if there was a sucsessful login within the last 24 hours
		 * and just log in again if not.
		 */
		$lastConnection = $this->get_instance( 'Data' )->get_DB( 'lastGitlabConnections|' . $this->_get_ID() );
		if( $lastConnection === null
		 || $lastConnection < ( time() - ( 24 * 60 * 60 ) )
		) {
			$sucess = true;

			/*
			 * Get Userdata and delete CookieFile if existing. 
			 */
			extract( $this->get_userData_() );
			if( file_exists( $this->_get_cookieFileName() ) ) {
				unlink( $this->_get_cookieFileName() );
			}
			$baseUrl = $this->get_url_( 'files' );
			$u = parse_url( $baseUrl );
			$baseUrl = ( isset( $u['scheme'] ) ? $u['scheme'] : 'http' )
				. '://' . $u['host'] 
				. ( isset( $u['port'] ) ? ':' . $u['port'] : '' )
				. '/';
			unset( $u );

			/*
			 * First request for getting the authenticity_token and cookies needed for the login.
			 */
			$curl = curl_init( $baseUrl . 'users/sign_in' );
			curl_setopt( $curl, CURLOPT_HEADER, 1 );
			curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1);
			curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
			curl_setopt( $curl, CURLOPT_COOKIEJAR, $this->_get_cookieFileName() );
			$c = curl_exec( $curl );
			$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
			curl_close( $curl );

			if( $status !== 200 ) {
				$sucess = false;
			}

			if( $sucess ) {
				sleep( 1 );

				/*
				 * Parse the token from result.
				 */
				preg_match( '/<input name="authenticity_token" type="hidden" value="(.*)"/', $c, $token );
				$token = $token[1];

				/*
				 * Build the login data array.
				 */
				$fields = array(
					'user[email]' => $user,
					'user[password]' => $pass,
					'user[remember_me]' => 0,
					'commit' => 'Sign in',
					'authenticity_token' => $token,
					'utf8' => '✓'
				);

				/*
				 * Parse the data.
				 */
				$fields_string = '';
				foreach( $fields as $key=>$value ) { $fields_string .= $key.'='.$value.'&'; }
				$fields_string = substr( $fields_string, 0, strlen( $fields_string ) -1 );

				/*
				 * Second request - the actual login attempt.
				 */
				$curl = curl_init();
				curl_setopt( $curl, CURLOPT_URL, $baseUrl . 'users/sign_in' );
				curl_setopt( $curl, CURLOPT_POST, count( $fields ) );
				curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $curl, CURLOPT_POSTFIELDS,$fields_string );
				curl_setopt( $curl, CURLOPT_COOKIEFILE, $this->_get_cookieFileName() );
				curl_setopt( $curl, CURLOPT_COOKIEJAR, $this->_get_cookieFileName() );
				$result = curl_exec( $curl );
				$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
				curl_close( $curl );

				if( $status !== 302 ) {
					$sucess = false;
				}
			}

			sleep( 1 );

			if( $sucess ) {
				/*
				 * Third request for checking if the login was sucsessfull.
				 */
				$curl = curl_init();
				curl_setopt( $curl, CURLOPT_URL, $baseUrl );
				curl_setopt( $curl, CURLOPT_RETURNTRANSFER, 1 );
				curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
				curl_setopt( $curl, CURLOPT_COOKIEFILE, $this->_get_cookieFileName() );
				curl_setopt( $curl, CURLOPT_COOKIEJAR, $this->_get_cookieFileName() );
				curl_exec( $curl );
				$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
				curl_close( $curl );
			}

			if( ( $sucess === false || $status !== 200 ) && $i <= 3 ) {
				/*
				 * If the login was not sucessfull try another 2 times,
				 */
				sleep( $i );
				$i++;
				return $this->_connect_upGitlab( $i );
			} elseif( $i > 3 ) {
				/*
				 * Not able to login after three attempts.
				 */
				$this->_exit( 'error', 'Could not login to gitlab.' , 19 );
			} else {
				/*
				 * Login was sucsessfull - save the current time into the database to prevent
				 * other logins within the next 24 houres.
				 */
				$this->get_instance( 'Data' )
					->set_DB( 'lastGitlabConnections|' . $this->_get_ID(), time() )
					->save_DB();
				return;
			}
		}
	}
} ?>