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
 * @package   HH_Validate
 */

/**
 * Description of DomainUnique
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: DomainUnique.php 339 2011-10-22 23:25:03Z farmnik $
 * @package   HH_Validate
 * @copyright $Date: 2011-10-22 20:25:03 -0300 (Sat, 22 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Validate_DomainUnique extends Zend_Validate_Abstract
{
    const NOT_UNIQUE = 'NotUnique';

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
     * @param  mixed $domain
     * @return boolean
     * @throws Zend_Valid_Exception If validation of $value is impossible
     */
    public function isValid($domain)
    {
        $this->_value = $domain;

        $result = HH_Domain_Farm::fetchSingleByDomain($domain);

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