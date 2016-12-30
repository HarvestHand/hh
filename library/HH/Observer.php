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
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Observer
 */

/**
 * Description of Observer
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Observer.php 302 2011-08-03 22:26:55Z farmnik $
 * @package   HH_Observer
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
interface HH_Observer
{
    /**
     * Update observer
     *
     * @param HH_Observer_Subject $subject Subject being observed
     * @param HH_Observer_Event|null $event Event type
     */
    public function update (HH_Observer_Subject $subject,
        HH_Observer_Event $event = null);
}