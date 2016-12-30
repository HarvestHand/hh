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
 * @copyright $Date: 2015-05-27 11:14:23 -0300 (Wed, 27 May 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Service
 */

/**
 * Description of Button
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Ipn.php 843 2015-05-27 14:14:23Z farmnik $
 * @copyright $Date: 2015-05-27 11:14:23 -0300 (Wed, 27 May 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Paypal_Ipn
{
    /**
     * @var HH_Domain_Farm
     */
    protected $farm;

    protected $config = array();
    protected $data;
    protected $type = self::TRANSACTION_UNKNOWN;
    protected $customerId = null;
    protected $invoiceId = null;
    protected $groupId = null;
    protected $groupData = array();

    const TRANSACTION_CUSTOMER = 'CUSTOMER';
    const TRANSACTION_INVOICE = 'INVOICE';
    const TRANSACTION_GROUP = 'GROUP';
    const TRANSACTION_UNKNOWN = 'UNKNOWN';

    public function __construct(HH_Domain_Farm $farm, $data = array(),
        $config = array())
    {
        $this->farm = $farm;
        $this->setData($data);
        $this->config = $config;
    }

    public function setConfig($params)
    {
        foreach ($params as $key => $value) {
            $this->config[$key] = $value;
        }
    }

    public function setData($data)
    {
        $this->data = $data;

        $this->parseTransactionType();
    }

    /**
     * Check if IPN data is valid.
     *
     * @return boolean
     */
    public function isValid()
    {
        $data = $this->data;
        $data['cmd'] = '_notify-validate';

        $url = !empty($data['test_ipn'])
            ? 'https://www.sandbox.paypal.com' : 'https://www.paypal.com';
        $url .= '/cgi-bin/webscr';

        // Get cURL resource
        $curl = curl_init();

        // Set some options
        curl_setopt_array($curl, array(
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_URL => $url,
            CURLOPT_POST => 1,
            CURLOPT_RETURNTRANSFER => 1,
            CURLOPT_POSTFIELDS => http_build_query($data),
            CURLOPT_SSL_VERIFYPEER => 1,
            CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_FORBID_REUSE => 1,
            CURLOPT_HTTPHEADER => array('Connection: Close')
        ));

        // Send the request & save response to $result
        $result = curl_exec($curl);

        // Close request to clear up some resources
        curl_close($curl);

        if (strcmp($result, 'VERIFIED') == 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * Map IPN to HH invoice, customer, or invoice group,
     * and create transactions
     *
     * @return boolean
     * @throws HHF_Domain_Transaction_Exception_Notvalid
     */
    public function mapToTransaction()
    {
        if (!$this->isValid()) {
            throw new HHF_Domain_Transaction_Exception_Notvalid($this->data);
        }

        if ($this->data['txn_type'] != 'web_accept') {
            return false;
        }

        if (stripos($this->data['payment_status'], 'Completed') === false) {
            return false;
        }

        if ($this->hasBeenRecorded()) {
            return false;
        }

        switch ($this->type) {
            case self::TRANSACTION_CUSTOMER :
                $this->applyTransactionToCustomer();
                break;
            case self::TRANSACTION_INVOICE :
                $this->applyTransactionToInvoice();
                break;
            case self::TRANSACTION_GROUP :
                $this->applyTransactionFromGroup();
                break;
            case self::TRANSACTION_UNKNOWN :
                $this->applyTransaction($this->data['mc_gross']);
                break;
        }

        return true;
    }

    /**
     * Parse IPN transaction data to HH type
     */
    protected function parseTransactionType()
    {
        $this->type = self::TRANSACTION_UNKNOWN;

        if (stripos($this->data['item_number'], 'hhc:') !== false) {
            list(, $customerId) = explode(':', $this->data['item_number'], 2);

            if (is_numeric($customerId)) {
                $customer = new HHF_Domain_Customer(
                    $this->farm,
                    $customerId
                );

                if (!$customer->isEmpty()) {
                    $this->customerId = $customerId;
                    $this->type = self::TRANSACTION_CUSTOMER;
                }
            }
        }

        if (stripos($this->data['item_number'], 'hhi:') !== false) {
            list(, $invoiceId) = explode(':', $this->data['item_number'], 2);

            if (is_numeric($invoiceId)) {

                $invoice = new HHF_Domain_Customer_Invoice(
                    $this->farm,
                    $invoiceId
                );

                if (!$invoice->isEmpty()) {
                    $this->invoiceId = $invoiceId;
                    $this->customerId = $invoice['customerId'];
                    $this->type = self::TRANSACTION_INVOICE;
                }
            }
        }

        if (stripos($this->data['item_number'], 'hhg:') !== false) {
            list(, $groupId) = explode(':', $this->data['item_number'], 2);

            if (!empty($groupId)) {

                HH_Domain_Keyvalue::clean();

                $keyValue = new HH_Domain_Keyvalue($groupId);

                if (!$keyValue->isEmpty()
                    && $keyValue['type'] == 'preauth:' . $this->farm['id']) {

                    $this->type = self::TRANSACTION_GROUP;
                    $this->groupId = $groupId;
                    $this->groupData = unserialize($keyValue->data);
                }

            }
        }
    }

    /**
     * Apply a transaction
     *
     * @param type $total
     * @param type $customerId
     * @param type $invoiceId
     * @return \HHF_Domain_Transaction
     */
    protected function applyTransaction(
        $total,
        $customerId = null,
        $invoiceId = null
    ) {
        $transaction = new HHF_Domain_Transaction($this->farm);

        $transaction->getService()
            ->save(
                array(
                    'invoiceId' => $invoiceId,
                    'customerId' => $customerId,
                    'transactionDate' => $this->getTransactionDate(),
                    'type' => HHF_Domain_Transaction::TYPE_PAYPAL,
                    'reference' => $this->data['txn_id'],
                    'total' => (float) $total,
                    'remainingToApply' => (float) $total
                )
            );

        return $transaction;
    }

    /**
     * apply a group of invoices, or general customer payments
     */
    protected function applyTransactionFromGroup()
    {
        $toApplyToInvioce = array();

        foreach ($this->groupData as $row) {

            $transaction = $this->applyTransaction(
                $row['total'],
                $row['customerId'],
                $row['invoiceId']
            );

            if (empty($row['invoiceId'])) {
                $toApplyToInvioce[] = $transaction;
            }
        }

        foreach ($toApplyToInvioce as $transaction) {
            $transaction->getService()->applyBestFitToInvoice();
        }

        $keyValue = new HH_Domain_Keyvalue($this->groupId);
        $keyValue->delete();
    }

    /**
     * Apply a transaction from a single invoice
     */
    protected function applyTransactionToInvoice()
    {
        $this->applyTransaction(
            $this->data['mc_gross'],
            $this->customerId,
            $this->invoiceId
        );
    }

    /**
     * Apply a transaction from a customer
     *
     */
    protected function applyTransactionToCustomer()
    {
        $transaction = $this->applyTransaction(
            $this->data['mc_gross'],
            $this->customerId,
            $this->invoiceId
        );

        $transaction->getService()->applyBestFitToInvoice();
    }

    /**
     * Parse transaction date
     *
     * @return sting
     */
    protected function getTransactionDate()
    {
        if (!empty($this->data['payment_date'])) {

            $date = DateTime::createFromFormat(
                'H:i:s M d, Y e',
                $this->data['payment_date']
            );

            if ($date !== false) {

                return $date->format('Y-m-d');
            }
        }

        return date('Y-m-d');
    }

    /**
     * Check to see if this transaction has been recorded before
     * @return boolean
     */
    public function hasBeenRecorded()
    {
        if (empty($this->data['txn_id'])) {
            return false;
        }

        $transactions = HHF_Domain_Transaction::fetch($this->farm, array(
            'where' => array(
                'reference' => $this->data['txn_id'],
                'type' => HHF_Domain_Transaction::TYPE_PAYPAL
            )
        ));

        if (count($transactions)) {
            return true;
        }

        return false;
    }

    public function __get($name)
    {
        return $this->data[$name];
    }
}