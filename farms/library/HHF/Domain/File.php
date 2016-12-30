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
 * @copyright $Date: 2013-05-07 10:20:48 -0300 (Tue, 07 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * File model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: File.php 652 2013-05-07 13:20:48Z farmnik $
 * @copyright $Date: 2013-05-07 10:20:48 -0300 (Tue, 07 May 2013) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_File extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const FETCH_ALL = null;
    const CATEGORY_ADDONS = 'ADDONS';
    const CATEGORY_SHARES = 'SHARES';
    const CATEGORY_BLOG = 'BLOG';
    const CATEGORY_WEBSITE = 'WEBSITE';
    const TYPE_IMAGE = 'IMAGE';
    const TYPE_DOCUMENT = 'DOCUMENT';
    const IMAGE_LARGE = 'L';
    const IMAGE_SMALL = 'S';
    const IMAGE_THUMBNAIL = 'T';

    public static $sizes = array(
        self::IMAGE_LARGE,
        self::IMAGE_SMALL,
        self::IMAGE_THUMBNAIL
    );

    public static $categories = array(
        self::CATEGORY_ADDONS,
        self::CATEGORY_BLOG,
        self::CATEGORY_SHARES,
        self::CATEGORY_WEBSITE
    );
    
    public static $types = array(
        self::TYPE_DOCUMENT,
        self::TYPE_IMAGE
    );

    public function  __construct(HH_Domain_Farm $farm, $id = null, $data = null, $config = array())
    {
        if (empty($config['path'])) {
            $config['path'] = Bootstrap::getZendConfig()->resources->files->path;
        }

        if (empty($config['imageExtentions'])) {
            $config['imageExtentions'] = Bootstrap::getZendConfig()->resources->files->imageExtentions;
        }

        if (empty($config['documentExtentions'])) {
            $config['documentExtentions'] = Bootstrap::getZendConfig()->resources->files->documentExtentions;
        }

        if (empty($config['size'])) {
            $config['size'] = Bootstrap::getZendConfig()->resources->files->size;
        }
        
        if (empty($config['imageMimeTypes'])) {
            $config['imageMimeTypes'] = Bootstrap::getZendConfig()->resources->files->imageMimeTypes;
        }
        
        if (empty($config['documentMimeTypes'])) {
            $config['documentMimeTypes'] = Bootstrap::getZendConfig()->resources->files->documentMimeTypes;
        }

        if (empty($config['imageSizes'])) {
            $config['imageSizes'] = Bootstrap::getZendConfig()->resources->files->imageSizes->toArray();
        }

        parent::__construct($farm, $id, $data, $config);
    }


    /**
     * Get data (lazy loader)
     *
     * @return null
     */
    protected function _get()
    {
        if (empty($this->_id)) {
            $this->_setData();
            return;
        }

        $cache = $this->_getZendCache();
        if (($data = $cache->load((string) $this)) !== false) {
            $this->_setData($data);
            return;
        }

        $sql = 'SELECT
                  *
                FROM
                    ' . $this->_getDatabase() . '
                WHERE
                    id = ?';

        $data = $this->_getZendDb()->fetchRow($sql, $this->_id);

        if (!empty($data['categories']) && strpos($data['categories'], ',')) {
            $data['categories'] = explode(',', $data['categories']);
        }

        $this->_setData(
            $data
        );

        $cache->save($this->_data, (string) $this);
    }

    /**
     * Prepare data to be entered into the database
     *
     * convert categories
     *
     * @param array $data Data to prepare
     * @param boolean $insert Is data to be inserted (false is updated)
     * @return array
     */
    protected function _prepareData($data, $insert = true)
    {
        if (isset($data['categories']) && is_array($data['categories'])) {
            $data['categories'] = implode(',', $data['categories']);
        }

        return parent::_prepareData($data, $insert);
    }

    /**
     * Upload file
     * 
     * @param string $file name of input element
     * @param string $type type of file
     * @param string $categories categories for this file
     * @param string $title title of file
     * @param strimg $details details of file
     * @return HHF_Domain_File
     */
    public function upload($file, $type = null, $categories = null, $title = null, $details = null)
    {
        $data = array();
        $isNew = false;
        $upload = new Zend_File_Transfer_Adapter_Http(
            array(
                'useByteString' => false
            )
        );

        if (!$upload->isUploaded($file)) {
            throw new HHF_Domain_File_Exception_NoUpload();
        }

        $path = $this->_getPath(true);

        if (!is_dir($path)) {
            mkdir($path, 0777, true);
        }

        $upload->setDestination($path);

        $data['name'] = $upload->getFileName($file);
        $data['size'] = $upload->getFileSize($file);
        $data['title'] = $title;
        $data['details'] = $details;
        $data['categories'] = $categories;
        $data['mimeType'] = $upload->getMimeType($file);

        $filter = self::getFilter(self::FILTER_NEW);
        $filter->setData($data);

        if (!$filter->isValid()) {
            throw new Exception('Invalid data');
        }

        if (!$this->isEmpty()) {
            $this->update($data);
        } else {
            $isNew = true;
            $this->insert($data);
        }

        $upload->addFilter(
            new Zend_Filter_File_Rename(
                array(
                    'source' => '*',
                    'target' => $this->_getPath(),
                    'overwrite' => true
                )
            )
        );

        $upload->addValidator(
            new Zend_Validate_File_Size(
                $this->_config['size']
            )
        );

        if ($type == self::TYPE_IMAGE) {
            $upload->addValidator(
                new Zend_Validate_File_Extension(
                    $this->_config['imageExtentions']
                )
            );

//            $upload->addValidator(
//                new Zend_Validate_File_IsImage($this->_config['imageMimeTypes'])
//            );

        } else if ($type == self::TYPE_DOCUMENT) {
            $upload->addValidator(
                new Zend_Validate_File_Extension(
                    $this->_config['documentExtentions']
                )
            );
        } else {
            $upload->addValidator(
                new Zend_Validate_File_Extension(
                    $this->_config['documentExtentions'] . ',' 
                        . $this->_config['imageExtentions']
                )
            );
        }


        if (!$upload->receive($file)) {
            if ($isNew) {
                $this->delete();
            }
            
            throw new HHF_Domain_File_Exception_UploadFailed(
                implode('; ', $upload->getMessages())
            );
        } else {
            if ($type == self::TYPE_IMAGE || substr($data['mimeType'], 0, 5) == 'image') {
                // resize image

                try {

                    if (!$isNew) {
                        @unlink($this->_getPath() . '_' . self::IMAGE_SMALL);
                        @unlink($this->_getPath() . '_' . self::IMAGE_THUMBNAIL);
                    }

                    $dimensions = $this->_resizeImage(self::IMAGE_LARGE);

                } catch (Exception $e) {
                    if ($isNew) {
                        $this->delete();
                    }

                    throw $e;
                }

                $data['width'] = $dimensions['width'];
                $data['height'] = $dimensions['height'];
                $data['size'] = $dimensions['size'];
                
                $this->update($data);
            }
        }

        return $this;
    }

    /**
     *
     * @param string $size Target size
     * @return array final dimensions
     */
    protected function _resizeImage($size)
    {
        $im = new Imagick($this->_getPath());
        
        $curWidth = $im->getImageWidth();
        
        $targetWidth = $this->_config['imageSizes'][$size];
            
        if ($curWidth > $targetWidth) {
            $im->thumbnailImage($targetWidth, null, false);

            $newFile = $this->_getPath();

            switch ($size) {
                case self::IMAGE_SMALL:
                    $newFile .= '_' . self::IMAGE_SMALL;
                    break;
                case self::IMAGE_THUMBNAIL:
                    $newFile .= '_' . self::IMAGE_THUMBNAIL;
                    break;
            }

            $im->writeImage($newFile);

            $size = filesize($newFile);
        } else {

            $size = filesize($this->_getPath());
            
        }

        return array(
            'width'  => $im->getImageWidth(),
            'height' => $im->getImageHeight(),
            'size'   => $size
        );
    }
    
    protected function _getPath($basePath = false)
    {
        $path = $this->_config['path'] . DIRECTORY_SEPARATOR . $this->_farm->id;
        
        if ($basePath) {
            return $path;
        }
        
        return $path . DIRECTORY_SEPARATOR . $this->id;
    }

    public function toData($size = false, $headers = false)
    {
        if (!$this->isEmpty()) {

            if ($size && substr($this->mimeType, 0, 5) == 'image') {
                switch ($size) {
                    case self::IMAGE_LARGE :

                        if ($headers) {
                            header('Content-Type: ' . $this->mimeType);
                            header('Content-Length: ' . $this->size);
                        }

                        return @file_get_contents($this->_getPath());
                        break;

                    case self::IMAGE_SMALL :

                        $path = $this->_getPath() . '_' . $size;

                        if ($headers) {
                            header('Content-Type: ' . $this->mimeType);
                        }

                        $file = @file_get_contents($path);

                        if ($file === false) {
                            try {
                                // make image
                                $this->_resizeImage($size);

                                $file = @file_get_contents($path);
                            } catch (Exception $exception) {
                                unset($exception);
                            }
                            
                            if ($file === false) {
                                return @file_get_contents($this->_getPath());
                            }
                        }

                        return $file;

                        break;

                    case self::IMAGE_THUMBNAIL :

                        $path = $this->_getPath() . '_' . $size;

                        if ($headers) {
                            header('Content-Type: ' . $this->mimeType);
                        }

                        $file = @file_get_contents($path);

                        if ($file === false) {
                            // make image
                            try {
                                $this->_resizeImage($size);
                                
                                $file = @file_get_contents($path);
                            } catch (Exception $exception) {
                                unset($exception);
                            }
                            
                            if ($file === false) {
                                return @file_get_contents($this->_getPath());
                            }
                        }

                        return $file;

                        break;
                }
            } else {
                if ($headers) {
                    header('Content-Type: ' . $this->mimeType);
                    header('Content-Length: ' . $this->size);
                    header('Content-Disposition: attachment; filename=' . basename($this->name));
                }

                return @file_get_contents($this->_getPath());
            }
        }
    }

    /**
     * Get Zend_Filter_Input for model
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);

        switch ($filter) {

            case self::FILTER_NEW :
            case self::FILTER_EDIT :
                $inputFilter = new Zend_Filter_Input(
                    array(
                        '*' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'title' => array(
                            new Zend_Filter_Null()
                        ),
                        'details' => array(
                            new Zend_Filter_Null()
                        ),
                        'categories' => array(
                            new Zend_Filter_Null()
                        ),
                        'mimeType' => array(
                            new Zend_Filter_Null()
                        ),
                        'width' => array(
                            new Zend_Filter_Null()
                        ),
                        'height' => array(
                            new Zend_Filter_Null()
                        )
                    ),
                    array(
                        'name' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid file name is required')
                            )
                        ),
                        'title' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'details' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'categories' => array(
                            new Zend_Validate_InArray(self::$categories),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'mimeType' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'size' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'width' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                        'height' => array(
                            new Zend_Validate_Int(),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null
                        ),
                    ),
                    null,
                    array(
                        Zend_Filter_Input::MISSING_MESSAGE   =>
                            $translate->_("'%field%' is required"),
                        Zend_Filter_Input::NOT_EMPTY_MESSAGE =>
                            $translate->_("'%field%' is required"),
                    )
                );
                break;
        }

        return $inputFilter;
    }

    /**
     * Fetch all pages
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchFiles(HH_Domain_Farm $farm, $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm);

        $bind = array();

        $result = $db->fetchAll($sql, $bind);

        $return = array();

        foreach ($result as $file) {
            $return[] = new self(
                $farm,
                $file['id'],
                $file,
                $options
            );
        }

        return $return;
    }
}
