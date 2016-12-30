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
 * @copyright $Date: 2012-03-14 15:42:47 -0300 (Wed, 14 Mar 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Filter
 */

/**
 * Description of HtmlToText
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: HtmlToText.php 469 2012-03-14 18:42:47Z farmnik $
 * @package   HH_Filter
 * @copyright $Date: 2012-03-14 15:42:47 -0300 (Wed, 14 Mar 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Filter_HtmlToText implements Zend_Filter_Interface
{    
    /**
     * @see lib/Zend/Filter/Zend_Filter_Interface#filter()
     */
    function filter($value)
    {
        if (empty($value)) {
            return $value;
        }

        $file = tempnam('/tmp', 'html');

        if ($file === false) {
            return $value;
        }
        
        $res = file_put_contents($file, $value);

        if ($res !== false) {
            $res = `/usr/bin/elinks -no-home 1 -dump-charset utf8 -dump-width 78 -dump 1 $file`;

            if (!empty($res)) {
                return trim($res);
            }

        }
    }
}