<?php 
/**
 * Caching class handles the cache, converts gitlab tars into zip files
 * and renames the project directories if needed.
 *
 * @copyright Copyright (c) 2012, Hannes Diercks
 * @author  Hannes Diercks <xiphe@gmx.de>
 * @version 1.0.0
 * @link    https://github.com/Xiphe/WP-Project-Update-API/
 * @package WP Project Update API
 */
class Cache extends Basics {

	/**
	 * Singleton holder.
	 *
	 * @access public
	 * @var object
	 */
	public static $singleton = true;

	/**
	 * If cache can and should be used.
	 *
	 * @access private
	 * @var bool
	 */
	private static $s_useCache;

	/**
	 * If the file should be written to cache.
	 *
	 * @access private
	 * @var boolean
	 */
	private static $s_writeCache = false;

	/**
	 * Saves all temp directories created in runtime.
	 *
	 * @access private
	 * @var array
	 */
	private $_tempDirs = array();

	/**
	 * The cache directory for the current project.
	 *
	 * @access private
	 * @var string
	 */
	private $_cacheDir;

	/**
	 * Flag for extending the include path only once.
	 *
	 * @access private
	 * @var boolean
	 */
	private $_extendedIncludePath = false;


	/**
	 * The initiation.
	 *
	 * @access public
	 * @return void
	 */
	public function init() {
		self::$s_useCache = ProjectUpdates::$sUseCache;

		if( self::$s_useCache ) {
			if( !$this->_check_commitSha() ) {
				$this->_delete_cache();
			}
		}
	}

	/**
	 * checks if the file is available in cache and returns it or false if not.
	 *
	 * @access public
	 * @param  string $key the file-key
	 * @return mixed       the file contents or false if file is not available.
	 */
	public function get_cachedFile( $key ) {
		if( !self::$s_useCache ) {
			return false;
		} elseif( file_exists( ( $file = $this->_get_cacheDir() . $key . '.tmp' ) ) ) {
			return file_get_contents( $file );
		} else {
			if( self::$s_writeCache === false ) {
				self::$s_writeCache = $key;
			}
			return false;
		}
	}

	/**
	 * Writes the file into the project cache folder if allowed to.
	 *
	 * @access public
	 * @param  string $key      the file-key
	 * @param  string $contents the files content
	 * @return void
	 */
	public function cacheFile( $key, $contents ) {
		if( self::$s_writeCache === true || self::$s_writeCache === $key ) {
			
			if( !is_dir( $this->_get_cacheDir() ) ) {
				$dirs = preg_split('#[/\\\]#', $this->_get_cacheDir() );
				$dirs = array_slice( $dirs, count( $dirs ) - 3 );
				$base = $this->_get_baseCacheDir();
				foreach( $dirs as $dir ) {
					if( $dir === '' ) { continue; }
					if( !is_dir( $base . $dir ) ) {
						mkdir( $base . $dir );
					}
					$base .= $dir . DS;
				}
			}

			file_put_contents( $this->_get_cacheDir() . $key . '.tmp', $contents );

			if( self::$s_writeCache === $key ) {
				self::$s_writeCache = false;
			}
		} 
	}

	/**
	 * Writes the Archive-Data into archive.tmp in Projects cache folder.
	 * Checks if all archives have to be touched for renaming the base folder.
	 * Or if host is gitlab and the archive have to be converted to zip.
	 *
	 * @access public
	 * @param  string &$archiveData the archive data
	 * @return void
	 */
	public function cacheArchive( &$archiveData ) {
		if( $this->get_instance( 'Data' )->get_globalSetting( 'renameFolders' ) === true ) {
			$this->_extract( $archiveData );
			$this->_checkTmpArchiveFolder();
			$this->_compressTempFolder();
			$archiveData = file_get_contents( $this->_getTempDir( 'archives' ) . 'final.zip' );
		} elseif( $this->get_instance( 'Data' )->projectSettings->host === 'gitlab' ) {
			$archiveData = $this->_convertTarToZip( $archiveData );
		}
		$this->cacheFile( 'archive', $archiveData );
	}

	/**
	 * Checks if a sha is stored in db and compares it to the remotes last commit sha.
	 * If they are the same cached files will be used.
	 *
	 * @access private
	 * @return	void
	 */
	private function _check_commitSha() {
		$Data = $this->get_instance( 'Data' );

		$dbShaPath =  sprintf(
			'lastCommitSha|%s|%s',
			strtolower( $this->get_instance( 'Request' )->slug ),
			strtolower( $this->get_instance( 'Request' )->branch )
		);

		$localCommitSha = $Data->get_DB( $dbShaPath );

		$remoteCommitSha = $this->get_instance( 'ProjectUpdates' )->Api->get_currentCommitSha();

		if( $remoteCommitSha === false ) {
			self::$s_useCache = false;
			return false;
		} elseif( $localCommitSha !== $remoteCommitSha ) {
			$Data->set_DB( $dbShaPath, $remoteCommitSha )->save_DB();
			self::$s_writeCache = true;
			self::$s_useCache = false;
			return false;
		} else {
			return true;
		}
	}

	/**
	 * Returns the projects cache directory and generates it if needed.
	 *
	 * @access private
	 * @return string the projects cache directory
	 */
	private function _get_cacheDir() {
		if( !isset( $this->_cacheDir ) ) {
			$this->_cacheDir = $this->_get_baseCacheDir()
				. preg_replace( '/[^a-z0-9-_]/', '', strtolower( $this->get_instance( 'Request' )->slug ) ) . DS
				. preg_replace( '/[^a-z0-9-_]/', '', strtolower( $this->get_instance( 'Request' )->branch ) ) . DS;
		}
		return $this->_cacheDir;
	}

	/**
	 * Returns the base cache directory.
	 *
	 * @access private
	 * @return string the base directory.
	 */
	private function _get_baseCacheDir() {
		return dirname( dirname( __FILE__ ) ) . DS . 'cache' . DS;
	}

	/**
	 * Tries to delete the projects cache.
	 *
	 * @access private
	 * @return void
	 */
	private function _delete_cache() {
		if( is_dir( $this->_get_cacheDir() ) ) {
			$this->_rrmdir( $this->_get_cacheDir() );
		}
	}

	/**
	 * Recursive directory deletion.
	 * by http://www.php.net/manual/de/function.rmdir.php#108113
	 *
	 * @access private
	 * @param  string $dir the directory to be deleted
	 * @return void
	 */
	private function _rrmdir( $dir ) {
		foreach( glob( $dir . '/*' ) as $file ) {
			if( is_dir( $file ) )
				$this->_rrmdir( $file );
			else
				unlink( $file );
		}
		rmdir( $dir );
	}

	/**
	 * Extracts the passed archive data into a temp directory.
	 *
	 * @access private
	 * @param  string $archiveData the archive data
	 * @return void
	 */
	private function _extract( $archiveData ) {
		$this->_extendIncludePath();

		@include_once( 'File/Archive.php' );
		if( !class_exists( 'File_Archive' ) ) {
			$this->_exit( 'error', 'archive could not be converted. please install PEAR:File_Archive.', 26 );
			return false;
		}
		$suffix = '.zip';
		if( $this->get_instance( 'Data' )->projectSettings->host === 'gitlab' ) {
			$suffix = '.tgz';
		}

		$tmpName = $this->_getTempDir( 'archives' ) . 'tmp' . $suffix;
		$tmpArchive = $tmpName . DS;
		$folder = $this->_getTempDir( 'remote' );

		file_put_contents( $tmpName, $archiveData );

		$File_Archive = new File_Archive;
		@$File_Archive->extract( $tmpArchive, $folder );

		unlink( $tmpName );
	}

	/**
	 * Renames the unpacked folder into slug or a folder name given by project settings.
	 *
	 * @access private
	 * @return void
	 */
	private function _checkTmpArchiveFolder() {
		$final = $this->_getTempDir( 'final' );

		if( count( ( $dirs = glob( $this->_getTempDir( 'remote' ) . '*' ) ) ) === 1
		 && is_dir( $dirs[0] )
		) {
			/*
			 * The archive already contained a sub-folder - just rename it.
			 */
			rename( $dirs[0], $final . DS . $this->get_instance( 'Data' )->get_folderName() );
		} else {
			/*
			 * The archive had all its files in archive root -> move them into a folder.
			 */
			mkdir( ( $target = $final . DS . $this->get_instance( 'Data' )->get_folderName() ) );
			foreach( glob( $this->_getTempDir( 'remote' ) . '*' ) as $file ) {
				if( $file === $this->_getTempDir( 'remote' ) . 'pax_global_header' ) {
					continue;
				}
				rename( $file, $target . DS . basename( $file ) );
			}
		}
	}

	/**
	 * compresses the contents of the final temp folder.
	 *
	 * @access private
	 * @return void
	 */
	private function _compressTempFolder() {
		$File_Archive = new File_Archive;
		$folder = $this->_getTempDir( 'archives' );
		@$File_Archive->extract(
			glob( $this->_getTempDir( 'final' ) ),
			@File_Archive::toArchive( 'final.zip', $folder )
		);
	}

	/**
	 * Returns the path to a temporary directory and generates it if needed.
	 *
	 * @access private
	 * @return string temp directory path
	 */
	private function _getTempDir( $key ) {
		if( !isset( $this->_tempDirs[$key] ) ) {
			$this->_tempDirs[$key] = $this->_get_uniqueTempFolderName();
			mkdir( $this->_tempDirs[$key] );
		}
		return $this->_tempDirs[$key] . DS;
	}

	private function _get_rootTempDir() {
		return dirname( dirname( __FILE__ ) ) . DS . 'temp' . DS;
	}

	/**
	 * Build a unique folder name string for temp folders.
	 *
	 * Random String generation by
	 *   http://www.noobis.de/developer/141-php-random-string-erzeugen.html
	 *
	 * @access private
	 * @return string a temp folder name
	 */
	private function _get_uniqueTempFolderName() {
		$name = 't' . str_replace( '.', '', microtime( true ) ) . '_';

		$chars = "abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890";
		srand( ( double ) microtime() * 1000000 );
		
		$i = 0;
		while( $i < 5 ) {
			$num = rand() % strlen( $chars );
			$tmp = substr( $chars, $num, 1 );
			$name = $name . $tmp;
			$i++;
		}

		if( !is_dir( ( $dir = dirname( dirname( __FILE__ ) ) . DS . 'temp' . DS . $name ) ) ) {
			return $dir;
		} else {
			return $this->_get_uniqueTempFolderName();
		}
	}

	/**
	 * Converts the archive data from .tar.gz to .zip using PEAR:File_Archive.
	 *
	 * @access private
	 * @param  string $data the .tar.gz data
	 * @return string       the .zip data
	 */
	private function _convertTarToZip( $archiveData ) {
		$this->_extendIncludePath();
		
		@include_once( 'File/Archive.php' );
		if( !class_exists( 'File_Archive' ) ) {
			$this->_exit( 'error', 'archive could not be converted. please install PEAR:File_Archive.', 26 );
			return false;
		}

		file_put_contents( ( $tar = $this->_getTempDir( 'tarconvert' ) . 'tmp.tgz' ), $archiveData );

		$File_Archive = new File_Archive;
		$ttar = $this->_getTempDir( 'tarconvert' ) . 'tmp.tgz' . DS;
		$folder = $this->_getTempDir( 'tarconvert' );

		@$File_Archive->extract( $ttar, @File_Archive::toArchive( 'tmp.zip', $folder ) );

		$data = file_get_contents( $this->_getTempDir( 'tarconvert' ) . 'tmp.zip' );

		return $data;
	}

	/**
	 * Looks if PEAR::FILE_ARCHIVE is available in the projects include folder
	 * and adds the projects include folder to phps include paths.
	 *
	 * @access private
	 * @return void
	 */
	private function _extendIncludePath() {
		if( !$this->_extendedIncludePath && is_dir( dirname( __FILE__ ) . DS . 'File' ) ) {
			set_include_path( dirname( __FILE__ ) . ':' . get_include_path() );
		}
		$this->_extendedIncludePath = true;
	}

	/**
	 * On shutdown: Check if temp-directory have been generated and delete them.
	 *
	 * @access public
	 * @return void.
	 */
	public function __destruct() {
		if( count( $this->_tempDirs ) > 0 ) {
			foreach( $this->_tempDirs as $dir ) {
				$this->_rrmdir( $dir );
			}
		}
	}
} ?>