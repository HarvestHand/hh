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
 * @copyright $Date: 2012-06-19 22:17:05 -0300 (Tue, 19 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of transaction service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 554 2012-06-20 01:17:05Z farmnik $
 * @copyright $Date: 2012-06-19 22:17:05 -0300 (Tue, 19 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Customer_Service extends HH_Object_Service
{
    /**
     * @var HHF_Domain_Customer
     */
    protected $_object;
    
    public function remove()
    {
        // delete references to customer 
        $tables = array(
            'Customer_Addon',
            'Customer_Balance',
            'Customer_Invoice',
            'Customer_Share',
            'Log',
            'Preference',
            'Transaction'
        );
        
        $database = Bootstrap::getZendDb();
        $database->beginTransaction();
        
        try {
            foreach ($tables as $table) {
                $object = 'HHF_Domain_' . $table;

                $objects = $object::fetch(
                    $this->_object->getFarm(),
                    array(
                        'where' => array(
                            'customerId' => $this->_object->id
                        )
                    )
                );

                foreach ($objects as $object) {
                    $object->getService()->remove();
                }
            }

            $farmer = $this->_object->getFarmer();
            if ($farmer instanceof HH_Domain_Farmer) {

                // post comments
                $postComments = HHF_Domain_Post_Comment::fetch(
                    $this->_object->getFarm(), 
                    array(
                        'where' => array(
                            'farmerId' => $farmer->id,
                            'farmerRole' => HH_Domain_Farmer::ROLE_MEMBER
                        )
                    )
                );

                foreach ($postComments as $postComment) {
                    $postComment->getService()->remove();
                }

                $farmer->getService()->remove();
            }
         
            // then delete actual customer
            $this->_object->delete();
            
            $database->commit();
        } catch (Exception $exception) {
            $database->rollBack();
            
            throw $exception;
        }
    }
    
    public function payAllOpenInvoices()
    {
        if ($this->_object['balance'] > 0) {
            
            // check for unapplied transactions
            $transactions = HHF_Domain_Transaction::fetch(
                $this->_object->getFarm(), 
                array(
                    'where' => array(
                        'customerId' => $this->_object['id'],
                        'remainingToApply > 0'
                    )
                )
            );
            
            if ($transactions->count() > 0) {
                throw new HHF_Domain_Customer_Exception_TransactionsToApply();
            }
            
            $invoices = HHF_Domain_Customer_Invoice::fetch(
                $this->_object->getFarm(),
                array(
                    'where' => array(
                        'customerId' => $this->_object['id'],
                        'appliedToBalance' => 1,
                        'paid' => 0
                    )
                )
            );

            foreach ($invoices as $invoice) {
                $invoice->getService()->issueTransaction();
            }
        }
    }
}
