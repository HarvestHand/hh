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
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Page
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Page.php 329 2011-09-27 01:14:40Z farmnik $
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Facebook_Page extends HH_Service_Facebook_Base
{
    protected $_pageId;

    /**
     * User constructor
     */
    public function __construct($pageId, $config = array())
    {
        $this->_pageId = $pageId;
        parent::__construct($config);
    }
    
    /**
     * set the User ID
     * 
     * @param type $pageId
     * @return HH_Service_Facebook_User 
     */
    public function setPageId($pageId)
    {
        $this->_pageId = $pageId;
        
        return $this;
    }
    
    public function addPost($params = array())
    {
        $params['access_token'] = $this->_config['access_token'];
        
        $client = self::getHttpClient();
        
        $client->setUri(
            self::$url . $this->_pageId . '/feed'
        );
        
        $client->setParameterPost($params);

        $result = $this->_parseResponse(
            $client->request(Zend_Http_Client::POST)
        );
        
        if (!empty($result['id'])) {
            return $result['id'];
        } else {
            return $result;
        }
    }

    public function deletePost($postId)
    {
        $params = array(
            'access_token' => $this->_config['access_token']
        );
        
        $client = self::getHttpClient();
        
        $client->setUri(
            self::$url . $postId
        );
        
        $client->setParameterGet($params);

        $result = $this->_parseResponse(
            $client->request(Zend_Http_Client::DELETE)
        );
        
        return $result;
    }
    
    public function getPage()
    {
        $params = array(
            'access_token' => $this->_config['access_token']
        );
        
        $client = self::getHttpClient();
        
        $client->setUri(
            self::$url . $this->_pageId
        );
        
        $client->setParameterGet($params);

        $result = $this->_parseResponse(
            $client->request(Zend_Http_Client::GET)
        );
        
        return $result;
    }
}