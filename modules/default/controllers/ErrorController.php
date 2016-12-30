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
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */

/**
 * Description of ErrorController
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @category  Core
 * @package   
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class ErrorController extends HH_Controller_Action
{
    public function init()
    {
        parent::init();
    }

    public function errorAction()
    {
        $errors = $this->_request->getParam('error_handler');

        switch ($errors->type) {
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_CONTROLLER:
            case Zend_Controller_Plugin_ErrorHandler::EXCEPTION_NO_ACTION:
                // 404 error -- controller or action not found
                $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');

                // ... get some output to display...
                $this->render('404');
                break;
            default:
                // application error; display error page, but don't change status code
                $this->getResponse()->setRawHeader(
                    'HTTP/1.0 500 Internal Server Error'
                );

                HH_Error::exceptionHandler(
                    $errors['exception'],
                    E_USER_WARNING
                );
                $this->view->exception = $errors['exception'];
                $this->render('500');
                break;
        }
    }
}