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
 * @copyright $Date: 2015-07-10 00:31:24 -0300 (Fri, 10 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Object
 */

/**
 * Description of object DB
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Db.php 892 2015-07-10 03:31:24Z farmnik $
 * @copyright $Date: 2015-07-10 00:31:24 -0300 (Fri, 10 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Object
 */
abstract class HHF_Object_Db extends HH_Object_Db
{
    /**
     * @var HH_Domain_Farm
     */
    protected $_farm;
    protected static $_collection = 'HHF_Object_Collection_Db';

    public function  __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_farm = $farm;

        parent::__construct($id, $data, $config);
    }

    public function  __toString()
    {
        return parent::__toString() . '_' . $this->_farm->id;
    }

    public static function singleton(HH_Domain_Farm $farm, $id = null,
        $data = null, $config = array())
    {
        $class = get_called_class();

        $hash = md5($class . $farm->id . print_r($id, true));

        if (isset(self::$_instances[$hash]) && self::$_instances[$hash] instanceof $class) {
            return self::$_instances[$hash];
        }

        return self::$_instances[$hash] = new $class($farm, $id, $data, $config);
    }

    protected function _getDatabase()
    {
       return 'farmnik_hh_' . $this->_farm->id . '.'
            . HH_Object_Collection_Db::_buildTableName(get_class($this));
    }

    /**
     * Get farm object
     *
     * @return HH_Domain_Farm
     */
    public function getFarm()
    {
        return $this->_farm;
    }

    public function setFarm($farm){
        parent::setFarm($farm);
    }
    /**
     * Fetch a collection
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return HHF_Object_Collection_Db
     */
    public static function fetch(HH_Domain_Farm $farm, $options = array())
    {
        $collection = static::$_collection;

        return $collection::fetch(get_called_class(), $options, $farm);
    }

    /**
     * Fetch single object
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return HHF_Object
     */
    public static function fetchOne(HH_Domain_Farm $farm, $options = array())
    {
        if (!array_key_exists('limit', $options)) {
            $options['limit'] = array(
                'offset' => 0,
                'rows' => 1
            );
        }

        $set = static::fetch($farm, $options);

        if ($set->count()) {
            return $set->current();
        }

        $class = get_called_class();

        return new $class($farm);
    }
}