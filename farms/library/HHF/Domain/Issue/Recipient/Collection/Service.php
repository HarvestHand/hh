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
 * @copyright $Date: 2012-09-11 22:03:28 -0300 (Tue, 11 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Add on model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 576 2012-09-12 01:03:28Z farmnik $
 * @copyright $Date: 2012-09-11 22:03:28 -0300 (Tue, 11 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Issue_Recipient_Collection_Service extends HH_Object_Collection_Service
{   
    public function save($recipients)
    {
        $filter = HHF_Domain_Issue_Recipient::getFilter(
            HHF_Domain_Issue_Recipient::FILTER_NEW,
            array(
                'farm' => $this->_collection->getFarm()
            )
        );

        foreach ($recipients as $recipient) {
            
            $filter->setData($recipient);
            
            if (!$filter->isValid()) {
                
                throw new HH_Object_Exception_Validation(
                    $filter->getMessages()
                );
            }
        }
        
        $originalRecipients = HHF_Domain_Issue_Recipient::fetch(
            $this->_collection->getFarm(),
            array(
                'where' => array(
                    'issueId' => $recipient['issueId']
                )
            )
        );
        
        foreach ($originalRecipients as $originalRecipient) {
            $originalRecipient->delete();
        }
        
        // insert
        foreach ($recipients as $recipient) {
            $recipientObject = new HHF_Domain_Issue_Recipient(
                $this->_collection->getFarm()
            );

            $recipientObject->insert($recipient);
            $this->_collection[] = $recipientObject;
        }
    }
    
    public function getCustomers()
    {
        $customers = array();
        
        foreach ($this->_collection as $recipient) {
            $customersList = $recipient->getService()->getCustomers();
            
            $customers = $customers + $customersList;
        }
        
        return array_values($customers);
    }
    
    public function getEmails()
    {
        $emails = array();
        
        foreach ($this->_collection as $recipient) {
            $recipientList = $recipient->getService()->getEmails();
            
            $emails = array_merge($emails, $recipientList);
        }
        
        // dedupe
        $deduped = array();
        
        foreach ($emails as $key => $email) {
            $found = false;
            
            foreach ($emails as $key2 => $toCheckEmail) {
                if ($key == $key2) {
                    continue;
                }
                
                if ($email[0] == $toCheckEmail[0]) {
                    $found = true;
                    break;
                }
            }
            
            if (!$found) {
                $deduped[] = $email;
            }
        }
        
        return $deduped;
    }
    
    public function toFormArray()
    {
        $recipients = array();
        
        foreach ($this->_collection as $recipient) {
            $name = $recipient['list'];
            
            $params = Zend_Json::decode($recipient['params']);
            
            if (!empty($params)) {
                $name = $name . ':' . implode('|', $params);
            }
            
            $recipients[] = $name;
        }
        
        return $recipients;
    }
}