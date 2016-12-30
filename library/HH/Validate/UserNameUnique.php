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
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Validate
 */

/**
 * Description of UserNameUnique
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: UserNameUnique.php 302 2011-08-03 22:26:55Z farmnik $
 * @package   HH_Validate
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Validate_UserNameUnique extends Zend_Validate_Abstract
{
    const NOT_UNIQUE = 'NotUnique';

    protected $_role;
    protected $_currentUser;
    protected $_currentFarm;
    protected $_messageTemplates = array(
        self::NOT_UNIQUE => "'%value%' is already taken"
    );

    /**
     * UserNameUnique constructor
     */
    public function  __construct($role = HH_Domain_Farmer::ROLE_FARMER,
        HH_Domain_Farmer $currentUser = null, HH_Domain_Farm $currentFarm = null)
    {
        $this->_role = $role;
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
     * @param  mixed $value
     * @return boolean
     * @throws Zend_Valid_Exception If validation of $value is impossible
     */
    public function isValid($userName)
    {
        $this->_value = $userName;

        $result = HH_Domain_Farmer::fetchByUserName(
            $userName,
            $this->_role,
            $this->_currentFarm
        );

        if (empty($result)) {
            return true;
        } else {
            if (!empty($this->_currentUser)) {
                foreach ($result as $user) {
                    if ($user->id != $this->_currentUser->id) {
                        $this->_error(self::NOT_UNIQUE);
                        return false;
                    }
                }
                return true;
            } else {
                $this->_error(self::NOT_UNIQUE);
                return false;
            }
        }
    }
}