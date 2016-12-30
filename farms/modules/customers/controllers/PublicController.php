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
class Customers_PublicController extends HHF_Controller_Action
{
    public function init(){
        parent::init();

        $this->theme->bootstrap($this);
    }

    public function postDispatch(){
        if($this->_helper->layout->isEnabled()){

            $view = clone $this->view;
            $moduleDir = Zend_Controller_Front::getInstance()->getControllerDirectory('website');
            $viewsDir = dirname($moduleDir) . '/views';
            $view->addBasePath($viewsDir);

            $this->view->placeholder('Zend_Layout')->sideBar = $view->render('public/sideBar.phtml');
        }

        parent::postDispatch();
    }

    public function indexAction(){
        $this->view->messages = $this->_helper->getHelper('FlashMessenger')->getMessages();

        if(empty($this->view->messages)){
            $this->_forward('customer', 'public', 'customers');
        }
    }

    public function usernameuniqueAction(){
        if($this->_request->isXmlHttpRequest()){

            // do server side AJAX validation
            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader('Content-Type', 'application/json', true);

            if(!empty($_GET['farmer']['userName'])){

                $farmer = null;

                $id = (int)$this->_request->getParam('id', 0);

                if($id){

                    $customer = new HHF_Domain_Customer($this->farm, $id);

                    if(!$customer->isEmpty()){
                        $farmer = $customer->getFarmer();
                    }
                } else{
                    if(!empty($this->farmer) && $this->farmer->role == HH_Domain_Farmer::ROLE_MEMBER){
                        $farmer = $this->farmer;
                    }
                }

                $validate = new HH_Validate_UserNameUnique(HH_Domain_Farmer::ROLE_MEMBER, $farmer, $this->farm);
                $this->_response->appendBody(Zend_Json::encode($validate->isValid($_GET['farmer']['userName'])));
            }
        }
    }

    public function localeAction(){
        if($this->_request->isXmlHttpRequest()){

            // do server side AJAX validation
            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader('Content-Type', 'application/json', true);

            if(!empty($_GET['country']) && !empty($_GET['subdivision']) && !empty($_GET['unlocode'])){
                $country = substr($_GET['country'], 0, 2);
                $subdivision = substr($_GET['subdivision'], 0, 3);
                $term = iconv('UTF8', 'ASCII//TRANSLIT', $_GET['unlocode']);
                $result = array();

                if(!empty($country)){
                    $locations = HH_Tools_Countries::getUnlocodes($country, $subdivision);
                    foreach($locations as $location){
                        $location = iconv('UTF8', 'ASCII//TRANSLIT', $location);

                        if(($pos = stripos($location, $term)) !== false){
                            if($pos == 0){
                                array_unshift($result, $location);
                            } else{
                                array_push($result, $location);
                            }
                        }
                    }
                }

                $this->_response->appendBody(Zend_Json::encode($result));
            }
        }
    }

    public function customerAction(){
        if(empty($this->farmer) || $this->farmer->role != HH_Domain_Farmer::ROLE_MEMBER){
            $this->_customerNew();
        } else{
            $this->_customerEdit();
        }
    }

    protected function _customerNew(){
        $this->view->getFormValue()->setDefaulVars(array('state'   => $this->farm->state,
                                                         'country' => $this->farm->country));

        if(!empty($_POST)){

            $this->view->errors = array();

            $filter = HHF_Domain_Customer::getFilter(HHF_Domain_Customer::FILTER_NEW_FRONTEND);

            $filter->setData($_POST);

            if(!$filter->isValid()){
                $this->view->errors = $filter->getMessages();

                return;
            }

            $filterFarmer = HH_Domain_Farmer::getFilter(HH_Domain_Farmer::FILTER_NEW,
                                                        array('role' => HH_Domain_Farmer::ROLE_MEMBER));

            if(!empty($_POST['farmer']['userName'])){
                $_POST['farmer']['firstName'] = $filter->getUnescaped('firstName');
                $_POST['farmer']['lastName'] = $filter->getUnescaped('lastName');
                $_POST['farmer']['email'] = $filter->getUnescaped('email');
                $_POST['farmer']['email2'] = $filter->getUnescaped('secondaryEmail');

                $filterFarmer->setData($_POST['farmer']);

                if(!$filterFarmer->isValid()){
                    $this->view->errors['farmer'] = $filterFarmer->getMessages();
                }
            } else{
                $filterFarmer->setData(null);

                if(!$filterFarmer->isValid()){
                    $this->view->errors['farmer'] = $filterFarmer->getMessages();
                }
            }

            if(empty($this->view->errors)){

                $data = $filter->getUnescaped();

                $farmer = new HH_Domain_Farmer();

                $farmerData = $filterFarmer->getUnescaped();
                $farmerData['farmId'] = $this->farm->id;
                $farmerData['role'] = HH_Domain_Farmer::ROLE_MEMBER;

                $farmer->getService()->save($farmerData);

                $data['farmerId'] = $farmer->id;
                $data['enabled'] = 1;

                $customer = new HHF_Domain_Customer($this->farm);
                $customer->insert($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(sprintf($this->translate->_('Account created!'), $customer->firstName,
                                               $customer->lastName));

                $this->_redirect('/customers/index', array('exit' => true));
            }
        }
    }

    protected function _customerEdit(){
        $currentCustomer = $this->view->customer = $this->farmer->getCustomer();

        if($currentCustomer->isEmpty()){
            $this->_redirect('/', array('exit' => true));
        } else{

            $defaultVars = $currentCustomer->toArray();

            if(!empty($currentCustomer->farmerId)){
                $defaultVars['farmer'] = $currentCustomer->getFarmer()->toArray();
                unset($defaultVars['farmer']['password']);
            }

            $this->view->getFormValue()->setDefaulVars($defaultVars);
        }

        if(!empty($_POST)){
            $filter = HHF_Domain_Customer::getFilter(HHF_Domain_Customer::FILTER_EDIT_FRONTEND);

            $filter->setData($_POST);

            if(!$filter->isValid()){
                $this->view->errors = $filter->getMessages();

                return;
            }

            if(empty($this->view->errors)){

                $data = $filter->getUnescaped();

                if(!empty($_POST['farmer']['password'])){
                    $farmer = new HH_Domain_Farmer($currentCustomer->farmerId);

                    $farmerData = array();
                    $farmerData['farmId'] = $this->farm->id;
                    $farmerData['role'] = HH_Domain_Farmer::ROLE_MEMBER;
                    $farmerData['password'] = $_POST['farmer']['password'];

                    $farmer->getService()->save($farmerData);

                }

                $currentCustomer->update($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(sprintf($this->translate->_('Account updated!')));

                $this->_redirect('/customers/index', array('exit' => true));

            }
        }
    }

    public function paymentAction(){
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_MEMBER);

        if($this->farm->getPreferences()->get('sidebarBalanceEnabled', 'website', true)){
            $where = array('customerId' => $this->farmer->getCustomer()->id, 'paid' => 0, 'appliedToBalance' => 1,
                           'pending'    => 0, 'outstandingAmount > 0');
        } else{
            $where = array('customerId' => $this->farmer->getCustomer()->id, 'paid' => 0, 'appliedToBalance' => 1,
                           'pending'    => 0, 'outstandingAmount > 0', 'dueDate > DATE_SUB(NOW(), INTERVAL 2 MONTH)');
        }

        $this->view->invoices = HHF_Domain_Customer_Invoice::fetch($this->farm, array('where'   => $where,
                                                                                      'orderBy' => array(array('column' => 'dueDate',
                                                                                                               'dir'    => 'ASC'))));

        if($this->_request->isXmlHttpRequest()){
            return $this->_invoice();
        }

        if(!empty($_POST)){
            $this->view->errors = array();
            $invoices = array();

            if(!empty($_POST['invoices'])){
                foreach($_POST['invoices'] as $invoiceId){
                    foreach($this->view->invoices as $invoice){
                        if($invoice['id'] == $invoiceId){
                            $invoices[] = $invoice;
                            break;
                        }
                    }
                }
            }

            $hasAnyPayment = (empty($_POST['amount']) || !is_numeric($_POST['amount'])) ? false : true;

            if(!$hasAnyPayment){
                $hasAnyPayment = !empty($invoices);
            }

            if(!$hasAnyPayment){
                $this->view->errors['amount'][] = $this->translate->_('A valid dollar amount is required');
            } else{

                $this->view->total = (float)$_POST['amount'];
                $this->view->note = (!empty($_POST['note']) ? $_POST['note'] : null);

                if((!empty($invoices) && !empty($this->view->total)) || (count($invoices) > 1)){

                    $keyValue = new HH_Domain_Keyvalue();
                    $group = array();

                    if(!empty($this->view->total)){
                        $group[] =
                            array('total'     => $this->view->total, 'customerId' => $this->farmer->getCustomer()->id,
                                  'invoiceId' => null, 'note' => $this->view->note);
                    }

                    foreach($invoices as $invoice){
                        $group[] = array('total'      => $invoice['outstandingAmount'],
                                         'customerId' => $this->farmer->getCustomer()->id,
                                         'invoiceId'  => $invoice['id'], 'note' => $this->view->note);

                        $this->view->total += floatval($invoice['outstandingAmount']);

                        if(!empty($this->view->note)){
                            // Handle saving note to transaction for invoice.

                        }

                    }

                    $keyValue->insert(array('ttl'  => 172800, 'type' => 'preauth:' . $this->farm['id'],
                                            'data' => serialize($group)));

                    $this->view->transactionId = 'hhg:' . $keyValue['id'];

                } elseif(!empty($invoices)){
                    $this->view->total = (float)$invoices[0]['outstandingAmount'];
                    $this->view->transactionId = 'hhi:' . $invoices[0]['id'];

                    $this->view->note = (!empty($_POST['note']) ? $_POST['note'] : null);

                    // If only one invoice chosen then save note in relation to it.

                } else{
                    $this->view->transactionId = 'hhc:' . $this->farmer->getCustomer()->id;

                    // If only an extra payment made with no invoice choses -- where save the note?
                }

                // issue email to farm
                if(!empty($this->farm->email)){
                    $layout = new Zend_Layout();
                    $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                    $layout->setLayout('email');
                    $layout->getView()->farm = $this->farm;

                    $view = new Zend_View();
                    $view->setScriptPath($this->view->getScriptPaths());
                    $view->customer = $this->farmer->getCustomer();
                    $view->farm = $this->farm;
                    $view->amount = $this->view->total;
                    $view->invoices = $invoices;
                    $view->note = (!empty($_POST['note']) ? $this->view->note : null);

                    $layout->content = $view->render('public/payment-email-farm.phtml');

                    if(empty($this->farmer->getCustomer()->email)){
                        $replyTo = array($this->farm->email, $this->farm->name);
                    } else{
                        $replyTo = array($this->farmer->getCustomer()->email,
                                         $this->farmer->getCustomer()->firstName . ' ' .
                                         $this->farmer->getCustomer()->lastName);
                    }

                    $email = new HH_Job_Email();
                    $email->add(array($this->farm->email, $this->farm->name), $this->farm->email,
                                $this->translate->_('New Payment From HarvestHand'), null, $layout->render(), $replyTo,
                                null, null, 'farmnik@harvesthand.com', 'farmnik@harvesthand.com');
                }

                $this->render('payment-payment');
            }
        }
    }

    public function _invoice(){
        $this->_helper->layout->disableLayout();

        if(!empty($_GET['invoiceId'])){
            foreach($this->view->invoices as $invoice){
                if($invoice['id'] == $_GET['invoiceId']){
                    $this->view->invoice = $invoice;
                    break;
                }
            }
        }

        $this->render('invoice');
    }

    public function passwordresetAction(){
        $this->view->sent = false;

        // The roles that are supported for password reset.
        $supportedRoles = array(HH_Domain_Farmer::ROLE_MEMBER, HH_Domain_Farmer::ROLE_FARMER);

        if(!empty($_POST)){
            $filter = new Zend_Filter_Input(array('*' => array(new Zend_Filter_StringTrim())),
                                            array('email' => array(new Zend_Validate_EmailAddress(),
                                                                   Zend_Filter_Input::PRESENCE      => Zend_Filter_Input::PRESENCE_OPTIONAL,
                                                                   Zend_Filter_Input::ALLOW_EMPTY   => true,
                                                                   Zend_Filter_Input::DEFAULT_VALUE => null,
                                                                   Zend_Filter_Input::MESSAGES      => array($this->translate->_('A valid email is required')))),
                                            $_POST,
                                            array(Zend_Filter_Input::MISSING_MESSAGE   => $this->translate->_("'%field%' is required"),
                                                  Zend_Filter_Input::NOT_EMPTY_MESSAGE => $this->translate->_("'%field%' is required"),));

            if($filter->isValid()){

                $email = Bootstrap::getZendDb()->quote($filter->getUnescaped('email'));

                $member = HH_Domain_Farmer::fetchOne(array('where' =>
                                                               'role IN (\'' . implode('\',\'', $supportedRoles) .
                                                               '\')' . ' AND farmId = ' . $this->farm->id . ' AND (' .
                                                               'email = ' . $email . ' OR email2 = ' . $email . ')'));

                if($member->isEmpty()){
                    $this->view->errors =
                        array('email' => array($this->translate->_('We can\'t find this email.  Maybe you don\'t have an account?')));
                } else{
                    $keyValue = new HH_Domain_Keyvalue();
                    $keyValue->insert(array('ttl'  => 10800, 'type' => 'passwordReset',
                                            'data' => serialize(array('role'     => $member->role,
                                                                      'farmId'   => $this->farm->id,
                                                                      'email'    => $filter->getUnescaped('email'),
                                                                      'farmerId' => $member->id))));

                    $layout = new Zend_Layout();
                    $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                    $layout->setLayout('email');
                    $layout->getView()->farm = $this->farm;

                    $view = new Zend_View();
                    $view->setScriptPath($this->view->getScriptPaths());
                    $view->farm = $this->farm;
                    $view->farmer = $member;
                    $view->key = $keyValue->id;

                    $layout->content = $view->render('public/email-reset-html.phtml');

                    if(!empty($this->farm->email)){
                        $replyTo = array($this->farm->email, $this->farm->name);
                        $from = array($this->farm->email, $this->farm->name);
                    } else{
                        $replyTo = array('farmnik@harvesthand.com', 'HarvestHand');
                        $from = array('farmnik@harvesthand.com', 'HarvestHand');
                    }

                    $email = new HH_Job_Email();
                    $email->add($from, $filter->getUnescaped('email'),
                                sprintf($this->translate->_('Lost Your Password For %s?'), $this->farm->name),
                                $view->render('public/email-reset-text.phtml'), $layout->render(), $replyTo, null, null,
                                'farmnik@harvesthand.com', 'farmnik@harvesthand.com');

                    $this->view->sent = true;
                }
            } else{
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    public function newpasswordAction(){
        $key = $this->_request->getParam('k', null);

        if(empty($_POST)){
            HH_Domain_Keyvalue::clean();
        }

        if(!empty($key)){
            $filter = new Zend_Filter_Alnum();

            $key = $filter->filter($key);

            $keyValue = new HH_Domain_Keyvalue($key);

            if($keyValue->isEmpty()){
                $this->view->badKey = true;
            } else{

                $keyData = unserialize($keyValue->data);

                $farmer = new HH_Domain_Farmer($keyData['farmerId']);

                if($farmer['role'] != $keyData['role']){
                    $this->view->badKey = true;
                    $keyValue->delete();
                }

                if($keyData['farmId'] != $this->farm->id){
                    $this->view->badKey = true;
                    $keyValue->delete();
                }
            }

        } else{
            $this->view->badKey = true;
        }

        if($_POST && empty($this->view->badKey)){

            if(strcasecmp(strtolower($keyData['email']), strtolower($_POST['email'])) !== 0){
                $this->view->errors =
                    array('email' => array($this->translate->_('This email isn\'t the one we are looking for.')));

                return;
            }

            $filter = HH_Domain_Farmer::getFilter(HH_Domain_Farmer::FILTER_PASSWORD);

            $filter->setData($_POST);

            if($filter->isValid()){
                $farmer->getService()->save($filter->getUnescaped());
                $keyValue->delete();

            } else{
                $this->view->errors = $filter->getMessages();
            }

        }
    }
    //@formatter:on
    public function vacationsAction(){

        if(empty($this->farmer)){
            $this->redirect('/', 'public', '/');
        }
        $customer = $this->farmer->getCustomer();

        // Validate the date ranges of the vacations.
        if($_POST && ($this->view->valid = $this->_validateVacations($_POST))){
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
        if($_GET){
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
                echo "Sorry, there aren't any vacation options for this share.";
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
}