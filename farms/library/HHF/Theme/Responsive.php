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
 * @copyright $Date: 2013-05-21 23:01:02 -0300 (Tue, 21 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Theme
 */

/**
 * Description of Theme
 *
 * @package   HHF_Theme
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Taproot.php 668 2013-05-22 02:01:02Z farmnik $
 * @copyright $Date: 2013-05-21 23:01:02 -0300 (Tue, 21 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Theme_Responsive extends HHF_Theme
{
    public function  __construct()
    {
        parent::__construct('responsive');

        $this->_styleSheets = array(
            '/_css/bootstrap.min.css',
//            '/_farms/css/themes/default/core.css?v=5',
//            '/_farms/css/themes/default/default.css?v=3',
            '//fonts.googleapis.com/css?family=Playfair+Display:400,700|Open+Sans:400,300,600,70',
            '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css',
            '/_farms/css/themes/responsive/ui.css?v=1',
            '/less/t/core?v=1',
        );

		$this->_layout .= '.responsive';
    }
}
