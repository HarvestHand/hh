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
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Controller
 */

/**
 * Description of Plugin
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Plugin.php 302 2011-08-03 22:26:55Z farmnik $
 * @copyright $Date: 2011-08-03 19:26:55 -0300 (Wed, 03 Aug 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Controller
 */
class HH_Controller_Plugin extends Zend_Controller_Plugin_Abstract
{
    /**
     * Timer data
     *
     * @var array
     */
    protected $_timer = array();

    protected $_memory = array();

    /**
     * Plugin constructor
     */
    public function  __construct()
    {

    }

    /**
     * Called after Zend_Controller_Router exits.
     *
     * @param Zend_Controller_Request_Abstract $request
     */
    public function  routeShutdown(Zend_Controller_Request_Abstract $request)
    {
        if ($request->getControllerName() != 'service') {
            Bootstrap::get('Zend_Session');
        }
    }

    public function dispatchLoopShutdown()
    {
        if (Bootstrap::$env == 'development') {

            $timerEnd = microtime(true) * 1000;

            $peakMemory = memory_get_peak_usage(true);

            $memory = round(
                $peakMemory / 1048576,
                2
            );

            $memoryDispatch = round(
                ($peakMemory - Bootstrap::$development['memoryBaseLine']) / 1048576,
                2
            );

            $message = new Zend_Wildfire_Plugin_FirePhp_TableMessage('Memory');
            $message->setBuffered(true);
            $message->setHeader(
                array('Peak', 'Dispatch')
            );
            $message->addRow(
                array($memory . ' MB', $memoryDispatch . ' MB')
            );
            $message->setOption('includeLineNumbers', false);
            Zend_Wildfire_Plugin_FirePhp::getInstance()->send($message);

            $time = round(
                $timerEnd - Bootstrap::$development['timer'],
                2
            );

            $timeDispatch = round(
                $timerEnd - Bootstrap::$development['timerPreDispatch'],
                2
            );

            $timerData = new Zend_Session_Namespace('HH_Timer', false);

            if (isset($_GET['RESET_TIMER'])) {
                $timerData->data[$_SERVER['SCRIPT_URI']] = array();
            }
            $timerData->data[$_SERVER['SCRIPT_URI']][] = $time;

            $avg = round(
                array_sum($timerData->data[$_SERVER['SCRIPT_URI']])
                    / count($timerData->data[$_SERVER['SCRIPT_URI']]),
                2
            );

            $data = array(
                $time,
                $timeDispatch,
                $avg,
                max($timerData->data[$_SERVER['SCRIPT_URI']]),
                min($timerData->data[$_SERVER['SCRIPT_URI']]),
                count($timerData->data[$_SERVER['SCRIPT_URI']])
            );

            $message = new Zend_Wildfire_Plugin_FirePhp_TableMessage('Timer');
            $message->setBuffered(true);
            $message->setHeader(
                array('Time', 'Dispatch', 'Avg', 'Max', 'Min', 'Runs')
            );
            $message->addRow($data);
            $message->setOption('includeLineNumbers', false);
            Zend_Wildfire_Plugin_FirePhp::getInstance()->send($message);
        }
    }
}