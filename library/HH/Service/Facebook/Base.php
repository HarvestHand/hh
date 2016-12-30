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
 * @copyright $Date: 2015-06-05 13:26:05 -0300 (Fri, 05 Jun 2015) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Service
 */

/**
 * Description of Base
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Base.php 857 2015-06-05 16:26:05Z farmnik $
 * @copyright $Date: 2015-06-05 13:26:05 -0300 (Fri, 05 Jun 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Facebook_Base extends Zend_Service_Abstract
{
    // Worth trying 2.3? ... Maybe?
    // Sending access requests to the base graph URI
    // ex: https://graph.facebook.com/
    // is now deprecated (Version 1.00)
    // https://developers.facebook.com/docs/apps/changelog
    public static $url = 'https://graph.facebook.com/v2.3/';
    protected $_config = array();

    /**
     * constructor
     */
    public function __construct($config = array())
    {
        $this->setConfig($config);
    }

    /**
     * set model config
     *
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->_config = array_merge(
            $this->_config,
            $config
        );
    }

    public function getAccessToken($code, $redirectUrl){
        $client = self::getHttpClient();

        $params = array('client_id' => $this->_config['client_id'], 'redirect_uri' => $redirectUrl, 'client_secret' => $this->_config['client_secret'], 'code' => $code);

        $client->setUri(self::$url . 'oauth/access_token?' . http_build_query($params));

        $result = $this->_parseResponse($client->request(Zend_Http_Client::GET));

        if(!empty($result['access_token'])){
            $this->setConfig(array('access_token' => $result['access_token']));
        }

        return $result;
    }

    protected function _parseResponse(Zend_Http_Response $response)
    {
        if ($response->isError()) {

            $body = $response->getBody();

            if (!empty($body)) {

                try {

                    $error = Zend_Json::decode($body);

                } catch (Exception $e) {
                    HH_Error::exceptionHandler($e, E_USER_NOTICE);
                }

                if (!empty($error['error']['message'])) {

                    throw new HH_Service_Facebook_Exception(
                        $error['error']['message'],
                        $error['error']['type'],
                        $response->getStatus()
                    );
                }
            }

            throw new HH_Service_Facebook_Exception($response->getMessage(), $response->getStatus());
        }

        try {

            $result = Zend_Json::decode($response->getBody());
        } catch (Zend_Json_Exception $exception) {
            // try
            parse_str($response->getBody(), $result);

            if (empty($result)) {
                throw $exception;
            }
        }

        if (is_array($result) && isset($result['error'])) {

            $code = isset($result['error_code']) ? $result['error_code'] : 0;

            if (isset($result['error_description'])) {
                // OAuth 2.0 Draft 10 style
                $msg = $result['error_description'];
            } else if (isset($result['error']) && is_array($result['error'])) {
                // OAuth 2.0 Draft 00 style
                $msg = $result['error']['message'];
            } else if (isset($result['error_msg'])) {
                // Rest server style
                $msg = $result['error_msg'];
            } else {
                $msg = 'Unknown Error.';
            }

            throw new HH_Service_Facebook_Exception($msg, "_parseResponse", $code);
        }

        return $result;
    }

    /**
     *
     * @param string $userId
     * @return HH_Service_Facebook_User
     */
    public function getUserObject($userId = 'me')
    {
        return new HH_Service_Facebook_User($userId, $this->_config);
    }

    public function getPageObject($pageId)
    {
        return new HH_Service_Facebook_Page($pageId, $this->_config);
    }
}