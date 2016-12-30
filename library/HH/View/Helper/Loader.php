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
 * @copyright $Date: 2015-01-15 21:13:48 -0400 (Thu, 15 Jan 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Loader
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Loader.php 830 2015-01-16 01:13:48Z farmnik $
 * @copyright $Date: 2015-01-15 21:13:48 -0400 (Thu, 15 Jan 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_Loader extends Zend_View_Helper_Abstract
{
    const JS = 'JS';
    const CSS = 'CSS';

    /**
     * Current module, if any
     * @var string
     */
    protected $_module = null;

    /**
     * Current farm, if any
     * @var boolean
     */
    protected $_farm = null;

    /**
     * Current module controller, if any
     * @var string
     */
    protected $_controller = null;

    /**
     * Browser cache hash, change to invalidate
     * old browser cache
     *
     * @var string
     */
    public static $cacheHash = 'Z';

    /**
     * use compressed objects
     * @var boolean
     */
    protected $_compress = true;

    /**
     * loader objects
     * @var array
     */
    protected static $_objects = array(
        self::CSS => array(),
        self::JS => array()
    );

    protected static $_lastObject = array();

    /**
     * Loader
     *
     * @param type $module
     * @return HH_View_Helper_Loader
     */
    public function Loader($module = null, $controller = null, $farm = null)
    {
        if ($module !== null) {
            if ($module === true) {
                $this->_module = Zend_Controller_Front::getInstance()
                    ->getRequest()->getModuleName();
            } else {
                $this->_module = $module;
            }

            if (Bootstrap::$farm) {
                $this->_farm = true;
            }

            if ($controller !== null) {

                if ($controller === true) {
                    $this->_controller = Zend_Controller_Front::getInstance()
                        ->getRequest()->getControllerName();
                } else {
                    $this->_controller = $controller;
                }
            }
        } else {
            $this->_module = null;
            $this->_controller = null;
            $this->_farm = null;
        }

        return $this;
    }

    /**
     * Use compressed objects?
     *
     * @param boolean $compress
     * @return HH_View_Helper_Loader
     */
    public function setCompress($compress = true)
    {
        $this->_compress = $compress;

        return $this;
    }

    /**
     * Hash to add to loader path to manage browser cache
     *
     * @param string $cacheHash
     * @return HH_View_Helper_Loader
     */
    public function setCacheHash($cacheHash)
    {
        self::$cacheHash = $cacheHash;

        return $this;
    }

    /**
     * Append data to loader
     *
     * @param string $name Type of data to append
     * @param mixed $value Value to add
     * @return HH_View_Helper_Loader
     */
    public function __set($name, $value)
    {
        return $this->append($value, $name);
    }

    /**
     * Get loader HTML
     * @param string $name Type of data to append
     * @return string
     */
    public function __get($name)
    {
        return $this->toString($name);
    }

    /**
     * Append data to loader
     *
     * @param mixed $value Value to add
     * @param string $type Type of data to append
     * @return HH_View_Helper_Loader
     */
    public function append($value, $type = self::JS)
    {
        self::$_lastObject =
            self::$_objects[$type][] = $this->_buildData($value);

        return $this;
    }

    protected function _buildData($value)
    {
        if (is_array($value)) {

            $return = array();

            foreach ($value as $v) {
                $return[] = $this->_buildData($v);
            }

            return $return;
        }

        return array(
            'path' => $value,
            'module' => $this->_module,
            'controller' => $this->_controller,
            'farm' => $this->_farm
        );
    }

    /**
     * Prepend data to loader
     *
     * @param mixed $value Value to add
     * @param string $type Type of data to append
     * @return HH_View_Helper_Loader
     */
    public function prepend($value, $type = self::JS)
    {
        self::$_lastObject = $this->_buildData($value);

        array_unshift(self::$_objects[$type], self::$_lastObject);

        return $this;
    }

    public function toString($type)
    {
        switch ($type) {
            case self::JS :
                return $this->jsToString();
                break;
            case self::CSS :
                return $this->cssToString();
                break;
        }
    }

    public function jsToString()
    {
        $return = '';
        $parts = array();

        foreach (self::$_objects[self::JS] as $item) {
            if (is_array($item) && !array_key_exists('path', $item)) {

                if (!empty($parts)) {
                    $return .= $this->_jsTemplate($parts);

                    $parts = array();
                }

                $groupParts = array();

                foreach ($item as $i) {
                    $groupParts[] = $this->_buildUrlPart($i);
                }

                $return .= $this->_jsTemplate($groupParts);

            } else {
                $parts[] = $this->_buildUrlPart($item);
            }
        }

        if (!empty($parts)) {
            $return .= $this->_jsTemplate($parts);
        }

        return $return;
    }

    protected function _jsTemplate($parts)
    {
        $prefix = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';

        $prefix .= 'static.' . Bootstrap::$rootDomain . '/loader/t/j/c/';

        if ($this->_compress) {
            $prefix .= '1';
        } else {
            $prefix .= '0';
        }

        $prefix .= '/f/';

        return sprintf(
            '<script type="text/javascript" src="%s%s/h/%s.js"></script>' . "\n",
            $this->view->escape($prefix),
            $this->view->escape(implode('~', $parts)),
            self::$cacheHash
        );
    }

    public function cssToString()
    {
        $return = '';
        $parts = array();

        foreach (self::$_objects[self::CSS] as $item) {
            if (is_array($item) && !array_key_exists('path', $item)) {

                if (!empty($parts)) {
                    $return .= $this->_cssTemplate($parts);

                    $parts = array();
                }

                $groupParts = array();

                foreach ($item as $i) {
                    $groupParts[] = $this->_buildUrlPart($i);
                }

                $return .= $this->_cssTemplate($groupParts);

            } else {
                $parts[] = $this->_buildUrlPart($item);
            }
        }

        if (!empty($parts)) {
            $return .= $this->_cssTemplate($parts);
        }

        return $return;
    }

    protected function _cssTemplate($parts)
    {
        $prefix = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';

        $prefix .= 'static.' . Bootstrap::$rootDomain . '/loader/t/c/c/0';

        $prefix .= '/f/';

        return sprintf(
            '<link rel="stylesheet" href="%s%s/h/%s.css">' . "\n",
            $this->view->escape($prefix),
            $this->view->escape(implode('~', $parts)),
            self::$cacheHash
        );
    }

    protected function _buildUrlPart($item)
    {
        $path = '';

        if (!empty($item['module'])) {

            if ($item['farm']) {
                $path .= 'hhf_';
            } else {
                $path .= 'hh_';
            }

            $path .= 'modules_' . $item['module'] . '_';

            if (!empty($item['controller'])) {
                $path .= $item['controller'] . '_';
            }
        }

        return $path . $item['path'];
    }

    /**
     * Run init on last set JS object
     */
    public function init()
    {
        $path = str_replace('_', '.', $this->_buildUrlPart(self::$_lastObject));

        $html = '<script type="text/javascript">' . $path . '.init(';

        $params = array();
        $args = func_num_args();
        for ($count = 0; $count < $args; ++$count) {
            $params[] = Zend_Json::encode(func_get_arg($count));
        }

        $html .= implode(',', $params);
        $html .= ');</script>';

        $this->view->placeholder('foot')->set($html);
    }
}
