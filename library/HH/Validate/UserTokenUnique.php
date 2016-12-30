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
 * @copyright $Date: 2012-12-27 00:44:49 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Validate
 */

/**
 * Description of UserTokenUnique
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: UserTokenUnique.php 607 2012-12-27 04:44:49Z farmnik $
 * @package   HH_Validate
 * @copyright $Date: 2012-12-27 00:44:49 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Validate_UserTokenUnique extends Zend_Validate_Abstract
{
    const NOT_UNIQUE = 'NotUnique';

    protected $_currentUser;
    protected $_currentFarm;
    protected $_messageTemplates = array(
        self::NOT_UNIQUE => "'%value%' is already taken"
    );

    /**
     * UserNameUnique constructor
     */
    public function  __construct(HH_Domain_Farmer $currentUser = null,
        HH_Domain_Farm $currentFarm = null)
    {
        $this->_currentUser = $currentUser;
        $this->_currentFarm = $currentFarm;
    }

    /**
     * Returns true if and only if $value meets the validation requirements
     *
     * If $value fails validation, then this method returns false, and
     * getMessages() will return an array of messages that explain why the
     * validation failed.
     *
     * @param  mixed $userToken
     * @return boolean
     * @throws Zend_Valid_Exception If validation of $value is impossible
     */
    public function isValid($userToken)
    {
        $this->_value = $userToken;

        $user = HH_Domain_Farmer::fetchUserByToken(
            $userToken,
            $this->_currentFarm
        );

        if (empty($user) || $user->isEmpty()) {
            return true;
        } else {
            if (!empty($this->_currentUser)) {
                if ($user->id != $this->_currentUser->id) {
                    $this->_error(self::NOT_UNIQUE);
                    return false;
                }
                return true;
            } else {
                $this->_error(self::NOT_UNIQUE);
                return false;
            }
        }
    }
}