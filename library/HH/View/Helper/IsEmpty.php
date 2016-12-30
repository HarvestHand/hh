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
 * @package   HH_View
 */

/**
 * Validates if something is empty
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: IsEmpty.php 329 2011-09-27 01:14:40Z farmnik $
 * @package   HH_View
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_IsEmpty extends Zend_View_Helper_Abstract
{

    /**
     * check if var is, or series of vars are, empty
     *
     * @param mixed $var
     * @return boolean
     */
    public function isEmpty()
    {
        foreach (func_get_args() as $arg) {
            if (empty($arg)) {
                return true;
            }
        }
        
        return false;
    }
}