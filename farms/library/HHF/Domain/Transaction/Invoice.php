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
 * @copyright $Date: 2015-07-10 00:31:24 -0300 (Fri, 10 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * File model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Invoice.php 892 2015-07-10 03:31:24Z farmnik $
 * @copyright $Date: 2015-07-10 00:31:24 -0300 (Fri, 10 Jul 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Transaction_Invoice extends HHF_Object_Db
{
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

    function getWhereInvoice($id){
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase() . '
                WHERE invoiceId = '.$id;

        $bind = array();

        $result = $db->fetchAll($sql, $bind);

        return $result;
    }
}
