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
 * @copyright $Date: 2012-10-22 21:46:55 -0300 (Mon, 22 Oct 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Facebook
 *
 * @package   HH_Job 
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Facebook.php 588 2012-10-23 00:46:55Z farmnik $
 * @copyright $Date: 2012-10-22 21:46:55 -0300 (Mon, 22 Oct 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Facebook extends HH_Job
{
    const ACTION_INSERT = 'INSERT';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';
    
    public function __construct($config = array())
    {
        parent::__construct($config);
    }
    
    public function add(HH_Domain_Farm $farm, $action, $params)
    {
        if ($this->_validateFacebookSetup($farm)) {
        
            parent::add('facebook', func_get_args());
        }
    }
    
    protected function _validateFacebookSetup(HH_Domain_Farm $farm)
    {
        // check if farm has facebook integration
        $preferences = $farm->getPreferences();
        
        $pageAccessToken = $preferences->get('facebook-pageAccessToken');
        $accessToken = $preferences->get('facebook-accessToken');
        $pageId = $preferences->get('facebook-pageId');
        
        if (!empty($pageAccessToken) && $accessToken && $pageId) {
        
            return true;
        }
        
        return false;
    }
    
    public function process(HH_Domain_Farm $farm, $action, $params)
    {
        if (!$this->_validateFacebookSetup($farm)) {
            return;
        }
        
        $preferences = $farm->getPreferences();
        
        $accessToken = $preferences->get('facebook-pageAccessToken');
        
        $client = new HH_Service_Facebook_Base(
            Bootstrap::get('Zend_Config')->resources->facebook->toArray()
        );
        
        $client->setConfig(array('access_token' => $accessToken));
        
        $page = $client->getPageObject($preferences->get('facebook-pageId'));
        
        try {
            
            if ($params instanceof HH_Object_Db) {
                $params->reload();
            }
            
            $action = $this->_updateAction($action, $params);
        
            switch ($action) {
                case 'INSERT' :
                    $this->_postProcessParams(
                        $action,
                        $params,
                        $this->_addPost($page, $params)
                    );
                    break;
                case 'UPDATE' :
                    $this->_postProcessParams(
                        'DELETE',
                        $params,
                        $this->_deletePost($page, $params)
                    );
                    $this->_postProcessParams(
                        $action,
                        $params,
                        $this->_addPost($page, $params)
                    );
                    break;
                case 'DELETE' :
                    $this->_postProcessParams(
                        $action,
                        $params,
                        $this->_deletePost($page, $params)
                    );
                    break;
            }
        } catch (HH_Service_Facebook_Exception $exception) {
            if ($exception->getType() == 'OAuthException') {
                
                $badTokens = array(
                    'Error validating access token',
                    'The session has been invalidated',
                    'Session has expired',
                    'Invalid OAuth access token'
                );
                
                $message = $exception->getMessage();
                
                foreach ($badTokens as $badToken) {
                    if (stripos($message, $message) !== false) {
                        // bad credentials
                        $preferences->delete('facebook-accessToken');
                        
                        // mail farm
                        $email = 'farmnik@harvesthand.com';
                        
                        if (!empty($farm['email'])) {
                            $email = $farm['email'];
                        } else {
                            $farmer = $farm->getPrimaryFarmer();
                            
                            if (!empty($farmer['email'])) {
                                $email = $farmer['email'];
                            }
                            
                            if (!empty($farmer['email2'])) {
                                $email = $farmer['email2'];
                            }
                        }
                        
                        $msg = '
Hi there,

When trying to post a HarvestHand item to Facebook on your behalf, Facebook returned an error.  The error indicating that HarvestHand no longer has permission to post to Facebook.  

There are a few reasons why this may happen:

 - You changed your Facebook password
 - You removed the HarvestHand Facebook application from your Facebook account
 
If you wish to re-enable your Facebook tie-in with HarvestHand, you will need to re-activate it in the HarvestHand backend.

If you have any questions, please respond to this email.


Team HarvestHand over and out.';
                        
                        $emailJob = new HH_Job_Email();
                        $emailJob->add(
                            array('farmnik@harvesthand.com', 'HarvestHand'),
                            array($email, $farm['name']),
                            'HarvestHand  / Facebook Tie-In Expired',
                            $msg
                        );
                        
                        return;
                    }
                }
            }
            
            throw $exception;
        }
    }
    
    /**
     * Update action to reflect current state
     * 
     * @param type $action
     * @param type $params
     * @return string 
     */
    protected function _updateAction($action, &$params)
    {
        $object = null;
        
        if ($params instanceof HHF_Domain_Delivery) {
            $object = new HHF_Domain_Delivery(
                $params->getFarm(),
                $params['id']
            );
        } else if ($params instanceof HHF_Domain_Post) {
            $object = new HHF_Domain_Post(
                $params->getFarm(),
                $params['id']
            );
        } else if ($params instanceof HHF_Domain_Issue) {
            $object = new HHF_Domain_Issue(
                $params->getFarm(),
                $params['id']
            );
        }
        
        if ($object === null) {
            return $action;
        }
        
        if ($object->isEmpty()) {
            return 'DELETE';
        }
        
        switch ($action) {
            case 'INSERT' :
                if (!empty($object['facebookId'])) {
                    return 'UPDATE';
                }
                break;
            case 'UPDATE' :
                if (empty($object['facebookId'])) {
                    return 'INSERT';
                }
                break;
        }
        
        return $action;
    }
    
    protected function _addPost(HH_Service_Facebook_Page $page, &$params)
    {
        $facebookParams = $this->_processParams($params, 'INSERT');
        
        
        if (!empty($facebookParams)) {
            return $page->addPost($facebookParams);
        }
    }
    
    protected function _deletePost(HH_Service_Facebook_Page $page, &$params)
    {
        $facebookParams = $this->_processParams($params, 'DELETE');
        
        if (!empty($facebookParams)) {
        
            try {
            
                return $page->deletePost($facebookParams);
            } catch (HH_Service_Facebook_Exception $exception) {
                unset ($exception);
            }
        }
    }
    
    protected function _postProcessParams($action, &$params, $result)
    {
        if ($params instanceof HHF_Domain_Delivery) {
            $this->_postProcessDelivery($action, $params, $result);
        } else if ($params instanceof HHF_Domain_Post) {
            $this->_postProcessPost($action, $params, $result);
        } else if ($params instanceof HHF_Domain_Issue) {
            $this->_postProcessIssue($action, $params, $result);
        }
    }
    
    protected function _postProcessDelivery($action, 
        HHF_Domain_Delivery $delivery, $facebookId)
    {
        $updateDelivery = new HHF_Domain_Delivery(
            $delivery->getFarm(),
            $delivery['id']
        );
        
        $updateDelivery->detachByType('HHF_Domain_Delivery_Observer');
        
        switch ($action) {
            case 'INSERT' :
            case 'UPDATE' :
                $updateDelivery->update(array('facebookId' => $facebookId));
                break;
            case 'DELETE' :
                if (!$updateDelivery->isEmpty()) {
                    $updateDelivery->update(array('facebookId' => null));
                }
                break;
        }
    }
    
    protected function _postProcessPost($action, 
        HHF_Domain_Post $post, $facebookId)
    {
        $updatePost = new HHF_Domain_Post(
            $post->getFarm(),
            $post['id']
        );
        
        $updatePost->detachByType('HHF_Domain_Post_Observer');
        
        switch ($action) {
            case 'INSERT' :
            case 'UPDATE' :
                $updatePost->update(array('facebookId' => $facebookId));
                break;
            case 'DELETE' :
                if (!$updatePost->isEmpty()) {
                    $updatePost->update(array('facebookId' => null));
                }
                break;
        }
    }
    
    protected function _postProcessIssue($action, 
        HHF_Domain_Issue $issue, $facebookId)
    {
        $updateIssue = new HHF_Domain_Issue(
            $issue->getFarm(),
            $issue['id']
        );
        
        $updateIssue->detachByType('HHF_Domain_Issue_Observer');
        
        switch ($action) {
            case 'INSERT' :
            case 'UPDATE' :
                $updateIssue->update(array('facebookId' => $facebookId));
                break;
            case 'DELETE' :
                if (!$updateIssue->isEmpty()) {
                    $updateIssue->update(array('facebookId' => null));
                }
                break;
        }
    }
    
    protected function _processParams(&$params, $action)
    {
        if ($action == 'DELETE') {
            if (!empty($params['facebookId'])) {
                return $params['facebookId'];
            } else {
                return null;
            }
        } else {
            if (is_array($params)) {
                return $params;
            } else {
                if ($params instanceof HHF_Domain_Delivery) {
                    return $this->_processDelivery($params);
                } else if ($params instanceof HHF_Domain_Post) {
                    return $this->_processPost($params);
                } else if ($params instanceof HHF_Domain_Issue) {
                    return $this->_processIssue($params);
                }
            }
        }
    }
    
    protected function _processDelivery(HHF_Domain_Delivery $delivery)
    {
        $dateFormatter = new IntlDateFormatter(
            Bootstrap::$locale,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE
        );

        list($year, $week) = explode('W', $delivery->week);

        $date = new DateTime();
        $date->setISODate($year, $week, 1);

        $startDate = $dateFormatter->format((int) $date->format('U'));
        $date->setISODate($year, $week, 7);
        $endDate = $dateFormatter->format((int) $date->format('U'));
        
        $description = array();
        
        foreach ($delivery->getItems() as $item) {
            $description[] = $item->item;
        }
        
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');
        
        return array(
            'message' => sprintf(
                $translate->_(
                    'New %s posted for the week of %s to %s'
                ),
                $delivery->getShare()->name,
                $startDate,
                $endDate
            ),
            'link' => $delivery->getFarm()->getBaseUri() . 'shares/index/week/' 
                . $week . '/year/' . $year,
            'name' => sprintf(
                $translate->_('%s to %s'),
                $startDate,
                $endDate
            ),
            'caption' => $delivery->getShare()->name,
            'description' => sprintf(
                $translate->_(
                    'In this share delivery there will be: %s'
                ),
                implode('; ', $description)
            ),
            'type' => 'link'
        );

    }
    
    protected function _processPost(HHF_Domain_Post $post)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');
        
        $description = trim(strip_tags($post->content));
        $picture = null;
        
        if (strlen($description) > 256) {
            $description = current(
                explode(
                    '||||||',
                    wordwrap($description, 256, '||||||')
                )
            ) . '...';
        }
        
        $doc = new DOMDocument();
        @$doc->loadHTML('<html><body>' . $post->content . '</body></html>');

        $tags = $doc->getElementsByTagName('img');

        foreach ($tags as $tag) {
           $image = $tag->getAttribute('src');
           
           if (stripos($image, 'http://') !== false) {
               $picture = $image;
               break;
           }
        }
        
        return array(
            'message' => sprintf(
                $translate->_(
                    'New blog post on the %s website.'
                ),
                $post->getFarm()->name
            ),
            'link' => $post->getFarm()->getBaseUri() . 'blog/post/' 
                . $post->token,
            'name' => $post->title,
            'caption' => sprintf(
                $translate->_(
                    '%s Blog'
                ),
                $post->getFarm()->name
            ),
            'description' => $description,
            'picture' => $picture,
            'type' => 'link'
        );

    }
    
    protected function _processIssue(HHF_Domain_Issue $issue)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');
        
        $description = trim(
            strip_tags(
                $issue->getService()->processContent(
                    $issue->getService()->createTestVariables()
                )
            )
        );
        $picture = null;
        
        if (strlen($description) > 256) {
            $description = current(
                explode(
                    '||||||',
                    wordwrap($description, 256, '||||||')
                )
            ) . '...';
        }
        
        $doc = new DOMDocument();
        @$doc->loadHTML('<html><body>' . $issue->content . '</body></html>');

        $tags = $doc->getElementsByTagName('img');

        foreach ($tags as $tag) {
           $image = $tag->getAttribute('src');
           
           if (stripos($image, 'http://') !== false) {
               $picture = $image;
               break;
           }
        }
        
        return array(
            'message' => sprintf(
                $translate->_(
                    'New newsletter issue on the %s website.'
                ),
                $issue->getFarm()->name
            ),
            'link' => $issue->getFarm()->getBaseUri() . 'newsletter/issue/id/' 
                . $issue->token,
            'name' => $issue->title,
            'caption' => sprintf(
                $translate->_(
                    '%s Newsletter'
                ),
                $issue->getFarm()->name
            ),
            'description' => $description,
            'picture' => $picture,
            'type' => 'link'
        );

    }
}