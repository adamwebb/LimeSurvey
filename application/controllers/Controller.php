<?php
/*
 * LimeSurvey
 * Copyright (C) 2007-2011 The LimeSurvey Project Team / Carsten Schmitz
 * All rights reserved.
 * License: GNU/GPL License v2 or later, see LICENSE.php
 * LimeSurvey is free software. This version may have been modified pursuant
 * to the GNU General Public License, and as distributed it includes or
 * is derivative of works licensed under the GNU General Public License or
 * other free or open source software licenses.
 * See COPYRIGHT.php for copyright notices and details.
 *
 */

namespace ls\controllers;
use \Yii;
abstract class Controller extends \CController
{
    /**
     * @var Array of active menus, keys are the menu names, values will be passed to the view.
     */
    public $menus = [];
    
    /**
     * This array contains the survey / group / question id used by the menu widget.
     * @var array
     */
    public $navData = array();
	/**
	 * Basic initialiser to the base controller class
	 *
	 * @access public
	 * @param string $id
	 * @param CWebModule $module
	 * @return void
	 */
	public function __construct($id, $module = null)
	{
		parent::__construct($id, $module);

        Yii::app()->session->init();
		$this->loadLibrary('LS.LS');
        $this->loadHelper('globalsettings');
		$this->loadHelper('common');
		$this->loadHelper('expressions.em_manager');
		$this->loadHelper('replacements');
		$this->_init();
	}

    public function accessRules() {
        return array_merge([
            ['allow', 'roles' => ['superadmin']],
            ['deny']
        ], parent::accessRules());
    }
	
	/**
	 * Loads a helper
	 *
	 * @access public
	 * @param string $helper
	 * @return void
	 */
	public function loadHelper($helper)
	{
		Yii::app()->loadHelper($helper);
	}

	/**
	 * Loads a library
	 *
	 * @access public
	 * @param string $helper
	 * @return void
	 */
	public function loadLibrary($library)
	{
		Yii::app()->loadLibrary($library);
	}

	protected function _init()
	{
		// Check for most necessary requirements
		// Now check for PHP & db version
		// Do not localize/translate this!

		$dieoutput='';
		if (version_compare(PHP_VERSION, '5.3.0', '<'))
			$dieoutput .= 'This script can only be run on PHP version 5.3.0 or later! Your version: '.PHP_VERSION.'<br />';

		if (!function_exists('mb_convert_encoding'))
			$dieoutput .= "This script needs the PHP Multibyte String Functions library installed: See <a href='http://manual.limesurvey.org/wiki/Installation_FAQ'>FAQ</a> and <a href='http://de.php.net/manual/en/ref.mbstring.php'>PHP documentation</a><br />";

		if ($dieoutput != '')
			throw new CException($dieoutput);

   		if (ini_get("max_execution_time") < 1200) @set_time_limit(1200); // Maximum execution time - works only if safe_mode is off
        if ((int)substr(ini_get("memory_limit"),0,-1) < (int) Yii::app()->getConfig('memory_limit')) @ini_set("memory_limit",Yii::app()->getConfig('memory_limit').'M'); // Set Memory Limit for big surveys

		// The following function (when called) includes FireBug Lite if true
		defined('FIREBUG') or define('FIREBUG' , Yii::app()->getConfig('use_firebug_lite'));

		//Every 50th time clean up the temp directory of old files (older than 1 day)
		//depending on the load the  probability might be set higher or lower
		if (rand(1,50)==1)
		{
			cleanTempDirectory();
		}

		//GlobalSettings Helper
		Yii::import("application.helpers.globalsettings");

		enforceSSLMode();// This really should be at the top but for it to utilise getGlobalSetting() it has to be here

        if (Yii::app()->getConfig('debug')==1) {//For debug purposes - switch on in config.php
            @ini_set("display_errors", 1);
            error_reporting(E_ALL);
        }
        elseif (Yii::app()->getConfig('debug')==2) {//For debug purposes - switch on in config.php
            @ini_set("display_errors", 1);
            error_reporting(E_ALL | E_STRICT);
        }
        else {
            @ini_set("display_errors", 0);
            error_reporting(0);
        }
        
		//SET LOCAL TIME
		$timeadjust = Yii::app()->getConfig("timeadjust");
		if (substr($timeadjust,0,1)!='-' && substr($timeadjust,0,1)!='+') {$timeadjust='+'.$timeadjust;}
		if (strpos($timeadjust,'hours')===false && strpos($timeadjust,'minutes')===false && strpos($timeadjust,'days')===false)
		{
			Yii::app()->setConfig("timeadjust",$timeadjust.' hours');
		}
        
        Yii::app()->setConfig('adminimageurl', Yii::app()->getConfig('styleurl').Yii::app()->getConfig('admintheme').'/images/');
        Yii::app()->setConfig('adminstyleurl', Yii::app()->getConfig('styleurl').Yii::app()->getConfig('admintheme').'/');
	}

    /**
     * Creates an absolute URL based on the given controller and action information.
     * @param string $route the URL route. This should be in the format of 'ControllerID/ActionID'.
     * @param array $params additional GET parameters (name=>value). Both the name and value will be URL-encoded.
     * @param string $schema schema to use (e.g. http, https). If empty, the schema used for the current request will be used.
     * @param string $ampersand the token separating name-value pairs in the URL.
     * @return string the constructed URL
     */
    public function createAbsoluteUrl($route,$params=array(),$schema='',$ampersand='&')
    {
        $sPublicUrl=Yii::app()->getConfig("publicurl");
        // Control if public url are really public : need scheme and host
        // If yes: use it 
        $aPublicUrl=parse_url($sPublicUrl);
        if(isset($aPublicUrl['scheme']) && isset($aPublicUrl['host']))
        {
            $url=parent::createAbsoluteUrl($route,$params,$schema,$ampersand);
            $sActualBaseUrl=Yii::app()->getBaseUrl(true);
            if (substr($url, 0, strlen($sActualBaseUrl)) == $sActualBaseUrl) {
                $url = substr($url, strlen($sActualBaseUrl));
            }
            return trim($sPublicUrl,"/").$url;
        }
        else 
            return parent::createAbsoluteUrl($route,$params,$schema,$ampersand);
    }
    /**
     * Base implementation for load model.
     * Should be overwritten if the model for the controller is not standard or
     * has no single PK.
     * @param type $id
     * @return type
     * @throws \CHttpException
     */
    protected function loadModel($id) {
        // Get the model name.
        $modelClass = substr(get_class($this), 0, -strlen('sController'));
        if (class_exists($modelClass)) {
            $model = $modelClass::model()->findByPk($id);
            if (!isset($model)) {
                throw new \CHttpException(404, $modelClass . " not found.");
            }
            return $model;
        } else {
            throw new \Exception("Override loadModel when using a non standard class.");
        }
        
    }

    public function getActionParams()
    {
        $psr7 = App()->request->psr7;

        return array_merge($psr7->getQueryParams(), $psr7->getParsedBody());
    }


    /**
     * Render as json when ajax request AND preferred response type is json.
     */
    public function render($view, $data = null, $return = false)
    {
        if (App()->request->isAjaxRequest
            && App()->request->preferredAcceptType['type'] == 'application'
            && App()->request->preferredAcceptType['subType'] == 'json'
        ) {
            $json = [
                'data' => $data,
                'alerts' => App()->user->getFlashes()
            ];
            echo json_encode($json, JSON_PRETTY_PRINT);
        } else {
            return parent::render($view, $data, $return); // TODO: Change the autogenerated stub
        }
    }

    public function filters() {
        return ['accessControl'];
    }


}