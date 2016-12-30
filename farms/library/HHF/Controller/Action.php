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
 * @copyright $Date: 2015-08-19 15:10:55 -0300 (Wed, 19 Aug 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Controller
 */

/**
 * Description of Action
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Action.php 929 2015-08-19 18:10:55Z farmnik $
 * @copyright $Date: 2015-08-19 15:10:55 -0300 (Wed, 19 Aug 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Controller
 */
class HHF_Controller_Action extends HH_Controller_Action
{
    /**
     * @var HH_Domain_Farm
     */
    public $farm;

    /**
     * @var HH_Domain_Farmer
     */
    public $farmer;

    /**
     * @var HHF_Theme
     */
    public $theme;


    /**
     * Initialize action
     */
    public function init()
    {
        if (!$this->_ranOnce) {

            $controllerName = $this->getRequest()->getControllerName();

            $this->farm = Bootstrap::$farm;

            if ($controllerName != 'service') {
                $this->farmer = HH_Domain_Farmer::getAuthenticated();

                $this->initView();
                if ($controllerName == 'admin') {
                    $this->initTitle();
                } else if ($this->farm instanceof HH_Domain_Farm) {
                    $this->initTitle($this->farm['name']);
                }

                $this->view->farmer = $this->farmer;
                $this->view->farm = $this->farm;

                $protocol = (!empty($_SERVER['HTTPS'])) ? 'https://' : 'http://';
                $this->view->images = $protocol . 'www.' .
                    Bootstrap::$rootDomain . '/_images/';
            }

            $this->theme = HHF_Theme::singleton($this->farm);

            $this->_ranOnce = true;
        }

        $this->initTranslate();
    }

    public function render($view = null)
    {
        $request = $this->getRequest();

        $view = $this->theme->getViewScript(
            $request->getModuleName(),
            $request->getControllerName(),
            (($view) ? $view : $request->getActionName())
        );

        str_replace(".", "", $view);
        parent::render($view);
    }

    public function validateAuthentiation($role = HH_Domain_Farmer::ROLE_FARMER)
    {
        if (($this->farmer instanceof HH_Domain_Farmer)
            && ($this->farm instanceof HH_Domain_Farm)) {

            if ($this->farm->id == $this->farmer->farmId) {
                if ($this->farmer->role == $role) {
                    return true;
                }
            }
        }

        switch ($role) {
            case HH_Domain_Farmer::ROLE_FARMER :
                if ($this->farm instanceof HH_Domain_Farm) {
                    HH_Tools_Authentication::setExplicitRedirectUrl(
                        $this->farm->getBaseUri()
                        . ltrim($_SERVER['REQUEST_URI'], '/')
                    );
                }

                $this->_redirect(
                    'http://www.' . Bootstrap::$rootDomain . '/login',
                    array('exit' => true)
                );
                break;

            case HH_Domain_Farmer::ROLE_MEMBER :
                HH_Tools_Authentication::setExplicitRedirectUrl(
                    $_SERVER['REQUEST_URI']
                );

                if ($this->farm instanceof HH_Domain_Farm) {
                    $this->_redirect(
                        $this->farm->getBaseUri() . 'login',
                        array('exit' => true)
                    );
                } else {
                    $this->_redirect(
                        'login',
                        array('exit' => true)
                    );
                }
                break;
        }
    }
}