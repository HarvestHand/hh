<?php
/**
 * HarvestHand
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
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */

/**
 * Description of Base
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Object.php 606 2012-12-27 04:25:36Z farmnik $
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */
abstract class HH_Object extends HH_Object_Interfaces
{
    /**
     * @var HH_Object_Service
     */
    protected $_service;
    protected $_data = array();
    protected $_id = null;
    protected $_observers = array();
    protected $_defaultObservers = array();
    protected $_isEmpty = true;
    protected $_isLoaded = false;
    protected $_config = array();
    protected static $_collection = 'HH_Object_Collection';
    protected static $_staticConfig = array();
    protected static $_instances = array();

    /**
     * Base constructor
     *
     * @param string|array $id
     * @param array|null $data
     * @param array $config
     */
    public function  __construct($id = null, $data = null, $config = array())
    {
        $this->_id = $id;

        if (is_array($data) || is_object($data)) {
            $this->_setData($data);
        }

        $this->setConfig($config);

        if (!empty($this->_defaultObservers)) {
            foreach ($this->_defaultObservers as $observerClass) {
                $this->attach(new $observerClass);
            }
        }
    }

    /**
     * set object config
     *
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->_config = array_merge(
            $this->_config,
            self::$_staticConfig,
            $config
        );
    }

    /**
     * Set object config across all to be initialized object classes
     *
     * @param array $config
     */
    public static function setStaticConfig($config)
    {
        self::$_staticConfig = $config;
    }

    /**
     *
     * @return Zend_Cache_Core
     */
    protected function _getZendCache()
    {
        if (isset($this->_config['Zend_Cache'])) {
            return $this->_config['Zend_Cache'];
        }

        return Bootstrap::get('Zend_Cache');
    }

    /**
     *
     * @return Zend_Cache_Core
     */
    protected static function _getStaticZendCache()
    {
        if (isset(self::$_staticConfig['Zend_Cache'])) {
            return self::$_staticConfig['Zend_Cache'];
        }

        return Bootstrap::get('Zend_Cache');
    }

    /**
     * @return HH_Translate
     */
    protected function _getZendTranslate()
    {
        if (isset($this->_config['Zend_Translate'])) {
            $translate = $this->_config['Zend_Translate'];
        } else {
            $translate = Bootstrap::getZendTranslate();
        }

        $translate->addModuleTranslation('library');

        return $translate;
    }

    /**
     * @return HH_Translate
     */
    protected static function _getStaticZendTranslate()
    {
        if (isset(self::$_staticConfig['Zend_Translate'])) {
            $translate = self::$_staticConfig['Zend_Translate'];
        } else {
            $translate = Bootstrap::getZendTranslate();
        }

        $translate->addModuleTranslation('library');

        return $translate;
    }

    /**
     * set object data vars
     *
     * @param array $data
     */
    protected function _setData($data = array(), $insert = true)
    {
//        if ($insert) {
//            $this->_data = array();
//        }

        if (is_object($data)) {
            $data = (array) $data;
        }

        if (is_array($data)) {
            foreach ($data as $key => $value) {
                if (substr($key, -8) == 'Datetime' || substr($key, -4) == 'Date') {
                    if (!is_object($value)) {
                        $this->_data[$key] = HH_Tools_Date::isoDatetimeToUTC(
                            $value
                        );
                    } else {
                        $this->_data[$key] = $value;
                    }
                } else {
                    $this->_data[$key] = $value;
                }
            }

            if (isset($data['id']) && empty($this->_id)) {
                $this->_id = $data['id'];
            }
        }

        $this->_isEmpty = empty($this->_data);
        $this->_isLoaded = true;
    }

    /**
     * Prepare data to be entered into the database
     *
     * Add timestamps
     * Convert dates / times to proper formats
     *
     * @param array $data Data to prepare
     * @param boolean $insert Is data to be inserted (false is updated)
     * @return array
     */
    protected function _prepareData($data, $insert = true)
    {
        $this->_data['updatedDatetime'] =
            $data['updatedDatetime'] = HH_Tools_Date::dateTimeToDb();

        if (!isset($data['addedDatetime']) && $insert == true) {
            $this->_data['addedDatetime'] =
                $data['addedDatetime'] = HH_Tools_Date::dateTimeToDb();
        }

        foreach ($data as $key => $value) {
            if ($value instanceof Zend_Date) {
                $data[$key] = HH_Tools_Date::dateTimeToDb($value);
            } else if (is_object($value)) {
                $data[$key] = (string) $value;
            }
        }

        return $data;
    }

    /**
     * Reset object data
     */
    protected function _reset()
    {
        $this->_data = array();
        $this->_id = null;
        $this->_isLoaded = false;
        $this->_isEmpty = true;
    }

    /**
     * Get data (lazy loader)
     */
    abstract protected function _get();

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    abstract public function insert($data);

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    abstract public function update($data = null);

    /**
     * Delete current object
     *
     * @throws HH_Object_Exception_Id if object ID is not set
     * @return boolean
     */
    abstract public function delete();

    /**
     * Get object service layer
     *
     * @return HH_Object_Service
     */
    public function getService()
    {
        if ($this->_service === null) {
            $name = get_called_class() . '_Service';

            $this->_service = new $name($this);
        }

        return $this->_service;
    }

    /**
     * Shortcut to service methods
     *
     * @param string $name
     * @param array $arguments
     * @return mixed
     */
    public function __call($name, $arguments)
    {
        return call_user_func_array(
            array(
                $this->getService(),
                $name
            ),
            $arguments
        );
    }

    /**
     * Get Zend_Filter_Input for object
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    abstract public static function getFilter($filter = null, $options = array());

    /**
	 * Notify an observer
     * @param HH_Observer_Event $event
	 * @see HH_Observer_Subject::notify
	 * @return void
	 */
    protected function _notify(HH_Observer_Event $event = null)
    {
        foreach ($this->_observers as $observer) {
            $observer->update($this, $event);
        }
    }

    /**
     * Convert object to array
     *
     * @return array
     */
    public function toArray($deep = false)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        if ($deep) {

            return $this->_toArray($this->_data);
        }

        return $this->_data;
    }

    protected function _toArray($data)
    {
        foreach ($data as &$v) {
            if (is_object($v) && method_exists($v, 'toArray')) {
                $v = $v->toArray(true);
            } else if (is_array($v)) {
                foreach ($v as &$v2) {
                    if (is_object($v2) && method_exists($v2, 'toArray')) {
                        $v2 = $v2->toArray(true);
                    }
                }
            }
        }

        return $data;
    }

    /**
     * Convert object to json
     *
     * @return string
     */
    public function toJson()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return json_encode($this->_data);
    }

    /**
     * Check to see if object is empty
     *
     * @return boolean
     */
    public function isEmpty()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }
        return $this->_isEmpty;
    }

    public function toForm(Zend_View_Abstract $view, $form = 'default',
        $params = array())
    {
        $classParts = explode('_', get_called_class());

        $classParts[] = 'Forms';

        if ($classParts[1] == 'Farm') {
            $formsPath = Bootstrap::$farmLibrary;
        } else {
            $formsPath = Bootstrap::$library;
        }

        $formsPath .= implode('/', $classParts) . '/';
        $found = false;

        // check that object form path is set
        foreach ($view->getScriptPaths() as $path) {
            if ($path == $formsPath) {
                $found = true;
                break;
            }
        }

        if (!$found) {
            $view->addScriptPath($formsPath);
        }

        $view->assign('objectParams', $params);
        $view->assign('object', $this);

        return $view->render(strtolower($form) . '-form.phtml');
    }

    public static function singleton($id = null, $data = null, $config = array())
    {
        $class = get_called_class();

        $hash = md5($class . print_r($id, true));

        if (isset(self::$_instances[$hash])
            && self::$_instances[$hash] instanceof $class) {

            return self::$_instances[$hash];
        }

        return self::$_instances[$hash] = new $class($id, $data, $config);
    }

    public static function getCollection($data = array())
    {
        $class = get_called_class();

        $class = $class::$_collection;

        $collection = new $class($data);

        $collection->setObjectType(get_class($this));

        return $collection;
    }

    public static function getActionHelper()
    {
        $name = get_called_class();

        $class = $name . '_ActionHelper';

        return new $class(str_replace('_', '', $name));
    }
}