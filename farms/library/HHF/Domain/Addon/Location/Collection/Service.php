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
 * @copyright $Date: 2014-03-17 16:14:04 -0300 (Mon, 17 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Add on model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 726 2014-03-17 19:14:04Z farmnik $
 * @copyright $Date: 2014-03-17 16:14:04 -0300 (Mon, 17 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Addon_Location_Collection_Service extends HH_Object_Collection_Service
{   
    public function save($locations)
    {
        $filter = HHF_Domain_Addon_Location::getFilter(
            HHF_Domain_Addon_Location::FILTER_NEW,
            array(
                'farm' => $this->_collection->getFarm()
            )
        );

        foreach ($locations as $location) {
            
            $filter->setData(
                $location
            );
            
            if (!$filter->isValid()) {
                
                throw new HH_Object_Exception_Validation(
                    $filter->getMessages()
                );
            }
        }

        if ($this->_collection->count()) {
            foreach ($this->_collection as $originalLocation) {
                $originalLocation->delete();
            }

            $this->_collection->setData(array());
        }
        
        // insert
        foreach ($locations as $location) {
            $locationObject = new HHF_Domain_Addon_Location(
                $this->_collection->getFarm()
            );

            $locationObject->insert($location);
            $this->_collection[] = $locationObject;
        }
    }
}
