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
 * @copyright $Date: 2015-06-11 14:26:42 -0300 (Thu, 11 Jun 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ClearApc.php 872 2015-06-11 17:26:42Z farmnik $
 * @copyright $Date: 2015-06-11 14:26:42 -0300 (Thu, 11 Jun 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_ClearApc extends HH_Tools_Console_Task
{
    public function run()
    {
        apc_clear_cache();

        $this->_console->outputText("APC Cache Cleared!");
        $this->_console->outputText(HH_Tools_String::generatePassword() . PHP_EOL);
        return HH_Tools_Console::ERROR_NONE;
    }
}