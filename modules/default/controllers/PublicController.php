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
class PublicController extends HH_Controller_Action
{
    public function init()
    {
        parent::init();
    }

    public function __call($name, $args)
    {
        $this->_helper->layout->disableLayout();
        $this->setNoRender();

        $hhFarmId = Bootstrap::getZendConfig()->resources->hh->farm;
        $hhFarm = new HH_Domain_Farm($hhFarmId);

        $farmSubdomain = $hhFarm['subdomain'];

        $url = 'http://' . $farmSubdomain . '.' . Bootstrap::getZendConfig()->resources->domains->root . $_SERVER['REQUEST_URI'];

//        $url = 'http://taproot.hhint.com/shares/register';

//        ob_start();
//        phpinfo();
//        $r = ob_get_contents();
//        ob_end_clean();

        $opts = array(
            'http'=>array(
                'method' => $_SERVER['REQUEST_METHOD'],
                'header' => 'Accept-language: ' . (!empty($_SERVER['HTTP_ACCEPT_LANGUAGE']) ? $_SERVER['HTTP_ACCEPT_LANGUAGE'] : 'en') . "\r\n" .
                    'Accept: ' . (!empty($_SERVER['HTTP_ACCEPT']) ? $_SERVER['HTTP_ACCEPT'] : 'text/html') . "\r\n" .
                    'User-agent: ' . (!empty($_SERVER['HTTP_USER_AGENT']) ? $_SERVER['HTTP_USER_AGENT'] : 'HH') . "\r\n" .
                    'Cookie: ' . (!empty($_SERVER['HTTP_COOKIE']) ? $_SERVER['HTTP_COOKIE'] : '') . "\r\n",
                'content' => (!empty($_POST) ? http_build_query($_POST) : null)
            )
        );

        $r = file_get_contents($url, null, stream_context_create($opts));

        $this->_response->setBody($r);
    }
//
//    public function indexAction()
//    {
//        $this->view->title = $this->translate->_('HarvestHand');
//
//        if (!empty($_POST)) {
//
//            $email = new HH_Job_Email();
//            $email->add(
//                'michael@eggplant.ws',
//                'michael@eggplant.ws',
//                'HarvestHand Notify',
//                var_export($_POST, true)
//            );
//        }
//    }

//    public function robotsTxtAction()
//    {
//        $this->_helper->layout->disableLayout();
//        $this->_response->setHeader('Content-Type', 'text/plain');
//    }
//
//    public function sitemapXmlAction()
//    {
//        $this->_helper->layout->disableLayout();
//        $this->_response->setHeader('Content-Type', 'application/xml');
//
//        $this->view->urls = array();
//
//        $this->view->urls[] = array(
//            'loc' => 'http://' . Bootstrap::$domain
//        );
//        $this->view->urls[] = array(
//            'loc' => 'http://' . Bootstrap::$domain . '/forum'
//        );
//    }

//    public function aboutAction()
//    {
//
//    }
//
//    public function featuresAction()
//    {
//
//    }

    public function memberformAction()
    {
        $this->_helper->layout->disableLayout();
    }

    public function csaformAction()
    {
        $this->_helper->layout->disableLayout();
    }

    public function loginAction()
    {
        $this->view->title = $this->translate->_('Login');
        $this->view->errors = false;

        if (!empty($_POST)) {

            $filter = HH_Domain_Farmer::getFilter('login');
            $filter->setData($_POST);

            if ($filter->isValid()) {

                $result = HH_Domain_Farmer::authenticate(
                    $filter->getUnescaped('userName'),
                    $filter->getUnescaped('password'),
                    $filter->getUnescaped('role'),
                    $filter->getUnescaped('farmId')
                );

                if ($result->isValid()) {

                    $auth = Zend_Auth::getInstance();
                    $farmer = $auth->getIdentity();

                    $this->_redirect(
                        HH_Tools_Authentication::getRedirectUrl($farmer),
                        array('exit' => true)
                    );

                } else {
                    $this->view->errors = array(
                        'title' => $this->translate->_('Login Failure'),
                        'body' => $this->translate->_('User name or password incorrect')
                    );
                }
            } else {
                $this->view->errors = array(
                    'title' => $this->translate->_('Woops!'),
                    'body' => $this->translate->_('Some of the information provided below is not right')
                );

                $this->view->errorMessages = $filter->getMessages();
            }
        }

        $this->render();
    }

    public function logoutAction()
    {
        if ($this->farmer instanceof HH_Domain_Farmer) {
            $this->farmer->logout();
        }

        $this->_redirect('/', array('exit' => true));
    }

    public function testAction()
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

            switch (Model_Public::detectSignupAjaxVidationType()) {
                case Model_Public::VALIDATE_USERNAME :
                    $validate = new HH_Validate_UserNameUnique(
                        HH_Domain_Farmer::ROLE_FARMER
                    );
                    $this->_response->appendBody(
                        Zend_Json::encode(
                            $validate->isValid($_GET['farmer']['userName'])
                        )
                    );
                    break;

                case Model_Public::VALIDATE_SUBDOMAIN :
                    $validate = new HH_Validate_SubdomainUnique();
                    $this->_response->appendBody(
                        Zend_Json::encode(
                            $validate->isValid($_GET['farm']['subdomain'])
                        )
                    );
                    break;

                case Model_Public::GET_SUBDIVISIONS :
                    $country = substr($_GET['country'], 0, 2);

                    $this->_response->appendBody(
                        HH_Tools_Countries::getRawSubdivisions($country)
                    );
                    break;

                case Model_Public::GET_UNLOCODES :
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


        } else if ($this->_request->isPost()) {

            $farmValidator = HH_Domain_Farm::getFilter(
                HH_Domain_Farm::FILTER_NEW
            );
            $farmerValidator = HH_Domain_Farmer::getFilter(
                HH_Domain_Farmer::FILTER_NEW,
                array('role' => HH_Domain_Farmer::ROLE_FARMER)
            );

            $farmValidator->setData($_POST['farm']);
            $farmerValidator->setData($_POST['farmer']);

            if ($farmValidator->isValid() && $farmValidator->isValid()) {

                $farmData = $farmValidator->getUnescaped();
                $farmerData = $farmerValidator->getUnescaped();

                if (!empty($farmerData['email']) && empty($farmData['email'])) {
                    $farmData['email'] = $farmerData['email'];
                }
                $farmerData['role'] = HH_Domain_Farmer::ROLE_FARMER;

                $db = Bootstrap::getZendDb();
                $db->beginTransaction();

                try {
                    $farmer = new HH_Domain_Farmer();
                    $farmer->getService()->save($farmerData);

                    $farmData['status'] = HH_Domain_Farm::STATUS_TRIAL;
                    $farmData['version'] = HH_Domain_Farm::getLatestVersion();
                    if (empty($farmData['timeZone'])) {
                        $farmData['timeZone'] = HH_Tools_Countries::getTimezone($farmData['country'], $farmData['state']);
                    }
                    $farmData['primaryFarmerId'] = $farmer->id;

                    $farm = new HH_Domain_Farm();
                    $farm->insert($farmData);

                    $farmer->update(array('farmId' => $farm->id));

                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    HH_Error::exceptionHandler($e);
                }


            } else {
                $this->view->errors = array();
                $this->view->errors['farmer'] = $farmerValidator->getMessages();
                $this->view->errors['farm'] = $farmValidator->getMessages();
            }

        } else {
            Model_Public::explodeGeoInformation();
        }
    }

    public function signupAction()
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

            switch (Model_Public::detectSignupAjaxVidationType()) {
                case Model_Public::VALIDATE_USERNAME :
                    $validate = new HH_Validate_UserNameUnique(
                        HH_Domain_Farmer::ROLE_FARMER
                    );
                    $this->_response->appendBody(
                        Zend_Json::encode(
                            $validate->isValid($_GET['farmer']['userName'])
                        )
                    );
                    break;

                case Model_Public::VALIDATE_SUBDOMAIN :
                    $validate = new HH_Validate_SubdomainUnique();
                    $this->_response->appendBody(
                        Zend_Json::encode(
                            $validate->isValid($_GET['farm']['subdomain'])
                        )
                    );
                    break;

                case Model_Public::GET_SUBDIVISIONS :
                    $country = substr($_GET['country'], 0, 2);

                    $this->_response->appendBody(
                        HH_Tools_Countries::getRawSubdivisions($country)
                    );
                    break;

                case Model_Public::GET_UNLOCODES :
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


        } else if ($this->_request->isPost()) {

            $farmValidator = HH_Domain_Farm::getFilter(
                HH_Domain_Farm::FILTER_NEW
            );
            $farmerValidator = HH_Domain_Farmer::getFilter(
                HH_Domain_Farmer::FILTER_NEW,
                array('role' => HH_Domain_Farmer::ROLE_FARMER)
            );

            $farmValidator->setData($_POST['farm']);
            $farmerValidator->setData($_POST['farmer']);

            if ($farmValidator->isValid() && $farmValidator->isValid()) {

                $farmData = $farmValidator->getUnescaped();
                $farmerData = $farmerValidator->getUnescaped();

                if (!empty($farmerData['email']) && empty($farmData['email'])) {
                    $farmData['email'] = $farmerData['email'];
                }
                $farmerData['role'] = HH_Domain_Farmer::ROLE_FARMER;

                $db = Bootstrap::getZendDb();
                $db->beginTransaction();

                try {
                    $farmer = new HH_Domain_Farmer();
                    $farmer->getService()->save($farmerData);

                    $farmData['status'] = HH_Domain_Farm::STATUS_TRIAL;
                    $farmData['version'] = HH_Domain_Farm::getLatestVersion();
                    if (empty($farmData['timeZone'])) {
                        $farmData['timeZone'] = HH_Tools_Countries::getTimezone($farmData['country'], $farmData['state']);
                    }
                    $farmData['primaryFarmerId'] = $farmer->id;

                    $farm = new HH_Domain_Farm();
                    $farm->insert($farmData);

                    $farmer->update(array('farmId' => $farm->id));

                    $db->commit();
                } catch (Exception $e) {
                    $db->rollBack();
                    HH_Error::exceptionHandler($e);
                }

				HH_Domain_Farmer::authenticate(
                    $farmer['userName'],
                    $_POST['farmer']['password'], // $farmer['password'] was the encrypted string, raw password required (GW 2014_02_10)
                    $farmer['role'],
                    $farmer['farmId']
                );

				$layout = new Zend_Layout();
                $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                $layout->setLayout('email');
                $layout->getView()->farm = $farm;

				$view = new Zend_View();
				$view->setScriptPath($this->view->getScriptPaths());
                $view->farmer = $farmer;
                $view->farm = $farm;

				$layout->content = $view->render('public/new-farm-email.phtml');

				$eml = new HH_Job_Email();
				$eml->add(
                    array(
                        $farm->email,
                        $farm->name
                    ),
					'team@harvesthand.com',
					$this->translate->_('New Farm Added in HarvestHand'),
					null,
					$layout->render(),
					$farm->email,
					null,
                    null,
                    'farmnik@harvesthand.com',
                    'farmnik@harvesthand.com'
                );

                $eml = new HH_Job_Email();
                $eml->add(
                    array(
                        'farmnik@harvesthand.com'
                    ),
                    'x+7401981833888@mail.asana.com',
                    $this->translate->_('New Farm Added in HarvestHand'),
                    null,
                    $layout->render(),
                    $farm->email,
                    null,
                    null,
                    'farmnik@harvesthand.com',
                    'farmnik@harvesthand.com'
                );

                $this->_redirect(
                    HH_Tools_Authentication::getRedirectUrl($farmer) . '/default/welcome',
                    array('exit' => true)
                );

            } else {
                $this->view->errors = array();
                $this->view->errors['farmer'] = $farmerValidator->getMessages();
                $this->view->errors['farm'] = $farmValidator->getMessages();
            }

        } else {
            Model_Public::explodeGeoInformation();
        }
    }

    public function legalAction()
    {
        $this->view->title = $this->translate->_('Legal');
    }

    public function acceptableusepolicyAction()
    {
        $this->view->title = $this->translate->_('Acceptable Use Policy');
    }

    public function forumAction()
    {
        if ($this->_request->getActionName() !== 'forum') {
            $this->_request->setActionName('forum');
        }
        $this->view->title = $this->translate->_('Community Forum');
    }

    public function vanillabridgeAction()
    {
        $this->_helper->layout->disableLayout();
    }

    public function loaderAction()
    {
        $this->_helper->layout->disableLayout();
        $this->setNoRender();

        $type = $this->_request->getParam('t', false);

        if ($type == 'j') {
            $this->_response->setHeader(
                'Content-Type', 'text/javascript',
                true
            );
        } elseif ($type == 'c') {
            $this->_response->setHeader(
                'Content-Type', 'text/css',
                true
            );
        }

        $this->_response->setHeader(
            'Cache-Control', 'public, max-age=29030400',
            true
        );
        $this->_response->setHeader(
            'Last-Modified',
            date('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] - 29030400) . ' GMT',
            true
        );
        $this->_response->setHeader(
            'Expires',
            date('D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + 29030400) . ' GMT',
            true
        );
        header_remove('Pragma');

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            $this->_response->setHttpResponseCode(304);
            return;
        }

        $compress = (bool) $this->_request->getParam('c', true);
        $files = $this->_request->getParam('f', false);

        if ($type == 'j' || $type == 'c') {

            $files = explode('~', $files);
            $body = '';

            if (!empty($files)) {

                $path = Bootstrap::$public . (($type == 'j') ? '_js/' : '_css/');

                foreach ($files as $file) {
                    if (preg_match('/^[a-z0-9_\-]+(\.js){0,1}$/i', $file)) {

                        $file = $path . str_replace('_', '/', $file);

                        if (strpos($file, '.js') === false && strpos($file, '.css') === false) {
                            $file .= ($type == 'j') ? '.js' : '.css';
                            $file .= ($compress) ? 'c' : '';
                        }

                        $body .= file_get_contents(
                            $file
                        ) . PHP_EOL;
                    }
                }
            }
            $this->_response->setBody($body);
        }
    }
}
