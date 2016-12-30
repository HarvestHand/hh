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
 * @package
 */

/**
 * Description of AdminController
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class ServiceController extends HHF_Controller_Action
{
    public function  init()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();
        parent::init();
    }


    public function indexAction()
    {

    }

    public function ipnAction()
    {
        $this->_helper->viewRenderer->setNoRender(true);
        $this->_helper->layout->disableLayout();

        $ipn = new HH_Service_Paypal_Ipn($this->farm, $_POST);

        $ipn->mapToTransaction();
    }

    public function loginrelayAction()
    {
        $idEncoded = $this->_request->getParam('i');

        if (empty($idEncoded)) {
            $this->_redirect(
                'http://www.' . Bootstrap::$rootDomain . '/',
                array('exit' => true)
            );
            return;
        }

        try {
            $id = HH_Crypto::decrypt($idEncoded);

            if (!preg_match('/^[[:print:]]+$/', $id)) {
                $this->_redirect(
                    'http://www.' . Bootstrap::$rootDomain . '/',
                    array('exit' => true)
                );
                return;
            }

            if (!empty($id) && ((isset($_COOKIE['HH']) && $id != $_COOKIE['HH']) || !isset($_COOKIE['HH']))) {
                Zend_Session::setId($id);
                Bootstrap::get('Zend_Session');

                $dataStore = new Zend_Session_Namespace('session');
                if (!isset($dataStore->transfer) || $dataStore->transfer != $id) {
                    Zend_Session::destroy();

                } else {
                    unset($dataStore->transfer);

                    $farmer = HH_Domain_Farmer::getAuthenticated();

                    if ($farmer instanceof HH_Domain_Farmer) {
                        $this->_redirect(
                            $farmer->getFarm()->getBaseUri() . 'admin',
                            array('exit' => true)
                        );
                    }
                }
            }

        } catch (Exception $exception) {
            unset($exception);
        }

        $this->_redirect(
            'http://www.' . Bootstrap::$rootDomain . '/',
            array('exit' => true)
        );
    }
}