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
 * @copyright $Date: 2015-11-03 20:22:47 -0400 (Tue, 03 Nov 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of invoice service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 964 2015-11-04 00:22:47Z farmnik $
 * @copyright $Date: 2015-11-03 20:22:47 -0400 (Tue, 03 Nov 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Invoice_Service extends HH_Object_Service
{
    /**
     * @var HHF_Domain_Customer_Invoice
     */
    protected $_object;

    public function save($data)
    {
        $data['total'] = 0;
        $data['subTotal'] = 0;
        $data['outstandingAmount'] = 0;

        if (!array_key_exists('pending', $data)) {
            $data['pending'] = 0;
        }

        foreach ($data['lines'] as &$line) {
            $lineTotal = round(
                $line['unitPrice'] * $line['quantity'],
                2,
                PHP_ROUND_HALF_UP
            );

            $line['total'] = $lineTotal;
            $data['subTotal'] += $lineTotal;
            $data['total'] += $lineTotal;
            $data['outstandingAmount'] += $lineTotal;
        }

        if ($this->_object->isEmpty()) {
            $this->_object->insert($data);

            if ($this->_object['outstandingAmount'] <= 0
                && empty($this->_object['pending'])) {

                // if 0 invoice, mark as paid
                $this->issueTransaction();
                $this->_object->reload();
            }
        } else {

            $wasPending = (!empty($this->_object['pending'])
                && array_key_exists('pending', $data)
                && $data['pending'] == 0);

            $this->_object->update($data);

            $this->updateRelatedLineObjects();

            if ($this->_object['outstandingAmount'] <= 0) {

                // if 0 invoice, mark as paid
                $this->issueTransaction();
                $this->_object->reload();
            }

            if ($wasPending) {
                // balance now due
                $this->applyToCustomerBalance(true);

                // email customer
                $this->emailCustomer(
                    array(
                        'wasPending' => $wasPending
                    )
                );
            } else {
                $this->applyToCustomerBalance();
            }
        }
    }

    protected function updateRelatedLineObjects()
    {
        foreach ($this->_object->getLines() as $line) {
            if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_ADDON) {
                $customerType = $line->getCustomerType();

                if (floatval($customerType['quantity'])
                    != floatval($line['quantity'])) {

                    $customerType->getService()->save(
                        array('quantity' => $line['quantity'])
                    );
                }
            }
        }
    }

    /**
     * UnApply invoice to standing balance for the customer
     *
     * @return boolean
     */
    public function unapplyFromCustomerBalance()
    {
        // remove from balance
        $balances = HHF_Domain_Customer_Balance::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'customerId' => $this->_object['customerId'],
                    'source' => HHF_Domain_Customer_Balance::SOURCE_INVOICE,
                    'sourceId' => $this->_object['id']
                )
            )
        );

        foreach ($balances as $balance) {
            $balance->getService()->remove();
        }

        $this->_object->update(array('appliedToBalance' => 0));
    }

    /**
     * Apply invoice to standing balance for the customer
     *
     * @param boolean $force Ignore date of invoice and do it now
     * @return boolean
     */
    public function applyToCustomerBalance($force = false)
    {
        if (!empty($this->_object['appliedToBalance'])) {
            // check that balanced applied needs to be adjusted

            $balance = HHF_Domain_Customer_Balance::fetchOne(
                $this->_object->getFarm(),
                array(
                    'where' => array(
                        'customerId' => $this->_object['customerId'],
                        'source' => HHF_Domain_Customer_Balance::SOURCE_INVOICE,
                        'sourceId' => $this->_object['id']
                    )
                )
            );

            if (!$balance->isEmpty() && $balance['amount'] != $this->_object['total']) {
                $balance->getService()->save(
                    array(
                        'amount' => $this->_object['total']
                    )
                );
            }


            return true;
        }

        if (!empty($this->_object['pending'])) {
            return false;
        }

        if (!$force) {
            $dueDate = $this->_object['dueDate'];

            if (!($dueDate instanceof Zend_Date)) {
                $dueDate = new Zend_Date($dueDate, 'yyyy-MM-dd');
            }

            $now = Zend_Date::now();
            $now = $now->getDate();
            $now->setTimezone('UTC');

            if ($dueDate->compareDate($now) > 0) {
                return false;
            }
        }

        $balance = new HHF_Domain_Customer_Balance($this->_object->getFarm());

        $balance->insert(
            array(
                'customerId' => $this->_object['customerId'],
                'amount' => $this->_object['total'],
                'source' => HHF_Domain_Customer_Balance::SOURCE_INVOICE,
                'sourceId' => $this->_object['id']
            )
        );

        $this->_object->update(array('appliedToBalance' => 1));

        return true;
    }

    /**
     * Apply payment against invoice
     *
     * @param float $amount
     * @return boolean
     */
    public function applyPayment($amount)
    {
        if (!empty($this->_object['pending'])) {
            return false;
        }

        if (!empty($this->_object['paid'])) {
            return false;
        }

        $outstanding = round(
            floatval($this->_object['outstandingAmount']) - floatval($amount),
            2
        );

        if ($outstanding <= 0) {
            $outstanding = 0;
            $paid = 1;
        } else {
            $paid = 0;
        }

        $this->_object->update(
            array(
                'outstandingAmount' => $outstanding,
                'paid' => $paid
            )
        );

        return true;
    }

    /**
     * Remove payment against invoice
     *
     * @param float $amount
     * @return boolean
     */
    public function removePayment($amount)
    {
        $outstanding = round(
            floatval($this->_object['outstandingAmount']) + floatval($amount),
            2
        );

        if ($outstanding <= 0) {
            $outstanding = 0;
            $paid = 1;
        } else {
            $paid = 0;
        }

        $this->_object->update(
            array(
                'outstandingAmount' => $outstanding,
                'paid' => $paid
            )
        );

        return true;
    }

    public function recalculate()
    {
        $lines = $this->_object->getLines();

        $newTotal = 0;
        $currentTotal = $this->_object['total'];
        $outstandingAmount = $this->_object['outstandingAmount'];

        foreach ($lines as $line) {
            $newTotal += $line['total'];
        }

        if ($outstandingAmount < $currentTotal) {
            $hasTransactions = true;
        } else {
            $hasTransactions = false;
        }

        if ($currentTotal != $newTotal) {

            if ($currentTotal > $newTotal) {
                // outstanding amount nocked down
                $diff = $currentTotal - $newTotal;

                $outstandingAmount -= $diff;

                $balanceAmount = '-' . strval($diff);

                if ($hasTransactions && $diff > 0) {
                    // unapply difference
                    $transactions = $this->_object->getTransactions();

                    if ($transactions !== null) {
                        foreach ($transactions as $transaction) {
                            if ($diff <= 0) {
                                break;
                            }

                            $diff = $transaction->getService()->removeFromInvoice(
                                $this->_object,
                                $diff
                            );
                        }
                    }
                }
            } else {
                // outstanding amount nocked up :)
                $diff = $newTotal - $currentTotal;

                $outstandingAmount += $diff;
                $balanceAmount = $diff;
            }

            $paid = ($outstandingAmount > 0) ? 0 :1;

            if ($outstandingAmount < 0) {
                $outstandingAmount = 0;
            }

            $this->_object->update(
                array(
                    'subTotal' => $newTotal,
                    'total' => $newTotal,
                    'outstandingAmount' => $outstandingAmount,
                    'paid' => $paid
                )
            );

            if (!empty($this->_object['appliedToBalance'])) {
                $balance = new HHF_Domain_Customer_Balance($this->_object->getFarm());

                $balance->insert(
                    array(
                        'customerId' => $this->_object['customerId'],
                        'amount' => $balanceAmount,
                        'source' => HHF_Domain_Customer_Balance::SOURCE_INVOICE,
                        'sourceId' => $this->_object['id'],
                        'note' => Bootstrap::getZendTranslate()->_('Invoice adjusted')
                    )
                );
            }
        }
    }

    public function remove()
    {
        // unlink transactions
        $transactions = $this->_object->getTransactions();

        if ($transactions !== null) {
            foreach ($transactions as $transaction) {
                $transaction->getService()->removeFromInvoices($this->_object);
            }
        }

        // remove from balance
        $balances = HHF_Domain_Customer_Balance::fetch(
            $this->_object->getFarm(),
            array(
                'where' => array(
                    'customerId' => $this->_object['customerId'],
                    'source' => HHF_Domain_Customer_Balance::SOURCE_INVOICE,
                    'sourceId' => $this->_object['id']
                )
            )
        );

        foreach ($balances as $balance) {
            $balance->getService()->remove();
        }

        return parent::remove();
    }

    public function issueTransaction($total = null,
        $type = HHF_Domain_Transaction::TYPE_CASH, $reference = null)
    {
        if (!$total) {
            $total = $this->_object['outstandingAmount'];
        }

        $transaction = new HHF_Domain_Transaction($this->_object->getFarm());

        $transaction->getService()
            ->save(
                array(
                    'invoiceId' => $this->_object['id'],
                    'customerId' => $this->_object['customerId'],
                    'transactionDate' => date('Y-m-d'),
                    'type' => $type,
                    'reference' => $reference,
                    'total' => (float) $total,
                    'remainingToApply' => (float) $total
                ),
                $this->_object
            );
    }

    /**
     * Email farm about purchase
     *
     * @param array $params
     * @param HHF_Domain_Customer_Invoice $invoice
     */
    public function emailFarm($params = array())
    {
        switch ($this->_object['type']) {
            case HHF_Domain_Customer_Invoice::TYPE_ADDONS:
                return $this->emailFarmAddon($params);
                break;
        }
    }

    /**
     * Email farm about add on products purchased
     * @param array $params
     * @return null
     */
    protected function emailFarmAddon($params = array())
    {
        $farm = $this->_object->getFarm();

        if (empty($farm->email)) {
            return;
        }

        $customer = new HHF_Domain_Customer(
            $this->_object->getFarm(),
            $this->_object['customerId']
        );

        $layout = new Zend_Layout();
        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
        $layout->setLayout('email');
        $layout->getView()->farm = $farm;

        $view = new Zend_View();
        $view->setScriptPath(
            Bootstrap::$farmModules . 'shares/views/scripts/'
        );

        // find location && week
        $location = null;
        $week = null;

        foreach ($this->_object->getLines() as $line) {
            if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_ADDON) {
                $customerAddon = $line->getCustomerType();
                $week = $customerAddon['week'];

                $shares = HHF_Domain_Customer_Share::fetchShares(
                    $farm,
                    array(
                        'fetch' => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
                        'customer' => $customer,
                        'filter' => 'active'
                    )
                );

                if (!empty($shares)) {
                    $share = array_pop($shares);
                    $location = $share->getLocation();
                }
                break;
            }
        }

        $view->customer = $customer;
        $view->location = $location;
        $view->params = $params;
        $view->week = $week;
        $view->invoice = $this->_object;
        $view->farm = $farm;

        $layout->content = $view->render('addons-email-farm.phtml');

        if (empty($customer->email)) {
            $replyTo = array($farm->email, $farm->name);
        } else {
            $replyTo = array(
                $customer->email,
                $customer->firstName . ' ' . $customer->lastName
            );
        }

        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');

        $headers = array();

        if ($this->_object['pending']) {
            $headers[] = array(
                'name' => 'X-Priority',
                'value' => '1 (Highest)'
            );
            $headers[] = array(
                'name' => 'X-MSMail-Priority',
                'value' => 'High'
            );
            $headers[] = array(
                'name' => 'Importance',
                'value' => 'High'
            );
        }

        if ($this->_object['pending']) {
            $subject = $translate->_('New Add On Products Purchased From HarvestHand (Pending)');
        } else {
            $subject = $translate->_('New Add On Products Purchased From HarvestHand');
        }

        $email = new HH_Job_Email();
        $email->add(
            array($farm->email, $farm->name),
            $farm->email,
            $subject,
            null,
            $layout->render(),
            $replyTo,
            null,
            null,
            'farmnik@harvesthand.com',
            'farmnik@harvesthand.com',
            $headers
        );
    }

    /**
     * Email customer about purchase
     *
     * @param array $params
     */
    public function emailCustomer($params = array()) {
        switch ($this->_object['type']) {
            case HHF_Domain_Customer_Invoice::TYPE_ADDONS:
                return $this->emailCustomerAddon($params);
                break;
        }
    }

    /**
     * Email customer about add on products purchased
     *
     * @param array $params
     * @return type
     */
    protected function emailCustomerAddon($params = array())
    {
        $customer = new HHF_Domain_Customer(
            $this->_object->getFarm(),
            $this->_object['customerId']
        );

        if (empty($customer->email)) {
            return;
        }

        $farm = $this->_object->getFarm();

        // find location && week
        $location = null;
        $week = null;

        foreach ($this->_object->getLines() as $line) {
            if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_ADDON) {
                $customerAddon = $line->getCustomerType();
                $week = $customerAddon['week'];

                $shares = HHF_Domain_Customer_Share::fetchShares(
                    $farm,
                    array(
                        'fetch' => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
                        'customer' => $customer,
                        'filter' => 'active'
                    )
                );

                $location = $shares[0]->getLocation();
                break;
            }
        }

        $layout = new Zend_Layout();
        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
        $layout->setLayout('email');
        $layout->getView()->farm = $farm;

        $view = new Zend_View();
        $view->setScriptPath(
            Bootstrap::$farmModules . 'shares/views/scripts/'
        );

        $view->customer = $customer;
        $view->location = $location;
        $view->params = $params;
        $view->week = $week;
        $view->invoice = $this->_object;
        $view->farm = $farm;

        $layout->content = $view->render(
            'addons-email-customer.phtml'
        );

        if (!empty($farm->email)) {
            $replyTo = array($farm->email, $farm->name);
            $from = array($farm->email, $farm->name);
        } else {
            $replyTo = array(
                $customer->email,
                $customer->firstName . ' ' . $customer->lastName
            );
            $from = array(
                $customer->email,
                $customer->firstName . ' ' . $customer->lastName
            );
        }

        $translate = Bootstrap::getZendTranslate();

        if ($this->_object['pending']) {
            $subject = $translate->_('New Add On Products Purchased From %s (Pending)');
        } elseif (!empty($params['wasPending'])) {
            $subject = $translate->_('New Add On Products Purchased From %s (Finalized)');
        } else {
            $subject = $translate->_('New Add On Products Purchased From %s');
        }

        $email = new HH_Job_Email();
        $email->add(
            $from,
            array(
                $customer->email,
                $customer->firstName . ' ' . $customer->lastName
            ),
            sprintf(
                $subject,
                $farm->name
            ),
            null,
            $layout->render(),
            $replyTo,
            null,
            null,
            'farmnik@harvesthand.com',
            'farmnik@harvesthand.com'
        );
    }
}
