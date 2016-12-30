<?php
//@formatter:off
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
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of AdminController
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Customers_AdminController extends HHF_Controller_Action
{
    public function  init()
    {
        parent::init();
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_FARMER);
        $this->view->headTitle($this->translate->_('Administration'));
    }

    public function searchAction()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $term = $this->getRequest()->getParam('term', false);

        if(!empty($term)) {

            if (is_numeric($term)) {
                $where = array(
                    'id' => $term
                );
            } else {

                $term = Bootstrap::getZendDb()->quote('%' . addcslashes($term, '%') . '%');

                $where = array(
                    ' (firstName LIKE ' . $term . ' OR lastName LIKE ' . $term . ') '
                );
            }

            $customers = HHF_Domain_Customer::fetch(
                $this->farm,
                array(
                    'columns' => array(
                        '*'
                    ),
                    'where' => $where
                )
            )->toArray();

            $data = array();
            if(!empty($customers)) {
                foreach($customers as $customer) {
                    array_push($data, array(
                        'id' => $customer['id'],
                        'label' => $customer['firstName'] . ' ' . $customer['lastName']
                    ));
                }
            }

            $this->_response->appendBody(
                json_encode($data, true)
            );
            return;
        }

        $this->_response->appendBody(
            Zend_Json::encode(array())
        );
    }

    public function  indexAction()
    {
        $this->view->count = HHF_Domain_Customer::fetchCustomerCount(
            $this->farm
        );

        $this->view->customerList = HHF_Domain_Customer::fetchCustomerList(
            $this->farm
        );

        $this->view->transactionsPending = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    t.*,
                    c.id as customerId,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName
                FROM
                    __DATABASE__ as t
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    t.customerId = c.id',
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'remainingToApply > 0'
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'transactionDate',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->invoicesPending = HHF_Domain_Customer_Invoice::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    i.*,
                    c.id as customerId,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName
                FROM
                    __DATABASE__ as i
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    i.customerId = c.id',
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'pending' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'dueDate',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );
    }

    public function customersAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_customersData();
        }

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv' || $format == 'vcf') {
            $limit = null;
            $columns = '*';
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );
            $columns = array(
                'id',
                'firstName',
                'lastName',
                'enabled',
                'addedDatetime',
                'email',
                'secondaryEmail',
                'balance'
            );
        }

        $this->view->customers = HHF_Domain_Customer::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => $columns,
                'limit' => $limit,
                'order' => $this->farmer->getPreferences()->getStructure(
                    'customers',
                    'lists',
                    array(
                        array(
                            'column' => 'lastName',
                            'dir' => 'asc'
                        )
                    )
                )
            )
        );

        if ($format == 'csv') {
            return $this->render('customers.csv');
        } else if ($format == 'vcf') {
            $this->_helper->layout->disableLayout();
            $this->_response->setHeader(
                'Content-Type',
                'text/vcard'
            );
            $this->_response->setHeader(
                'Content-Disposition',
                'attachment; filename="customers.vcf"'
            );
            return $this->render('customers.vcf');
        }

        $this->view->foundRows = $this->view->customers->getFoundRows();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _customersData()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array(
            'firstName',
            'lastName',
            'balance',
            'addedDatetime',
            'enabled'
        );

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $where = array(
                '(firstName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ' OR lastName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ')'
            );
        } else {
            $where = array();
        }

        $this->farmer->getPreferences()->replaceStructure(
            'customers',
            $order,
            'lists'
        );

        $rows = HHF_Domain_Customer::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'firstName',
                    'lastName',
                    'enabled',
                    'addedDatetime',
                    'email',
                    'secondaryEmail',
                    'balance'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Customer::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        $currency = Bootstrap::getZendCurrency();

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $data['balance'] = $currency->toCurrency($data['balance']);
            $data['addedDatetime'] = $data['addedDatetime']
                ->toString('yyyy-MM-dd');
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function customerAction()
    {
        $id = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($id)) {
            return $this->_customerNew();
        }

        switch ($action) {
            case self::ACTION_DELETE :
                return $this->_customerDelete($id);
                break;
            case self::ACTION_EDIT :
                return $this->_customerEdit($id);
                break;
            default :
                return $this->_customerView($id, $action);
                break;
        }
    }

    protected function _customerNew()
    {
        $this->view->customer = false;

        $this->view->getFormValue()->setDefaulVars(
            array(
                'state' => $this->farm->state,
                'country' => $this->farm->country
            )
        );

        if (!empty($_POST)) {

            $this->view->errors = array();

            $filter = HHF_Domain_Customer::getFilter(
                HHF_Domain_Customer::FILTER_NEW
            );

            $filter->setData($_POST);

            if (!$filter->isValid()) {
                $this->view->errors = $filter->getMessages();
                return;
            }

            if (!empty($_POST['farmer']['userName'])) {
                $filterFarmer = HH_Domain_Farmer::getFilter(
                    HH_Domain_Farmer::FILTER_NEW,
                    array(
                        'role' => HH_Domain_Farmer::ROLE_MEMBER
                    )
                );

                $_POST['farmer']['firstName'] = $filter->getUnescaped('firstName');
                $_POST['farmer']['lastName'] = $filter->getUnescaped('lastName');
                $_POST['farmer']['email'] = $filter->getUnescaped('email');
                $_POST['farmer']['email2'] = $filter->getUnescaped('secondaryEmail');

                $filterFarmer->setData($_POST['farmer']);

                if (!$filterFarmer->isValid()) {
                    $this->view->errors['farmer'] = $filterFarmer->getMessages();
                }
            }

            if (empty($this->view->errors)) {

                $data = $filter->getUnescaped();

                if (!empty($_POST['farmer']['userName'])) {
                    $farmer = new HH_Domain_Farmer();

                    $farmerData = $filterFarmer->getUnescaped();
                    $farmerData['farmId'] = $this->farm->id;
                    $farmerData['role'] = HH_Domain_Farmer::ROLE_MEMBER;

                    $farmer->getService()->save($farmerData);

                    $data['farmerId'] = $farmer->id;
                }

                $customer = new HHF_Domain_Customer($this->farm);
                $customer->insert($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Customer "%s %s" added!'),
                        $customer->firstName,
                        $customer->lastName
                    )
                );

                $this->_redirect('/admin/customers/customer?id=' . $customer['id'], array('exit' => true));

            }
        }

        $this->render('customer.new');
    }

    protected function _customerDelete($id)
    {
        $customer = new HHF_Domain_Customer($this->farm, $id);

        if ($customer->isEmpty()) {
            $this->_redirect('/admin/customers/customers', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Customer "%s %s" deleted!'),
                $customer->firstName,
                $customer->lastName
            )
        );

        try {
            $customer->getService()->remove();
        } catch (Exception $exception) {
            throw $exception;
        }

        $this->_redirect('/admin/customers/customers', array('exit' => true));
    }

    protected function _customerEdit($id)
    {
        $currentCustomer = $this->view->customer = new HHF_Domain_Customer(
            $this->farm,
            $id
        );

        if ($currentCustomer->isEmpty()) {
            $this->_redirect('/admin/customers/customer', array('exit' => true));
        }

        if ($this->_request->getParam('format') == 'vcf') {
            $this->_helper->layout->disableLayout();
            $this->_response->setHeader(
                'Content-Type',
                'text/vcard'
            );
            $this->_response->setHeader(
                'Content-Disposition',
                'attachment; filename="customer-' . (int) $id . '.vcf"'
            );
            return $this->render('customer.vcf');
        }

        $defaultVars = $currentCustomer->toArray();

        if (!empty($currentCustomer->farmerId)) {
            $farmer = $currentCustomer->getFarmer();
            if ($farmer instanceof HH_Domain_Farmer) {
                $defaultVars['farmer'] = $farmer->toArray();
                unset($defaultVars['farmer']['password']);
            } else {
                $defaultVars['farmer'] = array();
            }
        }

        $defaultVars['preferences'] = array(
            'newsletter' => array(
                'optOut' => $currentCustomer->getPreferences()->get('optOut', 'newsletter', 0)
            )
        );

        $this->view->getFormValue()->setDefaulVars($defaultVars);

        if (!empty($_POST)) {
            $this->view->errors = array();

            $filter = HHF_Domain_Customer::getFilter(
                HHF_Domain_Customer::FILTER_EDIT
            );

            $toValidate = $_POST;
            unset($toValidate['farmer']);
            unset($toValidate['preferences']);

            $filter->setData($toValidate);

            if (!$filter->isValid()) {
                $this->view->errors = $filter->getMessages();
                return;
            }

            if (!empty($_POST['farmer']['userName'])) {
                $currentFarmer = ($currentCustomer->farmerId) ?
                    HH_Domain_Farmer::singleton($currentCustomer->farmerId) : null;

                $filterType = (!empty($currentCustomer->farmerId)) ?
                    HH_Domain_Farmer::FILTER_EDIT : HH_Domain_Farmer::FILTER_NEW;

                $filterFarmer = HH_Domain_Farmer::getFilter(
                    $filterType,
                    array(
                        'role' => HH_Domain_Farmer::ROLE_MEMBER,
                        'farmer' => $currentFarmer,
                        'farm' => $this->farm
                    )
                );

                $_POST['farmer']['firstName'] = $filter->getUnescaped('firstName');
                $_POST['farmer']['lastName'] = $filter->getUnescaped('lastName');
                $_POST['farmer']['email'] = $filter->getUnescaped('email');
                $_POST['farmer']['email2'] = $filter->getUnescaped('secondaryEmail');

                $filterFarmer->setData($_POST['farmer']);

                if (!$filterFarmer->isValid()) {
                    $this->view->errors['farmer'] = $filterFarmer->getMessages();
                }
            }

            if (empty($this->view->errors)) {

                $data = $filter->getUnescaped();

                if (!empty($_POST['farmer']['userName']) && !empty($currentCustomer->farmerId)) {
                    $farmer = new HH_Domain_Farmer($currentCustomer->farmerId);

                    $farmerData = $filterFarmer->getUnescaped();
                    $farmerData['farmId'] = $this->farm->id;
                    $farmerData['role'] = HH_Domain_Farmer::ROLE_MEMBER;

                    if (array_key_exists('password', $farmerData) && empty($farmerData['password'])) {
                        unset($farmerData['password']);
                    }

                    $farmer->getService()->save($farmerData);

                } else if (!empty($_POST['farmer']['userName']) && empty($currentCustomer->farmerId)) {
                    $farmer = new HH_Domain_Farmer();

                    $farmerData = $filterFarmer->getUnescaped();
                    $farmerData['farmId'] = $this->farm->id;
                    $farmerData['role'] = HH_Domain_Farmer::ROLE_MEMBER;

                    $farmer->getService()->save($farmerData);

                    $data['farmerId'] = $farmer->id;
                } else if (empty($_POST['farmer']['userName']) && !empty($currentCustomer->farmerId)) {
                    $farmer = new HH_Domain_Farmer($currentCustomer->farmerId);

                    $farmer->getService()->remove();
                    $data['farmerId'] = null;
                }

                $currentCustomer->update($data);

                if (!empty($_POST['preferences'])) {
                    foreach ($_POST['preferences'] as $resource => $groupPreferences) {
                        foreach ($groupPreferences as $key => $value) {
                            $currentCustomer->getPreferences()->replace($key, $value, $resource);
                        }
                    }
                }

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Customer "%s %s" updated!'),
                        $currentCustomer->firstName,
                        $currentCustomer->lastName
                    )
                );

                $this->_redirect(
                    '/admin/customers/customer?id=' . $currentCustomer['id'],
                    array('exit' => true)
                );

            }
        }

        $this->render('customer.edit');
    }

    protected function _customerView($id, $action)
    {
        $currentCustomer = $this->view->customer = new HHF_Domain_Customer(
            $this->farm,
            $id
        );

        if ($currentCustomer->isEmpty()) {
            $this->_redirect('/admin/customers/customer', array('exit' => true));
        } else {

            if ($this->_request->getParam('format') == 'vcf') {
                $this->_helper->layout->disableLayout();
                $this->_response->setHeader(
                    'Content-Type',
                    'text/vcard'
                );
                $this->_response->setHeader(
                    'Content-Disposition',
                    'attachment; filename="customer-' . (int) $id . '.vcf"'
                );
                return $this->render('customer.vcf');
            }

            switch ($action) {
                case 'addonDelete' :
                    $addon = new HHF_Domain_Customer_Addon(
                        $this->farm,
                        (int) $this->_request->getParam('addonId', 0)
                    );

                    if (!$addon->isEmpty()) {
                        $addon->getService()->remove();

                        $this->_helper->getHelper('FlashMessenger')
                            ->addMessage(
                                $this->translate->_('Purchased product deleted!')
                            );

                        $this->_redirect(
                            '/admin/customers/customer?id=' . $id,
                            array('exit' => true)
                        );
                    }

                    break;
                case 'shareDelete' :

                    $share = new HHF_Domain_Customer_Share(
                        $this->farm,
                        (int) $this->_request->getParam('shareId', 0)
                    );

                    if (!$share->isEmpty()) {
                        $share->getService()->remove();

                        $this->_helper->getHelper('FlashMessenger')
                            ->addMessage(
                                $this->translate->_('Subscription deleted!')
                            );

                        $this->_redirect(
                            '/admin/customers/customer?id=' . $id,
                            array('exit' => true)
                        );
                    }

                    break;

                case 'payBalance' :

                    if ($currentCustomer['balance'] > 0) {
                        try {

                            $currentCustomer->getService()->payAllOpenInvoices();

                            $this->_helper->getHelper('FlashMessenger')
                                ->addMessage(
                                    sprintf(
                                        $this->translate->_(
                                            'Balance paid in full for %s %s!'
                                        ),
                                        $currentCustomer['firstName'],
                                        $currentCustomer['lastName']
                                    )
                                );

                        } catch (HHF_Domain_Customer_Exception_TransactionsToApply $exception) {
                            unset($exception);

                            $this->_helper->getHelper('FlashMessenger')
                                ->addMessage(
                                    sprintf(
                                        $this->translate->_(
                                            '%s %s has payments that have not yet been applied.  Fix those up first!'
                                        ),
                                        $currentCustomer['firstName'],
                                        $currentCustomer['lastName']
                                    )
                                );
                        }

                        if ($this->_request->getParam('r', 'i') == 's') {
                            $this->_redirect(
                                '/admin/customers/customers',
                                array('exit' => true)
                            );
                        } else {
                            $this->_redirect(
                                '/admin/customers/customer?id=' . $id,
                                array('exit' => true)
                            );
                        }
                    }

                    break;
            }

            $this->view->messages = $this->_helper->getHelper('FlashMessenger')
                ->getMessages();
        }

        $this->render('customer.view');
    }

    public function purchaseHistorySharesAction()
    {
        $customerId = (int) $this->_request->getParam('cid');
        $this->view->customerId = $customerId;

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_purchaseHistorySharesData($customerId);
        }
        $this->view->subscriptions = HHF_Domain_Customer_Share::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    sh.name as shareName,
                    sh.year as shareYear,
                    l.name as locationName,
                    s.id,
                    s.quantity,
                    s.paidInFull
                FROM
                    __DATABASE__ as s
                LEFT JOIN
                    __SCHEMA__.shares as sh
                ON
                    s.shareId = sh.id
                LEFT JOIN
                    __SCHEMA__.locations as l
                ON
                    s.locationId = l.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    's.customerId' => $customerId
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'sh.year',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'sh.name',
                        'dir' => 'desc'
                    )
                )
            )
        );

		$this->view->foundRows = $this->view->subscriptions->getFoundRows();
    }

    public function _purchaseHistorySharesData($customerId)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('shareName', 'shareYear', 'locationName', 'quantity', 'paidInFull');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $rows = HHF_Domain_Customer_Share::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    sh.name as shareName,
                    sh.year as shareYear,
                    l.name as locationName,
                    s.id,
                    s.quantity,
                    s.paidInFull
                FROM
                    __DATABASE__ as s
                LEFT JOIN
                    __SCHEMA__.shares as sh
                ON
                    s.shareId = sh.id
                LEFT JOIN
                    __SCHEMA__.locations as l
                ON
                    s.locationId = l.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => array(
                    's.customerId' => $customerId
                )
            )
        );

        $iTotalRecords = $rows->getFoundRows();
        $iTotalDisplayRecords = $rows->getFoundRows();

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function purchaseHistoryAddonsAction()
    {
        $customerId = (int) $this->_request->getParam('cid');
        $this->view->customerId = $customerId;

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_purchaseHistoryAddonsData($customerId);
        }

        $this->view->addons = HHF_Domain_Customer_Addon::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    a.*,
                    ad.name as addonName
                FROM
                    __DATABASE__ as a
                LEFT JOIN
                    __SCHEMA__.addons as ad
                ON
                    a.addonId = ad.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'a.customerId' => $customerId
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->addons->getFoundRows();
    }

    public function _purchaseHistoryAddonsData($customerId)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('week', 'addonName', 'quantity', 'paidInFull');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $rows = HHF_Domain_Customer_Addon::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    a.*,
                    ad.name as addonName
                FROM
                    __DATABASE__ as a
                LEFT JOIN
                    __SCHEMA__.addons as ad
                ON
                    a.addonId = ad.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => array(
                    'a.customerId' => $customerId
                )
            )
        );

        $iTotalRecords = $rows->getFoundRows();
        $iTotalDisplayRecords = $rows->getFoundRows();

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function invoicesHistoryAction()
    {
        $customerId = (int) $this->_request->getParam('cid');
		$format = $this->_request->getParam('format', false);

        $this->view->customerId = $customerId;

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_invoicesHistoryData($customerId);
        }

        $this->view->invoices = HHF_Domain_Customer_Invoice::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'customerId' => $customerId
                ),
                'order' => array(
                    array(
                        'column' => 'dueDate',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->customerId = $customerId;

		if ($format == 'csv') {
			return $this->render('invoices-history.csv');
		}

        $this->view->foundRows = $this->view->invoices->getFoundRows();
    }

    public function _invoicesHistoryData($customerId)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('id', 'dueDate', 'total', 'outstandingAmount');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $rows = HHF_Domain_Customer_Invoice::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'customerId' => $customerId
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
            )
        );

        $iTotalRecords = $rows->getFoundRows();
        $iTotalDisplayRecords = $rows->getFoundRows();

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        $currency = Bootstrap::getZendCurrency();

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            if (is_numeric($data['outstandingAmount'])) {
                $data['outstandingAmount'] = $currency->toCurrency($data['outstandingAmount']);
            }
            if (is_numeric($data['total'])) {
                $data['total'] = $currency->toCurrency($data['total']);
            }
            $data['dueDate'] = $data['dueDate']->toString('yyyy-MM-dd');

            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function transactionsHistoryAction()
    {
        $customerId = (int) $this->_request->getParam('cid');
        $this->view->customerId = $customerId;

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_transactionsHistoryData($customerId);
        }

        $this->view->transactions = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'customerId' => $customerId
                ),
                'order' => array(
                    array(
                        'column' => 'transactionDate',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->transactions->getFoundRows();
    }

    public function _transactionsHistoryData($customerId)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('id', 'transactionDate', 'total', 'remainingToApply', 'appliedToInvoices');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $rows = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'customerId' => $customerId
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
            )
        );

        $iTotalRecords = $rows->getFoundRows();
        $iTotalDisplayRecords = $rows->getFoundRows();

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        $currency = Bootstrap::getZendCurrency();

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            if (is_numeric($data['total'])) {
                $data['total'] = $currency->toCurrency($data['total']);
            }
            $data['transactionDate'] = $data['transactionDate']->toString('yyyy-MM-dd');

            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function subscriptionsAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Customer_Share::fetch(
            $this->farm,
            array(
                'columns' => 'year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $this->view->years = array();

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_subscriptionsData($year);
        }

        $this->view->year = $year;

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv') {
            $limit = null;
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );
        }

        $this->view->subscriptions = HHF_Domain_Customer_Share::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    s.*,
                    sh.name as shareName,
                    sd.startWeek as shareDurationStartWeek,
                    sd.iterations as shareDurationIterations,
                    si.name as shareSizeName,
                    c.id as customerId,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.address as customerAddress,
                    c.address2 as customerAddress2,
                    c.city as customerCity,
                    c.telephone as customerTelephone,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail,
                    l.name as locationName
                FROM
                    __DATABASE__ as s
                LEFT JOIN
                    __SCHEMA__.shares as sh
                ON
                    s.shareId = sh.id
                LEFT JOIN
                    __SCHEMA__.sharesDurations as sd
                ON
                    s.shareDurationId = sd.id
                LEFT JOIN
                    __SCHEMA__.sharesSizes as si
                ON
                    s.shareSizeId = si.id
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    s.customerId = c.id
                LEFT JOIN
                    __SCHEMA__.locations as l
                ON
                    s.locationId = l.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    's.year' => $year
                ),
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'customerLastName',
                        'dir' => 'asc'
                    ),
                    array(
                        'column' => 'customerFirstName',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->subscriptions->getFoundRows();

        if ($format == 'csv') {
            $this->render('subscriptions.csv');
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _subscriptionsData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('customerLastName', 'shareName', 'locationName', 'quantity', 'paidInFull');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $where = array(
                's.year' => $year,
                '(firstName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ' OR lastName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ')'
            );
        } else {
            $where = array(
                's.year' => $year
            );
        }

        $rows = HHF_Domain_Customer_Share::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.id as customerId,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail,
                    sh.name as shareName,
                    l.name as locationName,
                    s.id,
                    s.quantity,
                    s.paidInFull
                FROM
                    __DATABASE__ as s
                LEFT JOIN
                    __SCHEMA__.shares as sh
                ON
                    s.shareId = sh.id
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    s.customerId = c.id
                LEFT JOIN
                    __SCHEMA__.locations as l
                ON
                    s.locationId = l.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Customer_Share::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function addonsAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Customer_Addon::fetch(
            $this->farm,
            array(
                'columns' => 'SUBSTRING(week, 1, 4) as year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $this->view->years = array();

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_addonsData($year);
        }

        $this->view->year = $year;

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv') {
            $limit = null;
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );
        }

        $this->view->addons = HHF_Domain_Customer_Addon::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    a.*,
                    ad.name as addonName,
                    c.id as customerId,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.address as customerAddress,
                    c.address2 as customerAddress2,
                    c.city as customerCity,
                    c.telephone as customerTelephone,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail
                FROM
                    __DATABASE__ as a
                LEFT JOIN
                    __SCHEMA__.addons as ad
                ON
                    a.addonId = ad.id
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    a.customerId = c.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'a.week LIKE \'' . $year . '%\''
                ),
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'week',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->addons->getFoundRows();

        if ($format == 'csv') {
            $this->render('addons.csv');
            return;
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _addonsData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('week', 'customerLastName', 'addonName', 'quantity', 'paidInFull');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $where = array(
                'a.week LIKE \'' . $year . '%\'',
                '(firstName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ' OR lastName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ')'
            );
        } else {
            $where = array(
                'a.week LIKE \'' . $year . '%\''
            );
        }

        $rows = HHF_Domain_Customer_Addon::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.id as customerId,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail,
                    ad.name as addonName,
                    a.id,
                    a.quantity,
                    a.week,
                    a.paidInFull
                FROM
                    __DATABASE__ as a
                LEFT JOIN
                    __SCHEMA__.addons as ad
                ON
                    a.addonId = ad.id
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    a.customerId = c.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Customer_Addon::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function invoicesAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Customer_Invoice::fetch(
            $this->farm,
            array(
                'columns' => 'YEAR(dueDate) as year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $this->view->years = array();

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_invoicesData($year);
        }

        $this->view->year = $year;

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv') {
            $limit = null;
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );
        }

        $this->view->invoices = HHF_Domain_Customer_Invoice::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    i.*,
                    c.id as customerId,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.address as customerAddress,
                    c.address2 as customerAddress2,
                    c.city as customerCity,
                    c.telephone as customerTelephone,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail
                FROM
                    __DATABASE__ as i
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    i.customerId = c.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'YEAR(i.dueDate) = ' . (int) $year
                ),
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'dueDate',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->invoices->getFoundRows();

        if ($format == 'csv') {
            $this->render('invoices.csv');
            return;
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _invoicesData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('id', 'customerLastName', 'dueDate', 'total', 'outstandingAmount');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $where = array(
                'YEAR(i.dueDate) = ' . (int) $year,
                '(firstName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ' OR lastName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ')'
            );
        } else {
            $where = array(
                'YEAR(i.dueDate) = ' . (int) $year
            );
        }

        $rows = HHF_Domain_Customer_Invoice::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.id as customerId,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail,
                    i.id,
                    i.dueDate,
                    i.total,
                    i.outstandingAmount
                FROM
                    __DATABASE__ as i
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    i.customerId = c.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Customer_Invoice::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        $currency = Bootstrap::getZendCurrency();

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            if (is_numeric($data['outstandingAmount'])) {
                $data['outstandingAmount'] = $currency->toCurrency($data['outstandingAmount']);
            }
            if (is_numeric($data['total'])) {
                $data['total'] = $currency->toCurrency($data['total']);
            }
            $data['dueDate'] = $data['dueDate']->toString('yyyy-MM-dd');

            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function invoiceAction()
    {
        $id = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        switch ($action) {
            case self::ACTION_DELETE :
                return $this->_invoiceDelete($id);
                break;
            case self::ACTION_EDIT :
                return $this->_invoiceEdit($id);
                break;
            case 'payment' :
                return $this->_invoicePayment($id);
                break;
            case 'unapply' :
                return $this->_invoiceUnapply($id);
                break;
            default :
                return $this->_invoiceView($id);
                break;
        }
    }

    protected function _invoicePayment($id)
    {
        $invoice = new HHF_Domain_Customer_Invoice($this->farm, $id);

        if ($invoice->isEmpty()) {
            $this->_redirect('/admin/customers/invoices', array('exit' => true));
        }

        if ($invoice['paid']) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t mark invoice as paid.  It\'s already been marked as paid.')
                )
            );

            $this->_redirect('/admin/customers/invoice?id=' . (int) $invoice['id'], array('exit' => true));
        }

        // check for unapplied transactions
        $transactions = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'where' => array(
                    'customerId' => $invoice['customerId'],
                    'remainingToApply > 0'
                )
            )
        );

        if ($transactions->count() > 0) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $customer = $invoice->getCustomer();

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('%s %s has payments that have not yet been applied.  Fix those up first!'),
                    $customer['firstName'],
                    $customer['lastName']
                )
            );

            $this->_redirect('/admin/customers/invoice?id=' . (int) $invoice['id'], array('exit' => true));
        }

        $type = $this->_request->getParam('type', HHF_Domain_Transaction::TYPE_CASH);

        if (!in_array($type, array(HHF_Domain_Transaction::TYPE_CASH, HHF_Domain_Transaction::TYPE_CHEQUE))) {
            $type = HHF_Domain_Transaction::TYPE_CASH;
        }

        $total = (float) $this->_request->getParam('total', 0);

        if (empty($total) || $total > $invoice['outstandingAmount']) {
            $total = null;
        }

        $invoice->getService()->issueTransaction($total, $type);

        $messenger = $this->_helper->getHelper('FlashMessenger');

        if ($total == $invoice['outstandingAmount']) {

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Invoice all paid up!')
                )
            );
        } else {
            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Invoice paid in part!')
                )
            );
        }

        $this->_redirect('/admin/customers/invoice?id=' . (int) $invoice['id'], array('exit' => true));
    }

    protected function _invoiceUnapply($id)
    {
        $invoice = new HHF_Domain_Customer_Invoice($this->farm, $id);

        if ($invoice->isEmpty()) {
            $this->_redirect('/admin/customers/invoices', array('exit' => true));
        }

        $dueDate = new DateTime('@' . $invoice['dueDate']->getTimestamp());
        $now = new DateTime();

        if ($invoice['total'] != $invoice['outstandingAmount'] &&
            !$invoice['paid'] &&
            $invoice['appliedToBalance'] &&
            $dueDate > $now) {

            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t mark invoice as un-applied.  It doesn\'t pass muster.')
                )
            );

            $this->_redirect('/admin/customers/invoice?id=' . (int) $invoice['id'], array('exit' => true));
        }

        $invoice->getService()->unapplyFromCustomerBalance();

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Un-applied from the customer\'s balance!')
            )
        );

        $this->_redirect('/admin/customers/invoice?id=' . (int) $invoice['id'], array('exit' => true));
    }

    protected function _invoiceView($id)
    {
        $invoice = new HHF_Domain_Customer_Invoice($this->farm, $id);

        if ($invoice->isEmpty()) {
            $this->_redirect('/admin/customers/invoices', array('exit' => true));
        }

        $this->view->object = $invoice;
        $this->view->customer = $invoice->getCustomer();
        $this->view->transactions = $invoice->getTransactions();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        $this->render('invoice.view');
    }

    protected function _invoiceEdit($id)
    {
        $invoice = new HHF_Domain_Customer_Invoice($this->farm, $id);

        if ($invoice->isEmpty()) {
            $this->_redirect('/admin/customers/invoices', array('exit' => true));
        }

        if ($invoice['paid'] || $invoice['total'] != $invoice['outstandingAmount']) {
            $this->_redirect(
                '/admin/customers/invoice?id=' . $invoice['id'],
                array('exit' => true)
            );
        }

        $pending = $invoice['pending'];

        $this->view->object = $invoice;
        $this->view->customer = $invoice->getCustomer();

        if (!empty($_POST)) {
            try {

                $_POST['pending'] = 0;

                $invoice->getService()->save($_POST);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                if ($pending) {

                    $messenger->addMessage(
                        sprintf(
                            $this->translate->_('Invoice finalized!')
                        )
                    );
                } else {
                    $messenger->addMessage(
                        sprintf(
                            $this->translate->_('Invoice updated!')
                        )
                    );
                }

                $this->_redirect(
                    '/admin/customers/invoice?id=' . $invoice['id'],
                    array('exit' => true)
                );

            } catch (HH_Object_Exception_Validation $exception) {
                $this->view->errors = $exception->getErrorMessages();
            }
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        $this->render('invoice.edit');
    }

    public function subscriptionAction()
    {
        $id = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($id)) {
            return $this->_subscriptionNew();
        }

        switch ($action) {
            case self::ACTION_DELETE :
                return $this->_subscriptionDelete($id);
                break;
            case self::ACTION_EDIT :
                return $this->_subscriptionEdit($id);
                break;
            case 'payment' :
                return $this->_subscriptionPayment($id);
                break;
            default :
                return $this->_subscriptionView($id);
                break;
        }
    }

    protected function _subscriptionPayment($id)
    {
        $invoiceId = (int) $this->_request->getParam('iid', 0);
        $shareSubscription = new HHF_Domain_Customer_Share($this->farm, $id);

        if ($shareSubscription->isEmpty()) {
            $this->_redirect('/admin/customers/subscriptions', array('exit' => true));
        }

        $invoices = $shareSubscription->getCustomerInvoices();

        if (!count($invoices)) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t mark subscription as paid.  It\'s not been invoiced.')
                )
            );

            $this->_redirect('/admin/customers/subscription?id=' . (int) $shareSubscription['id'], array('exit' => true));
        }

        $targetInvoice = null;

        foreach ($invoices as $invoice) {
            if ($invoice['id'] == $invoiceId) {
                $targetInvoice = $invoice;
                break;
            }
        }

        if ($targetInvoice === null) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t find that invoice')
                )
            );

            $this->_redirect('/admin/customers/subscription?id=' . (int) $shareSubscription['id'], array('exit' => true));
        }

        if ($targetInvoice['paid']) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t mark invoice as paid.  It\'s already been marked as paid.')
                )
            );

            $this->_redirect('/admin/customers/subscription?id=' . (int) $shareSubscription['id'], array('exit' => true));
        }

        $targetInvoice->getService()->issueTransaction();

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Invoice all paid up!')
            )
        );

        $this->_redirect('/admin/customers/subscription?id=' . (int) $shareSubscription['id'], array('exit' => true));
    }

    protected function _subscriptionView($id)
    {
        $current = $this->view->object = new HHF_Domain_Customer_Share(
            $this->farm,
            $id
        );

        if ($current->isEmpty()) {
            $this->_redirect('/admin/customers/subscription', array('exit' => true));
        }

        $this->view->share = $current->getShare();

        $this->view->shareDuration = $this->view->share->getDurationById($current['shareDurationId']);

        $this->view->customer = $current->getCustomer();

        $this->view->location = $current->getLocation();

        $this->view->invoices = $current->getCustomerInvoices();

        $this->view->customerShare = $current;

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        return $this->render('subscription.view');
    }

    protected function _subscriptionNew()
    {
        $this->view->shares = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'where' => array(
                    '(year >= ' . (date('Y') - 1) . ')',
                    'enabled' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->customers = HHF_Domain_Customer::fetch(
            $this->farm,
            array(
                'columns' => array(
                    'id',
                    'lastName',
                    'firstName'
                ),
                'order' => array(
                    array(
                        'column' => 'lastName',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->locations = HHF_Domain_Location::fetchLocations(
            $this->farm
        );

        $this->view->plans = array(
            HHF_Order_Share::PAYMENT_PLAN_WEEKLY => $this->farm->
                getPreferences()->get('plansWeekly', 'shares', false),
            HHF_Order_Share::PAYMENT_PLAN_MONTHLY => $this->farm->
                getPreferences()->get('plansMonthly', 'shares', false),
            HHF_Order_Share::PAYMENT_PLAN_FIXED => $this->farm->
                getPreferences()->get('plansFixed', 'shares', false)
        );

        $this->view->getFormValue()->setDefaulVars(
            array(
                'quantity' => 1
            )
        );

        $this->view->subscription = false;

        if (!empty($_POST)) {

            $this->view->errors = array();

            $filter = HHF_Domain_Customer_Share::getFilter(
                HHF_Domain_Customer_Share::FILTER_NEW,
                array(
                    'farm' => $this->farm
                )
            );

            $currentShare = null;

            foreach ($this->view->shares as $share) {
                if ($share['id'] == $_POST['shareId']) {
                    $_POST['year'] = $share['year'];
                    $currentShare = $share;
                    break;
                }
            }

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $order = new HHF_Order_Share(
                    $this->farm,
                    array(),
                    $filter->getUnescaped('payment'),
                    $filter->getUnescaped('paymentPlan')
                );

                $fixedDates = null;

                switch ($filter->getUnescaped('paymentPlan')) {
                    case HHF_Order_Share::PAYMENT_PLAN_MONTHLY :
                        $downPayment = $this->farm->getPreferences()->
                            get('plansMonthlyUpfront', 'shares', false);
                        break;
                    case HHF_Order_Share::PAYMENT_PLAN_WEEKLY :
                        $downPayment = $this->farm->getPreferences()->
                            get('plansWeeklyUpfront', 'shares', false);
                        break;
                    case HHF_Order_Share::PAYMENT_PLAN_FIXED :

                        if (!empty($currentShare->planFixedDates)) {
                            HHF_Order_Share_PaymentPlan_Fixed::setDates(
                                $currentShare->planFixedDates,
                                Zend_Date::now()
                            );
                        }

                        $downPayment = false;
                        break;
                    default:
                        $downPayment = false;
                        break;
                }

                $shareItem = new HHF_Order_Item_Share(
                    $this->farm,
                    $filter->getUnescaped('shareId'),
                    $filter->getUnescaped('shareDurationId'),
                    $filter->getUnescaped('shareSizeId'),
                    $filter->getUnescaped('locationId'),
                    $filter->getUnescaped('quantity'),
                    $filter->getUnescaped('year')
                );

                list($year, $week) = explode('W', $_POST['startWeek']);

                $startDate = Zend_Date::now()
                    ->setWeekday(1)
                    ->setYear($year)
                    ->setWeek($week);

                $shareItem->setStartDate($startDate);

                if (!empty($downPayment)) {
                    $shareItem->setDownPayment(round($downPayment / 100, 2, PHP_ROUND_HALF_UP));
                }

                $order->addItem($shareItem);

                $order->setCustomer(
                    new HHF_Domain_Customer(
                        $this->farm,
                        $filter->getUnescaped('customerId')
                    )
                );
                $order->createCustomerShares(
                    $filter->getUnescaped('payment')
                );
                $order->getPaymentPlan()->createInvoices();

                $order->rewind();
                $subscriptionId = $order->current()->getCustomerShare()->id;

                $cusShare = HHF_Domain_Customer_Share::fetchOne($this->farm, ['where' => ['id' => $subscriptionId]]);
                $cusShare->notes = $filter->getUnescaped('notes');
                $cusShare->save();

                $messenger = $this->_helper->getHelper('FlashMessenger');

                // issue optional email to customer
                if (!empty($_POST['email'])) {

                    if (!empty($order->getCustomer()->email)) {

                        $layout = new Zend_Layout();
                        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                        $layout->setLayout('email');
                        $layout->getView()->farm = $this->farm;

                        $view = new Zend_View();
                        $view->setScriptPath(Bootstrap::$farmRoot . 'modules/shares/views/scripts/');
                        $view->customer = $order->getCustomer();
                        $view->order = $order;
                        $view->farm = $this->farm;
                        $view->paymentMethod = HHF_Domain_Transaction::TYPE_CASH;

                        if (!empty($this->farm->email)) {
                            $replyTo = array($this->farm->email, $this->farm->name);
                            $from = array($this->farm->email, $this->farm->name);
                        } else {
                            $replyTo = array(
                                $order->getCustomer()->email,
                                $order->getCustomer()->firstName . ' ' . $order->getCustomer()->lastName
                            );
                            $from = array(
                                $order->getCustomer()->email,
                                $order->getCustomer()->firstName . ' ' . $order->getCustomer()->lastName
                            );
                        }

                        $layout->content = $view->render('public/register-email-customer.phtml');

                        $email = new HH_Job_Email();
                        $email->add(
                            $from,
                            array(
                                $order->getCustomer()->email,
                                $order->getCustomer()->firstName . ' ' . $order->getCustomer()->lastName
                            ),
                            sprintf(
                                $this->translate->_('New Share Purchased From %s'),
                                $this->farm->name
                            ),
                            null,
                            $layout->render(),
                            $replyTo,
                            null,
                            null,
                            'farmnik@harvesthand.com',
                            'farmnik@harvesthand.com'
                        );

                        $messenger->addMessage(
                            sprintf(
                                $this->translate->_('Subscription added and email sent!')
                            )
                        );

                    } else {

                        $messenger->addMessage(
                            sprintf(
                                $this->translate->_('Subscription added! *** Bad news: we could not send that email to the cusomter because they don\'t have an email address on file. ***')
                            )
                        );
                    }
                } else {

                    $messenger->addMessage(
                        sprintf(
                            $this->translate->_('Subscription added!')
                        )
                    );
                }

                $this->_redirect(
                    '/admin/customers/subscription?id=' . $subscriptionId,
                    array('exit' => true)
                );

            } else {
                $this->view->errors = $filter->getMessages();
            }
        } else {
            HHF_Order_Share_PaymentPlan_Fixed::setDates(
                $this->farm->getPreferences()->
                    get('plansFixedDates', 'shares', false),
                Zend_Date::now()
            );
        }

        $this->render('subscription.new');
    }

    protected function _subscriptionDelete($id)
    {
        $subscription = new HHF_Domain_Customer_Share($this->farm, $id);

        if ($subscription->isEmpty()) {
            $this->_redirect('/admin/customers/subscriptions', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Subscription deleted!')
            )
        );

        $subscription->getService()->remove();

        $this->_redirect('/admin/customers/subscriptions', array('exit' => true));
    }

    protected function _subscriptionEdit($id)
    {
        $this->view->locations = HHF_Domain_Location::fetchLocations(
            $this->farm
        );

        $this->view->plans = array(
            HHF_Order_Share::PAYMENT_PLAN_WEEKLY => $this->farm->
                getPreferences()->get('plansWeekly', 'shares', false),
            HHF_Order_Share::PAYMENT_PLAN_MONTHLY => $this->farm->
                getPreferences()->get('plansMonthly', 'shares', false),
            HHF_Order_Share::PAYMENT_PLAN_FIXED => $this->farm->
                getPreferences()->get('plansFixed', 'shares', false)
        );

        $current = $this->view->subscription = new HHF_Domain_Customer_Share(
            $this->farm,
            $id
        );

        if ($current->isEmpty()) {
            $this->_redirect('/admin/customers/subscription', array('exit' => true));
        } else {

            $this->view->customer = $current->getCustomer();

            $this->view->share = $current->getShare();

            $this->view->invoices = $current->getCustomerInvoices();

            $this->view->getFormValue()->setDefaulVars(
                $current->toArray()
            );
        }

        if (!empty($_POST)) {
            $filter = HHF_Domain_Customer_Share::getFilter(
                HHF_Domain_Customer_Share::FILTER_EDIT,
                array(
                    'farm' => $this->farm
                )
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {
                $current->update($filter->getUnescaped());

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Subscription updated!')
                    )
                );

                $this->_redirect('/admin/customers/subscriptions', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }

        $this->render('subscription.edit');
    }

    public function addonAction()
    {
        $id = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($id)) {
            return $this->_addonNew();
        }

        switch ($action) {
            case self::ACTION_DELETE :
                return $this->_addonDelete($id);
                break;
            case self::ACTION_EDIT :
                return $this->_addonEdit($id);
                break;
            case 'payment' :
                return $this->_addonPayment($id);
                break;
            default :
                return $this->_addonView($id);
                break;
        }
    }

    protected function _addonPayment($id)
    {
        $addon = new HHF_Domain_Customer_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_redirect('/admin/customers/addons', array('exit' => true));
        }

        $invoice = $addon->getCustomerInvoice();

        if (empty($invoice)) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t mark addon as paid.  It\'s not been invoiced.')
                )
            );

            $this->_redirect('/admin/customers/addon?id=' . (int) $addon['id'], array('exit' => true));
        }

        if ($invoice['paid']) {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Can\'t mark invoice as paid.  It\'s already been marked as paid.')
                )
            );

            $this->_redirect('/admin/customers/addon?id=' . (int) $addon['id'], array('exit' => true));
        }

        $invoice->getService()->issueTransaction();

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Purchased add on product invoice all paid up!')
            )
        );

        $this->_redirect('/admin/customers/addon?id=' . (int) $addon['id'], array('exit' => true));
    }

    protected function _addonView($id)
    {
        $addon = new HHF_Domain_Customer_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_redirect('/admin/customers/addons', array('exit' => true));
        }

        $this->view->object = $addon;
        $this->view->customer = $addon->getCustomer();
        $this->view->addon = $addon->getAddon();
        $this->view->invoice = $addon->getCustomerInvoice();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        $this->render('addon.view');
    }

    protected function _addonNew()
    {
        $this->view->addons = HHF_Domain_Addon::fetch(
            $this->farm,
            array(
                'where' => 'enabled = 1 AND (inventory IS NULL OR inventory != 0)',
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->customers = HHF_Domain_Customer::fetch(
            $this->farm,
            array(
                'columns' => array(
                    'id',
                    'lastName',
                    'firstName'
                ),
                'order' => array(
                    array(
                        'column' => 'lastName',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->getFormValue()->setDefaulVars(
            array(
                'addons' => array(
                    array(
                        'quantity' => 1,
                        'week' => date('o\WW')
                    )
                )
            )
        );

        $this->view->addon = false;

        if (!empty($_POST)) {

            $this->view->errors = array();

            $customerAddons = new HHF_Domain_Customer_Addon_Collection(
                $this->farm
            );

            try {
                $subscriptions = HHF_Domain_Customer_Share::fetchShares(
                    $this->farm,
                    array(
                        'fetch' => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
                        'customer' => (int) $_POST['customerId'],
                        'filter' => 'active'
                    )
                );

                if (!count($subscriptions)) {
                    throw new HH_Object_Exception_Validation(
                        array(
                            'customerId' => array(
                                $this->translate->_('Customer does not have any active shares')
                            )
                        )
                    );
                }

                if (!empty($_POST['addons'])) {
                    foreach ($_POST['addons'] as &$value) {
                        $value['customerId'] = $_POST['customerId'];
                        $value['payment'] = $_POST['payment'];
                        $value['week'] = $_POST['week'];
                    }

                    unset($value);
                }

                $customerAddons->getService()->save(
                    (!empty($_POST['addons']) ? $_POST['addons'] : null)
                );

                $dueDate = Zend_Date::now()->getDate();
                list($year, $week) = explode('W', $customerAddons[0]['week']);
                $dueDate->set($year, Zend_Date::YEAR_8601);
                $dueDate->set($week, Zend_Date::WEEK);

                foreach ($subscriptions as $subscription) {
                    $location = $subscription->getLocation();

                    if ($location === null) {
                        continue;
                    }

                    $dueDate->set($location->dayOfWeek, Zend_Date::WEEKDAY_8601);
                    break;
                }

                $invoice = $customerAddons->getService()
                    ->createInvoice($dueDate);

                if ($_POST['paid'] || $invoice['total'] <= 0) {
                    $invoice->getService()->issueTransaction(
                        null,
                        $_POST['payment']
                    );
                }

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Purchased product added!')
                    )
                );

                $this->_redirect('/admin/customers/addons', array('exit' => true));

            } catch (HH_Object_Exception_Validation $exception) {
                $this->view->errors = $exception->getErrorMessages();
            }
        }

        $this->render('addon.edit');
    }

    protected function _addonDelete($id)
    {
        $addon = new HHF_Domain_Customer_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_redirect('/admin/customers/addons', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Purchased product deleted!')
            )
        );

        $addon->getService()->remove();

        $this->_redirect('/admin/customers/addons', array('exit' => true));
    }

    public function optionsAction()
    {

    }

    public function emailAction()
    {
        $customerId = (int) $this->_request->getParam('id');

        $this->view->customer = new HHF_Domain_Customer(
            $this->farm,
            $customerId
        );

        if ($this->view->customer->isEmpty()) {
            $this->_redirect('/admin/customers/customers', array('exit' => true));
        }

        $this->view->to = array();

        if (!empty($this->view->customer['email'])) {
            $this->view->to[$this->view->customer['email']] = $this->view->customer['email'];
        }

        if (!empty($this->view->customer['secondaryEmail'])) {
            $this->view->to[$this->view->customer['secondaryEmail']] = $this->view->customer['secondaryEmail'];
        }

        $this->view->from = $this->farm->getFarmerEmails();

        $this->view->getFormValue()->setDefaulVars(
            array(
                'from' => $this->farmer->email
            )
        );

        if (!empty($_POST)) {

            $filter = new Zend_Filter_Input(
                array(
                    'html' => new HH_Filter_Html(
                        array(
                            'AutoFormat.AutoParagraph' => true,
                            'AutoFormat.Linkify' => true,
                            'AutoFormat.RemoveEmpty' => true,
                            'HTML.SafeEmbed' => true,
                            'HTML.SafeObject' => true,
                            'Output.FlashCompat' => true,
                            'URI.Base' => $this->farm->getBaseUri(),
                            'URI.MakeAbsolute' => true,
                            'CSS.Trusted' => true,
                            'HTML.Trusted' => true,
                            'Filter.ExtractStyleBlocks.TidyImpl' => false,
                            'MyIframe' => true
                        )
                    )
                ),
                array(
                    'from' => array(
                        new Zend_Validate_StringLength(0, 255),
                        new Zend_Validate_EmailAddress(),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('The email address is not valid'),
                            $this->translate->_('The email address is not valid')
                        )
                    ),
                    'to' => array(
                        new Zend_Validate_StringLength(0, 255),
                        new Zend_Validate_EmailAddress(),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('The email address is not valid'),
                            $this->translate->_('The email address is not valid')
                        )
                    ),
                    'subject' => array(
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false
                    ),
                    'html' => array(
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false
                    )
                ),
                $_POST,
                array(
                    Zend_Filter_Input::MISSING_MESSAGE   =>
                        $this->translate->_("'%field%' is required"),
                    Zend_Filter_Input::NOT_EMPTY_MESSAGE =>
                        $this->translate->_("'%field%' is required"),
                )
            );

            if ($filter->isValid()) {

                $layout = new Zend_Layout();
                $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                $layout->setLayout('email');
                $layout->getView()->farm = $this->farm;

                $layout->content = $filter->getUnescaped('html');

                $email = new HH_Job_Email();
                $email->add(
                    array(
                        $filter->getUnescaped('from'),
                        $this->farm->name
                    ),
                    $filter->getUnescaped('to'),
                    $filter->getUnescaped('subject'),
                    null,
                    $layout->render(),
                    $filter->getUnescaped('from'),
                    null,
                    null,
                    'farmnik@harvesthand.com',
                    'farmnik@harvesthand.com'
                );

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Email queued for delivery!')
                    )
                );

                $this->_redirect('/admin/customers/customers', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    public function transactionsAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'columns' => 'YEAR(transactionDate) as year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $this->view->years = array();

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_transactionsData($year);
        }

        $this->view->year = $year;

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv') {
            $limit = null;
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );
        }

        $this->view->transactions = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    t.*,
                    c.id as customerId,
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.address as customerAddress,
                    c.address2 as customerAddress2,
                    c.city as customerCity,
                    c.telephone as customerTelephone,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail
                FROM
                    __DATABASE__ as t
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    t.customerId = c.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    'YEAR(t.transactionDate) = ' . (int) $year
                ),
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'transactionDate',
                        'dir' => 'desc'
                    ),
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->transactions->getFoundRows();

        if ($format == 'csv') {
            $this->render('transactions.csv');
            return;
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _transactionsData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('id', 'customerLastName', 'transactionDate', 'total', 'remainingToApply', 'appliedToInvoices');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $where = array(
                'YEAR(t.transactionDate) = ' . (int) $year,
                '(firstName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ' OR lastName LIKE '
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
                    . ')'
            );
        } else {
            $where = array(
                'YEAR(t.transactionDate) = ' . (int) $year
            );
        }

        $rows = HHF_Domain_Transaction::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    c.firstName as customerFirstName,
                    c.lastName as customerLastName,
                    c.id as customerId,
                    c.email as customerEmail,
                    c.secondaryEmail as customerSecondaryEmail,
                    t.id,
                    t.transactionDate,
                    t.total,
                    t.remainingToApply,
                    t.appliedToInvoices
                FROM
                    __DATABASE__ as t
                LEFT JOIN
                    __SCHEMA__.customers as c
                ON
                    t.customerId = c.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Transaction::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        $currency = Bootstrap::getZendCurrency();

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            if (is_numeric($data['total'])) {
                $data['total'] = $currency->toCurrency($data['total']);
            }
            if (is_numeric($data['remainingToApply'])) {
                $data['remainingToApply'] = $currency->toCurrency($data['remainingToApply']);
            }
            $data['transactionDate'] = $data['transactionDate']->toString('yyyy-MM-dd');

            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function transactionAction()
    {
        $id = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        switch ($action) {
            case self::ACTION_DELETE :
                return $this->_transactionDelete($id);
                break;
            case 'applyexisting' :
                return $this->_transactionApplyExisting($id);
                break;
            case 'applynew' :
                return $this->_transactionApplyNew($id);
                break;
            case 'unapply' :
                return $this->_transactionUnApply($id);
                break;
            default :
                return $this->_transactionView($id);
                break;
        }
    }

    protected function _transactionApplyExisting($id)
    {
        $transaction = new HHF_Domain_Transaction($this->farm, $id);

        if ($transaction->isEmpty()) {
            $this->_redirect('/admin/customers/transactions', array('exit' => true));
        }

        $transaction->getService()
            ->applyToInvoices($_POST['invoices']);

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Transaction applied!')
            )
        );

        $this->_redirect('/admin/customers/transaction?id=' . (int) $transaction['id'], array('exit' => true));
    }

    protected function _transactionDelete($id)
    {
        $transaction = new HHF_Domain_Transaction($this->farm, $id);

        if ($transaction->isEmpty()) {
            $this->_redirect('/admin/customers/transactions', array('exit' => true));
        }

        $transaction->getService()->remove();

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            $this->translate->_('Transaction deleted!')
        );

        $this->_redirect('/admin/customers/transactions', array('exit' => true));
    }

    protected function _transactionApplyNew($id)
    {
        $transaction = new HHF_Domain_Transaction($this->farm, $id);

        if ($transaction->isEmpty()) {
            $this->_redirect('/admin/customers/transactions', array('exit' => true));
        }

        $invoice = new HHF_Domain_Customer_Invoice($this->farm);

        $invoiceData = array(
            'customerId' => $transaction['customerId'],
            'type' => HHF_Domain_Customer_Invoice::TYPE_MISC,
            'pending' => 0,
            'dueDate' => date('Y-m-d'),
            'paid' => 0,
            'subTotal' => 0.00,
            'tax' => null,
            'total' => 0.00,
            'outstandingAmount' => 0.00,
            'message' => $this->translate->_('Misc Payment'),
            'lines' => array(
                array(
                    'type' => HHF_Domain_Customer_Invoice_Line::TYPE_MISC,
                    'referenceId' => null,
                    'description' => $this->translate->_('Misc Payment'),
                    'unitPrice' => $transaction['remainingToApply'],
                    'quantity' => 1,
                    'taxable' => 0,
                )
            )
        );

        $invoice->getService()->save($invoiceData);

        $transaction->getService()
            ->applyToInvoices($invoice);

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Transaction applied!')
            )
        );

        $this->_redirect('/admin/customers/transaction?id=' . (int) $transaction['id'], array('exit' => true));
    }

    protected function _transactionUnApply($id)
    {
        $transaction = new HHF_Domain_Transaction($this->farm, $id);

        if ($transaction->isEmpty()) {
            $this->_redirect('/admin/customers/transactions', array('exit' => true));
        }

        $transaction->getService()
            ->removeFromInvoices();

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Transaction un-applied!')
            )
        );

        $this->_redirect('/admin/customers/transaction?id=' . (int) $transaction['id'], array('exit' => true));
    }

    protected function _transactionView($id)
    {
        $transaction = new HHF_Domain_Transaction($this->farm, $id);

        if ($transaction->isEmpty()) {
            $this->_redirect('/admin/customers/transactions', array('exit' => true));
        }

        $this->view->object = $transaction;
        $this->view->customer = $transaction->getCustomer();

        if ($transaction['remainingToApply'] > 0 && !empty($transaction['customerId'])) {
            $invoices = HHF_Domain_Customer_Invoice::fetch(
                $this->farm,
                array(
                    'where' => array(
                        'customerId' => $transaction['customerId'],
                        'paid' => 0
                    ),
                    'order' => array(
                        array(
                            'column' => 'dueDate',
                            'dir' => 'asc'
                        )
                    )
                )
            );

            if (count($invoices)) {
                $currency = Bootstrap::getZendCurrency();
                $this->view->invoices = array();
                $this->view->invoicesData = array();

                foreach ($invoices as $invoice) {
                    $this->view->invoicesData[] = $invoice->toArray();

                    $this->view->invoices[$invoice['id']] = sprintf(
                        $this->translate->_('Invoice #%d, due %s for %s (%s outstanding)'),
                        $invoice['id'],
                        $invoice['dueDate']->toString('yyyy-MM-dd'),
                        $currency->toCurrency($invoice['total']),
                        $currency->toCurrency($invoice['outstandingAmount'])
                    );
                }
            }
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        $this->render('transaction.view');
    }
//@formatter:on
    public function vacationsAction(){
        if($_GET['cid']){
            $customer = $this->view->customer = new HHF_Domain_Customer($this->farm, $_GET['cid']);
        }

        if($this->_request->getParam('e', false)){
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else{
            $this->view->embedded = false;
        }

        // Validate the date ranges of the vacations.
        if($_POST && ($valid = $this->_validateVacations($_POST))){
            $customer = $this->view->customer = new HHF_Domain_Customer($this->farm, $_POST['cid']);
            $vacationObj = new HHF_Domain_Customer_Vacation($this->farm);
            $vacationObj->deleteWhereCustomerId($this->farm, $customer->id);
            $vacationObj->getService()->saveMultiple($this->farm, $customer, $_POST);
        }

        // Get all of the customers shares, and create an options object.
        if(empty($shares) && $customer){
            $queryOptions = array(
                'fetch'    => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
                'customer' => $customer
            );

            $shares = HHF_Domain_Customer_Share::fetchShares($this->farm, $queryOptions);
        }

        // Handle an AJAX request for additional form elements.
        if($_GET['shareId']){
            $queryOptions = array(
                'shareId' => $_GET['shareId']
            );

            $share = HHF_Domain_Customer_Share::fetchSingle($this->farm, $queryOptions);

            $queryOptions = array(
                'shareId' => $share->getShare()->id
            );

            $options = HHF_Domain_Share_VacationOption::fetchWhere($this->farm, $queryOptions);

            if (count($options) > 0){
                echo $this->view->vacationHelper()->buildForm($share, $options, $_GET['nextId']);
            } else {
                echo "Sorry, you must first add vacation options for this share.";
            }
            die;
        } // End GET.

        // Build a data structure to hold a share, vacations from said share, and vacation options at each index.
        $vacationData = array();

        foreach($shares as $share){

            $shareVacations = HHF_Domain_Customer_Vacation::fetchWhere($this->farm, array(
                'customerId' => $customer->id,
                'shareId'    => $share->id
            ));

            $vacationOptions = HHF_Domain_Share_VacationOption::fetchWhere($this->farm, array(
                'shareId' => $share->getShare()->id
            ));

            array_push($vacationData, array(
                'share'      => $share,
                'vacations'  => $shareVacations,
                'options'    => $vacationOptions
            ));
        }

        $this->view->vacations = $vacationData;

        // If data was just saved, return to the page with the id.
        if($_POST){
            $this->redirect('/admin/customers/customer?id=' . $_POST['cid'] . '&valid='.$valid.'#ui-tabs-5');
        }
    }

    protected function _validateVacations($data){
        $vacationObj = new HHF_Domain_Customer_Vacation($this->farm);

        foreach($data as $shareId => $posted){
            foreach($posted as $vacation){
                if(!$vacationObj->setStartWeek($vacation['Beginning Week']) || !$vacationObj->setEndWeek($vacation['Ending Week']))
                {
                    return false;
                }
            }
        }

        return true;
    }

    public function notesAction(){
        if($_GET['cid']){
            $customer = $this->view->customer = new HHF_Domain_Customer($this->farm, $_GET['cid']);
        } elseif ($_POST['customerId']){
            $customer = $this->view->customer = new HHF_Domain_Customer($this->farm, $_POST['customerId']);
        } else {
            $this->redirect('/admin/customers');
        }

        if($this->_request->getParam('e', false)){
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else{
            $this->view->embedded = false;
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $queryOptions = array(
            'fetch'    => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
            'customer' => $customer,
            'filter'   => 'active'
        );

        $this->view->shares = HHF_Domain_Customer_Share::fetchShares($this->farm, $queryOptions);

        $this->view->notes = HHF_Domain_Customer_Share_Note::fetch(
            $this->farm,
            array(
                'columns' => '*',
                'where' => array(
                    'customerId' => $customer->id
                ),
                'order' => array(
                    array(
                        'column' => 'week',
                        'dir' => 'DESC'
                    )
                )
            )
        );

        if($_POST){
            if(!empty($_POST['delete'])){
                HHF_Domain_Customer_Share_Note::deleteWhereId($this->farm, $_POST['delete']);
            } else {
                $filter = HHF_Domain_Customer_Share_Note::getFilter();
                $filter->setData($_POST);

                if (!$filter->isValid()) {
                    $messenger->addMessage(
                        sprintf(
                            $this->translate->_(reset($filter->getMessages()))
                        )
                    );
                } else {

                    $existing = HHF_Domain_Customer_Share_Note::fetchOne(
                        $this->farm,
                        array(
                            'columns' => '*',
                            'where' => array(
                                'customerId' => (int) $_POST['customerId'],
                                'customerShareId' => (int) $_POST['customerShareId'],
                                'week' => $_POST['week']
                            )
                        )
                    );

                    if($existing->isEmpty()){
                        $note = new HHF_Domain_Customer_Share_Note($_POST);
                        $note->setFarm($this->farm);
                        $note->insert($_POST);

                        $messenger->addMessage(
                            sprintf(
                                $this->translate->_('A note for '.$_POST['week']. ' was added!')
                            )
                        );
                    } else {
                        $messenger->addMessage(
                            sprintf(
                                $this->translate->_('A note for this customer share week already exists!')
                            )
                        );
                    }
                }
            }
            $this->redirect('/admin/customers/customer?id=' . $customer->id . '#ui-tabs-6');
        }
    }
}
