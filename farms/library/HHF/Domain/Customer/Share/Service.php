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
 * @copyright $Date: 2012-08-01 08:42:51 -0300 (Wed, 01 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of share service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 562 2012-08-01 11:42:51Z farmnik $
 * @copyright $Date: 2012-08-01 08:42:51 -0300 (Wed, 01 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Share_Service extends HH_Object_Service
{
    /**
     * @var HHF_Domain_Customer_Share
     */
    protected $_object;
    
    public function remove()
    {
        // remove related invoices
        $invoices = HHF_Domain_Customer_Invoice::fetchByType(
            $this->_object->getFarm(),
            HHF_Domain_Customer_Invoice_Line::TYPE_SHARE,
            $this->_object['id']
        );
        
        foreach ($invoices as $invoice) {
            foreach ($invoice->getLines() as $line) {
                if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_SHARE
                    && $line['referenceId'] == $this->_object['id']) {
                    
                    $line->getService()->remove();
                }
            }
        }
        
        return parent::remove();
    }
    
    public function updatePaymentStatus()
    {
        $invoices = $this->_object->getCustomerInvoices();
        
        if ($invoices === null) {
            return;
        }
        
        $paid = 1;
        
        foreach ($invoices as $invoice) {
            if (!$invoice->paid) {
                --$paid;
            }
        }
        
        if ($this->_object->paidInFull == $paid) {
            // already up-to-date
            return;
        }
        
        if ($paid > 0) {
            if (!$this->_object->paidInFull) {
                $this->_object->update(array('paidInFull' => 1));
            }
        } else {
            if ($this->_object->paidInFull) {
                $this->_object->update(array('paidInFull' => $paid));
            }
        }
    }
}