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
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Controller
 */

/**
 * Description of ValidateAuthentication
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ValidateAuthentication.php 323 2011-09-22 22:22:20Z farmnik $
 * @package   HH_Controller
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Controller_Action_Helper_ValidateAuthentication
    extends Zend_Controller_Action_Helper_Abstract
{

    /**
     * getName()
     *
     * @return string
     */
    public function getName()
    {
        return 'ValidateAuthentication';
    }

    /**
     * Validate authentication
     * @param string $role Farmer role
     * @return boolean
     */
    public function direct($role = HH_Domain_Farmer::ROLE_FARMER)
    {
        $farmer = HH_Domain_Farmer::getAuthenticated();

        $isAuthenticated = true;

        if ($farmer instanceof HH_Domain_Farmer) {
            if ($farmer->role !== $role) {
                $isAuthenticated = false;
            }
        } else {
            $isAuthenticated = false;
        }

        if ($isAuthenticated === false) {

            $authSessionData = new Zend_Session_Namespace('preAuthentication');
            $authSessionData->url = $_SERVER['REQUEST_URI'];
            $authSessionData->role = $role;

            $redirect = $this->_actionController->getHelper('Redirector');
            $redirect->gotoUrl(
                'http://www.' . Bootstrap::$rootDomain . '/login',
                array('exit' => true, 'prependBase' => true)
            );
        }

        return $isAuthenticated;
    }
}