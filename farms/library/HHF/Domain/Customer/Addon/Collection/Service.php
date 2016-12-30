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
 * @copyright $Date: 2014-03-17 21:52:17 -0300 (Mon, 17 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Add on model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 728 2014-03-18 00:52:17Z farmnik $
 * @copyright $Date: 2014-03-17 21:52:17 -0300 (Mon, 17 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Addon_Collection_Service extends HH_Object_Collection_Service
{
    /**
     * @var HHF_Domain_Customer_Addon_Collection
     */
    protected $_collection;

    public function save($purchasedAddons)
    {
        $filter = HHF_Domain_Customer_Addon::getFilter(
            HHF_Domain_Customer_Addon::FILTER_NEW,
            array(
                'farm' => $this->_collection->getFarm()
            )
        );

        $errors = array();

        foreach ($purchasedAddons as $key => $purchasedAddon) {

            // default to addon being marked paidInFull until invoiced
            $purchasedAddon['paidInFull'] = $purchasedAddons[$key]['paidInFull'] = 1;

            $filter->setData($purchasedAddon);

            if (!$filter->isValid()) {
                $addonErrors = $filter->getMessages();

                if (!array_key_exists('addons', $errors)) {
                    $errors['addons'] = array();
                }

                $errors['addons'][$key] = $addonErrors;
            }
        }

        if (!empty($errors)) {
            throw new HH_Object_Exception_Validation($errors);
        }

        // insert
        foreach ($purchasedAddons as $purchasedAddon) {
            $purchasedAddonObject = new HHF_Domain_Customer_Addon(
                $this->_collection->getFarm()
            );

            $addon = $this->_collection->getRelatedAddon(
                $purchasedAddon['addonId']
            );

            $purchasedAddonObject->setAddon($addon);
            $purchasedAddonObject->getService()->save($purchasedAddon);

            $this->_collection[] = $purchasedAddonObject;
        }
    }

    /**
     * @param Zend_Date $dueDate
     * @return \HHF_Domain_Customer_Invoice
     */
    public function createInvoice(Zend_Date $dueDate)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');

        $invoice = new HHF_Domain_Customer_Invoice(
            $this->_collection->getFarm()
        );

        $data = array(
            'customerId' => $this->_collection->offsetGet(0)->customerId,
            'type' => HHF_Domain_Customer_Invoice::TYPE_ADDONS,
            'pending' => 0,
            'dueDate' => $dueDate->toString('yyyy-MM-dd'),
            'paid' => 0,
            'subTotal' => 0.00,
            'tax' => null,
            'total' => 0.00,
            'outstandingAmount' => 0.00,
            'message' => null,
            'lines' => array()
        );

        foreach ($this->_collection as $customerAddon) {

            $addon = $this->_collection->getRelatedAddon(
                $customerAddon['addonId']
            );

            if (isset($addon['pendingOnOrder'])
                && $addon['pendingOnOrder'] == 1) {

                $data['pending'] = 1;
            }

            $data['lines'][] = array(
                'type' => HHF_Domain_Customer_Invoice_Line::TYPE_ADDON,
                'referenceId' => $customerAddon['id'],
                'description' => $addon['name'],
                'unitPrice' => $addon['price'],
                'quantity' => $customerAddon['quantity'],
                'taxable' => 0,
            );
        }

        $invoice->getService()->save($data);

        return $invoice;
    }

    /**
     * Email farm about add on products purchased
     *
     * @param null $location
     * @param null $deliveryDate
     * @param null $customer
     * @param HHF_Domain_Customer_Invoice $invoice
     */
    public function emailFarm(
        $location = null,
        $deliveryDate = null,
        $customer = null,
        HHF_Domain_Customer_Invoice $invoice = null
    ) {
        $farm = $this->_collection->getFarm();

        if (empty($farm->email)) {
            return;
        }

        if ($customer === null) {
            $customer = new HHF_Domain_Customer(
                $this->_collection->getFarm(),
                $this->_collection->offsetGet(0)->customerId
            );
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
        $view->deliveryDate = $deliveryDate;
        $view->purchases = $this->_collection;
        $view->invoice = $invoice;
        $view->farm = $farm;

        $layout->content = $view->render('public/addons-email-farm.phtml');

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

        if (!empty($invoice) && $invoice['pending']) {
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

        $email = new HH_Job_Email();
        $email->add(
            array($farm->email, $farm->name),
            $farm->email,
            $translate->_('New Add On Products Purchased From HarvestHand'),
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
     * Email customer about add on products purchased
     *
     * @param null $deliveryDate
     * @param null $customer
     * @param HHF_Domain_Customer_Invoice $invoice
     */
    public function emailCustomer(
        $deliveryDate = null,
        $customer = null,
        HHF_Domain_Customer_Invoice $invoice = null
    ) {
        if ($customer === null) {
            $customer = new HHF_Domain_Customer(
                $this->_collection->getFarm(),
                $this->_collection->offsetGet(0)->customerId
            );
        }

        if (empty($customer->email)) {
            return;
        }

        $farm = $this->_collection->getFarm();

        $layout = new Zend_Layout();
        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
        $layout->setLayout('email');
        $layout->getView()->farm = $farm;

        $view = new Zend_View();
        $view->setScriptPath(
            Bootstrap::$farmModules . 'shares/views/scripts/'
        );

        $view->customer = $customer;
        $view->purchases = $this->_collection;
        $view->deliveryDate = $deliveryDate;
        $view->invoice = $invoice;
        $view->farm = $farm;

        $layout->content = $view->render(
            'public/addons-email-customer.phtml'
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

        $email = new HH_Job_Email();
        $email->add(
            $from,
            array(
                $customer->email,
                $customer->firstName . ' ' . $customer->lastName
            ),
            sprintf(
                $translate->_('New Add On Products Purchased From %s'),
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
