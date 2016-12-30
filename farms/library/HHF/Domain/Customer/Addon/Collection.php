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
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Add on model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Collection.php 518 2012-04-25 02:25:26Z farmnik $
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Addon_Collection extends HHF_Object_Collection_Db
{
    /**
     * @var HHF_Object_Collection_Db 
     */
    protected $_addons;
    
    public function __construct(HH_Domain_Farm $farm, $data = array(), $config = array())
    {
        $this->_addons = new HHF_Object_Collection_Db($farm);
        $this->_addons->setObjectType('HHF_Domain_Addon');
        
        parent::__construct($farm, $data, $config);
    }
    
    /**
     * Set related Addons for customer addons
     * 
     * @param array $addons
     * @return \HHF_Domain_Customer_Addon_Collection 
     */
    public function setRelatedAddons($addons)
    {
        foreach ($addons as $addon) {
            $this->_addons[] = $addon;
        }
        
        return $this;
    }
    
    public function getRelatedAddon($addonId)
    {
        $addon = $this->_addons->searchById($addonId);
        
        if ($addon === null) {
            $addon = new HHF_Domain_Addon($this->_farm, $addonId);
            $this->_addons[] = $addon;
        }
        
        return $addon;
    }
}