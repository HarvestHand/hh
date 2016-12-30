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
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Tools
 */

/**
 * Description of String
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: String.php 606 2012-12-27 04:25:36Z farmnik $
 * @package   HH_Tools
 * @copyright $Date: 2012-12-27 00:25:36 -0400 (Thu, 27 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_String
{
    public static function convertToCacheSafe($string)
    {
        return preg_replace('/[^a-zA-Z0-9_]/', '_', $string);
    }

    /**
     * generate pronouncable password
     *
     * @param int $length
     * @return string
     */
    public static function generatePassword($length = null)
    {
        $length = ($length) ?: mt_rand(4, 9);

        $password = '';

        $vowels = array(
            'a', 'e', 'i', 'o', 'u',
            'ae', 'ou', 'io', 'ea', 'ou',
            'ia', 'ai', '@', '1', '3', '0', '00'
        );

        $consonants = array(
            'b', 'c', 'd', 'g', 'h',
            'j', 'k', 'l', 'm', 'n',
            'p', 'r', 's', 't', 'u',
            'v', 'w', 'tr', 'cr', 'fr',
            'dr', 'wr', 'pr', 'th', 'ch',
            'ph', 'st', 'sl', 'cl', '$'
        );

        $symbols = array(
            '!', '*'
        );

        for ($i = 0; $i < $length; ++$i) {
            $password .= $consonants[mt_rand(0, 28)] . $vowels[mt_rand(0, 16)];
        }

        if (mt_rand(1, 99) % 2) {
            $password = ucwords($password);
        } elseif (mt_rand(1, 99) % 2) {
            $password = strtoupper($password);
        }

        if (mt_rand(1, 99) % 2) {
            $password = $symbols[mt_rand(0, 1)] . $password;
        } elseif (mt_rand(1, 99) % 2) {
            $password = substr($password, 0, $length - 1) . $symbols[mt_rand(0, 1)];
        }

        if (mt_rand(1, 99) % 2) {
            $number = mt_rand(0, 99);

            $password = substr($password, 0, $length - strlen($number)) . $number;
        }

        return substr($password, 0, $length);
    }
}