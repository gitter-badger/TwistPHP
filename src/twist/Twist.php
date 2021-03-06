<?php
	/**
	 * This file is part of TwistPHP.
	 *
	 * TwistPHP is free software: you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation, either version 3 of the License, or
	 * (at your option) any later version.
	 *
	 * TwistPHP is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with TwistPHP.  If not, see <http://www.gnu.org/licenses/>.
	 *
	 * @author     Shadow Technologies Ltd. <contact@shadow-technologies.co.uk>
	 * @license    https://www.gnu.org/licenses/gpl.html LGPL License
	 * @link       https://twistphp.com
	 *
	 */

	use Twist\Classes\Instance;
	use Twist\Classes\Error;
	use Twist\Core\Controllers\Framework;
	use Twist\Core\Packages as Packages;

	/**
	 * Main functionality for the Framework, when using core functionality of the framework this class is that gateway to makes it all possible.
	 */
	class Twist{

		protected static $blLaunched = false;
		protected static $blRecordEvents = false;

		public function __construct(){
			throw new Exception("Twist Framework can only be called statically, please refer to documentation for more details");
		}

		/**
		 * Define PHP Defines but automatically checks to see if has already been defined, if so the new define is ignored but no error is thrown.
		 * @param $strKey
		 * @param $mxdValue
		 */
		public static function define($strKey,$mxdValue){
			if(!defined($strKey)){
				define($strKey,$mxdValue);
			}
		}

		/**
		 * Main function called by the boot.php file, this function will boot the framework setting all the variables and initialising required functionality to ensure that TwistPHP runs as expected.
		 */
		public static function launch(){

			if(self::$blLaunched === false){
				self::$blLaunched = true;

				//Get the base location of the site, based on apaches report fo the document root minus a trailing slash
				self::define('TWIST_DOCUMENT_ROOT',rtrim($_SERVER['DOCUMENT_ROOT'],'/'));

				$blAboveDocumentRoot = false;
				$strInstallationFolder = realpath(sprintf('%s/../',TWIST_FRAMEWORK));

				if(!($strInstallationFolder === TWIST_DOCUMENT_ROOT || strstr($strInstallationFolder,TWIST_DOCUMENT_ROOT))){
					$blAboveDocumentRoot = true;
				}

				$strBaseURI = str_replace('//','','/'.trim(str_replace(TWIST_DOCUMENT_ROOT,"",dirname($_SERVER['SCRIPT_FILENAME'])),'/').'/');

				if(strstr(TWIST_FRAMEWORK,TWIST_DOCUMENT_ROOT)){
					$strFrameworkURI = '/'.ltrim(str_replace(TWIST_DOCUMENT_ROOT,"",TWIST_FRAMEWORK),'/');
				}else{
					$strFrameworkURI = sprintf('%stwist/',$strBaseURI);
				}

				self::define('TWIST_FRAMEWORK_URI',$strFrameworkURI);
				self::define('TWIST_ABOVE_DOCUMENT_ROOT',$blAboveDocumentRoot);
				self::define('TWIST_BASE_PATH',dirname($_SERVER['SCRIPT_FILENAME']));
				self::define('TWIST_BASE_URI',$strBaseURI);

				date_default_timezone_set( !is_null( Twist::framework() -> setting('TIMEZONE') ) ? Twist::framework() -> setting('TIMEZONE') : 'Europe/London' );

				self::$blRecordEvents = (self::framework() -> setting('DEVELOPMENT_MODE') && self::framework() -> setting('DEVELOPMENT_EVENT_RECORDER'));

				//Log the framework boot time, this is the point in which the framework code was required
				Twist::Timer('TwistEventRecorder')->start($_SERVER['TWIST_BOOT']);

				self::define('E_TWIST_NOTICE',E_USER_NOTICE);
				self::define('E_TWIST_WARNING',E_USER_WARNING);
				self::define('E_TWIST_ERROR',E_USER_ERROR);
				self::define('E_TWIST_DEPRECATED',E_USER_DEPRECATED);

				self::define('TWIST_ERROR_LOG',Twist::framework() -> setting('ERROR_LOG'));
				self::define('TWIST_ERROR_SCREEN',Twist::framework() -> setting('ERROR_SCREEN'));

				//Register the PHP handlers
				self::errorHandlers();

				self::recordEvent('Handlers prepared');

				/**
				 * Override the error handlers and exception handlers and turn on AJAX debugging
				 * Note: In the future we could use this to enable the log handler instead
				 */
				self::define('TWIST_AJAX_REQUEST',array_key_exists('HTTP_X_REQUESTED_WITH',$_SERVER) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest');

				//Register all the installed packages
				Twist::framework() -> package() -> getInstalled();

				//Register the default PHP package extensions
				Twist::framework() -> package() -> extend('View','asset',array('module' => 'Asset','function' => 'viewExtension'));
				Twist::framework() -> package() -> extend('View','file',array('module' => 'File','function' => 'viewExtension'));
				Twist::framework() -> package() -> extend('View','image',array('module' => 'Image','function' => 'viewExtension'));
				Twist::framework() -> package() -> extend('View','session',array('module' => 'Session','function' => 'viewExtension'));
				Twist::framework() -> package() -> extend('View','user',array('module' => 'User','function' => 'viewExtension'));

				self::recordEvent('Packages prepared');

				//Initalise the resource handler
				Instance::storeObject('twistCoreResources',new \Twist\Core\Models\Resources());

				//Register the framework resources handler into the template system
				Twist::framework() -> package() -> extend('View','resource',array('instance' => 'twistCoreResources','function' => 'viewExtension'));

				//Register the framework message handler into the template system
				\Twist::framework() -> package() -> extend('View','messages',array('core' => 'messageHandler'));

				self::coreResources();

				self::recordEvent('Resources prepared');

				self::showSetup();
				self::phpSettings();

				self::recordEvent('Framework ready');
				self::define('TWIST_LAUNCHED',1);
			}
		}

		/**
		 * Add the ability to serve some basic core resources, this functionality will soon be deprecated in favour of using the resources package.
		 * @deprecated
		 */
        protected static function coreResources(){

            $strResourcesURI = sprintf('%s/%sCore/Resources/',rtrim(SITE_URI_REWRITE,'/'),ltrim(TWIST_FRAMEWORK_URI,'/'));

            $arrResources = array(
                'arable' => sprintf('%sarable/arable.min.css',$strResourcesURI),
                'arable-reset' => sprintf('%sarable/arable-reset.min.css',$strResourcesURI),
                'font-awesome' => sprintf('%sfont-awesome/css/font-awesome.min.css',$strResourcesURI),
                'jquery' => sprintf('%sjquery/jquery-2.1.3.min.js',$strResourcesURI),
                'jquery-legacy' => sprintf('%sjquery/jquery-1.11.2.min.js',$strResourcesURI),
                'logo' => sprintf('%stwist/logos/logo.png',$strResourcesURI),
                'logo-favicon' => sprintf('%stwist/logos/favicon.ico',$strResourcesURI),
                'logo-32' => sprintf('%stwist/logos/logo-32.png',$strResourcesURI),
                'logo-48' => sprintf('%stwist/logos/logo-48.png',$strResourcesURI),
                'logo-57' => sprintf('%stwist/logos/logo-57.png',$strResourcesURI),
                'logo-64' => sprintf('%stwist/logos/logo-64.png',$strResourcesURI),
                'logo-72' => sprintf('%stwist/logos/logo-72.png',$strResourcesURI),
                'logo-96' => sprintf('%stwist/logos/logo-96.png',$strResourcesURI),
                'logo-114' => sprintf('%stwist/logos/logo-114.png',$strResourcesURI),
                'logo-128' => sprintf('%stwist/logos/logo-128.png',$strResourcesURI),
                'logo-144' => sprintf('%stwist/logos/logo-144.png',$strResourcesURI),
                'logo-192' => sprintf('%stwist/logos/logo-192.png',$strResourcesURI),
                'logo-256' => sprintf('%stwist/logos/logo-256.png',$strResourcesURI),
                'logo-512' => sprintf('%stwist/logos/logo-512.png',$strResourcesURI),
                'logo-640' => sprintf('%stwist/logos/logo-640.png',$strResourcesURI),
                'logo-800' => sprintf('%stwist/logos/logo-800.png',$strResourcesURI),
                'logo-1024' => sprintf('%stwist/logos/logo-1024.png',$strResourcesURI),
                'logo-large' => sprintf('%stwist/logos/logo-512.png',$strResourcesURI),
                'logo-small' => sprintf('%stwist/logos/logo-32.png',$strResourcesURI),
                'modernizr' => sprintf('%smodernizr/modernizr-2.8.3.min.js',$strResourcesURI),
                'rummage' => sprintf('%srummage/rummage.min.js',$strResourcesURI),
                'shadow-js' => sprintf('%sshadow-js/shadow-js.min.js',$strResourcesURI),
                'unsemantic' => sprintf('%sunsemantic/unsemantic-grid-responsive-tablet-no-ie7.css',$strResourcesURI),
                'resources_uri' => $strResourcesURI,
                'uri' => ltrim(sprintf('%s/%s',rtrim(SITE_URI_REWRITE,'/'),ltrim(TWIST_FRAMEWORK_URI,'/')),'/')
            );

            //Integrate the basic core href tag support - legacy support
            Twist::framework() -> package() -> extend('Template','core',$arrResources);
        }

		/**
		 * Show the setup wizard, if the wizard is required to be output all existing routes will be cleared and the wizard will be served.
		 */
		protected static function showSetup(){

			if(Twist::framework() -> settings() -> showSetup()){

				self::Route()->purge();
				self::Route()->setDirectory(sprintf('%ssetup/',TWIST_FRAMEWORK_VIEWS));
				self::Route()->baseView('_base.tpl');
				self::Route()->baseURI(TWIST_BASE_URI);
				self::Route()->controller('/%','\Twist\Core\Controllers\Setup');
				self::Route()->serve();
			}
		}

		/**
		 * Set PHP settings that will allow your site to work the way you need it too
		 */
		protected static function phpSettings(){

			if(!is_null(Twist::framework()->setting('PHP_MEMORY_LIMIT'))){
				ini_set('memory_limit',Twist::framework()->setting('PHP_MEMORY_LIMIT'));
			}

			if(!is_null(Twist::framework()->setting('PHP_MAX_EXECUTION'))){
				ini_set('max_executions_time',Twist::framework()->setting('PHP_MAX_EXECUTION'));
			}
		}

		/**
		 * Register the PHP handlers for errors, exceptions and log outputs
		 */
		protected static function errorHandlers(){

			if(Twist::framework()->setting('ERROR_HANDLING')){
				Twist::framework() -> register() -> handler('error','Twist\Classes\Error','handleError');
			}

			if(Twist::framework()->setting('ERROR_FATAL_HANDLING')){
				Twist::framework() -> register() -> handler('fatal','Twist\Classes\Error','handleFatal');
			}

			if(Twist::framework()->setting('ERROR_EXCEPTION_HANDLING')){
				Twist::framework() -> register() -> handler('exception','Twist\Classes\Error','handleException');
			}

			if(Twist::framework()->setting('ERROR_LOG')){
				Twist::framework() -> register() -> shutdownEvent('errorLog','Twist\Classes\Error','outputLog');
			}
		}

		/**
		 * Log an error message that can be output using the {messages:} template tag
		 * @param $strMessage
		 * @param null $strKey
		 */
		public static function errorMessage($strMessage,$strKey = null){
			self::messageProcess($strMessage,$strKey,'error');
		}

		/**
		 * Log an warning message that can be output using the {messages:} template tag
		 * @param $strMessage
		 * @param null $strKey
		 */
		public static function warningMessage($strMessage,$strKey = null){
			self::messageProcess($strMessage,$strKey,'warning');
		}

		/**
		 * Log an notice message that can be output using the {messages:} template tag
		 * @param $strMessage
		 * @param null $strKey
		 */
		public static function noticeMessage($strMessage,$strKey = null){
			self::messageProcess($strMessage,$strKey,'notice');
		}

		/**
		 * Log an success message that can be output using the {messages:} template tag
		 * @param $strMessage
		 * @param null $strKey
		 */
		public static function successMessage($strMessage,$strKey = null){
			self::messageProcess($strMessage,$strKey,'success');
		}

		/**
		 * Redirect the user to a new page or site by URL, optionally you can make the redirect permanent.
		 * URL redirects can be passed in as full path/URL or relative to your current URI. For example you can pass in '../../' or './test'
		 * @param $urlRedirectURL URL that the user will be redirected too
		 * @param $blPermanent Set the redirect type to be a Permanent 301 redirect
		 */
		public static function redirect($urlRedirect,$blPermanent = false){

			$urlRedirect = \Twist::framework()->tools()->traverseURI($urlRedirect);

			header(sprintf('Location: %s',$urlRedirect),true,($blPermanent) ? 301 : 302);
			die();
		}

		/**
		 * Respond with a HTTP status page, pass in the status code that you require
		 * @param $intResponseCode Code of the required response i.e. 404
		 */
		public static function respond($intResponseCode,$strCustomDescription = null){
			Error::errorPage($intResponseCode,$strCustomDescription);
		}

		/**
		 * Dump data to the screen in a nice format with other key debug information
		 * @param null $mxdData
		 * @throws Exception
		 */
		public static function dump($mxdData = null){
			throw new \Exception(json_encode($mxdData),1200);
		}

		/**
		 * Record events on for the current page load can be logged and a time-line produced, helps with debugging.
		 * The TwistPHP event recorder only records and outputs events if DEVELOPMENT_MODE and DEVELOPMENT_EVENT_RECORDER settings are set to true|1.
		 * @param $strEventName
		 */
		public static function recordEvent($strEventName){
			if(self::$blRecordEvents){
				Twist::Timer('TwistEventRecorder')->log($strEventName);
			}
		}

		/**
		 * Get an array of all the recorded events from withing the TwistPHP even recorder, this will provide times and memory usage data on allow of key processing done by TwistPHP.
		 *  The TwistPHP event recorder only records and outputs events if DEVELOPMENT_MODE and DEVELOPMENT_EVENT_RECORDER settings are set to true|1.
		 * @param bool $blStopTimer
		 * @return array|mixed
		 */
		public static function getEvents($blStopTimer = false){
			return (self::$blRecordEvents) ? (($blStopTimer) ? \Twist::Timer('TwistEventRecorder')->stop() : \Twist::Timer('TwistEventRecorder')->results()) : array();
		}

		/**
		 * Returns the core framework classes, these are not packages but contain some useful tools such as settings.
		 * @return \Twist\Core\Controllers\Framework
		 */
		public static function framework(){

			$resTwistModule = (!Instance::isObject('CoreFramework')) ? new Framework() : Instance::retrieveObject('CoreFramework');
			Instance::storeObject('CoreFramework',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Process each message as they are added and store them for the current PHP session only
		 * @param $strMessage
		 * @param $strKey
		 * @param $strType
		 */
		protected static function messageProcess($strMessage,$strKey,$strType){

			$arrMessages = \Twist::Cache()->read('twistUserMessages');
			$arrMessages = (is_null($arrMessages)) ? array() : $arrMessages;

			$strMessageKey = sprintf('%s-%s',$strKey,$strType);

			if(array_key_exists($strMessageKey,$arrMessages)){
				$arrMessages[$strMessageKey]['messages'][] = $strMessage;
			}else{

				$arrMessages[$strMessageKey] = array(
					'type' => $strType,
					'key' => $strKey,
					'messages' => array($strMessage)
				);
			}

			\Twist::Cache()->write('twistUserMessages',$arrMessages,0);
		}

		/**
		 * Process the user messages to be output into the view
		 *
		 * Tag: Tag can be any one of the below but not multiple.
		 * {messages:all|error|notice|warning|success}
		 *
		 * Tag Parameters:
		 * combine - default is true (on)
		 * key - pass in the required messages by key can be pipe (|) separated
		 * style - determine th output styling, currently can be plain, rich or HTML
		 *
		 * Example Tag:
		 * {messages:error,combine=true,key=andi|dan,style=html}
		 *
		 * @param $strReference
		 * @param array $arrParameters
		 * @return string
		 */
		public static function messageHandler($strReference,$arrParameters = array()){

			$strOut = '';
			$arrCombine = array();
			$arrMessages = \Twist::Cache()->read('twistUserMessages');

			//Combine is enabled by default it not passed in (combines all messages by type)
			$blCombine = (!array_key_exists('combine',$arrParameters) || $arrParameters['combine']);

			$strStyle = array_key_exists('style',$arrParameters) ? $arrParameters['style'] : null;
			$mxdFilterByKey = array_key_exists('key',$arrParameters) ? $arrParameters['key'] : null;

			if(is_array($arrMessages)){
				foreach($arrMessages as $strUniqueKey => $arrData){

					if($strReference == 'all' || $strReference == $arrData['type']){

						if(is_null($mxdFilterByKey) || (is_array($mxdFilterByKey) && in_array($arrData['key'],$mxdFilterByKey)) || $mxdFilterByKey == $arrData['key']){

							switch($strStyle){

								case'plain':
									$strOut .= implode("\n",$arrData['messages']);
									break;

								case'rich':
									$strOut .= implode("<br>",$arrData['messages']);
									break;

								case'html':
								default:

									if($blCombine){
										if(!array_key_exists($arrData['type'],$arrCombine)){
											$arrCombine[$arrData['type']] = implode("<br>",$arrData['messages']);
										}else{
											$arrCombine[$arrData['type']] .= '<br>'.implode("<br>",$arrData['messages']);
										}
									}else{
										$strOut .= self::View()->build(sprintf('%smessages/%s.tpl',TWIST_FRAMEWORK_VIEWS,$arrData['type']),array('key' => $arrData['key'],'type' => $arrData['type'],'message' => implode("<br>",$arrData['messages'])));
									}
									break;
							}
						}
					}
				}

				//If we are looking at a combined output, we need to run a final process on the combined array
				if($strOut === '' && count($arrCombine)){
					foreach($arrCombine as $strType => $strMessage){
						$strOut .= self::View()->build(sprintf('%smessages/%s.tpl',TWIST_FRAMEWORK_VIEWS,$strType),array('key' => '','type' => $strType,'message' => $strMessage));
					}
				}
			}

			return $strOut;
		}

		/**
		 * Call 3rd parky packages in the framework located in your packages folder
		 * Alternatively packages can be called '$resMyPackage = new Package\MyPackage();'
		 * @param $strPackageName
		 * @return mixed
		 */
		public static function package($strPackageName){

			$strObjectRef = sprintf('userPackage_%s',$strPackageName);
			$strPackage = sprintf('\Packages\%s\Models\%s',$strPackageName,$strPackageName);

			$resPackage = (!Instance::isObject($strObjectRef)) ? new $strPackage() : Instance::retrieveObject($strObjectRef);
			Instance::storeObject($strObjectRef,$resPackage);
			return $resPackage;
		}

		/**
		 * Return an instance of the Archive package.
		 * @return \Twist\Core\Packages\Archive
		 */
		public static function Archive(){

			$resTwistModule = (!Instance::isObject('pkgArchive')) ? new Packages\Archive() : Instance::retrieveObject('pkgArchive');
			Instance::storeObject('pkgArchive',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Asset package.
		 * @return \Twist\Core\Packages\Asset
		 */
		public static function Asset(){

			$resTwistModule = (!Instance::isObject('pkgAsset')) ? new Packages\Asset() : Instance::retrieveObject('pkgAsset');
			Instance::storeObject('pkgAsset',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Cookie package.
		 * @return \Twist\Core\Packages\Cookie
		 */
		public static function Cookie(){

			$resTwistModule = (!Instance::isObject('pkgCookie')) ? new Packages\Cookie() : Instance::retrieveObject('pkgCookie');
			Instance::storeObject('pkgCookie',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the CSV package.
		 * @return \Twist\Core\Packages\CSV
		 */
		public static function CSV(){

			$resTwistModule = (!Instance::isObject('pkgCSV')) ? new Packages\CSV() : Instance::retrieveObject('pkgCSV');
			Instance::storeObject('pkgCSV',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Cache package.
		 * @return \Twist\Core\Packages\Cache
		 */
		public static function Cache(){

			$strObjectKey = (count(func_get_args())) ? func_get_arg(0) : 'twist';

			//Could be done in 2 lines of code but PHP editors are not smart enough to auto-complete
			if(count(func_get_args())){
				$strInstanceKey = sprintf('pkgCache-%s',$strObjectKey);
				$resTwistModule = (!Instance::isObject($strInstanceKey)) ? new Packages\Cache($strObjectKey) : Instance::retrieveObject($strInstanceKey);
				Instance::storeObject($strInstanceKey,$resTwistModule);
			}else{
				$resTwistModule = (!Instance::isObject('pkgCache')) ? new Packages\Cache($strObjectKey) : Instance::retrieveObject('pkgCache');
				Instance::storeObject('pkgCache',$resTwistModule);
			}

			return $resTwistModule;
		}

		/**
		 * Return an instance of the Command package.
		 * @return \Twist\Core\Packages\Command
		 */
		public static function Command(){

			$resTwistModule = (!Instance::isObject('pkgCommand')) ? new Packages\Command() : Instance::retrieveObject('pkgCommand');
			Instance::storeObject('pkgCommand',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Curl package.
		 * @return \Twist\Core\Packages\Curl
		 */
		public static function Curl(){

			$resTwistModule = (!Instance::isObject('pkgCurl')) ? new Packages\Curl() : Instance::retrieveObject('pkgCurl');
			Instance::storeObject('pkgCurl',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Database package.
		 * @return \Twist\Core\Packages\Database
		 */
		public static function Database(){

			$strObjectKey = (count(func_get_args())) ? func_get_arg(0) : 'twist';

			//Could be done in 2 lines of code but PHP editors are not smart enough to auto-complete
			if(count(func_get_args())){
				$strInstanceKey = sprintf('pkgDatabase-%s',$strObjectKey);
				$resTwistModule = (!Instance::isObject($strInstanceKey)) ? new Packages\Database($strObjectKey) : Instance::retrieveObject($strInstanceKey);
				Instance::storeObject($strInstanceKey,$resTwistModule);
			}else{
				$resTwistModule = (!Instance::isObject('pkgDatabase')) ? new Packages\Database($strObjectKey) : Instance::retrieveObject('pkgDatabase');
				Instance::storeObject('pkgDatabase',$resTwistModule);
			}

			return $resTwistModule;
		}

		/**
		 * Return an instance of the DateTime package.
		 * @return \Twist\Core\Packages\DateTime
		 */
		public static function DateTime(){

			$resTwistModule = (!Instance::isObject('pkgDateTime')) ? new Packages\DateTime() : Instance::retrieveObject('pkgDateTime');
			Instance::storeObject('pkgDateTime',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Device package.
		 * @return \Twist\Core\Packages\Device
		 */
		public static function Device(){

			$resTwistModule = (!Instance::isObject('pkgDevice')) ? new Packages\Device() : Instance::retrieveObject('pkgDevice');
			Instance::storeObject('pkgDevice',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Email package.
		 * @return \Twist\Core\Packages\Email
		 */
		public static function Email(){

			$resTwistModule = (!Instance::isObject('pkgEmail')) ? new Packages\Email() : Instance::retrieveObject('pkgEmail');
			Instance::storeObject('pkgEmail',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the File package.
		 * @return \Twist\Core\Packages\File
		 */
		public static function File(){

			$resTwistModule = (!Instance::isObject('pkgFile')) ? new Packages\File() : Instance::retrieveObject('pkgFile');
			Instance::storeObject('pkgFile',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the FTP package.
		 * @return \Twist\Core\Packages\FTP
		 */
		public static function FTP(){

			$resTwistModule = (!Instance::isObject('pkgFTP')) ? new Packages\FTP() : Instance::retrieveObject('pkgFTP');
			Instance::storeObject('pkgFTP',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the ICS package.
		 * @return \Twist\Core\Packages\ICS
		 */
		public static function ICS(){

			$resTwistModule = (!Instance::isObject('pkgICS')) ? new Packages\ICS() : Instance::retrieveObject('pkgICS');
			Instance::storeObject('pkgICS',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Image package.
		 * @return \Twist\Core\Packages\Image
		 */
		public static function Image(){

			$resTwistModule = (!Instance::isObject('pkgImage')) ? new Packages\Image() : Instance::retrieveObject('pkgImage');
			Instance::storeObject('pkgImage',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Localisation package.
		 * @return \Twist\Core\Packages\Localisation
		 */
		public static function Localisation(){

			$resTwistModule = (!Instance::isObject('pkgLocalisation')) ? new Packages\Localisation() : Instance::retrieveObject('pkgLocalisation');
			Instance::storeObject('pkgLocalisation',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Route package.
		 * @return \Twist\Core\Packages\Route
		 */
		public static function Route(){

			$strObjectKey = (count(func_get_args())) ? func_get_arg(0) : 'twist';

			//Could be done in 2 lines of code but PHP editors are not smart enough to auto-complete
			if(count(func_get_args())){
				$strInstanceKey = sprintf('pkgRoute-%s',$strObjectKey);
				$resTwistModule = (!Instance::isObject($strInstanceKey)) ? new Packages\Route($strObjectKey) : Instance::retrieveObject($strInstanceKey);
				Instance::storeObject($strInstanceKey,$resTwistModule);
			}else{
				$resTwistModule = (!Instance::isObject('pkgRoute')) ? new Packages\Route($strObjectKey) : Instance::retrieveObject('pkgRoute');
				Instance::storeObject('pkgRoute',$resTwistModule);
			}

			return $resTwistModule;
		}

		/**
		 * Return an instance of the Session package.
		 * @return \Twist\Core\Packages\Session
		 */
		public static function Session(){

			$resTwistModule = (!Instance::isObject('pkgSession')) ? new Packages\Session() : Instance::retrieveObject('pkgSession');
			Instance::storeObject('pkgSession',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Timer package.
		 * @return \Twist\Core\Packages\Timer
		 */
		public static function Timer(){

			$strObjectKey = (count(func_get_args())) ? func_get_arg(0) : 'twist';

			//Could be done in 2 lines of code but PHP editors are not smart enough to auto-complete
			if(count(func_get_args())){
				$strInstanceKey = sprintf('pkgTimer-%s',$strObjectKey);
				$resTwistModule = (!Instance::isObject($strInstanceKey)) ? new Packages\Timer($strObjectKey) : Instance::retrieveObject($strInstanceKey);
				Instance::storeObject($strInstanceKey,$resTwistModule);
			}else{
				$resTwistModule = (!Instance::isObject('pkgTimer')) ? new Packages\Timer($strObjectKey) : Instance::retrieveObject('pkgTimer');
				Instance::storeObject('pkgTimer',$resTwistModule);
			}

			return $resTwistModule;
		}

		/**
		 * Return an instance of the User package.
		 * @return \Twist\Core\Packages\User
		 */
		public static function User(){

			$resTwistModule = (!Instance::isObject('pkgUser')) ? new Packages\User() : Instance::retrieveObject('pkgUser');
			Instance::storeObject('pkgUser',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the Validate package.
		 * @return \Twist\Core\Packages\Validate
		 */
		public static function Validate(){

			$resTwistModule = (!Instance::isObject('pkgValidate')) ? new Packages\Validate() : Instance::retrieveObject('pkgValidate');
			Instance::storeObject('pkgValidate',$resTwistModule);
			return $resTwistModule;
		}

		/**
		 * Return an instance of the View package.
		 * @return \Twist\Core\Packages\View
		 */
		public static function View(){

			$strObjectKey = (count(func_get_args())) ? func_get_arg(0) : 'twist';

			//Could be done in 2 lines of code but PHP editors are not smart enough to auto-complete
			if(count(func_get_args())){
				$strInstanceKey = sprintf('pkgView-%s',$strObjectKey);
				$resTwistModule = (!Instance::isObject($strInstanceKey)) ? new Packages\View($strObjectKey) : Instance::retrieveObject($strInstanceKey);
				Instance::storeObject($strInstanceKey,$resTwistModule);
			}else{
				$resTwistModule = (!Instance::isObject('pkgView')) ? new Packages\View($strObjectKey) : Instance::retrieveObject('pkgView');
				Instance::storeObject('pkgView',$resTwistModule);
			}

			return $resTwistModule;
		}

		/**
		 * Return an instance of the XML package.
		 * @return \Twist\Core\Packages\XML
		 */
		public static function XML(){

			$resTwistModule = (!Instance::isObject('pkgXML')) ? new Packages\XML() : Instance::retrieveObject('pkgXML');
			Instance::storeObject('pkgXML',$resTwistModule);
			return $resTwistModule;
		}
	}