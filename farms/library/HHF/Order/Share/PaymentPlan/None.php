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
 * Description of None
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: None.php 695 2014-02-03 12:28:04Z farmnik $
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Order_Share_PaymentPlan_None extends HHF_Order_Share_PaymentPlan
{
    /**
     * Get start date for payment plan instalments
     * 
     * @return Zend_Date
     */
    public function getInstalmentStartDate()
    {
        $now = Zend_Date::now();
        $fullPaymentDueDate = null;
        
        foreach ($this->_order as $share) {
            /* @var $share HHF_Order_Item_Share */
            $testDate = $share->getDuration()->fullPaymentDueDate;
            
            /* @var $testDate Zend_Date */
            if ($testDate instanceof Zend_Date 
                && $testDate->compareDate($now) > 0) {

                if ($fullPaymentDueDate instanceof Zend_Date) {
                    if ($testDate->compareDate($fullPaymentDueDate) < 0) {
                        $fullPaymentDueDate = $testDate;
                    }
                } else {
                    $fullPaymentDueDate = $testDate;
                }
            }
        }
        
        if ($fullPaymentDueDate instanceof Zend_Date) {
            return $fullPaymentDueDate;
        }
        
        return $now;
    }
    
    /**
     * Get end date for payment plan instalments
     * 
     * @return Zend_Date
     */
    public function getInstalmentEndDate()
    {
        return Zend_Date::now();
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
        return $this->_order->getTotal();
    }
    
    public function createInvoices()
    {
        $translate = $this->_getTranslate();
        
        $invoice = new HHF_Domain_Customer_Invoice($this->_order->getFarm());
        
        $data = array(
            'customerId' => $this->_order->getCustomer()->id,
            'type' => HHF_Domain_Customer_Invoice::TYPE_SHARES,
            'dueDate' => $this->getInstalmentStartDate()->toString('yyyy-MM-dd'),
            'paid' => 0,
            'subTotal' => $this->getUpfrontTotal(),
            'tax' => null,
            'total' => $this->getUpfrontTotal(),
            'outstandingAmount' => $this->getUpfrontTotal(),
            'message' => null,
            'lines' => array()
        );
  
        foreach ($this->_order as $item) {
            
            /* @var $item HHF_Order_Item_Share */
            $total = $item->getFullSharePrice();
            $quantity = $item->getQuantity();
            
            if ($quantity > 1) {
                $unitPrice = round($total / $quantity, 2, PHP_ROUND_HALF_UP);
            } else {
                $unitPrice = $total;
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
        
        $this->_invoices = array($invoice);
        
        return $this->_invoices;
    }
}
