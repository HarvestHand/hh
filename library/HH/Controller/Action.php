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
 * @copyright $Date: 2015-05-22 13:42:41 -0300 (Fri, 22 May 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Controller
 */

/**
 * Description of Action
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Action.php 840 2015-05-22 16:42:41Z farmnik $
 * @copyright $Date: 2015-05-22 13:42:41 -0300 (Fri, 22 May 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Controller
 */
class HH_Controller_Action extends Zend_Controller_Action
{
    /**
     * @var HH_Domain_Farmer
     */
    public $farmer = null;

    /**
     * @var HH_Translate
     */
    public $translate = null;

    /**
     * @var Boolean is rendered
     */
    protected $_rendered = false;

    protected $_noRender = false;

    protected $_ranOnce = false;

    const ACTION_EDIT = 'edit';
    const ACTION_VIEW = '';
    const ACTION_DELETE = 'delete';

    /**
     * Initialize action
     */
    public function init()
    {
        if (!$this->_ranOnce) {

            if ($this->getRequest()->getControllerName() != 'service') {
                $this->farmer = HH_Domain_Farmer::getAuthenticated();

                $this->initView();
                $this->initTitle();

                $this->view->farmer = $this->farmer;

                $protocol = (!empty($_SERVER['HTTPS'])) ?
                    'https://' : 'http://';
                $this->view->images = $protocol . 'www.' .
                    Bootstrap::$rootDomain . '/_images/';
            }

            $this->_ranOnce = true;
        }

        $this->initTranslate();
    }

    /**
     * Initialize View object
     *
     * Initializes {@link $view} if not otherwise a Zend_View_Interface.
     *
     * If {@link $view} is not otherwise set, instantiates a new Zend_View
     * object, using the 'views' subdirectory at the same level as the
     * controller directory for the current module as the base directory.
     * It uses this to set the following:
     * - script path = views/scripts/
     * - helper path = views/helpers/
     * - filter path = views/filters/
     *
     * @return Zend_View_Interface
     */
    public function initView()
    {
        if (isset($this->view) && ($this->view instanceof Zend_View_Interface)) {
            return $this->view;
        }

        $request = $this->getRequest();
        $module  = $request->getModuleName();
        $dirs    = $this->getFrontController()->getControllerDirectory();
        if (empty($module) || !isset($dirs[$module])) {
            $module = $this->getFrontController()->getDispatcher()->getDefaultModule();
        }
        $baseDir = dirname($dirs[$module]) . DIRECTORY_SEPARATOR . 'views';

        $this->view = new Zend_View(
            array(
                'basePath' => $baseDir,
                'encoding' => 'UTF-8'
            )
        );

        $this->view->addHelperPath(Bootstrap::$library . 'HH/View/Helper', 'HH_View_Helper_');
        if (!empty(Bootstrap::$farmLibrary)) {
            $this->view->addHelperPath(Bootstrap::$farmLibrary . 'HHF/View/Helper', 'HHF_View_Helper_');
        }

        $this->view->doctype(Zend_View_Helper_Doctype::HTML5);
        $this->view->headTitle()->setSeparator(' / ');

        $this->_helper->layout->setView($this->view);

        return $this->view;
    }

    public function initTranslate()
    {
        $this->translate = Bootstrap::get('Zend_Translate');

        $this->translate->addModuleTranslation(
            $this->_request->getModuleName()
        );
    }

    public function initTitle($title = 'HarvestHand')
    {
        $this->view->headTitle($title);
    }

    /**
     * Render view script
     *
     * @param string $view  view script
     */
    public function render($view = null){

        if ($this->_noRender) {
            return;
        }

        if (strstr($view, '/')) {
            $contentId = array_pop(explode('/', $view));
            $file = $view . '.phtml';
        } else if (!empty($view)) {
            $contentId = $view;
            $file = $this->getRequest()->getControllerName() . "/{$view}.phtml";
        } else {
            $request = $this->getRequest();
            $contentId = $request->getActionName();
            $file = $request->getControllerName() . '/' . $contentId . '.phtml';
        }

        $this->view->contentId = 'body-content-' . $contentId;

        $this->getResponse()->setBody(
            $this->view->render($file)
        );

        $this->_rendered = true;
    }

    public function setNoRender()
    {
        $this->_noRender = true;
    }

    public function postDispatch()
    {
        if ($this->view instanceof Zend_View_Interface && $this->_rendered == false && $this->_request->isDispatched()) {
            $this->render();
        }
    }
}