<?php

/**
 * File Name: VacationOption.php
 * @author: Ray Winkelman | raywinkelman@gmail.com
 * @since 06 22, 2015 - 12:10 PM
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
 * @copyright Date: 6/22/15 - 12:10 PM
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HarvestHand
 **/
class HHF_Domain_Share_VacationOption extends HHF_Object_Db
{
    public function __construct($data = array()){

        foreach($data as $key => $value){
            $this->$key = $value;
        }
    }

    public static function fetchWhere(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                *
            FROM
                ' . self::_getStaticDatabase($farm) .  '
            WHERE
                1 = 1';

        foreach($options as $field => $value){
            $sql .= ' AND '.$field.' = '.$value;
        }

        $rawData = $db->fetchAll($sql);

        $vacations = array();

        foreach ($rawData as $row){
            array_push($vacations, new HHF_Domain_Share_VacationOption($row));
        }

        return $vacations;
    }

    public static function deleteOptionsWhereShareId(HH_Domain_Farm $farm, $shareId){
        $db = self::_getStaticZendDb();
        $db->delete(self::_getStaticDatabase($farm), 'shareId = ' . $shareId);
    }

    public static function getFilter($filter = null, $options = array()){
        // TODO: Implement getFilter() method.
    }

    public function setFarm($farm){
        parent::setFarm($farm);
    }
}