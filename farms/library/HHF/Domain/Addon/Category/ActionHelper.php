<?php

class HHF_Domain_Addon_Category_ActionHelper extends HH_Object_ActionHelper
{
    protected $translate = null;
    protected $tableColumns = array('name');

    public function __construct($name)
    {
        parent::__construct($name);

        $this->translate = Bootstrap::getZendTranslate();
    }

    public function bootstrapList($options = array())
    {
        if ($this->_actionController->getRequest()->isXmlHttpRequest()) {
            $this->_actionController->setNoRender();
            $this->_actionController->getHelper('layout')->disableLayout();

            $this->_actionController->getResponse()->setHeader(
                'Content-Type',
                'application/json',
                true
            );;

            switch ($this->_actionController->getRequest()->getParam('a')) {
                default :
                    return $this->getTableData();
            }
        }

        $format = $this->_actionController->getRequest()->getParam('format', false);
        $where = array();

        if ($format == 'csv') {
            $limit = null;
        } else {
            $limit = array(
                'offset' => 0,
                'rows' => 50
            );
        }

        $class = $this->getObjectClass();

        $this->_actionController->view->collection = $class::fetch(
            $this->_actionController->farm,
            array(
                'where' => $where,
                'limit' => $limit
            )
        );

        if ($format == 'csv') {
            $this->_actionController->getHelper('layout')->disableLayout();
            return $this->_actionController->render($this->getViewScript(true, 'csv'));
        }

        $this->_actionController->view->foundRows = $this->_actionController->view->collection->getFoundRows();

        $this->_actionController->view->messages = $this->_actionController->getHelper('FlashMessenger')
            ->getMessages();
    }

    protected function getTableData()
    {
        $offset = (int) $this->_actionController->getRequest()->getParam('iDisplayStart', 0);
        $rows = (int) $this->_actionController->getRequest()->getParam('iDisplayLength', 50);

        if ($rows <= 0) {
            $rows = 50;
        }

        $order = array();

        if (isset($_GET['iSortCol_0'])) {
            for ($i = 0; $i < (int) $_GET['iSortingCols']; ++$i) {
                if ($_GET['bSortable_' . (int) $_GET['iSortCol_' . $i]] == "true") {

                    $dir = $_GET['sSortDir_' . $i];

                    if (strcasecmp($dir, 'asc') !== 0 && strcasecmp($dir, 'desc') !== 0) {
                        $dir = 'desc';
                    }

                    $order[] = array(
                        'column' => $this->tableColumns[(int)$_GET['iSortCol_' . $i]],
                        'dir' => $dir
                    );
                }
            }
        }

        $search = addcslashes($this->_actionController->getRequest()->getParam('sSearch'), '%_');

        $where = array();

        if (!empty($search)) {

            foreach ($this->tableColumns as $column) {
                $where[] = $column . ' LIKE ' . Bootstrap::getZendDb()->quote('%' . $search . '%');
            }
        }

        $rows = HHF_Domain_Addon_Category::fetch(
            $this->_actionController->farm,
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

            $totalRows = HHF_Domain_Addon_Category::fetch(
                $this->_actionController->farm,
                array(
                    'columns' => '*',
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
            'sEcho' => (int) $this->_actionController->getRequest()->getParam('sEcho', 0),
            'iTotalRecords' => $iTotalRecords,
            'iTotalDisplayRecords' => $iTotalDisplayRecords,
            'aaData' => array()
        );

        foreach ($rows as $row) {
            $data = $row->toArray();

            array_walk($data, function(&$value, $key) {
                if ($value instanceof Zend_Date) {
                    $value = (string) $value;
                }
            });

            $data['DT_RowId'] = 'row-' . $row->id;
            $result['aaData'][] = $data;
        }

        $this->_actionController->getResponse()->appendBody(
            Zend_Json::encode($result)
        );
    }

    public function bootstrapItem($options = array())
    {
        $itemId = $this->_actionController->getRequest()->getParam('id', 0);
        $action = $this->_actionController->getRequest()->getParam('a', false);

        if (empty($itemId)) {
            $this->itemNew();
        } else {
            if (empty($action)) {
                $this->itemEdit($itemId);
            } else {
                $method = 'item' . ucfirst(
                        strtolower(
                            filter_var(
                                $action,
                                FILTER_SANITIZE_STRING,
                                FILTER_FLAG_STRIP_LOW & FILTER_FLAG_STRIP_HIGH
                            )
                        )
                    );

                if (method_exists($this, $method)) {
                    $this->$method($itemId);
                }
            }
        }
    }

    protected function itemNew()
    {
        $this->_actionController->view->item = false;

        if (!empty($_POST)) {

            try {
                $class = $this->getObjectClass();

                $item = new $class($this->_actionController->farm);
                $item->save($_POST);

                $messenger = $this->_actionController->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Product category "%s" added!'),
                        $item->name
                    )
                );

                $this->_actionController->redirect('/admin/shares/addons-categories', array('exit' => true));

            } catch (HH_Object_Exception_Validation $error) {
                $this->_actionController->view->errors = $error->getErrorMessages();
            }
        }
    }

    protected function itemEdit($id)
    {
        $class = $this->getObjectClass();

        $item = $this->_actionController->view->item = new $class(
            $this->_actionController->farm,
            $id
        );

        if ($item->isEmpty()) {
            $this->_actionController->redirect('/admin/shares/addon', array('exit' => true));
        } else {
            $defaults = $item->toArray();

            $this->_actionController->view->getFormValue()->setDefaulVars($defaults);
        }

        if (!empty($_POST)) {
            try {
                $item->save($_POST);

                $messenger = $this->_actionController->getHelper('FlashMessenger');

                $messenger->addMessage(
                    sprintf(
                        $this->translate->_('Product category "%s" updated!'),
                        $item->name
                    )
                );

                $this->_actionController->redirect('/admin/shares/addons-categories', array('exit' => true));

            } catch (HH_Object_Exception_Validation $error) {
                $this->_actionController->view->errors = $error->getErrorMessages();
            }
        }
    }

    protected function itemDelete($id)
    {
        $class = $this->getObjectClass();

        $item = new $class($this->_actionController->farm, $id);

        if ($item->isEmpty()) {
            $this->_actionController->redirect('/admin/shares/addons-categories', array('exit' => true));
        }

        $messenger = $this->_actionController->getHelper('FlashMessenger');

        $messenger->addMessage(
            sprintf(
                $this->translate->_('Product category "%s" was deleted'),
                $item->name
            )
        );

        $item->getService()->remove();

        $this->_actionController->redirect('/admin/shares/addons-categories', array('exit' => true));
    }
}
