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
 * @copyright $Date: 2016-07-01 09:24:44 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Report.php 980 2016-07-01 12:24:44Z farmnik $
 * @copyright $Date: 2016-07-01 09:24:44 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_Report extends HH_Tools_Console_Task
{
    public function run()
    {
        $report = $this->_console->getArgs()->getOption('report');
        $lock = 'report' . ucfirst($report);


        if (($pid = $this->_console->isLocked($lock)) !== false) {
            $this->_console->outputText(
                'Task \'' . $lock . '\' locked with a PID of ' . $pid
            );
            return HH_Tools_Console::ERROR_LOCK;
        }

        $this->_console->setLock($lock);

        try {
            call_user_func(array($this, $report));
        } catch (Exception $e) {
            $this->_console->removeLock($lock);
            throw $e;
        }

        $this->_console->removeLock($lock);

        return HH_Tools_Console::ERROR_NONE;
    }

    protected function vendors()
    {
        $distributors = HH_Domain_Farm::fetch(
            array(
                'where' => array(
                    'FIND_IN_SET(\'' . HH_Domain_Farm::TYPE_DISTRIBUTOR . '\', type) > 0'
                )
            )
        );

        foreach ($distributors as $distributor) {

            $parentNetworks = $distributor->getParentNetworks('APPROVED');
            $updated = $distributor->getPreferences()->getStructure('networkProductsUpdated', 'shares', array());
            $parentNetworksStatus = array();

            foreach ($parentNetworks as $network) {

                $vendor = $network->getRelation();

                $parentNetworksStatus[] = array(
                    'id' => $vendor->id,
                    'name' => $vendor->name,
                    'updated' => (!empty($updated[$vendor->id]) ? $updated[$vendor->id] : null),
                    'status' => $network->status,
                );

                $farmer = $vendor->getPrimaryFarmer();
                $farmerEmail = null;

                if (!empty($farmer->email)) {
                    $farmerEmail = $farmer->email;
                } else if (!empty($vendor->email)) {
                    $farmerEmail = $vendor->email;
                }

                if (empty($farmerEmail)) {
                    continue;
                }

                $purchaseable = HHF_Domain_Addon::fetch(
                    $distributor,
                    array(
                        'where' => array(
                            'vendorId' => $vendor->id,
                            'enabled = 1',
                            '(inventory IS NULL OR inventory != 0)',
                            '(expirationDate IS NULL OR expirationDate > \'' . Zend_Date::now()->setTimezone('UTC')->toString('yyyy-MM-dd') . '\')'
                        )
                    )
                );

                $notPurchaseable = HHF_Domain_Addon::fetch(
                    $distributor,
                    array(
                        'where' => array(
                            'vendorId' => $vendor->id,
                            '(enabled = 0 OR inventory = 0 OR expirationDate <= \'' . Zend_Date::now()->setTimezone('UTC')->toString('yyyy-MM-dd') . '\')'
                        )
                    )
                );

                if (!$purchaseable->count() && !$notPurchaseable->count()) {
                    continue;
                }

                $view = new Zend_View();
                $view->setScriptPath(
                    Bootstrap::$farmRoot . 'modules/shares/views/scripts/'
                );

                $view->purchaseable = $purchaseable;
                $view->notPurchaseable = $notPurchaseable;
                $view->distributor = $distributor;
                $view->vendor = $vendor;
                $view->farmer = $farmer;
                $view->categories = HHF_Domain_Addon_Category::fetchAllForm($distributor);

                $layout = new Zend_Layout();
                $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                $layout->setLayout('email');;
                $layout->content = $view->render('admin/distributor-email-vendor.phtml');

                $email = new HH_Job_Email();
                $email->add(
                    $distributor->getPrimaryFarmer()->email,
                    array(
                        $farmerEmail,
                        $vendor->name
                    ),
                    sprintf(
                        'Please Confirm Your Products With %s',
                        $distributor->name
                    ),
                    null,
                    $layout->render()
                );
            }

            if (!empty($parentNetworksStatus)) {

                $farmer = $distributor->getPrimaryFarmer();
                $farmerEmail = null;

                if (!empty($farmer->email)) {
                    $farmerEmail = $farmer->email;
                } else if (!empty($distributor->email)) {
                    $farmerEmail = $distributor->email;
                }

                if (empty($farmerEmail)) {
                    continue;
                }

                $view = new Zend_View();
                $view->setScriptPath(
                    Bootstrap::$farmRoot . 'modules/shares/views/scripts/'
                );

                $view->parentNetworksStatus = $parentNetworksStatus;
                $view->distributor = $distributor;
                $view->farmer = $farmer;

                $layout = new Zend_Layout();
                $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
                $layout->setLayout('email');;
                $layout->content = $view->render('admin/distributor-email-status.phtml');

                $email = new HH_Job_Email();
                $email->add(
                    'farmnik@harvesthand.com',
                    array(
                        $farmerEmail,
                        $distributor->name
                    ),
                    sprintf(
                        'HarvestHand Product Status Update'
                    ),
                    null,
                    $layout->render()
                );
            }
        }
    }

    protected function billing()
    {
        $farms = HH_Domain_Farm::fetch();
        $db = Bootstrap::getZendDb();

        $html = '';
	$firstOfMonth = date('Y-m-01', mktime(date('H'), date('i'), date('s'), date('n'), date('j') - 7));
	$lastOfMonth = date('Y-m-t', mktime(date('H'), date('i'), date('s'), date('n'), date('j') - 7));

        foreach ($farms as $farm) {

            $html .= '<h1>' . htmlspecialchars($farm['name']) . '</h1>';

            $sql = 'SELECT
                customerId,
                date(MIN(addedDatetime)),
                count(*),
                date(MAX(addedDatetime))
            FROM
                farmnik_hh_' . $farm['id'] . '.customersShares
            WHERE
                addedDatetime BETWEEN \'' . $firstOfMonth . '\' AND \'' . $lastOfMonth . '\'
            GROUP BY
                customerId
            ORDER BY
                customerId';

            $result = $db->fetchAll($sql);

            $html .= PHP_EOL . '<p>Total customers added between ' . $firstOfMonth . ' and ' . $lastOfMonth . ': ' . count($result) . '</p>';
        }

        $layout = new Zend_Layout();
        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
        $layout->setLayout('email');
        $layout->content = $html;

        $email = new HH_Job_Email();
        $email->add(
            'team@harvesthand.com',
            array(
                'team@harvesthand.com'
            ),
            'Membership Billing Report For ' . $firstOfMonth . ' to ' . $lastOfMonth,
            null,
            $layout->render()
        );
    }
}
