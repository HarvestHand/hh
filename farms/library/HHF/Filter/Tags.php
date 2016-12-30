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
 * @copyright $Date: 2011-10-24 22:14:03 -0300 (Mon, 24 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Farm_Filter
 */

/**
 * 
 *
 * @package   HH_Farm_Filter
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Tags.php 341 2011-10-25 01:14:03Z farmnik $
 * @copyright $Date: 2011-10-24 22:14:03 -0300 (Mon, 24 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Filter_Tags implements Zend_Filter_Interface
{
    public function filter($value)
    {
        if (strpos($value, ',') !== false) {
            $tags = explode(',', $value);
            
            foreach ($tags as $key => $tag) {
                $tags[$key] = trim($tag);
                
                if (empty($tags[$key])) {
                    unset($tags[$key]);
                } else {
                    $tags[$key] = ucwords(
                        strtolower(
                            substr($tags[$key], 0, 150)
                        )
                    );
                }
            }
            
            if (!empty($tags)) {
                $tags = array_unique($tags);
            }
            
            return array_values($tags);
            
        } else {
            $value = trim($value);
            
            if (!empty($value)) {
                $value = array($value);
            } else {
                $value = array();
            }
        }

        return $value;
    }
}