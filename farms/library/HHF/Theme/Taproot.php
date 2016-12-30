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
 * @version   $Id: Taproot.php 929 2015-08-19 18:10:55Z farmnik $
 * @copyright $Date: 2015-08-19 15:10:55 -0300 (Wed, 19 Aug 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Theme_Taproot extends HHF_Theme
{
    public function  __construct()
    {
        parent::__construct('taproot');
        $this->_styleSheets = array(
            '/_css/bootstrap.min.css',
            '/_farms/css/themes/default/core.css?v=5',
            '/_farms/css/themes/default/taproot.css?v=5',
            '/_css/ui/taproot/jquery-ui.css?v=2',
            '//maxcdn.bootstrapcdn.com/font-awesome/4.1.0/css/font-awesome.min.css'
        );
    }
}
