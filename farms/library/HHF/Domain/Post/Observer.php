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
 * @copyright $Date: 2012-04-10 10:06:21 -0300 (Tue, 10 Apr 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Domain
 */

/**
 * Description of Observer
 *
 * @package   HHF_Domain
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Observer.php 508 2012-04-10 13:06:21Z farmnik $
 * @copyright $Date: 2012-04-10 10:06:21 -0300 (Tue, 10 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Domain_Post_Observer implements HH_Observer
{

    /**
     * Update observer
     *
     * @param HH_Observer_Subject $subject Subject being observed
     * @param HH_Observer_Event|null $event Event type
     */
    public function update (HH_Observer_Subject $subject,
        HH_Observer_Event $event = null)
    {
        $this->_addFacebookJob($subject, $event);
        $this->_addTwitterJob($subject, $event);
        $this->_addSitemapJob($subject, $event);
    }
    
    protected function _addSitemapJob(HH_Observer_Subject $subject,
        HH_Observer_Event $event = null)
    {
        if ($event->getEvent() != 'DELETE' 
            && $subject->publish == HHF_Domain_Post::PUBLISH_PUBLISHED) {
            
            $sitemap = new HH_Job_Sitemap();
            $sitemap->add($subject->getFarm());
        }
    }
    
    protected function _addFacebookJob(HH_Observer_Subject $subject,
        HH_Observer_Event $event = null)
    {
        // If not enabled on insert, skip
        if ($event->getEvent() == 'INSERT' 
            && $subject->publish != HHF_Domain_Post::PUBLISH_PUBLISHED) {
            
            return;
        }
        
        // if not enabled on update, but tied to a facebook post, delete
        if ($event->getEvent() == 'UPDATE' 
            && $subject->publish != HHF_Domain_Post::PUBLISH_PUBLISHED 
            && !empty($subject->facebookId)) {
            
            $facebook = new HH_Job_Facebook();
            $facebook->add($subject->getFarm(), 'DELETE', $subject);
            
            return;
        }
        
        // if not enabled on update, skip
        if ($event->getEvent() == 'UPDATE' 
            && $subject->publish != HHF_Domain_Post::PUBLISH_PUBLISHED) {

            return;
        }
        
        // if on delete it is not tied to a facebook post, skip
        if ($event->getEvent() == 'DELETE' && empty($subject->facebookId)) {
            return;
        }
        
        $now = Zend_Date::now();
        $now = $now->getDate();
        $now->setTimezone('UTC');
        
        if ($subject->publishedDatetime->compareDate($now) <= 0) {
        
            $facebook = new HH_Job_Facebook();
            $facebook->add($subject->getFarm(), $event->getEvent(), $subject);
        } else if (!empty($subject->facebookId)) {
            
            // should not be published
            $facebook = new HH_Job_Facebook();
            $facebook->add($subject->getFarm(), 'DELETE', $subject);
        }
    }

    
    protected function _addTwitterJob(HH_Observer_Subject $subject,
        HH_Observer_Event $event = null)
    {
        // If not enabled on insert, skip
        if ($event->getEvent() == 'INSERT' 
            && $subject->publish != HHF_Domain_Post::PUBLISH_PUBLISHED) {
            
            return;
        }
        
        // if not enabled on update, but tied to a facebook post, delete
        if ($event->getEvent() == 'UPDATE' 
            && $subject->publish != HHF_Domain_Post::PUBLISH_PUBLISHED 
            && !empty($subject->twitterId)) {
            
            $twitter = new HH_Job_Twitter();
            $twitter->add($subject->getFarm(), 'DELETE', $subject);
            
            return;
        }
        
        // if not enbaled on update, skip
        if ($event->getEvent() == 'UPDATE' 
            && $subject->publish != HHF_Domain_Post::PUBLISH_PUBLISHED) {

            return;
        }
        
        // if on delete it is not tied to a facebook post, skip
        if ($event->getEvent() == 'DELETE' && empty($subject->twitterId)) {
            return;
        }
        
        $now = Zend_Date::now();
        $now = $now->getDate();
        $now->setTimezone('UTC');
        
        if ($subject->publishedDatetime->compareDate($now) <= 0) {
        
            $twitter = new HH_Job_Twitter();
            $twitter->add($subject->getFarm(), $event->getEvent(), $subject);
        } else if (!empty($subject->facebookId)) {
            
            // should not be published
            $twitter = new HH_Job_Twitter();
            $twitter->add($subject->getFarm(), 'DELETE', $subject);
        }
    }
}