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
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Sitemap
 *
 * @package   HH_Job 
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Newsletter.php 576 2012-09-12 01:03:28Z farmnik $
 * @copyright $Date: 2012-09-11 22:03:28 -0300 (Tue, 11 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Newsletter extends HH_Job
{
    public function __construct($config = array())
    {
        parent::__construct($config);
    }
    
    public function add(HH_Domain_Farm $farm, $issueId)
    {
        parent::add('newsletter', func_get_args());
    }    
    
    
    public function process(HH_Domain_Farm $farm, $issueId)
    {
        $issue = new HHF_Domain_Issue($farm, $issueId);
        
        if ($issue->isEmpty()) {
            return;
        }
        
        $recipients = $issue->getRecipients();
        
        if (empty($recipients) || !count($recipients)) {
            return;
        }
        
        $layout = new Zend_Layout();
        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
        $layout->setLayout('email');
        $layout->getView()->farm = $farm;
        
        $filter = new HH_Filter_HtmlToText();
        $emailJob = new HH_Job_Email();
        
        foreach ($recipients->getService()->getCustomers() as $customer) {
            
            $emails = $this->_getEmails($customer);
            
            if (empty($emails)) {
                continue;
            }
            
            $layout->content = $issue->getService()->processContent(
                $this->_createIssueVariables($customer, $farm)
            );
            $bodyHtml = $layout->render();
            
            $bodyText = $filter->filter($bodyHtml);
            
            $emailJob->add(
                array($issue->from, $farm->name),
                $emails[0], 
                $issue->title,
                $bodyText,
                $bodyHtml,
                array($issue->from, $farm->name),
                (!empty($emails[1]) ? $emails[1] : null),
                null,
                'farmnik@harvesthand.com',
                'farmnik@harvesthand.com',
                array(
                    array('name' => 'List-Unsubscribe', 'value' => $issue->from)
                )
            );
        }
    }
    
    protected function _createIssueVariables(HHF_Domain_Customer $customer,
        HH_Domain_Farm $farm)
    {
        $customerArray = $customer->toArray();
        
        if ($customerArray['addedDatetime'] instanceof Zend_Date) {
            $customerArray['addedDatetime'] = $customerArray['addedDatetime']
                ->get(Zend_Date::ISO_8601);
        }
        
        if ($customerArray['updatedDatetime'] instanceof Zend_Date) {
            $customerArray['updatedDatetime'] = $customerArray['updatedDatetime']
                ->get(Zend_Date::ISO_8601);
        }
        
        $farmer = $customer->getFarmer();
        
        if ($farmer instanceof HH_Domain_Farmer) {
            $customerArray['userName'] = $farmer['userName'];
        } else {
            $customerArray['userName'] = null;
        }
        
        $variables = array(
            'customer' => $customerArray,
            'farm' => $farm->toArray()
        );
        
        return $variables;
    }
    
    protected function _getEmails($customer)
    {
        $emails = array();
        
        $validateEmail = new Zend_Validate_EmailAddress();
        
        if (!empty($customer['email'])) {
            if ($validateEmail->isValid($customer['email'])) {
                $email = array(
                    $customer['email']
                );

                if (isset($customer['firstName'])) {
                    $name = trim($customer['firstName'] . ' ' . $customer['lastName']);
                } else if (isset($customer['lastName'])) {
                    $name = trim($customer['lastName']);
                }

                if (!empty($name)) {
                    $email[] = $name;
                }

                $emails[] = $email;
            }
        }

        if (!empty($customer['secondaryEmail']) 
            && $customer['email'] != $customer['secondaryEmail']) {
            
            if ($validateEmail->isValid($customer['secondaryEmail'])) {

                $email = array(
                    $customer['secondaryEmail']
                );

                if (isset($customer['secondaryFirstName'])) {
                    $name = trim($customer['secondaryFirstName'] . ' ' . $customer['secondaryLastName']);
                } else if (isset($customer['secondaryLastName'])) {
                    $name = trim($customer['secondaryLastName']);
                }

                if (!empty($name)) {
                    $email[] = $name;
                }

                $emails[] = $email;
            }
        }
        
        return $emails;
    }
}