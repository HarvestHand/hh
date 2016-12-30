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
 * @package   HHF_Theme
 */

/**
 * Description of Theme
 *
 * @package   HHF_Theme
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Theme.php 929 2015-08-19 18:10:55Z farmnik $
 * @copyright $Date: 2015-08-19 15:10:55 -0300 (Wed, 19 Aug 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Theme
{
    protected $_theme = null;
    protected static $_instance = array();
    protected $_layout = 'public';
    protected $_overrides = array();
    protected $_styleSheets = array(
        '/_css/bootstrap.min.css',
        '/_farms/css/themes/default/core.css?v=5',
        '/_farms/css/themes/default/default.css?v=3',
        '/_css/ui/default/jquery-ui.css?v=3',
        '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css'
    );

    /**
     * @var HHF_Controller_Action
     */
    protected $_action;

    /**
     * Theme constructor
     */
    public function  __construct($theme = null)
    {
        $this->_theme = $theme;
    }

    /**
     * @param HH_Domain_Farm $farm
     * @return HHF_Theme
     */
    public static function factory(HH_Domain_Farm $farm = null)
    {
        if ($farm instanceof HH_Domain_Farm) {

            $theme = $farm->getPreferences()
                ->get('theme', 'website', 'default');
        } else {
            $theme = 'default';
        }

        $class = 'HHF_Theme_' . ucfirst(strtolower($theme));

        return new $class();
    }

    /**
     * @param HH_Domain_Farm $farm
     * @return HHF_Theme
     */
    public static function singleton(HH_Domain_Farm $farm = null)
    {
        $farmId = (string) $farm;

        if (isset(self::$_instance[$farmId])) {
            return self::$_instance[$farmId];
        }

        self::$_instance[$farmId] = self::factory($farm);

        return self::$_instance[$farmId];
    }

    public function getLayout()
    {
        return $this->_layout;
    }

    public function getViewScript($module, $controller, $action)
    {
        if (!empty($this->_overrides[$module][$controller][$action])) {
            return $controller . '/' . $action . '.' . $this->_theme;
        }

        return $controller . '/' . $action;
    }

    public function bootstrap(HHF_Controller_Action $action)
    {
        $this->_action = $action;

        $action->getHelper('layout')->setLayout(
            $this->getLayout()
        );

        foreach (array_reverse($this->_styleSheets) as $styleSheet) {
            if (is_array($styleSheet)) {
                $action->view->headLink()
                    ->prependStylesheet($styleSheet[0], $styleSheet[1]);
            } else {
                $action->view->headLink()
                    ->prependStylesheet($styleSheet);
            }
        }
    }

    /**
     * @param $sheet
     * @return HHF_Theme
     */
    public function prependStyleSheet($sheet)
    {
        array_unshift($this->_styleSheets, $sheet);
        return $this;
    }

    /**
     * @param $sheet
     * @return HHF_Theme
     */
    public function appendStyleSheet($sheet)
    {
        array_push($this->_styleSheets, $sheet);
        return $this;
    }

    public function getStyleSheets()
    {
        $sheets = array();

        foreach (array_reverse($this->_styleSheets) as $styleSheet) {
            if (is_array($styleSheet)) {
                $sheets[] = $styleSheet[0];
                $sheets[] = $styleSheet[1];
            } else {
                $sheets[] = $styleSheet;
            }
        }

        return $sheets;
    }
}
