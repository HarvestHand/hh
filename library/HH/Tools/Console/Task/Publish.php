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
 * @copyright $Date: 2012-04-10 10:44:05 -0300 (Tue, 10 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Publish.php 509 2012-04-10 13:44:05Z farmnik $
 * @copyright $Date: 2012-04-10 10:44:05 -0300 (Tue, 10 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_Publish extends HH_Tools_Console_Task
{
    public function run()
    {
        if (($pid = $this->_console->isLocked('publish')) !== false) {
            $this->_console->outputText(
                'Task \'publish\' locked with a PID of ' . $pid
            );
            return HH_Tools_Console::ERROR_LOCK;
        }

        $this->_console->setLock('publish');

        try {

            $farms = HH_Domain_Farm::fetch();
            
            foreach ($farms as $farm) {
                $this->_console->outputText('Publishing content for ' . $farm->name);
                
                $posts = HHF_Domain_Post::fetch(
                    $farm, 
                    array(
                        'where' => 'publish = \'PUBLISHED\' '
                            . 'AND DATE(publishedDatetime) = DATE(NOW()) ' 
                            . 'AND (facebookId IS NULL OR twitterId IS NULL)'
                    )
                );
                
                foreach ($posts as $post) {
                    $this->_console->outputText('Publishing blog post ' . $post->id);
                    $post->update(array());
                }
                
//                $issues = HHF_Domain_Issue::fetch(
//                    $farm, 
//                    array(
//                        'where' => 'publish = \'PUBLISHED\' '
//                            . 'AND archive = 1 ' 
//                            . 'AND DATE(publishedDatetime) = DATE(NOW()) ' 
//                            . 'AND (facebookId IS NULL OR twitterId IS NULL)'
//                    )
//                );
//                
//                foreach ($issues as $issue) {
//                    $this->_console->outputText('Publishing content for newsletter issue ' . $issue->id);
//                    $issue->update(array());
//                }
            }

        } catch (Exception $e) {
            $this->_console->removeLock('publish');
            throw $e;
        }

        $this->_console->removeLock('publish');
        
        return HH_Tools_Console::ERROR_NONE;
    }
}