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
 * @copyright $Date: 2013-03-25 23:22:56 -0300 (Mon, 25 Mar 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of transaction service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 624 2013-03-26 02:22:56Z farmnik $
 * @copyright $Date: 2013-03-25 23:22:56 -0300 (Mon, 25 Mar 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Transaction_Service extends HH_Object_Service
{
    /**
     * @var HHF_Object_Db
     */
    protected $_object;
    
    public function save($data)
    {
        if ($this->_object->isEmpty()) {
            $this->_object->insert($data);
        } else {
            $this->_object->update($data);
        }
        
        $this->applyToInvoices();
        $this->applyToCustomerBalance();
    }
    
    public function remove()
    {
        if (!empty($this->_object['appliedToInvoices'])) {
            $this->removeFromInvoices(
                explode(',', $this->_object['appliedToInvoices'])
            );
        }
        
        if ($this->_object['appliedToBalance']) {
            $this->removeFromCustomerBalance();
        }
        
        return parent::remove();
    }
    
    /**
     * Apply transaction to customer balance
     * 
     * @return boolean 
     */
    public function applyToCustomerBalance()
    {
        if (!empty($this->_object['appliedToBalance']) 
            || empty($this->_object['customerId'])) {
            
            return true;
        }
        
        $balance = new HHF_Domain_Customer_Balance($this->_object->getFarm());
        
        $balance->insert(
            array(
                'customerId' => $this->_object['customerId'],
                'amount' => floatval('-' . $this->_object['total']),
                'source' => HHF_Domain_Customer_Balance::SOURCE_TRANSACTION,
                'sourceId' => $this->_object['id']
            )
        );
        
        $this->_object->update(array('appliedToBalance' => 1));
        
        return true;
    }
    
    /**
     * Apply transaction to customer balance
     * 
     * @return boolean 
     */
    public function removeFromCustomerBalance()
    {
        if (!$this->_object['appliedToBalance'] || empty($this->_object['customerId'])) {
            return true;
        }
        
        $balances = HHF_Domain_Customer_Balance::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'customerId' => $this->_object['customerId'],
                    'source' => HHF_Domain_Customer_Balance::SOURCE_TRANSACTION,
                    'sourceId' => $this->_object['id']
                )
            )
        );
        
        foreach ($balances as $balance) {
            $balance->getService()->remove();
        }
        
        $this->_object->update(array('appliedToBalance' => 0));
        
        return true;
    }
    
    /**
     * Invoice to apply transaction to
     * 
     * @param array $invoices
     * @return \HHF_Domain_Customer_Invoice[]
     * @throws Exception 
     */
    public function applyToInvoices($invoices = null)
    {
        $invoiceApplied = array();
        
        if ($invoices === null) {
            // use transaction invoice
            if (!empty($this->_object['invoiceId'])) {
                $invoices = array($this->_object['invoiceId']);
            }
        }
        
        if (!is_array($invoices)) {
            if (!empty($invoices)) {
                $invoices = array($invoices);
            }
        }

        $remainingToApply = (float) $this->_object['remainingToApply'];
        
        if (!empty($invoices)) {
            foreach ($invoices as $invoiceId) {
                if ($invoiceId instanceof HHF_Domain_Customer_Invoice) {
                    $invoice = $invoiceId;
                } else {
                
                    $invoice = new HHF_Domain_Customer_Invoice(
                        $this->_object->getFarm(),
                        $invoiceId
                    );
                }
                
                if ($invoice->isEmpty()) {
                    // transaction for a bogus invoice
                    throw new Exception('Transaction for a bogus invoice');
                }
                
                $invoice->getService()->applyToCustomerBalance(true);
                
                if (!empty($invoice['outstandingAmount']) || !$invoice['paid']) {
                    
                    if ($remainingToApply > $invoice['outstandingAmount']) {
                        $remainingToApply -= $invoice['outstandingAmount'];
                        $toPay = $invoice['outstandingAmount'];
                    } else if ($remainingToApply == $invoice['outstandingAmount']) {
                        $toPay = $invoice['outstandingAmount'];
                        $remainingToApply = 0;
                    } else {
                        $toPay = $remainingToApply;
                        $remainingToApply = 0;
                    }
                    
                    $invoice->getService()
                        ->applyPayment($toPay);
                    
                    // record application
                    $transactionInvoice = new HHF_Domain_Transaction_Invoice(
                        $this->_object->getFarm()
                    );
                    
                    $transactionInvoice->insert(
                        array(
                            'transactionId' => $this->_object['id'],
                            'invoiceId' => $invoice['id'],
                            'amountApplied' => $toPay
                        )
                    );
                    
                    $invoiceApplied[] = $invoice;
                }
                
                if ($remainingToApply <= 0) {
                    break;
                }
            }
            
            if (!empty($invoiceApplied)) {
                
                $applied = (!empty($this->_object['appliedToInvoices'])) 
                    ? explode(',', $this->_object['appliedToInvoices']) 
                    : array();
                
                foreach ($invoiceApplied as $invoice) {
                    $applied[] = $invoice['id'];
                }
                
                $this->_object->update(
                    array(
                        'appliedToInvoices' => implode(',', array_unique($applied)),
                        'remainingToApply' => $remainingToApply
                    )
                );
            }
            
        }
        
        return $invoiceApplied;
    }
    
    /**
     * Invoice to remove transaction from
     * 
     * @param array $invoices
     * @return \HHF_Domain_Customer_Invoice[]
     * @throws Exception 
     */
    public function removeFromInvoices($invoices = null)
    {
        if (empty($this->_object['appliedToInvoices'])) {
            return true;
        }

        $appliedInvoices = explode(',', $this->_object['appliedToInvoices']);
        $invoicesUnapplied = array();
        
        if ($invoices === null) {
            // use transaction invoice
            if (!empty($this->_object['appliedToInvoices'])) {
                $invoices = explode(',', $this->_object['appliedToInvoices']);
            }
        }
        
        if (!is_array($invoices)) {
            if (!empty($invoices)) {
                $invoices = array($invoices);
            }
        }
        
        $remainingToApply = (float) $this->_object['remainingToApply'];
        
        if (!empty($invoices)) {
            foreach ($invoices as $invoiceId) {
                if ($invoiceId instanceof HHF_Domain_Customer_Invoice) {
                    $invoice = $invoiceId;
                } else {
                
                    $invoice = new HHF_Domain_Customer_Invoice(
                        $this->_object->getFarm(),
                        $invoiceId
                    );
                }
                
                if ($invoice->isEmpty()) {
                    // transaction for a bogus invoice
                    throw new Exception('Transaction for a bogus invoice');
                }
                
                $transactionInvoice = HHF_Domain_Transaction_Invoice::fetchOne(
                    $this->_object->getFarm(),
                    array(
                        'where' => array(
                            'transactionId' => $this->_object['id'],
                            'invoiceId' => $invoice['id']
                        )
                    )
                );
                
                if (!$transactionInvoice->isEmpty()) {
                
                    if (!empty($transactionInvoice['amountApplied'])) {

                        $remainingToApply += $transactionInvoice['amountApplied'];

                        $invoice->getService()
                            ->removePayment($transactionInvoice['amountApplied']);
                    }
                    
                    $invoicesUnapplied[] = $invoice;
                    
                    $transactionInvoice->getService()->remove();
                }
            }
            
            if (!empty($invoicesUnapplied)) {
                
                $toDrop = array();
                
                foreach ($invoicesUnapplied as $invoice) {
                    $toDrop[] = $invoice['id'];
                }
                
                $this->_object->update(
                    array(
                        'appliedToInvoices' => implode(
                            ',',
                            array_diff($appliedInvoices, $toDrop)
                        ),
                        'remainingToApply' => $remainingToApply
                    )
                );
            }
            
        }
        
        return $invoicesUnapplied;
    }
    
    /**
     * Remove ammount originally applied to an select invoice
     * 
     * @param  \HHF_Domain_Customer_Invoice $invoices
     * @return float remaining not removed
     * @throws Exception 
     */
    public function removeFromInvoice(HHF_Domain_Customer_Invoice $invoice,
        $amount)
    {
        if (empty($this->_object['appliedToInvoices'])) {
            return $amount;
        }

        $appliedInvoices = explode(',', $this->_object['appliedToInvoices']);
        
        $remainingToApply = (float) $this->_object['remainingToApply'];
        

        $transactionInvoice = HHF_Domain_Transaction_Invoice::fetchOne(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'transactionId' => $this->_object['id'],
                    'invoiceId' => $invoice['id'],
                )
            )
        );

        if (!$transactionInvoice->isEmpty()) {

            if (!empty($transactionInvoice['amountApplied'])) {

                if ($amount >= $transactionInvoice['amountApplied']) {
                    // transaction get's unapplied 
                    // for this invoice amount applied
                    
                    $remainingToApply += $transactionInvoice['amountApplied'];
                    $amount -= $transactionInvoice['amountApplied'];
                    
                    $toDrop = array(
                        $invoice['id']
                    );

                    $this->_object->update(
                        array(
                            'appliedToInvoices' => implode(
                                ',',
                                array_diff($appliedInvoices, $toDrop)
                            ),
                            'remainingToApply' => $remainingToApply
                        )
                    );
                    
                    $transactionInvoice->getService()->remove();
                } else {
                    
                    // only remove a piece of the amount applied
                    $remainingToApply += $amount;
                    $transactionInvoice['amountApplied'] -= $amount;
                    $amount = 0;

                    $this->_object->update(
                        array(
                            'remainingToApply' => $remainingToApply
                        )
                    );
                    
                    $transactionInvoice->update(
                        array(
                            'amountApplied' => $transactionInvoice['amountApplied']
                        )
                    );
                }
            }
        }
        
        return $amount;
    }

    /**
     * @return bool|HHF_Domain_Customer_Invoice
     */
    public function applyBestFitToInvoice()
    {
        if (empty($this->_object['customerId'])) {
            return false;
        }
        
        $transactionInvoices = HHF_Domain_Transaction_Invoice::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'transactionId' => $this->_object['id']
                )
            )
        );
        
        if ($transactionInvoices->count() > 0) {
            return false;
        }

        // try for older invoice on exact match
        $invoices = HHF_Domain_Customer_Invoice::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'customerId' => $this->_object['customerId'],
                    'paid' => 0,
                    'pending' => 0,
                    'outstandingAmount' => $this->_object['remainingToApply'],
                    'DATE(addedDatetime) <= ' . Bootstrap::getZendDb()->quote(
                        HH_Tools_Date::dateToDb($this->_object['addedDatetime'])
                    )
                ),
                'order' => array(
                    array(
                        'column' => 'dueDate',
                        'dir' => 'ASC'
                    )
                )
            )
        );
        
        if ($invoices->count() > 0) {
            $invoice = $invoices->current();

            $this->applyToInvoices($invoice);
            
            return $invoice;
        }

        // try for older invoices on sum match
        $invoices = HHF_Domain_Customer_Invoice::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'customerId' => $this->_object['customerId'],
                    'paid' => 0,
                    'pending' => 0,
                    'dueDate <= ' . Bootstrap::getZendDb()->quote(
                        HH_Tools_Date::dateToDb($this->_object['addedDatetime'])
                    )
                ),
                'order' => array(
                    array(
                        'column' => 'dueDate',
                        'dir' => 'ASC'
                    )
                )
            )
        );

        if ($invoices->count() > 0) {
            $total = 0;
            $toApply = array();

            foreach ($invoices as $invoice) {
                $total += $invoice['outstandingAmount'];
                $toApply[] = $invoice;
            }

            if ($total == $this->_object['remainingToApply']) {
                $this->applyToInvoices($toApply);

                return $invoices;
            }
        }

        // try for next newest invoice
        $invoices = HHF_Domain_Customer_Invoice::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'customerId' => $this->_object['customerId'],
                    'paid' => 0,
                    'pending' => 0,
                    'outstandingAmount' => $this->_object['remainingToApply'],
                    'DATE(addedDatetime) > ' . Bootstrap::getZendDb()->quote(
                        HH_Tools_Date::dateToDb($this->_object['addedDatetime'])
                    )
                ),
                'order' => array(
                    array(
                        'column' => 'dueDate',
                        'dir' => 'ASC'
                    )
                )
            )
        );

        if ($invoices->count() > 0) {
            $invoice = $invoices->current();

            $this->applyToInvoices($invoice);

            return $invoice;
        }

        return false;
    }
}
