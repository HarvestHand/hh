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
class AdminController extends HHF_Controller_Action
{
    public function  init()
    {
        parent::init();
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_FARMER);
        $this->view->headTitle($this->translate->_('Administration'));
    }


    public function indexAction()
    {
        $hhFarmId = Bootstrap::getZendConfig()->resources->hh->farm;
        $this->view->hhFarm = new HH_Domain_Farm($hhFarmId);

        $this->view->hhPosts = HHF_Domain_Post::fetch(
            $this->view->hhFarm,
            array(
                'columns' => array(
                    'id',
                    'token',
                    'title',
                    'publish',
                    'category',
                    'categoryToken',
                    'addedDatetime',
                    'publishedDatetime'
                ),
                'countRows' => true,
                'limit' => array('offset' => 0, 'rows' => 20),
                'order' => array(
                    array(
                        'column' => 'publishedDatetime',
                        'dir' => 'DESC'
                    )
                ),
                'where' => array(
                    'publish' => HHF_Domain_Post::PUBLISH_PUBLISHED
                )
            )
        );

        $this->view->stream = HHF_Domain_Log::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'limit' => array('offset' => 0, 'rows' => 100),
                'order' => array(
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'DESC'
                    )
                )
            )
        );
    }

    public function supportAction(){

        $params = $this->_request->getQuery();

        if(!empty($params['video'])){ // If the request is for a video.

            switch ($params['video']){
                case 'general_setup':
                    $title = 'General Setup Tutorial';
                    $video = 'JIjflFZNuyY';
                    break;
                case 'website':
                    $title = 'Website Setup Tutorial';
                    $video = '8WVzstIxGws';
                    break;
                case 'shares_locations':
                    $title = 'Shares and Locations Setup Tutorial';
                    $video = 'OjUmloGsWAU';
                    break;
                case 'packing_list':
                    $title = 'Delivery/Packing List Setup Tutorial';
                    $video = 'ONA7xd_ygng';
                    break;
            }
            $this->view->video = $video;

        } else { // Default to rendering the contact form.
            $title = 'Submit a Support Ticket';
            $view = new Zend_View();
            $form = new HH_View_SupportTicketForm();

            $this->view->form = $form->render($view);

            if($this->_request->isPost()){

                $formData = $this->_request->getPost();

                if($form->isValid($formData)){

                    $ticket = new HH_Domain_SupportTicket();
                    if($ticket->sendEmail($formData)){
                        $this->view->messages = "You will hear from us soon.";
                    } else{
                        $this->view->messages = "Something went wrong. Try again, or e-mail ray@harvesthand.com.";
                        $form->populate($formData);
                    }

                } else{
                    $this->view->messages = "Something you typed wasn't quite right.";
                    $form->populate($formData);
                }
            }
        }
        $this->view->title = $title;
    }

    public function optionsAction()
    {
        $this->view->isMasterFarm = $this->farm->isMasterFarm();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function optionsgeneralAction()
    {

        if ($this->_request->isXmlHttpRequest()) {

            // do server side AJAX validation
            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader(
                'Content-Type',
                'application/json',
                true
            );

            $result = false;

            switch ($this->_request->getParam('a')) {
                case 'add-distributor':
                    $network = new HH_Domain_Network();
                    $network->save(
                        array(
                            'type' => HH_Domain_Network::TYPE_DISTRIBUTION,
                            'farmId' => (int) $this->_request->getParam('parent-distributor'),
                            'relationId' => (int) $this->farm->id
                        )
                    );

                    break;

                case 'toggle-relation':
                    $network = HH_Domain_Network::fetchOne(
                        array(
                            'where' => array(
                                'farmId' => $this->farm->id,
                                'relationId' => (int) $this->_request->getParam('relationId')
                            )
                        )
                    );

                    if (!$network->isEmpty()) {

                        $network->save(
                            array(
                                'status' => $this->_request->getParam('status')
                            )
                        );

                        $result = true;
                    }

                    break;

                case 'distributors':
                    $distributors = HH_Domain_Farm::fetchDistributors($this->farm);

                    $result = array();

                    if (count($distributors)) {
                        foreach ($distributors as $distributor) {
                           $result[] = $distributor->toArray();
                        }
                    }

                    break;

                case 'child-network' :
                    $result = array(
                        'aaData' => array()
                    );

                    $networks = $this->farm->getChildNetworks();

                    foreach ($networks as $network) {
                        $result['aaData'][] = array(
                            $network->getFarm()->name,
                            $network->status,
                        );
                    }

                    break;

                case 'parent-network' :
                    $result = array(
                        'aaData' => array()
                    );

                    $networks = $this->farm->getParentNetworks();

                    $updated = $this->farm->getPreferences()->getStructure('networkProductsUpdated', 'shares', array());

                    foreach ($networks as $network) {
                        $result['aaData'][] = array(
                            $network->getRelation()->id,
                            $network->getRelation()->name,
                            (!empty($updated[$network->getRelation()->id]) ? $updated[$network->getRelation()->id] : null),
                            $network->status,
                        );
                    }

                    break;
            }

            return $this->_response->appendBody(
                Zend_Json::encode(
                    $result
                )
            );
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        $preferences = new HHF_Preferences(
            $this->farm,
            HHF_Domain_Preference::TYPE_FARM,
            null
        );

        if (!empty($_POST)) {

            if (!empty($_POST['facebook-pageId'])) {
                $store = new Zend_Session_Namespace('facebook');

                if (!empty($store->pages)) {
                    foreach ($store->pages as $page) {
                        if ($page['id'] == $_POST['facebook-pageId']) {
                            $_POST['facebook-pageAccessToken'] = $page['access_token'];

                            $client = new HH_Service_Facebook_Base(
                                Bootstrap::get('Zend_Config')
                                    ->resources->facebook->toArray()
                            );

                            $client->setConfig(
                                array('access_token' => $page['access_token'])
                            );

                            $page = $client->getPageObject(
                                $_POST['facebook-pageId']
                            );

                            $result = $page->getPage();

                            $_POST['facebook-pageLink'] = $result['link'];
                        }
                    }
                }
            }

            if (!empty($_POST['twitter-remove'])) {
                $preferences->delete('twitter-oauthToken');
                $preferences->delete('twitter-oauthTokenSecret');

                unset($_POST['twitter-remove']);
            }

            if (!empty($_POST['facebook-remove'])) {
                $preferences->delete('facebook-accessToken');

                unset($_POST['facebook-remove']);
            }

            foreach ($_POST as $key => $value) {
                $preferences->replace($key, $value);
            }

            $messenger = $this->_helper->getHelper('FlashMessenger');

            /* @var $messenger Zend_Controller_Action_Helper_FlashMessenger */
            $messenger->addMessage(
                $this->translate->_('General options updated!')
            );

            $this->_redirect('/admin/default/options', array('exit' => true));
        }

        $defaultVars = array();

        $preferences->setDefaultResource('paypal');

        foreach ($preferences as $preference) {
            $hash = $preference->resource . '-' . $preference->key;

            $defaultVars[$hash] = $preference->value;
        }

        $preferences->setDefaultResource('facebook');

        foreach ($preferences as $preference) {
            $hash = $preference->resource . '-' . $preference->key;

            $defaultVars[$hash] = $preference->value;
        }

        $this->view->childNetworks = $this->farm->getChildNetworks();
        if ($this->farm->isType(HH_Domain_Farm::TYPE_DISTRIBUTOR)) {
            $this->view->parentNetworks = $this->farm->getParentNetworks();
        }

        $this->view->getFormValue()->setDefaulVars($defaultVars);

        if (!empty($defaultVars['facebook-accessToken'])) {

            $client = new HH_Service_Facebook_Base(
                Bootstrap::get('Zend_Config')->resources->facebook->toArray()
            );

            $client->setConfig(
                array('access_token' => $defaultVars['facebook-accessToken'])
            );

            try {
                $rawFacebookPages = $client->getUserObject()->getAccounts();
            } catch (HH_Service_Facebook_Exception $exception) {
                $rawFacebookPages = array();
                unset($defaultVars['facebook-accessToken']);
                $this->view->getFormValue()->setDefaulVars($defaultVars);
            }

            if (!empty($rawFacebookPages['data'])) {

                $pages = array();

                foreach ($rawFacebookPages['data'] as $page) {
                    if (strcasecmp($page['category'], 'application') === 0) {
                        continue;
                    }

                    $pages[$page['id']] = $page['name'];
                }

                $this->view->facebookPages = $pages;

                $store = new Zend_Session_Namespace('facebook');
                $store->pages = $rawFacebookPages['data'];
            }
        }
    }

    public function optionspaymentsAction()
    {
        $preferences = new HHF_Preferences(
            $this->farm,
            HHF_Domain_Preference::TYPE_FARM,
            null,
            'paypal'
        );

        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                if ($key == 'a') {
                    continue;
                }

                $preferences->replace($key, $value);
            }

            if (!empty($_POST['a'])) {
                switch ($_POST['a']) {
                    case 'paypal-new' :

                        $paypal = new HH_Service_Paypal_Adaptive_Accounts();
                        $result = $paypal->createAccount(
                            $this->farm
                        );

                        if ($result === false) {
                            $this->view->errors = array(
                                'paypal-businessType' => array(
                                    $this->translate->_('Unable to contact PayPal... Contact HarvestHand for... a hand.')
                                )
                            );

                            return;
                        } else {
                            if ($result['responseEnvelope']['ack'] != 'Success') {
                                HH_Error::errorHandler(
                                    E_USER_WARNING,
                                    'PayPal Adaptive Accounts Create Account Failed',
                                    __FILE__,
                                    __LINE__,
                                    $result
                                );

                                $this->view->errors = array(
                                    'paypal-businessType' => array(
                                        $this->translate->_('Unable to contact PayPal... Contact HarvestHand for... a hand.')
                                    )
                                );

                                return;
                            }

                            $preferences->replace('paypal-accountId', $result['accountId']);
                            $preferences->replace('paypal-createAccountKey', $result['createAccountKey']);

                            if (stripos($result['execStatus'], 'COMPLETED') !== false) {
                                $this->_redirect(
                                    $result['redirectURL'],
                                    array(
                                        'exit' => true,
                                        'prependBase' => false
                                    )
                                );
                            }
                        }

                        break;
                }
            }

            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Payment options updated!')
                )
            );

            $this->_redirect('/admin/default/options', array('exit' => true));
        }

        $defaultVars = array();

        foreach ($preferences as $preference) {
            $hash = $preference->resource . '-' . $preference->key;

            $defaultVars[$hash] = $preference->value;
        }

        $this->view->getFormValue()->setDefaulVars($defaultVars);
    }

    public function optionsfarmAction()
    {
        if ($this->_request->isXmlHttpRequest()) {

            // do server side AJAX validation
            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader(
                'Content-Type',
                'application/json',
                true
            );

            $result = false;

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

            return $this->_response->appendBody(
                Zend_Json::encode(
                    $result
                )
            );
        }

        if ($this->farm->isEmpty()) {
            $this->_redirect(
                '/admin/default/options',
                array('exit' => true)
            );
        }

        $this->view->getFormValue()->setDefaulVars(
            array(
                'farm' => $this->farm->toArray()
            )
        );

        if (!empty($_POST)) {
            $this->view->errors = array();

            $filter = HH_Domain_Farm::getFilter(
                HH_Domain_Farmer::FILTER_NEW
            );

            $filter->setData($_POST['farm']);

            if ($filter->isValid()) {

                $data = $filter->getUnescaped();

                if ($data['type'] == HH_Domain_Farm::TYPE_DISTRIBUTOR) {
                    if ($this->farm->isType(HH_Domain_Farm::TYPE_DISTRIBUTOR)) {
                        $data['type'] = $this->farm->type;
                    } else {
                        $data['type'] = $this->farm->type . ',' . HH_Domain_Farm::TYPE_DISTRIBUTOR;
                    }
                } else {
                    if ($this->farm->isType(HH_Domain_Farm::TYPE_DISTRIBUTOR)) {
                        $data['type'] = $this->farm->getType();
                        unset($data['type'][array_search(HH_Domain_Farm::TYPE_DISTRIBUTOR, $data['type'])]);

                        $data['type'] = implode(',', $data['type']);
                    } else {
                        $data['type'] = $this->farm->type;
                    }
                }

                $this->farm->update($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('%s info updated!'),
                        $this->farm->name
                    )
                );

                $this->_redirect(
                    '/admin/default/options',
                    array('exit' => true)
                );

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    public function farmslistAction()
    {
        // Only master farm admin can see this.
        if (!$this->farm->isMasterFarm()) {
            $this->redirect(
                'admin/default/options',
                // exit=>true terminates current script
                array('exit' => true)
            );
        }

        $this->view->farms = HH_Domain_Farm::fetch(
            array(
                'columns' => '*',
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function farminfoAction()
    {
        // Only master farm admin can see this.
        if (!$this->farm->isMasterFarm()) {
            $this->redirect(
                'admin/default/options',
                // exit=>true terminates current script
                array('exit' => true)
            );
        }

        $farmId = (int) $this->_request->getParam('id', 0);
        $farm = HH_Domain_Farm::fetchOne(
            array(
                'where' => array(
                    'id' => $farmId
                )
            )
        );

        $this->view->farm = $farm;
        $this->view->primaryFarmer = $farm->getPrimaryFarmer();
        $this->view->farmers = $farm->getFarmers();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function becomeuserAction()
    {
        // Only master farm admin can do this.
        if (!$this->farm->isMasterFarm()) {
            $this->redirect(
                'admin/default/options',
                // exit=>true terminates current script
                array('exit' => true)
            );
        }

        // Username is given in query param.
        $userName = $this->_request->getParam('username');

        // Take first user with given username,
        $user = HH_Domain_Farmer::fetchByUserName($userName, 'FARMER')[0];

        // Effectively log in as that user.
        Zend_Auth::getInstance()->getStorage()->write($user);

        // Redirect to the appropriate farm as the new user.
        $redirectURL =  HH_Tools_Authentication::getRedirectUrl($user);
        $this->redirect($redirectURL);
    }

    public function optionsusersAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_usersData();
        }

        $this->view->users = HH_Domain_Farmer::fetch(
            array(
                'countRows' => true,
                'columns' => '*',
                'where' => array(
                    'farmId' => $this->farm->id,
                    'role' => HH_Domain_Farmer::ROLE_FARMER
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'userName',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->users->getFoundRows();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _usersData()
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
        $columns = array('firstName', 'lastName', 'userName', 'addedDatetime');

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

            $query = Bootstrap::getZendDb()->quote('%' . $search . '%');

            $where = array(
                '(firstName LIKE ' . $query . ' OR lastName LIKE ' . $query . ' OR userName LIKE ' . $query . ')',
                'farmId' => $this->farm->id,
                'role' => HH_Domain_Farmer::ROLE_FARMER
            );
        } else {
            $where = array(
                'farmId' => $this->farm->id,
                'role' => HH_Domain_Farmer::ROLE_FARMER
            );
        }

        $rows = HH_Domain_Farmer::fetch(
            array(
                'countRows' => true,
                'columns' => '*',
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

            $totalRows = HH_Domain_Farmer::fetch(
                array(
                    'countRows' => true,
                    'where' => array(
                        'farmId' => $this->farm->id,
                        'role' => HH_Domain_Farmer::ROLE_FARMER
                    ),
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
            $data['addedDatetime'] = $data['addedDatetime']
                ->toString('yyyy-MM-dd');
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function optionsuserAction()
    {
        $farmerId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($farmerId)) {
            $this->_farmerNew();
        } else if ($action != 'delete') {
            $this->_farmerEdit($farmerId);
        } else {
            $this->_farmerDelete($farmerId);
        }
    }

    protected function _farmerNew()
    {
        if (!$this->farm->isPrimaryFarmer($this->farmer)) {
            $this->_redirect(
                '/admin/default/options_users',
                array('exit' => true)
            );
        }

        $this->view->farmer = false;

        if (!empty($_POST)) {

            $this->view->errors = array();

            $filter = HH_Domain_Farmer::getFilter(
                HH_Domain_Farmer::FILTER_NEW,
                array(
                    'role' => HH_Domain_Farmer::ROLE_FARMER
                )
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $data = $filter->getUnescaped();

                $farmer = new HH_Domain_Farmer();

                $data['farmId'] = $this->farm->id;
                $data['role'] = HH_Domain_Farmer::ROLE_FARMER;

                $farmer->getService()->save($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('User "%s %s" added!'),
                        $farmer->firstName,
                        $farmer->lastName
                    )
                );

                $this->_redirect(
                    '/admin/default/options_users',
                    array('exit' => true)
                );

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    protected function _farmerEdit($id){
        $farmer = $this->view->farmer = new HH_Domain_Farmer($id);

        if($farmer->isEmpty()){
            $this->_redirect('/admin/default/options_users', array('exit' => true));
        }

        if(!$this->farm->isFarmer($farmer)){
            $this->_redirect('/admin/default/options_users', array('exit' => true));
        }

        if(!$this->farm->isPrimaryFarmer($this->farmer) && $this->farmer->id != $farmer->id){

            $this->_redirect('/admin/default/options_users', array('exit' => true));
        }

        $defaults = $farmer->toArray();
        unset($defaults['password']);

        $this->view->getFormValue()->setDefaulVars($defaults);

        if(!empty($_POST)){
            $this->view->errors = array();

            $filter = HH_Domain_Farmer::getFilter(HH_Domain_Farmer::FILTER_EDIT, array('role' => HH_Domain_Farmer::ROLE_FARMER, 'farmer' => $farmer));

            $filter->setData($_POST);

            if($filter->isValid()){

                $data = $filter->getUnescaped();

                $data['farmId'] = $this->farm->id;
                $data['role'] = HH_Domain_Farmer::ROLE_FARMER;

                if(array_key_exists('password', $data) && empty($data['password'])){
                    unset($data['password']);
                }

                $farmer->getService()->save($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(sprintf($this->translate->_('User "%s %s" updated!'), $farmer->firstName, $farmer->lastName));

                $this->_redirect('/admin/default/options_users', array('exit' => true));

            } else{
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    protected function _farmerDelete($id)
    {
        $farmer = new HH_Domain_Farmer($id);

        if ($farmer->isEmpty()) {
            $this->_redirect(
                '/admin/default/options_users',
                array('exit' => true)
            );
        }

        if (!$this->farm->isPrimaryFarmer($this->farmer)
            && !$this->farm->isPrimaryFarmer($farmer)) {

            $this->_redirect(
                '/admin/default/options_users',
                array('exit' => true)
            );
        }

        if (!$this->farm->isFarmer($farmer)) {

            $this->_redirect(
                '/admin/default/options_users',
                array('exit' => true)
            );
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('User "%s %s" deleted!'),
                $farmer->firstName,
                $farmer->lastName
            )
        );

        $farmer->delete();

        $this->_redirect(
            '/admin/default/options_users',
            array('exit' => true)
        );
    }

    public function usernameuniqueAction()
    {
        if ($this->_request->isXmlHttpRequest()) {

            // do server side AJAX validation
            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader(
                'Content-Type',
                'application/json',
                true
            );

            if (!empty($_GET['userName'])) {

                $farmer = null;

                $id = (int) $this->_request->getParam('id', 0);

                if ($id) {

                    $farmer = new HH_Domain_Farmer(
                        $id
                    );
                }

                $validate = new HH_Validate_UserNameUnique(
                    HH_Domain_Farmer::ROLE_FARMER,
                    $farmer
                );
                $this->_response->appendBody(
                    Zend_Json::encode(
                        $validate->isValid($_GET['userName'])
                    )
                );
            }
        }
    }

    public function paypalAction()
    {
        $action = strtolower($this->_request->getParam('a', 0));

        switch ($action) {
            case 'added' :
            default:
                $this->render('paypal_added');
                break;
            case 'agreement' :
                $this->_helper->layout->disableLayout();
                $paypal = new HH_Service_Paypal_Adaptive_Accounts();
                $result = $paypal->getUserAgreement($this->farm);

                if ($result === false) {
                    $this->view->errors = $this->translate->_('Unable to contact PayPal... Contact HarvestHand for... a hand.');
                } else {
                    if ($result['responseEnvelope']['ack'] != 'Success') {
                        HH_Error::errorHandler(
                            E_USER_WARNING,
                            'PayPal Adaptive Accounts Get User Agreement Failed',
                            __FILE__,
                            __LINE__,
                            $result
                        );

                        $this->view->errors = $this->translate->_('Unable to contact PayPal... Contact HarvestHand for... a hand.');
                    } else {
                        $this->view->agreement = $result['agreement'];
                    }
                }

                $this->render('paypal_agreement');
                break;
        }
    }

    public function twitterrequestAction()
    {
        $config = array(
            'callbackUrl' => 'http://' . Bootstrap::$domain
                . '/admin/default/twitter_grant/',
            'siteUrl' => 'https://api.twitter.com/oauth',
            'consumerKey' => Bootstrap::get('Zend_Config')
                ->resources->twitter->oauth_consumer_key,
            'consumerSecret' => Bootstrap::get('Zend_Config')
                ->resources->twitter->oauth_consumer_secret
        );

        $consumer = new Zend_Oauth_Consumer($config);

        try {

            // fetch a request token
            $token = $consumer->getRequestToken();

            $twitter = new Zend_Session_Namespace('twitter');

            // persist the token to storage
            $twitter->requestToken = serialize($token);

            $this->_redirect(
                $consumer->getRedirectUrl(),
                array('exit' => true)
            );

        } catch (Exception $exception) {
            HH_Error::exceptionHandler($exception, E_USER_WARNING);

            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                $this->translate->_('Ouch!  Twitter isn\'t cooperating.  We\'ll need to try that again.')
            );

            $this->_redirect('/admin/default/options_general', array('exit' => true));
        }
    }

    public function twittergrantAction()
    {
        $config = array(
            'callbackUrl' => 'http://' . Bootstrap::$domain
                . '/admin/default/twitter_grant/',
            'siteUrl' => 'https://api.twitter.com/oauth',
            'consumerKey' => Bootstrap::get('Zend_Config')
                ->resources->twitter->oauth_consumer_key,
            'consumerSecret' => Bootstrap::get('Zend_Config')
                ->resources->twitter->oauth_consumer_secret
        );

        $consumer = new Zend_Oauth_Consumer($config);

        $twitter = new Zend_Session_Namespace('twitter');

        if (!empty($_GET) && isset($twitter->requestToken)) {

            try {

                $accessToken = $consumer->getAccessToken(
                    $_GET,
                    unserialize($twitter->requestToken)
                );

                $preferences = new HHF_Preferences(
                    $this->farm,
                    HHF_Domain_Preference::TYPE_FARM
                );

                $preferences->replace(
                    'twitter-oauthToken',
                    $accessToken->getToken()
                );

                $preferences->replace(
                    'twitter-oauthTokenSecret',
                    $accessToken->getTokenSecret()
                );

                $client = new HH_Service_Twitter(
                    array(
                        'accessToken' => $accessToken
                    )
                );

                $result = $client->accountVerifyCredentials();

                $preferences->replace(
                    'twitter-screenName',
                    (string) $result->screen_name
                );

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    $this->translate->_('Got your Twitter permissions set up. ** If you change your Twitter password, you will need to set this up again. **')
                );

                $this->_redirect('/admin/default/options_general', array('exit' => true));

            } catch (Exception $exception) {
                HH_Error::exceptionHandler($exception, E_USER_WARNING);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    $this->translate->_('Ouch!  Twitter isn\'t cooperating.  We\'ll need to try that again.')
                );

                $this->_redirect('/admin/default/options_general', array('exit' => true));
            }

        } else {
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                $this->translate->_('Ouch!  Twitter isn\'t cooperating.  We\'ll need to try that again.')
            );

            $this->_redirect('/admin/default/options_general', array('exit' => true));
        }
    }

    public function facebookgrantAction()
    {
        $error = (bool) $this->_request->getParam('error', false);

        if (!$error) {
            $code = $this->_request->getParam('code');

            if (empty($code)) {
                throw new Exception('Where\'s the code?');
            }

            $client = new HH_Service_Facebook_Base(
                Bootstrap::get('Zend_Config')->resources->facebook->toArray()
            );

            $tokenArray = $client->getAccessToken($code, 'http://' . $this->farm->subdomain . '.' . Bootstrap::$rootDomain . '/admin/default/facebook_grant/'
            );

            $preferences = new HHF_Preferences(
                $this->farm,
                HHF_Domain_Preference::TYPE_FARM
            );

            $preferences->replace(
                'facebook-accessToken',
                $tokenArray['access_token']
            );

            $userArray = $client->getUserObject()->getUser();

            $preferences->replace(
                'facebook-userId',
                $userArray['id']
            );

            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                $this->translate->_('Got your Facebook permissions set up.  Now choose your Facebook Page and save your settings. ** If you change your Facebook password, you will need to set this up again. **')
            );

            $this->_redirect('/admin/default/options_general', array('exit' => true));
        } else {
            throw new Exception($_GET['error_description']);
        }
    }

    public function uploadAction()
    {
        $this->_helper->layout->disableLayout();

        $this->view->CKEditorFuncNum = (int) $this->_request->getParam(
            'CKEditorFuncNum',
            false
        );

        $type = strtoupper(
            $this->_request->getParam('type', HHF_Domain_File::TYPE_IMAGE)
        );

        $category = strtoupper(
            $this->_request->getParam(
                'category',
                HHF_Domain_File::CATEGORY_WEBSITE
            )
        );

        $this->view->filePath = null;
        $this->view->errors = array();

        if (!in_array($type, HHF_Domain_File::$types)) {
            $type = HHF_Domain_File::TYPE_IMAGE;
        }

        if (!in_array($category, HHF_Domain_File::$categories)) {
            $category = HHF_Domain_File::CATEGORY_WEBSITE;
        }

        if (!empty($_FILES['upload']['name'])) {

            $file = new HHF_Domain_File($this->farm);

            try {

                $file->upload(
                    'upload',
                    $type,
                    $category
                );

                $this->view->filePath = '/default/file/id/' . $file->id . '/s/' . HHF_Domain_File::IMAGE_SMALL;

            } catch (Exception $e) {

                $this->view->errors['upload'] = array(
                    $this->translate->_(
                        'Unable to receive uploaded file. ' . $e->getMessage()
                    )
                );

            }
        } else {
            $this->view->errors['upload'] = array(
                $this->translate->_(
                    'No file received'
                )
            );
        }
    }

    public function welcomeAction(){

    }
}
