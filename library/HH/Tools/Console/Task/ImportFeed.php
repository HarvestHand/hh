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
 * @copyright $Date: 2012-04-05 22:19:21 -0300 (Thu, 05 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ImportFeed.php 497 2012-04-06 01:19:21Z farmnik $
 * @copyright $Date: 2012-04-05 22:19:21 -0300 (Thu, 05 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_ImportFeed extends HH_Tools_Console_Task_ImportFeeds
{
    protected $_task = 'import_feeds';
    
    protected function _buildUri()
    {
        $feed = $this->_console->getArgs()->getOption('import-feed');
        
        $ck = time();
        
        return 'https://www.google.com/reader/api/0/stream/contents/feed/' . $feed 
            . '?ck=' . $ck . '&r=n&n=1000&mediaRss=true&client=HH';
        
    }
    
    protected function _postImport()
    {
        HH_Domain_Post::dedupe();
    }
    
}