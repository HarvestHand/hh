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
 * @copyright $Date: 2012-12-23 12:31:52 -0400 (Sun, 23 Dec 2012) $
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Job
 */

/**
 * Description of Sitemap
 *
 * @package   HH_Job
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Sitemap.php 600 2012-12-23 16:31:52Z farmnik $
 * @copyright $Date: 2012-12-23 12:31:52 -0400 (Sun, 23 Dec 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Job_Sitemap extends HH_Job
{
    /**
     * @var Zend_Mail
     */
    protected $_mail;

    public function __construct($config = array())
    {
        parent::__construct($config);
    }

    public function add(HH_Domain_Farm $farm = null)
    {
        parent::add('sitemap', func_get_args());
    }


    public function process(HH_Domain_Farm $farm = null)
    {
        static $processed = array();

        if ($farm == null) {

            $url = 'http://www.' . Bootstrap::$rootDomain . '/sitemap.xml';
        } else {
            self::generate($farm, true);

            $url = $farm->getBaseUri() . 'sitemap.xml';
        }

        if (array_key_exists($url, $processed)) {
            return;
        }

        $processed[$url] = true;

        $engines = array(
            'http://www.google.com/webmasters/tools/ping?sitemap=',
            'http://www.bing.com/webmaster/ping.aspx?siteMap='
        );

        foreach ($engines as $engine) {

            file_get_contents($engine . urlencode($url));

            sleep(5);
        }
    }

    public static function generate(HH_Domain_Farm $farm, $regenerate = false)
    {
        $cache = Bootstrap::getZendCacheFile();
        $id = $farm->id . '_sitemap';

        if (!$regenerate && ($sitemap = $cache->load($id)) !== false) {
            return $sitemap;
        }

        $baseUri = rtrim($farm->getBaseUri(), '/');

        $view = new Zend_View();
        $view->setBasePath(Bootstrap::$root . 'layouts');

        $view->urls = array();

        $view->urls[] = array(
            'loc' => $baseUri
        );

        $view->urls[] = array(
            'loc' => $baseUri . '/contact'
        );

        $nav = new HHF_Navigation($farm);

        /* @var $nav HHF_Navigation */
        foreach ($nav->getPages() as $page) {
            /* @var $page Zend_Navigation_Page_Uri */

            $href = $page->getHref();

            if (stripos($href, 'http') === 0) {
                continue;
            } else {
                $view->urls[] = array(
                    'loc' => $baseUri . $href
                );
            }

            if ($page->hasChildren()) {
                foreach ($page->getPages() as $page2) {

                    $href = $page2->getHref();

                    if (stripos($href, 'http') === 0) {
                        continue;
                    } else {

                        $view->urls[] = array(
                            'loc' => $baseUri . $href
                        );
                    }
                }
            }
        }

        if ($farm->getPreferences()->get('enabled', 'shares', true)) {
            $deliveries = HHF_Domain_Delivery::fetchDeliveries($farm);

            foreach ($deliveries as $delivery) {

                $view->urls[] = array(
                    'loc' => $baseUri
                        . '/shares?week=' . $delivery['week'],
                    'lastmod' => $delivery['updatedDatetime']
                        ->get(Zend_Date::W3C)
                );
            }
        }

        if ($farm->getPreferences()->get('blogEnabled', 'website', true)) {
            $posts = HHF_Domain_Post::fetch(
                $farm,
                array(
                    'columns' => array(
                        'id',
                        'token',
                        'updatedDatetime'
                    ),
                    'order' => array(
                        array(
                            'column' => 'publishedDatetime',
                            'dir' => 'DESC'
                        )
                    ),
                    'where' => $where = array(
                        'publish' => HHF_Domain_Post::PUBLISH_PUBLISHED,
                        'DATE(publishedDatetime) <= DATE(NOW())'
                    )
                )
            );

            foreach ($posts as $post) {
                $view->urls[] = array(
                    'loc' => $baseUri
                        . '/blog/post/' . $post->token,
                    'lastmod' => $post['updatedDatetime']
                        ->get(Zend_Date::W3C)
                );
            }
        }

        if ($farm->getPreferences()->get('enabled', 'newsletter', true)) {
            $issues = HHF_Domain_Issue::fetch(
                $farm,
                array(
                    'columns' => array(
                        'id',
                        'title',
                        'token',
                        'publish',
                        'archive',
                        'publishedDatetime',
                        'updatedDatetime'
                    ),
                    'where' => array(
                        'publish' => 1,
                        'archive' => 1
                    ),
                    'order' => array(
                        array(
                            'column' => 'publishedDatetime',
                            'dir' => 'desc'
                        )
                    )
                )
            );

            foreach ($issues as $issue) {
                $view->urls[] = array(
                    'loc' => $baseUri
                        . '/newsletter/issue/id/' . $issue->token,
                    'lastmod' => $issue['updatedDatetime']
                        ->get(Zend_Date::W3C)
                );
            }
        }

        $sitemap = $view->render('sitemap.xml.phtml');

        $cache->save($sitemap, $id, array(), null);

        return $sitemap;
    }
}