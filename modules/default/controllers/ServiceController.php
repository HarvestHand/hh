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
 * ServiceController
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @category  Core
 * @package   
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class ServiceController extends HH_Controller_Action
{
    public function init()
    {
        parent::init();
    }

    public function indexAction()
    {
    }
    
    public function syncAction()
    {
        $this->_helper->layout->disableLayout();
        $this->setNoRender();
        
        $this->_response->setHeader('Content-Type', 'image/gif');

        $this->_response->setBody(
            base64_decode('R0lGODlhAQABAIAAAP///////yH5BAEKAAEALAAAAAABAAEAAAICTAEAOw==')
        );
        
        $idEncoded = $this->_request->getParam('i');
        
        if (empty($idEncoded)) {
            return;
        }
        
        try {
            $id = HH_Crypto::decrypt($idEncoded);
            
            if (!preg_match('/^[[:print:]]+$/', $id)) {
                return;
            }
            
            if (!empty($id) && isset($_COOKIE['HH']) && $id != $_COOKIE['HH']) {
                Zend_Session::setId($id);
                Bootstrap::get('Zend_Session');
                
                $dataStore = new Zend_Session_Namespace('session');
                if (!isset($dataStore->transfer) || $dataStore->transfer != $id) {
                    Zend_Session::destroy();
                } else {
                    unset($dataStore->transfer);
                }
            }
            
        } catch (Exception $exception) {
            unset($exception);
        }
    }
    
    public function flushConfigAction()
    {
        $this->_helper->layout->disableLayout();
        $this->setNoRender();
        
        $key = $this->_getParam('key');
        
        if ($key == Bootstrap::getZendConfig()->resources->crypto->key) {
        
            apc_delete('HH_Config');

            Bootstrap::getZendCache();

            $this->_response->setBody(
                json_encode(array('error' => false))
            );
        } else {
            $this->_response->setBody(
                json_encode(array('error' => true))
            );
        }
    }
}