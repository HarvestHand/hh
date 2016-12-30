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
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of AdminController
 *
 * @package
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id$
 * @copyright $Date$
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class Shares_AdminController extends HHF_Controller_Action
{
    public function init()
    {
        parent::init();
        $this->validateAuthentiation(HH_Domain_Farmer::ROLE_FARMER);
        $this->view->headTitle($this->translate->translate('Administration'));
    }

    public function indexAction()
    {
        $this->view->shares = HHF_Domain_Share::fetchOne(
            $this->farm,
            array(
                'columns' => 'COUNT(*) as count'
            )
        );

        $this->view->locations = HHF_Domain_Location::fetchOne(
            $this->farm,
            array(
                'columns' => 'COUNT(*) as count'
            )
        );

        $this->view->addons = HHF_Domain_Addon::fetchOne(
            $this->farm,
            array(
                'columns' => 'COUNT(*) as count'
            )
        );

        $this->view->deliveries = HHF_Domain_Delivery::fetchOne(
            $this->farm,
            array(
                'columns' => 'COUNT(*) as count'
            )
        );

        $this->view->shareStats = HHF_Domain_Customer_Share_Collection::fetchStats(
            $this->farm,
            array(
                'year' => date('Y')
            )
        );
    }

    public function sharesAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'columns' => 'year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $this->view->years = array();

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_sharesData($year);
        }

        $this->view->year = $year;

        $this->view->shares = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => '*',
                'where' => array(
                    'year' => $year
                ),
                'limit' => array(
                    'offset' => 0,
                    'rows' => 50
                ),
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->shares->getFoundRows();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _sharesData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('name', 'deliverySchedule', 'enabled');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $query = Bootstrap::getZendDb()->quote('%' . $search . '%');

            $where = array(
                'year' => $year,
                'name LIKE ' . $query,
            );
        } else {
            $where = array(
                'year' => $year
            );
        }

        $rows = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => '*',
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Share::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    ),
                    'where' => array(
                        'year' => $year
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function shareAction()
    {
        $shareId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        $rawLocations = HHF_Domain_Location::fetchLocations(
            $this->farm,
            array('order' => HHF_Domain_Location::ORDER_DATETIME)
        );
        $locations = array();
        $date = Zend_Date::now();

        foreach ($rawLocations as $location) {

            $date->setWeekday($location['dayOfWeek']);
            $date->setTime($location['timeStart'], 'H:m:s');
            $endDate = clone $date;
            $endDate->setTime($location['timeEnd'], 'H:m:s');

            $locations[$location['id']] = array(
                'label' =>  $location['name'] . ', ' . $location['city'],
                'title' =>  sprintf(
                    '%s, %s - %s',
                    $date->get(Zend_Date::WEEKDAY),
                    $date->toString('h:mm a'),
                    $endDate->toString('h:mm a')
                )
            );
        }

        $this->view->locations = $locations;

        if (empty($shareId)) {
            $this->_shareNew();
        } else if ($action != 'delete') {
            $this->_shareEdit($shareId);
        } else {
            $this->_shareDelete($shareId);
        }
    }

    protected function _shareNew()
    {
        $this->view->location = false;

        $this->view->getFormValue()->setDefaulVars(
            array(
                'durations' => array(
                    array(
                        'locations' => array('')
                    )
                ),
                'sizes' => array(
                    array(
                        'size' => 1,
                        'name' => $this->translate->_('Full Share')
                    )
                ),
                'purchaseStartDate' => date('Y-m-d'),
                'locationPrice' => 1
            )
        );

        if (!empty($_POST)) {
            $data = array();
            $this->view->errors = array();

            try {
                $data = HHF_Domain_Share::validate(
                    $_POST,
                    array('farm' => $this->farm)
                );
            } catch (HH_Object_Exception_Validation $error) {
                $this->view->errors = $error->getErrorMessages();
            }

            if (empty($this->view->errors)) {

                $share = new HHF_Domain_Share($this->farm);
                $share->insert($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Share "%s" added!'),
                        $share->name
                    )
                );

                $this->_redirect('/admin/shares/shares', array('exit' => true));
            }
        }
    }

    protected function _shareDelete($id)
    {
        $share = new HHF_Domain_Share($this->farm, $id);

        if ($share->isEmpty()) {
            $this->_redirect('/admin/shares/shares', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Share "%s" was deleted'),
                $share->name
            )
        );

        $share->delete();

        $this->_redirect('/admin/shares/shares', array('exit' => true));
    }

    protected function _shareEdit($id)
    {
        $currentShare = $this->view->share = new HHF_Domain_Share(
            $this->farm,
            $id
        );

        if ($currentShare->isEmpty()) {
            $this->_redirect('/admin/shares/shares', array('exit' => true));
        } else {
            $shareData = $currentShare->toArray(true);

            $shareData['purchaseStartDate'] = (!empty($shareData['purchaseStartDate'])) ?
                Zend_Date::now()->set($shareData['purchaseStartDate'])->toString('yyyy-MM-dd') :
                null;

            $shareData['customerPurchaseStartDate'] = (!empty($shareData['customerPurchaseStartDate'])) ?
                Zend_Date::now()->set($shareData['customerPurchaseStartDate'])->toString('yyyy-MM-dd') :
                null;

            if (!empty($shareData['durations'])) {
                foreach ($shareData['durations'] as &$duration) {

                    $duration['fullPaymentDueDate'] = (!empty($duration['fullPaymentDueDate'])) ? Zend_Date::now()->set($duration['fullPaymentDueDate'])->toString('yyyy-MM-dd') : null;

                    if (!empty($duration['locations'])) {
                        foreach ($duration['locations'] as &$location) {
                            $location = $location['locationId'];
                        }
                    }
                }
            }

            $queryOptions = array(
                'shareId' => $id
            );
            $options = HHF_Domain_Share_VacationOption::fetchWhere($this->farm, $queryOptions);
            $this->view->v = $options;

            $this->view->getFormValue()->setDefaulVars($shareData);
        }

        if (!empty($_POST)) {

            $this->view->errors = array();

            try {
                $data = HHF_Domain_Share::validate(
                    $_POST,
                    array(
                        'farm' => $this->farm,
                        'share' => $currentShare
                    )
                );
            } catch (HH_Object_Exception_Validation $error) {
                $this->view->errors = $error->getErrorMessages();
            }

            // Make an object.
            $vacationObj = new HHF_Domain_Share_VacationOption();
            $vacationObj->setFarm($this->farm);

            // Prepare for new options by deleting existing.
            $vacationObj->deleteOptionsWhereShareId($this->farm, $id);

            // Sanitizing the options. (Why not..)
            $validVacaOpt = filter_var_array($_POST['vacation'], FILTER_SANITIZE_STRING);

            if(!empty($validVacaOpt)){
                foreach($validVacaOpt as $vacaOpt){
                    $vacationArray =
                        array(
                            'shareId' => $id,
                            'vacationOption' => $vacaOpt['option']
                        );

                    $vacationObj->insert($vacationArray);
                }
            }

            if (empty($this->view->errors)){

                $currentShare->update($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Share "%s" updated!'),
                        $currentShare->name
                    )
                );

                $this->_redirect('/admin/shares/share?id='.$id, array('exit' => true));
            }
        }
    }

    public function locationsAction()
    {
        if ($this->_request->isXmlHttpRequest()) {
            return $this->_locationsData();
        }

        $this->view->locations = HHF_Domain_Location::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => '*',
                'order' => array(
                    array(
                        'column' => 'city',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->foundRows = $this->view->locations->getFoundRows();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _locationsData()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('name', 'city', 'time', 'enabled');

	if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    if ($columns[(int) $_GET['iSortCol_' . $i]] == 'time') {
                        $order[] = array(
                            'column' => 'dayOfWeek',
                            'dir' => $dir
                        );
                        $order[] = array(
                            'column' => 'timeStart',
                            'dir' => $dir
                        );

                    } else {

                        $order[] = array(
                            'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                            'dir' => $dir
                        );
                    }
                }
            }
	    }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        if (!empty($search)) {

            $query = Bootstrap::getZendDb()->quote('%' . $search . '%');

            $where = array(
                '(name LIKE ' . $query . ' OR city LIKE ' . $query . ')'
            );
        } else {
            $where = array();
        }

        $rows = HHF_Domain_Location::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => '*',
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Location::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
	);

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function locationAction()
    {
        $locationId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if ($this->_request->isXmlHttpRequest()) {

            // do server side AJAX validation

            $this->setNoRender();
            $this->_helper->layout->disableLayout();

            $this->_response->setHeader(
                'Content-Type',
                'application/json',
                true
            );

            $result = false;

            if (!empty($_GET['country']) && !empty($_GET['subdivision']) &&
                !empty($_GET['unlocode'])) {

                $country = substr($_GET['country'], 0, 2);
                $subdivision = substr($_GET['subdivision'], 0, 3);
                $term = iconv('UTF8', 'ASCII//TRANSLIT', $_GET['unlocode']);
                $result = array();

                if (!empty($country)) {
                    $locations = HH_Tools_Countries::getUnlocodes(
                        $country,
                        $subdivision
                    );
                    foreach ($locations as $location) {
                        $location = iconv(
                            'UTF8',
                            'ASCII//TRANSLIT',
                            $location
                        );

                        if (($pos = stripos($location, $term)) !== false) {
                            if ($pos == 0) {
                                array_unshift($result, $location);
                            } else {
                                array_push($result, $location);
                            }
                        }
                    }
                }

                $this->_response->appendBody(
                    Zend_Json::encode(
                        $result
                    )
                );
            } else if (!empty($_GET['country']) && !empty($_GET['subdivisions'])) {

                $country = substr($_GET['country'], 0, 2);

                $this->_response->appendBody(
                    HH_Tools_Countries::getRawSubdivisions($country)
                );
            }

        } else {
            if (empty($locationId)) {
                $this->_locationNew();
            } else if ($action != 'delete') {
                $this->_locationEdit($locationId);
            } else {
                $this->_locationDelete($locationId);
            }
        }
    }

    protected function _locationNew()
    {
        $this->view->location = false;

        $this->view->getFormValue()->setDefaulVars(
            array(
                'state' => $this->farm->state,
                'country' => $this->farm->country
            )
        );

        if (!empty($_POST)) {
            $filter = HHF_Domain_Location::getFilter(
                HHF_Domain_Location::FILTER_NEW
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $location = new HHF_Domain_Location($this->farm);
                $location->insert($filter->getUnescaped());

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Location "%s" added!'),
                        $location->name
                    )
                );

                $this->_redirect('/admin/shares/locations', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    protected function _locationDelete($id)
    {
        $this->view->location = new HHF_Domain_Location($this->farm, $id);

        if ($this->view->location->isEmpty()) {
            $this->_redirect('/admin/shares/locations', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Location "%s" was deleted'),
                $this->view->location->name
            )
        );

        // Delete the record in the joining table.
        $tempFarm = new HHF_Domain_Share($this->farm);
        $tempFarm->deleteByLocation($this->farm->id, $id);

        $this->view->location->delete();

        $this->_redirect('/admin/shares/locations', array('exit' => true));
    }

    protected function _locationEdit($id)
    {
        $currentLocation = $this->view->location = new HHF_Domain_Location(
            $this->farm,
            $id
        );

        if ($currentLocation->isEmpty()) {
            $this->_redirect('/admin/shares/location', array('exit' => true));
        } else {
            $this->view->getFormValue()->setDefaulVars($currentLocation);
        }

        if (!empty($_POST)) {
            $filter = HHF_Domain_Location::getFilter(
                HHF_Domain_Page::FILTER_EDIT
            );

            $filter->setData($_POST);

            if ($filter->isValid()) {

                $data = $filter->getUnescaped();

                $currentLocation->update($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Location "%s" updated!'),
                        $currentLocation->name
                    )
                );


                $this->_redirect('/admin/shares/locations', array('exit' => true));

            } else {
                $this->view->errors = $filter->getMessages();
            }
        }
    }

    public function addonsCategoriesAction()
    {
        $this->_helper->addHelper(HHF_Domain_Addon_Category::getActionHelper());

        $this->_helper->HHFDomainAddonCategory->bootstrapList();
    }

    public function addonCategoryAction()
    {
        $this->_helper->addHelper(HHF_Domain_Addon_Category::getActionHelper());

        $this->_helper->HHFDomainAddonCategory->bootstrapItem();
    }

    public function addonsAction()
    {
        $this->view->source = $source = $this->_request->getParam('source');
        $format = $this->_request->getParam('format', false);

        if ($this->_request->isXmlHttpRequest()) {
            $action = $this->_request->getParam('a');

            switch ($action) {
                case 'enable' :
                    return $this->_addonEnable();
                    break;
                case 'disable' :
                    return $this->_addonDisable();
                    break;
                case 'setExpiration' :
                    return $this->_addonSetExpiration();
                    break;
                default :
                    return $this->_addonsData($source);
            }
        }

        if ($this->_request->getParam('a') == 'ping' && (int) $this->_request->getParam('distributorId')) {

            $distributor = new HH_Domain_Farm($this->_request->getParam('distributorId'));

            $isInNetwork = false;

            if (!$distributor->isEmpty()) {

                foreach ($distributor->getParentNetworks(HH_Domain_Network::STATUS_APPROVED) as $network) {
                    if ($network->relationId == $this->farm->id) {
                        $isInNetwork = true;
                        break;
                    }
                }

                if ($isInNetwork) {
                    $networkSync = new HH_Job_Networksync();
                    $networkSync->add(
                        $this->farm,
                        array(
                            'distributorId' => (int) $this->_request->getParam('distributorId')
                        )
                    );

                    $messenger = $this->_helper->getHelper('FlashMessenger');

                    $messenger->addMessage(
                        sprintf(
                            $this->translate->_('Products for %s marked as updated!'),
                            $distributor->name
                        )
                    );
                }
            }
        }

        $where = array();

        if (!empty($source)) {
            list($sourceType, $sourceValue) = explode('_', $source, 2);

            if ($sourceType == 'S') {

                if ($sourceValue == '__NULL__') {
                    $where[] = 'source IS NULL';
                } else {
                    $where['source'] = $sourceValue;
                }
            } else if ($sourceType == 'D') {
                if ($sourceValue == '__NULL__') {
                    $where[] = 'distributorId IS NULL';
                } else {
                    $where['distributorId'] = $sourceValue;
                }
            } else if ($sourceType == 'V') {
                if ($sourceValue == '__NULL__') {
                    $where[] = 'vendorId IS NULL';
                } else {
                    $where['vendorId'] = $sourceValue;
                }
            }
        }

        if ($format == 'csv') {
            $limit = null;
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );

            if (!empty($where)) {

                $action = $this->_request->getParam('a');

                switch ($action) {
                    case 'enable' :
                        $database = Bootstrap::getZendDb();

                        $sql = 'UPDATE ' .
                            HHF_Object_Collection_Db::_getStaticDatabase('HHF_Domain_Addon', $this->farm) . '
                            SET enabled = 1';

                        $whereRaw = array();
                        $bind = array();

                        foreach ($where as $key => $value) {
                            if (is_numeric($key)) {
                                $whereRaw[] = $value;
                            } else {
                                $whereRaw[] = $database->quoteIdentifier($key) . ' = ?';
                                $bind[] = $value;
                            }
                        }

                        if (!empty($whereRaw)) {
                            $sql .= ' WHERE ' . implode(' AND ', $whereRaw);
                        }

                        $database->query($sql, $bind);

                        break;
                    case 'disable' :

                        $database = Bootstrap::getZendDb();

                        $sql = 'UPDATE ' .
                            HHF_Object_Collection_Db::_getStaticDatabase('HHF_Domain_Addon', $this->farm) . '
                            SET enabled = 0';

                        $whereRaw = array();
                        $bind = array();

                        foreach ($where as $key => $value) {
                            if (is_numeric($key)) {
                                $whereRaw[] = $value;
                            } else {
                                $whereRaw[] = $database->quoteIdentifier($key) . ' = ?';
                                $bind[] = $value;
                            }
                        }

                        if (!empty($whereRaw)) {
                            $sql .= ' WHERE ' . implode(' AND ', $whereRaw);
                        }

                        $database->query($sql, $bind);

                        break;
                }
            }
        }

        $this->view->addons = HHF_Domain_Addon::fetchAllDisplay(
            $this->farm,
            array(
                'where' => $where,
                'limit' => $limit
            )
        );

        if ($format == 'csv') {
            $this->_helper->layout->disableLayout();
            return $this->render('addons.csv');
        }

        $this->view->foundRows = $this->view->addons->getFoundRows();

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();

        $this->view->sources = HHF_Domain_Addon::fetchSources($this->farm);
        $this->view->distributors = $this->farm->getChildNetworks(
            HH_Domain_Network::STATUS_APPROVED
        );

        if ($this->farm->isType(HH_Domain_Farm::TYPE_DISTRIBUTOR)) {
            $this->view->vendors = $this->farm->getParentNetworks();
        }
    }

    protected function _addonEnable()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $id = $this->_request->getParam('id');

        $addon = new HHF_Domain_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_response->setBody(
                json_encode(
                    array(
                        'result' => false,
                        'data' => null,
                        'msg' => $this->translate->_('Product not found')
                    )
                )
            );

            return;
        }

        $data = $addon->toArray();
        $data['enabled'] = 1;
        $addon->save($data);

        $data = $addon->toArray();

        $data['categoryName'] = null;

        $categories = HHF_Domain_Addon_Category::fetchAllForm($this->farm);

        foreach ($categories as $key => $category) {
            if ($key == $addon['categoryId']) {
                $data['categoryName'] = $category;
                break;
            }
        }

        if ($data['expirationDate'] instanceof Zend_Date) {
            $data['expirationDate'] = (string) $data['expirationDate'];
        }

        $this->_response->setBody(
            json_encode(
                array(
                    'result' => true,
                    'data' => $data,
                    'msg' => $this->translate->_('Enabled')
                )
            )
        );
    }

    protected function _addonDisable()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $id = $this->_request->getParam('id');

        $addon = new HHF_Domain_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_response->setBody(
                json_encode(
                    array(
                        'result' => false,
                        'data' => null,
                        'msg' => $this->translate->_('Product not found')
                    )
                )
            );

            return;
        }

        $data = $addon->toArray();
        $data['enabled'] = 0;
        $addon->save($data);

        $data = $addon->toArray();

        $data['categoryName'] = null;

        $categories = HHF_Domain_Addon_Category::fetchAllForm($this->farm);

        foreach ($categories as $key => $category) {
            if ($key == $addon['categoryId']) {
                $data['categoryName'] = $category;
                break;
            }
        }

        if ($data['expirationDate'] instanceof Zend_Date) {
            $data['expirationDate'] = (string) $data['expirationDate'];
        }

        $this->_response->setBody(
            json_encode(
                array(
                    'result' => true,
                    'data' => $data,
                    'msg' => $this->translate->_('Disabled')
                )
            )
        );
    }

    protected function _addonSetExpiration()
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $id = $this->_request->getParam('id');

        $addon = new HHF_Domain_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_response->setBody(
                json_encode(
                    array(
                        'result' => false,
                        'data' => null,
                        'msg' => $this->translate->_('Product not found')
                    )
                )
            );

            return;
        }

        $expirationDate = $this->_request->getParam('expirationDate');

        $validator = new Zend_Validate_Date();

        if (!$validator->isValid($expirationDate)) {
            $this->_response->setBody(
                json_encode(
                    array(
                        'result' => false,
                        'data' => null,
                        'msg' => $this->translate->_('Invalid date')
                    )
                )
            );

            return;
        }

        $data = $addon->toArray();
        $data['expirationDate'] = $expirationDate;
        $addon->save($data);

        $data = $addon->toArray();

        $data['categoryName'] = null;

        $categories = HHF_Domain_Addon_Category::fetchAllForm($this->farm);

        foreach ($categories as $key => $category) {
            if ($key == $addon['categoryId']) {
                $data['categoryName'] = $category;
                break;
            }
        }

        if ($data['expirationDate'] instanceof Zend_Date) {
            $data['expirationDate'] = (string) $data['expirationDate'];
        }

        $this->_response->setBody(
            json_encode(
                array(
                    'result' => true,
                    'data' => $data,
                    'msg' => $this->translate->_('Enabled')
                )
            )
        );
    }

    public function _addonsData($source)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('a.name', 'categoryName', 'inventory', 'active');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int)$_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
	    }

        $search = addcslashes($this->_request->getParam('sSearch'), '%_');

        $where = array();

        if (!empty($source)) {
            list($sourceType, $sourceValue) = explode('_', $source, 2);

            if ($sourceType == 'S') {

                if ($sourceValue == '__NULL__') {
                    $where[] = 'source IS NULL';
                } else {
                    $where['source'] = $sourceValue;
                }
            } else if ($sourceType == 'D') {
                if ($sourceValue == '__NULL__') {
                    $where[] = 'distributorId IS NULL';
                } else {
                    $where['distributorId'] = $sourceValue;
                }
            } else if ($sourceType == 'V') {
                if ($sourceValue == '__NULL__') {
                    $where[] = 'vendorId IS NULL';
                } else {
                    $where['vendorId'] = $sourceValue;
                }
            }
        }

        if (!empty($search)) {

            $where[] = 'a.name LIKE ' . Bootstrap::getZendDb()->quote('%' . $search . '%');
        }

        $rows = HHF_Domain_Addon::fetchAllDisplay(
            $this->farm,
            array(
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order,
                'where' => $where
            )
        );

        if (!empty($search)) {
            $iTotalDisplayRecords = $rows->getFoundRows();

            $totalRows = HHF_Domain_Addon::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 1
                    )
                )
            );

            $iTotalRecords = $totalRows->getFoundRows();
        } else {
            $iTotalRecords = $rows->getFoundRows();
            $iTotalDisplayRecords = $rows->getFoundRows();
        }

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();

            if ($data['expirationDate'] instanceof Zend_Date) {
                $data['expirationDate'] = (string) $data['expirationDate'];
            }
            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function addonAction()
    {
        $addonId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($addonId)) {
            $this->_addonNew();
        } else if ($action != 'delete') {
            $this->_addonEdit($addonId);
        } else {
            $this->_addonDelete($addonId);
        }
    }

    protected function _addonNew()
    {
        $this->view->addon = false;

        if (!empty($_POST)) {

            try {
                $addon = new HHF_Domain_Addon($this->farm);
                $addon->save($_POST);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Product "%s" added!'),
                        $addon->name
                    )
                );

                $this->_redirect('/admin/shares/addons', array('exit' => true));

            } catch (HH_Object_Exception_Validation $error) {
                $this->view->errors = $error->getErrorMessages();
            }
        }

        $this->view->getFormValue()->setDefaulVars(
            array(
                'priceBy' => HHF_Domain_Addon::PRICE_BY_UNIT,
                'unitType' => HHF_Domain_Addon::UNIT_TYPE_UNIT
            )
        );

        $categories = HHF_Domain_Addon_Category::fetchAllForm($this->farm);

        $this->view->categories = array_merge(
            $categories,
            array (
                '_new' => $this->translate->_('Add New Category')
            )
        );

        $rawLocations = HHF_Domain_Location::fetchLocations(
            $this->farm,
            array('order' => HHF_Domain_Location::ORDER_DATETIME)
        );
        $locations = array();
        $date = Zend_Date::now();

        foreach ($rawLocations as $location) {

            $date->setWeekday($location['dayOfWeek']);
            $date->setTime($location['timeStart'], 'H:m:s');
            $endDate = clone $date;
            $endDate->setTime($location['timeEnd'], 'H:m:s');

            $locations[$location['id']] = array(
                'label' =>  $location['name'] . ', ' . $location['city'],
                'title' =>  sprintf(
                    '%s, %s - %s',
                    $date->get(Zend_Date::WEEKDAY),
                    $date->toString('h:mm a'),
                    $endDate->toString('h:mm a')
                )
            );
        }

        $this->view->locations = $locations;

        $this->view->distributors = array();

        foreach ($this->farm->getChildNetworks(HH_Domain_Network::STATUS_APPROVED) as $childNetwork) {

            /** @var HH_Domain_Network $childNetwork */
            $farm = $childNetwork->getFarm();

            $this->view->distributors[$farm['id']] = $farm['name'];
        }

        $this->view->sources = HHF_Domain_Addon::fetchSources($this->farm);
    }

    protected function _addonDelete($id)
    {
        $addon = new HHF_Domain_Addon($this->farm, $id);

        if ($addon->isEmpty()) {
            $this->_redirect('/admin/shares/addons', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Product "%s" was deleted'),
                $addon->name
            )
        );

        $addon->getService()->remove();

        $this->_redirect('/admin/shares/addons', array('exit' => true));
    }

    protected function _addonEdit($id)
    {
        $currentAddon = $this->view->addon = new HHF_Domain_Addon(
            $this->farm,
            $id
        );

        if ($currentAddon->isEmpty()) {
            $this->_redirect('/admin/shares/addon', array('exit' => true));
        } else {
            $defaults = $currentAddon->toArray();

            $defaults['locations'] = array();

            foreach ($currentAddon->getLocations() as $location) {
                $defaults['locations'][] = $location['locationId'];
            }

            if (!empty($currentAddon->distributorId)) {
                $defaults['publishToNetwork'] = 1;
            }

            $this->view->getFormValue()->setDefaulVars($defaults);
        }

        if (!empty($_POST)) {
            try {
                $currentAddon->save($_POST);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Product "%s" updated!'),
                        $currentAddon->name
                    )
                );

                $this->_redirect('/admin/shares/addons', array('exit' => true));

            } catch (HH_Object_Exception_Validation $error) {
                $this->view->errors = $error->getErrorMessages();
            }
        }

        $categories = HHF_Domain_Addon_Category::fetchAllForm($this->farm);

        $this->view->categories = array_merge(
            $categories,
            array (
                '_new' => $this->translate->_('Add New Category')
            )
        );

        $rawLocations = HHF_Domain_Location::fetchLocations(
            $this->farm,
            array('order' => HHF_Domain_Location::ORDER_DATETIME)
        );
        $locations = array();
        $date = Zend_Date::now();

        foreach ($rawLocations as $location) {

            $date->setWeekday($location['dayOfWeek']);
            $date->setTime($location['timeStart'], 'H:m:s');
            $endDate = clone $date;
            $endDate->setTime($location['timeEnd'], 'H:m:s');

            $locations[$location['id']] = array(
                'label' =>  $location['name'] . ', ' . $location['city'],
                'title' =>  sprintf(
                    '%s, %s - %s',
                    $date->get(Zend_Date::WEEKDAY),
                    $date->toString('h:mm a'),
                    $endDate->toString('h:mm a')
                )
            );
        }

        $this->view->locations = $locations;

        $this->view->distributors = array();

        foreach ($this->farm->getChildNetworks(HH_Domain_Network::STATUS_APPROVED) as $childNetwork) {

            /** @var HH_Domain_Network $childNetwork */
            $farm = $childNetwork->getFarm();

            $this->view->distributors[$farm['id']] = $farm['name'];
        }

        $this->view->sources = HHF_Domain_Addon::fetchSources($this->farm);
    }

    public function deliveriesAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'columns' => 'year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $this->view->years = array();

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!in_array(date('Y'), $this->view->years)) {
            $this->view->years[date('Y')] = date('Y');
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_deliveriesData($year);
        }

        $this->view->year = $year;

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv') {
            $this->view->deliveries = HHF_Domain_Delivery::fetch(
                $this->farm,
                array(
                    'sql' => 'SELECT
                            *
                        FROM
                            __DATABASE__ AS d
                        LEFT JOIN
                            __SCHEMA__.deliveriesItems AS i
                        ON d.id = i.deliveryId',
                    'columns' => '*',
                    'where' => array(
                        'week like \'' . (int) $year . '%\''
                    ),
                    'order' => array(
                        array(
                            'column' => 'd.addedDatetime',
                            'dir' => 'desc'
                        )
                    )
                )
            );

        } else {
            $this->view->deliveries = HHF_Domain_Delivery::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'columns' => array(
                        'id',
                        'shareId',
                        'week',
                        'enabled'
                    ),
                    'where' => array(
                        'week like \'' . (int) $year . '%\''
                    ),
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 50
                    ),
                    'order' => array(
                        array(
                            'column' => 'addedDatetime',
                            'dir' => 'desc'
                        )
                    )
                )
            );

            $this->view->foundRows = $this->view->deliveries->getFoundRows();
        }

        $rawShares = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'columns' => array(
                    'id',
                    'name'
                ),
            )
        );

        $shares = array();

        foreach ($rawShares as $share) {
            $shares[$share->id] = $share->name;
        }

        foreach ($this->view->deliveries as $delivery) {

            if (isset($shares[$delivery->shareId])) {
                $delivery->shareName = $shares[$delivery->shareId];
            } else {
                $delivery->shareName = $this->translate->_('Unknown');
            }
        }

        if ($format == 'csv') {
            $this->_helper->layout->disableLayout();
            return $this->render('deliveries.csv');
        }

        $this->view->messages = $this->_helper->getHelper('FlashMessenger')
            ->getMessages();
    }

    public function _deliveriesData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();
        $columns = array('shareId', 'week', 'enabled');

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $rows = HHF_Domain_Delivery::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'id',
                    'shareId',
                    'week',
                    'enabled'
                ),
                'where' => array(
                    'week like \'' . (int) $year . '%\''
                ),
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order
            )
        );


        $iTotalRecords = $rows->getFoundRows();
        $iTotalDisplayRecords = $rows->getFoundRows();

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        $rawShares = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'columns' => array(
                    'id',
                    'name'
                )
            )
        );

        $shares = array();

        foreach ($rawShares as $share) {
            $shares[$share->id] = $share->name;
        }

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->id;
            if (!empty($shares[$row['shareId']])) {
                $data['shareName'] = $shares[$data['shareId']];
            } else {
                $data['shareName'] = $this->translate->_('Unknown');
            }
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function deliveryAction()
    {
        $deliveryId = (int) $this->_request->getParam('id', 0);
        $action = $this->_request->getParam('a', false);

        if (empty($deliveryId)) {
            $this->_deliveryNew();
        } else if ($action != 'delete') {
            $this->_deliveryEdit($deliveryId);
        } else {
            $this->_deliveryDelete($deliveryId);
        }
    }

    protected function _deliveryNew()
    {
        $this->view->shares = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'where' => array(
                    'year IN(' . date('Y') . ',' . (date('Y') - 1) . ')',
                    'enabled' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        $this->view->delivery = false;

        $date = new Zend_Date();
        $date->addWeek(1);

        $share = current($this->view->shares);

        $this->view->getFormValue()->setDefaulVars(
            array(
                'week' => $date->toString('YYYYWww'),
                'shareId' => $share->id
            )
        );

        if (!empty($_POST)) {

            $data = array();
            $this->view->errors = array();

            try {
                $data = HHF_Domain_Delivery::validate(
                    $_POST,
                    array('farm' => $this->farm)
                );
            } catch (HH_Object_Exception_Validation $error) {
                $this->view->errors = $error->getErrorMessages();
            }

            if (empty($this->view->errors)) {

                $delivery = new HHF_Domain_Delivery($this->farm);
                $delivery->insert($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Delivery added!')
                    )
                );

                $this->_redirect('/admin/shares/deliveries', array('exit' => true));

            }
        }
    }

    protected function _deliveryDelete($id)
    {
        $delivery = new HHF_Domain_Delivery($this->farm, $id);

        if ($delivery->isEmpty()) {
            $this->_redirect('/admin/shares/deliveries', array('exit' => true));
        }

        $messenger = $this->_helper->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Delivery deleted')
            )
        );

        $delivery->delete();

        $this->_redirect('/admin/shares/deliveries', array('exit' => true));
    }

    protected function _deliveryEdit($id)
    {
        $currentDelivery = $this->view->delivery = new HHF_Domain_Delivery(
            $this->farm,
            $id
        );

        if ($currentDelivery->isEmpty()) {
            $this->_redirect('/admin/shares/delivery', array('exit' => true));
        } else {
            $this->view->getFormValue()->setDefaulVars($currentDelivery);
        }

        $this->view->shares = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'where' => array(
                    'year IN(' . date('Y') . ',' . (date('Y') - 1) . ')',
                    'enabled' => 1
                ),
                'order' => array(
                    array(
                        'column' => 'name',
                        'dir' => 'asc'
                    )
                )
            )
        );

        if (!empty($_POST)) {
            $data = array();
            $this->view->errors = array();

            try {
                $data = HHF_Domain_Delivery::validate(
                    $_POST,
                    array(
                        'farm' => $this->farm,
                        'delivery' => $currentDelivery
                    )
                );
            } catch (HH_Object_Exception_Validation $error) {
                $this->view->errors = $error->getErrorMessages();
            }

            if (empty($this->view->errors)) {

                $currentDelivery->update($data);

                $messenger = $this->_helper->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Delivery updated!')
                    )
                );


                $this->_redirect('/admin/shares/deliveries', array('exit' => true));

            }
        }
    }

    public function deliveryReportsAction()
    {
        $year = (int) $this->_request->getParam('year');

        $years = HHF_Domain_Share::fetch(
            $this->farm,
            array(
                'columns' => 'year',
                'groupBy' => array(
                    'year'
                ),
                'order' => array(
                    array(
                        'column' => 'year',
                        'dir' => 'desc'
                    )
                )
            )
        );

        $found = false;
        $years = $years->toArray();
        $this->view->years = array();

        if (!empty($years)) {
            $now = date('Y');

            if ($years[0]['year'] != $now) {
                array_unshift($years, array('year' => $now));
            }
        }

        foreach ($years as $y) {
            if ($y['year'] == $year) {
                $found = true;
            }
            $this->view->years[$y['year']] = $y['year'];
        }

        if (!$found) {
            if (!empty($this->view->years)) {
                $year = max($this->view->years);
            } else {
                $year = date('Y');
            }
        }

        if ($this->_request->isXmlHttpRequest()) {
            return $this->_deliveryReportsData($year);
        }

        $this->view->year = $year;

        $format = $this->_request->getParam('format', false);

        if ($format == 'csv') {

            $this->view->deliveryReports = HHF_Domain_Delivery::fetch(
                $this->farm,
                array(
                    'columns' => '*',
                    'sql' => 'SELECT
                        d.week as deliveryWeek,
                        d.shareId as shareId,
                        s.name as shareName,
                        s.deliverySchedule as shareDeliverySchedule,
                        s.year as shareYear,
                        sd.startWeek as shareDurationStartWeek,
                        sd.iterations as shareDurationIterations,
                        ss.size as shareSizeSize,
                        ss.name as shareSizeName,
                        l.name as locationName,
                        l.dayOfWeek as locationDayOfWeek,
                        cs.startWeek as customerShareStartWeek,
                        cs.endWeek as customerShareEndWeek,
                        cs.quantity as customerShareQuantity,
                        c.firstName as customerFirstName,
                        c.lastName as customerLastName,
                        va.vacationOption as vacationOption,
                        cv.startWeek as vacationStart,
                        cv.endWeek as vacationEnds
                    FROM
                        __SCHEMA__.deliveries as d
                    LEFT JOIN
                        __SCHEMA__.shares as s
                    ON
                        s.id = d.shareId
                    LEFT JOIN
                        __SCHEMA__.customersShares as cs
                    ON
                        cs.shareId = d.shareId
                    LEFT JOIN
                        __SCHEMA__.sharesDurations as sd
                    ON
                        sd.id = cs.shareDurationId
                    LEFT JOIN
                        __SCHEMA__.sharesSizes as ss
                    ON
                        ss.id = cs.shareSizeId
                    LEFT JOIN
                        __SCHEMA__.locations as l
                    ON
                        l.id = cs.locationId
                    LEFT JOIN
                        __SCHEMA__.customers as c
                    ON
                        c.id = cs.customerId
                    LEFT JOIN
                        __SCHEMA__.customersVacations as cv
                    ON
                        (cs.id = cv.shareId AND (d.week BETWEEN cv.startWeek AND cv.endWeek))
                    LEFT JOIN
                        __SCHEMA__.sharesVacationOptions as va
                    ON
                        cv.vacationOptionId = va.id
                    WHERE
                        d.week LIKE \'' . (int) $year . '%\'
                    AND
                        d.enabled = 1
                    AND
                        c.enabled = 1
                    ORDER BY
                        d.week ASC,
                        l.dayOfWeek ASC,
                        l.timeStart ASC,
                        c.lastName ASC'
                )
            );

        } else {
            $this->view->deliveryReports = HHF_Domain_Delivery::fetch(
                $this->farm,
                array(
                    'countRows' => true,
                    'columns' => array(
                        'week'
                    ),
                    'limit' => array(
                        'offset' => 0,
                        'rows' => 50
                    ),
                    'where' => array(
                        'enabled' => 1,
                        'week like \'' . (int) $year . '%\''
                    ),
                    'groupBy' => 'week',
                    'order' => array(
                        array(
                            'column' => 'week',
                            'dir' => 'desc'
                        )
                    )
                )
            );

            $this->view->foundRows = $this->view->deliveryReports->getFoundRows();

	        $rawLocations = HHF_Domain_Location::fetchLocations(
	            $this->farm,
	            array('order' => HHF_Domain_Location::ORDER_DATETIME)
	        );
	        $locations = array();
	        $date = Zend_Date::now();

	        foreach ($rawLocations as $location) {

	            $date->setWeekday($location['dayOfWeek']);

                $optGroup = $date->toString(Zend_Date::WEEKDAY);

                if (!array_key_exists($optGroup, $locations)) {
                    $locations[$optGroup] = array();
                }

	            $locations[$optGroup][$location['id']] = $location['name'] . ', ' . $location['city'];
	        }

			$this->view->locations = $locations;
        }

        if ($format == 'csv') {
            $this->_helper->layout->disableLayout();
            return $this->render('deliveries-reports.csv');
        }
    }

    public function _deliveryReportsData($year)
    {
        $this->setNoRender();
        $this->_helper->layout->disableLayout();

        $this->_response->setHeader(
            'Content-Type',
            'application/json',
            true
        );

        $offset = (int) $this->_request->getParam('iDisplayStart', 0);
        $rows = (int) $this->_request->getParam('iDisplayLength', 100);

        if ($rows <= 0) {
            $rows = 100;
        }

        $order = array();
        $columns = array('week', 'week');

	    if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $columns[(int) $_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
	    }

        $rows = HHF_Domain_Delivery::fetch(
            $this->farm,
            array(
                'countRows' => true,
                'columns' => array(
                    'week',
                    'week as weekNumber'
                ),
                'where' => array(
                    'enabled' => 1,
                    'week like \'' . (int) $year . '%\''
                ),
                'groupBy' => 'week',
                'limit' => array(
                    'offset' => $offset,
                    'rows' => $rows
                ),
                'order' => $order
            )
        );

        $iTotalRecords = $rows->getFoundRows();
        $iTotalDisplayRecords = $rows->getFoundRows();

        $result = array(
            'sEcho' => (int) $this->_request->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
	    );

        foreach ($rows as $row) {
            $data = $row->toArray();
            $data['DT_RowId'] = 'row-' . $row->week;
            $result['aaData'][] = $data;
        }

        $this->_response->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function deliveryReportAction()
    {
        $this->view->isoWeek = $this->_request->getParam('week', false);

		$filterLocations = $this->_request->getParam('locations', array());
		$this->view->filterLocations = $filterLocations;

        if (empty($this->view->isoWeek) || strpos($this->view->isoWeek, 'W') === false) {
            $this->_redirect(
                '/admin/shares/delivery-reports',
                array('exit' => true)
            );
        }

        list ($year, $week) = explode('W', $this->view->isoWeek);

        $this->view->week = (int) $week;
        $this->view->year = (int) $year;

        if (empty($this->view->week) || empty($this->view->year)) {
            $this->_redirect(
                '/admin/shares/delivery-reports',
                array('exit' => true)
            );
        }

        $weekDate = new Zend_Date();
        $weekDate->set($year, Zend_Date::YEAR_8601)
            ->set($week, Zend_Date::WEEK)
            ->set(1, Zend_Date::WEEKDAY_8601);

        $this->view->deliveries = HHF_Domain_Delivery::fetchDeliveries(
            $this->farm,
            array(
                'week' => $this->view->isoWeek
            )
        );

        if (empty($this->view->deliveries)) {
            $this->_redirect(
                '/admin/shares/delivery-reports',
                array('exit' => true)
            );
        }

        $this->view->customerAddons = HHF_Domain_Customer_Addon::fetchAddons(
            $this->farm,
            array(
                'week' => $this->view->isoWeek
            )
        );
        $this->view->shareDurations = array();
        $this->view->customerShares = array();

		if (!is_array($filterLocations) || empty($filterLocations)) {// show all locations
	        $this->view->locations = HHF_Domain_Location::fetchLocations(
	            $this->farm,
	            array(
	                'order' => HHF_Domain_Location::ORDER_DATETIME
	            )
	        );
		} else { // location ids are already set so use em

			$locs = HHF_Domain_Location::fetchLocations(
	            $this->farm,
	            array(
	                'order' => HHF_Domain_Location::ORDER_DATETIME,

	            )
	        );

            foreach ($locs as $key => $loc) {
                if (array_search($loc->id, $filterLocations) === false) {
                    unset($locs[$key]);
                }
            }

			$this->view->locations = $locs;

		}

        foreach ($this->view->deliveries as $delivery) {
            $share = HHF_Domain_Share::singleton(
                $this->farm,
                $delivery->shareId
            );

            // is there an applicable duration
            foreach ($share->durations as $duration) {
                /* @var $duration HHF_Domain_Share_Duration */
                $startDate = $duration->getStartDate(null, $share->year);
                if ($weekDate->compareDate($startDate) >= 0 && $weekDate->compareDate($duration->getEndDate($share->deliverySchedule, null, $share->year)) <= 0) {
                    if (!isset($this->view->shareDurations[$share->id])) {
                        $this->view->shareDurations[$share->id] = array($duration->id);
                    } else {
                        $this->view->shareDurations[$share->id][] = $duration->id;
                    }
                }
            }
        }

        // find customer shares that are applicable
        if (!empty($this->view->shareDurations)) {
            $limitToLocations = array();

            $customerShares = HHF_Domain_Customer_Share::fetchShares(
                $this->farm,
                array(
                    'fetch' => HHF_Domain_Customer_Share::FETCH_SHARES,
                    'shares' => array_keys($this->view->shareDurations),
                    'isoWeek' => $this->view->isoWeek
                )
            );

            foreach ($customerShares as $customerShare) {
                // find customer sharein available share and durations
                foreach ($this->view->shareDurations as $shareId => $shareDurations) {
                    if ($customerShare->shareId == $shareId) {
                        foreach ($shareDurations as $durationId) {
                            if ($durationId == $customerShare->shareDurationId) {
                                $this->view->customerShares[] = $customerShare;
                            }
                        }
                    }
                }

                $limitToLocations[$customerShare['locationId']] = true;
            }

            foreach ($this->view->locations as $key => $location) {
                if (!array_key_exists($location['id'], $limitToLocations)) {
                    unset($this->view->locations[$key]);
                }
            }
        } else {

            $this->view->locations = array();

        }
    }

    public function optionsAction()
    {
        $preferences = new HHF_Preferences(
            $this->farm,
            HHF_Domain_Preference::TYPE_FARM,
            null,
            'shares'
        );

        if (!empty($_POST)) {

            $_POST['shares-share'] = htmlspecialchars(strtolower($_POST['shares-share']));
            $_POST['shares-shares'] = htmlspecialchars(strtolower($_POST['shares-shares']));

            foreach ($_POST as $key => $value) {
                $preferences->replace($key, $value);
            }

            $messenger = $this->_helper->getHelper('FlashMessenger');

            $messenger->addMessage(
                sprintf(
                    $this->translate->_('Shares options updated!')
                )
            );

            $this->_redirect('/admin/default/options', array('exit' => true));
        }

        $defaultVars = array(
            'shares-share' => 'share',
            'shares-shares' => 'shares'
        );

        foreach ($preferences as $preference) {
            $hash = $preference->resource . '-' . $preference->key;

            $defaultVars[$hash] = $preference->value;
        }

        $this->view->getFormValue()->setDefaulVars($defaultVars);
    }
}
