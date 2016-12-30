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
 * @copyright $Date: 2014-03-23 19:53:58 -0300 (Sun, 23 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Item.php 732 2014-03-23 22:53:58Z farmnik $
 * @copyright $Date: 2014-03-23 19:53:58 -0300 (Sun, 23 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Delivery_Item extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;

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

        $sql = 'SELECT
                  *
                FROM
                    ' . $this->_getDatabase() . '
                WHERE
                    id = ?
                AND
                    deliveryId = ?';

        $this->_setData($this->_getZendDb()->fetchRow($sql, $this->_id));
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

            $sql = 'DELETE FROM
                        ' . $this->_getDatabase() . '
                    WHERE
                        id = ?
                    AND
                        deliveryId = ?';

            $this->_getZendDb()->query($sql, $this->_id);
        }

        $this->_reset();
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
        $data['id'] = $db->lastInsertId();
        $this->_id = array(
            $data['id'],
            $data['deliveryId']
        );

        $this->_setData($data);
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

            $this->_getZendDb()->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array(
                    'id = ?' => $this->_id[0],
                    'deliveryId = ?' => $this->_id[1]
                )
            );

            $this->_setData($data, false);
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
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {

            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        'item' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'source' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        ),
                        'quantity_1' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        ),
                        'quantity_0_5' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        ),
                        'quantity_2' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null()
                        )
                    ),
                    array(
                        'id' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'item' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Item looks too long')
                            )
                        ),
                        'source' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Source looks too long')
                            )
                        ),
                        'certification' => array(
                            new Zend_Validate_InArray(
                                HHF_Domain_Certification::$certifications
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                HHF_Domain_Certification::ORGANIC,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('I don\'t know about this certification')
                            )
                        ),
                        'quantity_1' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Quantity is to big')
                            )
                        ),
                        'quantity_0_5' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Quantity is to big')
                            )
                        ),
                        'quantity_2' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Quantity is to big')
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
     * Fetch all items by parent delivery
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchByParent(HH_Domain_Farm $farm, $parent)
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    deliveryId = ?';

        $bind = array($parent);

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $item) {

            $return[] = new self(
                $farm,
                array(
                    $item['id'],
                    $item['deliveryId']
                ),
                $item
            );
        }

        return $return;
    }
}
