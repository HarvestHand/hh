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
 * @copyright $Date: 2015-03-05 10:57:43 -0400 (Thu, 05 Mar 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Tools
 */

/**
 * Description of Date
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Date.php 832 2015-03-05 14:57:43Z farmnik $
 * @package   HH_Tools
 * @copyright $Date: 2015-03-05 10:57:43 -0400 (Thu, 05 Mar 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Date
{
    /**
     * Normalize date/time to DB format
     *
     * @param int|string|Zend_Db|null $date
     * @param boolean $utc
     * @return string|null
     */
    public static function dateTimeToDb($date = null, $utc = true)
    {
        if ($date === null) {
            $date = Zend_Date::now();
        }

        if ($date instanceof Zend_Date) {
            /* @var $date Zend_Date */
            if ($utc == true) {
                $date->setTimezone('UTC');
            }
            return $date->get('yyyy-MM-dd HH:mm:ss');
        }

        // is timestamp
        if (is_numeric($date) && $date > 1) {
            $date = new Zend_Date();
            if ($utc) {
                $date->setTimezone('UTC');
            }
            $date->setTimestamp($date);
            return $date->get('yyyy-MM-dd HH:mm:ss');
        }

        // Assume ISO_8601
        if (!empty($date) && is_string($date)) {
            $date = new Zend_Date($date, Zend_Date::ISO_8601);
            return $date->get('yyyy-MM-dd HH:mm:ss');
        }

        return null;
    }

    /**
     * Normalize date/time to ISO 8601 format
     *
     * @param int|string|Zend_Db $date
     * @param boolean $utc
     * @return string|null
     */
    public static function dateTimeToIso($date, $utc = true)
    {
        if ($date instanceof Zend_Date) {
            /* @var $date Zend_Date */
            if ($utc == true) {
                $date->setTimezone('UTC');
            }
            return $date->getIso();
        }

        // is timestamp
        if (is_numeric($date) && $date > 1) {
            $date = new Zend_Date();
            if ($utc) {
                $date->setTimezone('UTC');
            }
            $date->setTimestamp($date);
            return $date->getIso();
        }

        // Assume ISO_8601
        if (!empty($date) && is_string($date) && $date != '0000-00-00 00:00:00') {
            $date = new Zend_Date($date, Zend_Date::ISO_8601);
            return $date->getIso();
        }

        return null;
    }

    /**
     * Normalize date to DB format
     *
     * @param int|string|Zend_Db $date
     * @param boolean $utc
     * @return string|null
     */
    public static function dateToDb($date, $utc = true)
    {
        if ($date instanceof Zend_Date) {
            /* @var $date Zend_Date */
            if ($utc == true) {
                $date->setTimezone('UTC');
            }
            return $date->get('yyyy-MM-dd');
        }

        // is timestamp
        if (is_numeric($date) && $date > 1) {
            $date = new Zend_Date();
            if ($utc) {
                $date->setTimezone('UTC');
            }
            $date->setTimestamp($date);
            return $date->get('yyyy-MM-dd');
        }

        // Assume ISO_8601
        if (!empty($date) && is_string($date)) {
            return $date;
        }

        return null;
    }

    /**
     * Convert ISO Datetime to Zend_Date object in the UTC Timezone
     *
     * @param mixed $date
     * @param string $timezone
     * @return Zend_Date|Null
     */
    public static function isoDatetimeToUTC($date, $timezone = 'UTC')
    {
        if ($date instanceof Zend_Date) {
            /* @var $date Zend_Date */
            $date->setTimezone('UTC');
            return $date;
        }

        // is timestamp
        if (is_numeric($date) && $date > 1) {
            $dateObj = new Zend_Date();
            $dateObj->setTimezone($timezone);
            $dateObj->setTimestamp($date);
            $dateObj->setTimezone('UTC');
            return $dateObj;
        }

        // Assume ISO_8601
        if (!empty($date) && is_string($date)) {
            $dateObj = new Zend_Date();
            $dateObj->setTimezone($timezone);
            $dateObj->set($date, Zend_Date::ISO_8601);
            $dateObj->setTimezone('UTC');
            return $dateObj;
        }

        return null;
    }
    
    /**
     * Number of weeks in year
     * @return int
     */
    public static function weeksInYear($year = null)
    {
        if ($year === null) {
            $year = date('Y');
        }
        
        $date = new Zend_Date(
            array(
                'day' => 31, 
                'month' => 12,
                'year' => $year
            )
        );
        
        $weeks = $date->get(Zend_Date::WEEK);
        
        if ($weeks == 1) {
            $date->sub(1, Zend_Date::WEEK);
            return $date->get(Zend_Date::WEEK);
        }
        
        return $weeks;
    }
    
    /**
     * Compare date by week, including the year
     * 
     * 0 = equal, 1 = later, -1 = earlier
     * 
     * @param Zend_Date $leftDate
     * @param Zend_Date $rightDate
     * @return int
     */
    static function compareYearWeek(Zend_Date $leftDate, Zend_Date $rightDate)
    {
        $leftDateObj = new dateTime('@' . $leftDate->toString('U'));
        $rightDateObj = new dateTime('@' . $rightDate->toString('U'));

        $leftYear = $leftDateObj->format('o');
        $rightYear = $rightDateObj->format('o');

        if ($leftYear == $rightYear) {
            $yearCompare = 0;
        } else if ($leftYear < $rightYear) {
            $yearCompare = -1;
        } else {
            $yearCompare = 1;
        }
        
        if ($yearCompare == 0) {
            return $leftDate->compareWeek($rightDate);
        } else {
            return $yearCompare;
        }
    }
    
    /**
     * Compare date by month, including the year
     * 
     * @param Zend_Date $leftDate
     * @param Zend_Date $rightDate
     * @return int
     */
    static function compareYearMonth(Zend_Date $leftDate, Zend_Date $rightDate)
    {
        $yearCompare = $leftDate->compareYear($rightDate);
        
        if ($yearCompare == 0) {
            return $leftDate->compareMonth($rightDate);
        } else {
            return $yearCompare;
        }
    }
}
