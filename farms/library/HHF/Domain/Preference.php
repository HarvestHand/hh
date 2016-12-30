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
 * @copyright $Date: 2011-09-24 22:07:36 -0300 (Sat, 24 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Preference model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Preference.php 325 2011-09-25 01:07:36Z farmnik $
 * @copyright $Date: 2011-09-24 22:07:36 -0300 (Sat, 24 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Preference extends HHF_Object_Db
{
    const TYPE_CUSTOMER = 'CUSTOMER';
    const TYPE_FARM = 'FARM';
    const TYPE_FARMER = 'FARMER';
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    
    public static $types = array(
        self::TYPE_FARM,
        self::TYPE_FARMER,
        self::TYPE_CUSTOMER
    );

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
                        'type' => array(
                            new Zend_Validate_InArray(self::$types),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'resource' => array(
                            new Zend_Validate_StringLength(0, 50),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'key' => array(
                            new Zend_Validate_StringLength(0, 50),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'value' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'farmerId' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'customerId' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
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
     * Fetch all preferences
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchPreferences(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        if (isset($options['type'])) {
            
            switch ($options['type']) {
                case self::TYPE_CUSTOMER :
                    $sql .= ' WHERE type = ?';
                    $bind[] = $options['type'];
                    
                    if (isset($options['resource'])) {
                        $sql .= ' AND resource = ?';
                        $bind[] = $options['resource'];
                    }
                    
                    if (isset($options['customerId'])) {
                        $sql .= ' AND customerId = ?';
                        $bind[] = $options['customerId'];
                    }
                    
                    break;
                case self::TYPE_FARM :
                    $sql .= ' WHERE type = ?';
                    $bind[] = $options['type'];
                    
                    if (isset($options['resource'])) {
                        $sql .= ' AND resource = ?';
                        $bind[] = $options['resource'];
                    }
                    
                    break;
                case self::TYPE_FARMER :
                    $sql .= ' WHERE type = ?';
                    $bind[] = $options['type'];
                    
                    if (isset($options['resource'])) {
                        $sql .= ' AND resource = ?';
                        $bind[] = $options['resource'];
                    }
                    
                    if (isset($options['farmerId'])) {
                        $sql .= ' AND farmerId = ?';
                        $bind[] = $options['farmerId'];
                    }
                    break;
            }
        } else if (isset($options['resource'])) {
            $sql .= ' AND resource = ?';
            $bind[] = $options['resource'];
        }
        
        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $preference) {
            $return[] = new self(
                $farm,
                $preference['id'],
                $preference,
                $options
            );
        }

        return $return;
    }
}