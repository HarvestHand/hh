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
 * @copyright $Date: 2014-08-03 15:10:59 -0300 (Sun, 03 Aug 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Location.php 794 2014-08-03 18:10:59Z farmnik $
 * @copyright $Date: 2014-08-03 15:10:59 -0300 (Sun, 03 Aug 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Addon_Location extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';

    /**
     * @return HHF_Domain_Location
     */
    public function getLocation()
    {
        return new HHF_Domain_Location(
            $this->getFarm(),
            $this->locationId
        );
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

                $rawLocations = HHF_Domain_Location::fetchLocations($options['farm']);
                $locations = array();
                
                foreach ($rawLocations as $location) {
                    $locations[] = $location['id'];
                }


                $inputFilter = new Zend_Filter_Input(
                    array(),
                    array(
                        'addonId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => self::_getStaticDatabase(
                                            $options['farm'],
                                            'HHF_Domain_Addon'
                                        ),
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb()
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid addon is required')
                            )
                        )
                    ),
                    array(
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
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid location is required')
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
