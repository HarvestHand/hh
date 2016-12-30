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
 * @copyright $Date: 2016-12-30 08:52:44 -0400 (Fri, 30 Dec 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Customer model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Invoice.php 1013 2016-12-30 12:52:44Z farmnik $
 * @copyright $Date: 2016-12-30 08:52:44 -0400 (Fri, 30 Dec 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Invoice extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const TYPE_SHARES = 'SHARES';
    const TYPE_ADDONS = 'ADDONS';
    const TYPE_MISC = 'MISC';
    protected $_lines = array();

    public function __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Customer_Invoice_Observer';

        parent::__construct($farm, $id, $data, $config);
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

            $lines = (!empty($data['lines'])) ?
                $data['lines'] : null;

            unset($data['lines']);

            $db->insert(
                $this->_getDatabase(),
                $this->_prepareData($data)
            );
            $data['id'] = $db->lastInsertId();

            $data['lines'] = array();

            if (!empty($lines)) {

                foreach ($lines as $key => $line) {
                    $lineObj = new HHF_Domain_Customer_Invoice_Line(
                        $this->_farm
                    );

                    $line['customerInvoiceId'] = $data['id'];

                    $lineObj->insert($line);

                    $data['lines'][$key] = $lineObj;
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

    public function update($data = null)
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        $preEventData = $this->_data;

        $db = $this->_getZendDb();

//        $db->beginTransaction();

        try {

            $lines = (!empty($data['lines'])) ?
                $data['lines'] : null;

            unset($data['lines']);

            $db->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array('id = ?' => $this->_id)
            );

            $data['lines'] = array();

            if (!empty($lines)) {

                foreach ($lines as $line) {
                    $lineObj = new HHF_Domain_Customer_Invoice_Line(
                        $this->_farm,
                        $line['id']
                    );

                    $lineObj->update($line);

                    $data['lines'][] = $lineObj;
                }
            }

//            $db->commit();

        } catch(Exception $e) {

//            $db->rollBack();

            throw $e;
        }

        $this->_lines = null;
        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string) $this);
        $this->_notify(new HH_Object_Event_Update($preEventData));
    }

    /**
     *
     * @return HHF_Domain_Customer_Invoice_Line[]
     */
    public function getLines()
    {
        if (!$this->isEmpty()
            && !($this->_lines instanceof HHF_Object_Collection_Db)) {

            $this->_lines = HHF_Domain_Customer_Invoice_Line::fetch(
                $this->_farm,
                array(
                    'where' => array(
                        'customerInvoiceId' => $this->_id
                    )
                )
            );

            foreach ($this->_lines as $line) {
                $line->setInvoice($this);
            }
        } else if ($this->_lines instanceof HHF_Object_Collection_Db) {
            return $this->_lines;
        } else {
            $this->_lines = new HHF_Object_Collection_Db($this->getFarm());
            $this->_lines->setObjectType('HHF_Domain_Customer_Invoice_Line');
        }

        return $this->_lines;
    }

    /**
     * @return HHF_Domain_Customer
     */
    public function getCustomer()
    {
        if (!$this->isEmpty() && !empty($this->customerId)) {

            return HHF_Domain_Customer::singleton(
                $this->_farm,
                $this->customerId
            );
        }
    }

    /**
     * Get related customer purchased items (share or add on)
     *
     * @return array
     */
    public function getRelatedCustomerItems()
    {
        $lines = $this->getLines();
        $items = array();

        if (count($lines)) {
            foreach ($lines as $line) {
                switch ($line['type']) {
                    case HHF_Domain_Customer_Invoice_Line::TYPE_ADDON :
                        $items[] = new HHF_Domain_Customer_Addon(
                            $this->getFarm(),
                            $line['referenceId']
                        );
                        break;
                    case HHF_Domain_Customer_Invoice_Line::TYPE_SHARE :
                        $items[] = new HHF_Domain_Customer_Share(
                            $this->getFarm(),
                            $line['referenceId']
                        );
                        break;
                }
            }
        }

        return $items;
    }

    /**
     * Get invoice transactions
     *
     * @return HHF_Object_Collection_Db|null
     */
    public function getTransactions()
    {
        if (!$this->isEmpty() && !empty($this->customerId)) {

            $transactionsInvoices = HHF_Domain_Transaction_Invoice::fetch(
                $this->getFarm(),
                array(
                    'columns' => '*',
                    'where' => array(
                        'invoiceId' => $this->_id
                    )
                )
            );

            $transactionIds = array();

            foreach ($transactionsInvoices as $key => $transactionInvoice) {
                $transactionIds[] = $transactionInvoice['transactionId'];
            }

            if (!empty($transactionIds)) {
                return HHF_Domain_Transaction::fetch(
                    $this->getFarm(),
                    array(
                        'where' => array(
                            'id IN(' . implode(',', $transactionIds) . ')'
                        )
                    )
                );
            }
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
        $filter = ($filter) ?: self::FILTER_NEW;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        $presence = ($filter == self::FILTER_NEW) ?
            Zend_Filter_Input::PRESENCE_REQUIRED :
            Zend_Filter_Input::PRESENCE_OPTIONAL;

        $allowEmpty = ($filter == self::FILTER_NEW) ? false : true;

        switch ($filter) {

            case self::FILTER_NEW_PARTIAL :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
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
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share is required')
                            )
                        ),
                        'shareDurationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Duration'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid delivery schedule is required')
                            )
                        ),
                        'shareSizeId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Size'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share size is required')
                            )
                        ),
                        'locationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Location'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share pickup location is required')
                            )
                        ),
                        'quantity' => array(
                            new Zend_Validate_Between(array('min' => 1, 'max' => 99)),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share quantity is required')
                            )
                        ),
                        'year' => array(
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty
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
            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'customerId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Customer'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid customer is required')
                            )
                        ),
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
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share is required')
                            )
                        ),
                        'shareDurationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Duration'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid delivery schedule is required')
                            )
                        ),
                        'shareSizeId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Size'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share size is required')
                            )
                        ),
                        'locationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Location'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share pickup location is required')
                            )
                        ),
                        'quantity' => array(
                            new Zend_Validate_Between(array('min' => 1, 'max' => 99)),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share quantity is required')
                            )
                        ),
                        'year' => array(
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty
                        ),
                        'paymentPlan' => array(
                            new Zend_Validate_InArray(
                                HHF_Order_Share::$paymentPlans
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                HHF_Order_Share::PAYMENT_PLAN_NONE,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid payment plan is required')
                            )
                        ),
                        'payment' => array(
                            new Zend_Validate_InArray(
                                HHF_Domain_Transaction::$payments
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A payment method is required')
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
     * Fetch invoice by line item type
     *
     * @param HH_Domain_Farm $farm
     * @param string $type
     * @param int $referenceId
     * @return HHF_Domain_Customer_Invoice
     */
    public static function fetchByType(HH_Domain_Farm $farm, $type,
        $referenceId)
    {
        $options = array(
            'sql' => 'SELECT DISTINCT
                        customerInvoiceId as id
                    FROM
                        __SCHEMA__.customersInvoicesLines',
            'where' => array(
                'type' => $type,
                'referenceId' => (int) $referenceId
            ),
            'order' => array(
                array(
                    'column' => 'customerInvoiceId',
                    'dir' => 'asc'
                )
            )
        );

        return self::fetch($farm, $options);
    }

    /**
     * Fetch invoice by line item type
     *
     * @param HH_Domain_Farm $farm
     * @param string $type
     * @param int $referenceId
     * @return HHF_Domain_Customer_Invoice
     */
    public static function fetchOneByType(HH_Domain_Farm $farm, $type,
        $referenceId)
    {
        $options = array(
            'sql' => 'SELECT
                        customerInvoiceId as id
                    FROM
                        __SCHEMA__.customersInvoicesLines',
            'where' => array(
                'type' => $type,
                'referenceId' => (int) $referenceId
            )
        );

        return self::fetchOne($farm, $options);
    }
}
