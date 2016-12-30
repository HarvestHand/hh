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
class Website_PublicController extends HHF_Controller_Action
{
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
        $this->_contextSwitch->addActionContext(
            'blog',
            array('rss', 'atom')
        );
        $this->_contextSwitch->initContext();

        $this->theme->bootstrap($this);
    }

    public function postDispatch()
    {
        if ($this->_helper->layout->isEnabled()) {

            $this->view->action = $this->getRequest()->getActionName();
            $this->view->module = $this->getRequest()->getModuleName();

            $this->view->placeholder('Zend_Layout')
                ->sideBar = $this->view->render('public/sideBar.phtml');
        }

        parent::postDispatch();
    }

    public function indexAction()
    {
        $enabled = $this->farm->getPreferences()->get('enabled', 'website', true);

        if (!$enabled) {
            $mainWebsite = $this->farm->getPreferences()->get('main', 'website');

            if (!empty($mainWebsite)) {
                return $this->_redirect($mainWebsite, array('exit' => true));
            }
        }

        $this->view->page = HHF_Domain_Page::fetchHomePage($this->farm);

        if ($this->view->page->isEmpty()) {

            // display something
            $mainWebsite = $this->farm->getPreferences()->get('main', 'website');

            if (!empty($mainWebsite)) {
                $this->_redirect($mainWebsite, array('exit' => true));
            }
        } else {
            if ($this->view->page->target == HHF_Domain_Page::TARGET_EXTERNAL) {
                if (!empty($this->view->page->url)) {
                    $this->_redirect(
                        $this->view->page->url,
                        array('exit' => true)
                    );
                }
            }
        }
    }

    public function robotsTxtAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_response->setHeader('Content-Type', 'text/plain');
    }

    public function sitemapXmlAction()
    {
        $this->_helper->layout->disableLayout();
        $this->_response->setHeader('Content-Type', 'application/xml');
        $this->_noRender = true;

        $this->_response->setBody(HH_Job_Sitemap::generate($this->farm));
    }

    public function  __call($methodName, $args)
    {
        $enabled = $this->farm->getPreferences()
            ->get('enabled', 'website', true);

        if (!$enabled) {
            $mainWebsite = $this->farm->getPreferences()
                ->get('main', 'website');

            if (!empty($mainWebsite)) {
                return $this->_redirect($mainWebsite, array('exit' => true));
            }
        }

        $request = explode('/', $_SERVER['REQUEST_URI']);

        $this->view->page = HHF_Domain_Page::fetchPageByToken(
            $this->farm,
            end($request)
        );

        if ($this->view->page->isEmpty()) {
            $redirect = HHF_Domain_Redirect::fetchIncomingPath(
                $this->farm,
                $_SERVER['REQUEST_URI']
            );

            if ($redirect->isEmpty()) {
                throw new Zend_Controller_Action_Exception(
                    sprintf('Page "%s" not found', $this->_request->getActionName()), 404
                );
            } else {
                $this->_redirect(
                    $redirect->outgoingPath,
                    array(
                        'exit' => true,
                        'code' => (int) $redirect->type
                    )
                );
            }
        } else {
            if ($this->view->page->target == HHF_Domain_Page::TARGET_EXTERNAL) {
                if (!empty($this->view->page->url)) {
                    $this->_redirect(
                        $this->view->page->url,
                        array('exit' => true)
                    );
                }
            }
        }

        $this->view->headTitle($this->view->page->title);
        $this->render('index');
    }

    public function contactAction()
    {
        if (empty($this->farm->email)) {
            $this->_forward('index', 'public', 'website');
        }

        $this->view->errors = array();

        if (!empty($_POST)) {

            $filter = new Zend_Filter_Input(
                array(
                    '*' => array(
                        new Zend_Filter_StringTrim()
                    )
                ),
                array(
                    'name' => array(
                        new Zend_Validate_StringLength(0, 255),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('A valid name is required')
                        )
                    ),
                    'email' => array(
                        new Zend_Validate_EmailAddress(),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_OPTIONAL,
                        Zend_Filter_Input::ALLOW_EMPTY => true,
                        Zend_Filter_Input::DEFAULT_VALUE => null,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('A valid delivery email is required')
                        )
                    ),
                    'telephone' => array(
                        new Zend_Validate_StringLength(0, 255),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_OPTIONAL,
                        Zend_Filter_Input::ALLOW_EMPTY => true,
                        Zend_Filter_Input::DEFAULT_VALUE => null
                    ),
                    'note' => array(
                        new Zend_Validate_StringLength(0, 25500),
                        Zend_Filter_Input::PRESENCE =>
                            Zend_Filter_Input::PRESENCE_REQUIRED,
                        Zend_Filter_Input::ALLOW_EMPTY => false,
                        Zend_Filter_Input::MESSAGES => array(
                            $this->translate->_('A note is required')
                        )
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

                $view = new Zend_View();
                $view->setScriptPath($this->view->getScriptPaths());
                $view->name = $filter->getUnescaped('name');
                $view->email = $filter->getUnescaped('email');
                $view->telephone = $filter->getUnescaped('telephone');
                $view->note = $filter->getUnescaped('note');

                $layout->content = $view->render('public/contact-email.phtml');

                if (empty($view->email)) {
                    $replyTo = array($this->farm->email, $this->farm->name);
                } else {
                    $replyTo = array($view->email, $view->name);
                }

                $email = new HH_Job_Email();
                $email->add(
                    array($this->farm->email, $this->farm->name),
                    $this->farm->email,
                    $this->translate->_('New Website Note From HarvestHand'),
                    null,
                    $layout->render(),
                    $replyTo,
                    null,
                    null,
                    'farmnik@harvesthand.com',
                    'farmnik@harvesthand.com'
                );
            } else {
                $this->view->errors = $filter->getMessages();
            }


        }
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
                    HH_Domain_Farmer::ROLE_MEMBER,
                    $this->farm->id
                );

                if ($result->isValid()) {

                    $auth = Zend_Auth::getInstance();
                    $farmer = $auth->getIdentity();

                    if (!empty($farmer->getFarm()->domain)) {

                        $dataStore = new Zend_Session_Namespace('session');
                        $dataStore->transfer = Zend_Session::getId();
                    }

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

    public function blogAction()
    {
        $enabled = $this->farm->getPreferences()
            ->get('blogEnabled', 'website', true);

        if (!$enabled) {
            return $this->_forward('404', 'error', 'default');
        }

        $post = $this->_request->getParam('post', false);
        $id = (int) $this->_request->getParam('id', 0);
        $author = (int) $this->_request->getParam('author', 0);
        $tag = $this->_request->getParam('tag', 0);
        $this->view->headTitle($this->translate->_('Blog'));

        if (!empty($post) || !empty($id)) {
            $this->_blogPost($post, $id);
        } else if (!empty($author)) {
            $this->_blogAuthorIndex($author);
        } else if (!empty($tag)) {
            $this->_blogTagIndex($tag);
        } else {
            $this->_blogIndex();
        }
    }

    protected function _blogAuthorIndex($farmerId)
    {
        $role = strtoupper($this->_request->getParam('role'));
        $page = (int) $this->_request->getParam('page', 0);
        $this->view->page = $page;

        if (!in_array($role, array(HH_Domain_Farmer::ROLE_FARMER, HH_Domain_Farmer::ROLE_MEMBER))) {
            $role = HH_Domain_Farmer::ROLE_FARMER;
        }

        $where = array(
            'publish' => HHF_Domain_Post::PUBLISH_PUBLISHED,
            'DATE(publishedDatetime) <= DATE(NOW())'
        );

        $this->view->title = $this->translate->_('Blog');
        $this->view->subTitle = '';

        if (!empty($farmerId)) {

            $farmer = HH_Domain_Farmer::fetchOne(
                array(
                    'where' => array(
                        'id' => $farmerId,
                        'role' => $role,
                        'farmId' => $this->farm->id
                    )
                )
            );

            if (!$farmer->isEmpty()) {
                $this->view->farmer = $farmer;
            } else {
                $this->_redirect('/blog', array('exit' => true));
            }

            if ($this->view->farmer->isEmpty()) {
                $this->_redirect('/blog', array('exit' => true));
            }

            $where['farmerId'] = $this->view->farmer->id;
            $where['farmerRole'] = $role;

            $filterTitle = $this->translate->_(
                sprintf(
                    'Posts by %s',
                    $this->view->farmer->getFullName()
                )
            );

            $this->view->headTitle($filterTitle);

            $this->view->subTitle .= $filterTitle;
        }

        if (empty($page)) {
            $limit = array('offset' => 0, 'rows' => 5);
        } else {
            $limit = array(
                'offset' => (($page * 5) - 5),
                'rows' => 5
            );
        }

        $this->view->posts = HHF_Domain_Post::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'publishedDatetime',
                        'dir' => 'DESC'
                    )
                ),
                'where' => $where
            )
        );

        $this->view->foundRows = $this->view->posts->getFoundRows();

        switch ($this->_contextSwitch->getCurrentContext()) {
            case 'rss':
            case 'atom':
                $this->view->format = $this->_contextSwitch->getCurrentContext();

                $this->_helper->layout->disableLayout();
                return $this->render('blog.feed');
                break;
        }

        $this->view->paginator = new Zend_Paginator(
            new Zend_Paginator_Adapter_Null($this->view->foundRows)
        );

        $this->view->paginator->setDefaultItemCountPerPage(5);
        $this->view->paginator->setCurrentPageNumber($this->view->page);
    }


    protected function _blogTagIndex($tagToken)
    {
        $where = array(
            'publish' => HHF_Domain_Post::PUBLISH_PUBLISHED,
            'DATE(publishedDatetime) <= DATE(NOW())'
        );

        $this->view->title = $this->translate->_('Blog');
        $this->view->subTitle = '';
        $page = (int) $this->_request->getParam('page', 0);
        $this->view->page = $page;

        if (!empty($tagToken)) {

            $tag = HHF_Domain_Tag::fetchTagByToken($this->farm, $tagToken);

            if (!$tag->isEmpty()) {
                $this->view->tag = $tag;
            } else {
                $this->_redirect('/blog', array('exit' => true));
            }

            $posts = HHF_Domain_Tag_Relationship::fetchTypesArrayByTag(
                $this->farm,
                $tag,
                HHF_Domain_Tag_Relationship::TYPE_POST
            );

            $where[] = 'id IN(' . implode(',', $posts) . ')';

            $filterTitle = $this->translate->_(
                sprintf(
                    'Posts Tagged \'%s\'',
                    $this->view->tag->tag
                )
            );

            $this->view->headTitle($filterTitle);

            $this->view->subTitle .= $filterTitle;
        }

        if (empty($page)) {
            $limit = array('offset' => 0, 'rows' => 5);
        } else {
            $limit = array(
                'offset' => (($page * 5) - 5),
                'rows' => 5
            );
        }

        if (!empty($posts)) {

            $this->view->posts = HHF_Domain_Post::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => $limit,
                    'order' => array(
                        array(
                            'column' => 'publishedDatetime',
                            'dir' => 'DESC'
                        )
                    ),
                    'where' => $where
                )
            );

            $this->view->foundRows = $this->view->posts->getFoundRows();
        } else {
            $this->view->posts = array();
            $this->view->foundRows = 0;
        }

        switch ($this->_contextSwitch->getCurrentContext()) {
            case 'rss':
            case 'atom':
                $this->view->format = $this->_contextSwitch->getCurrentContext();

                $this->_helper->layout->disableLayout();
                return $this->render('blog.feed');
                break;
        }

        $this->view->paginator = new Zend_Paginator(
            new Zend_Paginator_Adapter_Null($this->view->foundRows)
        );

        $this->view->paginator->setDefaultItemCountPerPage(5);
        $this->view->paginator->setCurrentPageNumber($this->view->page);
    }

    protected function _blogIndex()
    {
        $category = $this->_request->getParam('category', false);
        $tag = $this->_request->getParam('tag', false);
        $page = (int) $this->_request->getParam('page', 0);
        $this->view->page = $page;

        $translit = new HHF_Filter_Transliteration(255);

        $where = array(
            'publish' => HHF_Domain_Post::PUBLISH_PUBLISHED,
            'DATE(publishedDatetime) <= DATE(NOW())'
        );

        $this->view->title = $this->translate->_('Blog');
        $this->view->subTitle = '';

        if (!empty($category)) {
            $this->view->categoryToken =
                $where['categoryToken'] = $translit->filter($category);

            $filterTitle = $this->translate->_(
                sprintf(
                    'Category %s',
                    $where['categoryToken']
                )
            );

            $this->view->headTitle($filterTitle);

            $this->view->subTitle .= $filterTitle;
        }

        if (!empty($tag)) {
            $where['tag'] = $translit->filter($tag);

            $filterTitle = $this->translate->_(
                sprintf(
                    'Tag %s',
                    $where['tag']
                )
            );

            $this->view->headTitle(
                $filterTitle
            );

            if (!empty($this->view->subTitle)) {
                $this->view->subTitle = ' / ';
            }

            $this->view->subTitle .= $filterTitle;
        }

        if (empty($page)) {
            $limit = array('offset' => 0, 'rows' => 5);
        } else {
            $limit = array(
                'offset' => (($page * 5) - 5),
                'rows' => 5
            );
        }

        $this->view->posts = HHF_Domain_Post::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'limit' => $limit,
                'order' => array(
                    array(
                        'column' => 'publishedDatetime',
                        'dir' => 'DESC'
                    )
                ),
                'where' => $where
            )
        );

        $this->view->foundRows = $this->view->posts->getFoundRows();

        switch ($this->_contextSwitch->getCurrentContext()) {
            case 'rss':
            case 'atom':
                $this->view->format = $this->_contextSwitch->getCurrentContext();

                $this->_helper->layout->disableLayout();
                return $this->render('blog.feed');
                break;
        }

        $this->view->paginator = new Zend_Paginator(
            new Zend_Paginator_Adapter_Null($this->view->foundRows)
        );

        $this->view->paginator->setDefaultItemCountPerPage(5);
        $this->view->paginator->setCurrentPageNumber($this->view->page);
    }

    protected function _blogPost($post, $id)
    {
        if (!empty($post)) {
            $this->view->post = HHF_Domain_Post::fetchPostByToken(
                $this->farm,
                $post
            );
        } else if (!empty($id)) {
            $this->view->post = new HHF_Domain_Post($this->farm, $id);
        }

        if ($this->view->post->isEmpty()) {
            $this->_forward('404', 'error', 'default');
        } else {

            if (!empty($_POST)) {
                $filter = HHF_Domain_Post_Comment::getFilter(
                    HHF_Domain_Post_Comment::FILTER_NEW,
                    array('farm' => $this->farm)
                );

                $_POST['postId'] = $this->view->post->id;
                $_POST['farmerId'] = $this->farmer->id;
                $_POST['farmerRole'] = $this->farmer->role;

                $filter->setData($_POST);

                if ($filter->isValid()) {

                    $comment = new HHF_Domain_Post_Comment($this->farm);
                    $comment->insert($filter->getUnescaped());

                    $_POST = array();

                } else {
                    $this->view->errors = $filter->getMessages();
                }

            }

            $this->render('blog-post');

        }
    }

    public function disclaimerAction()
    {
        $this->view->headTitle($this->translate->_('Website Usage Disclaimer'));

        $this->view->disclaimer = $this->farm->getPreferences()
            ->get('disclaimer', 'website', '');
    }

    public function lessAction()
    {
        $this->_helper->layout->disableLayout();
        $this->setNoRender();

        $this->_response->setHeader(
            'Content-Type', 'text/css',
            true
        );

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

        try {

            require 'Less/Less.php';

            $preferences = new HHF_Preferences(
                $this->farm,
                HHF_Domain_Preference::TYPE_FARM,
                null,
                'website'
            );

            $theme = $preferences->get('theme', null, 'default');
            $target = $this->_request->getParam('t', 'bootstrap');

            if (!preg_match('/^[a-z0-9_\-]+(\.js){0,1}$/i', $target)) {
                $target = 'bootstrap';
            }

            $files = array(
                Bootstrap::$public . '_farms/less/themes/' . $theme . '/' . $target . '/main.less' => '/'
            );

            $file = Less_Cache::Get(
                $files,
                array(
                    'compress' => true,
                    'cache_dir' => Bootstrap::$root . 'temp'
                ),
                $preferences->getStructure($target, null, array())
            );

            $this->_response->setBody(
                file_get_contents(
                    Bootstrap::$root . 'temp/' . $file
                )
            );

        } catch (Exception $e) {
            HH_Error::exceptionHandler($e, E_USER_WARNING);
        }

    }

    /**
     * added by GW June 2014 -- needed a page outside of CMS to list the features so as not to appear in the menu. 
     * 
     */

    public function allFeaturesAction () {}
}
