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
 * @package   HH_Service
 */

/**
 * Description of Twitter
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Twitter.php 689 2013-11-14 23:39:06Z farmnik $
 * @copyright $Date: 2013-11-14 19:39:06 -0400 (Thu, 14 Nov 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Twitter extends Zend_Service_Twitter
{

    /**
     * Twitter constructor
     */
    public function __construct($options = null, Zend_Oauth_Consumer $consumer = null, Zend_Http_Client $httpClient = null)
    {
        parent::__construct($options, $consumer, $httpClient);
        
        $this->methodTypes[] = 'help';
    }
    
    /**
     * Show help configuration
     *
     * @throws Zend_Http_Client_Exception if HTTP request fails or times out
     * @return Zend_Rest_Client_Result
     */
    public function helpConfiguration()
    {
        $this->init();
        $path = 'help/configuration';
        $response = $this->get($path);
        return new Zend_Service_Twitter_Response($response);
    }

}
