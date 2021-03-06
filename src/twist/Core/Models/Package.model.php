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

	namespace Twist\Core\Models;
	use Twist\Classes\Instance;

	/**
	 * Handle all package/package related enquiries, for instance if you want to know if a package is installed or what version it is.
	 */
	class Package{

		protected $arrPackages = array();

		/**
		 * Get an array of all packages in the system, both installed and not installed (but in the packages folder)
		 * @return array
		 */
		public function getAll(){

			$arrOut = array_merge($this->getUninstalled(),$this->getInstalled());
			ksort($arrOut);

			return $arrOut;
		}

		/**
		 * Get an array of all the packages that are in the packages folder but have not been installed
		 * @return array
		 */
		public function getUninstalled(){

			$arrOut = array();
			$this->getInstalled();

			//Find Packages
			foreach(scandir(TWIST_PACKAGES) as $strFile){

				$dirPackage = sprintf('%s/%s',TWIST_PACKAGES,$strFile);

				if(!in_array($strFile,array('.','..')) && is_dir($dirPackage)){

					$strPackageSlug = strtolower(basename($dirPackage));

					//Check to see if the package is already installed
					if(!array_key_exists($strPackageSlug,$this->arrPackages)){

						if(is_file(sprintf('%s/info.json',$dirPackage)) &&
							is_file(sprintf('%s/install.php',$dirPackage)) &&
							is_file(sprintf('%s/uninstall.php',$dirPackage))){

							$rawJson = file_get_contents(sprintf('%s/info.json',$dirPackage));
							$arrDetails = json_decode($rawJson,true);

							$arrOut[$strPackageSlug] = array(
								'slug' => $strPackageSlug,
								'name' => $arrDetails['name'],
								'version' => $arrDetails['version'],
								'folder' => basename($dirPackage),
								'package' => 1,
								'details' => $arrDetails
							);
						}
					}
				}
			}

			return $arrOut;
		}

		/**
		 * Get an array of all the installed packages on the system
		 * @return array|bool
		 */
		public function getInstalled($blRebuild = false){

			if(\Twist::Database()->checkSettings() && !count($this->arrPackages) || $blRebuild){

				$this->arrPackages = array();
				$arrPackages = \Twist::Database()->getAll(TWIST_DATABASE_TABLE_PREFIX.'packages');

				if(count($arrPackages)){
					$arrPackages = \Twist::framework()->tools()->arrayReindex($arrPackages,'slug');

					foreach($arrPackages as $strSlug => $arrPackageData){
						$this->load($strSlug,$arrPackageData);
					}
				}
			}

			return $this->arrPackages;
		}

		/**
		 * Load the package into the framework for us
		 * @param $strSlug
		 * @param $arrPackageData
		 */
		protected function load($strSlug,$arrPackageData){

			if(!array_key_exists($strSlug,$this->arrPackages)){
				$this->arrPackages[$strSlug] = $arrPackageData;
			}

			$dirPath = sprintf('%s/%s',TWIST_PACKAGES,$arrPackageData['folder']);

			$rawJson = file_get_contents(sprintf('%s/info.json',$dirPath));
			$arrDetails = json_decode($rawJson,true);

			//Add the details from the info JSON file
			$this->arrPackages[$strSlug]['details'] = $arrDetails;
			$this->arrPackages[$strSlug]['path'] = $dirPath;

			//Add the URI to the package here
			$this->arrPackages[$strSlug]['uri'] = '';

			//Register any resources into the framework from the package
			if(file_exists(sprintf('%s/resources.json',$dirPath))){
				$resCoreResources = Instance::retrieveObject('twistCoreResources');
				$resCoreResources->extendLibrary(sprintf('%s/resources.json',$dirPath),sprintf('%s/resources',$dirPath));
			}

			//Expand the JSON data
			$this->arrPackages[$strSlug]['resources'] = ($arrPackageData['resources'] != '') ?  json_decode($arrPackageData['resources'],true) : array();
			$this->arrPackages[$strSlug]['routes'] = ($arrPackageData['routes'] != '') ?  json_decode($arrPackageData['routes'],true) : array();
			$this->arrPackages[$strSlug]['blocks'] = ($arrPackageData['blocks'] != '') ?  json_decode($arrPackageData['blocks'],true) : array();
			$this->arrPackages[$strSlug]['extensions'] = ($arrPackageData['extensions'] != '') ?  json_decode($arrPackageData['extensions'],true) : array();
		}

		/**
		 * Find the uninstalled package by its slug and run the install.php file within the package folder.
		 * @param $strInstallSlug
		 * @return bool
		 */
		public function installer($strInstallSlug){

			$blOut = false;

			foreach($this->getUninstalled() as $strSlug => $arrEachPackage){
				if($strInstallSlug === $strSlug){
					include sprintf('%s/%s/install.php',TWIST_PACKAGES,$arrEachPackage['folder']);
					$blOut = true;
					break;
				}
			}

			return $blOut;
		}

		/**
		 * Install the package into the framework, this function is called by the packages install.php file located in the package folder.
		 * It handles the registration of the package withing the framework.
		 */
		public function install(){

			$arrBacktrace = debug_backtrace();

			if(count($arrBacktrace)){

				$dirInstallFile = $arrBacktrace[0]['file'];
				$dirPackage = dirname($dirInstallFile);

				$rawJson = file_get_contents(sprintf('%s/info.json',$dirPackage));
				$arrDetails = json_decode($rawJson,true);

				$strSlug = strtolower(basename($dirPackage));

				$arrResources = $arrRoutes = $arrBlocks = $arrExtensions = array();

				$resPackage = \Twist::Database()->createRecord(TWIST_DATABASE_TABLE_PREFIX.'packages');

				$resPackage->set('slug',$strSlug);
				$resPackage->set('name',$arrDetails['name']);
				$resPackage->set('version',$arrDetails['version']);
				$resPackage->set('folder',basename($dirPackage));
				$resPackage->set('package',(is_file(sprintf('%s/package.php',$dirPackage))) ? '1' : '0');
				$resPackage->set('installed',date('Y-m-d H:i:s'));
				$resPackage->set('resources',json_encode($arrResources));
				$resPackage->set('routes',json_encode($arrRoutes));
				$resPackage->set('blocks',json_encode($arrBlocks));
				$resPackage->set('extensions',json_encode($arrExtensions));

				$intPackage = $resPackage->commit();

				//Update the list of installed packages
				$this->load($strSlug,$resPackage->values());

				return $intPackage;
			}

			return false;
		}

		/**
		 * Install any DB and tables required by the package, this function is called by the packages install.php file located in the package folder.
		 * @param $dirInstallSQL
		 */
		public function importSQL($dirInstallSQL){

			$arrBacktrace = debug_backtrace();
			if(count($arrBacktrace)) {

				$dirInstallFile = $arrBacktrace[0]['file'];
				$dirPackage = dirname($dirInstallFile);

				//Install the SQL tables when required
				$dirInstallSQL = (!file_exists($dirInstallSQL)) ? sprintf('%s/%s', $dirPackage, $dirInstallSQL) : $dirInstallSQL;

				if(file_exists($dirInstallSQL)){

					//Create a temp file with all the required table pre-fixes
					$dirImportFile = tempnam(sys_get_temp_dir(), 'twist-import');
					file_put_contents($dirImportFile, str_replace('/*TWIST_DATABASE_TABLE_PREFIX*/`', sprintf('`%s', TWIST_DATABASE_TABLE_PREFIX), file_get_contents($dirInstallSQL)));

					//Import the SQL form the temp file
					\Twist::Database()->importSQL($dirImportFile);

					//Remove the temp file form the system
					unlink($dirImportFile);
				}
			}
		}

		/**
		 * Install any framework settings that are required by the package, this function is called by the packages install.php file located in the package folder.
		 * @param $dirSettingsJSON
		 * @throws \Exception
		 */
		public function importSettings($dirSettingsJSON){

			$arrBacktrace = debug_backtrace();
			if(count($arrBacktrace)) {

				$dirInstallFile = $arrBacktrace[0]['file'];
				$dirPackage = dirname($dirInstallFile);
				$strSlug = strtolower(basename($dirPackage));

				//Install the SQL tables when required
				$dirSettingsJSON = (!file_exists($dirSettingsJSON)) ? sprintf('%s/%s', $dirPackage, $dirSettingsJSON) : $dirSettingsJSON;

				if(file_exists($dirSettingsJSON)){

					$arrSettings = json_decode(file_get_contents($dirSettingsJSON),true);
					if(count($arrSettings)){

						foreach($arrSettings as $strKey => $arrOptions){

							\Twist::framework()->settings()->install(
								$strSlug,
								'package',
								$strKey,
								$arrOptions['default'],
								$arrOptions['title'],
								$arrOptions['description'],
								$arrOptions['default'],
								$arrOptions['type'],
								$arrOptions['options'],
								$arrOptions['null']
							);
						}
					}
				}
			}
		}

		/**
		 * Remove all the settings for a particular package, this is usually called by the uninstall.php file located in the package folder
		 */
		public function removeSettings(){

			$arrBacktrace = debug_backtrace();
			if(count($arrBacktrace)) {

				$dirInstallFile = $arrBacktrace[0]['file'];
				$dirPackage = dirname($dirInstallFile);
				$strSlug = strtolower(basename($dirPackage));

				\Twist::framework()->settings()->uninstall($strSlug,'package');
			}
		}

		/**
		 * Find the installed package by its slug and run the uninstall.php file within the package folder.
		 * @param $strInstallSlug
		 * @return bool
		 */
		public function uninstaller($strUnInstallSlug){

			$blOut = false;

			foreach($this->getInstalled() as $strSlug => $arrEachPackage){
				if($strUnInstallSlug === $strSlug){
					include sprintf('%s/%s/uninstall.php',TWIST_PACKAGES,$arrEachPackage['folder']);
					$blOut = true;
					break;
				}
			}

			return $blOut;
		}

		/**
		 * Uninstall the package from the framework, this happens by removing the package record form the twist_packages DB table.
		 */
		public function uninstall(){

			$arrBacktrace = debug_backtrace();

			if(count($arrBacktrace)){

				$dirInstallFile = $arrBacktrace[0]['file'];
				$dirPackage = dirname($dirInstallFile);

				$rawJson = file_get_contents(sprintf('%s/info.json',$dirPackage));
				$arrDetails = json_decode($rawJson,true);

				$strSlug = strtolower(basename($dirPackage));

				$resPackage = \Twist::Database()->getRecord(TWIST_DATABASE_TABLE_PREFIX.'packages',$strSlug,'slug');
				$resPackage->delete();

				return true;
			}

			return false;
		}

		/**
		 * Check to see if a package is installed on the framework by its package slug (lowercase package folder name)
		 * @param $strPackageSlug
		 * @return bool
		 */
		public function isInstalled($strPackageSlug){
			return (count(\Twist::Database()->get(TWIST_DATABASE_TABLE_PREFIX.'packages',$strPackageSlug,'slug'))) ? true : false;
		}

		/**
		 * Check to see that a package is installed and usable, optional throw an exception of the package dosnt exist
		 * @param $strPackage
		 * @param $blThrowException
		 * @return bool
		 */
		public function exists($strPackageSlug,$blThrowException = false){

			$blInstalled = $this->isInstalled($strPackageSlug);

			if($blThrowException && !$blInstalled){
				throw new \Exception(sprintf("The package '%s' has not been installed or does not exist",$strPackageSlug));
			}

			return $blInstalled;
		}

		/**
		 * Get the details of an installed package and return them as an array
		 * @param $strPackageSlug
		 * @return array
		 */
		public function get($strPackageSlug){
			return (array_key_exists($strPackageSlug,$this->arrPackages)) ? $this->arrPackages[$strPackageSlug] : array();
		}

		/**
		 * Register the package for use withing the system
		 * @param $strPackage
		 * @param $mxdKey
		 * @param $mxdData
		 */
		public function extend($strPackage,$mxdKey,$mxdData){

			//@deprecate when remove template all traces of templates
			$strPackage = ($strPackage === 'Template') ? 'View' : $strPackage;

			if(!array_key_exists($strPackage,$this->arrPackages)){
				$this->arrPackages[$strPackage] = array('resources' => array(),'routes' => array(),'blocks' => array(),'extensions' => array());
			}

			$this->arrPackages[$strPackage]['extensions'][$mxdKey] = $mxdData;
		}

		/**
		 * Get the array of extensions for the requested package
		 * @param $strPackage
		 * @return array
		 */
		public function extensions($strPackage){
			return (array_key_exists($strPackage,$this->arrPackages)) ? $this->arrPackages[$strPackage]['extensions'] : array();
		}

		/**
		 * Get all the current information for any installed package
		 * @param $strPackage
		 * @return array
		 */
		public function information($strPackage){
			$arrParts = explode('\\',$strPackage);
			//$strPackage = strtolower(array_pop($arrParts));
			$strPackage = array_pop($arrParts);
			return (array_key_exists($strPackage,$this->arrPackages)) ? $this->arrPackages[$strPackage] : array();
		}

		/**
		 * Load the interface that comes as part of a package
		 * @param $strPackage
		 * @throws \Exception
		 */
		public function route($strPackageRoute,$strRegisteredURI,$mxdBaseView){

			$arrParts = explode('\\',$strPackageRoute);

			if($arrParts[0] == 'Twist' || $this->isInstalled(strtolower($arrParts[1]))){

				//Call the interface
				$objInterface = new $strPackageRoute($arrParts[1]);
				$objInterface->baseURI($strRegisteredURI);

				//Set the view directory to the one in the package
				$objInterface->setDirectory( ($arrParts[0] == 'Twist') ? TWIST_FRAMEWORK_VIEWS : sprintf('%s/%s/Views/',TWIST_PACKAGES,$arrParts[1]));

				if($mxdBaseView === false || is_null($mxdBaseView)){
					$objInterface->baseViewIgnore();
				}elseif($mxdBaseView !== true){
					$objInterface->baseView($mxdBaseView);
				}

				$objInterface->load();
				$objInterface->serve();
			}else{
				throw new \Exception(sprintf("TwistPHP: There is no registered package route '%s'",$arrParts[0]));
			}
		}

		/**
		 * Register a package route into a routes array
		 * @param $strPackage
		 * @param $strRouteName
		 */
		public function registerRoute($strPackage,$strRouteName){

			if(!array_key_exists($strPackage,$this->arrPackages)){
				$this->arrPackages[$strPackage] = array('type' => null,'name' => null,'description' => null,'version' => null,'author' => null,'class' => null,'instances' => null,'path' => '','uri' => '','routes' => array(),'extensions' => array(),'installed' => 0);
			}

			$this->arrPackages[$strPackage]['routes'][$strRouteName] = $strRouteName;
			$this->arrRoutes[$strRouteName] = $strPackage;
		}
	}