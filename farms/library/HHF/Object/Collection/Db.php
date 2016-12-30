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
 * @package   HHF_Object
 */

/**
 * Description of object DB
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Db.php 518 2012-04-25 02:25:26Z farmnik $
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Object
 */
class HHF_Object_Collection_Db extends HH_Object_Collection_Db
{
    /**
     * @var HH_Domain_Farm 
     */
    protected $_farm;
    
    public function __construct(HH_Domain_Farm $farm, $data = array(),
        $config = array())
    {
        $this->_farm = $farm;
        
        parent::__construct($data, $config);
    }
    
    /**
     * @return HH_Domain_Farm 
     */
    public function getFarm()
    {
        return $this->_farm;
    }
    
    /**
     * @return HHF_Object_Collection_Db 
     */
    protected static function _getCollection($objectClass, $options, $farm)
    {
        $class = get_called_class();
        
        $collection = new $class($farm, array(), $options);
         
        $collection->setObjectType($objectClass);
         
        return $collection;
    }
}