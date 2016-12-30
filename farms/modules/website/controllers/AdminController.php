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
class Website_AdminController extends HHF_Controller_Action
{
    public function  init()
    {
        parent::init();
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_FARMER);
        $this->view->headTitle($this->translate->_('Administration'));
    }

    public function  indexAction()
    {

    }

    public function statsAction()
    {
        $this->view->report = $this->_request->getParam(
            'report', 
            'summary'
        );
        
        $date = $this->_request->getParam('statsDate');
        
        try {
            if (Zend_Date::isDate($date, 'yyyy-MM-dd')) {
                $date = new Zend_Date($date, 'yyyy-MM-dd');
            } else { 
                $date = Zend_Date::now();
            }
        } catch (Exception $exception) {
            $date = Zend_Date::now();
        }
        
        $this->view->date = $date;
        
        $this->view->getFormValue()->setDefaulVars(
            array(
                'statsDate' => $date->get('yyyy-MM-dd')
            )
        );
    }
    
    public function pagesAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_pagesData();
        }
        
        $this->view->pages = HHF_Domain_Page::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'title',
                    'parent',
                    'sort',
                    'publish'
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'parent',
                        'dir' => 'asc'
                    ),
                    array(
                        'column' => 'sort',
                        'dir' => 'asc'
                    )
                )
            )
        );
        
        $this->view->foundRows = $this->view->pages->getFoundRows();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _pagesData()
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
        $columns = array('title', 'parent', 'publish');
        
	if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {
                    
                    $dir = $_GET['sSortDir_' . $i];
                    
                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }
                    
                    if ($columns[(int) $_GET['iSortCol_' . $i]] == 'publish') {
                        $order[] = array(
                            'column' => 'parent',
                            'dir' => $dir
                        );
                        $order[] = array(
                            'column' => 'sort',
                            'dir' => $dir
                        );
                        
                    } else {
                    
                        $order[] = array(
                            'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                            'dir' => $dir
                        );
                    }
                }
            }
	}
        
        $search = addcslashes($this->_request->getParam('sSearch'), '%_');
        
        if (!empty($search)) {
            
            $where = array(
                'title LIKE ' 
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
            );
        } else {
            $where = array();
        }
        
        $rows = HHF_Domain_Page::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'title',
                    'parent',
                    'sort',
                    'publish'
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
            
            $totalRows = HHF_Domain_Page::fetch(
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
            if ($row->parent === null && $row->sort == 0) {
                $data['placement'] = $this->translate->_('Home Page');
            } else if ($row->parent === null) {
                $data['placement'] = $this->translate->_('Top');
            } else {
                
                $parent = $rows->getParent($row);

                if ($parent === false) {
                    $page = new HHF_Domain_Page($this->farm, $row->parent);
                    if ($page->isEmpty()) {
                        $data['placement'] = $this->translate->_('Orphaned');
                    } else {
                        $data['placement'] = sprintf(
                            $this->translate->_('Under "%s"'),
                            $page->title
                        );
                    }
                } else {
                    $data['placement'] = sprintf(
                        $this->translate->_('Under "%s"'),
                        $parent->title
                    );
                }
            }
            $result['aaData'][] = $data;
        }
        
        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }
    
    public function pageAction()
    {
        $pageId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($pageId)) {
            $this->_pageNew();
        } else if ($action != 'delete') {
            $this->_pageEdit($pageId);
        } else {
            $this->_pageDelete($pageId);
        }
    }

    protected function _pageNew()
    {
        $this->view->page = false;

        $this->view->parents = HHF_Domain_Page::fetchPages(
            $this->farm,
            array(
                'fetch'   => HHF_Domain_Page::FETCH_TOPLEVEL,
                'order'   => HHF_Domain_Page::ORDER_SORT,
                'summary' => true
            )
        );
        
        $pages = HHF_Domain_Page::fetchPages(
            $this->farm,
            array(
                'fetch'   => HHF_Domain_Page::FETCH_ALL,
                'order'   => HHF_Domain_Page::ORDER_SORT,
                'summary' => true
            )
        );

        $structure = array();
        foreach ($pages as $page) {
            if (array_key_exists($page->parent, $structure)) {
                $structure[$page->parent]['children'][] = $page->toArray();
            } else {
                $structure[$page->parent] = array(
                    'children' => array($page->toArray())
                );
            }            
        }

        $this->view->structure = $structure;

        if (!empty($_POST)) {
            $filter = HHF_Domain_Page::getFilter(
                HHF_Domain_Page::FILTER_NEW,
                array(
                    'title' => $_POST['title'],
                    'farm' => $this->farm
                )
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $currentPage = new HHF_Domain_Page($this->farm);
                $currentPage->insert($filter->getUnescaped());
                
                // refresh page sorts
                if (!empty($_POST['sortStructure'])) {
                    parse_str($_POST['sortStructure'], $structure);
                    if (!empty($structure['sort']) && is_array($structure['sort'])) {
                        foreach ($structure['sort'] as $sort => $pageId) {
                            if ($pageId == $currentPage->id) {
                                continue;
                            }

                            $updatePage = new HHF_Domain_Page(
                                $this->farm,
                                $pageId
                            );

                            if (!$updatePage->isEmpty()) {
                                $updatePage->update(array('sort' => $sort));
                            }
                        }
                    }
                }

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Page "%s" added!'),
                        $currentPage->title
                    )
                );
                
                $this->_redirect('/admin/website/pages', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    protected function _pageDelete($id)
    {
        $this->view->page = new HHF_Domain_Page($this->farm, $id);

        if ($this->view->page->isEmpty()) {
            $this->_redirect('/admin/website/pages', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Page "%s" was deleted'),
                $this->view->page->title
            )
        );

        $this->view->page->delete();

        $this->_redirect('/admin/website/pages', array('exit' => true));
    }

    protected function _pageEdit($id)
    {
        $currentPage = $this->view->page = new HHF_Domain_Page($this->farm, $id);

        if ($currentPage->isEmpty()) {
            $this->_redirect('/admin/website/pages', array('exit' => true));
        } else {
            $this->view->getFormValue()->setDefaulVars($currentPage);
        }
        
        $pages = HHF_Domain_Page::fetchPages(
            $this->farm,
            array(
                'fetch'   => HHF_Domain_Page::FETCH_ALL,
                'order'   => HHF_Domain_Page::ORDER_SORT,
                'summary' => true
            )
        );

        $parents = array();
        $structure = array();

        foreach ($pages as $page) {
            if ($page->parent === null) {
                $parents[] = $page;
            }

            if (array_key_exists($page->parent, $structure)) {
                $structure[$page->parent]['children'][] = $page->toArray();
            } else {
                $structure[$page->parent] = array(
                    'children' => array($page->toArray())
                );
            }            
        }

        $this->view->parents = $parents;
        $this->view->structure = $structure;

        if (!empty($_POST)) {
            $filter = HHF_Domain_Page::getFilter(
                HHF_Domain_Page::FILTER_EDIT,
                array(
                    'title' => $_POST['title'],
                    'farm' => $this->farm,
                    'currentId' => $currentPage->id
                )
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $updatedParent = false;

                $data = $filter->getUnescaped();

                if ($data['parent'] != $currentPage->parent) {
                    $updatedParent = $data['parent'];
                }

                $currentPage->update($data);

                // refresh page sorts
                if (!empty($_POST['sortStructure'])) {
                    parse_str($_POST['sortStructure'], $structure);
                    if (!empty($structure['sort']) && is_array($structure['sort'])) {
                        foreach ($structure['sort'] as $sort => $pageId) {
                            if ($pageId == $currentPage->id) {
                                continue;
                            }

                            $updatePage = new HHF_Domain_Page(
                                $this->farm,
                                $pageId
                            );

                            if (!$updatePage->isEmpty()) {
                                $updatePage->update(array('sort' => $sort));
                            }
                        }
                    }
                }

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Page "%s" updated!'),
                        $currentPage->title
                    )
                );
                
                $this->_redirect('/admin/website/pages', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    public function blogAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_blogData();
        }
        
        $this->view->posts = HHF_Domain_Post::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'token',
                    'title',
                    'publish',
                    'category',
                    'categoryToken',
                    'addedDatetime'
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

        $this->view->foundRows = $this->view->posts->getFoundRows();
        
        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }
    
    public function _blogData()
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
        $columns = array('title', 'category', 'publish', 'addedDatetime');
        
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
                'title LIKE ' 
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
            );
        } else {
            $where = array();
        }
        
        $rows = HHF_Domain_Post::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'token',
                    'title',
                    'publish',
                    'category',
                    'categoryToken',
                    'addedDatetime'
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
            
            $totalRows = HHF_Domain_Post::fetch(
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
            $data['addedDatetime'] = $data['addedDatetime']
                ->toString('yyyy-MM-dd');
            $result['aaData'][] = $data;
        }
        
        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }
    
    public function postAction()
    {
        $postId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_tagLookup();
        }
        
        if (empty($postId)) {
            $this->_postNew();
        } else if ($action != 'delete') {
            $this->_postEdit($postId);
        } else {
            $this->_postDelete($postId);
        }
    }

    protected function _tagLookup()
    {
        // do server side AJAX validation
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );
        
        $term = $this->_request->getParam('term');
        
        $this->_response->appendBody(
            Zend_Json::encode(
                HHF_Domain_Tag::fetchTagArray($this->farm, $term)
            )
        );
    }
    
    protected function _postNew()
    {
        $this->view->post = false;

        $this->view->categories = array_merge(
            HHF_Domain_Post::fetchCategories(
                $this->farm
            ),
            array (
                '_new' => $this->translate->_('Add New Category')
            )
        );
        
        $this->view->farmers = array();
        
        $farmers = HH_Domain_Farmer::fetch(
            array(
                'where' => array(
                    'farmId' => $this->farm->id,
                    'role' => HH_Domain_Farmer::ROLE_FARMER
                )
            )
        );
        
        foreach ($farmers as $farmer) {
            $this->view->farmers[$farmer->id] = $farmer->getFullName();
        }
        
        if (!empty($_POST)) {
            
            if ($_POST['category'] == '_new') {
                if (!empty($_POST['categoryNew'])) {
                    $_POST['category'] = $_POST['categoryNew'];
                } else {
                    $_POST['category'] = null;
                }
            }
            
            $_POST['role'] = HH_Domain_Farmer::ROLE_FARMER;
            
            $filter = HHF_Domain_Post::getFilter(
                HHF_Domain_Post::FILTER_NEW,
                array(
                    'title' => $_POST['title'],
                    'farm' => $this->farm,
                    'category' => $_POST['category']
                )
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $currentPost = new HHF_Domain_Post($this->farm);
                $currentPost->insert($filter->getUnescaped());

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Post "%s" added!'),
                        $currentPost->title
                    )
                );
                
                $this->_redirect('/admin/website/blog', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    protected function _postDelete($id)
    {
        $this->view->post = new HHF_Domain_Post($this->farm, $id);

        if ($this->view->post->isEmpty()) {
            $this->_redirect('/admin/website/blog', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Post "%s" was deleted'),
                $this->view->post->title
            )
        );

        $this->view->post->delete();

        $this->_redirect('/admin/website/blog', array('exit' => true));
    }

    protected function _postEdit($id)
    {
        $currentPost = $this->view->post = new HHF_Domain_Post(
            $this->farm,
            $id
        );

        if ($currentPost->isEmpty()) {
            $this->_redirect('/admin/website/blog', array('exit' => true));
        } else {
            $defaultVars = $currentPost->toArray();
            if (!empty($defaultVars['publishedDatetime'])) {
                $defaultVars['publishedDatetime'] 
                    = HH_Tools_Date::dateToDb($defaultVars['publishedDatetime']);
            }
            
            if (!empty($defaultVars['tags'])) {
                $tags = array();
                foreach ($defaultVars['tags'] as $tag) {
                    $tags[] = $tag->tag;
                }
                
                $defaultVars['tags'] = implode(', ', $tags);
            } else {
                $defaultVars['tags'] = null;
            }
            
            $this->view->getFormValue()
                ->setDefaulVars($defaultVars);
            
            $this->view->categories = array_merge(
                HHF_Domain_Post::fetchCategories(
                    $this->farm
                ),
                array (
                    '_new' => $this->translate->_('Add New Category')
                )
            );
            
            $this->view->farmers = array();
        
            $farmers = HH_Domain_Farmer::fetch(
                array(
                    'where' => array(
                        'farmId' => $this->farm->id,
                        'role' => HH_Domain_Farmer::ROLE_FARMER
                    )
                )
            );

            foreach ($farmers as $farmer) {
                $this->view->farmers[$farmer->id] = $farmer->getFullName();
            }
        }
        
        if (!empty($_POST)) {
            
            if ($_POST['category'] == '_new') {
                if (!empty($_POST['categoryNew'])) {
                    $_POST['category'] = $_POST['categoryNew'];
                } else {
                    $_POST['category'] = null;
                }
            }
            
            $filter = HHF_Domain_Post::getFilter(
                HHF_Domain_Page::FILTER_EDIT,
                array(
                    'title' => $_POST['title'],
                    'category' => $_POST['category'],
                    'farm' => $this->farm,
                    'currentId' => $currentPost->id
                )
            );
            
            $_POST['role'] = HH_Domain_Farmer::ROLE_FARMER;
            
            $filter->setData($_POST);

            if ($filter->isValid()) {

                $currentPost->update($filter->getUnescaped());

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Post "%s" updated!'),
                        $currentPost->title
                    )
                );
                
                $this->_redirect('/admin/website/blog', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }
    
    public function recipesAction()
    {
        
    }
    
    public function optionsAction()
    {
        $preferences = new HHF_Preferences(
            $this->farm, 
            HHF_Domain_Preference::TYPE_FARM, 
            null,
            'website'
        );
        
        if (!empty($_POST)) {
            
            $this->errors = array();
            
            if (!empty($_FILES['imageUpload']['name'])) {

                $headerImage = $preferences->get('headerImage');
                
                if (!empty($headerImage)) {

                    $file = new HHF_Domain_File(
                        $this->farm,
                        $preferences->get('headerImage')
                    );

                } else {

                    $file = new HHF_Domain_File(
                        $this->farm
                    );
                }

                try {

                    $file->upload(
                        'imageUpload',
                        HHF_Domain_File::TYPE_IMAGE,
                        HHF_Domain_File::CATEGORY_SHARES,
                        $this->translate->_('Website Header Image')
                    );

                    $_POST['website-headerImage'] = $file->id;
                    $_POST['website-headerHeight'] = $file->height;

                } catch (Exception $e) {

                    $this->errors['imageUpload'] = array(
                        $this->translate->_(
                            'Unable to receive uploaded file.'
                        )
                    );

                }
            }
            
            foreach ($_POST as $key => $value) {
                $preferences->replace($key, $value);
            }
            
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Website options updated!')
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
    
    public function linksAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_linksData();
        }
        
        $this->view->links = HHF_Domain_Link::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => '*',
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );
        
        $this->view->foundRows = $this->view->links->getFoundRows();
        
        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }
    
    public function _linksData()
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
        $columns = array('name', 'url');
        
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
                'name LIKE ' 
                    . Bootstrap::getZendDb()->quote('%' . $search . '%')
            );
        } else {
            $where = array();
        }
        
        $rows = HHF_Domain_Link::fetch(
            $this->farm,
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
            
            $totalRows = HHF_Domain_Link::fetch(
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
    
    public function linkAction()
    {
        $linkId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);
        
        if (empty($linkId)) {
            $this->_linkNew();
        } else if ($action != 'delete') {
            $this->_linkEdit($linkId);
        } else {
            $this->_linkDelete($linkId);
        }
    }
    
    
    protected function _linkNew()
    {
        $this->view->link = false;

        if (!empty($_POST)) {
            
            $filter = HHF_Domain_Link::getFilter(
                HHF_Domain_Post::FILTER_NEW
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $link = new HHF_Domain_Link($this->farm);
                $link->insert($filter->getUnescaped());

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Link "%s" added!'),
                        $link->name
                    )
                );
                
                $this->_redirect('/admin/website/links', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    protected function _linkDelete($id)
    {
        $this->view->link = new HHF_Domain_Link($this->farm, $id);

        if ($this->view->link->isEmpty()) {
            $this->_redirect('/admin/website/links', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Link "%s" was deleted'),
                $this->view->link->name
            )
        );

        $this->view->link->delete();

        $this->_redirect('/admin/website/links', array('exit' => true));
    }

    protected function _linkEdit($id)
    {
        $link = $this->view->link = new HHF_Domain_Link(
            $this->farm,
            $id
        );

        if ($link->isEmpty()) {
            $this->_redirect('/admin/website/links', array('exit' => true));
        } else {
            $this->view->getFormValue()
                ->setDefaulVars($link->toArray());
        }
        
        if (!empty($_POST)) {
            
            $filter = HHF_Domain_Link::getFilter(
                HHF_Domain_Page::FILTER_EDIT
            );
            
            $filter->setData($_POST);

            if ($filter->isValid()) {

                $link->update($filter->getUnescaped());

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Link "%s" updated!'),
                        $link->name
                    )
                );
                
                $this->_redirect('/admin/website/links', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }
}