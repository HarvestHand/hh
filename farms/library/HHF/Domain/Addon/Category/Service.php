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
class HHF_Domain_Addon_Category_Service extends HH_Object_Service
{
    /**
     *
     * @var HHF_Domain_Addon_Category
     */
    protected $_object;

    public function save($data)
    {
        $translate = Bootstrap::getZendTranslate();
        $translate->addModuleTranslation('library');

        // validate
        $validateOptions = array(
            'farm' => $this->_object->getFarm()
        );

        if (!$this->_object->isEmpty()) {
            $validateOptions['id'] = $this->_object->id;
        }

        $categoryData = HHF_Domain_Addon_Category::validate(
            $data,
            $validateOptions
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
                    $categoryData['name']
                );

                $categoryData['image'] = $file->id;

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
            $result =$this->_object->insert($categoryData);
        } else {

            $result = $this->_object->update($categoryData);
        }

        return $result;
    }

    public function remove()
    {
        if (!$this->_object->isEmpty()) {

            // move addons effected

            $addons = HHF_Domain_Addon::fetch(
                $this->_object->getFarm(),
                array(
                    'where' => array(
                        'categoryId' => $this->_object->id
                    )
                )
            );

            if ($addons->count()) {

                $newCategoryId = 'Other-Goodies';

                $categories = HHF_Domain_Addon_Category::fetchAllForm(
                    $this->_object->getFarm()
                );

                foreach ($categories as $categoryId => $category) {
                    if ($categoryId == 'Other-Goodies') {
                        break;
                    }
                }

                if (!empty($categoryId)) {
                    $newCategoryId = $categoryId;
                }

                foreach ($addons as $addon) {
                    $addon->save(
                        array(
                            'categoryId' => $newCategoryId
                        )
                    );
                }
            }

            $this->_object->delete();
        }
    }
}
