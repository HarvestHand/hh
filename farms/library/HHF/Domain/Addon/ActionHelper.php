<?php

class HHF_Domain_Addon_ActionHelper extends HH_Object_ActionHelper
{
    protected $locations = null;
    protected $translate = null;

    public function __construct($name)
    {
        parent::__construct($name);

        $this->translate = Bootstrap::getZendTranslate();
    }

    public function shopping()
    {
        $this->_actionController->theme = new HHF_Theme_Shopping();
        $this->_actionController->theme->bootstrap($this->_actionController);

        $this->assignShoppingDefaults();

        if ($this->_actionController->getRequest()->isXmlHttpRequest()) {
            return $this->shoppingEmail();
        }

        $options = array(
            'fetch' => HHF_Domain_Addon::FETCH_PURCHASEABLE,
            'locations' => array(
                array(
                    'id' => $this->_actionController->view->currentLocation['id']
                )
            ),
            'networkStatus' => HH_Domain_Network::STATUS_APPROVED,
            'orderBy' => 'source',
            'search' => $this->_actionController->view->search
        );

        if (!empty($this->_actionController->view->category)) {
            $options['categoryId'] = $this->_actionController->view->category;
        }

        if (!empty($this->_actionController->view->source)) {
            $options['source'] = $this->_actionController->view->source;
        }

        $this->_actionController->view->selectedSources = array();
        $this->_actionController->view->selectedCategories = array();

        $this->_actionController->view->addons = HHF_Domain_Addon::fetchAddons(
            $this->_actionController->farm,
            $options
        );

        if (empty($this->_actionController->view->source) || empty($this->_actionController->view->category)) {

            $addSource = empty($this->_actionController->view->source) ? true : false;
            $addCategory = empty($this->_actionController->view->category) ? true : false;

            foreach ($this->_actionController->view->addons as $addon) {
                if ($addSource) {
                    if (!empty($addon['source'])) {
                        $key = preg_replace(
                            array(
                                '/[ ]/msx',
                                '/[^A-Za-z0-9\-_\.]/msx'
                            ),
                            array(
                                '-',
                                ''
                            ),
                            $addon['source']
                        );

                        $this->_actionController->view->selectedSources[$key] = $addon['source'];
                    }
                }

                if ($addCategory) {
                    if (!empty($addon['categoryId'])) {
                        $key = preg_replace(
                            array(
                                '/[ ]/msx',
                                '/[^A-Za-z0-9\-_\.]/msx'
                            ),
                            array(
                                '-',
                                ''
                            ),
                            $addon['categoryId']
                        );

                        $this->_actionController->view->selectedCategories[$key] = $addon['categoryName'];
                    }
                }
            }
        }

        if (empty($this->_actionController->view->category)) {

        }

        $this->_actionController->view->headTitle(
            $this->translate->translate('What\'s Fresh')
        );
    }

    protected function assignShoppingDefaults()
    {
        $this->_actionController->view->week = $this->getShoppingDay();
        $this->_actionController->view->location = $this->getShoppingLocationId(
            $this->_actionController->view->week->format('N')
        );

        $this->_actionController->view->locations = $this->buildSortedLocations();

        foreach ($this->_actionController->view->locations as $location) {
            if ($location['id'] == $this->_actionController->view->location) {
                $currentLocation = $location;
                break;
            }
        }

        $this->_actionController->view->currentLocation = $currentLocation;

        $this->_actionController->view->category = $this->_actionController->getRequest()->getParam('category', false);

        $this->_actionController->view->search = $this->_actionController->getRequest()->getParam('search');

        $this->_actionController->view->categories = array();

        $categories = HHF_Domain_Addon_Category::fetch(
            $this->_actionController->farm,
            array(
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        foreach ($categories as $category) {
            $this->_actionController->view->categories[$category['id']] = $category;
        }

        reset($this->_actionController->view->categories);

        $this->_actionController->view->source = $this->_actionController->getRequest()->getParam('source');

        if (empty($this->_actionController->view->category) && empty($this->_actionController->view->source)) {
            if (count($this->_actionController->view->categories)) {
                $this->_actionController->view->category = key($this->_actionController->view->categories);
            }
        }

        $this->_actionController->view->sources = HHF_Domain_Addon::fetchSources(
            $this->_actionController->farm,
            array(
                'status' => HH_Domain_Network::STATUS_APPROVED
            )
        );

        $this->_actionController->view->getFormValue()->setDefaulVars(
            array(
                'category' => $this->_actionController->view->category,
                'location' => $this->_actionController->view->location,
                'week' => $this->_actionController->view->week->format('o\WWN'),
                'source' => $this->_actionController->view->source
            )
        );

        $this->_actionController->view->disclaimer = $this->_actionController->farm->getPreferences()
            ->get('disclaimer', 'website', '');
    }

    protected function shoppingEmail()
    {
        $this->_actionController->setNoRender();
        $this->_actionController->getHelper('layout')->disableLayout();

        $this->_actionController->getResponse()->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $addons = $this->_actionController->getRequest()->getParam('addons');
        $emailAddress = $this->_actionController->getRequest()->getParam('email');

        $emailValidator = new Zend_Validate_EmailAddress();

        if (!$emailValidator->isValid($emailAddress)) {
            return $this->_actionController
                ->getResponse()
                ->setBody(
                    Zend_Json::encode(
                        array(
                            'status' => false,
                            'message' => $this->translate->_('Email doesn\'t look right')
                        )
                    )
                );
        }

        if (!is_array($addons) || empty($addons)) {
            return $this->_actionController
                ->getResponse()
                ->setBody(
                    Zend_Json::encode(
                        array(
                            'status' => false,
                            'message' => $this->translate->_('Shopping list looks empty?!')
                        )
                    )
                );
        }

        $addonIds = array();

        foreach ($addons as $addon) {
            if (is_numeric($addon)) {
                $addonIds[] = $addon;
            }
        }

        if (empty($addonIds)) {
            return $this->_actionController
                ->getResponse()
                ->setBody(
                    Zend_Json::encode(
                        array(
                            'status' => false,
                            'message' => $this->translate->_('Shopping list looks empty?!')
                        )
                    )
                );
        }

        $addons = HHF_Domain_Addon::fetchAddons(
            $this->_actionController->farm,
            array(
                'fetch' => HHF_Domain_Addon::FETCH_PURCHASEABLE,
                'ids' => $addonIds,
                'locations' => array(
                    array(
                        'id' => $this->_actionController->view->currentLocation['id']
                    )
                )
            )
        );

        if (empty($addons)) {
            return $this->_actionController
                ->getResponse()
                ->setBody(
                    Zend_Json::encode(
                        array(
                            'status' => false,
                            'message' => $this->translate->_('Shopping list looks empty?!')
                        )
                    )
                );
        }

        $layout = new Zend_Layout();
        $layout->setLayoutPath(Bootstrap::$farmRoot . 'layouts/scripts/');
        $layout->setLayout('email');
        $layout->getView()->farm = $this->_actionController->farm;

        $view = new Zend_View();
        $view->setScriptPath($this->_actionController->view->getScriptPaths());
        $view->addons = $addons;
        $view->week = $this->_actionController->view->week;
        $view->currentLocation = $this->_actionController->view->currentLocation;
        $view->farm = $this->_actionController->farm;

        if (!empty($this->_actionController->farm->email)) {
            $replyTo = array(
                $this->_actionController->farm->email,
                $this->_actionController->farm->name
            );
            $from = array(
                $this->_actionController->farm->email,
                $this->_actionController->farm->name
            );
        } else {
            $from = array(
                'team@harvesthand.com'
            );
            $replyTo = null;
        }

        $layout->content = $view->render('public/shopping-email.phtml');

        $email = new HH_Job_Email();
        $email->add(
            $from,
            array(
                $emailAddress
            ),
            sprintf(
                $this->translate->_('Your Shopping List For %s'),
                $this->_actionController->farm->name
            ),
            null,
            $layout->render(),
            $replyTo,
            null,
            null,
            'farmnik@harvesthand.com',
            'farmnik@harvesthand.com'
        );

        return $this->_actionController
            ->getResponse()
            ->setBody(
                Zend_Json::encode(
                    array(
                        'status' => true,
                        'message' => $this->translate->_('Bon voyage!  Your shopping list has been email to you.')
                    )
                )
            );

    }

    protected function getShoppingLocationId($dayOfWeek)
    {
        $locationId = $this->_actionController->getRequest()->getParam('location');
        $isValid = false;
        $defaultLocation = null;

        foreach ($this->getLocations() as $location) {
            if ($location['id'] == $locationId) {
                $isValid = true;
                break;
            }
        }

        if ($isValid) {
            return $locationId;
        }

        foreach ($this->getLocations() as $location) {
            if ($location['dayOfWeek'] == $dayOfWeek) {
                return $location['id'];
                break;
            }
        }

        foreach ($this->getLocations() as $location) {
            if ($location['dayOfWeek'] > $dayOfWeek) {
                return $location['id'];
                break;
            }
        }

        foreach ($this->getLocations() as $location) {
            if ($location['dayOfWeek'] < $dayOfWeek) {
                return $location['id'];
                break;
            }
        }
    }

    protected function buildSortedLocations()
    {
        $rawLocations = $this->getLocations();
        $locations = array();
        $currentWeek = $this->getFirstAvailableLocationDate();

        $weekDate = new DateTime();
        $weekDate->setTimezone(new DateTimeZone($this->_actionController->farm['timezone']));

        $timeFormatter = new IntlDateFormatter(
            Bootstrap::$locale,
            IntlDateFormatter::NONE,
            IntlDateFormatter::SHORT,
            $this->_actionController->farm['timezone']
        );

        $dateFormatter = new IntlDateFormatter(
            Bootstrap::$locale,
            IntlDateFormatter::TRADITIONAL,
            IntlDateFormatter::NONE,
            $this->_actionController->farm['timezone']
        );

        foreach ($rawLocations as $location) {

            $locationWeek = clone $currentWeek;
            $dayOfWeek = $currentWeek->format('N');

            if ($dayOfWeek > $location['dayOfWeek']) {
                $locationWeek->add(new DateInterval('P' . (7 - $dayOfWeek + $location['dayOfWeek']) . 'D'));
            } else if ($dayOfWeek < $location['dayOfWeek']) {
                $locationWeek->add(new DateInterval('P' . ($location['dayOfWeek'] - $dayOfWeek) . 'D'));
            }

            list($hour, $min, $sec) = explode(':', $location['timeStart']);

            $weekDate->setTime($hour, $min, $sec);

            $startTime = $timeFormatter->format($weekDate);

            list($hour, $min, $sec) = explode(':', $location['timeEnd']);

            $weekDate->setTime($hour, $min, $sec);

            $endTime = $timeFormatter->format($weekDate);

            $title = sprintf(
                $this->translate->_('%s, %s from %s to %s'),
                $location['name'],
                $dateFormatter->format($locationWeek),
                $startTime,
                $endTime
            );

            $date = sprintf(
                $this->translate->_('%s from %s to %s'),
                $dateFormatter->format($locationWeek),
                $startTime,
                $endTime
            );

            $locationData = array(
                'value' => $location['name'],
                'title' => $title,
                'date' => $date,
                'week' => $locationWeek->format('o\WWN'),
                'dayOfWeek' => $location['dayOfWeek'],
                'timeStart' => $location['timeStart'],
                'timeEnd' => $location['timeEnd'],
                'id' => $location['id']
            );

            $locations[] = $locationData;
        }

        usort($locations, function($a, $b) use ($dayOfWeek) {

            if ($a['dayOfWeek'] < $dayOfWeek && $b['dayOfWeek'] > $dayOfWeek) {
                return -1;
            }

            if ($a['dayOfWeek'] > $dayOfWeek && $b['dayOfWeek'] < $dayOfWeek) {
                return 1;
            }

            if ($a['dayOfWeek'] >= $dayOfWeek && $b['dayOfWeek'] >= $dayOfWeek) {
                if ($a['dayOfWeek'] > $b['dayOfWeek']) {
                    return 1;
                }

                if ($a['dayOfWeek'] < $b['dayOfWeek']) {
                    return -1;
                }

                if ($a['dayOfWeek'] == $b['dayOfWeek']) {
                    $aDate = new DateTime();
                    list($hour, $minute, $second) = explode(':', $a['timeStart']);
                    $aDate->setTime($hour, $minute, $second);

                    $bDate = new DateTime();
                    list($hour, $minute, $second) = explode(':', $b['timeStart']);
                    $bDate->setTime($hour, $minute, $second);

                    if ($aDate < $bDate) {
                        return -1;
                    } else if ($aDate > $bDate) {
                        return 1;
                    }

                    return 0;
                }
            }

            if ($a['dayOfWeek'] < $dayOfWeek && $b['dayOfWeek'] < $dayOfWeek) {
                if ($a['dayOfWeek'] > $b['dayOfWeek']) {
                    return 1;
                }

                if ($a['dayOfWeek'] < $b['dayOfWeek']) {
                    return -1;
                }

                if ($a['dayOfWeek'] == $b['dayOfWeek']) {
                    $aDate = new DateTime();
                    list($hour, $minute, $second) = explode(':', $a['timeStart']);
                    $aDate->setTime($hour, $minute, $second);

                    $bDate = new DateTime();
                    list($hour, $minute, $second) = explode(':', $b['timeStart']);
                    $bDate->setTime($hour, $minute, $second);

                    if ($aDate < $bDate) {
                        return -1;
                    } else if ($aDate > $bDate) {
                        return 1;
                    }

                    return 0;
                }
            }

            if ($a['dayOfWeek'] > $b['dayOfWeek']) {
                return 1;
            }

            if ($a['dayOfWeek'] < $b['dayOfWeek']) {
                return -1;
            }

            if ($a['dayOfWeek'] == $b['dayOfWeek']) {
                return 0;
            }
        });

        return $locations;
    }

    protected function getLocations()
    {
        if ($this->locations !== null) {
            return $this->locations;
        }

        $this->locations = HHF_Domain_Location::fetchLocations(
            $this->_actionController->farm,
            array(
                'fetch' => HHF_Domain_Location::FETCH_ENABLED,
                'order' => HHF_Domain_Location::ORDER_DATETIME
            )
        );

        return $this->locations;
    }

    protected function getShoppingDay()
    {
        $day = $this->_actionController->getRequest()->getParam('week');

        if (!$this->validateIsoWeek($day)) {
            return $this->getFirstAvailableLocationDate();

        } else {
            return new DateTime($day);
        }
    }

    protected function getFirstAvailableLocationDate()
    {
        static $cache = null;

        if ($cache instanceof DateTime) {
            return $cache;
        }

        $shopDate = new DateTime();
        $shopDate->setTimezone(new DateTimeZone($this->_actionController->farm['timezone']));

        $setDayOfWeek = false;
        $day = $shopDate->format('N');

        foreach ($this->getLocations() as $location) {
            if ($location['dayOfWeek'] == $day) {
                $setDayOfWeek = true;
                break;
            }
        }

        if (!$setDayOfWeek) {
            foreach ($this->getLocations() as $location) {
                if ($location['dayOfWeek'] > $day) {
                    $shopDate->add(new DateInterval('P' . ($location['dayOfWeek'] - $day) . 'D'));
                    $setDayOfWeek = true;
                    break;
                }
            }
        }

        if (!$setDayOfWeek) {
            foreach ($this->getLocations() as $location) {
                if ($location['dayOfWeek'] < $day) {
                    $shopDate->add(new DateInterval('P' . (7 - $day + $location['dayOfWeek']) . 'D'));
                    $setDayOfWeek = true;
                    break;
                }
            }
        }

        $cache = $shopDate;

        return $shopDate;
    }

    protected function validateIsoWeek($day)
    {
        $today = new DateTime();

        if (!empty($day)) {
            list($year, $week, $day) = sscanf($day, '%04dW%02d%1d');

            if ($year < $today->format('o')) {
                return false;
            }

            if ($week < $today->format('W')) {
                return false;
            }

            if ($day < $today->format('N')) {
                return false;
            }

            $validDayOfWeek = false;

            foreach ($this->getLocations() as $location) {
                if ($location['dayOfWeek'] == $day) {
                    $validDayOfWeek = true;
                    break;
                }
            }

            return $validDayOfWeek;

        } else {
            return false;
        }
    }
}
