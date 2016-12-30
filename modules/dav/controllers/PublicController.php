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
 * IndexController
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @category  Core
 * @package
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class PublicController extends HH_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->_helper->layout->disableLayout();
        $this->_noRender = true;

        set_error_handler(array($this, 'exceptionHandler'));
    }

    public function exceptionHandler($errno, $errstr, $errfile, $errline)
    {
        throw new ErrorException($errstr, 0, $errno, $errfile, $errline);
    }

    public function __call($methodName, $args)
    {
        $zendDb = Bootstrap::getZendDb();
        $pdo = $zendDb->getConnection();

        $principalBackend = new Sabre\DAVACL\PrincipalBackend\PDO($pdo);
        $carddavBackend   = new Sabre\CardDAV\Backend\PDO($pdo);

        $nodes = array(
            new Sabre\DAVACL\PrincipalCollection($principalBackend),
        //    new Sabre\CalDAV\CalendarRootNode($authBackend, $caldavBackend),
            new Sabre\CardDAV\AddressBookRoot($principalBackend, $carddavBackend),
        );

        $server = new Sabre\DAV\Server($nodes);
        $server->setBaseUri('/');
        $server->addPlugin(new Sabre\DAV\Browser\Plugin());
        $server->addPlugin(new Sabre\CardDAV\Plugin());
        $server->exec();
    }
}