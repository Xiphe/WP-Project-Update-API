<?php
/**
 * Main and fall-back API class handles the requests to remote hosts
 * and generates the results.
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.1
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class Api extends Basics {

	/**
	 * Singleton holder.
	 *
	 * @access public
	 * @var object
	 */
	public static $singleton = true;

	/**
	 * Holds the project information parsed from remote info file.
	 *
	 * @access private
	 * @var array
	 */
	private $_info;

	/**
	 * Holds the bare info and readme files.
	 *
	 * @access protected
	 * @var object
	 */
	protected $files_;


	/**
	 * The initiation
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		$this->files_ = new stdClass;
	}

	/**
	 * Checks if the action is the Api::$s_allowedActions array
	 * and calls the action method.
	 *
	 * @access public
	 * @return void
	 */
	final public function do_action() {
		call_user_func( array( $this, 'do_' .  $this->get_instance( 'Request' )->action ) );
	}

	/**
	 * Generates the output for the basic_check action.
	 *
	 * @access public
	 * @return void
	 */
	public function do_basic_check() {
		/*
		 * Check the version passed in request against the remote version.
		 */
		if( version_compare(
				$this->get_instance( 'Request' )->version,
				$this->get_info( 'version' ),
				'<'
			)
		) {
			$projectSettings = $this->get_instance( 'Data' )->projectSettings;

			/*
			 * New version exists. Check if the project is a theme (info-file = *.css) or a plugin (info-file = *.php).
			 * And build the response.
			 */
			if( $this->get_instance( 'Request' )->type === 'theme' 
			 || ( isset( $projectSettings->info )
			   && pathinfo( $projectSettings->info, PATHINFO_EXTENSION ) === 'css'
			 	)
			) {
				$r = array(
					'new_version' => $this->get_info( 'version' ),
					'url' => $this->get_detailUrl_(),
					'package' => $this->get_packageUrl_()
				);
			} else {
				$r = new stdClass;
				$r->slug = $this->get_instance( 'Request' )->slug;
				$r->new_version = $this->get_info( 'version' );
				$r->url = $this->get_info( 'pluginuri' );
				$r->package = $this->get_packageUrl_();
			}


			print serialize( $r );
			exit;
		} else {
			$this->_exit( 'ok', 'version up to date.' , 0 );
		}		
	}

	/**
	 * Generate the output for the plugin_information action.
	 *
	 * @access public
	 * @return void
	 */
	public function do_plugin_information() {
		$r = new stdClass;
		$r->slug = $this->get_instance( 'Request' )->slug;
		$r->downloaded = $this->get_Downloads_();
		$r->download_link = $this->get_packageUrl_();

		$this->build_BasicResponse_( $r );

		if( isset( $r->authoruri ) ) {
			$r->author = '<a href="' . $r->authoruri . '">' . $r->author . '</a>';
		}

		$r->sections = $this->get_Sections_();

		print serialize( $r );
		exit;
	}

	/**
	 * Do the download_latest action by getting the archive string, setting the headers
	 * and counting up the downloads.
	 *
	 * @access public
	 * @return void
	 */
	final public function do_download_latest() {
		$file = $this->pre_get_projectArchive_();
		if( $file !== false ) {
			header( "Pragma: public" );
			header( "Expires: 0" ); 
			header( "Cache-Control: must-revalidate, post-check=0, pre-check=0" );
			header( "Cache-Control: private", false );
			header( "Content-Type: application/zip" );
			header( "Content-Disposition: attachment; filename=\"" . $this->get_instance( 'Request' )->branch . ".zip\";" );
			header( "Content-Transfer-Encoding: binary" ); 

			header( "Content-Length: " . strlen( $file ) );

			$this->set_Downloads_();
			echo $file;
			exit;
		} else {
			$this->_exit( 'error', 'unable to download file.' , 21 );
		}
	}

	/**
	 * Do the project_details action by parsing the readme file and put some html around it.
	 *
	 * @access public
	 * @return void
	 */
	public function do_project_details() {
		$rm = $this->get_file_( 'readme' );

		$projectSettings = $this->get_instance( 'Data' )->projectSettings;

		if( !isset( $projectSettings->readme )
		 || pathinfo( 
				$projectSettings->readme,
				PATHINFO_EXTENSION
			) === 'md'
		) {
			require_once('markdown.php');
			$rm = Markdown( $rm );
		} else {
			$rm = nl2br( $rm );
		}

		include( 'res/templates/project_details_head.html' );
		echo '<header>' . sprintf( 'Project information for Version <strong>%s</strong>.', $this->get_info('version') ) . '</header>';
		echo '<div role="main">' . $rm . '</div>';
		include( 'res/templates/project_details_footer.html' );
		exit;
	}

	/**
	 * Getter for information parsed from the projects remote info file.
	 *
	 * @access public
	 * @param  string $key the key of the requested value
	 * @return string      the requested value
	 */
	public function get_info( $key ) {
		/*
		 * Check if the info-file was already parsed.
		 */
		if( !isset( $this->_info ) ) {
			$file = $this->get_file_( 'info' );
			if( !is_array( $file ) ) {
				$file = preg_split( '/\n/', $file );
			}
			$this->_info = array();
			foreach( $file as $l ) {
				if( count( ( $p = explode(':', $l, 2 ) ) ) > 1 ) {
					$this->_info[ preg_replace('/[^a-z0-9]/', '', strtolower( $p[0] )) ] = trim( $p [1] );
				}
				if( trim($l) == '*/' )
					break;
			}
		}

		/*
		 * look if the key exists and return it or null.
		 */
		if( isset( $this->_info[ $key ] ) ) {
			return $this->_info[ $key ];
		} else {
			return null;
		}
	}

	/**
	 * Gets the latest commits sha from the current branch at current host.
	 *
	 * @access public
	 * @return mixed the sha string or false if error.
	 */
	public function get_currentCommitSha() {
		$url = $this->get_url_( 'commits' );

		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $url );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$r = @json_decode( curl_exec( $curl ) );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if( $status !== 200 || !is_array( $r ) || empty( $r ) ) {
			$this->_exit( 'error', 'Unable to connect to host - login error or project or branch not found.', 27 );
		}

		return $r[0]->sha;
	}

	/**
	 * Tries to get the project archive from cache or calls get_archive()
	 * and tries to cache the result.
	 *
	 * @access protected
	 * @return string the archive string
	 */
	final protected function pre_get_projectArchive_() {
		if( ( $archive = $this->get_instance( 'Cache' )->get_cachedFile( 'archive' ) ) !== false ) {
			return $archive;
		} else {
			$archive = $this->get_archive_();
			if( $archive !== false ) {
				$this->get_instance( 'Cache' )->cacheArchive( $archive );
			}
			
			return $archive;
		}
	}

	/**
	 * Default method to get the project archive.
	 *
	 * @access protected
	 * @return string the contents of the archive.
	 */
	protected function get_archive_() {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $this->get_url_( 'archive' ) );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$r = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if( $status !== 200 ) {
			$this->_exit( 'error', 'Unable to connect to host - login error or project or branch not found.', 31 );
		}
		return $r;
	}

	/**
	 * Add more infos to the response of plugin_information.
	 *
	 * @access protected
	 * @param  object &$r the response object.
	 * @return void
	 */
	protected function build_BasicResponse_( &$r ) {
		foreach( array(
			'pluginname',
			'themename',
			'version',
			'date',
			'author',
			'requires',
			'tested',
			'authoruri',
			'external',
			'pluginuri'
		) as $k ) {
			if( ( $v = $this->get_info( $k ) ) !== null ) {
				if( $k === 'themename' || $k === 'pluginname' ) {
					$r->name = $v;
				} elseif( $k == 'pluginuri' ) {
					$r->homepage = $v;
				} elseif( $k == 'date' ) {
					$r->last_updated = $v;
				} else {
					$r->$k = $v;
				}
			} else {
				if( $k !== 'themename' && $k !== 'pluginname' ) {
					$r->$k = '';
				}
			}
		}
	}

	/**
	 * Extracts sections from the readme file by cutting it at its headlines and appending
	 * markdown to each section.
	 * If the readme file is not a markdown file all of its content will be returned for the
	 * "description" section.
	 *
	 * @access protected
	 * @return array the sections
	 */
	protected function get_Sections_() {
		$rm = $this->get_file_( 'readme' );

		if( !isset( $projectSettings->readme )
		 || pathinfo( 
				$projectSettings->readme,
				PATHINFO_EXTENSION
			) === 'md'
		) {
			require_once( dirname( __FILE__ ) . DS . 'markdown.php');

			$equalize = function( $str, $space = '' ) {
				return trim( strtolower( str_replace( ' ', $space, $str ) ) );
			};

			$r = array();
			$c = preg_split(
				'/([\w-_ ]+)[\n|\r|\n\r]{1,2}[$^=|-]{2,}[\n|\r|\n\r]{2,}/',
				$rm,
				-1,
				PREG_SPLIT_NO_EMPTY | PREG_SPLIT_DELIM_CAPTURE
			);
			
			$i = 0;
			while( isset( $c[$i] ) ) {
				if( $i === 0 && $equalize( $c[0] ) == $equalize( $this->get_info( 'pluginname' ) ) ) {
					$i++;
					$r['description'] = Markdown( trim( $c[$i++] ) );
				} else {
					$r[ $equalize( $c[$i++], '_' ) ] = Markdown( trim( $c[$i++] ) );
				}
			}

			return $r;
		} else {
			return array(
				'description' => $rm
			);
		}
	}

	/**
	 * Getter for current download count of the current project.
	 *
	 * @access protected
	 * @return int download-count.
	 */
	final protected function get_Downloads_() {
		$dl = $this->get_instance( 'Data' )
			->get_DB( 'downloads|' . strtolower(  $this->get_instance( 'Request' )->slug ) );
		if( !is_int( $dl ) ) {
			$dl = 0;
		}
		return $dl;
	}

	/**
	 * Sets the download-count +1 and saves it into the database.
	 *
	 * @access protected
	 * @return void.
	 */
	final protected function set_Downloads_() {
		$dl = $this->get_Downloads_();
		$dl++;
		$this->get_instance( 'Data' )
			->set_DB( 'downloads|' . strtolower( $this->get_instance( 'Request' )->slug ), $dl )
			->save_DB();
	}

	/**
	 * Checks if the file is allowed and not already requested.
	 *
	 * @access protected
	 * @param  string $key the file-key
	 * @return bool        true if the file is allowed but need to be requested from remote
	 *                     false if the file is already available.
	 */
	final protected function get_file_( $key ) {
		if( !in_array( $key, array( 'info', 'readme' ) ) ) {
			$this->_exit( 'error', 'not allowed to get file', 16 );
		} elseif( isset( $this->files_->$key ) ) {
			return $this->files_->$key;
		} elseif( ( $file = $this->get_instance( 'Cache' )->get_cachedFile( $key ) ) !== false ) {
			$this->files_->$key = $file;
			return $file;
		} else {
			$fileUrl = $this->_getFileUrlFor( $key );

			$this->files_->$key = $this->realy_get_file_( $fileUrl );
			$this->get_instance( 'Cache' )->cacheFile( $key, $this->files_->$key );
			return $this->files_->$key;
		}
	}

	/**
	 * Default method to get the requested file from remote.
	 *
	 * @access protected
	 * @param  string $fileUrl URL to the file
	 * @return string          the contents of the file.
	 */
	protected function realy_get_file_( $fileUrl ) {
		$curl = curl_init();
		curl_setopt( $curl, CURLOPT_URL, $fileUrl );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, true );
		curl_setopt( $curl, CURLOPT_TIMEOUT, 10 );
		curl_setopt( $curl, CURLOPT_SSL_VERIFYPEER, ( PHP_OS === 'WINNT' ? false : true ) );
		$file = curl_exec( $curl );
		$status = curl_getinfo( $curl, CURLINFO_HTTP_CODE );
		curl_close( $curl );

		if( $status !== 200 ) {
			$this->_exit( 'error', 'Unable to connect to host - login error or project or branch not found.', 30 );
		}
		return $file;
	}

	/**
	 * Builds the download URL.
	 *
	 * @access protected
	 * @return string the download URL
	 */
	final protected function get_packageUrl_() {
		$Request = $this->get_instance( 'Request' );
		return ProjectUpdates::$sBaseUrl . '?action=download_latest&slug='
			. $Request->slug . '&apikey=' . $Request->apikey
			. '&branch=' . $Request->branch;
	}

	/**
	 * Builds the detail-view URL.
	 *
	 * @access protected
	 * @return string the detail-view URL
	 */
	final protected function get_detailUrl_() {
		$Request = $this->get_instance( 'Request' );
		$r = ProjectUpdates::$sBaseUrl . '?action=project_details&slug='
			. $Request->slug . '&apikey=' . $Request->apikey
			. '&branch=' . $Request->branch;
		if( $Request->type === 'theme' ) {
			$r .= '&type=theme';
		}
		return $r;
	}

	/**
	 * Gets the user name and password for the current host.
	 *
	 * @access protected
	 * @return array user name, password and token if available.
	 */
	final protected function get_userData_() {
		$projectSettings = $this->get_instance( 'Data' )->projectSettings;

		$globalKey = isset( $projectSettings->globalkey ) ?
				$projectSettings->globalkey : $projectSettings->host;

		$user = $projectSettings->user;
		$pass = isset( $projectSettings->password )
			  ? $projectSettings->password
			  : $this->get_instance( 'Data' )
			  		 ->get_globalSetting( "users|$globalKey|$user" );

		if( is_object( $pass ) ) {
			$token = $pass->token;
			$pass = $pass->pass;
		}

		if( isset( $projectSettings->token ) ) {
			$token = $projectSettings->token;
		}

		if( !isset( $pass ) ) {
			$this->_exit( 'undefined', 'no pw found' , 14 );
		}

		$r = array( 'user' => $user, 'pass' => $pass );
		if( isset( $token ) ) {
			$r['token'] = $token;
		}

		return $r;
	}

	/**
	 * Gets the requested URL for the current project.
	 *
	 * @access protected
	 * @param  string $key the urlKey
	 * @return string      the URL.
	 */
	final protected function get_url_( $key ) {
		$Data = $this->get_instance( 'Data' );

		$globalKey = isset( $Data->projectSettings->globalkey )
			? $Data->projectSettings->globalkey : $Data->projectSettings->host;

		$url = $Data->get_globalSetting( "urls|$globalKey|$key" );

		$slug = isset( $Data->projectSettings->remoteSlug )
			? $Data->projectSettings->remoteSlug
			: $this->get_instance( 'Request' )->slug;

		$url = str_replace( array(
			':slug',
			':projectOwner',
			':branch'
		), array(
			$slug,
			$Data->projectSettings->projectOwner,
			$this->get_instance( 'Request' )->branch
		), $url );

		return $url;
	}

	/**
	 * Gets the file URL for the passed file-key.
	 *
	 * @access private
	 * @param  string $key the file-key
	 * @return string      the file-URL
	 */
	private function _getFileUrlFor( $key ) {
		$projectSettings = $this->get_instance( 'Data' )->projectSettings;
		$Request = $this->get_instance( 'Request' );

		/*
		 * Check if the key was set in project settings and prefer it to global "guessed" filename.
		 */
		if( isset( $projectSettings->$key ) ) {
			$file = $projectSettings->$key;
		} else {
			if( $key === 'info' ){
				/*
				 * Check if the request might be a theme and set file to style.css if so.
				 */
				if( isset( $Request->type ) && $Request->type === 'theme' ) {
					$file = 'style.css';
				} else {
					$file = $Request->slug . '.php';
				}
			} else {
				$file = 'readme.md';
			}
		}

		$url = str_replace( ':filepath', $file, $this->get_url_( 'files' ) );

		return $url;
	}
} ?>