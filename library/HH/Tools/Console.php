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
 * @copyright $Date: 2014-05-15 21:26:35 -0300 (Thu, 15 May 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Console
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Console.php 765 2014-05-16 00:26:35Z farmnik $
 * @copyright $Date: 2014-05-15 21:26:35 -0300 (Thu, 15 May 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console
{
    const ERROR_NONE = 0;
    const ERROR_PARSE = 1;
    const ERROR_GENERAL = 2;
    const ERROR_LOCK = 3;
    
    /**
     * @var Zend_Console_Getopt
     */
    protected $_console;

    protected $_consoleOptions = array(
        'apply-invoices|a' => 'Apply matured forward dated invoices',
        'clear-lock|l=w'   => 'Clear task lock [lock ID]',
        'config|c'         => 'Re-read config',
        'help|h'           => 'Help',
        'import-feeds|i'   => 'Import Google Reader Feeds for Planet HH',
        'import-feed=s'    => 'Import Google Reader Feed for Planet HH',
        'publish|p'        => 'Publish queued up items',
        'reseed-cache|r'   => 'Reseed Cache Items',
        'report=s'         => 'Run Report',
        'run-jobs|j'       => 'Run Jobs',
        'test'             => 'Run a test',
        'upgrade|u'        => 'Run Upgrade Process',
    );
    
    protected $_args = array();
    
    /**
     * Console constructor
     */
    public function __construct()
    {
        $this->_console = new Zend_Console_Getopt($this->_consoleOptions);
    }

    /**
     * Run the console interperter
     *
     * @return int
     */
    public function run()
    {
        try {

            $this->_parseArgs();

        } catch(Zend_Console_Getopt_Exception $e) {
            $this->outputText($e->getMessage() 
                . PHP_EOL . PHP_EOL . $e->getUsageMessage());

            return self::ERROR_PARSE;
        }

        return $this->_dispatch();
    }
    
    /**
     * Output text to the console
     *
     * @param string $text
     * @return null
     */
    public function outputText($text)
    {
        echo $text;

        if (substr($text, -1) != PHP_EOL) {
            echo PHP_EOL;
        }
    }

    /**
     * Get console args interface
     *
     * @return Zend_Console_Getopt
     */
    public function getArgs()
    {
        return $this->_console;
    }

    /**
     * Set task lock
     *
     * @param string $task Task name
     */
    public function setLock($task)
    {
        Bootstrap::getZendCache()
            ->save((string) getmypid(), 'console_locks_' . $task);
    }

    /**
     * Remove task lock
     *
     * @param string $task Task name
     */
    public function removeLock($task)
    {
        Bootstrap::getZendCache()
            ->remove('console_locks_' . $task);
    }

    /**
     * Is task locked
     *
     * @param string $task Task name
     * @return boolean|int False or PID of lock
     */
    public function isLocked($task)
    {
        $cache = Bootstrap::getZendCache();
        if (($lock = $cache->load('console_locks_' . $task)) !== false) {

            if (!empty($lock)) {
                return $lock;
            }

            return false;
        }
        return false;
    }

    /**
     * Parse console arguments
     *
     * @return null
     */
    protected function _parseArgs()
    {
        $this->_console->parse();

        $args = $this->_console->toString();

        if (!empty($args)) {
            if (strpos($args, ' ')) {
                $args = explode(' ', $args);
            } else {
                $args = array($args);
            }

            foreach ($args as $arg) {
                list($key, $value) = explode('=', $arg);
                $this->_args[$key] = $value;
            }
        }
    }

    /**
     * Dispatch all task requests
     *
     * @return int
     */
    protected function _dispatch()
    {
        if (empty($this->_args)) {
            $this->outputText($this->_console->getUsageMessage());

            return self::ERROR_NONE;
        }

        foreach ($this->_args as $arg => $param) {

            $class = 'HH_Tools_Console_Task_' . str_replace(
                ' ',
                '',
                ucwords(str_replace('-', ' ', strtolower($arg)))
            );

            $path = Bootstrap::$library 
                . str_replace('_', '/', $class) . '.php';
            
            if (is_readable($path) === false) {
                continue;
            }

            try {

                $task = new $class($this);
                $result = $task->run();

                if ($result != self::ERROR_NONE) {
                    return $result;
                }

            } catch (Exception $e) {

                $this->outputText($e->__toString());

                return self::ERROR_GENERAL;

            }

        }

        return self::ERROR_NONE;
    }
}
