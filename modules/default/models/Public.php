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
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */

/**
 * Description of Model_Public
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @category  Core
 * @package   
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Model_Public
{
    const VALIDATE_USERNAME = 'username';
    const VALIDATE_SUBDOMAIN = 'subdomain';
    const GET_SUBDIVISIONS = 'subdivision';
    const GET_UNLOCODES = 'unlocodes';

    /**
     * detect ajax validation to bedone on signup form
     *
     * @return string
     */
    public static function detectSignupAjaxVidationType()
    {
        if (!empty($_GET['farmer']['userName'])) {
            return self::VALIDATE_USERNAME;
        } else if (!empty($_GET['farm']['subdomain'])) {
            return self::VALIDATE_SUBDOMAIN;
        } else if (!empty($_GET['country']) && !empty($_GET['subdivision']) && !empty($_GET['unlocode'])) {
            return self::GET_UNLOCODES;
        } else if (!empty($_GET['country']) && !empty($_GET['subdivisions'])) {
            return self::GET_SUBDIVISIONS;
        }
    }

    /**
     * Extract geo info from cookie and place in $_GET super global
     */
    public static function explodeGeoInformation()
    {
        if (!empty($_COOKIE['geo'])) {
            if (empty($_GET['farm'])) {
                $_GET['farm'] = array();
            }

            list($_GET['farm']['country'], $_GET['farm']['state']) = explode('|', $_COOKIE['geo']);

            if (is_numeric($_GET['farm']['state'])) {
                $_GET['farm']['state'] = (int) $_GET['farm']['state'];
            }
        }
    }
}