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
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Validate
 */

/**
 * Description of UserNameUnique
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: SubdomainUnique.php 606 2012-12-27 04:25:36Z farmnik $
 * @package   HH_Validate
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Validate_SubdomainUnique extends Zend_Validate_Abstract
{
    const NOT_UNIQUE = 'NotUnique';

    protected $_reserved = array(
        'www',
        'planet',
        'dav',
        'support'
    );
    protected $_currentFarm;
    protected $_messageTemplates = array(
        self::NOT_UNIQUE => "'%value%' is already taken"
    );

    /**
     * UserNameUnique constructor
     */
    public function  __construct(HH_Domain_Farm $currentFarm = null)
    {
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
    public function isValid($subdomain)
    {
        $this->_value = $subdomain;

        foreach ($this->_reserved as $reserved) {
            if (stripos($reserved, $subdomain) !== false) {
                $this->_error(self::NOT_UNIQUE);
                return false;
            }
        }

        $result = HH_Domain_Farm::fetchSingleBySubdomain($subdomain);

        if (empty($result)) {
            return true;
        } else {
            if (!empty($this->_currentFarm)) {
                if ($result->id != $this->_currentFarm->id) {
                    $this->_error(self::NOT_UNIQUE);
                    return false;
                } else {
                    return true;
                }
            } else {
                $this->_error(self::NOT_UNIQUE);
                return false;
            }
        }
    }
}