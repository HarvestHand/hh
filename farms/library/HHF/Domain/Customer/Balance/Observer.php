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
 * @copyright $Date: 2012-09-04 22:17:37 -0300 (Tue, 04 Sep 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of Observer
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Observer.php 574 2012-09-05 01:17:37Z farmnik $
 * @copyright $Date: 2012-09-04 22:17:37 -0300 (Tue, 04 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Balance_Observer implements HH_Observer
{

    /**
     * Update observer
     *
     * @param HH_Observer_Subject $subject Subject being observed
     * @param HH_Observer_Event|null $event Event type
     */
    public function update (HH_Observer_Subject $subject,
        HH_Observer_Event $event = null)
    {
        if ($event->getEvent() == 'DELETE') { 
            $preEventData = $event->getPreEventData();
            
            $customerId = (!empty($preEventData['customerId'])) ? $preEventData['customerId'] : null;
        } else {
            $customerId = $subject['customerId'];
        }
        
        if (!empty($customerId)) {
            $customer = new HHF_Domain_Customer(
                $subject->getFarm(),
                $customerId
            );
            
            if (!$customer->isEmpty()) {
                
                $balance = (float) $customer->getBalanceTotal();
                
                $customer->update(
                    array('balance' => $balance)
                );
            }
        }
    }
}