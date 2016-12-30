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
 * @copyright $Date: 2016-10-25 22:26:24 -0300 (Tue, 25 Oct 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Customer model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Addon.php 1007 2016-10-26 01:26:24Z farmnik $
 * @copyright $Date: 2016-10-25 22:26:24 -0300 (Tue, 25 Oct 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Addon extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_NEW_PARTIAL = 'partial';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = 'all';
    const FETCH_CUSTOMER = 'customer';

    /**
     * Related add on
     * @var HHF_Domain_Addon
     */
    protected $addon;

    public function __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Customer_Addon_Observer';

        parent::__construct($farm, $id, $data, $config);
    }

    public function delete()
    {
        $invoice = $this->getCustomerInvoice();

        $result = parent::delete();

        $invoice->delete();

        return $result;
    }

    /**
     * @return HHF_Domain_Addon
     */
    public function getAddon()
    {
        if ($this->addon instanceof HHF_Domain_Addon) {
            return $this->addon;
        }

        if (!$this->isEmpty() && !empty($this->addonId)) {

            $this->addon = HHF_Domain_Addon::singleton(
                $this->_farm,
                $this->addonId
            );
        }

        return $this->addon;
    }

    public function setAddon(HHF_Domain_Addon $addon)
    {
        $this->addon = $addon;
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
     * Get customer invoice for this addon
     *
     * @return HHF_Domain_Customer_Invoice|null
     */
    public function getCustomerInvoice()
    {
        if (!$this->isEmpty()) {

            return HHF_Domain_Customer_Invoice::fetchOneByType(
                $this->_farm,
                HHF_Domain_Customer_Invoice_Line::TYPE_ADDON,
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
                        'addonId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Addon'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid product is required')
                            )
                        ),
                        'quantity' => array(
                            new HHF_Validate_Customer_Addon_Quantity($options['farm']),
                            Zend_Filter_Input::FIELDS => array('addonId', 'quantity'),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty
                        ),
                        'paidInFull' => array(
                            new Zend_Validate_Between(array('min' => 0, 'max' => 1)),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid product payment status is required')
                            )
                        ),
                        'week' => array(
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty
                        ),
                        'payment' => array(
                            new Zend_Validate_InArray(HHF_Domain_Transaction::$payments),
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
     * Fetch all shares
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchAddons(HH_Domain_Farm $farm, $options = array())
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
                case self::FETCH_CUSTOMER :
                    $where[] = 'customerId = ?';
                    if ($options['customer'] instanceof HHF_Domain_Customer) {
                        $bind[] = $options['customer']->id;
                    } else {
                        $bind[] = $options['customer'];
                    }
                    break;
            }
        }

        if (isset($options['week'])) {
            $where[] = 'week = ?';
            $bind[] = $options['week'];
        }

        if (!empty($where)) {
            $sql .= ' WHERE ' . implode(' AND ', $where);
        }

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

        return $return;
    }

    /**
     * Figure out which week the addon order should take place
     * @param HHF_Domain_Customer_Share[] $customerShares
     * @param HH_Domain_Farm $farm
     * @return Zend_Date
     */
    public static function calculateAddonWeek(
        &$customerShares,
        HH_Domain_Farm $farm
    ) {
        if (empty($customerShares)) {
            return null;
        }

        $today = Zend_Date::now();

        if (!empty($farm->timezone)) {
            $today->setTimezone($farm->timezone);
        }

        $addonWeekDate = false;

        $globalCutOffTime = self::parseCutOffTime(
            $farm->getPreferences()->get(
                'addOnCutOffTime',
                'shares',
                false
            ),
            $farm
        );

        foreach ($customerShares as $customerShare) {

            /* @var $customerShare HHF_Domain_Customer_Share */
            $location = $customerShare->getLocation();

            if ($location === null) {
                continue;
            }

            $duration = $customerShare->getShareDuration();

            $isLocationThisWeek = self::isLocationThisWeek(
                $today,
                $location,
                $farm,
                $globalCutOffTime
            );

//            $isLocationThisWeek = self::isLocationThisWeek(
//                $today,
//                $customerShare,
//                $farm,
//                $globalCutOffTime
//            );

            if ($isLocationThisWeek) {
                $date = clone $today;
                $date->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601)
                    ->setTime($location->timeStart, 'HH:mm:ss');

                return $date;
            }

            // candidate for next week?

            // get next delivery week for share from this week, not next week

            $nextWeekDate = clone $today;
            $nextWeekDate->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
            $share = $customerShare->getShare();
            $duration = $share->getDurationById(
                $customerShare->shareDurationId
            );

            switch ($share->deliverySchedule) {
                case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                    $nextWeekDate->addWeek(1);
                    break;
                case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                    $nextWeekDate->addWeek(2);
                    break;
                case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                    $nextWeekDate->addMonth(1);
                    $nextWeekDate->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
                    break;
            }

            $endDateCmp = $nextWeekDate->compareDate(
                $duration->getEndDate(
                    $share->deliverySchedule,
                    null,
                    $customerShare->year
                )
            );

            if ($endDateCmp <= 0) {
                if ($addonWeekDate !== false) {
                    if ($nextWeekDate->compareDate($addonWeekDate) < 0) {
                        $addonWeekDate = $nextWeekDate;
                    }
                } else {
                    $addonWeekDate = $nextWeekDate;
                }
            }
        }

        if ($addonWeekDate !== false) {
            return $addonWeekDate;
        }
    }

    /**
     * Figure out which week the addon order should take place
     * @param HHF_Domain_Customer_Share[] $customerShares
     * @param HH_Domain_Farm $farm
     * @return Zend_Date
     */
    public static function calculateAddonWeek2(
        &$customerShares,
        HH_Domain_Farm $farm
    ) {
        if (empty($customerShares)) {
            return null;
        }

        $addonWeekDate = false;

        $globalCutOffTime = self::parseCutOffTime(
            $farm->getPreferences()->get(
                'addOnCutOffTime',
                'shares',
                false
            ),
            $farm
        );

        foreach ($customerShares as $customerShare) {

            $today = Zend_Date::now();

//            if (!empty($farm->timezone)) {
//                $today->setTimezone($farm->timezone);
//            }

            /* @var $customerShare HHF_Domain_Customer_Share */
            $location = $customerShare->getLocation();

            if ($location === null) {
                continue;
            }

            $share = $customerShare->getShare();
            $duration = $customerShare->getShareDuration();

            // adjust today to a share week
            $shareStartDate = $duration->getStartDate($location, $share->year);
            $shareEndDate = $duration->getEndDate($share->deliverySchedule, $location, $share->year);
            $endVsToday = $shareEndDate->compareDate($today);

//            echo 'today ' . $today->toString(Zend_Date::ISO_8601) . PHP_EOL;
//            echo 'shareStartDate ' . $shareStartDate->toString(Zend_Date::ISO_8601) . PHP_EOL;
//            echo 'shareEndDate ' . $shareEndDate->toString(Zend_Date::ISO_8601) . PHP_EOL;
//            echo 'endVsToday ' . $endVsToday . PHP_EOL;

            if ($today->compareDate($shareStartDate) <= 0) {
//                echo 'we are before the share start' . PHP_EOL;
                // check if cut off has passed

                $firstWeek = clone $shareStartDate;

                $firstWeek->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
                $firstWeek->setTime($location->timeStart, 'HH:mm:ss');

                $cutOffTime = null;

                if (!empty($location->addOnCutOffTime)) {
                    $cutOffTime = self::parseCutOffTime(
                        $location->addOnCutOffTime,
                        $farm
                    );
                } else {
                    if (is_object($globalCutOffTime)) {
                        $cutOffTime = clone $globalCutOffTime;
                    } else {
                        $cutOffTime = $globalCutOffTime;
                    }
                }

                if (is_numeric($cutOffTime) && $cutOffTime < 0) {
                    // minus days

                    $firstWeek->setTime('00:00:00', 'HH:mm:ss')
                        ->subHour(abs($cutOffTime));

                } if (is_object($cutOffTime) && $cutOffTime instanceof Zend_Date) {
                    $firstWeek->setTime($cutOffTime->getTime());
                }

//                echo '$firstWeek ' . $firstWeek->toString(Zend_Date::ISO_8601) . PHP_EOL;
//                echo '$today->compare($firstWeek) ' . $today->compare($firstWeek) . PHP_EOL;

                if ($today->compare($firstWeek) <= 0) {
                    $firstWeek = clone $shareStartDate;

                    $firstWeek->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601)
                        ->setTime($location->timeStart, 'HH:mm:ss');

                    return $firstWeek;
                }
            }

            if ($endVsToday > 0) {

                $compWeek = HH_Tools_Date::compareYearWeek($today, $shareStartDate);

//                echo 'compWeek prior adjustment loop ' . $compWeek . PHP_EOL;

                while ($shareStartDate->compareDate($shareEndDate) <= 0 && $compWeek !== 0) {

                    // while we are not passed the end week, and today is not in a share duration

                    if ($compWeek > 0) {

                        // start week is less than today, cycle durations till we get to a present share week
                        switch ($share->deliverySchedule) {
                            case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                                $shareStartDate->addWeek(1);
                                break;
                            case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                                $shareStartDate->addWeek(2);
                                break;
                            case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                                $shareStartDate->addMonth(1);
                                $shareStartDate->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
                                break;
                        }

//                        echo 'adjusted start week ' . $shareStartDate->toString(Zend_Date::ISO_8601) . PHP_EOL;

                    } else if ($compWeek < 0) {

                        $today->set(1, Zend_Date::WEEKDAY_8601);
                        $today->setTime('00:00:00', 'HH:mm:ss');

                        $today->addWeek(1);

//                        echo 'adjusted today ' . $today->toString(Zend_Date::ISO_8601) . PHP_EOL;

                    }

                    $compWeek = HH_Tools_Date::compareYearWeek($today, $shareStartDate);

//                    echo 'compWeek after adjustment ' . $compWeek . PHP_EOL;
                }

                if (!HH_Tools_Date::compareYearWeek($today, $shareStartDate) === 0) {
//                    echo 'no validate week' . PHP_EOL;
                    continue;
                }

            } else if ($endVsToday < 0) {
                // share finished
                continue;
            }

//            echo 'location day of week ' . $location->dayOfWeek . PHP_EOL;
//            echo 'global cut off time ' . $globalCutOffTime . PHP_EOL;
//            echo 'location cut off time ' . $location->addOnCutOffTime. PHP_EOL;

            $isLocationThisWeek = self::isLocationThisWeekOrFutureWeek(
                $today,
                $location,
                $farm,
                $globalCutOffTime,
                $share->deliverySchedule
            );

            if ($isLocationThisWeek) {

                $date = clone $today;
                $date->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601)
                    ->setTime($location->timeStart, 'HH:mm:ss');

//                echo ' location this week ' . $date->toString(Zend_Date::ISO_8601) . PHP_EOL;

                return $date;
            }

            // candidate for next week?

            // get next delivery week for share from this week, not next week

            $nextWeekDate = clone $today;
            $nextWeekDate->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);

            switch ($share->deliverySchedule) {
                case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                    $nextWeekDate->addWeek(1);
                    break;
                case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                    $nextWeekDate->addWeek(2);
                    break;
                case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                    $nextWeekDate->addMonth(1);
                    $nextWeekDate->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
                    break;
            }

            $endDateCmp = $nextWeekDate->compareDate(
                $duration->getEndDate(
                    $share->deliverySchedule,
                    null,
                    $customerShare->year
                )
            );

            if ($endDateCmp <= 0) {

//                echo ' next share week ' . $nextWeekDate->toString(Zend_Date::ISO_8601) . PHP_EOL;

                if ($addonWeekDate !== false) {
                    if ($nextWeekDate->compareDate($addonWeekDate) < 0) {
                        $addonWeekDate = $nextWeekDate;
                    }
                } else {
                    $addonWeekDate = $nextWeekDate;
                }
            }
        }

        if ($addonWeekDate !== false) {
            return $addonWeekDate;
        }
    }

    /**
     * Check if location is add on taget for this week
     *
     * @param Zend_Date $today
     * @param HHF_Domain_Customer_Share $customerShare
     * @param HH_Domain_Farm $farm
     * @param Zend_Date|int $globalCutOffTime
     * @return boolean
     */
    protected static function isLocationThisWeek(
        Zend_Date $today,
        HHF_Domain_Location $location,
        HH_Domain_Farm $farm,
        $globalCutOffTime = null
    ) {

//        protected static function isLocationThisWeek(
//            Zend_Date $today,
//            HHF_Domain_Customer_Share $customerShare,
//            HH_Domain_Farm $farm,
//            $globalCutOffTime = null
//        ) {

        // is location this week needs to first look at if the share is scheduled for this week first, then the location

        $locationDate = clone $today;
        $locationDate = $locationDate->set(
            $location->dayOfWeek,
            Zend_Date::WEEKDAY_8601
        );

        $cutOffTime = null;

        if (!empty($location->addOnCutOffTime)) {
            $cutOffTime = self::parseCutOffTime(
                $location->addOnCutOffTime,
                $farm
            );
        } else {
            if (is_object($globalCutOffTime)) {
                $cutOffTime = clone $globalCutOffTime;
            } else {
                $cutOffTime = $globalCutOffTime;
            }
        }

        if (is_numeric($cutOffTime) && $cutOffTime < 0) {
            // minus days

            $cutOffDate = clone $locationDate;
            $cutOffDate = $cutOffDate->setTime('00:00:00', 'HH:mm:ss')
                ->subHour(abs($cutOffTime));

            if ($today->compare($cutOffDate) < 0) {
                return true;
            } else {
                return false;
            }
        }

        $dateCompare = $today->compareDate($locationDate);

        if ($dateCompare < 0) {
            // today is before location date
            return true;

        } else if ($dateCompare == 0) {

            // today is location date?

            $locationDate->setTime($location->timeStart, 'HH:mm:ss');

            if ($today->compareTime($locationDate) < 0) {

                // today is before location start time
                if ($cutOffTime instanceof Zend_Date) {
                    if ($today->compareTime($cutOffTime) <= 0) {

                        // today is before global cut off time!
                        return true;
                    }
                } else {

                    // today is before location start time!
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Check if location is add on taget for this week
     *
     * @param Zend_Date $dateToCheck
     * @param HHF_Domain_Customer_Share $customerShare
     * @param HH_Domain_Farm $farm
     * @param Zend_Date|int $globalCutOffTime
     * @return boolean
     */
    protected static function isLocationThisWeekOrFutureWeek(
        Zend_Date $dateToCheck,
        HHF_Domain_Location $location,
        HH_Domain_Farm $farm,
        $globalCutOffTime = null,
        $deliverySchedule
    ) {

        $cutOffDate = Zend_Date::now();
        $cutOffDate = $cutOffDate->set(
            $location->dayOfWeek,
            Zend_Date::WEEKDAY_8601
        );

        $cutOffTime = null;

        if (!empty($location->addOnCutOffTime)) {
            $cutOffTime = self::parseCutOffTime(
                $location->addOnCutOffTime,
                $farm
            );
        } else {
            if (is_object($globalCutOffTime)) {
                $cutOffTime = clone $globalCutOffTime;
            } else {
                $cutOffTime = $globalCutOffTime;
            }
        }

        if (is_numeric($cutOffTime) && $cutOffTime < 0) {
            // minus days

            $cutOffDate = clone $cutOffDate;
            $cutOffDate = $cutOffDate->setTime('00:00:00', 'HH:mm:ss')
                ->subHour(abs($cutOffTime));

        } if (is_object($cutOffTime) && $cutOffTime instanceof Zend_Date) {
            $cutOffDate->setTime($cutOffTime->getTime());
        }

//        echo '$cutOffDate ' . $cutOffDate->toString(Zend_Date::ISO_8601) . PHP_EOL;

        $futureWeekCheck = clone $cutOffDate;

        switch ($deliverySchedule) {
            case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                $futureWeekCheck->addWeek(1);
                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                $futureWeekCheck->addWeek(2);
                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                $futureWeekCheck->addMonth(1);
                $futureWeekCheck->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
                break;
        }

//        echo '$dateToCheck ' . $dateToCheck->toString(Zend_Date::ISO_8601) . PHP_EOL;
//        echo '$futureWeekCheck ' . $futureWeekCheck->toString(Zend_Date::ISO_8601) . PHP_EOL;
//        echo '$dateToCheck->compare($futureWeekCheck) ' . $dateToCheck->compare($futureWeekCheck) . PHP_EOL;

        if ($dateToCheck->compare($futureWeekCheck) > 0) {
            return true;
        }

        $dateCompare = $dateToCheck->compareDate($cutOffDate);

//        echo '$dateToCheck->compareDate($cutOffDate) ' . $dateCompare . PHP_EOL;

        if ($dateCompare < 0) {
            // today is before location date
            return true;

        } else if ($dateCompare == 0) {

            // today is location date?

            $cutOffDate->setTime($location->timeStart, 'HH:mm:ss');

            if ($dateToCheck->compareTime($cutOffDate) < 0) {

                // today is before location start time
                if ($cutOffTime instanceof Zend_Date) {
                    if ($dateToCheck->compareTime($cutOffTime) <= 0) {

                        // today is before global cut off time!
                        return true;
                    }
                } else {

                    // today is before location start time!
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Parse cut off time into date object or int
     * @param string $time
     * @param HH_Domain_Farm $farm
     * @return Zend_Date|int|null
     */
    protected static function parseCutOffTime($time, HH_Domain_Farm $farm)
    {
        if (!empty($time) &&
            !(is_numeric($time) && $time < 0)) {

            try {
                $timeObject = Zend_Date::now();

                if (!empty($farm->timezone)) {
                    $timeObject->setTimezone($farm->timezone);
                }

                $timeObject->setTime($time, 'HH:mm');

                return $timeObject;
            } catch (Exception $exception) {
                $time = null;
                HH_Error::exceptionHandler($exception, E_USER_WARNING);
            }
        }

        return $time;
    }
}
