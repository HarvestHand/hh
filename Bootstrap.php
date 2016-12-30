<?php
/**
 * Harvest Hand
 *
 * LICENSE
 *
 * This source file is subject to the GNU General Public License Version 3
 * that is bundled with this package in the file LICENSE.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/gpl-3.0.html
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to farmnik@harvesthand.com so we can send you a copy immediately.
 *
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */

/**
 * Bootstrap
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @category  Core
 * @package   Bootstrap
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Bootstrap
{
    /**
     * Install root path
     * @var string
     */
    public static $root;

    /**
     * Path to farm version
     * @var string
     */
    public static $farmRoot;

    /**
     * File system path to public HTML dir
     * @var string
     */
    public static $public;

    /**
     * Path to root level modules
     * @var string
     */
    public static $modules;

    /**
     * Path to farm level modules
     * @var string
     */
    public static $farmModules;

    /**
     * Path to root level library
     * @var string
     */
    public static $library;

    /**
     * Path to farm level library
     * @var string
     */
    public static $farmLibrary;
    public static $bootstrapArgs;

    /**
     * Do we have a valid farm?
     * @var boolean
     */
    public static $farm = false;

    public static $planet = false;

    public static $dav = false;

    /**
     * Valid TLDs
     * @var array
     */
    public static $domains = array(
        'harvesthand.com' => true,
        'harvesthand-dev.com' => true, 'harvesthand-demo.com' => true, 'harvesthand.duckdns.org' => true, // TODO: remove this.
        'hhint.com' => true
    );

    /**
     * Current domain
     * @var string
     */
    public static $domain;

    /**
     * Current TLD
     * @var string
     */
    public static $rootDomain = false;

    /**
     * Bootstrap for unit tests
     * @var boolean
     */
    public static $runTests = false;

    /**
     * Current locale
     * @var string
     */
    public static $locale = 'en_CA';

    /**
     * Current environment (prod or dev)
     * @var string
     */
    public static $env = 'production';

    /**
     * Development related runtime data
     * @var array
     */
    public static $development = array();

    /**
     * Initialize bootstrapper
     *
     * @staticvar boolean $ran
     * @return null
     */
    public static function init()
    {
        static $ran = false;

        if ($ran) {
            return;
        }

        // define constants
        if (PHP_SAPI != 'cli') {
            self::_parseRequest();
        }
        self::_initVars();
        self::_initInfrastructure();
        if (PHP_SAPI != 'cli') {
            self::_initMvc();
        }

        $ran = true;
    }

    /**
     * Parse request initializing enviroment variables
     */
    protected static function _parseRequest(){
        // Taking 'index.php' out of the script name.
        //TODO: Find out if $request is ever actually used (not much memory/time being wasted but this executes every
        // single time a page loads...)
        $request = explode('/', str_replace(str_replace('/index.php', '', $_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_URL']), 4);

        // Stripping 'www.' out of the URI and any other un-applicable suffixes.
        $domain = strtolower($_SERVER['HTTP_HOST']);

        if(strpos($domain, 'www.') === 0){
            $domain = substr($domain, 4);
        } else if(strpos($domain, 'static.') === 0){
            $domain = substr($domain, 7);
        } else if(strpos($domain, 'planet.') === 0){
            $domain = substr($domain, 7);
            self::$planet = true;
        } else if(strpos($domain, 'dav.') === 0){
            $domain = substr($domain, 4);
            self::$dav = true;
        }

        // Checks the array of valid TLDs for the cleaned, and current URI.
        if(array_key_exists($domain, self::$domains)){
            self::$domain = 'www.' . $domain;
            self::$rootDomain = $domain;

            return;
        }

        // explode() returns an array of the remaining suffixes and list() is assigning the values of the elements
        // (in the linear fashion you would expect) to it's parameters.
        // http://php.net/manual/en/function.list.php
        list($subDomain, $domain) = explode('.', $domain, 2);

        // Checking the latter domain (parent) is in the array of valid domains again.
        if(!array_key_exists($domain, self::$domains)){

            self::$domain = $subDomain . '.' . $domain;

            //TODO: Why is this here? Must be used to trigger the landing page? ... Move to a constant...
            self::$farm = '_TEST_DOMAIN_';

            return;
        } else{
            self::$rootDomain = $domain;
        }

        self::$domain = $subDomain . '.' . $domain;
        self::$farm = $subDomain;
    }

    /**
     * Initialize environmental variables
     */
    protected static function _initVars(){
        $env = getenv('APPLICATION_ENV');

        if(!empty($env)){
            self::$env = $env;
        }
        self::$root = dirname(__FILE__) . '/';
        self::$public = self::$root . 'public/';
        self::$library = self::$root . 'library/';
        self::$modules = self::$root . 'modules/';

        if(self::$env == 'development'){
            self::$development['timer'] = microtime(true) * 1000;
        }

        self::$farmRoot = self::$root . 'farms/';
        self::$farmLibrary = self::$farmRoot . 'library/';

        if(self::$farm){
            self::$farmModules = self::$farmRoot . 'modules/';
        } else if(self::$runTests){
            self::$farmModules = self::$farmRoot . 'modules/';
        }
    }

    /**
     * Initialize error handing and auto loader infrastructure
     */
    protected static function _initInfrastructure()
    {
        ini_set('display_errors', 0);
        ini_set('error_reporting', E_ALL & ~E_STRICT);

        register_shutdown_function(array('Bootstrap', 'shutdownHandler'));

        if(self::$runTests){
            if(!self::$farm){

                $path = '.' . PATH_SEPARATOR;
                $path .= self::$library . PATH_SEPARATOR;
                $path .= get_include_path();

                set_include_path($path);
            } else{

                $path = '.' . PATH_SEPARATOR;
                $path .= self::$library . PATH_SEPARATOR;
                $path .= self::$farmLibrary . PATH_SEPARATOR;
                $path .= get_include_path();

                set_include_path($path);

            }
        } else{
            if(!self::$farm){
                set_include_path('.' . PATH_SEPARATOR . self::$library);
            } else{

                $path = '.' . PATH_SEPARATOR;
                $path .= self::$library . PATH_SEPARATOR;
                $path .= self::$farmLibrary;

                set_include_path($path);

            }
        }

        include Bootstrap::$library . 'HH/Observer/Subject.php';
        include Bootstrap::$library . 'HH/Object/Interfaces.php';
        include Bootstrap::$library . 'HH/Object.php';
        include Bootstrap::$library . 'HH/Object/Db.php';
        include Bootstrap::$library . 'HH/Error.php';

        spl_autoload_register(array('Bootstrap', 'autoloadHandler'));
        set_exception_handler(array('HH_Error', 'exceptionHandler'));
        set_error_handler(array('HH_Error', 'errorHandler'), E_ALL);

        include Bootstrap::$root . 'scripts/includes.php';

        Zend_Locale::setCache(self::getZendCache());
        self::getZendLocale();

        if(empty(self::$rootDomain)){
            self::$rootDomain = self::getZendConfig()->resources->domains->root;
        }
    }

    /**
     * get zend cache handle
     *
     * @return Zend_Cache_Core
     */
    public static function getZendCache(){
        return self::get('Zend_Cache');
    }

    /**
     * Get registry object
     *
     * @param string $resource
     * @return mixed
     */
    public static function get($resource)
    {
        if(Zend_Registry::isRegistered($resource)){
            return Zend_Registry::get($resource);
        } else{
            switch($resource){
                case 'Zend_Config' :
                    if(PHP_SAPI != 'cli'){
                        $result = apc_fetch('HH_Config');
                    } else{
                        $result = false;
                    }

                    if($result instanceof Zend_Config){
                        Zend_Registry::set($resource, $result);
                    } else{
                        Zend_Registry::set($resource, new Zend_Config_Ini(self::$root . 'config.ini', self::$env));

                        if(PHP_SAPI != 'cli'){
                            apc_store('HH_Config', Zend_Registry::get($resource));
                        }
                    }

                    break;
                case 'Zend_Mail_Transport' :
                    Zend_Registry::set($resource, new Zend_Mail_Transport_Smtp(Bootstrap::get('Zend_Config')->resources->mail->transport->host, Bootstrap::get('Zend_Config')->resources->mail->transport->toArray()));

                    Zend_Mail::setDefaultTransport(Zend_Registry::get($resource));
                    break;
                case 'Zend_Db' :

                    $db = Zend_Db::factory(Bootstrap::get('Zend_Config')->resources->db->adapter, Bootstrap::get('Zend_Config')->resources->db->params->toArray());

                    if(Bootstrap::$env == 'development' && PHP_SAPI != 'cli'){
                        $profiler = new Zend_Db_Profiler_Firebug('DB Queries');
                        $profiler->setEnabled(true);
                        $db->setProfiler($profiler);
                    }

                    Zend_Registry::set($resource, $db);
                    break;
                case 'Zend_Cache' :
                    Zend_Registry::set($resource, Zend_Cache::factory(Bootstrap::get('Zend_Config')->resources->cache->frontend->type, Bootstrap::get('Zend_Config')->resources->cache->backend->type, Bootstrap::get('Zend_Config')->resources->cache->frontend->params->toArray(), Bootstrap::get('Zend_Config')->resources->cache->backend->params->toArray()));
                    break;
                case 'Zend_Cache_File' :
                    Zend_Registry::set($resource, Zend_Cache::factory(Bootstrap::get('Zend_Config')->resources->cache->frontend->type, 'file', Bootstrap::get('Zend_Config')->resources->cache->frontend->params->toArray(), array('cache_dir' => self::$root . 'temp')));
                    break;
                case 'Zend_Translate' :

                    Zend_Translate::setCache(self::getZendCache());

                    Zend_Registry::set($resource, new HH_Translate(Bootstrap::get('Zend_Config')->resources->translate->params->toArray() + array('locale' => self::$locale)));

                    break;
                case 'Zend_Session' :
                    Zend_Session::setSaveHandler(new HH_Session());
                    $options = Bootstrap::get('Zend_Config')->resources->session->toArray();

                    if(isset($options['throw_startup_exceptions'])){
                        $options['throw_startup_exceptions'] = (int)$options['throw_startup_exceptions'];
                    }

                    if(self::$farm instanceof HH_Domain_Farm && !empty(self::$farm->domain)){

                        $options['cookie_domain'] = '.' . self::$farm->domain;
                    } else{
                        $options['cookie_domain'] = '.' . Bootstrap::$rootDomain;
                    }
                    Zend_Session::setOptions($options);
                    Zend_Session::start();
                    Zend_Registry::set($resource, 'Zend_Session');
                    break;
                case 'Zend_Log' :
                    $logger = new Zend_Log();

                    if(Bootstrap::$env == 'development' && PHP_SAPI != 'cli'){

                        $front = Zend_Controller_Front::getInstance();
                        $request = $front->getRequest();
                        $responce = $front->getResponse();

                        if($request != null && $responce != null){

                            $channel = Zend_Wildfire_Channel_HttpHeaders::getInstance();
                            $channel->setRequest($request);
                            $channel->setResponse($responce);

                            $logger->addWriter(new Zend_Log_Writer_Firebug());
                        }
                    }

                    $writter = new HH_Log_Writer_Db();

                    if(Bootstrap::$env == 'development'){
                        $writter->addFilter(Zend_Log::INFO);
                    }

                    $logger->addWriter($writter);

                    if(PHP_SAPI == 'cli'){
                        $writter = new Zend_Log_Writer_Stream('php://stderr');

                        if(Bootstrap::$env == 'development'){
                            $writter->addFilter(Zend_Log::INFO);
                        }

                        $logger->addWriter($writter);
                    }

                    Zend_Registry::set($resource, $logger);
                    break;
                case 'Zend_Navigation' :
                    Zend_Registry::set($resource, new HHF_Navigation(self::$farm, HH_Domain_Farmer::getAuthenticated()));
                    break;
                case 'Zend_Locale' :
                    Zend_Registry::set($resource, new Zend_Locale(Bootstrap::$locale));
                    break;
                case 'Zend_Currency' :
                    Zend_Registry::set($resource, new Zend_Currency(self::$locale));
                    break;
                case 'Zend_Queue' :
                    Zend_Registry::set($resource, new Zend_Queue('Db', array('name' => 'jobs', 'dbAdapter' => Bootstrap::get('Zend_Db'), 'options' => array(Zend_Db_Select::FOR_UPDATE => true))));
                    break;
                default :
                    Zend_Registry::set($resource, new $resource());
                    break;
            }
        }

        return Zend_Registry::get($resource);
    }

    /**
     * @return Zend_Locale
     */
    public static function getZendLocale()
    {
        return self::get('Zend_Locale');
    }

    /**
     * get config handle
     *
     * @return Zend_Config_Ini
     */
    public static function getZendConfig()
    {
        return self::get('Zend_Config');
    }

    /**
     * Initialize Zend MVC
     */
    protected static function _initMvc()
    {
        Zend_Controller_Action_HelperBroker::addPath(self::$library . 'HH/Controller/Action/Helper/', 'HH_Controller_Action_Helper');

        $controllerDir = (self::$farm) ? self::$farmModules . 'default/controllers' : self::$modules . 'default/controllers';

        $frontController = Zend_Controller_Front::getInstance()->returnResponse(true)->setParam('disableOutputBuffering', true)->setParam('noViewRenderer', true)->setDefaultControllerName('public')->registerPlugin(new HH_Controller_Plugin());

        if(self::$farm){
            $frontController->setControllerDirectory(array('default' => $controllerDir, 'customers' => self::$farmModules . 'customers/controllers', 'newsletter' => self::$farmModules . 'newsletter/controllers', 'shares' => self::$farmModules . 'shares/controllers', 'website' => self::$farmModules . 'website/controllers'));

            $routes = array('action' => new Zend_Controller_Router_Route(':action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'website'), array('controller' => 'public|admin|service', 'module' => 'default|customers|newsletter|shares|website')), 'module' => new Zend_Controller_Router_Route(':module/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service', 'module' => 'default|customers|newsletter|shares|website')), 'module_action' => new Zend_Controller_Router_Route(':module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service', 'module' => 'default|customers|newsletter|shares|website')), 'controller_module_action' => new Zend_Controller_Router_Route(':controller/:module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service|error', 'module' => 'default|customers|newsletter|shares|website')));

            if(Bootstrap::$farm == '_TEST_DOMAIN_'){
                Bootstrap::$farm = HH_Domain_Farm::fetchSingleByDomain(self::$domain);
            } else{
                Bootstrap::$farm = HH_Domain_Farm::fetchSingleBySubdomain(Bootstrap::$farm);
            }

            if(!empty(Bootstrap::$farm['timezone'])){
                date_default_timezone_set(Bootstrap::$farm['timezone']);
            }

            $farm = true;

        } else if(self::$planet){

            $farm = false;

            $frontController->setControllerDirectory(array('default' => self::$modules . 'planet/controllers'));

            $routes = array('action' => new Zend_Controller_Router_Route(':action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'planet'), array('controller' => 'public|admin|service', 'module' => 'planet')), 'module' => new Zend_Controller_Router_Route(':module/*', array('controller' => 'public', 'action' => 'index', 'module' => 'planet'), array('controller' => 'public|admin|service', 'module' => 'planet')), 'module_action' => new Zend_Controller_Router_Route(':module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'planet'), array('controller' => 'public|admin|service', 'module' => 'planet')), 'controller_module_action' => new Zend_Controller_Router_Route(':controller/:module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'planet'), array('controller' => 'public|admin|service', 'module' => 'planet')));

            date_default_timezone_set('America/Halifax');

        } else if(self::$dav){

            $farm = false;

            $frontController->setControllerDirectory(array('default' => self::$modules . 'dav/controllers'));

            $routes = array('action' => new Zend_Controller_Router_Route(':action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'dav'), array('controller' => 'public|admin', 'module' => 'dav')), 'module' => new Zend_Controller_Router_Route(':module/*', array('controller' => 'public', 'action' => 'index', 'module' => 'dav'), array('controller' => 'public|admin', 'module' => 'dav')), 'module_action' => new Zend_Controller_Router_Route(':module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'dav'), array('controller' => 'public|admin', 'module' => 'dav')), 'controller_module_action' => new Zend_Controller_Router_Route(':controller/:module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'dav'), array('controller' => 'public|admin', 'module' => 'dav')));

            date_default_timezone_set('America/Halifax');

        } else{

            $farm = false;

            $frontController->setControllerDirectory(array('default' => $controllerDir));

            $routes = array('action' => new Zend_Controller_Router_Route(':action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service', 'module' => 'default')), 'module' => new Zend_Controller_Router_Route(':module/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service', 'module' => 'default')), 'module_action' => new Zend_Controller_Router_Route(':module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service', 'module' => 'default')), 'controller_module_action' => new Zend_Controller_Router_Route(':controller/:module/:action/*', array('controller' => 'public', 'action' => 'index', 'module' => 'default'), array('controller' => 'public|admin|service', 'module' => 'default')));

            date_default_timezone_set('America/Halifax');
        }

        $frontController->getRouter()->removeDefaultRoutes()->addRoutes($routes);

        $layoutPath = (self::$farm) ? self::$farmRoot . 'layouts/scripts' : self::$root . 'layouts/scripts';

        Zend_Layout::startMvc(array('layoutPath' => $layoutPath,));

        if(Bootstrap::$env == 'development'){
            self::$development['memoryBaseLine'] = memory_get_peak_usage(true);
            self::$development['timerPreDispatch'] = microtime(true) * 1000;
        }

        try {

            if($farm && Bootstrap::$farm === null){
                $request = new Zend_Controller_Request_Http();

                $error = new ArrayObject(array(), ArrayObject::ARRAY_AS_PROPS);
                $error->exception = new Zend_Controller_Action_Exception('Page not found', 404);
                $error->type = Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION;
                $error->request = $request;

                $request->setParam('error_handler', $error)->setPathInfo('/error/default/error/');

                $frontController->setRequest($request);
            }

            ob_start();
            $responce = $frontController->dispatch();
            ob_end_clean();
        } catch(Exception $exception){
            ob_end_clean();
            throw $exception;
        }

        $responce->sendResponse();

        if(defined('SID')){
            Zend_Session::writeClose();
        }
    }

    /**
     * Library auto load handler
     * @param string $className
     */
    public static function autoloadHandler($className)
    {
        if(strpos($className, '\\') !== false){
            $className = str_replace('\\', '_', $className);
        }

        if(strpos($className, 'HHF_') === 0){

            require Bootstrap::$farmLibrary . str_replace('_', '/', $className) . '.php';

        } else if(strpos($className, 'HH_') === 0){

            require Bootstrap::$library . str_replace('_', '/', $className) . '.php';

        } else if(strpos($className, 'Zend') === 0){

            require Bootstrap::$library . str_replace('_', '/', $className) . '.php';

        } else if(strpos($className, 'Model_') === 0){

            $module = Zend_Controller_Front::getInstance()->getRequest()->getModuleName();

            $className = substr($className, 6);

            if(!empty(self::$farmModules)){

                require Bootstrap::$farmModules . $module . '/models/' . str_replace('_', '/', $className) . '.php';

            } else{

                require Bootstrap::$modules . $module . '/models/' . str_replace('_', '/', $className) . '.php';

            }
        } else{

            require str_replace('_', '/', $className) . '.php';

        }
    }

    /**
     * Shutdown handler
     *
     * Check for errors that might have not been caught
     */
    public static function shutdownHandler(){
        if($error = error_get_last()){
            if(isset($error['type']) && ($error['type'] == E_ERROR || $error['type'] == E_PARSE || $error['type'] == E_COMPILE_ERROR)
            ){

                HH_Error::do500($error, true);

            }
        }
    }

    /**
     * @return Zend_Currency
     */
    public static function getZendCurrency(){
        return self::get('Zend_Currency');
    }

    /**
     * @return Zend_Log
     */
    public static function getZendLog(){
        return self::get('Zend_Log');
    }

    /**
     * get db handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    public static function getZendDb(){
        return self::get('Zend_Db');
    }

    /**
     * get zend mail handle
     *
     * @return Zend_Mail_Transport_Smtp
     */
    public static function getZendMailTransport(){
        return self::get('Zend_Mail_Transport');
    }

    /**
     * get zend cache handle
     *
     * @return Zend_Cache_Core
     */
    public static function getZendCacheFile(){
        return self::get('Zend_Cache_File');
    }

    /**
     * get zend translate handle
     *
     * @return HH_Translate
     */
    public static function getZendTranslate(){
        return self::get('Zend_Translate');
    }
}
