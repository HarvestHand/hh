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
 * @copyright $Date: 2012-02-21 17:03:28 -0400 (Tue, 21 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Log model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Log.php 442 2012-02-21 21:03:28Z farmnik $
 * @copyright $Date: 2012-02-21 17:03:28 -0400 (Tue, 21 Feb 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Log extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    
    const CATEGORY_DEFAULT = 'DEFAULT';
    const CATEGORY_CUSTOMERS = 'CUSTOMERS';
    const CATEGORY_SHARES = 'SHARES';
    const CATEGORY_ADDONS = 'ADDONS';
    const CATEGORY_NEWSLETTER = 'NEWSLETTER';
    const CATEGORY_WEBSITE = 'WEBSITE';
    
    const EVENT_NEW = 'NEW';
    
    public static $events = array(
        self::EVENT_NEW
    );
    
    public static $categories = array(
        HHF_Domain_Log::CATEGORY_DEFAULT,
        HHF_Domain_Log::CATEGORY_CUSTOMERS,
        HHF_Domain_Log::CATEGORY_SHARES,
        HHF_Domain_Log::CATEGORY_ADDONS,
        HHF_Domain_Log::CATEGORY_NEWSLETTER,
        HHF_Domain_Log::CATEGORY_WEBSITE
    );
    
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
                        ),
                        'customerId' => array(
                            new Zend_Filter_Null()
                        )
                    ),
                    array(
                        'customerId' => array(
                            new Zend_Validate_StringLength(0, 30),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'description' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'category' => array(
                            new Zend_Validate_InArray(self::$categories),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'event' => array(
                            new Zend_Validate_InArray(self::$events),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
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
