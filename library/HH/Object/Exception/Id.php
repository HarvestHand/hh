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
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */

/**
 * Description of Id
 *
 * @package   HH_Object
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Id.php 323 2011-09-22 22:22:20Z farmnik $
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Object_Exception_Id extends Exception
{
    public function __construct($message = 'Primary key not defined', 
        $code = null, $previous = null)
    {
        parent::__construct($message, $code, $previous);
    }
}