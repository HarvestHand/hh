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
 * @copyright $Date: 2011-10-12 22:39:02 -0300 (Wed, 12 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */
/**
 * Description of Base
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Base.php 334 2011-10-13 01:39:02Z farmnik $
 * @copyright $Date: 2011-10-12 22:39:02 -0300 (Wed, 12 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Paypal_Adaptive_Base  extends Zend_Service_Abstract
{
    protected $_config = array();
    
    /**
     * Accounts constructor
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
    
    /**
     * @return Zend_Http_Client
     */
    protected function _getPaypalClient()
    {
        $config = Bootstrap::get('Zend_Config')->resources->paypal;
        
        $client = self::getHttpClient();
        $client->setHeaders(
            array(
                'X-PAYPAL-SECURITY-USERID' => $config->adaptive->username,
                'X-PAYPAL-SECURITY-PASSWORD' => $config->adaptive->password,
                'X-PAYPAL-SECURITY-SIGNATURE' => $config->adaptive->signature,
                'X-PAYPAL-APPLICATION-ID' => $config->adaptive->id,
                'X-PAYPAL-DEVICE-IPADDRESS' => $this->_sniffIpAddress(),
                'X-PAYPAL-REQUEST-DATA-FORMAT' => 'JSON',
                'X-PAYPAL-RESPONSE-DATA-FORMAT' => 'JSON',
                'X-PAYPAL-MERCHANT-REFERRAL-BONUS-ID' => $config->referralId,
                'X-PAYPAL-SANDBOX-EMAIL-ADDRESS' => 'worker@mompopmedia.com'
            )
        );
        
        return $client;
    }
    
    protected function _sniffIpAddress()
    {
        if (isset($_SERVER['REMOTE_ADDR'])) { 
            return $_SERVER['REMOTE_ADDR']; 
        } else if (isset($_SERVER['HTTP_X_FORWARDED_FOR'])) { 
            return $_SERVER['HTTP_X_FORWARDED_FOR']; 
        } else if (isset($_SERVER['HTTP_CLIENT_IP'])) { 
            return $_SERVER['HTTP_CLIENT_IP']; 
        } 
    }
    
    protected function _getLanguageFromFarm($farm)
    {
        switch ($farm->country) {
            default : 
                return 'en_US';
            case 'AU' :
                return 'en_AU';
            case 'FR' :
                return 'fr_FR';
            case 'GB' :
                return 'en_GB';
        }
    }
}