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
class Newsletter_PublicController extends HHF_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->theme->bootstrap($this);
    }
    
    public function postDispatch()
    {   
        if ($this->_helper->layout->isEnabled()) {

            $view = clone $this->view;
            $moduleDir = Zend_Controller_Front::getInstance()
                ->getControllerDirectory('website');
            $viewsDir = dirname($moduleDir) . '/views';
            $view->addBasePath($viewsDir);

            $this->view->placeholder('Zend_Layout')
                ->sideBar = $view->render('public/sideBar.phtml');
        }

        parent::postDispatch();
    }

    public function indexAction()
    {
        $this->view->issues = HHF_Domain_Issue::fetch(
            $this->farm,
            array(
                'columns' => array(
                    'id',
                    'title',
                    'token',
                    'publish',
                    'archive',
                    'publishedDatetime'
                ),
                'where' => array(
                    'publish' => 1,
                    'archive' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'publishedDatetime',
                        'dir' => 'desc'
                    )
                )
            )
        );
    }
    
    public function issueAction()
    {
        $this->_helper->layout->disableLayout();
        
        $id = $this->_request->getParam('id');

        if (is_numeric($id)) {
            $this->view->issue = new HHF_Domain_Issue($this->farm, $id);
        } else {
            $this->view->issue = HHF_Domain_Issue::fetchOne(
                $this->farm, 
                array(
                    'where' => array(
                        'token' => $id
                    )
                )
            );
        }
    }
}