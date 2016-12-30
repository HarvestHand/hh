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
 * @copyright $Date: 2015-07-28 16:56:59 -0300 (Tue, 28 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Customer model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Customer.php 916 2015-07-28 19:56:59Z farmnik $
 * @copyright $Date: 2015-07-28 16:56:59 -0300 (Tue, 28 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FILTER_NEW_FRONTEND = 'newfrontend';
    const FILTER_EDIT_FRONTEND = 'editfrontend';
    const FETCH_ALL = null;
    const ORDER_LASTNAME = 'LASTNAME';

    protected $_farmer = null;
    protected $_preferences = array();

    /**
     * Get object service layer
     *
     * @return HHF_Domain_Customer_Service
     */
    public function getService()
    {
        return parent::getService();
    }

    public function getBalanceTotal()
    {
        $balance = HHF_Domain_Customer_Balance::fetchOne(
            $this->getFarm(),
            array(
                'columns' => 'SUM(amount) as amount',
                'where' => array(
                    'customerId' => $this['id']
                )
            )
        );

        if (empty($balance['amount'])) {
            return 0;
        } else {
            return $balance['amount'];
        }
    }

    /**
     * get Farmer object
     * @return HH_Domain_Farmer
     */
    public function getFarmer()
    {
        if (!empty($this->farmerId) && $this->_farmer === null) {
            $this->_farmer = HH_Domain_Farmer::singleton($this->farmerId);
        }

        return $this->_farmer;
    }

    /**
     * Get farm preferences
     *
     * @param string $resource
     * @return HHF_Preferences
     */
    public function getPreferences($resource = null)
    {
        if (isset($this->_preferences[$resource]) &&
            $this->_preferences[$resource] instanceof HHF_Preferences) {

            return $this->_preferences[$resource];
        }

        $this->_preferences[$resource] = new HHF_Preferences(
            $this->getFarm(),
            HHF_Domain_Preference::TYPE_CUSTOMER,
            $this->_id,
            $resource
        );

        return $this->_preferences[$resource];
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

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {

            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        )
                    ),
                    array(
                        'balance' => array(
                            new Zend_Validate_Float(array('locale' => 'en')),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => '0.00',
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Must be a number.')
                            )
                        ),
                        'firstName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'lastName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'address' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'address2' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'city' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'state' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'zipCode' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'country' => array(
                            new Zend_Validate_StringLength(2, 2),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'telephone' => array(
                            new Zend_Validate_StringLength(0, 20),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'fax' => array(
                            new Zend_Validate_StringLength(0, 20),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'email' => array(
                            new Zend_Validate_EmailAddress(),
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Email is invalid'),
                                $translate->_('Email is invalid'),
                            )
                        ),
                        'secondaryEmail' => array(
                            new Zend_Validate_EmailAddress(),
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Email is invalid'),
                                $translate->_('Email is invalid'),
                            )
                        ),
                        'secondaryFirstName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'secondaryLastName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'notes' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid note is required')
                            )
                        ),
                        'enabled' => array(
                            new Zend_Validate_InArray(
                                array(
                                    0, 1
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Enabled status is required')
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
            case self::FILTER_NEW_FRONTEND :
            case self::FILTER_EDIT_FRONTEND :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim(),
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        )
                    ),
                    array(
                        'balance' => array(
                            new Zend_Validate_Float(array('locale' => 'en')),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => '0.00',
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Must be a number.')
                            )
                        ),
                        'firstName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'lastName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'address' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid address is required')
                            )
                        ),
                        'address2' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid address is required')
                            )
                        ),
                        'city' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid city is required')
                            )
                        ),
                        'state' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid state is required')
                            )
                        ),
                        'zipCode' => array(
                            new Zend_Validate_StringLength(0, 45),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid zip code is required')
                            )
                        ),
                        'country' => array(
                            new Zend_Validate_StringLength(2, 2),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid country is required')
                            )
                        ),
                        'telephone' => array(
                            new Zend_Validate_StringLength(0, 20),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid telephone is required')
                            )
                        ),
                        'fax' => array(
                            new Zend_Validate_StringLength(0, 20),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid fax is required')
                            )
                        ),
                        'email' => array(
                            new Zend_Validate_EmailAddress(),
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Email is invalid'),
                                $translate->_('Email is invalid'),
                            )
                        ),
                        'secondaryEmail' => array(
                            new Zend_Validate_EmailAddress(),
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Email is invalid'),
                                $translate->_('Email is invalid'),
                            )
                        ),
                        'secondaryFirstName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'secondaryLastName' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
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

    /**
     * Fetch all customers
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchCustomerList(HH_Domain_Farm $farm)
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    id,
                    CONCAT(firstName, \' \', lastName) as label
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                ORDER BY lastName ASC, firstName ASC';

        return $db->fetchAll($sql);
    }

    /**
     * Fetch all customers
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchCustomers(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        if (isset($options['order'])) {

            switch ($options['order']) {
                case self::ORDER_LASTNAME :
                    $sql .= ' ORDER BY lastName ASC';

                    break;
            }
        }

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $customer) {
            $return[] = new self(
                $farm,
                $customer['id'],
                $customer,
                $options
            );
        }

        return $return;
    }

    /**
     * Fetch customer count
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return int
     */
    public static function fetchCustomerCount(HH_Domain_Farm $farm)
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    count(*)
                FROM
                    ' . self::_getStaticDatabase($farm);

        return (int) $db->fetchOne($sql);
    }

    /**
     * Fetch single customer by farmer ID
     *
     * @param HH_Domain_Farm $farm
     * @param int|HH_Domain_Farmer $farmer
     * @param array $options
     * @return HHF_Domain_Customer|null
     */
    public static function fetchCustomerByFarmer(HH_Domain_Farm $farm, $farmer,
        $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    farmerId = ?';

        $bind = array();

        if ($farmer instanceof HH_Domain_Farmer) {
            $bind[] = $farmer->id;
        } else {
            $bind[] = (int) $farmer;
        }

        $result = $db->fetchRow($sql, $bind);

        if (!empty($result)) {
            return new self(
                $farm,
                $result['id'],
                $result,
                $options
            );
        }
    }
}