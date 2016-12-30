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
 * @copyright $Date: 2014-12-22 12:22:27 -0400 (Mon, 22 Dec 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Theme
 */

/**
 * Description of Theme
 *
 * @package   HHF_Theme
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Shopping.php 820 2014-12-22 16:22:27Z farmnik $
 * @copyright $Date: 2014-12-22 12:22:27 -0400 (Mon, 22 Dec 2014) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Theme_Shopping extends HHF_Theme
{
    public function  __construct()
    {
        $this->_styleSheets = array(
            array(
                '/_farms/css/themes/shopping/bootstrap.min.css?v=2',
                'all'
            ),
            '/_farms/css/themes/shopping/core.css?v=5',
            '/_css/addtohomescreen.css?v=1',
            '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css'
        );

        $this->_layout = 'shopping';

        parent::__construct('shopping');
    }

    public function bootstrap(HHF_Controller_Action $action)
    {
        foreach ($action->view->headLink()->getContainer()->getKeys() as $key) {
            $action->view->headLink()->offsetUnset($key);
        }

        return parent::bootstrap($action);
    }
}
