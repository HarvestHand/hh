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
 * @copyright $Date: 2012-09-02 20:22:40 -0300 (Sun, 02 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_View
 */

/**
 * Validates if something is empty
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: DataTable.php 572 2012-09-02 23:22:40Z farmnik $
 * @package   HH_View
 * @copyright $Date: 2012-09-02 20:22:40 -0300 (Sun, 02 Sep 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_View_Helper_DataTable extends Zend_View_Helper_Abstract
{
    protected $_table;
    protected $_columns = array();

    /**
     * check if var is, or series of vars are, empty
     *
     * @param mixed $var
     * @return boolean
     */
    public function dataTable($table = null, $columns = null)
    {
        if ($table !== null) {
            $this->setTable($table);
        }
        
        if (is_array($columns)) {
            $this->setColumns($columns);
        }
        
        return $this;
    }
    
    public function setTable($table)
    {
        $this->_table = $table;
        
        return $this;
    }
    
    public function setColumns($columns)
    {
        $this->_columns = $columns;
        
        return $this;
    }
    
    public function sorting($farmer, $defaultCol = 0, $defaultDir = 'asc')
    {
        $json = array();
        
        if ($farmer instanceof HH_Domain_Farmer) {
            $order = $farmer->getPreferences()->getStructure(
                $this->_table,
                'lists'
            );
        } else {
            $order = array();
        }
        
        foreach ($order as $columnData) {
            $curColumn = array_search(
                $columnData['column'], 
                $this->_columns
            );

            $curDir = $defaultDir;
            
            if ($curColumn === false) {
                continue;
            }
            
            if (in_array($columnData['dir'], array('asc', 'desc')) == true) {
                $curDir = $columnData['dir'];
            }
            
            $json[] = array($curColumn, $curDir);
        }
        
        if (empty($json)) {
            if (is_numeric($defaultCol)) {
                $json[] = array($defaultCol, $defaultDir);
            } else {
                $curColumn = array_search(
                    $defaultCol, 
                    $this->_columns
                );

                if ($curColumn === false) {
                    $curColumn = 0;
                }
                
                $json[] = array($curColumn, $defaultDir);
            }
        }
        
        return json_encode($json);
    }
}