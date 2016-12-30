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
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Tools
 */

/**
 * Description of Countries
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Countries.php 302 2011-08-03 22:26:55Z farmnik $
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Tools
 */
class HH_Tools_Countries
{
    /**
     * Get raw JSON subdivisions by country
     *
     * @param string $country ISO country code
     * @return string JSON
     */
    public static function getRawSubdivisions($country)
    {
        $file = dirname(__FILE__) .
            '/Countries/Data/' .
            strtoupper($country) .
            '/subdivisions.json';

        $json = @file_get_contents($file);

        if ($json !== false) {
            return $json;
        } else {
            return '{}';
        }
    }

    /**
     * Get subdivisions by country
     * 
     * @param string $country ISO country code
     * @return array
     */
    public static function getSubdivisions($country)
    {
        $json = self::getRawSubdivisions($country);

        return json_decode($json, true);
    }

    /**
     * Get raw JSON UN/Locodes by country and subdivision
     *
     * @param string $country ISO country code
     * @param string $subdivision ISO subdivision code
     * @return string JSON
     */
    public static function getRawUnlocodes($country, $subdivision = null)
    {

        if (strlen($subdivision) > 3 || $subdivision === null) {
            $subdivision = '_';
        }

        $path = dirname(__FILE__) . 
            '/Countries/Data/' .
            strtoupper($country) .
            '/unlocode/';
        
        $file = $path . $subdivision . '.json';

        $json = @file_get_contents($file);

        if ($json !== false) {
            return $json;
        } else if ($subdivision != '_') {

            $path = dirname(__FILE__) . 
                '/Countries/Data/' .
                strtoupper($country) .
                '/unlocode/';
            
            $file = $path . '_.json';

            $json = @file_get_contents($file);
            if ($json === false) {
                return '{}';
            }
            return $json;
        } else {
            return '{}';
        }
    }

    /**
     * Get UN/Locodes by country and subdivision
     *
     * @param string $country ISO country code
     * @param string $subdivision ISO subdivision code
     * @return array
     */
    public static function getUnlocodes($country, $subdivision = null)
    {
        return json_decode(
            self::getRawUnlocodes($country, $subdivision),
            true
        );
    }

    /**
     * Get default timezone by country (optional subdivision)
     *
     * @param string $country ISO country code
     * @param string $subdivision ISO subdivision code
     * @return string
     */
    public static function getTimezone($country, $subdivision = null)
    {
        $file = dirname(__FILE__) .
            '/Countries/Data/' .
            strtoupper($country) .
            '/tz.json';

        $json = file_get_contents($file);

        if ($json !== false) {
            $tz = json_decode($json, true);

            if (empty($subdivision)) {
                return $tz['_'];
            } else {
                return $tz[$subdivision];
            }
            
        } else {
            return '';
        }
    }
}