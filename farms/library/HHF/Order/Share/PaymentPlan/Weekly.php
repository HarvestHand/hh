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
 * Description of Weekly
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Weekly.php 695 2014-02-03 12:28:04Z farmnik $
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Order_Share_PaymentPlan_Weekly extends HHF_Order_Share_PaymentPlan
{

    /**
     * Count number of instalments
     * @return int
     */
    public function count()
    {
        $instalmentStartDate = $this->getInstalmentStartDate();
        $instalmentEndDate = $this->getInstalmentEndDate();
        
        $instalmentStartWeek = $instalmentStartDate->get(Zend_Date::WEEK);
        $instalmentEndWeek = $instalmentEndDate->get(Zend_Date::WEEK);

        if ($instalmentStartWeek < $instalmentEndWeek) {
            return $instalmentEndWeek - $instalmentStartWeek + 1;
        } else {
            return (
                HH_Tools_Date::weeksInYear(
                    $instalmentStartDate->get(Zend_Date::YEAR)
                ) - $instalmentStartWeek
            ) + $instalmentEndWeek + 1;
        }
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
            ->set(1, Zend_Date::WEEKDAY_8601);

        if ($orderDate->compare($shareStartDate, Zend_Date::WEEK) == 0) {
            $shareStartDate->add(1, Zend_Date::WEEK);
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
            ->set(1, Zend_Date::WEEKDAY_8601);
    }
    
    /**
     * Get total for all instalments
     * 
     * @return float
     */
    public function getInstalmentsTotal()
    {
        $total = 0;
        
        return $total;
    }
    
    /**
     * Get total due upfront within this payment plan
     * 
     * @return float 
     */
    public function getUpfrontTotal()
    {
        return 0;
    }
    
    /**
     * @todo Needs to be implemented!
     */
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
    * @todo
    */
    public function current() 
    {
        return array(
            'date' => $this->getInstalmentStartDate(),
            'total' => $this->_order->getTotal()
        );
    }
}
