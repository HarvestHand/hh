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
 * @copyright $Date: 2016-07-01 10:27:38 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH
 */

/**
 * Description of Errors
 *
 * @package   HH
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Error.php 984 2016-07-01 13:27:38Z farmnik $
 * @copyright $Date: 2016-07-01 10:27:38 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Error extends HH_Object_Db
{
    protected $_columns = array(
        'id' => true,
        'priority' => true,
        'code' => true,
        'message' => true,
        'file' => true,
        'line' => true,
        'backtrace' => true,
        'context' => true,
        'class' => true,
        'server' => true,
        'post' => true,
        'farmerId' => true,
        'extra' => true,
        'updatedDatetime' => true,
        'addedDatetime' => true
    );

    /**
     * Get data (lazy loader)
     */
    protected function _get()
    {
        if (empty($this->_id)) {
            $this->_setData();
            return;
        }

        $sql = 'SELECT * FROM ' . $this->_getDatabase() .  ' WHERE id = ?';

        $this->_setData(
            $this->_getZendDb()->fetchRow($sql, $this->_id)
        );
    }

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    public function insert($data)
    {
        $db = $this->_getZendDb();

        try {
            $db->insert($this->_getDatabase(), $this->_prepareData($data));
            $data['id'] = $db->lastInsertId();
        } catch (Exception $e) {
            mail('ray@harvesthand.com', 'HH: Error', $e->__toString());
        }

        $this->_setData($data);
    }

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    public function update($data = null)
    {
        if (!empty($this->_id)) {

            if (!$this->_isLoaded) {
                $this->_get();
            }

            $this->_getZendDb()->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array('id = ?' => $this->_id)
            );

            $this->_setData($data, false);
        }
    }

    /**
     * Prepare data to be entered into the database
     *
     * @param array $data Data to prepare
     * @param boolean $insert Is data to be inserted (false is updated)
     * @return array
     */
    protected function  _prepareData($data, $insert = true)
    {
        $extra = array();

        if (isset($data['message']) && $data['message'] instanceof Exception) {
            $message = $data['message'];

            $data['code'] = $message->getCode();
            $data['file'] = $message->getFile();
            $data['line'] = $message->getLine();
            $data['backtrace'] = $message->getTrace();
            $data['class'] = get_class($message);
            $data['message'] = $message->getMessage();

            $previous = $message->getPrevious();

            if ($previous instanceof Exception) {
                $extra['previousException'] = $previous->__toString();
            }
        }

        foreach ($data as $key => $value) {
            if (isset($this->_columns[$key])) {
                if ($key == 'context' && is_array($value)) {

                    $context = array();

                    foreach ($value as $k => $val) {
                        if (is_object($val) && method_exists($val, 'toJson')) {
                            $context[$k] = $val->toJson();
                        } else if (is_object($val)) {
                            $context[$k] = json_encode(print_r($val, 1));
                        } else {
                            $context[$k] = $val;
                        }
                    }

                    $data[$key] = json_encode($context);
                } else if (is_array($value)) {
                    $data[$key] = json_encode($value);
                } else if (is_object($value) && method_exists($value, 'toJson')) {
                    $data[$key] = $value->toJson();
                } else if (is_object($value)) {
                    $data[$key] = print_r($value, 1);
                } else {
                    $data[$key] = $value;
                }
            } else {
                if (is_array($value)) {
                    $extra[$key] = json_encode($value);
                } else if (is_object($value) && method_exists($value, 'toJson')) {
                    $extra[$key] = $value->toJson();
                } else if (is_object($value)) {
                    $extra[$key] = print_r($value, 1);
                } else {
                    $extra[$key] = $value;
                }
                unset($data[$key]);
            }
        }

        if (!empty($extra)) {
            if (!empty($data['extra'])) {
                array_unshift($extra, $data['extra']);
                $data['extra'] = json_encode($extra);
            } else {
                $data['extra'] = json_encode($extra);
            }
        }

        return parent::_prepareData($data, $insert);
    }

    /**
     * Get Zend_Filter_Input for model
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {
    }

    /**
     * Show an appropriate 500 error message
     *
     * @param mixed $error Error data
     * @param boolean $mail Send email about error
     */
    public static function do500($error = array(), $mail = false)
    {
        @ob_end_clean();
        if (PHP_SAPI != 'cli') {

            include __DIR__ . '/../../scripts/500.php';

        } else {

            var_dump($error);

        }

        if ($mail) {
            @mail(
                'farmnik@harvesthand.com',
                'HH: Fatal Error',
                print_r($error, true) . PHP_EOL
                . print_r($_SERVER, true) . PHP_EOL
            );
        }

        die(1);
    }

    /**
     * Handle errors
     *
     * @param int $type Error type
     * @param string $message Error messages
     * @param string $file Error file
     * @param string $line Error file line
     * @param string $context Error context
     */
    public static function errorHandler($type, $message, $file, $line, $context)
    {
        // check for suppressed error and ignore
        if (error_reporting() === 0 || $type == E_STRICT) {
            return;
        }

        if ($type != E_DEPRECATED){
            $developerBreakpoint = 1; //TODO: Remove this when I have time to look into updating the production
            // interpreter.
        }

        $log = Bootstrap::getZendLog();

        $farmerId = (!empty($_SESSION['Zend_Auth']['storage']['id']))
                ? $_SESSION['Zend_Auth']['storage']['id'] : null;

        $additionalData = array(
            'file' => $file,
            'line' => $line,
            'context' => $context,
            'backtrace' => null,
            'server' => $_SERVER,
            'post' => $_POST,
            'farmerId' => $farmerId
        );

        switch($type) {
            case E_WARNING :
            case E_USER_WARNING :
            default :
                $backtrace = debug_backtrace();
                array_shift($backtrace);
                $additionalData['backtrace'] = $backtrace;

                $log->log(
                    $message,
                    4,
                    $additionalData
                );
                break;
            case E_NOTICE :
                $backtrace = debug_backtrace();
                array_shift($backtrace);
                $additionalData['backtrace'] = $backtrace;

                $log->log(
                    $message,
                    5,
                    $additionalData
                );
                break;
            case E_USER_NOTICE :
            case E_STRICT :
            case E_DEPRECATED :
            case E_USER_DEPRECATED :
                $log->log(
                    $message,
                    5,
                    $additionalData
                );
                break;
            case E_USER_ERROR :
                $backtrace = debug_backtrace();
                array_shift($backtrace);
                $additionalData['backtrace'] = $backtrace;

                $log->log(
                    $message,
                    0,
                    $additionalData
                );
                self::do500(func_get_args());
                break;
            case E_RECOVERABLE_ERROR :
                $backtrace = debug_backtrace();
                array_shift($backtrace);
                $additionalData['backtrace'] = $backtrace;

                $log->log(
                    $message,
                    3,
                    $additionalData
                );
                break;
        }
    }

    /**
     * Handle exceptions
     *
     * @param Exception $exception
     * @param int $type
     */
    public static function exceptionHandler($exception, $type = E_USER_ERROR)
    {
        $log = Bootstrap::getZendLog();

        $farmerId = (!empty($_SESSION['Zend_Auth']['storage']['id']))
                ? $_SESSION['Zend_Auth']['storage']['id'] : null;

        $additionalData = array(
            'file' => null,
            'line' => null,
            'context' => null,
            'backtrace' => null,
            'server' => $_SERVER,
            'post' => $_POST,
            'farmerId' => $farmerId
        );

        $log->log($exception, 0, $additionalData);

        switch ($type) {
            case E_ERROR :
            case E_USER_ERROR :
                self::do500($exception);
                break;
        }
    }
}
