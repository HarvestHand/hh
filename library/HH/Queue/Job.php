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
 * @copyright $Date: 2011-10-12 22:39:02 -0300 (Wed, 12 Oct 2011) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Queue
 */

/**
 * Description of Job
 *
 * @package   HH_Queue
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Job.php 334 2011-10-13 01:39:02Z farmnik $
 * @copyright $Date: 2011-10-12 22:39:02 -0300 (Wed, 12 Oct 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Queue_Job extends HH_Queue
{
    public function __construct($config = array())
    {
        $this->setConfig($config);
    }
    
    public function add($job, $params = array())
    {
        $queue = $this->_getZendQueue();
        
        $queue->setOption('name', 'jobs');
        
        $queue->createQueue('jobs');
        
        $queue->send(
            serialize(
                array(
                    'job' => $job,
                    'params' => $params
                )
            )
        );
    }
    
    public static function run()
    {
        $queue = self::_getStaticZendQueue();
        $queue->setOption('name', 'jobs');
        
        $jobs = $queue->receive(100);
        
        foreach ($jobs as $job) {
            $jobArgs = unserialize($job->body);
            
            try {
                
                if ($jobArgs === false) {
                    throw new Exception('Unable to unserialize job');
                }
            
                call_user_func(
                    array('HH_Job', 'run'),
                    $jobArgs['job'],
                    $jobArgs['params']
                );
                
            } catch (Exception $exception) {
                HH_Error::exceptionHandler($exception, E_USER_WARNING);
                
                // re-queue for trying later
                $queue->send($job->body);
            }
            
            $queue->deleteMessage($job);
        }
    }

}