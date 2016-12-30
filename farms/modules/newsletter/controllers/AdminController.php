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
class Newsletter_AdminController extends HHF_Controller_Action
{
    public function  init()
    {
        parent::init();
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_FARMER);
        $this->view->headTitle($this->translate->_('Administration'));
    }

    public function indexAction()
    {
        $this->view->issues = HHF_Domain_Issue::fetchOne(
            $this->farm,
            array(
                'columns' => 'COUNT(*) as count'
            )
        );
    }
    
    public function issuesAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_issuesData();
        }
        
        $this->view->issues = HHF_Domain_Issue::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'title',
                    'publish',
                    'archive',
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

        $this->view->foundRows = $this->view->issues->getFoundRows();
        
        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }
    
    public function _issuesData()
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
        $columns = array('title', 'publish', 'archive', 'addedDatetime');
        
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
        
        $rows = HHF_Domain_Issue::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'title',
                    'publish',
                    'archive',
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
            
            $totalRows = HHF_Domain_Issue::fetch(
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
    
    public function issueAction()
    {
        if ($this->_request->isXmlHttpRequest() || !empty($_POST['preview'])) {
            return $this->_issuePreview();
        }
        
        $issueId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);
        
        if (empty($issueId)) {
            $this->_issueNew();
        } else if ($action != 'delete') {
            $this->_issueEdit($issueId);
        } else {
            $this->_issueDelete($issueId);
        }
    }

    public function recipientsAction()
    {
        $this->_helper->layout->setLayout('embedded');

        $recipients = new HHF_Object_Collection_Db($this->farm);
        $recipients->setObjectType('HHF_Domain_Issue_Recipient');

        $rawRecipients = $this->_request->getParam('recipients');

        if (!empty($rawRecipients) && is_array($rawRecipients)) {
            foreach ($rawRecipients as $rawRecipient) {
                if (strpos($rawRecipient, ':')) {
                    list($list, $params) = explode(':', $rawRecipient);

                    if (strpos($params, '|')) {
                        $params = explode('|', $params);
                    }
                } else {
                    $list = $rawRecipient;
                    $params = null;
                }

                $recipients[] = new HHF_Domain_Issue_Recipient(
                    $this->farm,
                    null,
                    array(
                        'list' => $list,
                        'params' => Zend_Json::encode($params)
                    )
                );
            }
        }

        $this->view->recipients = $recipients;

    }

    protected function _issuePreview()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );
        
        try {
        
            $issue = new HHF_Domain_Issue($this->farm);
            $issue->getService()->sendPreview($_POST);
        
            $this->_response->appendBody(
                Zend_Json::encode(
                    $this->translate->_('Sent a preview email.  Give it a few minutes to arrive.')
                )
            );
            
        } catch (HH_Object_Exception_Validation $exception) {
            $errors = $exception->getErrorMessages();
            $message = $this->translate->_('That failed miserably!  ');
            
            foreach ($errors as $key => $msgs) {
                $message .= implode('; ', $msgs);
            }
            
            $this->_response->appendBody(
                Zend_Json::encode(
                    $this->translate->_($message)
                )
            );
        }
        
        
    }
    
    protected function _issueNew()
    {
        $this->view->issue = false;
        
        $this->view->from = $this->farm->getFarmerEmails();
        
        $this->view->getFormValue()->setDefaulVars(
            array(
                'from' => $this->farmer->email
            )
        );
        
        if (!empty($_POST)) {
            
            try {
            
                $issue = new HHF_Domain_Issue($this->farm);
                $issue->getService()->save($_POST);
                
                $messenger = $this->_helper->getHelper('FlashMessenger');

                if ($issue->publish) {
                    $message = 'Issue "%s" added and queued up for delivery!';
                } else {
                    $message = 'Issue "%s" added!';
                }
                
                $messenger->addMessage(
                    sprintf(
                        $this->translate->_($message),
                        $issue->title
                    )
                );
                
                $this->_redirect('/admin/newsletter/issues', array('exit' => true));
                
            } catch (HH_Object_Exception_Validation $exception) {
                $this->view->errors = $exception->getErrorMessages();
            }
        }
    }

    protected function _issueDelete($id)
    {
        $this->view->issue = new HHF_Domain_Issue($this->farm, $id);

        if ($this->view->issue->isEmpty()) {
            $this->_redirect('/admin/newsletter/issues', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Issue "%s" was deleted'),
                $this->view->issue->title
            )
        );

        $this->view->issue->getService()->delete();

        $this->_redirect('/admin/newsletter/issues', array('exit' => true));
    }

    protected function _issueEdit($id)
    {
        $currentIssue = $this->view->issue = new HHF_Domain_Issue(
            $this->farm,
            $id
        );

        if ($currentIssue->isEmpty()) {
            $this->_redirect('/admin/newsletter/issues', array('exit' => true));
        } else {
            $defaultVars = $currentIssue->toArray();
            
            $defaultVars['recipients'] = $currentIssue->getRecipients()
                ->getService()
                ->toFormArray();
            
            $this->view->getFormValue()
                ->setDefaulVars($defaultVars);
            
            $this->view->from = $this->farm->getFarmerEmails();
        }
        
        if (!empty($_POST)) {
            
            try {
                $published = (bool) $currentIssue->publish;
                
                $currentIssue->getService()->save($_POST);
                
                $messenger = $this->_helper->getHelper('FlashMessenger');

                if ($currentIssue->publish && !$published) {
                    $message = 'Issue "%s" updated and queued up for delivery!';
                } else {
                    $message = 'Issue "%s" updated!';
                }
                
                $messenger->addMessage(
                    sprintf(
                        $this->translate->_($message),
                        $currentIssue->title
                    )
                );
                
                $this->_redirect('/admin/newsletter/issues', array('exit' => true));
                
            } catch (HH_Object_Exception_Validation $exception) {
                $this->view->errors = $exception->getErrorMessages();
            }
        }
    }
    
    public function optionsAction()
    {
        $preferences = new HHF_Preferences(
            $this->farm, 
            HHF_Domain_Preference::TYPE_FARM, 
            null,
            'newsletter'
        );
        
        if (!empty($_POST)) {
            foreach ($_POST as $key => $value) {
                $preferences->replace($key, $value);
            }
            
            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Newsletter options updated!')
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
}
