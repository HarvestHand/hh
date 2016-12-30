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
 * @copyright $Date: 2013-04-13 23:05:11 -0300 (Sat, 13 Apr 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Order
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Order.php 638 2013-04-14 02:05:11Z farmnik $
 * @copyright $Date: 2013-04-13 23:05:11 -0300 (Sat, 13 Apr 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
abstract class HHF_Order implements Iterator
{
    /**
     * @var HH_Domain_Farm
     */
    protected $_farm;
    
    protected $_items = array();
    
    /**
     * Payment type
     * 
     * @var string
     */
    protected $_payment = null;

    /**
     * @var Zend_Date
     */
    protected $_orderDate = null;
    
    protected $_validIteration = false;
    
    protected $_cache;

    protected $_itemCache;
    
    protected $_total = array();
    
    /**
     * @var HHF_Domain_Customer
     */
    protected $_customer;
    
    /**
     * Order constructor
     */
    public function __construct(HH_Domain_Farm $farm, $items = array(), 
        $payment = HHF_Domain_Transaction::PAYMENT_CASH, $orderDate = null)
    {
        $this->_farm = $farm;
        
        foreach ($items as $item) {
            $this->addItem($item);
        }
        
        $this->setPayment($payment);
        $this->setOrderDate($orderDate);
        
        $this->_cache = rand(0,1000);
        $this->_itemCache = rand(0,1000);
    }
    
    /**
     * @return HH_Domain_Farm
     */
    public function getFarm()
    {
        return $this->_farm;
    }
    
    /**
     * Add item to order
     * @param HHF_Order_Item $item
     * @return HHF_Order 
     */
    public function addItem(HHF_Order_Item $item)
    {
        $item->setOrder($this);
        $this->_items[] = $item;
        
        $this->_cache++;
        $this->_itemCache++;
        
        return $this;
    }
    
    public function isEmpty()
    {
        return empty($this->_items);
    }
    
    /**
     * Set payment type
     * 
     * @param string $payment
     * @return HHF_Order
     */
    public function setPayment($payment)
    {
        $this->_payment = $payment;
        $this->_cache++;
        return $this;
    }
    
    /**
     * @return string 
     */
    public function getPayment()
    {
        return $this->_payment;
    }
    
    /**
     * Set order date
     * 
     * @param Zend_Date $date
     * @return HHF_Order 
     */
    public function setOrderDate($date = null)
    {
        if ($date === null) {
            $date = Zend_Date::now();
        }
        
        $this->_orderDate = $date;
        $this->_cache++;
        return $this;
    }
    
    /**
     * Order date
     * 
     * @return Zend_Date|null 
     */
    public function getOrderDate()
    {
        return $this->_orderDate;
    }
    
    /**
     * Set order customer
     * 
     * @param HHF_Domain_Customer $customer 
     */
    public function setCustomer(HHF_Domain_Customer $customer)
    {
        $this->_customer = $customer;
    }
    
    /**
     * @return HHF_Domain_Customer
     */
    public function getCustomer()
    {
        return $this->_customer;
    }
    
    /**
     * Get order total
     * 
     * @return float 
     */
    abstract public function getTotal();
    
    public function getCacheId()
    {
        return $this->_cache;
    }
    
    public function rewind() 
    {
        $this->_validIteration = (false !== reset($this->_items)); 
    }

    public function current() 
    {
        return current($this->_items);
    }

    public function key() {
        return key($this->_items);
    }

    public function next() 
    {
        $this->_validIteration = (false !== next($this->_items));
    }

    public function valid() 
    {
        return $this->_validIteration;
    }
}
