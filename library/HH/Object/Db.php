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
 * @package   HH_Object
 */

/**
 * Description of Base
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Db.php 892 2015-07-10 03:31:24Z farmnik $
 * @copyright $Date: 2015-07-10 00:31:24 -0300 (Fri, 10 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */
abstract class HH_Object_Db extends HH_Object
{
    protected static $_collection = 'HH_Object_Collection_Db';

    /**
     * get db handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected function _getZendDb()
    {
        if (isset($this->_config['Zend_Db'])) {
            return $this->_config['Zend_Db'];
        }

        return Bootstrap::get('Zend_Db');
    }

    /**
     * get db handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected static function _getStaticZendDb()
    {
        if (isset(self::$_staticConfig['Zend_Db'])) {
            return self::$_staticConfig['Zend_Db'];
        }

        return Bootstrap::get('Zend_Db');
    }

    /**
     * Get data (lazy loader)
     */
    protected function _get()
    {
        if (empty($this->_id)) {
            $this->_setData();
            return;
        }

        $cache = $this->_getZendCache();
        if (($data = $cache->load((string) $this)) !== false) {
            $this->_setData($data);
            return;
        }

        $sql = 'SELECT
                  *
                FROM
                    ' . $this->_getDatabase() . '
                WHERE
                    id = ?';

        $this->_setData(
            $this->_getZendDb()->fetchRow($sql, $this->_id)
        );

        $cache->save($this->_data, (string) $this);
    }

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    public function insert($data)
    {
        $db = $this->_getZendDb();

        $db->insert(
            $this->_getDatabase(),
            $this->_prepareData($data)
        );

        if (empty($data['id'])) {
            $data['id'] = $db->lastInsertId();
        }

        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string) $this);

        $this->_notify(new HH_Object_Event_Insert());
    }

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    public function update($data = null)
    {
        if (!empty($this->_id)) {

            if (!$this->_isLoaded) {
                $this->_get();
            }

            $preEventData = $this->_data;

            $this->_getZendDb()->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array('id = ?' => $this->_id)
            );

            $this->_setData($data, false);

            $this->_getZendCache()->save($this->_data, (string) $this);

            $this->_notify(new HH_Object_Event_Update($preEventData));
        }
    }

    /**
     * Delete current object
     *
     * @throws HH_Object_Exception_Id if object ID is not set
     * @return boolean
     */
    public function delete()
    {
        if (!empty($this->_id)) {

            if (!$this->_isLoaded) {
                $this->_get();
            }

            $preEventData = $this->_data;

            $sql = 'DELETE FROM
                        ' . $this->_getDatabase() . '
                    WHERE
                        id = ?';

            $this->_getZendDb()->query($sql, $this->_id);

            $this->_getZendCache()
                ->remove((string) $this);

            $this->_notify(new HH_Object_Event_Delete($preEventData));
        }

        $this->_reset();
    }

    public function reload()
    {
        $this->_isLoaded = 0;

        $this->_get();
    }

    /**
     * Fetch a collection
     *
     * @param array $options
     * @return HH_Object_Collection_Db
     */
    public static function fetch($options = array())
    {
        $collection = static::$_collection;

        return $collection::fetch(get_called_class(), $options);
    }

    /**
     * Fetch single object
     *
     * @param array $options
     * @return HH_Object
     */
    public static function fetchOne($options = array())
    {
        if (!array_key_exists('limit', $options)) {
            $options['limit'] = array(
                'offset' => 0,
                'rows' => 1
            );
        }

        $set = static::fetch($options);

        if ($set->count()) {
            return $set->current();
        }

        $class = get_called_class();

        return new $class();
    }

    protected function _getDatabase()
    {
        return 'farmnik_hh' . '.' . HH_Object_Collection_Db::_buildTableName(
            get_class($this)
        );
    }

    /**
     * Convert class name to database table name
     *
     * @param HH_Domain_Farm $farm
     * @param string $class Name of class to convert to DB string
     * @return string
     */
    protected static function _getStaticDatabase(HH_Domain_Farm $farm = null,
        $class = null)
    {
        return HH_Object_Collection_Db::_getStaticDatabase(
            (is_null($class) ? get_called_class() : $class),
            $farm
        );
    }

    public function setFarm($farm){
        $this->_farm = $farm;
    }
}