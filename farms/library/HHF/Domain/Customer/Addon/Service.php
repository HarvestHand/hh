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
 * @copyright $Date: 2012-11-18 22:16:39 -0400 (Sun, 18 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of addon service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 596 2012-11-19 02:16:39Z farmnik $
 * @copyright $Date: 2012-11-18 22:16:39 -0400 (Sun, 18 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Addon_Service extends HH_Object_Service
{
    /**
     *
     * @var HHF_Domain_Customer_Addon
     */
    protected $_object;

    public function save($data)
    {
        if ($this->_object->isEmpty()) {
            $this->_object->insert($data);
            $this->updateInventory();
        } else {
            $updateInventory = false;

            if (array_key_exists('quantity', $data)) {
                if ($data['quantity'] != $this->_object['quantity']) {
                    $updateInventory = $this->_object['quantity'];
                }
            }

            $this->_object->update($data);

            if ($updateInventory) {
                $this->updateInventory($updateInventory);
            }
        }
    }

    /**
     * Update addon inventory
     */
    protected function updateInventory($previousAmount = null)
    {
        $addon = $this->_object->getAddon();
        $inventory = null;

        // update inventory
        if (!$addon->isEmpty() && is_numeric($addon->inventory)) {
            if ($previousAmount === null) {
                $inventory = $addon->inventory - $this->_object['quantity'];
            } else {
                if ($this->_object['quantity'] > $previousAmount) {
                    // reduce diff
                    $diff = $this->_object['quantity'] - $previousAmount;

                    $inventory = $addon['inventory'] - $diff;

                } elseif ($this->_object['quantity'] < $previousAmount) {
                    // add diff
                    $diff = $previousAmount - $this->_object['quantity'];

                    $inventory = $addon['inventory'] + $diff;
                }
            }

            if ($inventory === null) {
                return;
            }

            if ($inventory < 0) {
                $inventory = 0;
            }

            $addon->update(
                array(
                    'inventory' => $inventory
                )
            );
        }
    }

    public function updatePaymentStatus()
    {
        $invoice = $this->_object->getCustomerInvoice();

        if (!($invoice instanceof HHF_Domain_Customer_Invoice)
            || $invoice->isEmpty()) {

            return;
        }

        if ($invoice->paid) {

            if (!$this->_object->paidInFull) {
                $this->_object->update(array('paidInFull' => 1));
            }
        } else if (!$invoice->paid) {

            if ($this->_object->paidInFull) {
                $this->_object->update(array('paidInFull' => 0));
            }
        }
    }

    public function remove()
    {
        // remove related invoices
        $invoices = HHF_Domain_Customer_Invoice::fetchByType(
            $this->_object->getFarm(),
            HHF_Domain_Customer_Invoice_Line::TYPE_ADDON,
            $this->_object['id']
        );

        foreach ($invoices as $invoice) {
            foreach ($invoice->getLines() as $line) {
                if ($line['type'] == HHF_Domain_Customer_Invoice_Line::TYPE_ADDON
                    && $line['referenceId'] == $this->_object['id']) {

                    $line->getService()->remove();
                }
            }
        }

        return parent::remove();
    }
}