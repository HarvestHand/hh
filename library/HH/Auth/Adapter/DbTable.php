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
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */

/**
 * IndexController
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: DbTable.php 339 2011-10-22 23:25:03Z farmnik $
 * @category  HH_Auth
 * @package   
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Auth_Adapter_DbTable extends Zend_Auth_Adapter_DbTable
{
    protected $_role = null;
    protected $_farmId = null;
    
    /**
     * Set role
     * 
     * @param type $role
     * @return HH_Auth_Adapter_DbTable 
     */
    public function setRole($role)
    {
        $this->_role = $role;
        
        return $this;
    }
    
    /**
     * Set farm ID
     * 
     * @param type $farmId
     * @return HH_Auth_Adapter_DbTable 
     */
    public function setFarmId($farmId)
    {
        $this->_farmId = $farmId;
        
        return $this;
    }
    
    /**
     * _authenticateCreateSelect() - This method creates a Zend_Db_Select object that
     * is completely configured to be queried against the database.
     *
     * @return Zend_Db_Select
     */
    protected function _authenticateCreateSelect()
    {
        $dbSelect = parent::_authenticateCreateSelect();
        
        switch ($this->_role) {
            case HH_Domain_Farmer::ROLE_ADMIN :
                $dbSelect->where(
                    $this->_zendDb->quoteInto('role = ?', $this->_role)
                );
                $dbSelect->where('farmId IS NULL');
                break;
            case HH_Domain_Farmer::ROLE_FARMER :
                $dbSelect->where(
                    $this->_zendDb->quoteInto('role = ?', $this->_role)
                );
                $dbSelect->where('farmId IS NOT NULL');
                break;
            case HH_Domain_Farmer::ROLE_MEMBER :
                $dbSelect->where(
                    $this->_zendDb->quoteInto('role = ?', $this->_role)
                );
                $dbSelect->where(
                    $this->_zendDb->quoteInto('farmId = ?', $this->_farmId)
                );
                break;
        }
        
        return $dbSelect;
    }
}
