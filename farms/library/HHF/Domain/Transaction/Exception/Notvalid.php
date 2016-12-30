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
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Transaction validation exception
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Notvalid.php 518 2012-04-25 02:25:26Z farmnik $
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Transaction_Exception_Notvalid extends Exception
{
    public $data;
    
    public function __construct($data)
    {
        $this->data = $data;
        
        parent::__construct(
            'IPN data did not validate: ' . print_r($data, 1)
        );
    }
}
