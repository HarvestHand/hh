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
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of NetworkSync
 *
 * @package   HH_Job 
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Networksync.php 798 2014-09-01 04:24:03Z farmnik $
 * @copyright $Date: 2014-09-01 01:24:03 -0300 (Mon, 01 Sep 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Networksync extends HH_Job
{
    public function __construct($config = array())
    {
        parent::__construct($config);
    }
    
    public function add($object, $options)
    {
        parent::add('networksync', func_get_args());
    }
    
    public function process($object, $options)
    {
        switch (get_class($object)) {
            
            case 'HHF_Domain_Addon' :
                $this->processAddon($object, $options);
                break;
            case 'HH_Domain_Farm' :
                $this->pingDistributor($object, $options);
                break;
            default : 
                break;
        }
    }
    
    protected function pingDistributor(HH_Domain_Farm $vendor, $options)
    {
        $distributor = new HH_Domain_Farm($options['distributorId']);

        $isInNetwork = false;

        foreach ($distributor->getParentNetworks(HH_Domain_Network::STATUS_APPROVED) as $network) {
            if ($network->relationId == $vendor->id) {
                $isInNetwork = true;
                break;
            }
        }

        if (!$isInNetwork) {
            trigger_error('Network Sync From Non Relation', E_USER_WARNING);
            return;
        }

        $networkProductsUpdated = $distributor->getPreferences()
            ->getStructure('networkProductsUpdated', 'shares', array());

        $networkProductsUpdated[$vendor->id] = date('Y-m-d');

        $distributor->getPreferences()
            ->replaceStructure('networkProductsUpdated', $networkProductsUpdated, 'shares');
    }
    protected function processAddon(HHF_Domain_Addon $addon, $options)
    {
        if (!empty($options['deleteFrom'])) {

            if ($options['deleteFrom'] == 'distributor') {
                $this->deleteDistributorAddon($options);
            }
        } else if (!empty($addon->distributorId)) {
            $this->updateDistributorAddon($addon, $options);
        } else if (!empty($addon->vendorId)) {
            $this->updateVendorAddon($addon, $options);
        }
    }

    protected function deleteDistributorAddon($options)
    {
        if (!empty($options['externalId']) && !empty($options['vendorId']) && !empty($options['distributorId'])) {

            $targetAddon = HHF_Domain_Addon::fetchOne(
                new HH_Domain_Farm($options['distributorId']),
                array(
                    'where' => array(
                        'externalId' => $options['externalId'],
                        'vendorId' => $options['vendorId']
                    )
                )
            );

            if (!$targetAddon->isEmpty()) {
                $targetAddon->getService()->remove();
            }
        }
    }

    protected function updateDistributorAddon(HHF_Domain_Addon $addon, $options)
    {
        $distributor = new HH_Domain_Farm($addon->distributorId);
        $vendor = $addon->getFarm();

        $isInNetwork = false;

        foreach ($distributor->getParentNetworks(HH_Domain_Network::STATUS_APPROVED) as $network) {
            if ($network->relationId == $vendor->id) {
                $isInNetwork = true;
                break;
            }
        }

        if (!$isInNetwork) {
            trigger_error('Network Sync From Non Relation', E_USER_WARNING);
            return;
        }

        $addonData = array_intersect_key(
            $addon->toArray(),
            array(
                'name' => 1,
                'details' => 1,
                'inventory' => 1,
                'inventoryMinimumAlert' => 1,
                'price' => 1,
                'priceBy' => 1,
                'pendingOnOrder' => 1,
                'unitType' => 1,
                'unitOrderMinimum' => 1,
                'enabled' => 1,
                'expirationDate' => 1
            )
        );

        $addonData['vendorId'] = $vendor->id;
        $addonData['source'] = $vendor->name;
        $addonData['externalId'] = $addon->id;
        if (!empty($options['categoryId'])) {
            $addonData['categoryId'] = $options['categoryId'];
        }
        if (!empty($options['certification'])) {
            $addonData['certification'] = $options['certification'];
        }
        if (!empty($options['locations'])) {
            $addonData['locations'] = $options['locations'];
        }

        if (!empty($addon->image)) {
            $addonData['image'] = $vendor->getBaseUri()
                . 'default/file/id/'
                . $addon->image .'/s/'
                . HHF_Domain_File::IMAGE_THUMBNAIL;
        } else {
            $addonData['image']= null;
        }

        $targetAddon = HHF_Domain_Addon::fetchOne(
            $distributor,
            array(
                'where' => array(
                    'externalId' => $addonData['externalId'],
                    'vendorId' => $vendor->id
                )
            )
        );

        $targetAddon->save($addonData);

        $networkProductsUpdated = $distributor->getPreferences()
            ->getStructure('networkProductsUpdated', 'shares', array());

        $networkProductsUpdated[$vendor->id] = date('Y-m-d');

        $distributor->getPreferences()
            ->replaceStructure('networkProductsUpdated', $networkProductsUpdated, 'shares');
    }

    protected function updateVendorAddon(HHF_Domain_Addon $addon, $options)
    {

    }
}
