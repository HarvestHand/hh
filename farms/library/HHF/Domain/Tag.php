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
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Tag model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Tag.php 409 2012-01-17 22:45:31Z farmnik $
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Tag extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    
    public static function getFilter($filter = null, $options = array())
    {
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);
        
        switch ($filter) {
            case self::FILTER_NEW :
                $tokenFilter = new HHF_Filter_Transliteration(
                    150,
                    'UTF-8',
                    false,
                    false
                );
                
                $inputFilter = new Zend_Filter_Input(
                    array(),
                    array(
                        'id' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true
                        ),
                        'tag' => array(
                            new Zend_Validate_StringLength(0, 150),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'token' => array(
                            new Zend_Validate_StringLength(0, 150),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                $tokenFilter->filter($options['tag'])
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
    
    public static function fetchTagArray(HH_Domain_Farm $farm, $search = '')
    {
        $database = self::_getStaticZendDb();
        
        $sql = 'SELECT 
                tag
            FROM
                ' . self::_getStaticDatabase($farm) . '
            WHERE
                tag LIKE ?';
        
        $bind = array(
            '%' . addcslashes($search, '%_') . '%'
        );
        
        return $database->fetchCol($sql, $bind);
    }
    
    /**
     * @param HH_Domain_Farm $farm
     * @param type $token
     * @return HHF_Domain_Tag
     */
    public static function fetchTagByToken(HH_Domain_Farm $farm, $token)
    {
        $tokenFilter = new HHF_Filter_Transliteration(
            150,
            'UTF-8',
            false,
            false
        );
        
        return self::fetchOne(
            $farm,
            array(
                'where' => array(
                    'token' => $tokenFilter->filter($token)
                )
            )
        );
    }
}