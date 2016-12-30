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
 * @copyright $Date: 2012-09-25 20:49:08 -0300 (Tue, 25 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * File model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Transaction.php 584 2012-09-25 23:49:08Z farmnik $
 * @copyright $Date: 2012-09-25 20:49:08 -0300 (Tue, 25 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Transaction extends HHF_Object_Db
{
    const TYPE_PAYPAL = 'PAYPAL';
    const TYPE_CASH = 'CASH';
    const TYPE_CHEQUE = 'CHEQUE';
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    
    public static $payments = array(
        HHF_Domain_Transaction::TYPE_PAYPAL,
        HHF_Domain_Transaction::TYPE_CASH,
        HHF_Domain_Transaction::TYPE_CHEQUE,
    );
    
    /**
     * Get object service layer
     * 
     * @return HHF_Domain_Transaction_Service
     */
    public function getService()
    {
        return parent::getService();
    }
    
    /**
     * @return HHF_Domain_Customer 
     */
    public function getCustomer()
    {
        if (!$this->isEmpty() && !empty($this->customerId)) {
        
            return HHF_Domain_Customer::singleton(
                $this->_farm,
                $this->customerId
            );
        }
    }
    
    /**
     * Get Zend_Filter_Input for domain
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {

            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        
                    ),
                    null,
                    array(
                        Zend_Filter_Input::MISSING_MESSAGE   =>
                            $translate->_("'%field%' is required"),
                        Zend_Filter_Input::NOT_EMPTY_MESSAGE =>
                            $translate->_("'%field%' is required"),
                    )
                );
                break;
        }

        return $inputFilter;
    }
}
