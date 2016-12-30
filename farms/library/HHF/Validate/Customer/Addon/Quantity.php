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
 * @copyright $Date: 2012-11-07 21:57:02 -0400 (Wed, 07 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Farm_Validate
 */

/**
 * Description of Time
 *
 * @package   HH_Farm_Validate
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Quantity.php 591 2012-11-08 01:57:02Z farmnik $
 * @copyright $Date: 2012-11-07 21:57:02 -0400 (Wed, 07 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Validate_Customer_Addon_Quantity extends Zend_Validate_Abstract
{
    /**
     * Validation failure message key for when the value is not above the min
     */
    const NOT_MINIMUM        = 'notMinimum';
    /**
     * Validation failure message key for when the value is not above the min
     */
    const NO_INVENTORY        = 'noInventory';

    /**
     * Validation failure message template definitions
     *
     * @var array
     */
    protected $_messageTemplates = array(
        self::NOT_MINIMUM  => "A minimum of '%unitOrderMinimum%' units has to be ordered",
        self::NO_INVENTORY => "Insufficient inventory"
    );

    /**
     * Additional variables available for validation failure messages
     *
     * @var array
     */
    protected $_messageVariables = array(
        'unitOrderMinimum' => 'unitOrderMinimum'
    );

    protected $unitOrderMinimum = 1;

    /**
     * @var HH_Domain_Farm
     */
    protected $farm;

    public function __construct(HH_Domain_Farm $farm)
    {
        $this->farm = $farm;
    }

    public function isValid($value)
    {
        try {
            $addon = new HHF_Domain_Addon($this->farm, $value['addonId']);

            if ($addon->isEmpty()) {
                $this->_error(self::NO_INVENTORY);
                return false;
            }

            // check minimum
            if (!empty($addon['unitOrderMinimum'])) {

                $this->unitOrderMinimum = $addon['unitOrderMinimum'];

                if ($value['quantity'] < $addon['unitOrderMinimum']) {
                    $this->_error(self::NOT_MINIMUM);
                    return false;
                }
            } else if ($value['quantity'] < 1) {
                $this->_error(self::NOT_MINIMUM);
                return false;
            }

            // check inventory max
            if (is_numeric($addon['inventory']) && $value['quantity'] > $addon['inventory']) {
                $this->_error(self::NO_INVENTORY);
                return false;
            }


        } catch (Exception $e) {
            return false;
        }

        return true;
    }
}