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
 * @copyright $Date: 2013-11-14 19:39:06 -0400 (Thu, 14 Nov 2013) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Twitter
 *
 * @package   HH_Job 
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Twitter.php 689 2013-11-14 23:39:06Z farmnik $
 * @copyright $Date: 2013-11-14 19:39:06 -0400 (Thu, 14 Nov 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Twitter extends HH_Job
{
    const ACTION_INSERT = 'INSERT';
    const ACTION_UPDATE = 'UPDATE';
    const ACTION_DELETE = 'DELETE';
    
    protected $_shortUrlLength = 19;
    
    public function __construct($config = array())
    {
        parent::__construct($config);
    }
    
    public function add(HH_Domain_Farm $farm, $action, $params)
    {
        if ($this->_validateTwitterSetup($farm)) {
        
            parent::add('twitter', func_get_args());
        }
    }
    
    protected function _validateTwitterSetup(HH_Domain_Farm $farm)
    {
        // check if farm has facebook integration
        $preferences = $farm->getPreferences();
        
        $token = $preferences->get('twitter-oauthToken');
        $tokenSecret = $preferences->get('twitter-oauthTokenSecret');
        
        if (!empty($token) && $tokenSecret) {
        
            return true;
        }
        
        return false;
    }
    
    public function process(HH_Domain_Farm $farm, $action, $params)
    {
        if (!$this->_validateTwitterSetup($farm)) {
            return;
        }
        
        $preferences = $farm->getPreferences();
        
        $token = new Zend_Oauth_Token_Access();
        $token->setToken($preferences->get('twitter-oauthToken'));
        $token->setTokenSecret($preferences->get('twitter-oauthTokenSecret'));

        $client = new HH_Service_Twitter(
            array(
                'accessToken' => $token,
                'oauthOptions' => array(
                    'siteUrl' => 'https://api.twitter.com/oauth',
                    'consumerKey' => Bootstrap::get('Zend_Config')
                            ->resources->twitter->oauth_consumer_key,
                    'consumerSecret' => Bootstrap::get('Zend_Config')
                            ->resources->twitter->oauth_consumer_secret
                )
            )
        );

        try {
        
            $configuration = $client->helpConfiguration();
            
            $this->_shortUrlLength = (int) $configuration->short_url_length;
            
            if ($params instanceof HH_Object_Db) {
                $params->reload();
            }
            
            $action = $this->_updateAction($action, $params);
            
            switch ($action) {
                case 'INSERT' :
                    $this->_postProcessParams(
                        $action,
                        $params,
                        $this->_addPost($client, $params)
                    );
                    break;
                case 'UPDATE' :
                    $this->_postProcessParams(
                        'DELETE',
                        $params,
                        $this->_deletePost($client, $params)
                    );
                    $this->_postProcessParams(
                        $action,
                        $params,
                        $this->_addPost($client, $params)
                    );
                    break;
                case 'DELETE' :
                    $this->_postProcessParams(
                        $action,
                        $params,
                        $this->_deletePost($client, $params)
                    );
                    break;
            }
        } catch (Zend_Service_Twitter_Exception $exception) {
            if (stripos($exception->getMessage(), 'OAuth') !== false) {
                
                // bad credentials
                $preferences->delete('twitter-oauthToken');
                $preferences->delete('twitter-oauthTokenSecret');
                
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

When trying to post a HarvestHand item to Twitter on your behalf, Twitter returned an error.  The error indicating that HarvestHand no longer has permission to post to Twitter.  

There are a few reasons why this may happen:

 - You changed your Twitter password
 - You removed the HarvestHand Twitter application from your Twitter account

If you wish to re-enable your Twitter tie-in with HarvestHand, you will need to re-activate it in the HarvestHand backend.

If you have any questions, please respond to this email.


Team HarvestHand over and out.';

                $emailJob = new HH_Job_Email();
                $emailJob->add(
                    array('farmnik@harvesthand.com', 'HarvestHand'),
                    array($email, $farm['name']),
                    'HarvestHand  / Twitter Tie-In Expired',
                    $msg
                );
                
                return;
            } else if (stripos($exception->getMessage(), 'Status is a duplicate') !== false) {
                return;
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
                if (!empty($object['twitterId'])) {
                    return 'UPDATE';
                }
                break;
            case 'UPDATE' :
                if (empty($object['twitterId'])) {
                    return 'INSERT';
                }
                break;
        }
        
        return $action;
    }


    protected function _addPost(Zend_Service_Twitter $client, &$params)
    {
        $twitterParams = $this->_processParams($params, 'INSERT');
        
        if (!empty($twitterParams)) {
            $result = $client->statusesUpdate($twitterParams);
            
            if (isset($result->error)) {
                throw new Zend_Service_Twitter_Exception(
                    (string) $result->error
                );
            }
            
            return (string) $result->id;
        }
    }
    
    protected function _deletePost(Zend_Service_Twitter $client, &$params)
    {
        $twitterParams = $this->_processParams($params, 'DELETE');
        
        if (!empty($twitterParams)) {
        
            try {
            
                $result = $client->statusesDestroy($twitterParams);
                
                return true;
            } catch (Zend_Http_Client_Exception $exception) {
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
        HHF_Domain_Delivery $delivery, $twitterId)
    {
        $updateDelivery = new HHF_Domain_Delivery(
            $delivery->getFarm(),
            $delivery['id']
        );
        
        $updateDelivery->detachByType('HHF_Domain_Delivery_Observer');
        
        switch ($action) {
            case 'INSERT' :
            case 'UPDATE' :
                $updateDelivery->update(array('twitterId' => $twitterId));
                break;
            case 'DELETE' :
                if (!$updateDelivery->isEmpty()) {
                    $updateDelivery->update(array('twitterId' => null));
                }
                break;
        }
    }
    
    protected function _postProcessPost($action, 
        HHF_Domain_Post $post, $twitterId)
    {
        $updatePost = new HHF_Domain_Post(
            $post->getFarm(),
            $post['id']
        );
        
        $updatePost->detachByType('HHF_Domain_Post_Observer');
        
        switch ($action) {
            case 'INSERT' :
            case 'UPDATE' :
                $updatePost->update(array('twitterId' => $twitterId));
                break;
            case 'DELETE' :
                if (!$updatePost->isEmpty()) {
                    $updatePost->update(array('twitterId' => null));
                }
                break;
        }
    }
    
    protected function _postProcessIssue($action, 
        HHF_Domain_Issue $issue, $twitterId)
    {
        $updateIssue = new HHF_Domain_Issue(
            $issue->getFarm(),
            $issue['id']
        );
        
        $updateIssue->detachByType('HHF_Domain_Issue_Observer');
        
        switch ($action) {
            case 'INSERT' :
            case 'UPDATE' :
                $updateIssue->update(array('twitterId' => $twitterId));
                break;
            case 'DELETE' :
                if (!$updateIssue->isEmpty()) {
                    $updateIssue->update(array('twitterId' => null));
                }
                break;
        }
    }
    
    protected function _processParams(&$params, $action)
    {
        if ($action == 'DELETE') {
            if (!empty($params['twitterId'])) {
                return $params['twitterId'];
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
        
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');
        
        $message = sprintf(
            $translate->_(
                'New !S posted for the week of %s'
            ),
            $startDate
        );
        
        $name = $delivery->getShare()->name;
        
        $url = $delivery->getFarm()->getBaseUri() . '/shares/index/week/'
            . $week . '/year/' . $year;
        
        $len = iconv_strlen($message, 'UTF-8');
        
        $nameLen = iconv_strlen($name, 'UTF-8');
        
        $totalLen = $len + $nameLen + $this->_shortUrlLength;
        
        if ($totalLen > 140) {
            $name = substr($name, 0, 140 - $totalLen);
        }
           
        $message = str_replace('!S', $name, $message);
        
        $len = iconv_strlen($message, 'UTF-8') 
            + 4 + $this->_shortUrlLength;
        
        if ($len < 140) {
            
            $items = array();
            $itemsLen = $len;
            $skipped = false;
            
            foreach ($delivery->getItems() as $item) {
                $itemLen = iconv_strlen($item->item, 'UTF-8');
                
                if ($itemsLen + 2 + $itemLen > 140) {
                    continue;
                    $skipped = true;
                }
                
                $items[] = $item->item;
                $itemsLen += 2 + $itemLen;
            }
            
            if (!empty($items)) {

                if ($skipped && ($itemsLen + 5 <= 140)) {
                    $items[] = '...';
                }
                
                $message .= ' (' . implode(', ', $items) . ')';
                
            }
        }
        
        $message .= ' ' . $url;
        
        return $message;
    }
    
    protected function _processPost(HHF_Domain_Post $post)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');
        
        $message = sprintf(
            $translate->_(
                'New blog post: %s'
            ),
            $post->title
        );
        
        $url = $post->getFarm()->getBaseUri() . 'blog/post/' . $post->token;
        
        $len = iconv_strlen($message, 'UTF-8') 
            + 4 + $this->_shortUrlLength;
        
        if ($len > 140) {
            $message = substr($message, 0, (136 - $this->_shortUrlLength)) . '...';
        }
        
        $message .= ' ' . $url;
        
        return $message;
    }
    
    protected function _processIssue(HHF_Domain_Issue $issue)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');
        
        $message = sprintf(
            $translate->_(
                'New newsletter issue: %s'
            ),
            $issue->title
        );
        
        $url = $issue->getFarm()->getBaseUri() . 'newsletter/issue/id/' . $issue->token;
        
        $len = iconv_strlen($message, 'UTF-8') 
            + 4 + $this->_shortUrlLength;
        
        if ($len > 140) {
            $message = substr($message, 0, (136 - $this->_shortUrlLength)) . '...';
        }
        
        $message .= ' ' . $url;
        
        return $message;
    }
}
