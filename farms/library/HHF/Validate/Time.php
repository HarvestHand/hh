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
 * @copyright $Date: 2011-10-12 22:39:02 -0300 (Wed, 12 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Farm_Validate
 */

/**
 * Description of Time
 *
 * @package   HH_Farm_Validate
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Time.php 334 2011-10-13 01:39:02Z farmnik $
 * @copyright $Date: 2011-10-12 22:39:02 -0300 (Wed, 12 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Validate_Time extends Zend_Validate_Abstract
{
    public function isValid($value)
    {
        try {
            if (Zend_Date::isDate($value, 'HH:mm')) {
                return true;
            }

        } catch (Exception $e) {
            return false;
        }

        return false;
    }
}