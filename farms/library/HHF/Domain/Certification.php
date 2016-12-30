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
 * @copyright $Date: 2014-03-21 18:23:48 -0300 (Fri, 21 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Location model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Item.php 731 2014-03-21 21:23:48Z farmnik $
 * @copyright $Date: 2014-03-21 18:23:48 -0300 (Fri, 21 Mar 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Certification
{
    const Chemical_Free = 'Chemical Free';
    const Grassfed = 'Grassfed';
    const Sustainably_Harvested = 'Sustainably Harvested';
    const Wild = 'Wild';
    const Heritage_Breeds = 'Heritage Breeds';
    const ORGANIC = 'ORGANIC';
    const NATURAL = 'NATURAL';
    const CERTIFIED_NATURAL = 'CERTIFIED_NATURAL';
    const TRANSITIONAL = 'TRANSITIONAL';
    const CONVENTIONAL = 'CONVENTIONAL';
    const BIODYNAMIC = 'BIODYNAMIC';
    const GRASS = 'GRASS';
    const SPRAY_FREE = 'SPRAY_FREE';
    const FAIR_TRADE = 'FAIR_TRADE';
    const FREE_RANGE = 'FREE_RANGE';

    public static $certifications = array(
        self::ORGANIC,
        self::BIODYNAMIC,
        self::CERTIFIED_NATURAL,
        self::NATURAL,
        self::GRASS,
        self::TRANSITIONAL,
        self::CONVENTIONAL,
        self::SPRAY_FREE,
        self::FAIR_TRADE,
        self::FREE_RANGE,
        self::Chemical_Free,
        self::Grassfed,
        self::Sustainably_Harvested,
        self::Wild,
        self::Heritage_Breeds
    );

    public static function getSelectOptions() {
        $translate = Bootstrap::getZendTranslate();

        $return = array(
            '' => '',
            self::ORGANIC => $translate->_('Certified Organic'),
            self::BIODYNAMIC => $translate->_('Certified Biodynamic'),
            self::CERTIFIED_NATURAL => $translate->_('Certified Naturally Grown'),
            self::NATURAL => $translate->_('Naturally Grown'),
            self::GRASS => $translate->_('Pastured'),
            self::TRANSITIONAL => $translate->_('Transition to Organic'),
            self::CONVENTIONAL => $translate->_('Non Organic'),
            self::SPRAY_FREE => $translate->_('Spray Free'),
            self::FAIR_TRADE => $translate->_('Certified Fair Trade'),
            self::FREE_RANGE => $translate->_('Certified Free Range'),
            self::Chemical_Free => $translate->_('Chemical Free'),
            self::Grassfed => $translate->_('Grassfed'),
            self::Sustainably_Harvested => $translate->_('Sustainably Harvested'),
            self::Wild => $translate->_('Wild'),
            self::Heritage_Breeds => $translate->_('Heritage Breeds')
        );

        asort($return);

        return $return;
    }
}
