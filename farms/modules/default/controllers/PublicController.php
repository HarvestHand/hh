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
class PublicController extends HHF_Controller_Action
{
    public function init()
    {
        parent::init();
        
        $this->theme->bootstrap($this);
    }

    public function indexAction()
    {
        $this->_forward('index', 'public', 'website');
        
    }
    
    public function fileAction()
    {
        $this->_noRender = true;
        $this->_helper->layout->disableLayout();

        $id = (int) $this->_request->getParam('id', 0);
        $size = $this->_request->getParam('s', HHF_Domain_File::IMAGE_LARGE);

        if (!in_array($size, HHF_Domain_File::$sizes)) {
            $size = HHF_Domain_File::IMAGE_LARGE;
        }

        $file = new HHF_Domain_File($this->farm, $id);

        $data = $file->toData($size, true);
        
        if ($data !== false) {
            $this->_response->setBody($data);
        } else {
            $this->getResponse()->setRawHeader('HTTP/1.1 404 Not Found');
        }
    }
}