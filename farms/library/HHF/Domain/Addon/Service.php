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
 * @copyright $Date: 2012-08-01 08:42:51 -0300 (Wed, 01 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of addon service
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Service.php 562 2012-08-01 11:42:51Z farmnik $
 * @copyright $Date: 2012-08-01 08:42:51 -0300 (Wed, 01 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Addon_Service extends HH_Object_Service
{
    /**
     *
     * @var HHF_Domain_Addon
     */
    protected $_object;

    public function save($data)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');

        if (!empty($data['categoryNew'])) {
            $category = HHF_Domain_Addon_Category::fetchOneByName($this->_object->getFarm(), $data['categoryNew']);

            if ($category->isEmpty()) {
                $category->getService()->save(
                    array(
                        'name' => $data['categoryNew']
                    )
                );
            }

            $data['categoryId'] = $category->id;
            unset($data['categoryNew']);
        }

        // validate
        if (!$this->_object->isEmpty()) {
            if (empty($data['name'])) {
                $data['name'] = $this->_object['name'];
            }

            if (empty($data['price'])) {
                $data['price'] = $this->_object['price'];
            }

            if (!isset($data['enabled'])) {
                $data['enabled'] = $this->_object['enabled'];
            }
        }

        $addonData = HHF_Domain_Addon::validate(
            $data,
            array()
        );

        // upload images
        if (!empty($_FILES['imageUpload']['name'])) {

            $file = new HHF_Domain_File(
                $this->_object->getFarm(),
                ($this->_object->isEmpty() ? null : $this->_object->image)
            );

            try {

                $file->upload(
                    'imageUpload',
                    HHF_Domain_File::TYPE_IMAGE,
                    HHF_Domain_File::CATEGORY_ADDONS,
                    $addonData['name']
                );

                $addonData['image'] = $file->id;

            } catch (Exception $e) {

                throw new HH_Object_Exception_Validation(
                    array(
                        'imageUpload' => array(
                            $translate->_(
                                'Unable to receive uploaded file.'
                            )
                        )
                    )
                );
            }
        }

        if ($this->_object->isEmpty()) {
            $result =$this->_object->insert($addonData);
        } else {

            $hadDistributor = false;

            if (!empty($this->_object['distributorId']) && $this->_object['distributorId'] != $this->_object->getFarm()->id) {
                $hadDistributor = $this->_object['distributorId'];
            }

            $result = $this->_object->update($addonData);

            if (empty($this->_object['distributorId']) && $hadDistributor !== false) {
                $options = array(
                    'deleteFrom' => 'distributor'
                );

                $options['externalId'] = $this->_object->id;
                $options['vendorId'] = $this->_object->getFarm()->id;
                $options['distributorId'] = $hadDistributor;

                $this->publishToNetwork($options);
            }
        }

        // set locations
        $locations = array();

        if (!empty($data['locations'])) {
            foreach ($data['locations'] as $location) {
                $locations[] = array(
                    'addonId' => $this->_object->id,
                    'locationId' => $location
                );
            }
        }

        $this->_object->getLocations()->getService()->save($locations);

        if (!empty($this->_object['distributorId']) && $this->_object['distributorId'] != $this->_object->getFarm()->id) {
            $this->publishToNetwork((empty($data['distributor']) ? array() : $data['distributor']));
        }

        return $result;
    }

    public function remove()
    {
        $options = false;

        if (!empty($this->_object['distributorId']) && $this->_object['distributorId'] != $this->_object->getFarm()->id) {

            $options = array(
                'deleteFrom' => 'distributor'
            );

            $options['externalId'] = $this->_object->id;
            $options['vendorId'] = $this->_object->getFarm()->id;
            $options['distributorId'] = $this->_object->distributorId;
        }

        $result = $this->_object->delete();

        if ($options) {
            $this->publishToNetwork($options);
        }

        return $result;
    }

    protected function publishToNetwork($data)
    {
        $networkSync = new HH_Job_Networksync();
        $networkSync->add(
            $this->_object,
            $data
        );
    }

    public function sendInventoryAlert()
    {
        $farm = $this->_object->getFarm();

        if (!empty($farm->email)) {

            $layout = new Zend_Layout();
            $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
            $layout->setLayout('email');
            $layout->getView()->farm = $farm;

            $view = new Zend_View();
            $view->setScriptPath(
                Bootstrap::$farmModules . 'shares/views/scripts/'
            );

            $view->addon = $this->_object;
            $view->farm = $farm;

            $layout->content = $view->render('admin/addon-email-farm.phtml');

            $replyTo = array($farm->email, $farm->name);

            $translate = Bootstrap::getZendTranslate();
            $translate->addModuleTranslation('library');

            $email = new HH_Job_Email();
            $email->add(
                array($farm->email, $farm->name),
                $farm->email,
                $translate->_('Product Inventory Getting Low in HarvestHand'),
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
