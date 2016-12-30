<?php
/**
 * File Name: Vacation.php
 * @author: Ray Winkelman | raywinkelman@gmail.com
 * Date: 6/18/15
 * Time: 9:34 AM
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
 * @copyright Date: 6/18/15 - 9:34 AM
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HarvestHand
 */

/**
 * Short description of class HHF_Domain_Vacation
 *
 * @abstract
 * @access public
 * @author Ray Winkelman
 * @since June 17th, 2015
 * @version 1.00
 */
abstract class HHF_Domain_Vacation extends HHF_Object_Db
{
    // --- OPERATIONS ---
    public function __construct($data = array())
    {
        foreach ($data as $key => $val) {
            $this->$key = $val;
        }
    }

    public function setStartWeek($param)
    {
        if(!empty($this->endWeek)){
            if($this->_validateRange($param, $this->endWeek)){
                $this->startWeek = $param;
            } else {
                return false;
            }
        } else {
            $this->startWeek = $param;
        }
        return true;
    }

    public function setEndWeek($param)
    {
        if(!empty($this->startWeek)){
            if($this->_validateRange($this->startWeek, $param)){
                $this->endWeek = $param;
            } else {
                return false;
            }
        } else {
            $this->endWeek = $param;
        }
        return true;
    }

    public function setFarm($farm){
        parent::setFarm($farm);
    }

    // PRIVATE OPERATIONS
    private function _validateRange($start, $end)
    {
        list($startYear, $startWeek) = explode("W", $start);
        list($endYear, $endWeek) = explode("W", $end);

        if(($startYear <= $endYear) || (($startYear == $endYear) && ($startWeek <= $endWeek))){
            return true;
        } else {
            return false;
        }
    }

} /* end of abstract class HHF_Domain_Vacation */

?>