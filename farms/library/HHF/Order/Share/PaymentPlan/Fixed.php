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
 * Description of Fixed
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Fixed.php 695 2014-02-03 12:28:04Z farmnik $
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Order_Share_PaymentPlan_Fixed extends HHF_Order_Share_PaymentPlan
{
    protected static $_fixedDates = array();
    
    /**
     * Set dates that are part of this payment plan
     * 
     * @param string $dates
     * @param Zend_Date $orderDate 
     */
    public static function setDates($dates, $orderDate, $purchaseYear = null)
    {
        self::$_fixedDates = array();
        
        foreach (self::_parseDates($dates, $purchaseYear) as $date) {
                
            $foundDate = false;

            foreach (self::$_fixedDates as $fixedDate) {
                if ($fixedDate['day'] == $date['day'] 
                    && $fixedDate['month'] == $date['month'] 
                    && $fixedDate['year'] == $date['year']) {

                    $foundDate = true;
                    break;
                }
            }

            if (!$foundDate) {
                self::$_fixedDates[] = array(
                    'day' => $date['day'],
                    'month' => $date['month'],
                    'year' => $date['year'],
                    'enabled' => true
                );
            }
        }
        
        self::_setEnabledFixedPaymentDates($orderDate);
    }
    
    public static function mergeDates($original, $new, $purchaseYear = null)
    {
        $mergedDates = array_merge(
            self::_parseDates($original, $purchaseYear),
            self::_parseDates($new, $purchaseYear)
        );
        
        foreach ($mergedDates as $key => $date) {
            $mergedDates[$key] = $date['year'] . '-' . $date['month'] . '-' . $date['day'];
        }
        
        array_unique($mergedDates);
        
        usort($mergedDates, function($a, $b) {
            $x = new DateTime($a);
            $y = new DateTime($b);
            
            if ($x < $y) {
                return -1;
            } else if ($x > $y) {
                return 1;
            }
            
            return 0;
        });
        
        return implode(',', $mergedDates);
    }
    
    protected static function _parseDates($dates, $purchaseYear = null)
    {
        $parsedDates = array();
        
        if (is_string($dates)) {
            $dates = explode(',', $dates);
            
            $previousMonth = 0;
            
            foreach ($dates as $date) {
                $datePieces = explode('-', $date);
                
                if (count($datePieces) == 2) {
                    $day = trim($datePieces[1]);
                    $month = trim($datePieces[0]);
                    $year = null;
                } else if (count($datePieces) == 3) {
                    $day = trim($datePieces[2]);
                    $month = trim($datePieces[1]);
                    $year = trim($datePieces[0]);
                } else {
                    continue;
                }
                
                if (empty($year)) {
                
                    if ($previousMonth > $month) {
                        $year = (empty($purchaseYear)) 
                            ? date('Y') + 1 : $purchaseYear + 1;
                    } else {
                        $year = (empty($purchaseYear)) ? date('Y') : $purchaseYear;
                    }
                }
                
                $parsedDates[] = array(
                    'day' => $day,
                    'month' => $month,
                    'year' => $year,
                );
                
                $previousMonth = $month;
            }
        }
        
        return $parsedDates;
    }
    
    protected static function _setEnabledFixedPaymentDates($orderDate)
    {
        if (empty(self::$_fixedDates)) {
            return;
        }
        
        if ($orderDate instanceof Zend_Date) {
            foreach (self::$_fixedDates as &$date) {
                $dateObj = new Zend_Date(
                    $date['year'] . '-' . $date['month'] . '-' . $date['day'],
                    'yyyy-MM-dd'
                );
                
                if ($orderDate->compareDate($dateObj) >= 0) {
                    $date['enabled'] = false;
                } else if ($orderDate->compareMonth($dateObj) == 0 
                    && $orderDate->compareYear($dateObj) == 0) {
                    
                    if ($date['day'] < $orderDate->sub(7, Zend_Date::DAY)->get(Zend_Date::DAY_SHORT)) {
                        $date['enabled'] = false;
                    } else {
                        $date['enabled'] = true;
                    }
                } else {
                    $date['enabled'] = true;
                }
            }
        } else {
            foreach (self::$_fixedDates as &$date) {
                $date['enabled'] = true;
            }
        }
    }
    
    /**
     * Get fixed payment dates
     * @param boolean $enabled
     * @return array
     */
    public static function getDates($enabled = true)
    {
        $return = array();
        
        foreach (self::$_fixedDates as $date) {
            if ($enabled == false || $date['enabled']) {
                $return[] = array(
                    'year' => $date['year'],
                    'month' => $date['month'],
                    'day' => $date['day']
                );
            }
        }
        
        return $return;
    }
    
    /**
     * Count number of instalments to be made
     * @return int
     */
    public function count()
    {
        return count(self::getDates());
    }
    
    /**
     * Get start date for payment plan instalments
     * 
     * @return Zend_Date
     */
    public function getInstalmentStartDate()
    {
        foreach (self::$_fixedDates as $date) {
            if ($date['enabled'] == false) {
                continue;
            }
            return new Zend_Date(
                array(
                    'year' => $date['year'],
                    'month' => $date['month'],
                    'day' => $date['day'],
                )
            );
        }
    }
    
    /**
     * Get end date for payment plan instalments
     * 
     * @return Zend_Date
     */
    public function getInstalmentEndDate()
    {
        $date = end(self::$_fixedDates);
        reset(self::$_fixedDates);
        
        return new Zend_Date(
            array(
                'year' => $date['year'],
                'month' => $date['month'],
                'day' => $date['day'],
            )
        ); 
    }
    
    /**
     * Get total due upfront within this payment plan
     * 
     * @return float 
     */
    public function getUpfrontTotal()
    {
        $total = 0;
        
        $instalmentTotal = $this->_getInstalmentTotal();
        
        foreach (self::$_fixedDates as $date) {
            if ($date['enabled'] == false) {
                $total += $instalmentTotal;
            }
        }
        
        return $total;
    }
    
    /**
     * Get total for all instalments
     * 
     * @return float
     */
    public function getInstalmentsTotal()
    {
        $total = 0;
        
        $instalmentTotal = $this->_getInstalmentTotal();
        
        foreach (self::$_fixedDates as $date) {
            if ($date['enabled'] == true) {
                $total += $instalmentTotal;
            }
        }
        
        return $total;
    }
    
    /**
     * Get the total for a single instalment
     * 
     * @return float
     */
    protected function _getInstalmentTotal()
    {
        $total = $this->_order->getTotal();
        
        $numberOfInstalments = count(self::$_fixedDates);
        
        return round($total / $numberOfInstalments, 2, PHP_ROUND_HALF_UP);
    }
    
    public function createInvoices()
    {
        $this->_invoices = array();
        
        $translate = $this->_getTranslate();
        
        $payments = $this->count();
        
        // upfront total
        $upfrontTotal = $this->getUpfrontTotal();
        
        if (!empty($upfrontTotal)) {
            
            ++$payments;
            
            $invoice = new HHF_Domain_Customer_Invoice(
                $this->_order->getFarm()
            );
        
            $data = array(
                'customerId' => $this->_order->getCustomer()->id,
                'type' => HHF_Domain_Customer_Invoice::TYPE_SHARES,
                'dueDate' => Zend_Date::now()->toString('yyyy-MM-dd'),
                'paid' => 0,
                'subTotal' => $upfrontTotal,
                'tax' => null,
                'total' => $upfrontTotal,
                'outstandingAmount' => $upfrontTotal,
                'message' => $translate->_(
                    'Upfront payment on installment payment plan'
                ),
                'lines' => array()
            );

            foreach ($this->_order as $item) {

                /* @var $item HHF_Order_Item_Share */
                $total = $item->getFullSharePrice();
                $quantity = $item->getQuantity();

                if ($quantity > 1) {
                    $unitPrice = round(
                        $total / $quantity, 
                        2, 
                        PHP_ROUND_HALF_UP
                    );
                } else {
                    $unitPrice = $total;
                }
                
                if ($payments > 1) {
                    $unitPrice = round(
                        $unitPrice / $payments,
                        2, 
                        PHP_ROUND_HALF_UP
                    );
                    $total = round(
                        $total / $payments, 
                        2, 
                        PHP_ROUND_HALF_UP
                    );
                }

                $data['lines'][] = array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_SHARE,
                    'referenceId' => $item->getCustomerShare()->id,
                    'description' => $this->_formatShareName($item),
                    'unitPrice' => $unitPrice,
                    'quantity' => $quantity,
                    'total' => $total,
                    'taxable' => 0,
                );
            }

            $deliveryTotal = $this->_order->getDeliveryTotal();

            if (!empty($deliveryTotal)) {
                
                if ($payments > 1) {
                    $deliveryTotal = round(
                        $deliveryTotal / $payments,
                        2,
                        PHP_ROUND_HALF_UP
                    );
                }
                
                $data['lines'][] = array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_DELIVERY,
                    'referenceId' => null,
                    'description' => $translate->_('Delivery Fee'),
                    'unitPrice' => $deliveryTotal,
                    'quantity' => 1,
                    'total' => $deliveryTotal,
                    'taxable' => 0,
                );
            }

            $adminTotal = $this->_order->getAdministrativeTotal();

            if (!empty($adminTotal)) {

                if ($payments > 1) {
                    $adminTotal = round(
                        $adminTotal / $payments,
                        2,
                        PHP_ROUND_HALF_UP
                    );
                }

                $data['lines'][] = array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_ADMINISTRATION,
                    'referenceId' => null,
                    'description' => $translate->_('Administration Fee'),
                    'unitPrice' => $adminTotal,
                    'quantity' => 1,
                    'total' => $adminTotal,
                    'taxable' => 0,
                );
            }

            $invoice->getService()->save($data);

            $this->_invoices[] = $invoice;
        }
        
        // instalments
        foreach ($this as $i => $instalment) {
            $invoice = new HHF_Domain_Customer_Invoice(
                $this->_order->getFarm()
            );

            $data = array(
                'customerId' => $this->_order->getCustomer()->id,
                'type' => HHF_Domain_Customer_Invoice::TYPE_SHARES,
                'dueDate' => $instalment['date']->toString('yyyy-MM-dd'),
                'paid' => 0,
                'subTotal' => $instalment['total'],
                'tax' => null,
                'total' => $instalment['total'],
                'outstandingAmount' => $instalment['total'],
                'message' => sprintf(
                    $translate->_('%s of %s installments starting %s and ending %s'),
                    ($i + 1),
                    $this->count(),
                    $this->getInstalmentStartDate()->get(Zend_Date::DATE_LONG),
                    $this->getInstalmentEndDate()->get(Zend_Date::DATE_LONG)
                ),
                'lines' => array()
            );

            foreach ($this->_order as $item) {

                /* @var $item HHF_Order_Item_Share */
                $total = $item->getFullSharePrice();
                $quantity = $item->getQuantity();

                if ($quantity > 1) {
                    $unitPrice = round(
                        $total / $quantity,
                        2,
                        PHP_ROUND_HALF_UP
                    );
                } else {
                    $unitPrice = $total;
                }
                
                if ($payments > 1) {
                    $unitPrice = round(
                        $unitPrice / $payments,
                        2, 
                        PHP_ROUND_HALF_UP
                    );
                    $total = round(
                        $total / $payments, 
                        2, 
                        PHP_ROUND_HALF_UP
                    );
                }

                $data['lines'][] = array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_SHARE,
                    'referenceId' => $item->getCustomerShare()->id,
                    'description' => $this->_formatShareName($item),
                    'unitPrice' => $unitPrice,
                    'quantity' => $quantity,
                    'total' => $total,
                    'taxable' => 0,
                );
            }

            $deliveryTotal = $this->_order->getDeliveryTotal();

            if (!empty($deliveryTotal)) {
                
                if ($payments > 1) {
                    $deliveryTotal = round(
                        $deliveryTotal / $payments,
                        2,
                        PHP_ROUND_HALF_UP
                    );
                }
                
                $data['lines'][] = array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_DELIVERY,
                    'referenceId' => null,
                    'description' => $translate->_('Delivery Fee'),
                    'unitPrice' => $deliveryTotal,
                    'quantity' => 1,
                    'total' => $deliveryTotal,
                    'taxable' => 0,
                );
            }

            $adminTotal = $this->_order->getAdministrativeTotal();

            if (!empty($adminTotal)) {

                if ($payments > 1) {
                    $adminTotal = round(
                        $adminTotal / $payments,
                        2,
                        PHP_ROUND_HALF_UP
                    );
                }

                $data['lines'][] = array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_ADMINISTRATION,
                    'referenceId' => null,
                    'description' => $translate->_('Administrative Fee'),
                    'unitPrice' => $adminTotal,
                    'quantity' => 1,
                    'total' => $adminTotal,
                    'taxable' => 0,
                );
            }

            $invoice->getService()->save($data);

            $this->_invoices[] = $invoice;
        }
        
        return $this->_invoices;
    }

    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return array
     */
    public function current() 
    {
        $position = 0;
        
        foreach (self::$_fixedDates as $date) {
            if ($date['enabled'] == true) {
                if ($position == $this->_instalmentIteratorPosition) {
                    return array(
                        'date' => new Zend_Date(
                            array(
                                'year' => $date['year'],
                                'month' => $date['month'],
                                'day' => $date['day'],
                            )
                        ),
                        'total' => $this->_getInstalmentTotal()
                    );
                } else {
                    ++$position;
                }
            }
        }
    }
}
