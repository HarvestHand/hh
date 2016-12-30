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
 * @copyright $Date: 2012-11-25 09:52:43 -0400 (Sun, 25 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_View
 */

/**
 * Validates if something is empty
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: FromRecipients.php 599 2012-11-25 13:52:43Z farmnik $
 * @package   HHF_View
 * @copyright $Date: 2012-11-25 09:52:43 -0400 (Sun, 25 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_View_Helper_FromRecipients extends Zend_View_Helper_Abstract
{

    /**
     * check if var is, or series of vars are, empty
     *
     * @param mixed $var
     * @return boolean
     */
    public function FromRecipients()
    {
        $recipients = array(
            'ALL' => $this->view->translate('All Customers')
        );
        
        // get shares
        $years = array(
            date('Y'),
            date('Y') - 1
        );
        
        $shares = HHF_Domain_Share::fetch(
            $this->view->farm,
            array(
                'sql' => 'SELECT 
                    s.id,
                    s.year,
                    s.name,
                    GROUP_CONCAT(sd.startWeek) as startWeeks,
                    GROUP_CONCAT(sdl.locationId) as locationLimits
                FROM 
                    __SCHEMA__.shares as s
                LEFT JOIN
                    __SCHEMA__.sharesDurations as sd
                ON
                    sd.shareId = s.id
                LEFT JOIN
                    __SCHEMA__.sharesDurationsLocations as sdl
                ON
                    sdl.shareId = s.id
                WHERE
                    s.enabled = 1
                AND
                    year IN(' . date('Y') . ', ' . (date('Y') - 1) . ')
                GROUP BY
                    s.id
                ORDER BY
                    year DESC, name ASC;',
                'columns' => array(
                    '*'
                )
            )
        );
        
        $locations = HHF_Domain_Location::fetch(
            $this->view->farm,
            array(
                'where' => array(
                    'enabled' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );
        
        $yearTargets = array();
        $dateFormatter = new IntlDateFormatter(
            Bootstrap::$locale,
            IntlDateFormatter::LONG,
            IntlDateFormatter::NONE
        );
        
        foreach ($years as $year) {
            $shareGroup = $this->view->translate('%d Shares', $year);
            $locationGroup = $this->view->translate('%d Share Locations', $year);
            $startWeekGroup = $this->view->translate('%d Share Start Week', $year);
            $locationShareGroup = $this->view->translate('%d Share By Location', $year);
            
            $yearTargets[$year] = array(
                $shareGroup => array(),
                $locationGroup => array(),
                $startWeekGroup => array()
            );
            
            foreach ($shares as $share) {
                if ($share['year'] != $year) {
                    continue;
                }
                
                $key = 'SHARE:' . $share['id'] . '|' . $share['year'];
                
                $yearTargets[$year][$shareGroup][$key] = $share['name'];
                
                //locations
                if (empty($share['locationLimits'])) {
                    foreach ($locations as $location) {
                        $key = 'LOCATION:' . $location['id'] . '|' . $year;
                        $name = $location['name'] . ' - ' . $location['city'];
                        
                        $yearTargets[$year][$locationGroup][$key] = $name;

                        $key = 'SHARE:' . $share['id'] . '|' . $share['year'] . '|' . $location['id'];
                        $name = $share['name'] . ': ' . $location['name'] . ' - ' . $location['city'];

                        $yearTargets[$year][$locationShareGroup][$key] = $name;
                    }
                } else {
                    $limitLocations = array_unique(
                        explode(',', $share['locationLimits']), 
                        SORT_NUMERIC
                    );
                    
                    if (!empty($limitLocations)) {
                    
                        foreach ($locations as $location) {
                            if (!in_array($location['id'], $limitLocations)) {
                                continue;
                            }
                            
                            $key = 'LOCATION:' . $location['id'] . '|' . $year;
                            $name = $location['name'] . ' - ' . $location['city'];

                            $yearTargets[$year][$locationGroup][$key] = $name;

                            $key = 'SHARE:' . $share['id'] . '|' . $share['year'] . '|' . $location['id'];
                            $name = $share['name'] . ': ' . $location['name'] . ' - ' . $location['city'];

                            $yearTargets[$year][$locationShareGroup][$key] = $name;
                        }
                    }
                }
                
                // durations
                if (empty($share['startWeeks'])) {
                    continue;
                }
                
                $startWeeks = array_unique(
                    explode(',', $share['startWeeks']), 
                    SORT_NUMERIC
                );

                if (empty($startWeeks)) {
                    continue;
                }
                
                foreach ($startWeeks as $startWeek) {
                    $key = 'STARTWEEK:' . $startWeek . '|' . $year;
                    
                    $date = new DateTime($year . '-W' . sprintf('%02d', $startWeek) . '-1');
                    
                    $name = $this->view->translate(
                        'Week %d (%s)',
                        $startWeek,
                        $dateFormatter->format($date)
                    );
                        
                    $yearTargets[$year][$startWeekGroup][$key] = $name;
                }
            }
        }
        
        foreach ($years as $year) {
            $shareGroup = $this->view->translate('%d Shares', $year);
            $locationGroup = $this->view->translate('%d Share Locations', $year);
            $startWeekGroup = $this->view->translate('%d Share Start Week', $year);
            $locationShareGroup = $this->view->translate('%d Share By Location', $year);
            
            if (!empty($yearTargets[$year][$shareGroup])) {
                $recipients[$shareGroup] = $yearTargets[$year][$shareGroup];
            }
            
            if (!empty($yearTargets[$year][$locationGroup])) {
                $recipients[$locationGroup] = $yearTargets[$year][$locationGroup];
            }
            
            if (count($yearTargets[$year][$startWeekGroup]) > 1) {
                uksort($yearTargets[$year][$startWeekGroup], function($a, $b) {
                    $leftWeek = (int) substr($a, 10);
                    $rightWeek = (int) substr($b, 10);
                    
                    if ($leftWeek < $rightWeek) {
                        return -1;
                    } else if ($leftWeek > $rightWeek) {
                        return 1;
                    }
                    
                    return 0;
                });
                $recipients[$startWeekGroup] = $yearTargets[$year][$startWeekGroup];
            }

            if (!empty($yearTargets[$year][$locationShareGroup])) {
                $recipients[$locationShareGroup] = $yearTargets[$year][$locationShareGroup];
            }
        }
        
        return $this->view->formSelect(
            'recipients[]',
            $this->view->getFormValue('recipients'),
            array(
                'id' => 'recipients',
                'class' => 'required',
                'title' => $this->view->translate('Please select the recipient list')
            ),
            $recipients
        );
    }
}