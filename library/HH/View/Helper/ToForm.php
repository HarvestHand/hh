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
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package HH_View
 */

/**
 * Description of ToForm
 *
 * @package   HH_View
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ToForm.php 329 2011-09-27 01:14:40Z farmnik $
 * @copyright $Date: 2011-09-26 22:14:40 -0300 (Mon, 26 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_ToForm extends Zend_View_Helper_Abstract
{

    /**
     * Convert HH model object to form markup
     * 
     * @param HH_Object $var
     * @param string $form Form to load
     * @param array $params
     * @return string
     */
    public function toForm(HH_Object $object, $form = 'default',
        $params = array())
    {
        return $object->toForm($this->view, $form, $params);
    }
}