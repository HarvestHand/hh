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
 * @copyright $Date: 2012-07-31 15:25:16 -0300 (Tue, 31 Jul 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of invoice service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 561 2012-07-31 18:25:16Z farmnik $
 * @copyright $Date: 2012-07-31 15:25:16 -0300 (Tue, 31 Jul 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Invoice_Line_Service extends HH_Object_Service
{
    /**
     * @var HHF_Domain_Customer_Invoice_Line 
     */
    protected $_object;
    
    public function remove()
    {
        $lines = $this->_object->getInvoice()->getLines();
        
        $linesRemaining = array();
        $location = null;
        
        foreach ($lines as $key => $line) {
            if ($line['id'] == $this->_object['id']) {
                $location = $key;
                continue;
            }
            
            if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_DELIVERY) {
                continue;
            }
            
            $linesRemaining[] = $line;
        }
        
        if (!empty($linesRemaining)) {
            // remaining lines.  delete and recalc invoice
            $this->_object->delete();
            
            /* @var $lines HHF_Object_Collection_Db */
            unset($lines[$location]);
            
            $this->_object->getInvoice()->getService()->recalculate();
        } else {
            $this->_object->getInvoice()->getService()->remove();
        }
    }
}