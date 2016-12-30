<?php

/**
 * File Name: Vacation.php
 * @author: Ray Winkelman | raywinkelman@gmail.com
 * Date: 6/18/15
 * Time: 9:30 AM
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
 * @copyright Date: 6/18/15 - 9:30 AM
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HarvestHand
 */
class HHF_Domain_Customer_Vacation extends HHF_Domain_Vacation
{
    public function __construct($data = array()){
        parent::__construct($data);
    }

    public static function fetchWhere(HH_Domain_Farm $farm, $options = array()){
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase($farm) . '
            WHERE
                1 = 1';

        foreach($options as $field => $value){
            $sql .= ' AND ' . $field . ' = ' . $value;
        }

        $rawData = $db->fetchAll($sql);

        $vacations = array();

        foreach($rawData as $row){
            array_push($vacations, new HHF_Domain_Customer_Vacation($row));
        }

        return $vacations;
    }

    public static function deleteWhereCustomerId(HH_Domain_Farm $farm, $cusId){
        $db = self::_getStaticZendDb();

        $sql = 'DELETE FROM
                ' . self::_getStaticDatabase($farm) . '
            WHERE
                customerId = ' . $cusId;

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
        // TODO: Implement getFilter() method.
    }
}