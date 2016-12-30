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
 * @copyright $Date: 2015-11-03 20:22:47 -0400 (Tue, 03 Nov 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Customer model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Share.php 964 2015-11-04 00:22:47Z farmnik $
 * @copyright $Date: 2015-11-03 20:22:47 -0400 (Tue, 03 Nov 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Share extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_NEW_PARTIAL = 'partial';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = 'all';
    const FETCH_CUSTOMER = 'customer';
    const FETCH_SHARES = 'shares';

    protected static $_collection = 'HHF_Domain_Customer_Share_Collection';

    public function __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Customer_Share_Observer';

        parent::__construct($farm, $id, $data, $config);
    }

    public function insert($data)
    {
        return parent::insert($this->_prepareWeekData($data));
    }

    public function update($data = null)
    {
        return parent::update($this->_prepareWeekData($data));
    }

    protected function _prepareWeekData($data)
    {
        if (!isset($data['startDate']) && isset($data['startWeek'])) {
            list($startYear, $startWeek) = explode('W', $data['startWeek']);

            $startDate = Zend_Date::now()->setTimezone('UTC')->getDate();
            $startDate->set($startYear, Zend_Date::YEAR_8601)
                ->set($startWeek, Zend_Date::WEEK)
                ->set(1, Zend_Date::WEEKDAY_8601);

            $data['startDate'] = HH_Tools_Date::dateToDb($startDate);
        }

        if (!isset($data['endDate']) && isset($data['endWeek'])) {
            list($endYear, $endWeek) = explode('W', $data['endWeek']);

            $endDate = Zend_Date::now()->setTimezone('UTC')->getDate();
            $endDate->set($endYear, Zend_Date::YEAR_8601)
                ->set($endWeek, Zend_Date::WEEK)
                ->set(7, Zend_Date::WEEKDAY_8601);

            $data['endDate'] = HH_Tools_Date::dateToDb($endDate);
        }

        return $data;
    }

    /**
     * Get share from customer share order
     *
     * @return HHF_Domain_Share
     */
    public function getShare()
    {
        if (!$this->isEmpty() && !empty($this->shareId)) {

            return HHF_Domain_Share::singleton(
                $this->_farm,
                $this->shareId
            );
        }
    }

    /**
     * Get share duration for customer order
     *
     * @return HHF_Domain_Share_Duration
     */
    public function getShareDuration()
    {
        if (!$this->isEmpty() && !empty($this->shareDurationId)) {

            $share = HHF_Domain_Share::singleton(
                $this->_farm,
                $this->shareId
            );

            return $share->getDurationById($this->shareDurationId);
        }
    }

    /**
     * Get location from customer share order
     *
     * @return HHF_Domain_Location
     */
    public function getLocation()
    {
        if (!$this->isEmpty() && !empty($this->locationId)) {

            return HHF_Domain_Location::singleton(
                $this->_farm,
                $this->locationId
            );
        }
    }

    public function getInvoices()
    {
        if (!$this->isEmpty()) {
            return HHF_Domain_Customer_Invoice::fetchByType(
                $this->_farm,
                HHF_Domain_Customer_Invoice_Line::TYPE_SHARE,
                $this->id
            );
        }
    }

    public function getDates()
    {
        return $this->getShare()->getDates($this->getShareDuration(), $this->getLocation());
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

    public function getCustomerInvoices()
    {
        if (!$this->isEmpty()) {

            return HHF_Domain_Customer_Invoice::fetchByType(
                $this->_farm,
                HHF_Domain_Customer_Invoice_Line::TYPE_SHARE,
                $this->_id
            );
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
                        'notes' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid note is required')
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
                            new Zend_Validate_InArray(HHF_Order_Share::$paymentPlans),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::DEFAULT_VALUE => HHF_Order_Share::PAYMENT_PLAN_NONE,
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
                        ),
                        'notes' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid note is required')
                            )
                        ),
                        'startWeek' => array(
                            Zend_Filter_Input::PRESENCE => Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'endWeek' => array(
                            Zend_Filter_Input::PRESENCE => Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true
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
     * Fetch all shares
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return HHF_Domain_Customer_Share[]
     */
    public static function fetchSharesCount(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    SUM(quantity)
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        if (!empty($options['shareId'])) {
            $sql .= ' WHERE shareId = ?';

            $bind[] = $options['shareId'];
        }

        if (!empty($options['shareDurationId'])) {
            $sql .= ' AND shareDurationId = ?';

            $bind[] = $options['shareDurationId'];
        }

        if (!empty($options['year'])) {
            $sql .= ' AND year = ?';

            $bind[] = $options['year'];
        }

        return $db->fetchOne($sql, $bind);

    }

    /**
     * Fetch all shares
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return HHF_Domain_Customer_Share[]
     */
    public static function fetchShares(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        if (isset($options['fetch'])) {

            switch ($options['fetch']) {
                case self::FETCH_CUSTOMER :
                    $sql .= ' WHERE customerId = ?';
                    if ($options['customer'] instanceof HHF_Domain_Customer) {
                        $bind[] = $options['customer']->id;
                    } else {
                        $bind[] = $options['customer'];
                    }
                    break;
                case self::FETCH_SHARES :

                    if (!is_array($options['shares'])) {
                        $options['shares'] = array($options['shares']);
                    }

                    $sql .= ' WHERE shareId IN(' . implode(
                        ',',
                        array_fill(0, count($options['shares']), '?')
                    ) . ')';

                    foreach ($options['shares'] as $share) {
                        $bind[] = $share;
                    }
                    break;
            }
        }

        if (!empty($options['isoWeek'])) {
            $sql .= ' AND startDate <= ? AND endDate >= ?';

            list ($year, $week) = explode('W', $options['isoWeek']);

            $weekDate = Zend_Date::now()->setTimezone()->getDate();
            $weekDate->set($year, Zend_Date::YEAR_8601)
                ->set($week, Zend_Date::WEEK)
                ->set(1, Zend_Date::WEEKDAY_8601);

            $bind[] = HH_Tools_Date::dateToDb($weekDate);

            $weekDate->set(7, Zend_Date::WEEKDAY_8601);

            $bind[] = HH_Tools_Date::dateToDb($weekDate);
        }

        $sql .= ' ORDER BY id ASC';

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $share) {
            $return[] = new self(
                $farm,
                $share['id'],
                $share,
                $options
            );
        }

        if (isset($options['filter']) && $options['filter'] == 'active') {

            return self::filterCustomerSharesToActive(
                $return,
                ((isset($options['week']) ? : null))
            );
        }

        return $return;
    }

    /**
     * Take in an array of customer shares ordered, and return an array of
     * shares in season
     *
     * @param array $customerShares
     * @param Zend_Date $weekDate
     * @return type
     */
    public static function filterCustomerSharesToActive(&$customerShares,
        Zend_Date $weekDate = null)
    {
        $filteredCustomerShares = array();

        if (!($weekDate instanceof Zend_Date)) {

            list($year, $week) = explode('W', date('o\WW', time()));

            $weekDate = new Zend_Date();
            $weekDate->set($year, Zend_Date::YEAR_8601)
                ->set($week, Zend_Date::WEEK)
                ->set(1, Zend_Date::WEEKDAY_8601);
        }

        $oneWeekOffset = clone $weekDate;
        $oneWeekOffset->addDay(7);

        foreach ($customerShares as $customerShare) {

            /* @var $share HHF_Domain_Share */
            $share = $customerShare->getShare();

            if ($share->isEmpty()) {
                continue;
            }

            /* @var $subscription HHF_Domain_Customer_Share */
            $duration = $share->getDurationById($customerShare->shareDurationId);
            $startDateCmp = $oneWeekOffset->compareDate(
                $duration->getStartDate(null, $customerShare->year)
            );
            $endDateCmp = $weekDate->compareDate(
                $duration->getEndDate(
                    $share->deliverySchedule,
                    null,
                    $customerShare->year
                )
            );

            if ($startDateCmp >= 0 && $endDateCmp <= 0) {
                $filteredCustomerShares[] = $customerShare;
            }
        }

        return $filteredCustomerShares;
    }

    public static function fetchSingle(HH_Domain_Farm $farm, $options = array())
    {
        $temp = array('where' => array('id' => $options['shareId']));
        return self::fetch($farm, $temp)[0];
    }
}
