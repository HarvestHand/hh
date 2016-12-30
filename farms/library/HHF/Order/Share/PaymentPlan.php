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
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Instalments
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: PaymentPlan.php 409 2012-01-17 22:45:31Z farmnik $
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
abstract class HHF_Order_Share_PaymentPlan implements Countable, Iterator
{
    /**
     * @var HHF_Order_Share
     */
    protected $_order;
    
    /**
     * Current payment plan 
     * 
     * @var string
     */
    protected $_plan;
    
    /**
     * @var HHF_Domain_Customer_Invoice[]
     */
    protected $_invoices = array();
    
    /**
     * Position of the instalment interator
     * 
     * @var int
     */
    protected $_instalmentIteratorPosition = 0;
    
    /**
     * @var Zend_Translate
     */
    protected $_translate;
    
    protected $_count = array();
    
    public function __construct($order, $plan)
    {
        $this->_order = $order;
        $this->_plan = $plan;
    }
    
    public static function factory(HHF_Order_Share $order, $plan)
    {
        $class = 'HHF_Order_Share_PaymentPlan_' . ucfirst(strtolower($plan));
        
        return new $class($order, $plan);
    }
    
    /**
     * Count number of instalments
     * @return int
     */
    public function count()
    {
        return 0;
    }
    
    /**
     * Can this payment plan be broken down into instalments?
     * @return boolean
     */
    public function canHaveInstalments()
    {
        if ($this->_plan == HHF_Order_Share::PAYMENT_PLAN_NONE) {
            return false;
        }
        
        return true;
    }
    
    /**
     * @return HHF_Domain_Customer_Invoice[]
     */
    public function getInvoices()
    {
        return $this->_invoices;
    }
    
    public function __toString()
    {
        return $this->_plan;
    }
    
    /**
     * @return HH_Translate
     */
    protected function _getTranslate()
    {
        if (!($this->_translate instanceof HH_Translate)) {
            $this->_translate = Bootstrap::getZendTranslate();
            $this->_translate->addModuleTranslation('library');
        }
        
        return $this->_translate;
    }
    
    protected function _formatShareName(HHF_Order_Item_Share $item)
    {
        $translate = $this->_getTranslate();
        
        switch($item->getShare()->deliverySchedule) {
            case HHF_Domain_Share::DELIVERY_SCHEDULE_WEEKLY :
                return sprintf(
                    $translate->_('%s (%s): %s weekly deliveries, running %s to %s'),
                    $item->getShare()->name,
                    $item->getSize()->name,
                    $item->getIterations(),
                    $item->getStartDate()->get(Zend_Date::DATE_MEDIUM),
                    $item->getEndDate()->get(Zend_Date::DATE_MEDIUM)
                );

            case HHF_Domain_Share::DELIVERY_SCHEDULE_SEMI_MONTHLY :
                return sprintf(
                    '%s (%s): %s semi-monthly deliveries, running %s to %s',
                    $item->getShare()->name,
                    $item->getSize()->name,
                    $item->getIterations(),
                    $item->getStartDate()->get(Zend_Date::DATE_MEDIUM),
                    $item->getEndDate()->get(Zend_Date::DATE_MEDIUM)
                );

                break;
            case HHF_Domain_Share::DELIVERY_SCHEDULE_MONTHLY :
                return sprintf(
                    '%s (%s): %s monthly deliveries, running %s to %s',
                    $item->getShare()->name,
                    $item->getSize()->name,
                    $item->getIterations(),
                    $item->getStartDate()->get(Zend_Date::DATE_MEDIUM),
                    $item->getEndDate()->get(Zend_Date::DATE_MEDIUM)
                );

                break;
        }
    }
    
    /**
     * Get start date for payment plan instalments
     * 
     * @return Zend_Date
     */
    abstract public function getInstalmentStartDate();
    
    /**
     * Get end date for payment plan instalments
     * 
     * @return Zend_Date
     */
    abstract public function getInstalmentEndDate();
    
    /**
     * Get total due upfront within this payment plan
     * 
     * @return float
     */
    abstract public function getUpfrontTotal();
    
    /**
     * Get total for all instalments
     * 
     * @return float
     */
    abstract public function getInstalmentsTotal();
    
    /**
     * Create and write invoice(s) to database
     * 
     * @return HHF_Domain_Customer_Invoice[]
     */
    abstract public function createInvoices();
    
    /**
     * Rewind the Iterator to the first element
     * @link http://php.net/manual/en/iterator.rewind.php
     * @return void
     */
    public function rewind() 
    {
        $this->_instalmentIteratorPosition = 0;
    }
    
    /**
     * Return the current element
     * @link http://php.net/manual/en/iterator.current.php
     * @return array
     */
    public function current() 
    {
        return array(
            'date' => $this->getInstalmentStartDate(),
            'total' => $this->_order->getTotal()
        );
    }

    /**
     * Return the key of the current element
     * @link http://php.net/manual/en/iterator.key.php
     * @return integer|null
     */
    public function key() {
        if ($this->_instalmentIteratorPosition > $this->count()) {
            return null;
        }
        return $this->_instalmentIteratorPosition;
    }

    /**
     * Move forward to next element
     * @link http://php.net/manual/en/iterator.next.php
     * @return void
     */
    public function next() 
    {
        ++$this->_instalmentIteratorPosition;
    }

    /**
     * Checks if current position is valid
     * @link http://php.net/manual/en/iterator.valid.php
     * @return boolean
     */
    public function valid() 
    {
        if ($this->_instalmentIteratorPosition >= $this->count()) {
            return false;
        }
        
        return true;
    }
}