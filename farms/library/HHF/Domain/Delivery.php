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
 * @copyright $Date: 2013-05-20 20:53:53 -0300 (Mon, 20 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Delivery.php 655 2013-05-20 23:53:53Z farmnik $
 * @copyright $Date: 2013-05-20 20:53:53 -0300 (Mon, 20 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Delivery extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    const FETCH_ENABLED = 'ENABLED';
    const ORDER_WEEK = 'WEEK';

    protected $_share = null;
    protected $_items = null;

    public function __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Delivery_Observer';

        parent::__construct($farm, $id, $data, $config);
    }

    /**
     * Get data (lazy loader)
     *
     * @return null
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

        $data = $this->_getZendDb()->fetchRow($sql, $this->_id);

        // fetch sizes
        $data['items'] = HHF_Domain_Delivery_Item::fetchByParent(
            $this->_farm,
            $this->_id
        );

        $this->_setData(
            $data
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

        $db->beginTransaction();

        try {

            $items = (!empty($data['items'])) ?
                $data['items'] : null;

            unset($data['items']);

            $db->insert(
                $this->_getDatabase(),
                $this->_prepareData($data)
            );
            $data['id'] = $db->lastInsertId();

            $data['items'] = array();

            if (!empty($items)) {
                foreach ($items as $key => $item) {
                    $itemObj = new HHF_Domain_Delivery_Item(
                        $this->_farm
                    );

                    $item['deliveryId'] = $data['id'];

                    $itemObj->insert($item);

                    $data['items'][$key] = $itemObj;
                }
            }

            $db->commit();

        } catch(Exception $e) {

            $db->rollBack();

            throw $e;
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

            $items = (array_key_exists('items', $data)) ?
                $data['items'] : null;

            unset($data['items']);

            $db = $this->_getZendDb();

            $db->beginTransaction();

            try {

                $db->update(
                    $this->_getDatabase(),
                    $this->_prepareData($data, false),
                    array('id = ?' => $this->_id)
                );

                if ($items !== null) {

                    $data['items'] = $this->_updateRelations(
                        $this->_data['items'],
                        $items,
                        'HHF_Domain_Delivery_Item'
                    );
                }

                $db->commit();

            } catch (Exception $e) {

                $db->rollBack();

                throw $e;
            }

            $this->_setData($data, false);

            $this->_getZendCache()->save($this->_data, (string) $this);

            $this->_notify(new HH_Object_Event_Update($preEventData));
        }
    }

    /**
     * Update relational data
     *
     * @param array $orginalData Original stored relational data
     * @param array $newData New relational data to be stored
     * @param string $class Model class name
     * @return array Array of updated relational data
     */
    protected function _updateRelations($orginalData, $newData, $class) {
        $relations = array();

        foreach ($orginalData as $rowOriginal) {

            $found = false;

            if (is_array($newData)) {
                foreach ($newData as $key => $rowNew) {
                    if (!isset($rowNew['id'])) {
                        continue;
                    }

                    if ($rowOriginal['id'] == $rowNew['id']) {

                        $rowOriginal->update($rowNew);
                        $relations[] = $rowOriginal;

                        unset($newData[$key]);

                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $rowOriginal->delete();
            }
        }

        foreach ($newData as $row) {

            $row['deliveryId'] = $this->_data['id'];

            $object = new $class($this->_farm);
            $object->insert($row);

            $relations[] = $object;
        }

        return $relations;
    }

    /**
     * Get share
     *
     * @return HHF_Domain_Share
     */
    public function getShare()
    {
        if ($this->_share instanceof HHF_Domain_Share) {
            return $this->_share;
        }

        $this->_share = new HHF_Domain_Share($this->_farm, $this->shareId);

        return $this->_share;
    }

    public function getItems()
    {
        if ($this->_items !== null) {
            return $this->_items;
        }

        $this->_items = HHF_Domain_Delivery_Item::fetchByParent(
            $this->_farm,
            $this->id
        );

        return $this->_items;
    }

    public function getDeliveryItems()
    {

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
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {

            case self::FILTER_NEW :
            case self::FILTER_EDIT :

                $weekExclude = '';

                if (!empty($options['delivery'])) {
                    $weekExclude = self::_getStaticZendDb()
                        ->quoteInto('shareId = ?', $_POST['shareId']);

                    $weekExclude .= self::_getStaticZendDb()
                        ->quoteInto('and id != ?', $options['delivery']['id']);
                } else {
                    $weekExclude = self::_getStaticZendDb()
                        ->quoteInto('shareId = ?', $_POST['shareId']);
                }


                $inputFilter = new Zend_Filter_Input(
                    array(
                    ),
                    array(
                        'shareId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share is required')
                            )
                        ),
                        'week' => array(
                            new Zend_Validate_Regex('/[1-2][0-9]{3}W[0-5][0-9]/'),
                            new Zend_Validate_Db_NoRecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Delivery'
                                    ),
                                    'field' => 'week',
                                    'exclude' => $weekExclude,
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid delivery week is required'),
                                $translate->_('You\'ve already added a delivery for this share week')
                            )
                        ),
                        'enabled' => array(
                            new Zend_Validate_InArray(
                                array(
                                    0, 1
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Enabled status is required')
                            )
                        )
                    ),
                    null,
                    array(
                        Zend_Filter_Input::MISSING_MESSAGE   =>
                            $translate->_("'%field%' is required"),
                        Zend_Filter_Input::NOT_EMPTY_MESSAGE =>
                            $translate->_("'%field%' is required"),
                    )
                );
                break;
        }

        return $inputFilter;
    }

    /**
     * Validate share data for data storage
     *
     * @param array $dataToValidate
     * @param array $options
     * @return array Validated data
     * @throws HH_Object_Exception_Validation
     */
    public static function validate(&$dataToValidate, $options)
    {
        $errors = array();

        $filterDelivery = HHF_Domain_Delivery::getFilter(
            HHF_Domain_Location::FILTER_NEW,
            $options
        );

        $filterItem = HHF_Domain_Delivery_Item::getFilter(
            HHF_Domain_Location::FILTER_NEW,
            $options
        );

        $items = array();

        if (isset($dataToValidate['items'])) {
            $items = $dataToValidate['items'];
        }

        $filterDelivery->setData($dataToValidate);

        if (!$filterDelivery->isValid()) {
            $errors = $filterDelivery->getMessages();
        } else {

            $data = $filterDelivery->getUnescaped();
        }

        if (!empty($items)) {
            $count = 0;

            foreach ($items as $item) {
                $filterItem->setData($item);

                if (!$filterItem->isValid()) {
                    if (!isset($errors['items'])) {
                        $errors['items'] = array();
                    }

                    $errors['items'][$count] = $filterItem->getMessages();
                } else {
                    if (!isset($data['items'])) {
                        $data['items'] = array();
                    }
                    $data['items'][$count] = $filterItem->getUnescaped();
                }
                ++$count;
            }
        }

        if (!empty($errors)) {
            throw new HH_Object_Exception_Validation($errors);
        }

        return $data;
    }

    public static function getSummary(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT DISTINCT
                week
            FROM
                ' . self::_getStaticDatabase($farm) . '
            WHERE enabled = 1
            ORDER BY week DESC';

        return $db->fetchAll($sql);
    }

    public static function hasDeliveries(HH_Domain_Farm $farm, $options = array())
    {
        $cache = self::_getStaticZendCache();
        if (($data = $cache->load((string) $farm . '_hasDeliveries')) !== false) {
            return (bool) $data;
        }

        $options['where'] = 'enabled = 1';
        $options['columns'] = 'count(*) as count';

        $result = self::fetchOne($farm, $options);

        $cache->save($result['count'], (string) $farm . '_hasDeliveries');

        return (bool) $result['count'];
    }

    /**
     * Fetch all pages
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return HHF_Domain_Delivery[]
     */
    public static function fetchDeliveries(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase($farm);

        $bind = array();
        $where = array();

        if (isset($options['fetch'])) {
            switch ($options['fetch']) {
                case self::FETCH_ENABLED :
                    $where[] = 'enabled = 1';

                    break;
            }
        }

        if (isset($options['week'])) {
            $where[] = 'week = ?';
            $bind[] = $options['week'];
        }

        if (isset($options['shares']) && !empty($options['shares'])) {

            if (!is_array($options['shares'])) {
                $options['shares'] = array($options['shares']);
            }

            $shareIds = array();

            foreach ($options['shares'] as $share) {
                if ($share instanceof HHF_Domain_Share) {
                    $shareIds[] = $share->id;
                    $bind[] = $share->id;
                } elseif ($share instanceof HHF_Domain_Customer_Share) {
                    $shareIds[] = $share->shareId;
                    $bind[] = $share->shareId;
                }
            }

            $where[] = 'shareId IN(' . implode(
                ',',
                array_fill(0, count($shareIds), '?')
            ) . ')';
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

        if (isset($options['order'])) {

            switch ($options['order']) {
                case self::ORDER_WEEK :
                    $sql .= ' ORDER BY week DESC';

                    break;
            }
        }

        if (isset($options['limit'])) {

            $sql .= ' limit ' . implode(',', $options['limit']);
        }

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $delivery) {
            $return[] = new self(
                $farm,
                $delivery['id'],
                $delivery,
                $options
            );
        }

        return $return;
    }
}
