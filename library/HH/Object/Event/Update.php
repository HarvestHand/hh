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
 * @copyright $Date: 2012-09-04 22:17:37 -0300 (Tue, 04 Sep 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */

/**
 * Description of Insert
 *
 * @package   HH_Object
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Update.php 574 2012-09-05 01:17:37Z farmnik $
 * @copyright $Date: 2012-09-04 22:17:37 -0300 (Tue, 04 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Object_Event_Update extends HH_Observer_Event
{

    /**
     * Insert constructor
     */
    public function __construct($preEventData = array())
    {
        parent::__construct('UPDATE', $preEventData);
    }

}