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
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH
 */

/**
 * Session manager
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Session.php 323 2011-09-22 22:22:20Z farmnik $
 * @package   HH
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Session extends HH_Object_Db
    implements Zend_Session_SaveHandler_Interface
{
    protected $_db;
    protected $_cache;

    /**
     * Construct session handler
     * @param <type> $id
     * @param <type> $data
     * @param int $config
     */
    public function  __construct($id = null, $data = null, $config = array())
    {
        if (!isset($config['writeDelay'])) {
            $config['writeDelay'] = 300;
        }

        $this->_getZendCache();
        $this->_getZendDb();

        parent::__construct($id, $data, $config);
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

        $data = $this->_prepareData($data);

        $db->query(
            'REPLACE INTO
                ' . $this->_getDatabase() . '
            SET
                id = ?,
                addedTimestamp = ?,
                updatedTimestamp = ?,
                data = ?,
                farmerId = ?',
            array(
                $data['id'],
                $data['addedTimestamp'],
                $data['updatedTimestamp'],
                $data['data'],
                $data['farmerId']
            )
        );

        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string) $this);
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

            $data = array_merge(
                $this->_data,
                $this->_prepareData($data, false)
            );

            $db = $this->_getZendDb();

            $db->query(
                'REPLACE INTO
                    ' . $this->_getDatabase() . '
                SET
                    id = ?,
                    addedTimestamp = ?,
                    updatedTimestamp = ?,
                    data = ?,
                    farmerId = ?',
                array(
                    $data['id'],
                    $data['addedTimestamp'],
                    $data['updatedTimestamp'],
                    $data['data'],
                    $data['farmerId']
                )
            );

            $this->_setData($data, true);

            $this->_getZendCache()->save($this->_data, (string) $this);
        }
    }

    /**
     * Get Zend_Filter_Input for model
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {

    }

    /**
     * Open Session - retrieve resources
     *
     * @param string $savePath
     * @param string $name
     */
    public function open($savePath, $name) {}

    /**
     * Close Session - free resources
     *
     */
    public function close()
    {
        return true;
    }

    /**
     * Read session data
     *
     * @param string $id
     */
    public function read($id)
    {
        $this->_id = $id;
        $this->_get();
        if (isset($this->_data['data'])) {
            return $this->_data['data'];
        }
    }

    /**
     * Write Session - commit data to resource
     *
     * @param string $id
     * @param mixed $data
     */
    public function write($id, $data)
    {
        if (!($this->_id == $id && $this->_isLoaded)) {
            $this->_id = $id;
            $this->_get();
        }

        if ($this->_isEmpty) {

            $farmerId = (!empty($_SESSION['Zend_Auth']['storage']['id']))
                ? $_SESSION['Zend_Auth']['storage']['id'] : null;

            $this->insert(
                array(
                    'id' => $id,
                    'data' => $data,
                    'farmerId' => $farmerId
                )
            );
        } else {
            $originalHash = hash('md5', $this->_data['data']);
            $currentHash = hash('md5', $data);

            $writeTime = $this->_data['updatedTimestamp'] + $this->_config['writeDelay'];
            $forceUpdate = ($_SERVER['REQUEST_TIME'] > $writeTime) ?
                true : false;

            $farmerId = (!empty($_SESSION['Zend_Auth']['storage']) && is_object($_SESSION['Zend_Auth']['storage']))
                ? $_SESSION['Zend_Auth']['storage']->id : null;

            if ($originalHash != $currentHash || $forceUpdate) {
                $this->update(
                    array(
                        'data' => $data,
                        'farmerId' => $farmerId
                    )
                );
            }
        }

        return true;
    }

    /**
     * Destroy Session - remove data from resource for
     * given session id
     *
     * @param string $id
     */
    public function destroy($id)
    {
        $this->_id = $id;

        $this->delete();

        return true;
    }

    /**
     * Garbage Collection - remove old session data older
     * than $maxlifetime (in seconds)
     *
     * @param int $maxlifetime
     */
    public function gc($maxlifetime)
    {
        $old = $_SERVER['REQUEST_TIME'] - $maxlifetime;

        $ids = $this->_getZendDb()->fetchCol(
            'SELECT
                id
            FROM
                ' . $this->_getDatabase() . '
            WHERE
                updatedTimestamp < ?',
            array(
                $old
            )
        );

        if (!empty($ids)) {
            foreach ($ids as $id) {
                $this->_id = $id;
                $this->delete();
            }
        }

        return true;
    }

    /**
     * get db handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected function _getZendDb()
    {
        if ($this->_db) {
            return $this->_db;
        }

        return $this->_db = parent::_getZendDb();
    }

    /**
     *
     * @return Zend_Cache_Core
     */
    protected function  _getZendCache()
    {
        if ($this->_cache) {
            return $this->_cache;
        }

        return $this->_cache = parent::_getZendCache();
    }

    /**
     * Prepare data to be entered into the database
     *
     * Add timestamps
     * Convert dates / times to proper formats
     *
     * @param array $data Data to prepare
     * @param boolean $insert Is data to be inserted (false is updated)
     * @return array
     */
    protected function  _prepareData($data, $insert = true)
    {
        if ($insert) {
            $this->_data['addedTimestamp'] =
                $data['addedTimestamp'] = $_SERVER['REQUEST_TIME'];
        }

        $this->_data['updatedTimestamp'] =
            $data['updatedTimestamp'] = $_SERVER['REQUEST_TIME'];

        return $data;
    }
}