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
 * @copyright $Date: 2015-06-05 13:26:05 -0300 (Fri, 05 Jun 2015) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Service
 */

/**
 * Description of Error
 *
 * @package   HH_Service
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Exception.php 857 2015-06-05 16:26:05Z farmnik $
 * @copyright $Date: 2015-06-05 13:26:05 -0300 (Fri, 05 Jun 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Service_Facebook_Exception extends Exception
{
    protected $_type;

    /**
     * Error constructor
     */
    public function __construct($message, $type, $code = "N/A", $previous = null)
    {
        $this->_type = $type;

        parent::__construct($message, $code, $previous);
    }

    public function getType()
    {
        return $this->_type;
    }
}