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
 * Description of Monthly
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Monthly.php 695 2014-02-03 12:28:04Z farmnik $
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Order_Share_PaymentPlan_Monthly extends HHF_Order_Share_PaymentPlan
{
    /**
     * Count number of instalments
     * @return int
     */
    public function count()
    {
        if (isset($this->_count[$this->_order->getCacheId()])) {
            return $this->_count[$this->_order->getCacheId()];
        }
        
        $instalmentStartDate = clone $this->getInstalmentStartDate();
        $instalmentEndDate = clone $this->getInstalmentEndDate();
        $months = 0;
        
        while (HH_Tools_Date::compareYearMonth($instalmentStartDate, $instalmentEndDate) < 1) {
            ++$months;
            $instalmentStartDate->addMonth(1);
        }
        
        $this->_count[$this->_order->getCacheId()] = $months;
        
        return $this->_count[$this->_order->getCacheId()];
    }
    
    /**
     * Get start date for payment plan instalments
     * 
     * @return Zend_Date
     */
    public function getInstalmentStartDate()
    {
        $orderDate = $this->_order->getOrderDate();
        $shareStartDate = clone $this->_order->getStartDate()
            ->setDay(1);

        if ($orderDate->compare($shareStartDate, Zend_Date::MONTH) == 0) {
            $shareStartDate->add(1, Zend_Date::MONTH);
        }

        return $shareStartDate;
    }
    
    /**
     * Get end date for payment plan instalments
     * 
     * @return Zend_Date
     */
    public function getInstalmentEndDate()
    {
        return $this->_order->getEndDate()
            ->set(1, Zend_Date::DAY);
    }
    
    /**
     * Get total due upfront within this payment plan
     * 
     * @return float 
     */
    public function getUpfrontTotal()
    {
        $total = 0;
        
        $orderDate = $this->_order->getOrderDate();
        $shareStartDate = $this->_order->getStartDate()
            ->setDay(1);
        
        if ($orderDate->compare($shareStartDate, Zend_Date::MONTH) == 0) {
            
            $numberOfInstalments = $this->count() + 1;
            
            $total = round(
                $this->_order->getTotal() / $numberOfInstalments,
                2,
                PHP_ROUND_HALF_UP
            );
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
        $total = $this->_order->getTotal();
        
        return $total - $this->getUpfrontTotal();
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
        $total = $this->_order->getTotal();
        $total -= $this->getUpfrontTotal();
        
        $numberOfInstalments = $this->count();
        
        $startDate = clone $this->getInstalmentStartDate();
        
        if ($this->_instalmentIteratorPosition) {
            $startDate->addMonth($this->_instalmentIteratorPosition);
        }
        
        return array(
            'date' => $startDate,
            'total' => round($total / $numberOfInstalments, 2, PHP_ROUND_HALF_UP)
        );
    }
}
