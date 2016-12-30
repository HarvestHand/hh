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
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package HH_View
 */

/**
 * Description of ToForm
 *
 * @package   HH_View
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: F.php 329 2011-09-27 01:14:40Z farmnik $
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_F extends Zend_View_Helper_Abstract
{
    /**
     * Current input name
     * 
     * @var array
     */
    protected $_name = array();
    
    /**
     * Array of default vars for form elements
     * 
     * @var array
     */
    protected $_defaultVars = null;
    
    public function F()
    {
        $name = func_get_args();
        $nameSize = count($name);
        
        if ($nameSize != 0) {
        
            if ($nameSize == 1 && $name[0] === null) {
                $this->_name = array();
            } else {
                $this->_name = $name;
            }
        }
        
        return $this;
    }
    
    public function errorHeader()
    {
        $return = '<div id="msg_error"';
        
        if ($this->isError()) {
            
            $ieVersion = false;
            
            if (isset($_SERVER['HTTP_USER_AGENT'])) {
                preg_match(
                    '/MSIE ([0-9]).([0-9]{1,2})/i',
                    $_SERVER['HTTP_USER_AGENT'], 
                    $rawVersion
                );
                
                if (!empty($rawVersion[1])) {
                    $ieVersion = $rawVersion[1];
                }
                
            }

            if ($ieVersion == 7) {                
                $return .= ' style="display: block;"';
            } else {
                $return .= ' style="display: table;"';
            }
            
        }
            
        $return .= '>';
        $return .= '<div class="icon icon-error"></div>';
        $return .= '<p>';
        
        if (!empty($this->view->msg_error)) {
            $return .= $this->view->escape($this->view->msg_error);
        } else {
            $return .= $this->view->translate(
                'There are errors in your form submission,' 
                . ' please see below for details.'
            );
        }
        $return .= '</p>';
        $return .= '</div>';
        
        return $return;
    }
    
    public function label($label, $title = null, $required = false)
    {
        $return = '<label for="' . implode('_', $this->_name) . '" ' 
            . 'title="' . $this->view->escape($title) . '">' 
            . $this->view->escape($label);
        
        if ($required) {
            $return .= ' <span class="form_required">*</span>';
        }
        
        $return .= '</label>';
        
        return $return;
    }
    
    public function inputError()
    {
        if (call_user_func(array($this, 'isError'))) {
            return '<label class="error" for="' . implode('_', $this->_name) 
                . '" style="display: block;">' 
                . $this->view->escape(
                    call_user_func(
                        array($this, 'inputErrorMessage')
                    )
                ) . '</label>';
        }
    }
    
    public function inputClass($class = '')
    {
        if (call_user_func(array($this, 'isError'))) {
            return $class . ' error';
        }
        
        return $class;
    }
    
    public function inputErrorMessage()
    {
        $arrayStruct = '';
        
        foreach ($this->_name as $key) {
            $arrayStruct .= '[\'' . $key . '\']';
        }
        
        if (!empty($arrayStruct)) {
            return implode(
                '; ',
                eval(
                    'return $this->view->errors' . $arrayStruct . ';'
                )
            );          
        }
    }
    
    public function isError()
    {
        if (empty($this->_name)) {
            return (!empty($this->view->errors));
        }
        
        $arrayStruct = '';
        
        foreach ($this->_name as $key) {
            $arrayStruct .= '[\'' . $key . '\']';
        }
        
        if (!empty($arrayStruct)) {
            return eval(
                'return (!empty($this->view->errors' . $arrayStruct . '));'
            );            
        }
        
        return false;
    }
    
    public function inputValue($default = null)
    {
        $nameCount = count($this->_name);
        
        $defaultVarPointer =& $this->_defaultVars;
        $getVarPointer =& $_GET;
        $postVarPointer =& $_POST;
        
        for ($count = 0; $count < $nameCount; ++$count) {
            $key = $this->_name[$count];
            
            if (($count + 1) == $nameCount) {
                // last stop, check for value
                if (is_array($postVarPointer) 
                    && array_key_exists($key, $postVarPointer)) {

                    if (!is_array($postVarPointer[$key])) {
                        return $this->view->escape($postVarPointer[$key]);
                    } else {
                        return $postVarPointer[$key];
                    }
                } else if (is_array($getVarPointer)
                    && array_key_exists($key, $getVarPointer)) {

                    if (!is_array($getVarPointer[$key])) {
                        return $this->view->escape($getVarPointer[$key]);
                    } else {
                        return $getVarPointer[$key];
                    }
                } else if (is_array($defaultVarPointer) 
                    && array_key_exists($key, $defaultVarPointer)) {

                    if (!is_array($defaultVarPointer[$key])) {
                        return $this->view->escape($defaultVarPointer[$key]);
                    } else {
                        return $defaultVarPointer[$key];
                    }
                }
            } else {
                // move pointers
                if (is_array($defaultVarPointer) 
                    && array_key_exists($key, $defaultVarPointer)) {

                    $defaultVarPointer =& $defaultVarPointer[$key];
                }
                
                if (is_array($getVarPointer) 
                    && array_key_exists($key, $getVarPointer)) {

                    $getVarPointer =& $getVarPointer[$key];
                }
                
                if (is_array($postVarPointer) 
                    && array_key_exists($key, $postVarPointer)) {

                    $postVarPointer =& $postVarPointer[$key];
                }
            }
        }
        
        return $default;
    }
    
    public function setDefaulVars($vars, $merge = true)
    {
        if (is_array($this->_defaultVars) && $merge) {
            $this->_defaultVars = array_merge($this->_defaultVars, $vars);
        } else {
        
            $this->_defaultVars = $vars;
        }
    }

    public function clearDefaultVars()
    {
        $this->_defaultVars = null;
    }
    
    /**
     * Convert HH model object to form markup
     * 
     * @param HH_Object $var
     * @param string $form Form to load
     * @param array $params
     * @return string
     */
    public function toForm(HH_Object $object, $form = 'default',
        $params = array())
    {
        return $object->toForm($this->view, $form, $params);
    }
}