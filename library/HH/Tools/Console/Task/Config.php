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
 * @copyright $Date: 2012-06-26 14:10:17 -0300 (Tue, 26 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Config.php 555 2012-06-26 17:10:17Z farmnik $
 * @copyright $Date: 2012-06-26 14:10:17 -0300 (Tue, 26 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_Config extends HH_Tools_Console_Task
{
    public function run()
    {
        $url = 'http://www.' . Bootstrap::$rootDomain 
            . '/service/default/flush-config?' 
            . http_build_query(
                array('key' => Bootstrap::getZendConfig()->resources->crypto->key)
            );
        
        $result = json_decode(file_get_contents($url), JSON_FORCE_OBJECT);
        
        if (!$result['error']) {
        
            $this->_console->outputText('Reloaded config');

            return HH_Tools_Console::ERROR_NONE;
        } else {
            $this->_console->outputText('Failed to reloaded config');

            return HH_Tools_Console::ERROR_GENERAL;
        }
    }
}