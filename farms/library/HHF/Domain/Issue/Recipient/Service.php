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
 * @copyright $Date: 2015-07-24 16:00:45 -0300 (Fri, 24 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of issue recipient service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 912 2015-07-24 19:00:45Z farmnik $
 * @copyright $Date: 2015-07-24 16:00:45 -0300 (Fri, 24 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Issue_Recipient_Service extends HH_Object_Service
{
    protected $_excludeOptOut = true;

    public function getCustomers($excludeOptOut = true)
    {
        $this->_excludeOptOut = $excludeOptOut;
        $method = '_resolve' . ucwords(strtolower($this->_object['list']));
        $params = Zend_Json::decode($this->_object['params']);

        if (!is_callable(array($this, $method))) {
            return array();
        }

        $customers = call_user_func_array(
            array($this, $method),
            (!empty($params) ? $params : array())
        );

        return $this->_buildCustomerList($customers);
    }

    public function getEmails($excludeOptOut = true)
    {
        $this->_excludeOptOut = $excludeOptOut;
        $method = '_resolve' . ucwords(strtolower($this->_object['list']));
        $params = Zend_Json::decode($this->_object['params']);

        if (!is_callable(array($this, $method))) {
            return array();
        }

        $customers = call_user_func_array(
            array($this, $method),
            (!empty($params) ? $params : array())
        );

        return $this->_buildEmailList($customers);
    }

    /**
     * All customers
     *
     * @return HHF_Object_Collection_Db
     */
    protected function _resolveAll()
    {
        $customers = HHF_Domain_Customer::fetch(
            $this->_object->getFarm(),
            array(
                'columns' => '*',
                'where' => array(
                    'enabled' => 1
                )
            )
        );

        if ($this->_excludeOptOut) {
            $optOutIds = $this->_getOptOutCustomers();

            foreach ($customers as $key => $customer) {
                if (isset($optOutIds[(string) $customer['id']])) {
                    unset($customers[$key]);
                }
            }
        }

        return $customers;
    }

    /**
     * By Share and year
     *
     * @param $shareId
     * @param $year
     * @param $locationId Optional location
     *
     * @return array|HHF_Object_Collection_Db
     */
    protected function _resolveShare($shareId, $year, $locationId = null)
    {
        $where = array(
            'shareId' => $shareId,
            'year' => $year
        );

        if (!empty($locationId)) {
            $where['locationId'] = $locationId;
        }

        $customerShares = HHF_Domain_Customer_Share::fetch(
            $this->_object->getFarm(),
            array(
                'where' => $where
            )
        );

        $customerIds = array();

        if ($this->_excludeOptOut) {
            $optOutIds = $this->_getOptOutCustomers();
        }

        foreach ($customerShares as $customerShare) {
            if ($this->_excludeOptOut && isset($optOutIds[(string) $customerShare['customerId']])) {
                continue;
            }

            if ($this->_wasShareCanceled($customerShare)) {
                continue;
            }
            $customerIds[$customerShare['customerId']] = true;
        }

        if (empty($customerIds)) {
            return array();
        }

        return HHF_Domain_Customer::fetch(
            $this->_object->getFarm(),
            array(
                'columns' => '*',
                'where' => array(
                    'id IN(' . implode(',', array_keys($customerIds)) . ')',
                    'enabled' => 1
                )
            )
        );
    }

    /**
     * By Location and year
     *
     * @param $locationId
     * @param $year
     *
     * @return array|HHF_Object_Collection_Db
     */
    protected function _resolveLocation($locationId, $year)
    {
        $customerShares = HHF_Domain_Customer_Share::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'locationId' => $locationId,
                    'year' => $year
                )
            )
        );

        $customerIds = array();

        if ($this->_excludeOptOut) {
            $optOutIds = $this->_getOptOutCustomers();
        }

        foreach ($customerShares as $customerShare) {
            if ($this->_excludeOptOut && isset($optOutIds[(string) $customerShare['customerId']])) {
                continue;
            }

            if ($this->_wasShareCanceled($customerShare)) {
                continue;
            }
            $customerIds[$customerShare['customerId']] = true;
        }

        if (empty($customerIds)) {
            return array();
        }

        return HHF_Domain_Customer::fetch(
            $this->_object->getFarm(),
            array(
                'columns' => '*',
                'where' => array(
                    'id IN(' . implode(',', array_keys($customerIds)) . ')',
                    'enabled' => 1
                )
            )
        );
    }

    /**
     * By share start week
     *
     * @param $week
     * @param $year
     *
     * @return array|HHF_Object_Collection_Db
     */
    protected function _resolveStartweek($week, $year)
    {
        $durations = HHF_Domain_Share_Duration::fetch(
            $this->_object->getFarm(),
            array(
                'columns' => array(
                    'id',
                    'startWeek',
                    'shareId'
                ),
                'where' => array(
                    'startWeek' => $week
                )
            )
        );

        $durationIds = array();

        foreach ($durations as $duration) {
            $durationIds[] = $duration['id'];
        }

        if (empty($durationIds)) {
            return array();
        }

        $customerShares = HHF_Domain_Customer_Share::fetch(
            $this->_object->getFarm(),
            array(
                'columns' => array(
                    'customerId'
                ),
                'where' => array(
                    'shareDurationId IN(' . implode(',', $durationIds) . ')',
                    'year' => $year
                )
            )
        );

        $customerIds = array();

        if ($this->_excludeOptOut) {
            $optOutIds = $this->_getOptOutCustomers();
        }

        foreach ($customerShares as $customerShare) {
            if ($this->_excludeOptOut && isset($optOutIds[(string) $customerShare['customerId']])) {
                continue;
            }

            if ($this->_wasShareCanceled($customerShare)) {
                continue;
            }
            $customerIds[$customerShare['customerId']] = true;
        }

        if (empty($customerIds)) {
            return array();
        }

        return HHF_Domain_Customer::fetch(
            $this->_object->getFarm(),
            array(
                'columns' => '*',
                'where' => array(
                    'id IN(' . implode(',', array_keys($customerIds)) . ')',
                    'enabled' => 1
                )
            )
        );
    }

    /**
     * Was this customer share canceled?
     *
     * @param HHF_Domain_Customer_Share $customerShare
     * @return bool
     */
    protected function _wasShareCanceled(HHF_Domain_Customer_Share $customerShare)
    {
        $share = $customerShare->getShare();

        if (!($share instanceof HHF_Domain_Share) || empty($customerShare['endWeek'])) {
            return false;
        }

        $shareDuration = $share->getDurationById($customerShare['shareDurationId']);

        if (!($shareDuration instanceof HHF_Domain_Share_Duration)) {
            return false;
        }

        $shareEndDate = $shareDuration->getEndDate(
            $share['deliverySchedule'],
            null,
            $customerShare['year']
        );

        list($customerEndYear, $customerEndWeek) = explode('W', $customerShare['endWeek']);

        $endYear = $shareEndDate->get(Zend_Date::YEAR_8601);
        $endWeek = $shareEndDate->get(Zend_Date::WEEK);

        if ($customerEndYear < $endYear) {
            return true;
        }

        return false;
    }

    protected function _getOptOutCustomers()
    {
        static $customers = null;

        if (is_array($customers)) {
            return $customers;
        }

        $database = Bootstrap::getZendDb();

        $sql = 'SELECT
            customerId
        FROM
            ' . HHF_Object_Collection_Db::_getStaticDatabase('HHF_Domain_Preference', $this->_object->getFarm()) . '
        WHERE
            `type` = ?
        AND
            `resource` = ?
        AND
            `key` = ?
        AND
            `value` = ?';

        $customers = $database->fetchCol(
            $sql,
            array(
                HHF_Domain_Preference::TYPE_CUSTOMER,
                'newsletter',
                'optOut',
                '1'
            )
        );

        $customers = array_flip(array_values($customers));

        return $customers;
    }

    protected function _buildCustomerList($rawCustomers)
    {
        $customers = array();

        foreach ($rawCustomers as $customer) {
            if (isset($customers[$customer['id']])) {
                continue;
            }

            $customers[$customer['id']] = $customer;
        }

        return $customers;
    }


    protected function _buildEmailList($customers)
    {
        $emails = array();
        $setEmails = array();
        $validateEmail = new Zend_Validate_EmailAddress();

        foreach ($customers as $customer) {
            $name = '';

            if (!empty($customer['email'])) {
                if (!isset($setEmails[$customer['email']])) {
                    if ($validateEmail->isValid($customer['email'])) {
                        $email = array(
                            $customer['email']
                        );

                        if (isset($customer['firstName'])) {
                            $name = trim($customer['firstName'] . ' ' . $customer['lastName']);
                        } else if (isset($customer['lastName'])) {
                            $name = trim($customer['lastName']);
                        }

                        if (!empty($name)) {
                            $email[] = $name;
                        }

                        $emails[] = $email;

                        $setEmails[$customer['email']] = true;
                    }
                }

            }

            if (!empty($customer['secondaryEmail'])) {
                if (!isset($setEmails[$customer['secondaryEmail']])) {
                    if ($validateEmail->isValid($customer['secondaryEmail'])) {

                        $email = array(
                            $customer['secondaryEmail']
                        );

                        if (isset($customer['secondaryFirstName'])) {
                            $name = trim($customer['secondaryFirstName'] . ' ' . $customer['secondaryLastName']);
                        } else if (isset($customer['secondaryLastName'])) {
                            $name = trim($customer['secondaryLastName']);
                        }

                        if (!empty($name)) {
                            $email[] = $name;
                        }

                        $emails[] = $email;

                        $setEmails[$customer['secondaryEmail']] = true;
                    }
                }

            }

        }

        return $emails;
    }
}
