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
 * @copyright $Date: 2016-07-01 09:22:57 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Theme
 */

/**
 * Description of Theme
 *
 * @package   HHF_Theme
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Schurman.php 977 2016-07-01 12:22:57Z farmnik $
 * @copyright $Date: 2016-07-01 09:22:57 -0300 (Fri, 01 Jul 2016) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Theme_Schurman extends HHF_Theme
{
    public function  __construct()
    {
        parent::__construct('schurman');
        $this->_styleSheets = array(
//            '/_css/bootstrap.min.css',
            'https://maxcdn.bootstrapcdn.com/twitter-bootstrap/2.3.2/css/bootstrap-combined.min.css',
            '/_farms/css/themes/schurman/core.css?v=6',
            '/_farms/css/themes/schurman/schurman.css?v=7',
            '/_css/ui/localmotive/jquery-ui.css?v=6',
            '/less/t/core?v=2',
            '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css',
        );

        $this->_layout .= '.schurman';
    }
}
