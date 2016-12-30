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
 * @copyright $Date: 2011-09-24 21:50:35 -0300 (Sat, 24 Sep 2011) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Job
 *
 * @package   HH_Job
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Job.php 324 2011-09-25 00:50:35Z farmnik $
 * @copyright $Date: 2011-09-24 21:50:35 -0300 (Sat, 24 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job
{
    protected $_config = array();
    protected static $_staticConfig = array();
    
    
    /**
     * Queue constructor
     */
    public function __construct($config = array())
    {
        $this->setConfig($config);
    }
    
    /**
     * set queue config
     *
     * @param array $config
     */
    public function setConfig($config)
    {
        $this->_config = array_merge(
            $this->_config,
            self::$_staticConfig,
            $config
        );
    }

    /**
     * Set queue config across all to be initialized queue classes
     * 
     * @param array $config
     */
    public static function setStaticConfig($config)
    {
        self::$_staticConfig = $config;
    }
    
    public static function run($job, $params)
    {
        $class = 'HH_Job_' . ucfirst(strtolower($job));
        
        $object = new $class();
        
        call_user_func_array(array($object, 'process'), $params);
        
        unset($object);
    }
    
    public function add($job, $params)
    {
        $queue = new HH_Queue_Job($this->_config);
        $queue->add($job, $params);
    }
}