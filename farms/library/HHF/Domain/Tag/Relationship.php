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
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Tag relationship model
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Relationship.php 409 2012-01-17 22:45:31Z farmnik $
 * @copyright $Date: 2012-01-17 18:45:31 -0400 (Tue, 17 Jan 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Tag_Relationship extends HHF_Object_Db
{
    const TYPE_POST = 'POST';
    const TYPE_CUSTOMER = 'CUSTOMER';
    const FILTER_NEW = 'new';
    const FILTER_EDIT = 'edit';
    
    public static function getFilter($filter = null, $options = array())
    {
        $inputFilter = null;

        $translate = self::_getStaticZendTranslate();
        Zend_Validate_Abstract::setDefaultTranslator($translate);
        
        switch ($filter) {
            case self::FILTER_NEW :
                
                break;
        }
        
        return $inputFilter;
    }
    
    public static function fetchTagsByType(HH_Domain_Farm $farm, $type, 
        $typeId = null)
    {
        $tags = array();
        
        $options = array(
            'where' => array(
                'type' => $type
            )
        );
        
        if (!empty($typeId)) {
            $options['where']['typeId'] = $typeId;
        }
        
        $relationships = self::fetch(
            $farm,
            $options
        );
        
        if (count($relationships)) {
            
            $ids = array();
            
            foreach ($relationships as $relationship) {
                $ids[] = $relationship['tagId'];
            }
            
            $tags = HHF_Domain_Tag::fetch(
                $farm,
                array(
                    'where' => 'id IN(' . implode(',', $ids) . ')'
                )
            );
        }
        
        return $tags;
    }
    
    public static function fetchTypesArrayByTag(HH_Domain_Farm $farm, 
        HHF_Domain_Tag $tag, $type)
    {
        $types = array();
        
        $options = array(
            'where' => array(
                'tagId' => $tag->id,
                'type' => $type
            )
        );
        
        $relationships = self::fetch(
            $farm,
            $options
        );
        
        if (!empty($relationships)) {
            
            foreach ($relationships as $relationship) {
                $types[] = $relationship['typeId'];
            }
        }
        
        return $types;
    }
}
