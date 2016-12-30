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
 * @copyright $Date: 2014-09-01 01:24:03 -0300 (Mon, 01 Sep 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package HH_Object
 */

/**
 * Description of ActionHelper
 *
 * @package   HH_Object
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ActionHelper.php 798 2014-09-01 04:24:03Z farmnik $
 * @copyright $Date: 2014-09-01 01:24:03 -0300 (Mon, 01 Sep 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Object_ActionHelper extends Zend_Controller_Action_Helper_Abstract
{
    protected $_name = null;
    
    public function __construct($name)
    {
        $this->_name = $name;
    }


    public function getName()
    {
        return $this->_name;
    }

    public function getViewScript($collection = true, $type = '')
    {
        $class = explode('_', get_called_class());

        array_shift($class);
        array_shift($class);
        array_pop($class);

        foreach ($class as $key => &$value) {
            $value = strtolower($value);

            if ($collection) {
                if (substr($value, -1, 1) == 'y') {
                    $value = substr($value, 0, -1) . 'ies';
                } else {
                    $value .= 's';
                }
            }
        }

        $script = implode('-', $class);

        if (!empty($type)) {
            $script .= '.' . $type;
        }

        return $script;
    }

    public function getObjectClass()
    {
        $class = explode('_', get_called_class());

        array_pop($class);

        return implode('_', $class);
    }
}
