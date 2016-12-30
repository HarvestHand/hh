<?php

/**
 * File Name: Notes.php
 * @author: Ray Winkelman | raywinkelman@gmail.com
 * @since 07 29, 2015 - 8:45 AM
 * @version 1.00
 *
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
 * @copyright Date: 7/29/15 - 8:45 AM
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HarvestHand
 */
class HHF_Domain_Customer_Share_Note extends HHF_Object_Db
{
    /**
     * Get customer share order
     *
     * @return HHF_Domain_Customer_Share
     */
    public function getCustomerShare()
    {
        if (!$this->isEmpty() && !empty($this->customerShareId)) {

            return HHF_Domain_Customer_Share::singleton(
                $this->_farm,
                $this->customerShareId
            );
        }
    }

    public function setFarm($farm){
        parent::setFarm($farm);
    }

    public static function deleteWhereId($farm, $id){
        $db = self::_getStaticZendDb();

        $sql = 'DELETE FROM
                ' . self::_getStaticDatabase($farm) . '
            WHERE
                id = ' . $id;

        return $db->query($sql);
    }
    /**
     * Get Zend_Filter_Input for object
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array()){

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        $inputFilter = new Zend_Filter_Input(array(
                 'note' => array(
                     new Zend_Filter_StringTrim(),
                     new Zend_Filter_Null()
                 ),
                 'week' => array(
                     new Zend_Filter_StringTrim(),
                     new Zend_Filter_Null()
                 )
             ), array(
                 'week' => array(
                     new Zend_Validate_StringLength(7),
                     Zend_Filter_Input::PRESENCE    => Zend_Filter_Input::PRESENCE_REQUIRED,
                     Zend_Filter_Input::ALLOW_EMPTY => false,
                     Zend_Filter_Input::MESSAGES    => array(
                         $translate->_('A valid week is required')
                     )
                 ),
                 'note' => array(
                     new Zend_Validate_StringLength(0, 255),
                     Zend_Filter_Input::PRESENCE    => Zend_Filter_Input::PRESENCE_REQUIRED,
                     Zend_Filter_Input::ALLOW_EMPTY => false,
                     Zend_Filter_Input::MESSAGES    => array(
                         $translate->_('A valid note is required')
                     )
                 )
             ), null, array(
                 Zend_Filter_Input::MISSING_MESSAGE   => $translate->_("'%field%' is required"),
                 Zend_Filter_Input::NOT_EMPTY_MESSAGE => $translate->_("'%field%' is required"),
             ));

        return $inputFilter;
    }
}
