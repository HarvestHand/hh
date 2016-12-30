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
        $this->_helper->layout->setLayout('planet');
        $this->view->headTitle($this->translate->_('Planet'));
        
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
        $this->_contextSwitch->addActionContext(
            'index',
            array('rss', 'atom')
        );
        $this->_contextSwitch->initContext();
    }

    public function indexAction()
    {
        $category = $this->_request->getParam('category', false);
        $page = (int) $this->_request->getParam('page', 0);
        $this->view->page = $page;
        
        if (empty($page)) { 
            $limit = array('offset' => 0, 'rows' => 50);
        } else {
            $limit = array(
                'offset' => (($page * 50) - 50),
                'rows' => 50
            );
        }
        
        $this->view->posts = HH_Domain_Post::fetch(
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'title',
                    'media',
                    'tags',
                    'postUrl',
                    'summary',
                    'author',
                    'blogUrl',
                    'blogName',
                    'category',
                    'addedDatetime',
                    'updatedDatetime',
                ),
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'DESC'
                    )
                )
            )
        );
        
        switch ($this->_contextSwitch->getCurrentContext()) { 
            case 'rss': 
            case 'atom':
                $this->view->format = $this->_contextSwitch->getCurrentContext();
                
                $this->_helper->layout->disableLayout();
                return $this->render('index.feed');
                break; 
        } 
        
        $this->view->foundRows = $this->view->posts->getFoundRows();
        
        $this->view->paginator = new Zend_Paginator(
            new Zend_Paginator_Adapter_Null($this->view->foundRows)
        );

        $this->view->paginator->setDefaultItemCountPerPage(50);
        $this->view->paginator->setCurrentPageNumber($this->view->page);
    }
    
    public function thumbAction()
    {
        $this->_noRender = true;
        $this->_helper->layout->disableLayout();

        $httpResponseCode = (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) ? 304 : 200;
        
        $id = $this->_request->getParam('id', 0);

        header('Content-Type: image/png', true, $httpResponseCode);
        
        header_remove('Pragma');
        header('Cache-Control: public, max-age=29030400', true);
        header(
            'Last-Modified: ' . date(
                'D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] - 29030400
            ) . ' GMT', 
            true
        );
        header(
            'Expires: ' . date(
                'D, d M Y H:i:s', $_SERVER['REQUEST_TIME'] + 29030400
            ) . ' GMT',
            true
        );

        if (!empty($_SERVER['HTTP_IF_MODIFIED_SINCE'])) {
            return;
        }

        $post = new HH_Domain_Post($id);

        if ($post->isEmpty()) {
            $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');
        } else {
            $img = $post->getRawImage();
            if ($img === false) {
                $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');
            } else {
                $this->_response->setBody($img);
            }
        }
    }
    
    public function oauthAction()
    {
//        [oauth_token] => 1/YRgRkguYika9wWlUc_hpLDgCte_mZlxn7a6JDoMHIPk
//            [oauth_token_secret] => G9cWh2il3l4d5SeuvgO6nvLl
        
        $oauthOptions = array(
            'requestScheme'        => Zend_Oauth::REQUEST_SCHEME_QUERYSTRING,
            'version'              => '1.0',
            'consumerKey'          => 'www.harvesthand.com',
            'consumerSecret'       => 'Vfvv3iS-bA1m8okWPZk3yvZZ',
            'signatureMethod'      => 'HMAC-SHA1',
            'requestTokenUrl'      => 'https://www.google.com/accounts/OAuthGetRequestToken',
            'userAuthorizationUrl' => 'https://www.google.com/accounts/OAuthAuthorizeToken',
            'accessTokenUrl'       => 'https://www.google.com/accounts/OAuthGetAccessToken',
            'callbackUrl'          => 'http://www.harvesthand.com/planet/oauth'
        );
        
        $consumer = new Zend_Oauth_Consumer($oauthOptions);
        
        $token = new Zend_Oauth_Token_Access();
        
        $token->setToken('1/SUtcf5-AsXcoKSFG6Zqp7lF0iZmgEo9_yBNDcrLxhDk');
        $token->setTokenSecret('-slQeqfeU1PyhykGnxBj33Mv');
        
        $http = $token->getHttpClient($oauthOptions);
//        $http->setHeaders('Content-Type', 'application/atom+xml');
        
        
        $http->setUri('https://www.google.com/reader/api/0/stream/contents/user/-/state/com.google/reading-list?ot=1317009600&r=o&n=1000&mediaRss=true&client=HH');
//        $http->setUri('https://www.google.com/reader/api/0/stream/contents/feed/http://blog.realtimefarms.com/feed/?ot=1117009600&r=o&n=1000&mediaRss=true&client=HH');
        $result = $http->request('GET');
        
        if ($result->isSuccessful()) {
            $this->view->result = Zend_Json::decode($result->getBody());
        } else {
            $this->view->result = $result;
        }

        return;
        
        
        
        if (!isset($_SESSION['ACCESS_TOKEN_GOOGLE'])) { 
            if (!empty($_GET)) { 
                $token = $consumer->getAccessToken($_GET, unserialize($_SESSION['REQUEST_TOKEN_GOOGLE'])); 
                $_SESSION['ACCESS_TOKEN_GOOGLE'] = serialize($token); 
            } else { 
                $token = $consumer->getRequestToken(array('scope'=> 'http://www.google.com/reader/api/*')); 
                $_SESSION['REQUEST_TOKEN_GOOGLE'] = serialize($token); 
                $consumer->redirect(); 
                exit; 
            } 
        } else { 
            $token = unserialize($_SESSION['ACCESS_TOKEN_GOOGLE']); 
        } 
        
        
        $http  = $token->getHttpClient($oauthOptions);
    }
}