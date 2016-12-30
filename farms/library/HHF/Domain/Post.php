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
 * @copyright $Date: 2015-09-09 11:50:52 -0300 (Wed, 09 Sep 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Web page blog post model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Post.php 955 2015-09-09 14:50:52Z farmnik $
 * @copyright $Date: 2015-09-09 11:50:52 -0300 (Wed, 09 Sep 2015) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Post extends HHF_Object_Db
{
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    const PUBLISH_DRAFT = 'DRAFT';
    const PUBLISH_PUBLISHED = 'PUBLISHED';
    const FETCH_ALL = null;
    const FETCH_CATEGORY = 'category';

    /**
     * @var HH_Domain_Farmer
     */
    protected $_farmer;

    public function __construct(HH_Domain_Farm $farm, $id = null, $data = null,
        $config = array())
    {
        $this->_defaultObservers[] = 'HHF_Domain_Post_Observer';

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

        if (!empty($data)) {

            $data['tags'] = HHF_Domain_Tag_Relationship::fetchTagsByType(
                $this->_farm,
                HHF_Domain_Tag_Relationship::TYPE_POST,
                $this->_id
            );
        }

        $this->_setData($data);

        $cache->save($this->_data, (string) $this);
    }

    /**
     * Delete current object
     *
     * @throws HH_Object_Exception_Id if object ID is not set
     * @return boolean
     */
    public function delete()
    {
        if (!empty($this->_id)) {

            if (!$this->_isLoaded) {
                $this->_get();
            }

            $preEventData = $this->_data;

            $sql = 'DELETE FROM
                        ' . $this->_getDatabase() . '
                    WHERE
                        id = ?';

            $this->_getZendDb()->query($sql, $this->_id);

            $this->_getZendCache()
                ->remove((string) $this);

            foreach ($this->tags as $tag) {

                $relationships = HHF_Domain_Tag_Relationship::fetch(
                    $this->_farm,
                    array(
                        'where' => array(
                            'tagId' => $tag->id
                        )
                    )
                );

                $otherRelationships = false;
                foreach ($relationships as $relationship) {
                    if ($relationship->type == HHF_Domain_Tag_Relationship::TYPE_POST
                        && $relationship->typeId == $this->_id) {

                        $relationship->delete();
                    } else {
                        $otherRelationships = true;
                    }
                }

                if (!$otherRelationships) {
                    $tag->delete();
                }
            }


            $this->_notify(new HH_Object_Event_Delete($preEventData));
        }

        $this->_reset();
    }

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    public function insert($data)
    {
        $tags = (array_key_exists('tags', $data) && count($data['tags'])) ?
            $data['tags'] : array();

        unset($data['tags']);

        $db = $this->_getZendDb();

        $db->insert(
            $this->_getDatabase(),
            $this->_prepareData($data)
        );
        $this->_id = $data['id'] = $db->lastInsertId();

        $data['tags'] = $this->_updateTags(
            array(),
            $tags
        );

        $this->_setData($data);

        $this->_getZendCache()->save($this->_data, (string) $this);

        $this->_notify(new HH_Object_Event_Insert());
    }

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    public function update($data = null)
    {
        if (!empty($this->_id)) {

            if (!$this->_isLoaded) {
                $this->_get();
            }

            $preEventData = $this->_data;

            $tags = (array_key_exists('tags', $data) && count($data['tags'])) ?
                $data['tags'] : array();

            unset($data['tags']);

            $this->_getZendDb()->update(
                $this->_getDatabase(),
                $this->_prepareData($data, false),
                array('id = ?' => $this->_id)
            );

            $data['tags'] = $this->_updateTags(
                (array_key_exists('tags', $this->_data)
                    ? $this->_data['tags'] : array()),
                $tags
            );

            $this->_setData($data, false);

            $this->_getZendCache()->save($this->_data, (string) $this);

            $this->_notify(new HH_Object_Event_Update($preEventData));
        }
    }

    /**
     * Update tag data
     *
     * @param array $orginalTags Original stored relational data
     * @param array $newTags New relational data to be stored
     * @return array Array of updated tag data
     */
    protected function _updateTags($orginalTags, $newTags) {
        $tags = array();

        foreach ($orginalTags as $originalTag) {

            $found = false;

            if (is_array($newTags)) {
                foreach ($newTags as $key => $newTag) {

                    if ($originalTag['tag'] == $newTag) {
                        $tags[] = $originalTag;

                        unset($newTags[$key]);

                        $found = true;
                        break;
                    }
                }
            }

            if (!$found) {
                $relationships = HHF_Domain_Tag_Relationship::fetch(
                    $this->_farm,
                    array(
                        'where' => array(
                            'tagId' => $originalTag->id
                        )
                    )
                );

                $otherRelationships = false;
                foreach ($relationships as $relationship) {
                    if ($relationship->type == HHF_Domain_Tag_Relationship::TYPE_POST
                        && $relationship->typeId == $this->_id) {

                        $relationship->delete();
                    } else {
                        $otherRelationships = true;
                    }
                }

                if (!$otherRelationships) {
                    $originalTag->delete();
                }
            }
        }

        foreach ($newTags as $tag) {

            $tagObject = HHF_Domain_Tag::fetchTagByToken($this->_farm, $tag);

            if ($tagObject->isEmpty()) {

                $tagFilter = HHF_Domain_Tag::getFilter(
                    HHF_Domain_Tag::FILTER_NEW,
                    array('tag' => $tag)
                );

                $tagFilter->setData(
                    array(
                        'tag' => $tag
                    )
                );

                $tagObject->insert($tagFilter->getUnescaped());

            }

            $tagRelationship = new HHF_Domain_Tag_Relationship(
                $this->_farm
            );

            $tagRelationship->insert(
                array(
                    'tagId' => $tagObject->id,
                    'type' => HHF_Domain_Tag_Relationship::TYPE_POST,
                    'typeId' => $this->_id
                )
            );

            $tags[] = $tagObject;
        }

        return $tags;
    }

    /**
     * Is page published
     *
     * @return boolean
     */
    public function isPublished()
    {
        if (!$this->_isLoaded) {
            $this->_get();
        }

        return ($this->_data['publish'] == self::PUBLISH_PUBLISHED) ?
            true : false;
    }

    /**
     * @return HH_Domain_Farmer
     */
    public function getFarmer()
    {
        if (!empty($this->farmerId)) {
            if ($this->_farmer instanceof HH_Domain_Farmer) {
                if ($this->_farmer->farmId != $this->_farm->id) {
                    $this->_farmer = null;
                }

                return $this->_farmer;
            }

            $farmer = HH_Domain_Farmer::fetchOne(
                array(
                    'where' => array(
                        'id' => $this->farmerId,
                        'role' => $this->farmerRole,
                        'farmId' => $this->_farm->id
                    )
                )
            );

            if (!$farmer->isEmpty()) {

                $this->_farmer = $farmer;
            }
        }

        return $this->_farmer;
    }

    /**
     * @return HHF_Domain_Post_Comment[]
     */
    public function getComments()
    {
        return HHF_Domain_Post_Comment::fetch(
            $this->_farm,
            array(
                'where' => array(
                    'postId' => $this->id
                ),
                'order' => array(
                    array(
                        'column' => 'addedDatetime',
                        'dir' => 'ASC'
                    )
                )
            )
        );
    }

    public function getTags()
    {

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
                $tokenFilter = new HHF_Filter_Transliteration(
                    255,
                    'UTF-8',
                    array(
                        'table' => 'farmnik_hh_' . $options['farm']->id . '.posts',
                        'field' => 'token',
                        'idField' => 'id',
                        'currentId' => (($filter == self::FILTER_EDIT) ?
                            $options['currentId'] : null)
                    )
                );

                $tokenCategoryFilter = new HHF_Filter_Transliteration(255);

                $inputFilter = new Zend_Filter_Input(
                    array(
                        'title' => array(
                            new Zend_Filter_StringTrim()
                        ),
                        'tags' => array(
                            new HHF_Filter_Tags()
                        ),
                        'content' => new HH_Filter_Html(
                            array(
                                'AutoFormat.AutoParagraph' => true,
                                'AutoFormat.Linkify' => true,
                                'AutoFormat.RemoveEmpty' => true,
                                'HTML.SafeEmbed' => true,
                                'HTML.SafeObject' => true,
                                'Output.FlashCompat' => true,
                                'URI.Base' => $options['farm']->getBaseUri(),
                                'URI.MakeAbsolute' => true,
                                'CSS.Trusted' => true,
                                'HTML.Trusted' => true,
                                'Filter.ExtractStyleBlocks.TidyImpl' => false,
                                'MyIframe' => true
                            )
                        )
                    ),
                    array(
                        'title' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid title is required')
                            )
                        ),
                        'token' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                $tokenFilter->filter($options['title']),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A token is required')
                            )
                        ),
                        'content' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false
                        ),
                        'publish' => array(
                            new Zend_Validate_InArray(
                                array(
                                    self::PUBLISH_DRAFT,
                                    self::PUBLISH_PUBLISHED
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Published status is required')
                            )
                        ),
                        'publishedDatetime' => array(
                            new Zend_Validate_Date('yyyy-M-d'),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => null,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('Post published date is required')
                            )
                        ),
                        'category' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => 'Uncategorized',
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid category is required')
                            )
                        ),
                        'categoryToken' => array(
                            new Zend_Validate_StringLength(0, 255),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                $tokenCategoryFilter->filter($options['category']),
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid category token is required')
                            )
                        ),
                        'tags' => array(
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_OPTIONAL,
                            Zend_Filter_Input::ALLOW_EMPTY => true,
                            Zend_Filter_Input::DEFAULT_VALUE => array(),
                        ),
                        'farmerId' => array(
                            new Zend_Validate_Db_RecordExists(
                                array(
                                    'table' => 'farmnik_hh.farmers',
                                    'field' => 'id',
                                    'adapter' => self::_getStaticZendDb(),
                                    'exclude' => 'farmId = '
                                        . (int) $options['farm']['id']
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid author is required')
                            )
                        ),
                        'farmerRole' => array(
                            new Zend_Validate_InArray(
                                array(
                                    HH_Domain_Farmer::ROLE_FARMER,
                                    HH_Domain_Farmer::ROLE_MEMBER,
                                )
                            ),
                            Zend_Filter_Input::PRESENCE =>
                                Zend_Filter_Input::PRESENCE_REQUIRED,
                            Zend_Filter_Input::ALLOW_EMPTY => false,
                            Zend_Filter_Input::DEFAULT_VALUE =>
                                HH_Domain_Farmer::ROLE_FARMER,
                            Zend_Filter_Input::MESSAGES => array(
                                $translate->_('A valid author is required')
                            )
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
     * Fetch single post by token
     *
     * @param HH_Domain_Farm $farm
     * @param string $token
     * @return HHF_Domain_Post
     */
    public static function fetchPostByToken(HH_Domain_Farm $farm, $token)
    {
        $cache = self::_getStaticZendCache();
        $key = 'HHF_Domain_Post_' . $farm->id .
            preg_replace('/([^a-zA-Z0-9_])+/', '_', $token);

        if (($data = $cache->load($key)) !== false) {
            return new self($farm, $data['id'], $data);
        }

        $db = self::_getStaticZendDb();

        $sql = 'SELECT
                    *
                FROM
                    ' . self::_getStaticDatabase($farm) . '
                WHERE
                    token = ?
                AND
                    publish = ?
                AND
                    DATE(publishedDatetime) <= DATE(NOW())';

        $result = $db->fetchRow($sql, array($token, self::PUBLISH_PUBLISHED));

        if (!empty($result)) {

            $result['tags'] = HHF_Domain_Tag_Relationship::fetchTagsByType(
                $farm,
                HHF_Domain_Tag_Relationship::TYPE_POST,
                $result['id']
            );

            return new self($farm, $result['id'], $result);
        } else {
            return new self($farm);
        }
    }

    /**
     * Blog categories
     *
     * @param HH_Domain_Farm $farm
     * @param array $options
     * @return array
     */
    public static function fetchCategories(HH_Domain_Farm $farm,
        $options = array())
    {
        $db = self::_getStaticZendDb();

        $sql = 'SELECT
            category,
            count(*) as frequency
        FROM
            ' . self::_getStaticDatabase($farm) . '
        GROUP BY category
        ORDER BY frequency DESC';

        $result = $db->fetchAll($sql);

        $return = array();

        if (!empty($result)) {
            foreach ($result as $row) {
                $return[$row['category']] = $row['category'];
            }

            if (!isset($return['Uncategorized'])) {
                $return['Uncategorized'] = self::_getStaticZendTranslate()->_(
                    'Uncategorized'
                );
            }

        } else {
            $return = array(
                'Uncategorized' => self::_getStaticZendTranslate()->_(
                    'Uncategorized'
                )
            );
        }

        return $return;
    }


    public function getPicture(){

        $picture = null;
        $doc = new DOMDocument();
        @$doc->loadHTML('<html><body>' . $this->content . '</body></html>');

        $tags = $doc->getElementsByTagName('img');

        foreach($tags as $tag){
            $img = $tag->getAttribute('src');

            if(stripos($img, 'http://') !== false){
                $picture = $img;
                break;
            }
        }

        return $picture;
    }
}