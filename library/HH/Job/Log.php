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
 * @copyright $Date: 2012-02-21 17:03:28 -0400 (Tue, 21 Feb 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Facebook
 *
 * @package   HH_Job 
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Log.php 442 2012-02-21 21:03:28Z farmnik $
 * @copyright $Date: 2012-02-21 17:03:28 -0400 (Tue, 21 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Log extends HH_Job
{
    public function __construct($config = array())
    {
        parent::__construct($config);
    }
    
    public function add($event, $object)
    {
        parent::add('log', func_get_args());
    }
    
    public function process($event, $object)
    {
        switch (get_class($object)) {
            
            case 'HHF_Domain_Customer_Addon' :
                $this->_processAddon($event, $object);
                break;
            
            case 'HHF_Domain_Customer_Share' :
                $this->_processShare($event, $object);
                break;
            
            default : 
                break;
        }
    }
    
    protected function _processAddon($event, HHF_Domain_Customer_Addon $addon)
    {
        $translate = Bootstrap::getZendTranslate();
        
        switch ($event) {
            case HHF_Domain_Log::EVENT_NEW :
                
                $customer = new HHF_Domain_Customer(
                    $addon->getFarm(), 
                    $addon['customerId']
                );
                
                if ($customer->isEmpty()) {
                    $name = $translate->_('Some Guy');
                } else {
                    $name = $customer['firstName'] . ' ' . $customer['lastName'];
                }
                
                $actualAddon = $addon->getAddon();
                
                if ($actualAddon->isEmpty()) {
                    $addonName = $translate->_('Something Unknown?!?!');
                } else {
                    $addonName = $actualAddon['name'];
                }
                
                $baseUrl = $addon->getFarm()->getBaseUri('http', true);
                
                $customerUrl = $baseUrl . 'admin/customers/customer?id=' 
                    . (int) $addon['customerId'];
                
                $dateFormatter = new IntlDateFormatter(
                    Bootstrap::$locale,
                    IntlDateFormatter::LONG,
                    IntlDateFormatter::NONE
                );
                
                $description = sprintf(
                    $translate->_(
                        '<a href="%s">%s</a> purchased a new addon (%s x %d)' 
                        . ' for their share for the week of %s.'
                    ),
                    $customerUrl,
                    $name,
                    $addonName,
                    $addon['quantity'],
                    $dateFormatter->format(new DateTime($addon['week']))
                );
                break;
        }
        
        $log = new HHF_Domain_Log($addon->getFarm());
        $log->insert(
            array(
                'customerId'  => $addon['customerId'],
                'description' => $description,
                'category'    => HHF_Domain_Log::CATEGORY_ADDONS,
                'event'       => $event
            )
        );
    }
    
    protected function _processShare($event, HHF_Domain_Customer_Share $share)
    {
        $translate = Bootstrap::getZendTranslate();
        
        switch ($event) {
            case HHF_Domain_Log::EVENT_NEW :
                
                $customer = new HHF_Domain_Customer(
                    $share->getFarm(), 
                    $share['customerId']
                );
                
                if ($customer->isEmpty()) {
                    $name = $translate->_('Some Guy');
                } else {
                    $name = $customer['firstName'] . ' ' . $customer['lastName'];
                }
                
                $actualShare = $share->getShare();
                
                if ($actualShare->isEmpty()) {
                    $shareName = $translate->_('Something Unknown?!?!');
                } else {
                    $shareName = $actualShare['name'];
                }
                
                $baseUrl = $share->getFarm()->getBaseUri('http', true);
                
                $customerUrl = $baseUrl . 'admin/customers/customer?id=' 
                    . (int) $share['customerId'];
                
                $shareUrl = $baseUrl . 'admin/customers/subscription?id=' 
                    . (int) $share['id'];
                
                $description = sprintf(
                    $translate->_(
                        '<a href="%s">%s</a> purchased a new share <a href="%s">%s</a> x %d.'
                    ),
                    $customerUrl,
                    $name,
                    $shareUrl,
                    $shareName,
                    $share['quantity']
                );
                break;
        }
        
        $log = new HHF_Domain_Log($share->getFarm());
        $log->insert(
            array(
                'customerId'  => $share['customerId'],
                'description' => $description,
                'category'    => HHF_Domain_Log::CATEGORY_SHARES,
                'event'       => $event
            )
        );
    }
}