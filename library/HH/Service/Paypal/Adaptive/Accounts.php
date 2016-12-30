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
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Service
 */

/**
 * Description of Accounts
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Accounts.php 339 2011-10-22 23:25:03Z farmnik $
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Paypal_Adaptive_Accounts 
    extends HH_Service_Paypal_Adaptive_Base
{
    protected $_category = '1011';
    protected $_subCategory = '2128';
    
    public function getUserAgreement(HH_Domain_Farm $farm)
    {
        $client = $this->_getPaypalClient();
        $client->setUri(
            Bootstrap::get('Zend_Config')
                ->resources->paypal->adaptive->account->url . 'GetUserAgreement'
        );
        
        $payload = array(
            'countryCode' => $farm->country,
            'languageCode' => $this->_getLanguageFromFarm($farm)
        );
        
        $client->setRawData(Zend_Json::encode($payload), 'application/json');
        
        /* @var $response Zend_Http_Response */
        $response = $client->request(Zend_Http_Client::POST);
        
        if ($response->isError()) {
            return false;
        }
        
        return Zend_Json::decode($response->getBody());
    }
    
    /**
     * Create new paypal account 
     * 
     * @param HH_Domain_Farm $farm
     * @return false|array 
     */
    public function createAccount(HH_Domain_Farm $farm)
    {
        $client = $this->_getPaypalClient();
        $client->setUri(
            Bootstrap::get('Zend_Config')
                ->resources->paypal->adaptive->account->url . 'CreateAccount'
        );
              
        /**
         * @todo ensure telephone is part of the profile
         */
        
        $payload = array(
            'accountType' => 'Business',
            'businessInfo' => array(
                'businessAddress' => array(
                    'line1' => $farm->address,
                    'line2' => $farm->address2,
                    'city' => $farm->city,
                    'state' => $farm->state,
                    'postalCode' => $farm->zipCode,
                    'countryCode' => $farm->country
                ),
                'businessName' => $farm->name,
                'category' => $this->_category,
                'customerServiceEmail' => $farm->email,
                'customerServicePhone' => $farm->telephone,
                'disputeEmail' => $farm->email,
                'subCategory' => $this->_subCategory,
                'webSite' => $farm->getBaseUri(),
                'workPhone' => $farm->telephone,
                'averagePrice' => $farm->getPreferences('paypal')->get('averagePrice'),
                'averageMonthlyVolume' => $farm->getPreferences('paypal')->get('averageMonthlyVolume'),
                'salesVenue' => 'WEB',
                'dateOfEstablishment' => $farm->getPreferences('paypal')->get('dateOfEstablishment'),
                'businessType' => $farm->getPreferences('paypal')->get('businessType'),
                'percentageRevenueFromOnline' => $farm->getPreferences('paypal')->get('percentageRevenueFromOnline')
            ),
            'address' => array(
                'line1' => $farm->address,
                'line2' => $farm->address2,
                'city' => $farm->city,
                'state' => $farm->state,
                'postalCode' => $farm->zipCode,
                'countryCode' => $farm->country
            ),
            'citizenshipCountryCode' => $farm->country,
            'contactPhoneNumber' => $farm->telephone,
            'createAccountWebOptions' => array(
                'returnUrl' => $farm->getBaseUri() . 'admin/default/paypal/a/added',
                'showAddCreditCard' => 'false'
            ),
            'currencyCode' => Zend_Locale_Data::getContent('en', 'currencytoregion', $farm->country),
            'dateOfBirth' => $farm->getPreferences('paypal')->get('dateOfBirth') . 'Z',
            'emailAddress' => $farm->email,
            'name' => array(
                'firstName' => $farm->getPrimaryFarmer()->firstName,
                'lastName' => $farm->getPrimaryFarmer()->lastName
            ),
            'notificationURL' => $farm->getBaseUri() . 'service/default/ipn',
            'preferredLanguageCode' => $this->_getLanguageFromFarm($farm),
            'registrationType' => 'Web',
            'requestEnvelope' => array(
                'detailLevel' => 'ReturnAll',
                'errorLanguage' => 'en_US'
            ),
            'suppressWelcomeEmail' => 'false'
        );
        
        $client->setRawData(Zend_Json::encode($payload), 'application/json');
        
        /* @var $response Zend_Http_Response */
        $response = $client->request(Zend_Http_Client::POST);
        
        if ($response->isError()) {
            return false;
        }
        
        return Zend_Json::decode($response->getBody());
    }
}