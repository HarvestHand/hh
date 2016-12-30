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
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */

/**
 * IndexController
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @category  Core
 * @package
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Shares_PublicController extends HHF_Controller_Action
{
    /**
     *
     * @var Zend_Controller_Action_Helper_ContextSwitch
     */
    protected $_contextSwitch;

    public function init()
    {
        parent::init();

        $this->_contextSwitch = $this->_helper->getHelper('contextSwitch');
        $this->_contextSwitch->setContext(
            'rss',
            array(
                'suffix' => 'rss',
                'headers' => array(
                    'Content-Type' => 'application/rss+xml'
                )
            )
        );
        $this->_contextSwitch->setContext(
            'atom',
            array(
                'suffix' => 'atom',
                'headers' => array(
                    'Content-Type' => 'application/atom+xml'
                )
            )
        );
        $this->_contextSwitch->setContext(
            'ics',
            array(
                 'suffix' => 'ics',
                 'headers' => array(
                     'Content-Type' => 'text/calendar'
                 )
            )
        );
        $this->_contextSwitch->addActionContext(
            'index',
            array('rss', 'atom', 'ics')
        );
        $this->_contextSwitch->initContext();

        $this->theme->bootstrap($this);
    }

    public function postDispatch()
    {
        $action = $this->_request->getActionName();

        if ($this->_helper->layout->isEnabled() && $action != 'register') {

            $view = clone $this->view;
            $moduleDir = Zend_Controller_Front::getInstance()
                ->getControllerDirectory('website');
            $viewsDir = dirname($moduleDir) . '/views';
            $view->addBasePath($viewsDir);

            $this->view->placeholder('Zend_Layout')
                ->sideBar = $view->render('public/sideBar.phtml');
        }

        parent::postDispatch();
    }

    public function indexAction()
    {
        switch ($this->_contextSwitch->getCurrentContext()) {
            case 'rss':
            case 'atom':
                $this->view->deliveries = HHF_Domain_Delivery::fetchDeliveries(
                    $this->farm,
                    array(
                        'fetch' => HHF_Domain_Delivery::FETCH_ENABLED,
                        'order' => HHF_Domain_Delivery::ORDER_WEEK,
                        'limit' => array(0,30)
                    )
                );
                $this->view->format = $this->_contextSwitch->getCurrentContext();

                $this->_helper->layout->disableLayout();
                return $this->render('all-shares.feed');
                break;
            case 'ics':
                $token = $this->_request->getParam('t');
                $customerShares = array();
                $customer = null;

                if (!empty($token)) {
                    $farmer = HH_Domain_Farmer::fetchUserByToken($token, $this->farm);

                    if (!$farmer->isEmpty()) {
                        $customer = $farmer->getCustomer();

                        if (!empty($customer)) {
                            $customerShares = HHF_Domain_Customer_Share::fetchShares(
                                $this->farm,
                                array(
                                    'fetch' => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
                                    'customer' => $customer
                                )
                            );
                        }
                    }
                }

                $this->view->deliveries = HHF_Domain_Delivery::fetchDeliveries(
                    $this->farm,
                    array(
                         'fetch' => HHF_Domain_Delivery::FETCH_ENABLED,
                         //'week' => date('Y') . 'W' . date('W'),
                         'order' => HHF_Domain_Delivery::ORDER_WEEK,
                         'shares' => $customerShares,
                         'limit' => array(0, 52)
                    )
                );
                $this->view->customerShares = $customerShares;
                $this->view->customer = $customer;
                $this->view->token = $token;

                $this->_helper->layout->disableLayout();
                $this->_response->setHeader('Content-Disposition', 'attachment; filename="shares.ics"');
                return $this->render('all-shares.ics');
                break;
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_deliveriesData();
        }

        if ($this->view->isAuthenticated()
            && ($this->farmer instanceof HH_Domain_Farmer)
            && $this->farmer->role == HH_Domain_Farmer::ROLE_MEMBER) {

            $this->_myShares();
        } else {
            $this->_allShares();
        }
    }

    public function shoppingAction()
    {
        $this->_helper->addHelper(HHF_Domain_Addon::getActionHelper());

        $this->_helper->HHFDomainAddon->shopping();
    }

    public function _deliveriesData()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('start', 0);
        $rows = (int) $this->_request->getParam('length', 50);
        $week = $this->_request->getParam('week');

        if (!empty($week)) {
            $this->view->deliveries = HHF_Domain_Delivery::fetch(
                $this->farm,
                array(
                    'where' => array(
                        'enabled' => 1,
                        'week' => $week
                    )
                )
            );


            $this->view->week = $week;

            $this->_response->appendBody(
                Zend_Json::encode($this->view->render('delivery-week.phtml'))
            );

        } else {

            $deliveries = HHF_Domain_Delivery::fetch(
                $this->farm,
                array(
                    'columns' => 'week',
                    'where' => array(
                        'enabled' => 1
                    ),
                    'groupBy' => array(
                        'week'
                    ),
                    'order' => array(
                        array(
                            'column' => 'week',
                            'dir' => 'desc'
                        )
                    ),
                    'limit' => array(
                        'offset' => $offset,
                        'rows' => $rows
                    ),
                )
            );

            $this->_response->appendBody(
                Zend_Json::encode($deliveries->toArray())
            );
        }
    }

    public function previousAction()
    {
        $this->_allShares();
    }

    protected function _allShares()
    {
        $this->view->deliveries = HHF_Domain_Delivery::fetch(
            $this->farm,
            array(
                'columns' => '*',
                'where' => array(
                    'enabled' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'week',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->year = (int) $this->_request->getParam('year');
        $this->view->week = $this->_request->getParam('week');

        if (strpos($this->view->week, 'W')) {
            list($this->view->year, $this->view->week) = explode('W', $this->view->week, 2);

            $this->view->year = (int) $this->view->year;
            $this->view->week = (int) $this->view->week;
        } else {
            $this->view->week = (int) $this->view->week;
        }

        if (empty($this->view->week) || empty($this->view->year)) {
            if (count($this->view->deliveries)) {
                list($year, $week) = explode(
                    'W',
                    $this->view->deliveries[count($this->view->deliveries) - 1]['week']
                );

                $this->view->week = $week;
                $this->view->year = $year;
            }
        }

        $this->render('all-shares');
    }

    protected function _myShares()
    {
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_MEMBER);

        if (!empty($this->farm->timezone)) {
            $now = new DateTime('now', new DateTimeZone($this->farm->timezone));
        } else {
            $now = new DateTime('now');
        }

        $this->view->week = $now->format('o\WW');

        $addonsEnabled = $this->farm->getPreferences()->get(
            'addonsEnabled',
            'shares',
            true
        );

        $this->view->subscriptions = HHF_Domain_Customer_Share::fetchShares(
            $this->farm,
            array(
                'fetch' => HHF_Domain_Customer_Share::FETCH_CUSTOMER,
                'customer' => $this->farmer->getCustomer(),
                'filter' => 'active'
            )
        );

        if (!empty($this->view->subscriptions)) {

            $sharesForDelivery = array();
            foreach ($this->view->subscriptions as $customerShare) {
                $sharesForDelivery[] = $customerShare->getShare();
            }

            $this->view->deliveries = HHF_Domain_Delivery::fetchDeliveries(
                $this->farm,
                array(
                    'fetch' => HHF_Domain_Delivery::FETCH_ENABLED,
                    'week' => $this->view->week,
                    'shares' => $sharesForDelivery
                )
            );

            if (empty($this->view->deliveries)) {

                for ($week = 2; $week <= 4; ++$week) {

                    // try next week
                    $now->add(new DateInterval('P1W'));

                    $this->view->deliveries = HHF_Domain_Delivery::fetchDeliveries(
                        $this->farm,
                        array(
                             'fetch' => HHF_Domain_Delivery::FETCH_ENABLED,
                             'week' => $now->format('o\WW'),
                             'shares' => $sharesForDelivery
                        )
                    );

                    if (!empty($this->view->deliveries)) {
                        $this->view->week = $now->format('o\WW');
                        break;
                    }
                }
            }

            if (!empty($this->view->deliveries) && $addonsEnabled) {

                $locations = array();

                foreach ($this->view->deliveries as $delivery) {
                    foreach ($this->view->subscriptions as $customerShare) {
                        if ($customerShare['shareId'] != $delivery['shareId']) {
                            continue;
                        }

                        $locations[] = $customerShare->getLocation();
                    }
                }

                $this->view->addons = HHF_Domain_Addon::fetchAddons(
                    $this->farm,
                    array(
                        'fetch' => HHF_Domain_Addon::FETCH_PURCHASEABLE,
                        'locations' => $locations
                    )
                );

                $this->view->addonWeek = HHF_Domain_Customer_Addon::calculateAddonWeek2(
                    $this->view->subscriptions,
                    $this->farm
                );

//                if ($this->farm->id == 1 && $this->view->addonWeek !== null && $this->view->addonWeek->toString('YYYYWww') == '2014W16') {
//                    $this->view->addonWeek->subWeek(3);
//                 }

                if ($this->view->addonWeek === null) {
                    $this->view->addons = $addonsEnabled = false;
                }
            }
        }

        if (!empty($_POST) && !empty($this->view->deliveries) && $addonsEnabled) {

            $this->view->errors = array();

            $toAdd = array();
            $addons = array();

            foreach ($_POST['addons'] as $addonId => $quantity) {
                if (!is_numeric($quantity['quantity']) || $quantity['quantity'] < 1) {
                    continue;
                }

                $data = array(
                    'customerId' => $this->farmer->getCustomer()->id,
                    'addonId' => $addonId,
                    'quantity' => $quantity['quantity'],
                    'week' => $this->view->addonWeek->toString('YYYYWww'),
                    'payment' => $_POST['payment']
                );

                foreach ($this->view->addons as $addon) {

                    if ($addon->id == $data['addonId']) {
                        $toAdd[] = $data;
                        $addons[] = $addon;
                        break;
                    }
                }
            }

            if (!empty($toAdd)) {

                $customerAddons = new HHF_Domain_Customer_Addon_Collection(
                    $this->farm
                );

                try {

                    $customerAddons->setRelatedAddons($addons);

                    $customerAddons->getService()->save(
                        $toAdd
                    );

                    $this->view->invoice = $customerAddons->getService()
                        ->createInvoice($this->view->addonWeek);

                    $this->view->invoice->getService()->emailFarm();
                    $this->view->invoice->getService()->emailCustomer();

                    $this->view->purchases = $customerAddons;

                    $this->render('my-shares-payment');
                    return;

                } catch (HH_Object_Exception_Validation $exception) {
                    $this->view->errors = $exception->getErrorMessages();
                }
            }
        }

        $this->render('my-shares');
    }

    public function registerAction()
    {
        if ($this->_request->isHead()) {
            $this->setNoRender();
            $this->_helper->layout->disableLayout();
            return;
        }

        if ($this->_request->isXmlHttpRequest()) {

            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader(
                'Content-Type',
                'application/json',
                true
            );

            switch ($action = $this->_request->getParam('a', 'userName')) {
                case 'userName' :
                    $validate = new HH_Validate_UserNameUnique(
                        HH_Domain_Farmer::ROLE_MEMBER,
                        null,
                        $this->farm
                    );
                    $this->_response->appendBody(
                        Zend_Json::encode(
                            $validate->isValid($_GET['farmer']['userName'])
                        )
                    );
                    break;

                case 'unlocodes' :
                    $country = substr($_GET['country'], 0, 2);
                    $subdivision = substr($_GET['subdivision'], 0, 3);
                    $term = iconv('UTF8', 'ASCII//TRANSLIT', $_GET['unlocode']);
                    $result = array();

                    if (!empty($country)) {
                        $locations = HH_Tools_Countries::getUnlocodes(
                            $country,
                            $subdivision
                        );
                        foreach ($locations as $location) {
                            $location = iconv(
                                'UTF8',
                                'ASCII//TRANSLIT',
                                $location
                            );

                            if (($pos = stripos($location, $term)) !== false) {
                                if ($pos == 0) {
                                    array_unshift($result, $location);
                                } else {
                                    array_push($result, $location);
                                }
                            }
                        }
                    }

                    $this->_response->appendBody(
                        Zend_Json::encode(
                            $result
                        )
                    );

                    break;

                default :
                    break;
            }

            return;
        }


        $this->view->step = (int) $this->_request->getParam('step', 1);
        $this->view->key = $this->_request->getParam('key', false);

        if (empty($this->view->key)) {
            $this->view->key = mt_rand(1, 10000);
        }

        switch ($this->view->step) {
            case 1:
            default:
                $this->_registerStep1();
                break;
            case 2:
                $this->_registerStep2();
                break;
            case 3:
                $dataStore = new Zend_Session_Namespace('shares_register');
                $dataStore->unsetAll();
                $this->_registerStep1();
                break;
        }
    }

    protected function _registerStep1()
    {
        $this->view->step = 1;

        $this->view->shares = HHF_Domain_Share::fetchShares(
            $this->farm,
            array(
                'fetch' => HHF_Domain_Share::FETCH_PURCHASABLE,
                'farmer' => $this->farmer,
                'order' => HHF_Domain_Share::ORDER_NAME
            )
        );

        $this->view->locations = HHF_Domain_Location::fetchLocations(
            $this->farm,
            array(
                'fetch' => HHF_Domain_Location::FETCH_PURCHASABLE,
                'order' => HHF_Domain_Location::ORDER_DATETIME
            )
        );

        if (!empty($_POST) && empty($_GET['previous'])) {

            $this->view->errors = array();

            // validate
            $filter = HHF_Domain_Customer_Share::getFilter(
                HHF_Domain_Customer_Share::FILTER_NEW_PARTIAL,
                array(
                    'farm' => $this->farm
                )
            );

            $data = array();

            foreach ($_POST['share'] as $id => $share) {
                if (empty($share['quantity'])) {
                    continue;
                }

                // to be fixed
                foreach ($this->view->shares as $s) {
                    if ($s['id'] == $id) {
                        $share['year'] = $s['year'];
                        break;
                    }
                }

                $filter->setData($share);

                if (!$filter->isValid()) {
                    $this->view->errors['share'][$id] = $filter->getMessages();
                } else {
                    $data['share'][$id] = $filter->getUnescaped();
                }
            }

            if (empty($this->view->errors)) {

                // store
                $dataStore = new Zend_Session_Namespace('shares_register');

                if (!isset($dataStore->key) || $dataStore->key != $this->view->key) {
                    $dataStore->unsetAll();
                    $dataStore->key = $this->view->key;
                }

                $dataStore->step1 = $data;

                // move to next step
                $_POST = array();
                $this->view->step = 2;
                $this->_registerStep2();
            }
        } else {
            // store
            $dataStore = new Zend_Session_Namespace('shares_register');

            if (!isset($dataStore->key) || $dataStore->key != $this->view->key) {
                $dataStore->unsetAll();
                $dataStore->key = $this->view->key;
            }

            if (!empty($dataStore->step1)) {
                $this->view->getFormValue()->setDefaulVars($dataStore->step1);
            }

        }
    }

    public function _registerStep2()
    {
        $this->view->step = 2;
        $refresh = (!empty($_GET['refresh'])) ? true : false;

        $this->view->membershipAgreement = $this->farm->
            getPreferences()->get('plansDetails', 'shares', false);

        $this->view->plans = array(
            HHF_Order_Share::PAYMENT_PLAN_WEEKLY => $this->farm->
                getPreferences()->get('plansWeekly', 'shares', false),
            HHF_Order_Share::PAYMENT_PLAN_MONTHLY => $this->farm->
                getPreferences()->get('plansMonthly', 'shares', false),
            HHF_Order_Share::PAYMENT_PLAN_FIXED => $this->farm->
                getPreferences()->get('plansFixed', 'shares', false)
        );

        $preferences = new HHF_Preferences(
            $this->farm,
            HHF_Domain_Preference::TYPE_FARM
        );

        // store
        $dataStore = new Zend_Session_Namespace('shares_register');

        if (!isset($dataStore->key) || $dataStore->key != $this->view->key) {
            $dataStore->unsetAll();
            $_POST = array();
            $this->view->step = 1;
            return $this->_registerStep1();
        }

        $plan = HHF_Order_Share::PAYMENT_PLAN_NONE;

        if (!empty($_POST['payment']['plan'])
            && in_array($_POST['payment']['plan'], HHF_Order_Share::$paymentPlans)) {

            $plan = $_POST['payment']['plan'];
        }

        $this->view->order = new HHF_Order_Share(
            $this->farm,
            array(),
            HHF_Domain_Transaction::TYPE_CASH,
            $plan
        );

        if ($this->farmer instanceof HH_Domain_Farmer) {
            $customer = $this->farmer->getCustomer();

            if ($customer instanceof HHF_Domain_Customer) {
                $this->view->order->setCustomer($customer);
            }
        }

        switch ($plan) {
            case HHF_Order_Share::PAYMENT_PLAN_MONTHLY :
                $downPayment = $this->farm->getPreferences()->
                    get('plansMonthlyUpfront', 'shares', false);
                break;
            case HHF_Order_Share::PAYMENT_PLAN_WEEKLY :
                $downPayment = $this->farm->getPreferences()->
                    get('plansWeeklyUpfront', 'shares', false);
                break;
            default:
                $downPayment = false;
                break;
        }

        $year = null;

        if (!empty($dataStore->step1['share'])) {

            foreach ($dataStore->step1['share'] as $id => $share) {

                if ($share['year'] > $year) {
                    $year = $share['year'];
                }

                if (empty($share['paymentPlan']) || $share['paymentPlan'] != $plan) {
                    $share['paymentPlan'] = $plan;
                    $dataStore->step1['share'][$id]['paymentPlan'] = $plan;
                }

                $shareItem = new HHF_Order_Item_Share(
                    $this->farm,
                    $share['shareId'],
                    $share['shareDurationId'],
                    $share['shareSizeId'],
                    $share['locationId'],
                    $share['quantity'],
                    $share['year']
                );

                if (!empty($downPayment)) {
                    $shareItem->setDownPayment(round($downPayment / 100, 2, PHP_ROUND_HALF_UP));
                }

                $this->view->order->addItem($shareItem);
            }
        }

        if (!empty($_POST) && !$refresh) {

            $_POST['user']['country'] = $this->farm->country;
            $this->view->errors = array();

            // validate
            $accountCreate = false;

            if (!empty($_POST['account']['create']) && in_array($_POST['account']['create'], array('new', 'existing'))) {
                $accountCreate = $_POST['account']['create'];
            } else {
                $this->view->errors['account'] = array(
                    'create' => array(
                        $this->translate->_('Do you want to create a new account, or use an existing?')
                    )
                );
            }

            if ($accountCreate == 'new') {

                $filterCustomer = HHF_Domain_Customer::getFilter(
                    HHF_Domain_Customer::FILTER_NEW
                );
                $filterFarmer = HH_Domain_Farmer::getFilter(
                    HH_Domain_Farmer::FILTER_NEW,
                    array(
                        'role' => HH_Domain_Farmer::ROLE_MEMBER,
                        'farm' => $this->farm
                    )
                );

                $_POST['user']['enabled'] = 1;

                $filterCustomer->setData($_POST['user']);

                if (!$filterCustomer->isValid()) {
                    $this->view->errors['user'] = $filterCustomer->getMessages();
                }

                $_POST['farmer']['firstName'] = $filterCustomer->getUnescaped('firstName');
                $_POST['farmer']['lastName'] = $filterCustomer->getUnescaped('lastName');
                $_POST['farmer']['email'] = $filterCustomer->getUnescaped('email');

                $filterFarmer->setData($_POST['farmer']);

                if (!$filterFarmer->isValid()) {
                    $this->view->errors['farmer'] = $filterFarmer->getMessages();
                }

            } else if ($accountCreate == 'existing') {
                $filterLogin = HH_Domain_Farmer::getFilter('login');

                $_POST['login']['farmId'] = $this->farm->id;
                $_POST['login']['role'] = HH_Domain_Farmer::ROLE_MEMBER;

                $filterLogin->setData($_POST['login']);

                if ($filterLogin->isValid()) {

                    $farmer = HH_Domain_Farmer::fetchSingleUserByCredentials(
                        $filterLogin->getUnescaped('userName'),
                        $filterLogin->getUnescaped('password'),
                        $filterLogin->getUnescaped('role'),
                        $filterLogin->getUnescaped('farmId')
                    );

                    if (!($farmer instanceof HH_Domain_Farmer)) {

                        $this->view->errors['login'] = array(
                            'userName' => array(
                                $this->translate->_('User name or password incorrect')
                            )
                        );
                    } else {
                        $customer = HHF_Domain_Customer::fetchCustomerByFarmer($this->farm, $farmer);

                        if (!($customer instanceof HHF_Domain_Customer)) {
                            $this->view->errors['login'] = array(
                                'userName' => array(
                                    $this->translate->_('User name or password incorrect')
                                )
                            );
                        }
                    }
                } else {
                    $this->view->errors['login'] = $filterLogin->getMessages();
                }
            }

            $filterPayment = new Zend_Filter_Input(
                array(
                    '*' => array(
                        new Zend_Filter_StringTrim()
                    )
                ),
                array(
                    'agreement' => array(
                        new Zend_Validate_InArray(array('1')),
                        Zend_Filter_Input::PRESENCE => (
                            (!empty($this->view->membershipAgreement))
                                ? Zend_Filter_Input::PRESENCE_REQUIRED
                                : Zend_Filter_Input::PRESENCE_OPTIONAL
                        ),
                        Zend_Filter_Input::ALLOW_EMPTY => (
                            (!empty($this->view->membershipAgreement))
                                ? false
                                : true
                        ),
                        Zend_Filter_Input::DEFAULT_VALUE => (
                            (!empty($this->view->membershipAgreement))
                                ? null
                                : 1
                        ),
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('To proceed, you must agree with the terms of our membership agreement')
                        )
                    ),
                    'plan' => array(
                        new Zend_Validate_InArray(
                            HHF_Order_Share::$paymentPlans
                        ),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false,
                        Zend_Filter_Input::DEFAULT_VALUE =>
                            HHF_Order_Share::PAYMENT_PLAN_NONE,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('To proceed, you must choose a valid payment plan')
                        )
                    ),
                    'method' => array(
                        new Zend_Validate_InArray(
                            HHF_Domain_Transaction::$payments
                        ),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('To proceed, you must specify a payment method')
                        )
                    )
                ),
                $_POST['payment'],
                array(
                    Zend_Filter_Input::MISSING_MESSAGE   =>
                        $this->translate->_("'%field%' is required"),
                    Zend_Filter_Input::NOT_EMPTY_MESSAGE =>
                        $this->translate->_("'%field%' is required"),
                )
            );

            if (!$filterPayment->isValid()) {
                $this->view->errors['payment'] = $filterPayment->getMessages();
            }

            if (empty($this->view->errors)) {

                $db = Bootstrap::getZendDb();
                $db->beginTransaction();

                try {

                    if ($accountCreate == 'new') {

                        $customerData = $filterCustomer->getUnescaped();

                        $farmer = new HH_Domain_Farmer();

                        $farmerData = $filterFarmer->getUnescaped();
                        $farmerData['farmId'] = $this->farm->id;
                        $farmerData['role'] = HH_Domain_Farmer::ROLE_MEMBER;

                        $farmer->getService()->save($farmerData);

                        $customerData['farmerId'] = $farmer->id;

                        $customer = new HHF_Domain_Customer($this->farm);
                        $customer->insert($customerData);
                    }

                    $this->view->order->setCustomer($customer);
                    $this->view->order->createCustomerShares(
                        $filterPayment->getUnescaped('method')
                    );

                    $db->commit();

                } catch (Exception $e) {
                    $db->rollBack();
                    throw $e;
                }

                try {
                    $this->view->order->getPaymentPlan()->createInvoices();
                } catch(Exception $exception) {
                    HH_Error::exceptionHandler($exception, E_USER_WARNING);
                }

                // issue email to farm
                if (!empty($this->farm->email)) {
                    $layout = new Zend_Layout();
                    $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                    $layout->setLayout('email');
                    $layout->getView()->farm = $this->farm;

                    $view = new Zend_View();
                    $view->setScriptPath($this->view->getScriptPaths());
                    $view->customer = $customer;
                    $view->order = $this->view->order;
                    $view->farm = $this->farm;
                    $view->paymentMethod = $filterPayment->getUnescaped('method');

                    $layout->content = $view->render('public/register-email-farm.phtml');

                    if (empty($customer->email)) {
                        $replyTo = array($this->farm->email, $this->farm->name);
                    } else {
                        $replyTo = array(
                            $customer->email,
                            $customer->firstName . ' ' . $customer->lastName
                        );
                    }

                    $email = new HH_Job_Email();
                    $email->add(
                        array($this->farm->email, $this->farm->name),
                        $this->farm->email,
                        $this->translate->_('New Share Purchased From HarvestHand'),
                        null,
                        $layout->render(),
                        $replyTo,
                        null,
                        null,
                        'farmnik@harvesthand.com',
                        'farmnik@harvesthand.com'
                    );
                }

                // issue email to customer
                if (!empty($customer->email)) {
                    $layout = new Zend_Layout();
                    $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                    $layout->setLayout('email');
                    $layout->getView()->farm = $this->farm;

                    $view = new Zend_View();
                    $view->setScriptPath($this->view->getScriptPaths());
                    $view->customer = $customer;
                    $view->order = $this->view->order;
                    $view->farm = $this->farm;
                    $view->paymentMethod = $filterPayment->getUnescaped('method');

                    if (!empty($this->farm->email)) {
                        $replyTo = array($this->farm->email, $this->farm->name);
                        $from = array($this->farm->email, $this->farm->name);
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

                    $layout->content = $view->render('public/register-email-customer.phtml');

                    $email = new HH_Job_Email();
                    $email->add(
                        $from,
                        array(
                            $customer->email,
                            $customer->firstName . ' ' . $customer->lastName
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
                }
                $dataStore->step2 = $_POST;

                // move to next step
                $_POST = array();
                $this->view->step = 3;
                $this->_registerStep3($filterPayment->getUnescaped('method'));
            }
        } else {
            // store
            $dataStore = new Zend_Session_Namespace('shares_register');

            if (!isset($dataStore->key) || $dataStore->key != $this->view->key) {
                $dataStore->unsetAll();
                $dataStore->key = $this->view->key;
            }

            if (!empty($dataStore->step2)) {
                $this->view->getFormValue()->setDefaulVars($dataStore->step2);
            } else {
                $this->view->getFormValue()->setDefaulVars(
                    array(
                        'user' => array(
                            'state' => $this->farm->state
                        )
                    )
                );
            }

            $_POST['user']['country'] = $this->farm->country;
        }
    }

    public function _registerStep3($paymentMethod)
    {
        $this->view->step = 3;
        $this->view->paymentMethod = $paymentMethod;

        $invoices = $this->view->order->getPaymentPlan()->getInvoices();

        if (!empty($invoices)) {
            $invoice = array_shift($invoices);
            $this->view->orderId = 'hhi:' . $invoice->id;
        } else {
            $this->view->orderId = 'hhi:';
        }

        $dataStore = new Zend_Session_Namespace('shares_register');
        $dataStore->unsetAll();


        if (!empty($_POST)) {
            $this->_redirect('/', array('exit' => true));
        }
    }

    public function purchaseHistoryAction()
    {
        if (!$this->view->isAuthenticated() || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER) {
            $this->_redirect('/', array('exit' => true));
        }

        if (!$this->farm->getPreferences()->get('addonsEnabled', 'shares', true)) {
            $this->_redirect('/shares/purchase-history-shares', array('exit' => true));
        }
    }

    public function purchaseHistorySharesAction()
    {
        if (!$this->view->isAuthenticated() || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER) {
            $this->_redirect('/', array('exit' => true));
        }

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_purchaseHistorySharesData();
        }

        $this->view->subscriptions = HHF_Domain_Customer_Share::fetch(
            $this->farm,
            array(
                'sql' => 'SELECT SQL_CALC_FOUND_ROWS
                    s.*,
                    sh.name as shareName,
                    sh.deliverySchedule,
                    sd.startWeek,
                    sd.iterations
                FROM
                    __DATABASE__ as s
                LEFT JOIN
                    __SCHEMA__.shares as sh
                ON
                    s.shareId = sh.id
                LEFT JOIN
                    __SCHEMA__.sharesDurations as sd
                ON
                    s.shareDurationId = sd.id',
                'countRows' => true,
                'columns' => array(
                    '*'
                ),
                'where' => array(
                    's.customerId' => $this->farmer->getCustomer()->id
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        foreach ($this->view->subscriptions as $subscription) {

            try {
                $subscription['startDate'] = HHF_Domain_Share_Duration::staticGetStartDate(
                    $this->farm,
                    $subscription->startWeek,
                    $subscription->year
                )->toString('yyyy-MM-dd');
            } catch (Exception $exception) {
                $subscription['startDate'] = '1979-01-01';
            }

            try {
                $subscription['endDate'] = HHF_Domain_Share_Duration::staticGetEndDate(
                    $this->farm,
                    $subscription->startWeek,
                    $subscription->year,
                    $subscription->deliverySchedule,
                    $subscription->iterations
                )->toString('yyyy-MM-dd');
            } catch (Exception $exception) {
                $subscription['endDate'] = '1979-01-01';
            }
        }

        $this->view->subscriptions->usort(function($a, $b) {
            $left = new DateTime($a['endDate']);
            $right = new DateTime($b['endDate']);

            if ($left < $right) {
                return 1;
            } else if ($left > $right) {
                return -1;
            }

            return 0;
        });

        $this->view->foundRows = $this->view->subscriptions->getFoundRows();
    }

    public function _purchaseHistorySharesData()
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
        $columns = array('year', 'shareName', 'quantity');
        $year = false;

	if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    if ($columns[(int) $_GET['iSortCol_' . $i]] == 'year') {
                        $year = $dir;
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
                    s.*,
                    sh.name as shareName,
                    sh.deliverySchedule,
                    sd.startWeek,
                    sd.iterations
                FROM
                    __DATABASE__ as s
                LEFT JOIN
                    __SCHEMA__.shares as sh
                ON
                    s.shareId = sh.id
                LEFT JOIN
                    __SCHEMA__.sharesDurations as sd
                ON
                    s.shareDurationId = sd.id',
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
                    's.customerId' => $this->farmer->getCustomer()->id
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

            try {
                $data['startDate'] = HHF_Domain_Share_Duration::staticGetStartDate(
                    $this->farm,
                    $data['startWeek'],
                    $data['year']
                )->toString('yyyy-MM-dd');
            } catch (Exception $exception) {
                $data['startDate'] = '1979-01-01';
            }

            try {
                $data['endDate'] = HHF_Domain_Share_Duration::staticGetEndDate(
                    $this->farm,
                    $data['startWeek'],
                    $data['year'],
                    $data['deliverySchedule'],
                    $data['iterations']
                )->toString('yyyy-MM-dd');
            } catch (Exception $exception) {
                $data['endDate'] = '1979-01-01';
            }

            $result['aaData'][] = $data;
        }

        if ($year) {

            if ($year == 'desc') {

                usort($result['aaData'], function($a, $b) {
                    $left = new DateTime($a['endDate']);
                    $right = new DateTime($b['endDate']);

                    if ($left < $right) {
                        return 1;
                    } else if ($left > $right) {
                        return -1;
                    }

                    return 0;
                });
            } else {
                usort($result['aaData'], function($a, $b) {
                    $left = new DateTime($a['endDate']);
                    $right = new DateTime($b['endDate']);

                    if ($left > $right) {
                        return 1;
                    } else if ($left < $right) {
                        return -1;
                    }

                    return 0;
                });
            }
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function purchaseHistoryAddonsAction()
    {
        if (!$this->view->isAuthenticated() || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER) {
            $this->_redirect('/', array('exit' => true));
        }

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_purchaseHistoryAddonsData();
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
                    'a.customerId' => $this->farmer->getCustomer()->id
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

    public function _purchaseHistoryAddonsData()
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
        $columns = array('addedDatetime', 'addonName', 'quantity');

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
                    'a.customerId' => $this->farmer->getCustomer()->id
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
            $data['addedDatetime'] = $data['addedDatetime']
                ->toString('yyyy-MM-dd');
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

	 public function purchaseHistoryInvoicesAction()
    {
        if (!$this->view->isAuthenticated() || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER) {
            $this->_redirect('/', array('exit' => true));
        }

		$customer = $this->farmer->getCustomer();
		$customerId = $customer->id;

        if ($this->_request->getParam('e', false)) {
            $this->_helper->layout->disableLayout();
            $this->view->embedded = true;
        } else {
            $this->view->embedded = false;
        }

        if ($this->_request->isXmlHttpRequest()
            && $this->_request->getParam('d', false)) {

            return $this->_purchaseHistoryInvoicesData($customerId);
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
		$this->view->foundRows = $this->view->invoices->getFoundRows();
    }

    public function _purchaseHistoryInvoicesData($customerId)
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

	public function purchaseHistoryInvoiceAction() {

		$id = (int) $this->_request->getParam('id',0);

		$invoice = new HHF_Domain_Customer_Invoice($this->farm, $id);

        if ($invoice->isEmpty()) {
            $this->_redirect('/shares/purchase-history', array('exit' => true));
        }

        $this->view->object = $invoice;
        $this->view->customer = $invoice->getCustomer();
        $this->view->transactions = $invoice->getTransactions();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

	}

    public function purchasedShareAction()
    {
        if (!$this->view->isAuthenticated() || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER) {
            $this->_redirect('/', array('exit' => true));
        }

        $id = (int) $this->_request->getParam('id');

        $current = $this->view->subscription = new HHF_Domain_Customer_Share(
            $this->farm,
            $id
        );

		$this->view->invoices = $this->view->subscription->getCustomerInvoices();

        if ($current->isEmpty() || $current->customerId != $this->farmer->getCustomer()->id) {
            $this->_redirect('/shares/purchase-history', array('exit' => true));
        }
    }

    public function purchasedAddonAction()
    {
        if (!$this->view->isAuthenticated() || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER) {
            $this->_redirect('/', array('exit' => true));
        }

        $id = (int) $this->_request->getParam('id');

        $current = $this->view->addon = new HHF_Domain_Customer_Addon(
            $this->farm,
            $id
        );

		$this->view->invoice = $this->view->addon->getCustomerInvoice();

        if ($current->isEmpty() || $current->customerId != $this->farmer->getCustomer()->id) {
            $this->_redirect('/shares/purchase-history', array('exit' => true));
        }

        $this->view->category = str_replace('-', ' ', $current->getAddon()->categoryId);
    }

    public function paidAction()
    {

    }
}
