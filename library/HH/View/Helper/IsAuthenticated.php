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
 * Validates if user is authenticated
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: IsAuthenticated.php 329 2011-09-27 01:14:40Z farmnik $
 * @package   HH_View
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_IsAuthenticated extends Zend_View_Helper_Abstract
{

    /**
     * Get form variable POST or GET value
     *
     * @param string $var Variable name
     * @param string $group Otional variable group name
     * @return mixed
     */
    public function isAuthenticated()
    {
        return (HH_Domain_Farmer::getAuthenticated() == null) ? false : true;
    }
}