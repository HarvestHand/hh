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
 * @copyright $Date: 2012-02-14 16:04:34 -0400 (Tue, 14 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Validate
 */

/**
 * Description of Url
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Url.php 438 2012-02-14 20:04:34Z farmnik $
 * @package   HH_Validate
 * @copyright $Date: 2012-02-14 16:04:34 -0400 (Tue, 14 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Validate_Url extends Zend_Validate_Abstract
{
    /**
     * Error codes
     * @const string
     */
    const INVALID_URL = 'invalidUrl';

    /**
     * Error messages
     * @var array
     */
    protected $_messageTemplates = array(
        self::INVALID_URL   => "'%value%' is not a valid URL. It must start with http(s)://",
    );

    public function isValid($value)
    {
        if (!is_string($value)) {
            $this->_error(self::INVALID_URL);
             return false;
        }

        $this->_setValue($value);
        
        try {
            if (!Zend_Uri::check($value)) {
                $this->_error(self::INVALID_URL);
                return false;
            }
        } catch (Zend_Uri_Exception $exception) {
            $this->_error(self::INVALID_URL);
            return false;
        }
        
        return true;
    }
}