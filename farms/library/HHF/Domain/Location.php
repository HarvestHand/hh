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
 * @copyright $Date: 2013-04-13 23:05:11 -0300 (Sat, 13 Apr 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Location.php 638 2013-04-14 02:05:11Z farmnik $
 * @copyright $Date: 2013-04-13 23:05:11 -0300 (Sat, 13 Apr 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Location extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    const FETCH_ENABLED = 'ENABLED';
    const FETCH_PURCHASABLE = 'PURCHASABLE';
    const ORDER_DATETIME = 'DATETIME';

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
                            new Zend_Filter_StringTrim()
                        ),
                        'address' => array(
                            new Zend_Filter_Null()
                        ),
                        'address2' => array(
                            new Zend_Filter_Null()
                        ),
                        'zipCode' => array(
                            new Zend_Filter_Null()
                        ),
                        'latitudeDegrees' => array(
                            new Zend_Filter_Null()
                        ),
                        'longitudeDegrees' => array(
                            new Zend_Filter_Null()
                        ),
                        'latitudeDegrees' => array(
                            new Zend_Filter_Null()
                        ),
                        'latitudeDegreesTopRight' => array(
                            new Zend_Filter_Null()
                        ),
                        'longitudeDegreesTopRight' => array(
                            new Zend_Filter_Null()
                        ),
                        'latitudeDegreesBottomLeft' => array(
                            new Zend_Filter_Null()
                        ),
                        'longitudeDegreesBottomLeft' => array(
                            new Zend_Filter_Null()
                        ),
                        'latitudeDegreesBottomRight' => array(
                            new Zend_Filter_Null()
                        ),
                        'longitudeDegreesBottomRight' => array(
                            new Zend_Filter_Null()
                        ),
                        'memberLimit' => array(
                            new Zend_Filter_Null()
                        ),
                        'pricePerDelivery' => array(
                            new Zend_Filter_Null(Zend_Filter_Null::STRING)
                        ),
                        'details' => array(
                            new Zend_Filter_Null()
                        ),
                        'timeStart' => array(
                            new HHF_Filter_TimeTo24Hour()
                        ),
                        'timeEnd' => array(
                            new HHF_Filter_TimeTo24Hour()
                        )
                    ),
                    array(
                        'name' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid name is required')
                            )
                        ),
                        'address' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'address2' => array(
                            new Zend_Validate_StringLength(0, 100),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
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
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'country' => array(
                            new Zend_Validate_StringLength(2, 2),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A country is required')
                            )
                        ),
                        'latitudeDegrees' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'longitudeDegrees' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'latitudeDegreesTopRight' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'longitudeDegreesTopRight' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'latitudeDegreesBottomLeft' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'longitudeDegreesBottomLeft' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'latitudeDegreesBottomRight' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'longitudeDegreesBottomRight' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid longitude / latitude is required')
                            )
                        ),
                        'dayOfWeek' => array(
                            new Zend_Validate_InArray(
                                array(
                                    1, 2, 3, 4, 5, 6, 7
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Day of week is required')
                            )
                        ),
                        'timeStart' => array(
                            new HHF_Validate_Time(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Start time is required')
                            )
                        ),
                        'timeEnd' => array(
                            new HHF_Validate_Time(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('End time is required')
                            )
                        ),
                        'pricePerDelivery' => array(
                            new Zend_Validate_Float(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid price is required')
                            )
                        ),
                        'memberLimit' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid memer limit is required')
                            )
                        ),
                        'details' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'addOnCutOffTime' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
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
        }

        return $inputFilter;
    }

    /**
     * Fetch all pages
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchLocations(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        if (isset($options['fetch'])) {

            switch ($options['fetch']) {
                case self::FETCH_ENABLED :
                case self::FETCH_PURCHASABLE :
                    $sql .= ' WHERE enabled = 1';

                    break;
            }
        }

        if (isset($options['order'])) {

            switch ($options['order']) {
                case self::ORDER_DATETIME :
                    $sql .= ' ORDER BY dayOfWeek, timeStart, timeEnd';

                    break;
            }
        }

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $location) {

            if (isset($options['fetch'])
                && $options['fetch'] == self::FETCH_PURCHASABLE
                && is_numeric($location['memberLimit'])) {

                if ($location['memberLimit'] <= 0) {
                    continue;
                }

                $shareLocation = HHF_Domain_Customer_Share::fetchOne(
                    $farm,
                    array(
                        'columns' => array(
                            'COUNT(locationId) as locationMembers'
                        ),
                        'where' => array(
                            'locationId' => $location['id'],
                            'year' => date('Y')
                        ),
                        'groupBy' => array(
                            'locationId'
                        )
                    )
                );

                if (!$shareLocation->isEmpty() && $shareLocation['locationMembers'] >= $location['memberLimit']) {
                    continue;
                }
            }

            $return[] = new self(
                $farm,
                $location['id'],
                $location,
                $options
            );
        }

        return $return;
    }
}