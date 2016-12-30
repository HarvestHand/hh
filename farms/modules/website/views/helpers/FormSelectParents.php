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
 * @package
 */

/**
 * Description of FormSelectParents
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Zend_View_Helper_FormSelectParents extends Zend_View_Helper_FormSelect
{
    public function formSelectParents($parents = array(), $parent = null, $id = null)
    {
        $options = array('' => $this->view->translate('Top level'));

        foreach ($parents as $p) {
            if ($p->id == $id) {
                continue;
            }
            $options[$p->id] = $this->view->translate('Under "%s"', $p->title);
        }

        return $this->formSelect(
            'parent',
            $parent,
            array(
                'id' => 'parent',
                'class' => '',
                'title' => $this->view->translate(
                    'Please enter your page\'s placement'
                )
            ),
            $options
        );
    }
}