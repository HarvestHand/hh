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
 * @copyright $Date: 2012-04-06 09:15:02 -0300 (Fri, 06 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Help
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: ImportFeeds.php 498 2012-04-06 12:15:02Z farmnik $
 * @copyright $Date: 2012-04-06 09:15:02 -0300 (Fri, 06 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Tools_Console_Task_ImportFeeds extends HH_Tools_Console_Task
{
    protected $_task = 'import_feeds';
    
    public function run()
    {
        if (($pid = $this->_console->isLocked($this->_task)) !== false) {
            $this->_console->outputText(
                'Task \'import_feeds\' locked with a PID of ' . $pid
            );
            return HH_Tools_Console::ERROR_LOCK;
        }

        $this->_console->setLock($this->_task);
        
        try {

            $this->_import();
            
            $this->_console->outputText('Ran Import');

        } catch (Exception $e) {
            $this->_console->removeLock($this->_task);
            throw $e;
        }

        $this->_console->removeLock($this->_task);
        
        return HH_Tools_Console::ERROR_NONE;
    }
    
    protected function _import()
    {
        $oauthOptions = array(
            'requestScheme'        => Zend_Oauth::REQUEST_SCHEME_QUERYSTRING,
            'version'              => '1.0',
            'consumerKey'          => '',
            'consumerSecret'       => '',
            'signatureMethod'      => 'HMAC-SHA1',
            'requestTokenUrl'      => 'https://www.google.com/accounts/OAuthGetRequestToken',
            'userAuthorizationUrl' => 'https://www.google.com/accounts/OAuthAuthorizeToken',
            'accessTokenUrl'       => 'https://www.google.com/accounts/OAuthGetAccessToken',
            'callbackUrl'          => 'http://www.harvesthand.com/planet/oauth'
        );
        
        $config = Bootstrap::getZendConfig()->resources->google->reader->toArray();
        $farm = new HH_Domain_Farm(Bootstrap::getZendConfig()->resources->hh->farm);
        
        $ot = $farm->getPreferences()->get('ot', 'planet', time() - 94608000);
        
        $oauthOptions['consumerKey'] = $config['consumerKey'];
        $oauthOptions['consumerSecret'] = $config['consumerSecret'];
        
        $token = new Zend_Oauth_Token_Access();
        
        $token->setToken($config['token'])
            ->setTokenSecret($config['tokenSecret']);
        
        $http = $token->getHttpClient($oauthOptions);
        
        $http->setUri($this->_buildUri($ot));
        $result = $http->request('GET');
        
        if ($result->isSuccessful()) {
            $contents = Zend_Json::decode($result->getBody());
        } else {
            throw new Exception('Googler Reader Error: ' . $result->getMessage());
        }
        
        unset($result);
        
        if (!empty($contents['items'])) {
            $blogCategories = array();
            $titles = array();
            
            foreach ($contents['items'] as $post) {
                $key = hash('sha256', $post['title'] . $post['origin']['htmlUrl']);
                
                if (array_key_exists($key, $titles)) {
                    continue;
                } else {
                    $titles[$key] = true;
                }
                
                $planetPost = new HH_Domain_Post($post['id']);
                
                if (empty($post['alternate'][0]['href'])) {
                    continue;
                }
                
                // test url
                try {
                    $httpClient = new Zend_Http_Client(
                        $post['alternate'][0]['href'],
                        array(
                            'useragent' => 'HarvestHand (http://planet.harvesthand.com)'
                        )
                    );
                    $result = $httpClient->request();
                    
                    if ($result->isError()) {
                        if (!$planetPost->isEmpty()) {
                            $planetPost->delete();
                        }

                        continue;
                    }
                    
                } catch (Exception $e) {
                    if (!$planetPost->isEmpty()) {
                        $planetPost->delete();
                    }
                    
                    continue;
                }
                
                unset($result);
                
                $category = 'farmers';
                $tags = array();
                
                if (!empty($post['categories'])) {
                    foreach ($post['categories'] as $postCategory) {
                        if (substr($postCategory, 0, 4) == 'user') {
                            if (strpos($postCategory, 'label') !== false) {
                                $pieces = explode('/', $postCategory);
                                
                                $category = array_pop($pieces);
                            }
                            
                            continue;
                        }
                        
                        $tags[] = $postCategory;
                    }
                } 
                
                if (!array_key_exists($this->_scrubBlogUrl($post['origin']['htmlUrl']), $blogCategories)) {
                    
                    $blogCategories[$this->_scrubBlogUrl($post['origin']['htmlUrl'])] = $category;
                    
                }
                
                $published = new Zend_Date();
                $published->setTimezone('UTC');
                $published->setTimestamp((int)$post['published']);
                
                if ($post['published'] > $ot) {
                    $ot = (int) $post['published'];
                }
                
                $updated = new Zend_Date();
                $updated->setTimezone('UTC');
                $updated->setTimestamp((int)$post['updated']);
                
                $summary = !empty($post['summary']['content']) ? $post['summary']['content'] : null;
                $content = !empty($post['content']['content']) ? $post['content']['content'] : null;
                
                $media = $this->_seedImage(
                    $post['id'],
                    (!empty($post['mediaGroups']) ? $post['mediaGroups'] : null),
                    $summary, 
                    $content, 
                    $post['title'],
                    $tags,
                    $category
                );
                
                $data = array(
                    'id' => $post['id'],
                    'crawlTimeMsec' => $post['crawlTimeMsec'],
                    'timestampUsec' => $post['timestampUsec'],
                    'title' => html_entity_decode($post['title'], ENT_QUOTES, 'UTF-8'),
                    'addedDatetime' => $published,
                    'updatedDatetime' => $updated,
                    'postUrl' => (!empty($post['alternate'][0]['href']) ? $post['alternate'][0]['href'] : null),
                    'category' => $blogCategories[$this->_scrubBlogUrl($post['origin']['htmlUrl'])],
                    'media' => (!empty($media) ? serialize($media) : null),
                    'summary' => $summary,
                    'content' => $content,
                    'author' => (!empty($post['author']) ? $post['author'] : null),
                    'streamId' => $post['origin']['streamId'],
                    'blogName' => $post['origin']['title'],
                    'blogUrl' => $this->_scrubBlogUrl($post['origin']['htmlUrl']),
                    'tags' => serialize($tags)
                );
                
                if (!$planetPost->isEmpty()) {
                    $planetPost->update($data);
                    
                    $this->_console->outputText('Updated ' . $data['title']);
                } else {
                    $planetPost->insert($data);
                    
                    $this->_console->outputText('Inserted ' . $data['title']);
                }
            }
        }
        
        $ot = $farm->getPreferences()->replace('ot', $ot, 'planet');
        
        $this->_postImport();
    }
    
    protected function _postImport()
    {
        
    }


    protected function _scrubBlogUrl($url)
    {
        if (stripos($url, 'feed.rss') !== false) {
            $url = str_replace(
                array(
                    'feed.rss',
                    '/feed'
                ),
                '',
                $url
            );
        }
        
        return $url;
    }
    
    protected function _buildUri($ot)
    {
        return 'https://www.google.com/reader/api/0/stream/contents/user/-/state/com.google/reading-list?ot=' 
            . $ot . '&r=o&n=1000&mediaRss=true&client=HH';
    }
    
    protected function _seedImage($id, $media, $summary, $contents, $title, $tags, $category) 
    {
        $id = HH_Tools_String::convertToCacheSafe($id);
        $path = Bootstrap::getZendConfig()->resources->files->path 
            . DIRECTORY_SEPARATOR . 'planet' 
            . DIRECTORY_SEPARATOR . $id . DIRECTORY_SEPARATOR;
        
        if (!empty($media[0]['contents'])) {
            foreach ($media[0]['contents'] as $key => $imageData) {
                if (empty($imageData['url'])) {
                    continue;
                }
                
                if (strpos($imageData['url'], 'gravatar') !== false) {
                    if (!empty($media[0]['contents'][$key + 1])) {
                        continue;
                    }
                }
                
                try {
                    
                    return $this->_buildImage($imageData['url'], $path);
                    
                } catch (Exception $e) {
                    $this->_console->outputText('Failed to create image ' . $e->getMessage());
                    continue;
                }
                
            }
        }
        
        // scrape content for images
        $images = array();
        
        if (!empty($contents)) {
            $images += $this->_scrapeImages('<body>' . $contents . '</body>');
        }

        if (!empty($summary)) {
            $images += $this->_scrapeImages('<body>' . $summary . '</body>');
        }
        
        if (!empty($images)) {
            foreach ($images as $image) {
                try {
                    
                    return $this->_buildImage($image, $path);
                    
                } catch (Exception $e) {
                    $this->_console->outputText('Failed to create image ' . $e->getMessage());
                    continue;
                }
            }
        }
        
        // get random image
        $url = 'http://api.flickr.com/services/rest/';
        
        $params = array(
            'method' => 'flickr.photos.search',
            'api_key' => Bootstrap::getZendConfig()->resources->yahoo->flickr->consumerKey,
            'license' => '5,1,2,3,6,7',
            'sort' => 'interestingness-asc',
            'media' => 'photos',
            'format' => 'php_serial',
            'text' => ''
        );
        
        $search = array($title);
        $search[] = $category;
        $search += $tags;
        
        foreach ($search as $term) {
            $params['text'] = $term;
            
            $query = file_get_contents($url . '?' . http_build_query($params));
            
            if (!$query) {
                continue;
            }
            
            $queryData = unserialize($query);
            
            if (empty($queryData['photos']['photo'])) {
                continue;
            }
            
            $photos = array_slice($queryData['photos']['photo'], 0, 50);
            
            $photo = $photos[array_rand($photos)];
            
            $imageUrl = 'http://farm' . $photo['farm'] . '.staticflickr.com/' 
                . $photo['server'] . '/' . $photo['id'] . '_' 
                . $photo['secret'] . '_m.jpg';
            
            try {
                return $this->_buildImage($imageUrl, $path);
            } catch (Exception $e) {
                $this->_console->outputText('Failed to create image ' . $e->getMessage());
                continue;
            } 
        }
        
    }
    
    protected function _buildImage($url, $savePath)
    {
        try {
        
            $tempFile = tempnam(sys_get_temp_dir(), 'planet');
            
            try {
                $httpClient = new Zend_Http_Client(
                    $url,
                    array(
                        'useragent' => 'HarvestHand (http://planet.harvesthand.com)'
                    )
                );
                $result = $httpClient->request();

                if ($result->isError()) {
                    unlink($tempFile);
                    return;
                }
                
            } catch (Exception $e) {
                unlink($tempFile);
                return;
            }
            
            file_put_contents($tempFile, $result->getBody());
            unset($result);

            $im = new Imagick($tempFile);

            if ($im->getImageHeight() < 40) {
                throw new Exception('Image too small');
            }
            
            $im->thumbnailImage(192, null, false);

            @mkdir($savePath, 0777, true);

            $im->writeImage($savePath . 'img.png');

            unlink($tempFile);

            return array(
                'width'  => $im->getImageWidth(),
                'height' => $im->getImageHeight()
            );
        } catch (Exception $e) {
            unlink($tempFile);
            throw $e;
        }
    }
    
    protected function _scrapeImages($html)
    {
        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $images = array();
        
        foreach($dom->getElementsByTagName('img') as $img) {
            $images[] = $img->getAttribute("src");
        }
        
        return $images;
    }
    
    protected function _dedupe()
    {
        
    }
}