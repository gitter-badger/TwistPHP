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

namespace Twist\Core\Controllers;

/**
 * The route controller for the framework manager, generates the pages of the manager tool.
 * @package Twist\Core\Controllers
 */
class Manager extends BaseUser{

	public function __construct(){
		\Twist::Route()->setDirectory(sprintf('%smanager/',TWIST_FRAMEWORK_VIEWS));
		$this->_aliasURI('update-setting','getUpdateSetting');
	}

	/**
	 * @alias dashboard
	 * @return string
	 */
	public function _index(){
		return $this->dashboard();
	}

	/**
	 * Manager dashboard page, here you have access to some of the core framework settings and information
	 * @return string
	 */
	public function dashboard(){

		$arrTags['development-mode'] = (\Twist::framework()->setting('DEVELOPMENT_MODE') == '1') ? 'On' : 'Off';
		$arrTags['maintenance-mode'] = (\Twist::framework()->setting('MAINTENANCE_MODE') == '1') ? 'On' : 'Off';
		$arrTags['debug-bar'] = (\Twist::framework()->setting('DEVELOPMENT_DEBUG_BAR') == '1') ? 'On' : 'Off';
		$arrTags['data-caching'] = (\Twist::framework()->setting('CACHE_ENABLED') == '1') ? 'On' : 'Off';

		$arrRoutes = \Twist::Route()->getAll();
		$arrTags['route-data'] = sprintf('<strong>%d</strong> ANY<br><strong>%d</strong> GET<br><strong>%d</strong> POST<br><strong>%d</strong> PUT<br><strong>%d</strong> DELETE',
			count($arrRoutes['ANY']),
			count($arrRoutes['GET']),
			count($arrRoutes['POST']),
			count($arrRoutes['PUT']),
			count($arrRoutes['DELETE']));

		$arrTags['user-accounts'] = sprintf('<strong>%d</strong> Superadmin,<br><strong>%d</strong> Admin,<br><strong>%d</strong> Advanced,<br><strong>%d</strong> Member',
			\Twist::Database()->count(sprintf('%susers',TWIST_DATABASE_TABLE_PREFIX),\Twist::framework()->setting('USER_LEVEL_SUPERADMIN'),'level'),
			\Twist::Database()->count(sprintf('%susers',TWIST_DATABASE_TABLE_PREFIX),\Twist::framework()->setting('USER_LEVEL_ADMIN'),'level'),
			\Twist::Database()->count(sprintf('%susers',TWIST_DATABASE_TABLE_PREFIX),\Twist::framework()->setting('USER_LEVEL_ADVANCED'),'level'),
			\Twist::Database()->count(sprintf('%susers',TWIST_DATABASE_TABLE_PREFIX),\Twist::framework()->setting('USER_LEVEL_MEMBER'),'level')
		);

		return $this->_view('pages/dashboard.tpl',$arrTags);
	}

	/**
	 * Overview of the TwistPHP cache system with the ability to clear out cache data so that it must be re-generated.
	 * @return string
	 */
	public function cache(){

		$arrTags = array();
		$this->parseCache(TWIST_APP_CACHE);

		foreach($this->arrCacheFiles as $strKey => $arrData){
			$arrTags['cache'] .= $this->_view('components/cache/each-file.tpl',$arrData);
		}

		return $this->_view('pages/cache.tpl',$arrTags);
	}

	var $arrCacheFiles = array();

	/**
	 * Run through all the cache files and build up a list of what has been cached
	 * @param $strCacheFolder
	 */
	protected function parseCache($strCacheFolder){

		foreach(scandir($strCacheFolder) as $strEachCache){
			if(!in_array($strEachCache,array('.','..','.htaccess'))){

				$strCurrentItem = sprintf('%s/%s',rtrim($strCacheFolder,'/'),$strEachCache);
				$strCacheKey = str_replace(TWIST_APP_CACHE,'',rtrim($strCacheFolder,'/'));

				if(is_dir($strCurrentItem)){
					$this->parseCache($strCurrentItem);
				}else{
					$this->arrCacheFiles[$strCacheKey]['key'] = $strCacheKey;
					$this->arrCacheFiles[$strCacheKey]['files']++;
					$this->arrCacheFiles[$strCacheKey]['size'] += filesize($strCurrentItem);
				}
			}
		}
	}

	/**
	 * HTaccess manager to all the editing of browser cache settings, rewrite rules and default host name redirects such as using www. or not and forcing https.
	 * @return string
	 */
	public function htaccess(){

		$arrTags = array('rewrite_rules' => '');

		$arrRewrites = json_decode(\Twist::framework()->setting('HTACCESS_REWRITES'),true);

		if(count($arrRewrites)){
			foreach($arrRewrites as $arrEachRewrite){
				$arrTags['rewrite_rules'] .= $this->_view('components/htaccess/rewrite-rule.tpl',$arrEachRewrite);
			}
		}

		return $this->_view('pages/htaccess.tpl',$arrTags);
	}

	public function postHtaccess(){

		\Twist::framework()->setting('SITE_WWW',$_POST['SITE_WWW']);
		\Twist::framework()->setting('SITE_PROTOCOL',$_POST['SITE_PROTOCOL']);
		\Twist::framework()->setting('SITE_PROTOCOL_FORCE',$_POST['SITE_PROTOCOL_FORCE']);
		\Twist::framework()->setting('SITE_DIRECTORY_INDEX',$_POST['SITE_DIRECTORY_INDEX']);

		\Twist::framework()->setting('HTACCESS_CACHE_HTML',$_POST['HTACCESS_CACHE_HTML']);
		\Twist::framework()->setting('HTACCESS_REVALIDATE_HTML',(array_key_exists('HTACCESS_REVALIDATE_HTML',$_POST)) ? '1' : '0');

		\Twist::framework()->setting('HTACCESS_CACHE_CSS',$_POST['HTACCESS_CACHE_CSS']);
		\Twist::framework()->setting('HTACCESS_REVALIDATE_CSS',(array_key_exists('HTACCESS_REVALIDATE_CSS',$_POST)) ? '1' : '0');

		\Twist::framework()->setting('HTACCESS_CACHE_JS',$_POST['HTACCESS_CACHE_JS']);
		\Twist::framework()->setting('HTACCESS_REVALIDATE_JS',(array_key_exists('HTACCESS_REVALIDATE_JS',$_POST)) ? '1' : '0');

		\Twist::framework()->setting('HTACCESS_CACHE_IMAGES',$_POST['HTACCESS_CACHE_IMAGES']);
		\Twist::framework()->setting('HTACCESS_REVALIDATE_IMAGES',(array_key_exists('HTACCESS_REVALIDATE_IMAGES',$_POST)) ? '1' : '0');

		\Twist::framework()->setting('HTACCESS_ETAG',(array_key_exists('HTACCESS_ETAG',$_POST)) ? '1' : '0');

		\Twist::framework()->setting('HTACCESS_DEFLATE_HTML',(array_key_exists('HTACCESS_DEFLATE_HTML',$_POST)) ? '1' : '0');
		\Twist::framework()->setting('HTACCESS_DEFLATE_CSS',(array_key_exists('HTACCESS_DEFLATE_CSS',$_POST)) ? '1' : '0');
		\Twist::framework()->setting('HTACCESS_DEFLATE_JS',(array_key_exists('HTACCESS_DEFLATE_JS',$_POST)) ? '1' : '0');
		\Twist::framework()->setting('HTACCESS_DEFLATE_IMAGES',(array_key_exists('HTACCESS_DEFLATE_IMAGES',$_POST)) ? '1' : '0');

		$arrTags = array('rewrite_rules' => '');
		$arrRewriteRules = array();

		foreach($_POST['rewrite'] as $intKey => $strRewriteURI){
			if(array_key_exists($intKey,$_POST['rewrite-redirect']) && array_key_exists($intKey,$_POST['rewrite-options']) && $strRewriteURI != '' && $_POST['rewrite-redirect'][$intKey] != ''){

				$arrRewriteRules[] = array('rule' => $strRewriteURI,'redirect' => $_POST['rewrite-redirect'][$intKey],'options' => $_POST['rewrite-options'][$intKey]);
				$arrTags['rewrite_rules'] .= sprintf("\tRewriteRule %s %s [%s]\n",$strRewriteURI,$_POST['rewrite-redirect'][$intKey],$_POST['rewrite-options'][$intKey]);
			}
		}

		\Twist::framework()->setting('HTACCESS_REWRITES',json_encode($arrRewriteRules));
		\Twist::framework()->setting('HTACCESS_CUSTOM',$_POST['HTACCESS_CUSTOM']);

		/**
		 * Update the .htaccess file to be a TwistPHP htaccess file
		 */
		$dirHTaccessFile = sprintf('%s/.htaccess',TWIST_PUBLIC_ROOT);
		file_put_contents($dirHTaccessFile,$this->_view(sprintf('%s/default-htaccess.tpl',TWIST_FRAMEWORK_VIEWS),$arrTags));

		return $this->htaccess();
	}

	/**
	 * An overview of all the settings in the TwistPHP Settings table, from here all settings can be updated as necessary.
	 * @return string
	 */
	public function settings(){

		if(array_key_exists('import',$_GET) && $_GET['import'] == 'core'){
			$resSetup = new Setup();
			$resSetup->importSettings(sprintf('%ssettings.json',TWIST_FRAMEWORK_INSTALL));
			\Twist::redirect('./settings');
		}

		$arrSettings = \Twist::framework() -> settings() -> arrSettingsInfo;
		$arrOption = array();

		foreach($arrSettings as $arrEachItem){

			$arrEachItem['input'] = '';

			if($arrEachItem['type'] === 'string'){
				$arrEachItem['input'] .= sprintf('<input type="text" name="settings[%s]" value="%s">',$arrEachItem['key'],$arrEachItem['value']);
			}elseif($arrEachItem['type'] === 'boolean'){
				$arrEachItem['input'] .= sprintf('<input type="checkbox" name="settings[%s]" %svalue="1">',$arrEachItem['key'],($arrEachItem['value'] == '1') ? 'checked ' : '');
			}elseif($arrEachItem['type'] === 'options'){

				$strOptions = '';
				$arrOptions = explode(',',$arrEachItem['options']);

				if(count($arrOptions) <= 3){
					foreach($arrOptions as $strEachOption){
						$strChecked = (trim($strEachOption) == $arrEachItem['value']) ? ' checked': '';
						$strOptionKey = sprintf('%s-%s',$arrEachItem['key'],trim($strEachOption));
						$arrEachItem['input'] .= sprintf('<input type="radio" id="settings_%s" name="settings[%s]" value="%s"%s><label for="settings_%s">%s</label>',$strOptionKey,$arrEachItem['key'],trim($strEachOption),$strChecked,$strOptionKey,trim($strEachOption));
					}
				}else{
					foreach($arrOptions as $strEachOption){
						$strChecked = (trim($strEachOption) == $arrEachItem['value']) ? 'selected ': '';
						$strOptions .= sprintf('<option %svalue="%s">%s</option>',$strChecked,trim($strEachOption),trim($strEachOption));
					}
					$arrEachItem['input'] .= sprintf('<select name="settings[%s]">%s</select>',$arrEachItem['key'],$strOptions);
				}

			}elseif($arrEachItem['type'] === 'integer'){
				$arrEachItem['input'] .= sprintf('<input type="text" name="settings[%s]" value="%s">',$arrEachItem['key'],$arrEachItem['value']);
			}else{
				//Unknown types
				$arrEachItem['input'] .= sprintf('<input type="text" name="settings[%s]" value="%s">',$arrEachItem['key'],$arrEachItem['value']);
			}

			//Output the original settings in hidden inputs
			$arrEachItem['input'] .= sprintf('<input type="hidden" name="original[%s]" value="%s">',$arrEachItem['key'],$arrEachItem['value']);

			$arrOption[$arrEachItem['package']] .= $this->_view('components/settings/each-setting.tpl', $arrEachItem );
		}

		$arrTags = array();
		foreach($arrOption as $strKey => $strList){

			//if($strKey != 'Core'){
			$arrListTags = array('title' => $strKey, 'list' => $strList);
			$arrTags['settings'] .= $this->_view('components/settings/group.tpl', $arrListTags );
			//}
		}

		return $this->_view('pages/settings.tpl',$arrTags);
	}

	/**
	 * Store all the setting changes POST'ed  form the settings page.
	 */
	public function postSettings(){

		$arrSettingsInfo = \Twist::framework()->settings()->arrSettingsInfo;

		if(array_key_exists('settings',$_POST) && count($_POST['settings']) && count($_POST['original'])){
			foreach($_POST['original'] as $strKey => $strValue){
				if(array_key_exists($strKey,$_POST['settings'])){
					//Store the new setting
					\Twist::framework() ->setting($strKey,$_POST['settings'][$strKey]);
				}else{
					//Store '0' as we can consider this an unchecked checkbox
					if($arrSettingsInfo[$strKey]['type'] === 'boolean'){
						\Twist::framework() ->setting($strKey,0);
					}
				}
			}
			$arrTags['message'] = '<p class="success">You new module settings were saved successfully</p>';
			//$arrSettings = \Twist::framework() -> settings() -> cache();
		}

		\Twist::redirect('./settings');
	}

	/**
	 * Allow a select few settings to be updated using GET parameters, these are settings that are displayed as buttons throughout the manager.
	 */
	public function getUpdateSetting(){

		$arrAllowedSettings = array('DEVELOPMENT_MODE','MAINTENANCE_MODE','DEVELOPMENT_DEBUG_BAR','CACHE_ENABLED');

		if(array_key_exists('setting',$_GET) && array_key_exists('setting_value',$_GET) && in_array($_GET['setting'],$arrAllowedSettings)){
			\Twist::framework() ->setting($_GET['setting'],$_GET['setting_value']);
		}

		\Twist::redirect('./dashboard');
	}

	/**
	 * Display all the installed and un-installed packages that are currently in your packages folder. The page does not currently have an APP store feature.
	 * @return string
	 */
	public function packages(){

		$arrTags = array();
		$arrModules = \Twist::framework()->package()->getAll();

		$arrTags['packages_installed'] = '';
		$arrTags['packages_available'] = '';

		foreach($arrModules as $arrEachModule){

			if(array_key_exists('name',$arrEachModule)){

				if(array_key_exists('installed',$arrEachModule)){
					$arrTags['packages_installed'] .= $this->_view('components/packages/each-installed.tpl',$arrEachModule);
				}else{
					$arrTags['packages_available'] .= $this->_view('components/packages/each-available.tpl',$arrEachModule);
				}
			}
		}

		if($arrTags['packages_installed'] === ''){
			$arrTags['packages_installed'] = '<tr><td colspan="6">No packages installed</td></tr>';
		}

		if($arrTags['packages_available'] === ''){
			$arrTags['packages_available'] = '<tr><td colspan="4">No packages to install</td></tr>';
		}

		return $this->_view('pages/packages.tpl',$arrTags);
	}

	/**
	 * Install a package into the system, pass the package slug in the GET param 'package'.
	 */
	public function install(){

		//Run the package installer
		if(array_key_exists('package',$_GET)){
			\Twist::framework()->package()->installer($_GET['package']);
		}

		\Twist::redirect('./packages');
	}

	/**
	 * Uninstall a package from the system, pass the package slug in the GET param 'package'.
	 */
	public function uninstall(){

		//Run the package installer
		if(array_key_exists('package',$_GET)){
			\Twist::framework()->package()->uninstaller($_GET['package']);
		}

		\Twist::redirect('./packages');
	}
}