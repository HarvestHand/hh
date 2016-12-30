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
 * @copyright $Date: 2012-03-16 00:19:38 -0300 (Fri, 16 Mar 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HHF_Navigation
 */

/**
 * Description of Navigation
 *
 * @package   HHF_Navigation
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Navigation.php 470 2012-03-16 03:19:38Z farmnik $
 * @copyright $Date: 2012-03-16 00:19:38 -0300 (Fri, 16 Mar 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HHF_Navigation extends Zend_Navigation
{
    /**
     * @var HH_Domain_Farm
     */
    protected $_farm;
    protected static $_instances = array();

    /**
     * Navigation constructor
     *
     * @param HH_Domain_Farm $farm
     */
    public function  __construct(HH_Domain_Farm $farm)
    {
        $this->_farm = $farm;

        $this->_init();
    }

    /**
     * Singleton
     * 
     * @param HH_Domain_Farm $farm
     * @return HHF_Navigation
     */
    public static function singleton(HH_Domain_Farm $farm)
    {
        $farmId = (string) $farm;

        if (isset(self::$_instances[$farmId])) {
            return self::$_instances[$farmId];
        }

        self::$_instances[$farmId] = new self($farm);

        return self::$_instances[$farmId];
    }

    protected function _init()
    {
        $pages = array();
        $parentPages = array();

        $webPages = HHF_Domain_Page::fetchPages(
            $this->_farm,
            array(
                'order' => HHF_Domain_Page::ORDER_PARENT_SORT,
                'summary' => true,
                'status' => HHF_Domain_Page::STATUS_PUBLISHED
            )
        );
        
        if ($this->_farm->getPreferences()->get('blogEnabled', 'website', true)) {
            $blog = new stdClass();
            $blog->parent = null;
            $blog->sort = 1000;
            $blog->title = Bootstrap::getZendTranslate()->_('Blog');
            $blog->token = 'blog';
            $blog->id = 'blog';
            
            $webPages[] = $blog;
        }
        
        if ($this->_farm->getPreferences()->get('enabled', 'newsletter', true)) {
            if (HHF_Domain_Issue::hasIssues($this->_farm)) {
            
                $newsletter = new stdClass();
                $newsletter->parent = null;
                $newsletter->sort = 1000;
                $newsletter->title = Bootstrap::getZendTranslate()->_('Newsletter');
                $newsletter->token = 'newsletter';
                $newsletter->id = 'newsletter';

                $webPages[] = $newsletter;
            }
        }
        
        if ($this->_farm->getPreferences()->get('enabled', 'shares', true)) {
            
            $sharesTerm = ucfirst(
                $this->_farm->getPreferences()
                    ->get('shares', 'shares', 'shares')
            );
            
            $register = new stdClass();
//            $register->parent = 'shares';
            $register->sort = 2;
            $register->title = Bootstrap::getZendTranslate()->_('Sign Up');
            $register->token = 'shares/register';
            $register->id = 'shares-register';
            
            $farmer = HH_Domain_Farmer::getAuthenticated();
            
            if (($farmer instanceof HH_Domain_Farmer) 
                && $farmer->role == HH_Domain_Farmer::ROLE_MEMBER) {
                
                $shares = new stdClass();
                $shares->parent = null;
                $shares->sort = 1000;
                $shares->token = 'shares';
                $shares->id = 'shares';
                $shares->title = sprintf(
                    Bootstrap::getZendTranslate()->_('My %s'),
                    $sharesTerm
                );
                
                $webPages[] = $shares;
                
                if (HHF_Domain_Delivery::hasDeliveries($this->_farm)) {
                    $previousShares = new stdClass();
                    $previousShares->parent = 'shares';
                    $previousShares->sort = 1;
                    $previousShares->title = sprintf(
                        Bootstrap::getZendTranslate()->_('Previous %s'),
                        $sharesTerm
                    );
                    $previousShares->token = 'previous';
                    $previousShares->id = 'previous';
                    
                    $webPages[] = $previousShares;
                }
                
                $webPages[] = $register;
                
                
            } else {
            
                if (HHF_Domain_Delivery::hasDeliveries($this->_farm)) {
                    $shares = new stdClass();
                    $shares->parent = null;
                    $shares->sort = 1000;
                    $shares->title = Bootstrap::getZendTranslate()->_($sharesTerm);
                    $shares->token = 'shares';
                    $shares->id = 'shares';
                    $webPages[] = $shares;
                }
                
                $webPages[] = $register;
            }
        }
        
        if (!empty($_SERVER['SCRIPT_URL'])) {
            $scriptUrl = rtrim($_SERVER['SCRIPT_URL'], '/');
        } else {
            $scriptUrl = null;
        }
        
        foreach ($webPages as $page) {
            
            $active = false;

            if (empty($page->parent)) {
                
                if ($page->sort == 0) {
                    if (isset($page->target) 
                        && $page->target == HHF_Domain_Page::TARGET_EXTERNAL 
                        && !empty($page->url)) {
                        
                        $uri = $page->url;
                    } else {
                        $uri = '/';
                    }
                } else {
                    if (isset($page->target) 
                        && $page->target == HHF_Domain_Page::TARGET_EXTERNAL 
                        && !empty($page->url)) {
                        
                        $uri = $page->url;
                        
                    } else {
                    
                        $uri = '/' . $page->token;
                    }
                }

                if (strcasecmp($scriptUrl, $uri) === 0 || ($uri == '/' && empty($scriptUrl))) {
                    $active = true;
                } else {
                    $active = false;
                }
                
                $pages[$page->id] = array(
                    'label' => $page->title,
                    'id'    => 'webpage-' . $page->id,
                    'title' => $page->title,
                    'uri'   => $uri,
                    'token' => $page->token,
                    'active' => $active,
                    'pages' => array()
                );
            } else if (!empty($pages[$page->parent])) {

                if (isset($page->target) 
                    && $page->target == HHF_Domain_Page::TARGET_EXTERNAL 
                    && !empty($page->url)) {

                    $uri = $page->url;

                } else {
                    $uri = '/' . $pages[$page->parent]['token'] 
                           . '/' . $page->token;
                }

                if (strcasecmp($scriptUrl, $uri) === 0) {
                    $active = true;
                } else {
                    $active = false;
                }
                
                $pages[$page->parent]['pages'][] = array(
                    'label' => $page->title,
                    'id'    => 'webpage-' . $page->id,
                    'title' => $page->title,
                    'uri'   => $uri,
                    'active' => $active,
                );
            }
        }
        
        if (!empty($parentPages)) {
            
            if (empty($scriptUrl)) {
                $active = true;
            } else {
                $active = false;
            }
            
            $homePageNav = array(
                'label' => $homePage->title,
                'id'    => 'webpage-' . $homePage->id,
                'title' => $homePage->title,
                'uri'   => '/',
                'active' => $active,
                'pages' => $parentPages
            );

            array_unshift($pages, $homePageNav);
        }

        if (!empty($pages)) {

            $this->addPages($pages);
        }
    }
}