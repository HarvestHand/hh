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
 * @copyright $Date: 2012-09-02 20:22:40 -0300 (Sun, 02 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Customer model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Balance.php 572 2012-09-02 23:22:40Z farmnik $
 * @copyright $Date: 2012-09-02 20:22:40 -0300 (Sun, 02 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Balance extends HHF_Object_Db
{
    const SOURCE_INVOICE = 'INVOICE';
    const SOURCE_TRANSACTION = 'TRANSACTION';
    const SOURCE_MISC = 'MISC';
    
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    
    public function __construct(\HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Customer_Balance_Observer';
        
        parent::__construct($farm, $id, $data, $config);
    }
    
    /**
     * Get Zend_Filter_Input for model
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {
        $inputFilter = null;
        $filter = ($filter) ?: self::FILTER_NEW;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {
            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                
                break;
        }

        return $inputFilter;
    }
}
