<?php
/**
 * @package info.ajaxplorer.plugins
 * 
 * Copyright 2007-2009 Charles du Jeu
 * This file is part of AjaXplorer.
 * The latest code can be found at http://www.ajaxplorer.info/
 * 
 * This program is published under the LGPL Gnu Lesser General Public License.
 * You should have received a copy of the license along with AjaXplorer.
 * 
 * The main conditions are as follow : 
 * You must conspicuously and appropriately publish on each copy distributed 
 * an appropriate copyright notice and disclaimer of warranty and keep intact 
 * all the notices that refer to this License and to the absence of any warranty; 
 * and give any other recipients of the Program a copy of the GNU Lesser General 
 * Public License along with the Program. 
 * 
 * If you modify your copy or copies of the library or any portion of it, you may 
 * distribute the resulting library provided you do so under the GNU Lesser 
 * General Public License. However, programs that link to the library may be 
 * licensed under terms of your choice, so long as the library itself can be changed. 
 * Any translation of the GNU Lesser General Public License must be accompanied by the 
 * GNU Lesser General Public License.
 * 
 * If you copy or distribute the program, you must accompany it with the complete 
 * corresponding machine-readable source code or with a written offer, valid for at 
 * least three years, to furnish the complete corresponding machine-readable source code. 
 * 
 * Any of the above conditions can be waived if you get permission from the copyright holder.
 * AjaXplorer is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; 
 * without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
 * 
 * Description : Access an SSH server via ftp.
 */
defined('AJXP_EXEC') or die( 'Access not allowed');

require_once(INSTALL_PATH."/plugins/access.fs/class.fsAccessWrapper.php");

class sftpAccessWrapper extends fsAccessWrapper {		

	static $sftpResource;
	
    /**
     * Initialize the stream from the given path. 
     * Concretely, transform ajxp.webdav:// into webdav://
     *
     * @param string $path
     * @return mixed Real path or -1 if currentListing contains the listing : original path converted to real path
     */
    protected static function initPath($path, $storeOpenContext = false, $skipZip = false){    	
    	$url = parse_url($path);
    	$repoId = $url["host"];
    	$repoObject = ConfService::getRepositoryById($repoId);
    	if(!isSet($repoObject)) throw new Exception("Cannot find repository with id ".$repoId);
		$path = $url["path"];
		// MAKE SURE THERE ARE NO // OR PROBLEMS LIKE THAT...
		$basePath = $repoObject->getOption("PATH");		
		if($basePath[strlen($basePath)-1] == "/"){
			$basePath = substr($basePath, 0, -1);			
		}
		if($basePath[0] != "/"){
			$basePath = "/$basePath";
		}
		$path = AJXP_Utils::securePath($path);
		if($path[0] == "/"){
			$path = substr($path, 1);
		}
		// SHOULD RETURN ssh2.sftp://Resource #23/server/path/folder/path
		return  "ssh2.sftp://".self::getSftpResource($repoObject).$basePath."/".$path;
    }    
    
    /**
     * Get ssh2 connection
     *
     * @param Repository $repoObject
     * @return Resource
     */
    protected static function getSftpResource($repoObject){
    	if(isSet(self::$sftpResource)){
    		return self::$sftpResource;
    	}
    	$callbacks = array('disconnect' => array("sftpAccessWrapper", "disconnectedSftp"));
		$remote_serv = $repoObject->getOption("SERV");
		$remote_port = $repoObject->getOption("PORT");
		$remote_user = $repoObject->getOption("USER");
		$remote_pass = $repoObject->getOption("PASS");
		
		$connection = ssh2_connect($remote_serv, intval($remote_port), array(), $callbacks);
		ssh2_auth_password($connection, $remote_user, $remote_pass);
		self::$sftpResource = ssh2_sftp($connection);
    	return self::$sftpResource;
    }
    
    /**
     * Callback for ssh2_connect in case of disconnexion.
     *
     * @param integer $code
     * @param String $message
     * @param String $language
     */
    protected static function disconnectedSftp($code, $message, $language){
    	throw new Exception($message, $code);
    }
    
    /**
     * Opens the stream
     * Diff with parent class : do not "securePath", as it removes double slash
     *
     * @param String $path Maybe in the form "ajxp.fs://repositoryId/pathToFile" 
     * @param String $mode
     * @param array $options
     * @param array $context
     * @return unknown
     */
    public function stream_open($path, $mode, $options, &$context)
    {
    	try{
	    	$this->realPath = $this->initPath($path);
    	}catch (Exception $e){
    		AJXP_Logger::logAction("error", array("message" => "Error while opening stream $path"));
    		return false;
    	}
    	if($this->realPath == -1){
    		$this->fp = -1;
    		return true;
    	}else{
	        $this->fp = fopen($this->realPath, $mode, $options);
	        return ($this->fp !== false);
    	}		
    }
    
    /**
     * Stats the given path. 
     *
     * @param string $path
     * @param mixed $flags
     * @return array
     */
    public function url_stat($path, $flags){    
    	$realPath = self::initPath($path);
    	$stat = @stat($realPath);
    	return $stat;
    }
    /**
     * Opens a handle to the dir
     * Fix PEAR by being sure it ends up with "/", to avoid 
     * adding the current dir to the children list.
     *
     * @param String $path
     * @param array $options
     * @return resource
     */
	public function dir_opendir ($path , $options ){
		$this->realPath = $this->initPath($path, true);	
		if($this->realPath[strlen($this->realPath)-1] != "/"){
			$this->realPath.="/";
		}
		if(is_string($this->realPath)){			
			$this->dH = @opendir($this->realPath);
		}else if($this->realPath == -1){
			$this->dH = -1;
		}
		return $this->dH !== false;
	}

	
	// DUPBLICATE STATIC FUNCTIONS TO BE SURE 
	// NOT TO MESS WITH self:: CALLS
	/**
	 * Remove a temporary file
	 *
	 * @param String $tmpDir
	 * @param String $tmpFile
	 */
	public static function removeTmpFile($tmpDir, $tmpFile){
		if(is_file($tmpFile)) unlink($tmpFile);
		if(is_dir($tmpDir)) rmdir($tmpDir);
	}

	protected static function closeWrapper(){
		if(self::$crtZip != null) {
			self::$crtZip = null;
			self::$currentListing  = null;
			self::$currentListingKeys = null;
			self::$currentListingIndex = null;
			self::$currentFileKey = null;
		}
	}

	/**
	 * Implementation of AjxpStream
	 *
	 * @param String $path
	 * @return string
	 */
	public static function getRealFSReference($path){
		$contextOpened =false;
		if(self::$crtZip != null){
			$contextOpened = true;
			$crtZip = self::$crtZip;
			self::$crtZip = null;
		}
		$realPath = self::initPath($path);
		if(!$contextOpened) {
			self::closeWrapper();
		}else{
			self::$crtZip = $crtZip;
		}
		return $realPath;
	}

	/**
	 * Override parent function, testing feof() does not seem to work.
	 * We may have performance problems on big files here.
	 *
	 * @param String $path
	 * @param Stream $stream
	 */
	public static function copyFileInStream($path, $stream){
		fwrite($stream, file_get_contents(self::initPath($path)));
	}

	/**
	 * Specific case for chmod : not supported natively by ssh2.sftp protocole
	 * we have to recreate an ssh2 connexion.
	 *
	 * @param string $path
	 * @param long $chmodValue
	 */
	public static function changeMode($path, $chmodValue){		
    	$url = parse_url($path);
    	$repoId = $url["host"];
    	$repoObject = ConfService::getRepositoryById($repoId);
		$remote_serv = $repoObject->getOption("SERV");
		$remote_port = $repoObject->getOption("PORT");
		$remote_user = $repoObject->getOption("USER");
		$remote_pass = $repoObject->getOption("PASS");
		$remote_base_path = $repoObject->getOption("PATH");
		
    	$callbacks = array('disconnect' => array("sftpAccessWrapper", "disconnectedSftp"));
		$connection = ssh2_connect($remote_serv, intval($remote_port), array(), $callbacks);
		ssh2_auth_password($connection, $remote_user, $remote_pass);    	
		ssh2_exec($connection,'chmod '.$chmodValue.' '.$remote_base_path.$path);		
	}
}
?>