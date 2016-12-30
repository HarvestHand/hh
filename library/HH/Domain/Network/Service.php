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
 * @copyright $Date: 2012-06-19 22:17:05 -0300 (Tue, 19 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of transaction service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 554 2012-06-20 01:17:05Z farmnik $
 * @copyright $Date: 2012-06-19 22:17:05 -0300 (Tue, 19 Jun 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Domain_Network_Service extends HH_Object_Service
{
    public function save($data)
    {
        if ($this->_object->isEmpty()) {
            if (empty($data['status'])) {
                $data['status'] = HH_Domain_Network::STATUS_PENDING;
            }

            $result = $this->_object->insert($data);

            $this->sendEmailAlert();

            return $result;
        } else {
            return $this->_object->update($data);
        }
    }

    protected function sendEmailAlert()
    {
        if (!empty($this->_object->getFarm()->email)) {
            $layout = new Zend_Layout();
            $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
            $layout->setLayout('email');
            $layout->getView()->farm = $this->_object->getFarm();

            $view = new Zend_View();
            $view->setScriptPath(Bootstrap::$modules . 'default/views/scripts/');
            $view->relation = $this->_object->getRelation();
            $view->farm = $this->_object->getFarm();
            $view->status = $this->_object->status;

            $layout->content = $view->render('admin/network-email.phtml');

            if (empty($this->_object->getRelation()->email)) {
                $replyTo = array($this->_object->getFarm()->email, $this->_object->getFarm()->name);
            } else {
                $replyTo = array(
                    $this->_object->getRelation()->email,
                    $this->_object->getRelation()->name
                );
            }

            $translate = Bootstrap::getZendTranslate();

            $email = new HH_Job_Email();
            $email->add(
                array($this->_object->getFarm()->email, $this->_object->getFarm()->name),
                $this->_object->getFarm()->email,
                $translate->_('New Vendor Request'),
                null,
                $layout->render(),
                $replyTo,
                null,
                null,
                'farmnik@harvesthand.com',
                'farmnik@harvesthand.com'
            );
        }
    }

}
