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
 * @copyright $Date: 2013-08-17 11:28:08 -0300 (Sat, 17 Aug 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Order
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Share.php 676 2013-08-17 14:28:08Z farmnik $
 * @copyright $Date: 2013-08-17 11:28:08 -0300 (Sat, 17 Aug 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Order_Item_Share extends HHF_Order_Item
{
    /**
     * @var HHF_Order_Share
     */
    protected $_order;
    
    /**
     * @var HHF_Domain_Share 
     */
    protected $_share;
    
    /**
     * @var HHF_Domain_Share_Duration
     */
    protected $_duration;
    
    /**
     * @var HHF_Domain_Share_Size
     */
    protected $_size;
    
    /**
     * @var HHF_Domain_Location
     */
    protected $_location;
    
    /**
     * Upfront payment amount
     * 
     * @var float
     */
    protected $_downPayment = null;
    
    /**
     * @var Zend_Date
     */
    protected $_startDate = null;
    
    /**
     * @var HHF_Domain_Customer_Share
     */
    protected $_customerShare;
    
    /**
     * Costs constructor
     */
    public function __construct(HH_Domain_Farm $farm, $shareId = null, 
        $durationId = null, $sizeId = null, $locationId = null, 
        $quantity = 1, $year = null)
    {
        parent::__construct($farm, $quantity);
        
        if ($shareId !== null) {
            $this->setShare($shareId, $durationId, $sizeId, $locationId);
        }
        
        $this->setStartDate(null, true, $year);
    }
    
    /**
     * Set share details
     * 
     * @param type $shareId
     * @param type $durationId
     * @param type $sizeId
     * @param type $locationId
     * @param type $paymentPlan
     * @return HHF_Order_Item_Share
     * @throws HHF_Order_Exception_InvalidShareData
     */
    public function setShare($shareId, $durationId, $sizeId, $locationId)
    {
        if ($shareId instanceof HHF_Domain_Share) {
            $this->_share = $shareId;
        } else {
            $this->_share = new HHF_Domain_Share($this->_farm, $shareId);
        }
        
        if ($this->_share->isEmpty()) {
            throw new HHF_Order_Exception_InvalidShareData(
                $shareId, 
                HHF_Order_Exception_InvalidShareData::TYPE_SHARE
            );
        }
        
        $this->_duration = $this->_share->getDurationById($durationId);
        
        if ($this->_duration === null) {
            throw new HHF_Order_Exception_InvalidShareData(
                $durationId, 
                HHF_Order_Exception_InvalidShareData::TYPE_DURATION
            );
        }
        
        $this->_size = $this->_share->getSizeById($sizeId);
        
        if ($this->_size === null) {
            throw new HHF_Order_Exception_InvalidShareData(
                $sizeId, 
                HHF_Order_Exception_InvalidShareData::TYPE_SIZE
            );
        }
        
        $this->_location = new HHF_Domain_Location(
            $this->_farm, 
            $locationId
        );
        
        if ($this->_location->isEmpty()) {
            throw new HHF_Order_Exception_InvalidShareData(
                $locationId, 
                HHF_Order_Exception_InvalidShareData::TYPE_LOCATION
            );
        }
        
        return $this;
    }
    
    /**
     * Set the start date for this order
     * 
     * @param Zend_Date $date
     * @param boolean $calculate If calculating, get the best possible start date
     * @return HHF_Order_Item_Share 
     */
    public function setStartDate(Zend_Date $date = null, $calculate = false,
        $year = null)
    {
        if ($date === null) {
            $date = Zend_Date::now();
        } else {
            $date = clone $date;
        }
        
        if ($calculate && $this->_location !== null && $this->_duration !== null) {
            
            $startDate = $this->_duration->getStartDate(
                $this->_location, 
                $year
            );
            $endDate = $this->_duration->getEndDate(
                $this->_share->deliverySchedule, 
                $this->_location,
                $year
            );
            
            if ($date->compareDate($startDate) == -1) {
                // before startDate, take the real start date
                $date = clone $startDate;
                
            } else if ($date->compareDate($endDate) == -1 && $date->compareDate($startDate) >= 0) {
                
                // in share period, grab next share week
                switch ($this->_share->deliverySchedule) {
                    case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                        $date->addWeek(1);
                        break;
                    case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                        $date->addWeek(2);
                        break;
                    case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                        $date->addMonth(1);
                        break;
                }

                $properWeek = $date->get(Zend_Date::WEEK);

                $date->set(
                    $startDate->get(Zend_Date::WEEKDAY_8601),
                    Zend_Date::WEEKDAY_8601
                );

                $adjustedDays = 0;

                if ($properWeek < $date->get(Zend_Date::WEEK)) {

                    while ($properWeek != $date->get(Zend_Date::WEEK)) {
                        $date->subDay(1);
                        if (++$adjustedDays >= 7) {
                            trigger_error('Adjusted by too many days', E_USER_WARNING);
                            break;
                        }
                    }

                } else if ($properWeek > $date->get(Zend_Date::WEEK)) {

                    while ($properWeek != $date->get(Zend_Date::WEEK)) {
                        $date->addDay(1);
                        if (++$adjustedDays >= 7) {
                            trigger_error('Adjusted by too many days', E_USER_WARNING);
                            break;
                        }
                    }
                }
                
            } else if ($date->compare($endDate) >= 0) {
                
                // after share period, grab next year
                $date = $this->_duration->getStartDate(
                    $this->_location, 
                    $date->get(Zend_Date::YEAR) + 1
                );
            }
        }
        
        $this->_startDate = $date;
        
        return $this;
    }
    
    /**
     * @return HHF_Domain_Share
     */
    public function getShare()
    {
        return $this->_share;
    }
    
    /**
     * @return HHF_Domain_Share_Duration
     */
    public function getDuration()
    {
        return $this->_duration;
    }
    
    /**
     * @return HHF_Domain_Share_Size
     */
    public function getSize()
    {
        return $this->_size;
    }
    
    /**
     * @return HHF_Domain_Location
     */
    public function getLocation()
    {
        return $this->_location;
    }

    /**
     * Set downpayment percent
     * 
     * @param float $upFront
     * @return HHF_Order_Item_Share 
     */
    public function setDownPayment($downPayment = null)
    {
        $this->_downPayment = $downPayment;
        
        return $this;
    }
    
    /**
     * Get start date for share
     * 
     * @return Zend_Date
     */
    public function getStartDate()
    {
        return $this->_startDate;
    }
    
    /**
     * Get end date of share
     * 
     * @return Zend_Date
     */
    public function getEndDate()
    {
        return $this->_duration->getEndDate(
            $this->_share->deliverySchedule, 
            $this->_location,
            $this->_share->year
        );
    }
    
    /**
     * Get the cost for a single delivery
     * 
     * @return float
     */
    public function getCostPerDelivery()
    {
        $price = (float) $this->_size->pricePerDelivery;
        
        $discount = 0.00;
        
        if ($this->_order->getPaymentPlan() 
            instanceof HHF_Order_Share_PaymentPlan_None) {

            $discount = (float) $this->getSize()->fullPaymentDiscount;
        }
        
        if (!empty($discount)) {
            $price = round($price - $discount, 2);
            
            if ($price < 0) {
                $price = 0;
            }
        }
        
        $price = $price * $this->getQuantity();
        
        return $price;
    }
    
    /**
     * Get full share price for all weeks
     * 
     * @return float 
     */
    public function getFullSharePrice()
    {
        $pricePerShare = $this->getCostPerDelivery();
        $iterations = $this->getIterations();
        
        return round($pricePerShare * $iterations, 2, PHP_ROUND_HALF_UP);
    }
    
    /**
     * Number of share iterations from start date
     * 
     * @return int 
     */
    public function getIterations()
    {
        $totalIterations = $this->_duration->iterations;
        $iterations = 0;
        
        $endDate = $this->getEndDate();
        $startDate = $this->getStartDate();

        switch ($this->_share->deliverySchedule) {
            case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                // count weeks between start and stop
                if ($endDate->get(Zend_Date::YEAR_8601) > $startDate->get(Zend_Date::YEAR_8601)) {
                    $iterations = $endDate->get(Zend_Date::WEEK);
                    $iterations += (
                        HH_Tools_Date::weeksInYear(
                            $startDate->get(Zend_Date::YEAR_8601)
                        ) - 
                        $startDate->get(Zend_Date::WEEK) + 1
                    );
                } else {
                    $endWeek = $endDate->get(Zend_Date::WEEK);
                    
                    if ($endWeek == 1) {
                        $endWeek = HH_Tools_Date::weeksInYear(
                            $endDate->get(Zend_Date::YEAR_8601)
                        ) + 1;
                    }
                    
                    $iterations = $endWeek - 
                        $startDate->get(Zend_Date::WEEK) + 1;
                }
                
                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                // count event 2 weeks between start and stop
                if ($endDate->get(Zend_Date::YEAR_8601) > $startDate->get(Zend_Date::YEAR_8601)) {
                    $iterations = $endDate->get(Zend_Date::WEEK);
                    $iterations += (
                        HH_Tools_Date::weeksInYear(
                            $startDate->get(Zend_Date::YEAR_8601)
                        ) - 
                        $startDate->get(Zend_Date::WEEK) + 1
                    );
                } else {
                    $endWeek = $endDate->get(Zend_Date::WEEK);
                    
                    if ($endWeek == 1) {
                        $endWeek = HH_Tools_Date::weeksInYear(
                            $endDate->get(Zend_Date::YEAR_8601)
                        ) + 1;
                    }
                    
                    $iterations = $endWeek - 
                        $startDate->get(Zend_Date::WEEK) + 1;
                }
                $iterations = round($iterations / 2, 0, PHP_ROUND_HALF_UP);
                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                // count months between start and stop
                if ($endDate->get(Zend_Date::YEAR_8601) > $startDate->get(Zend_Date::YEAR_8601)) {
                    $iterations = $endDate->get(Zend_Date::MONTH_SHORT);
                    $iterations += (
                        13 - $startDate->get(Zend_Date::MONTH_SHORT)
                    );
                } else {
                    $endMonth = $endDate->get(Zend_Date::MONTH_SHORT);
                    
                    if ($endMonth == 1) {
                        $endMonth = 12 + 1;
                    }

                    $startMonth = $startDate->get(Zend_Date::MONTH_SHORT);

                    if ($startMonth == 12) {
                        $startMonth = 1;
                    }
                    
                    $iterations = $endMonth -
                        $startMonth + 1;
                }
                break;
        }
        
        if ($iterations > $totalIterations) {
            trigger_error(
                "Total iterations ($totalIterations) is less than $iterations",
                E_USER_WARNING
            );
            
            $iterations = $totalIterations;
        }
        
        return $iterations;
    }
    
    /**
     * Has down payment plan
     * 
     * @return boolean
     */
    public function hasDownPayment()
    {
        if ($this->_order->getPaymentPlan()->canHaveInstalments() 
            && !empty($this->_downPayment)) {
            
            return true;
        }
        
        return false;
    }
    
    /**
     * Get downpayment cost
     * 
     * @return float
     */
    public function getDownPaymentCost()
    {
        if (!$this->_order->getPaymentPlan()->canHaveInstalments() 
            && !empty($this->_downPayment)) {
            
            return 0;
        }
        
        $total = $this->getFullSharePrice();
            
        return round($total * $this->_downPayment, 2, PHP_ROUND_HALF_UP);
    }
    
    /**
     * @return HHF_Domain_Customer_Share
     */
    public function getCustomerShare()
    {
        return $this->_customerShare;
    }
    
    public function createCustomerShare($payment)
    {
        $this->_customerShare = new HHF_Domain_Customer_Share(
            $this->_order->getFarm()
        );

        $this->_customerShare->insert(
            array(
                'customerId' => $this->_order->getCustomer()->id,                            
                'shareId' => $this->getShare()->id,
                'shareDurationId' => $this->getDuration()->id,
                'shareSizeId' => $this->getSize()->id,
                'locationId' => $this->getLocation()->id,
                'quantity' => $this->getQuantity(),
                'year' => $this->getShare()->year,
                'startWeek' => $this->getStartDate()->toString('YYYYWww'),
                'endWeek' => $this->getEndDate()->toString('YYYYWww'),
                'payment' => $payment,
                'paymentPlan' => (string) $this->_order->getPaymentPlan(),
                'paidInFull' => 0
            )
        );
        
        return $this->_customerShare;
    }
}
