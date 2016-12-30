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
 * @package   HH_Service
 */

/**
 * Description of User
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: User.php 329 2011-09-27 01:14:40Z farmnik $
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Facebook_User extends HH_Service_Facebook_Base
{
    protected $_userId = 'me';

    /**
     * User constructor
     */
    public function __construct($userId = 'me', $config = array())
    {
        $this->_userId = $userId;
        parent::__construct($config);
    }
    
    /**
     * set the User ID
     * 
     * @param type $userId
     * @return HH_Service_Facebook_User 
     */
    public function setUserId($userId)
    {
        $this->_userId = $userId;
        
        return $this;
    }
    
    /**
     * Get user meta data
     */
    public function getUser()
    {
        $params = array(
            'access_token' => $this->_config['access_token']
        );
        
        $client = self::getHttpClient();
        
        $client->setUri(
            self::$url . $this->_userId . '?' . http_build_query($params)
        );

        return $this->_parseResponse(
            $client->request(Zend_Http_Client::GET)
        );
    }
    
    /**
     * Get user permissions
     */
    public function getPermissions()
    {
        $params = array(
            'access_token' => $this->_config['access_token']
        );
        
        $client = self::getHttpClient();
        
        $client->setUri(
            self::$url . $this->_userId . '/permissions?' . http_build_query($params)
        );

        return $this->_parseResponse(
            $client->request(Zend_Http_Client::GET)
        );
    }

    public function getAccounts()
    {
        $params = array(
            'access_token' => $this->_config['access_token']
        );
        
        $client = self::getHttpClient();
        
        $client->setUri(
            self::$url . $this->_userId . '/accounts?' . http_build_query($params)
        );

        return $this->_parseResponse(
            $client->request(Zend_Http_Client::GET)
        );
    }
}