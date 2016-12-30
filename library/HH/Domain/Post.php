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
 * @copyright $Date: 2012-08-02 23:01:32 -0300 (Thu, 02 Aug 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Domain
 */

/**
 * Description of Post
 *
 * @package   HH_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Post.php 569 2012-08-03 02:01:32Z farmnik $
 * @copyright $Date: 2012-08-02 23:01:32 -0300 (Thu, 02 Aug 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Domain_Post extends HH_Object_Db
{
    public static function getFilter($filter = null, $options = array())
    {
        
    }
    
    public function getRawImage()
    {
        $path = $path = Bootstrap::getZendConfig()->resources->files->path 
            . DIRECTORY_SEPARATOR . 'planet' 
            . DIRECTORY_SEPARATOR 
            . HH_Tools_String::convertToCacheSafe($this->_id) 
            . DIRECTORY_SEPARATOR . 'img.png';
        
        return @file_get_contents($path);
    }
    
    public static function dedupe()
    {
        $database = Bootstrap::getZendDb();
        
        $dupes = $database->fetchAll(
            'SELECT 
                blogUrl, 
                title,
                DATE(addedDatetime) as addedDate,
                COUNT( * ) AS count
            FROM  
                farmnik_hh.posts 
            GROUP BY 
                DATE( addedDatetime ) , title, blogUrl
            HAVING (
                count >1
            )'
        );
        
        if (empty($dupes)) {
            return;
        }
        
        foreach ($dupes as $dupe) {
            $dupeGroup = self::fetch(
                array(
                    'where' => 'DATE(addedDatetime) = ' . $database->quote($dupe['addedDate']) . '
                        AND title =  ' . $database->quote($dupe['title']) . '
                        AND blogUrl = ' . $database->quote($dupe['blogUrl'])
                )
            );
            
            foreach ($dupeGroup as $key => $post) {
                if ($key == 0) {
                    continue;
                }
                
                $post->delete();
            }
        }
    }
}