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
 * @package
 */

/**
 * Description of Help
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ApplyInvoices.php 596 2012-11-19 02:16:39Z farmnik $
 * @copyright $Date: 2012-11-18 22:16:39 -0400 (Sun, 18 Nov 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_ApplyInvoices extends HH_Tools_Console_Task
{
    public function run()
    {
        if (($pid = $this->_console->isLocked('applyInvoices')) !== false) {
            $this->_console->outputText(
                'Task \'applyInvoices\' locked with a PID of ' . $pid
            );
            return HH_Tools_Console::ERROR_LOCK;
        }

        $this->_console->setLock('applyInvoices');

        try {

            $farms = HH_Domain_Farm::fetch();
            $now = Zend_Date::now();
            $now = $now->getDate()
                ->setTimezone('UTC');
            $now = $now->toString('yyyy-MM-dd');

            foreach ($farms as $farm) {
                $this->_console->outputText('Updating ' . $farm->name);

                $invoices = HHF_Domain_Customer_Invoice::fetch(
                    $farm,
                    array(
                        'where' => array(
                            'appliedToBalance' => 0,
                            'pending' => 0,
                            'dueDate <= ' . Bootstrap::getZendDb()->quote($now)
                        )
                    )
                );

                foreach ($invoices as $invoice) {
                    if ($invoice->getService()->applyToCustomerBalance()) {
                        $this->_console->outputText(
                            'Applied invoice ' . $invoice['id']
                        );
                    }
                }
            }

        } catch (Exception $e) {
            $this->_console->removeLock('applyInvoices');
            throw $e;
        }

        $this->_console->removeLock('applyInvoices');

        return HH_Tools_Console::ERROR_NONE;
    }
}