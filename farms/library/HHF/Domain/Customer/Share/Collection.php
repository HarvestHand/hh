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
 * @copyright $Date: 2012-06-09 12:24:19 -0300 (Sat, 09 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Share model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Collection.php 543 2012-06-09 15:24:19Z farmnik $
 * @copyright $Date: 2012-06-09 12:24:19 -0300 (Sat, 09 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Share_Collection extends HHF_Object_Collection_Db
{
    public static function fetchStats(HH_Domain_Farm $farm, $options = array())
    {
        $compiled = array();
        
        $where = array();
        
        if (!empty($options['year'])) {
            $where['year'] = $options['year'];
        }
        
        $shareStats = self::fetch(
            'HHF_Domain_Customer_Share',
            array(
                'columns' => array(
                    'shareId',
                    'shareDurationId',
                    'shareSizeId',
                    'sum(quantity) as total'
                ),
                'where' => $options,
                'groupBy' => array(
                    'shareId',
                    'shareDurationId',
                    'shareSizeId'
                ),
                'orderBy' => array(
                    'shareId',
                    'shareDurationId',
                    'shareSizeId'
                )
            ),
            $farm
        );
        
        foreach ($shareStats as $stats) {
            if (!array_key_exists($stats['shareId'], $compiled)) {
                $compiled[$stats['shareId']] = array(
                    'total' => $stats['total'],
                    'share' => new HHF_Domain_Share(
                        $farm,
                        $stats['shareId']
                    ),
                    'sizes' => array(),
                    'durations' => array()
                );
            } else {
            
                $compiled[$stats['shareId']]['total'] += $stats['total'];
            }
            
            if (
                !array_key_exists(
                    $stats['shareDurationId'],
                    $compiled[$stats['shareId']]['durations'])
                ) {
                
                $compiled[$stats['shareId']]['durations'][$stats['shareDurationId']] = array(
                    'duration' => $compiled[$stats['shareId']]['share']
                        ->getDurationById($stats['shareDurationId']),
                    'total' => $stats['total']
                );
            } else {
                $compiled[$stats['shareId']]['durations'][$stats['shareDurationId']]['total'] 
                    += $stats['total'];
            }
            
            if (
                !array_key_exists(
                    $stats['shareSizeId'],
                    $compiled[$stats['shareId']]['sizes'])
                ) {
                
                $compiled[$stats['shareId']]['sizes'][$stats['shareSizeId']] = array(
                    'size' => $compiled[$stats['shareId']]['share']
                        ->getSizeById($stats['shareSizeId']),
                    'total' => $stats['total']
                );
            } else {
                $compiled[$stats['shareId']]['sizes'][$stats['shareSizeId']]['total'] 
                    += $stats['total'];
            }
        }
        
        return $compiled;
    }
}