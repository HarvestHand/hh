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
 * @copyright $Date: 2013-02-24 23:27:30 -0400 (Sun, 24 Feb 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Tools
 */

/**
 * Description of Authentication
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Authentication.php 613 2013-02-25 03:27:30Z farmnik $
 * @copyright $Date: 2013-02-24 23:27:30 -0400 (Sun, 24 Feb 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Tools
 */
class HH_Tools_Authentication
{
    /**
     * @var Zend_Session_Namespace
     */
    protected static $_authSessionData = null;

    /**
     * Get redirect URL for farmer
     *
     * @param HH_Domain_Farmer $farmer
     * @param boolean $clear Clear preauth data
     * @return string Url
     */
    public static function getRedirectUrl(HH_Domain_Farmer $farmer,
        $clear = true)
    {
        $url = self::getExplicitRedirectUrl($farmer);

        if (empty($url)) {
            switch ($farmer['role']) {
                case HH_Domain_Farmer::ROLE_MEMBER :
                    $url = $farmer->getFarm()->getBaseUri() . 'shares';
                    break;
                case HH_Domain_Farmer::ROLE_FARMER :
                    $farm = $farmer->getFarm();
                    if (empty($farm->domain)) {
                        $url = $farmer->getFarm()->getBaseUri() . 'admin';
                    } else {
                        $dataStore = new Zend_Session_Namespace('session');
                        $dataStore->transfer = Zend_Session::getId();
                        
                        $url = $farmer->getFarm()->getBaseUri() . 'service/default/login_relay?i='
                            . urlencode(HH_Crypto::encrypt(Zend_Session::getId()));
                    }
                    break;
                case HH_Domain_Farmer::ROLE_ADMIN :
                    $url = 'http://www.' . Bootstrap::$rootDomain . '/admin';
                    break;
                default :
                    $url = 'http://www.' . Bootstrap::$rootDomain . '/';
                    break;
            }
        }

        if ($clear) {
            self::clearPreAuthData();
        }

        return $url;
    }

    /**
     * Get explicitly set redirect URL
     * 
     * @param HH_Domain_Farmer $farmer
     * @return string URL
     */
    public static function getExplicitRedirectUrl(HH_Domain_Farmer $farmer = null)
    {
        $url = null;

        $filter = new HH_Filter_HHUrl(Bootstrap::$domains);

        if (!empty($_POST['redirect_url'])) {
            $url = $filter->filter($_POST['redirect_url']);
        }

        if (empty($url)) {
            self::_seedPreAuthData();

            $url = (isset(self::$_authSessionData->url))
                    ? $filter->filter(self::$_authSessionData->url) : null;
        }

        if (!empty($url) && strpos($url, '%SUBDOMAIN%') !== false) {
            if (empty($farmer) || $farmer['role'] != HH_Domain_Farmer::ROLE_FARMER) {
                $url = str_replace('%SUBDOMAIN%', 'www', $url);
            } else {

                $subdomain = $farmer->getParent()->subdomain;

                if (empty($subdomain)) {
                    $subdomain = $farmer->farmId;
                }

                $url = str_replace('%SUBDOMAIN%', $subdomain, $url);
            }
        }

        return $url;
    }

    /**
     * Check for explicit redirect URL
     * 
     * @return Boolean
     */
    public static function hasExplicitRedirectUrl()
    {
        $url = self::getExplicitRedirectUrl();

        if (!empty($url)) {
            return true;
        }

        return false;
    }

    /**
     * Clear pre authentication data
     * 
     */
    public static function clearPreAuthData()
    {
        self::_seedPreAuthData();
        self::$_authSessionData->unsetAll();
    }

    /**
     * Get role of farmer logging in
     * 
     * @return string
     */
    public static function getLoginRole()
    {
        if (isset($_REQUEST['role'])) {
            $inArray = in_array($_REQUEST['role'], HH_Domain_Farmer::$roles);
        } else {
            $inArray = false;
        }

        if ($inArray) {
            return $_REQUEST['role'];
        }

        self::_seedPreAuthData();

        $inArray = in_array(
            self::$_authSessionData->role,
            HH_Domain_Farmer::$roles
        );

        if (isset(self::$_authSessionData->role) && $inArray) {
            return self::$_authSessionData->role;
        }

        return HH_Domain_Farmer::ROLE_FARMER;
    }

    /**
     * Get the role for a referring page
     * 
     * @return string Role
     */
    public static function getReferringParentRole()
    {
        self::_seedPreAuthData();

        $inArray = in_array(
            self::$_authSessionData->referringParentRole,
            HH_Domain_Farmer::$roles
        );

        return (isset(self::$_authSessionData->referringParentRole) && $inArray)
            ? self::$_authSessionData->referringParentRole
            : HH_Domain_Farmer::ROLE_FARMER;
    }

    /**
     * Get the id of the parent for a referring page
     * @return int
     */
    public static function getReferringParent()
    {
        self::_seedPreAuthData();

        return (int) (isset(self::$_authSessionData->referringParent))
            ? self::$_authSessionData->referringParent : null;
    }

    /**
     * Seed pre authentication data from session namespace
     */
    protected static function _seedPreAuthData()
    {
        if (self::$_authSessionData === null) {
            self::$_authSessionData = new Zend_Session_Namespace(
                'preAuthentication'
            );
        }
    }
    
    public static function setExplicitRedirectUrl($url)
    {
        self::_seedPreAuthData();
        
        self::$_authSessionData->url = $url;
    }
}