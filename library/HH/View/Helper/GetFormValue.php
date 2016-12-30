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
 * @copyright $Date: 2014-03-18 14:09:21 -0300 (Tue, 18 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_View
 */

/**
 * Description of GetFromValue
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: GetFormValue.php 729 2014-03-18 17:09:21Z farmnik $
 * @package   HH_View
 * @copyright $Date: 2014-03-18 14:09:21 -0300 (Tue, 18 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_GetFormValue extends Zend_View_Helper_Abstract
{

    protected $_defaultVars = null;

    /**
     * Get form variable POST or GET value
     *
     * @param string $var Variable name
     * @param string $group Otional variable group name
     * @return mixed
     */
    public function getFormValue($var = null, $group = null, $subGroup = null)
    {
        if ($var === null && $group === null) {
            return $this;
        }

        if ($group == null) {
            if (isset($_POST[$var])) {
                return $_POST[$var];
            } else if (isset($_GET[$var])) {
                return $_GET[$var];
            } else if ($this->_defaultVars !== null &&
                isset($this->_defaultVars[$var])) {

                return $this->_toString($var, $this->_defaultVars[$var]);
            }
        } else if ($subGroup === null) {
            if ($var === null) {
                return $this->_returnGroup($group);
            } else {

                if (isset($_POST[$group][$var])) {
                    return $_POST[$group][$var];
                } else if (isset($_GET[$group][$var])) {
                    return $_GET[$group][$var];
                } else if ($this->_defaultVars !== null &&
                    isset($this->_defaultVars[$group][$var])) {

                    return $this->_toString(
                        $var,
                        $this->_defaultVars[$group][$var]
                    );
                }
            }
        } else {
            if (isset($_POST[$group][$subGroup][$var])) {
                return $_POST[$group][$subGroup][$var];
            } else if (isset($_GET[$group][$subGroup][$var])) {
                return $_GET[$group][$subGroup][$var];
            } else if ($this->_defaultVars !== null &&
                isset($this->_defaultVars[$group][$subGroup][$var])) {

                return $this->_toString(
                    $var,
                    $this->_defaultVars[$group][$subGroup][$var]
                );
            }
        }

        return null;
    }

    public function setDefaulVars($vars)
    {
        $this->_defaultVars = $vars;
    }

    public function clearDefaultVars()
    {
        $this->_defaultVars = null;
    }

    protected function _returnGroup($group)
    {
        $return = array();

        if (isset($_POST[$group])) {
            $return  = $_POST[$group];
        } else if (isset($_GET[$group])) {
            $return = $_GET[$group];
        }  else if ($this->_defaultVars !== null &&
            isset($this->_defaultVars[$group])) {

            $return = $this->_defaultVars[$group];
        }

        return $return;
    }
    
    protected function _toString($key, $value)
    {
        if (is_object($value)) {
        
            if ($value instanceof Zend_Date) {
                
                if (stripos($key, 'datetime')) {
                    return HH_Tools_Date::dateTimeToIso($value);
                } else if (stripos($key, 'date')) {
                    return $value->toString('yyyy-MM-dd');
                }
                
                return $value->toString(Zend_Date::ISO_8601);
            }
        } else {
            return $value;
        }
    }
}
