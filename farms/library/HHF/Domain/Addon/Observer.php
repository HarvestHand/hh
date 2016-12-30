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
 * @copyright $Date: 2012-04-10 10:06:21 -0300 (Tue, 10 Apr 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of Observer
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Observer.php 508 2012-04-10 13:06:21Z farmnik $
 * @copyright $Date: 2012-04-10 10:06:21 -0300 (Tue, 10 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Addon_Observer implements HH_Observer
{
    public function update(
        \HH_Observer_Subject $subject,
        \HH_Observer_Event $event = null
    ) {

        if ($event->getEvent() != 'UPDATE') {
            return;
        }

        if (!$subject['enabled']) {
            return;
        }

        if (!is_numeric($subject['inventory'])
            || !is_numeric($subject['inventoryMinimumAlert'])) {

            return;
        }

        if ($subject['inventory'] > $subject['inventoryMinimumAlert']) {
            return;
        }

        $preEventData = $event->getPreEventData();

        if (empty($preEventData)
            || !is_numeric($preEventData['inventory'])
            || ($preEventData['inventory'] <= $subject['inventoryMinimumAlert'])) {

            return;

        }

        if ($preEventData['inventory'] == $subject['inventory']) {
            return;
        }

        $subject->getService()->sendInventoryAlert();
    }
}
