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
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Order
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Share.php 695 2014-02-03 12:28:04Z farmnik $
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Order_Share extends HHF_Order
{
    const PAYMENT_PLAN_NONE = 'NONE';
    const PAYMENT_PLAN_WEEKLY = 'WEEKLY';
    const PAYMENT_PLAN_MONTHLY = 'MONTHLY';
    const PAYMENT_PLAN_FIXED = 'FIXED';
    
    public static $paymentPlans = array(
        self::PAYMENT_PLAN_NONE,
        self::PAYMENT_PLAN_WEEKLY,
        self::PAYMENT_PLAN_MONTHLY,
        self::PAYMENT_PLAN_FIXED
    );

    /**
     * Payment plan type
     * 
     * @var HHF_Order_Share_PaymentPlan
     */
    protected $_paymentPlan = null;
    
    /**
     * @var Zend_Date
     */
    protected $_purchaseDate = null;

    protected $_startDate = array();
    
    protected $_endDate = array();
    
    protected $_deliveryTotal = array();

    protected $_administrativeTotal = array();

    protected $_locationsPaidDates = array();
    
    /**
     * Order constructor
     */
    public function __construct(HH_Domain_Farm $farm, $items = array(), 
        $payment = HHF_Domain_Transaction::TYPE_CASH, 
        $paymentPlan = self::PAYMENT_PLAN_NONE, $orderDate = null)
    {
        parent::__construct($farm, $items, $payment, $orderDate);
        
        $this->setPaymentPlan($paymentPlan);
        
        $this->_initPaymentPlans();
    }

    /**
     * Set the payment plan
     * 
     * @param type $plan
     * @return HHF_Order_Share
     */
    public function setPaymentPlan($plan)
    {
        $this->_paymentPlan = HHF_Order_Share_PaymentPlan::factory(
            $this, 
            $plan
        );
        
        $this->_cache++;
        return $this;
    }
    
    /**
     * Payment Plan
     * 
     * @return HHF_Order_Share_PaymentPlan
     */
    public function getPaymentPlan()
    {
        return $this->_paymentPlan;
    }
    
    /**
     * Add item to order
     * 
     * @param HHF_Order_Item $item
     * @return \HHF_Order_Share
     */
    public function addItem(HHF_Order_Item $item)
    {
        parent::addItem($item);
        
        $this->_initPaymentPlans();
        
        return $this;
    }
    
    protected function _initPaymentPlans()
    {
        $year = null;
        $fixedDates = null;
        
        foreach ($this->_items as $item) {
            /* @var $item HHF_Order_Item_Share */
            $shareYear = $item->getShare()->year;
            
            if ($shareYear > $year) {
                $year = $shareYear;
            }
            
            if (!empty($item->getShare()->planFixedDates)) {
                $fixedDates = HHF_Order_Share_PaymentPlan_Fixed::mergeDates(
                    $fixedDates,
                    $item->getShare()->planFixedDates
                );
            }
        }
        
        if (empty($fixedDates)) {
            $fixedDates = $this->_farm->getPreferences()->
                get('plansFixedDates', 'shares', false);
        }
        
        HHF_Order_Share_PaymentPlan_Fixed::setDates(
            $fixedDates, 
            Zend_Date::now(),
            $year
        );
    }
    
    public function createCustomerShares($payment)
    {
        $this->_getLocationPaidDates();

        foreach ($this as $item) {
            $item->createCustomerShare($payment);
        }
    }
    
    /**
     * get earliest start date across all items
     * 
     * @param Zend_Date $date
     * @return Zend_Date 
     */
    public function getStartDate()
    {
        if (isset($this->_startDate[$this->_cache])) {
            return $this->_startDate[$this->_cache];
        }
        
        $earliest = null;
        
        foreach ($this->_items as $item) {
            /* @var $item HHF_Order_Item_Share */
            $itemStartDate = $item->getStartDate();
            
            if ($earliest === null) {
                $earliest = clone $itemStartDate;
            } else if ($earliest->compare($itemStartDate) > 0) {
                $earliest = clone $itemStartDate;
            }
        }
        
        $this->_startDate[$this->_cache] = $earliest;
        
        return $earliest;
    }
    
    /**
     * Get greatest end date across all items
     * 
     * @return Zend_Date
     */
    public function getEndDate()
    {
        if (isset($this->_endDate[$this->_cache])) {
            return $this->_endDate[$this->_cache];
        }
        
        $end = Zend_Date::now();
        
        foreach ($this->_items as $item) {
            /* @var $item HHF_Order_Item_Share */
            $itemEnd = $item->getEndDate();
            if ($end->compare($itemEnd) < 0) {
                $end = clone $itemEnd;
            }
        }
        
        $this->_endDate[$this->_cache] = $end;
        
        return $end;
    }
    
    public function getDownPaymentCost()
    {
        $downPayment = 0;
        
        if ($this->order->getPaymentPlan()->canHaveInstalments() 
            && $this->order->getPaymentPlan()->count()) {

            foreach ($this->_items as $item) {
                /* @var $item HHF_Order_Item_Share */
                $downPayment += $item->getDownPaymentCost();
            }
        }
        
        return $downPayment;
    }

    /**
     * Get administrative total for order
     *
     * @return float
     */
    public function getAdministrativeTotal()
    {
        if (isset($this->_administrativeTotal[$this->_cache])) {
            return $this->_administrativeTotal[$this->_cache];
        }

        $total = 0;

        $adminFee = $this->_farm->getPreferences()->get('adminFee', 'shares', null);

        if (is_numeric($adminFee) && !empty($adminFee)) {

            // check if admin fee applied
            $applicableYears = array();
            $nonApplicableYears = array();
            $targetYears = array();

            if ($this->_customer instanceof HHF_Domain_Customer) {

                foreach ($this->_items as $item) {

                    $checkYear = $item->getShare()->year;

                    if (!in_array($checkYear, $targetYears)) {
                        $targetYears[] = $checkYear;
                    }
                }

                foreach ($targetYears as $targetYear) {

                    $customerShares = HHF_Domain_Customer_Share::fetch(
                        $this->getFarm(),
                        array(
                            'where' => array(
                                'customerId' => $this->_customer->id,
                                'year' => $targetYear
                            )
                        )
                    );

                    if ($customerShares->count()) {
                        foreach ($customerShares as $customerShare) {

                            $invoices = $customerShare->getInvoices();

                            if (!empty($invoices) && $invoices->count()) {
                                foreach ($invoices as $invoice) {
                                    $lines = $invoice->getLines();

                                    if (!empty($lines) && $lines->count()) {

                                        foreach ($lines as $line) {
                                            if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_ADMINISTRATION) {
                                                if (!in_array($targetYear, $nonApplicableYears)) {
                                                    $nonApplicableYears[] = $targetYear;
                                                }
                                            }
                                        }
                                    }
                                }
                            }
                        }
                    }

                    if (!in_array($targetYear, $nonApplicableYears) && !in_array($targetYear, $applicableYears)) {
                        $applicableYears[] = $targetYear;
                    }
                }

            } else {
                foreach ($this->_items as $item) {

                    $checkYear = $item->getShare()->year;

                    if (!in_array($checkYear, $applicableYears)) {
                        $applicableYears[] = $checkYear;
                    }
                }
            }

            if (!empty($applicableYears)) {
                $total = round(
                    ((float) $adminFee) * count($applicableYears),
                    2,
                    PHP_ROUND_HALF_UP
                );
            }
        }

        $this->_administrativeTotal[$this->_cache] = $total;

        return $total;
    }

    /**
     * Get delivery total for order
     * 
     * @return float
     */
    public function getDeliveryTotal()
    {
        if (isset($this->_deliveryTotal[$this->_cache])) {
            return $this->_deliveryTotal[$this->_cache];
        }
        
        $total = 0;
        
        $locations = array();
        
        foreach ($this->_items as $item) {
            
            /* @var $item HHF_Order_Item_Share */
            $share = $item->getShare();
            
            if (empty($share->locationPrice)) {
                continue;
            }
            
            $location = $item->getLocation();

            if (isset($locations[$location->id])) {

                $locations[$location->id]['dates'] = $this->_getLocationDates(
                    $item,
                    $locations[$location->id]['dates']
                );
                
            } else {
                
                $locations[$location->id] = array(
                    'pricePerDelivery' => $location['pricePerDelivery'],
                    'dates' => $this->_getLocationDates($item)
                );
            }
        }
        
        if (count($locations)) {
            foreach ($locations as $location) {
                $total += round(
                    (count($location['dates'])) * (float) $location['pricePerDelivery'], 
                    2, 
                    PHP_ROUND_HALF_UP
                );
            }
        }
        
        $this->_deliveryTotal[$this->_cache] = $total;
        
        return $total;
    }

    protected function _getLocationPaidDates()
    {
        if (!($this->_customer instanceof HHF_Domain_Customer)) {
            return array();
        }

        if (isset($this->_locationsPaidDates[$this->_itemCache])) {
            return $this->_locationsPaidDates[$this->_itemCache];
        }

        $this->_locationsPaidDates[$this->_itemCache] = array();

        foreach ($this->_items as $item) {

            $location = $item->getLocation();

            if (!array_key_exists($location->id, $this->_locationsPaidDates[$this->_itemCache])) {
                $this->_locationsPaidDates[$this->_itemCache][$location->id] = array();
            }

            // check for existing location in order
            $customerShares = HHF_Domain_Customer_Share::fetch(
                $this->getFarm(),
                array(
                    'where' => array(
                        'customerId' => $this->_customer->id,
                        'locationId' => $location->id,
                        'year' => $item->getShare()->year
                    )
                )
            );

            if ($customerShares->count()) {
                foreach ($customerShares as $customerShare) {

                    $deliverySchedule = $customerShare->getShare()->deliverySchedule;

                    $iteratorDate = clone $item->getStartDate();
                    $endDate = clone $item->getEndDate();

                    list($startYear, $startWeek) = explode('W', $customerShare->startWeek);

                    $iteratorDate->set($startYear, Zend_Date::YEAR_8601)
                        ->set($startWeek, Zend_Date::WEEK);

                    list($endYear, $endWeek) = explode('W', $customerShare->endWeek);

                    $endDate->set($endYear, Zend_Date::YEAR_8601)
                        ->set($endWeek, Zend_Date::WEEK);

                    while (HH_Tools_Date::compareYearWeek($iteratorDate, $endDate) <= 0) {

                        $this->_locationsPaidDates[$this->_itemCache][$location->id][$iteratorDate->toString('YYYY-w')] = true;

                        switch ($deliverySchedule) {
                            case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                                $iteratorDate->addWeek(1);
                                break;
                            case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                                $iteratorDate->addWeek(2);
                                break;
                            case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                                $iteratorDate->addMonth(1);
                                break;
                        }
                    }

                }
            }
        }

        return $this->_locationsPaidDates[$this->_itemCache];
    }

    protected function _getLocationDates(HHF_Order_Item_Share $item, $existingDates = array())
    {
        $locationsPaidDates = $this->_getLocationPaidDates();

        $paidDates = (!empty($locationsPaidDates[$item->getLocation()->id]))
            ? $locationsPaidDates[$item->getLocation()->id] : array() ;

        $iteratorDate = clone $item->getStartDate();
        $endDate = $item->getEndDate();
        $deliverySchedule = $item->getShare()->deliverySchedule;

        while (HH_Tools_Date::compareYearWeek($iteratorDate, $endDate) <= 0) {

            $week = $iteratorDate->toString('YYYY-w');

            if (!isset($paidDates[$week])) {
                $existingDates[$week] = true;
            }

            switch ($deliverySchedule) {
                case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                    $iteratorDate->addWeek(1);
                    break;
                case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                    $iteratorDate->addWeek(2);
                    break;
                case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                    $iteratorDate->addMonth(1);
                    break;
            }
        }

        return $existingDates;
    }

    /**
     * Get order total
     * 
     * @return float 
     */
    public function getTotal()
    {
        if (isset($this->_total[$this->_cache])) {
            return $this->_total[$this->_cache];
        }
        
        $total = 0;
        
        foreach ($this->_items as $item) {
            /* @var $item HHF_Order_Item_Share */
            $total += $item->getFullSharePrice();
        }
        
        $total += $this->getDeliveryTotal();
        $total += $this->getAdministrativeTotal();

        if ($total < 0) {
            $total = 0;
        }
        
        $this->_total[$this->_cache] = $total;
        
        return $total;
    }
}
