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
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Customer model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Line.php 695 2014-02-03 12:28:04Z farmnik $
 * @copyright $Date: 2014-02-03 08:28:04 -0400 (Mon, 03 Feb 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Invoice_Line extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const TYPE_SHARE = 'SHARE';
    const TYPE_ADDON = 'ADDON';
    const TYPE_DELIVERY = 'DELIVERY';
    const TYPE_ADMINISTRATION = 'ADMINISTRATION';
    const TYPE_MISC = 'MISC';

    /**
     * @var HHF_Domain_Customer_Invoice
     */
    protected $_invoice = null;
    protected $_customerType = null;
    protected $_type = null;

    public function setInvoice(HHF_Domain_Customer_Invoice $invoice)
    {
        $this->_invoice = $invoice;
    }

    /**
     * @return HHF_Domain_Customer_Invoice
     */
    public function getInvoice()
    {
        return $this->_invoice;
    }

    /**
     * Get line item type object
     *
     * @return \HHF_Domain_Addon
     */
    public function getType()
    {
        if ($this->_type !== null) {
            return $this->_type;
        }

        if ($this['type'] == self::TYPE_ADDON) {
            $customerAddon = $this->getCustomerType();

            $this->_type = new HHF_Domain_Addon(
                $this->_farm,
                $customerAddon['addonId']
            );
        }

        return $this->_type;
    }

    /**
     * Get line item customer type object
     *
     * @return \HHF_Domain_Customer_Addon
     */
    public function getCustomerType()
    {
        if ($this->_customerType !== null) {
            return $this->_customerType;
        }

        if ($this['type'] == self::TYPE_ADDON) {
            $this->_customerType = new HHF_Domain_Customer_Addon(
                $this->_farm,
                $this['referenceId']
            );
        }

        return $this->_customerType;
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

        $presence = ($filter == self::FILTER_NEW) ?
            Zend_Filter_Input::PRESENCE_REQUIRED :
            Zend_Filter_Input::PRESENCE_OPTIONAL;

        $allowEmpty = ($filter == self::FILTER_NEW) ? false : true;

        switch ($filter) {

            case self::FILTER_NEW_PARTIAL :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'shareId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share is required')
                            )
                        ),
                        'shareDurationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Duration'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid delivery schedule is required')
                            )
                        ),
                        'shareSizeId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Size'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share size is required')
                            )
                        ),
                        'locationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Location'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share pickup location is required')
                            )
                        ),
                        'quantity' => array(
                            new Zend_Validate_Between(array('min' => 1, 'max' => 99)),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share quantity is required')
                            )
                        ),
                        'year' => array(
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty
                        )
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
            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        )
                    ),
                    array(
                        'customerId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Customer'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid customer is required')
                            )
                        ),
                        'shareId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share is required')
                            )
                        ),
                        'shareDurationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Duration'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid delivery schedule is required')
                            )
                        ),
                        'shareSizeId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Share_Size'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share size is required')
                            )
                        ),
                        'locationId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                        $options['farm'],
                                        'HHF_Domain_Location'
                                    ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share pickup location is required')
                            )
                        ),
                        'quantity' => array(
                            new Zend_Validate_Between(array('min' => 1, 'max' => 99)),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid share quantity is required')
                            )
                        ),
                        'year' => array(
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty
                        ),
                        'paymentPlan' => array(
                            new Zend_Validate_InArray(
                                HHF_Order_Share::$paymentPlans
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                HHF_Order_Share::PAYMENT_PLAN_NONE,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid payment plan is required')
                            )
                        ),
                        'payment' => array(
                            new Zend_Validate_InArray(
                                HHF_Domain_Transaction::$payments
                            ),
                            Zend_Filter_Input::PRESENCE => $presence,
                            Zend_Filter_Input::ALLOW_EMPTY => $allowEmpty,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A payment method is required')
                            )
                        )
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
