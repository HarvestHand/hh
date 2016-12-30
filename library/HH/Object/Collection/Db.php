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
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */

/**
 * Description of object DB
 *
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Db.php 518 2012-04-25 02:25:26Z farmnik $
 * @copyright $Date: 2012-04-24 23:25:26 -0300 (Tue, 24 Apr 2012) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package   HH_Object
 */
class HH_Object_Collection_Db extends HH_Object_Collection
{
    private $_foundRows = null;
    
    public function getFoundRows()
    {
        if (is_numeric($this->_foundRows)) {
            return $this->_foundRows;
        }
        
        return $this->count();
    }
    
    /**
     * get db handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected function _getZendDb()
    {
        if (isset($this->_config['Zend_Db'])) {
            return $this->_config['Zend_Db'];
        }

        return Bootstrap::get('Zend_Db');
    }

    /**
     * get db handle
     *
     * @return Zend_Db_Adapter_Abstract
     */
    protected static function _getStaticZendDb()
    {
        if (isset(self::$_staticConfig['Zend_Db'])) {
            return self::$_staticConfig['Zend_Db'];
        }

        return Bootstrap::get('Zend_Db');
    }
    
    /**
     * 
     * @params string $class
     * @params array $options
     * @return HH_Object_Db[]
     */
    public static function fetch($class, $options = array(), $farm = null)
    {
        $database = self::_getStaticZendDb();
        
        $columns = self::_sqlBuildColumns($options);
        
        if (isset($options['sql'])) {
        
            $sql = $options['sql'];
            
            $sql = str_replace(
                array(
                    '__DATABASE__',
                    '__SCHEMA__',
                    '__TABLE__',
                    '__COLUMNS__'
                ),
                array(
                    self::_getStaticDatabase($class, (isset($farm) ? $farm : null)),
                    self::_getStaticSchema((isset($farm) ? $farm : null)),
                    self::_getStaticTable($class),
                    $columns
                ),
                $sql
            );

        } else {
            $sql = 'SELECT ' 
                . (!empty($options['countRows']) ? 'SQL_CALC_FOUND_ROWS ' : '') . '
                    ' . $columns . '
                FROM
                    ' . self::_getStaticDatabase(
                        $class, (isset($farm) ? $farm : null)
                    );
        }
        
        $bind = array();
        
        if (isset($options['where'])) {
            $sql .= self::_sqlBuildWhere($options['where'], $bind);
        }
        
        if (isset($options['groupBy'])) {
            $sql .= self::_sqlBuildGroupBy($options['groupBy']);
        }
        
        if (isset($options['order'])) {            
            $sql .= self::_sqlBuildOrder($options['order']);
        }
        
        if (isset($options['limit'])) {
            $sql .= self::_sqlBuildLimit($options['limit']);
        }
        
        $fetchByCol = ($columns == 'id') ? true : false;
        
        if ($fetchByCol) {
            $rows = $database->fetchCol($sql, $bind);
        } else {
            $rows = $database->fetchAll($sql, $bind);
        }
        
        $collection = static::_getCollection($class, $options, $farm);
        
        if (!empty($options['countRows'])) {
           $collection->_foundRows = $database->fetchOne('SELECT FOUND_ROWS()');
        }
        
        foreach ($rows as $row) {
            if (isset($farm)) {
                if ($fetchByCol) {
                    $collection[] = new $class($farm, $row, null, $options);
                } else {
                    $collection[] = new $class($farm, null, $row, $options);
                }
            } else {
                if ($fetchByCol) {
                    $collection[] = new $class($row, null, $options);
                } else {
                    $collection[] = new $class(null, $row, $options);
                }
            }
        }
        
        return $collection;
    }
    
    /**
     * @return HH_Object_Collection_Db 
     */
    protected static function _getCollection($objectClass, $options)
    {
        $class = get_called_class();
        
        $collection = new $class(array(), $options);
         
        $collection->setObjectType($objectClass);
         
        return $collection;
    }
    
    protected static function _sqlBuildWhere($data, &$bind)
    {
        if (!empty($data)) {
            
            if (is_array($data)) {
            
                $where = array();
                $database = self::_getStaticZendDb();

                foreach ($data as $key => $value) {
                    if (is_numeric($key)) {
                        $where[] = $value;
                    } else {
                        $where[] = $database->quoteIdentifier($key) . ' = ?';
                        $bind[] = $value;
                    }
                }

                if (!empty($where)) {
                    return ' WHERE ' . implode(' AND ', $where);
                }
            } else {
                return ' WHERE ' . $data;
            }
        }
    }
    
    protected static function _sqlBuildOrder($data)
    {
        if (!empty($data)) {            
            $orderBy = array();
            $database = self::_getStaticZendDb();
            
            foreach ($data as $colMeta) {
                $orderBy[] = $database->quoteIdentifier($colMeta['column']) 
                    . ' ' . $colMeta['dir'];
            }
            
            return ' ORDER BY ' . implode(', ', $orderBy);
        }
    }
    
    protected static function _sqlBuildGroupBy($data)
    {
        if (!empty($data)) {            
            $groupBy = array();
            $database = self::_getStaticZendDb();
            
            if (!is_array($data)) {
                $data = array($data);
            }
            
            foreach ($data as $column) {
                $groupBy[] = $database->quoteIdentifier($column) ;
            }
            
            return ' GROUP BY ' . implode(', ', $groupBy);
        }
    }
    
    protected static function _sqlBuildLimit($data)
    {
        return ' LIMIT ' . $data['offset'] 
                . ', ' . $data['rows'];
    }
    
    protected static function _sqlBuildColumns(&$options)
    {
        $columns = array(
            'id'
        );
        
        if (isset($options['columns'])) {
            if (is_array($options['columns'])) {
                $columns = $options['columns'];
            } else {
                $columns = array($options['columns']);
            }
            
        }
        
        return implode(', ', $columns);
    }
    
    /**
     * Convert class name to database table name
     *
     * @param HH_Domain_Farm $farm
     * @param string $class Name of class to convert to DB string
     * @return string
     */
    public static function _getStaticDatabase($class, 
        HH_Domain_Farm $farm = null)
    {
        return self::_getStaticSchema($farm) . '.' 
            . self::_getStaticTable($class);
    }
    
    /**
     * Get table name
     * 
     * @param string $class
     * @return string
     */
    protected static function _getStaticTable($class)
    {
        return self::_buildTableName($class);
    }
    
    /**
     * Get table schema
     * 
     * @param HH_Domain_Farm $farm
     * @return string
     */
    protected static function _getStaticSchema(HH_Domain_Farm $farm = null) {
        if ($farm instanceof HH_Domain_Farm) {
            return 'farmnik_hh_' . $farm->id;
        } else {
            return 'farmnik_hh';
        } 
    }
    
    public static function _buildTableName($class)
    {
        $pieces = explode('_', $class);
        $table = '';

        $first = true;
        
        foreach ($pieces as $piece) {
            
            if (in_array($piece, array('HH', 'Domain', 'HHF'))) {
                continue;
            }
            
            if (substr($piece, -1, 1) == 'y') {
                $piece = substr($piece, 0, -1) . 'ies';
            } else {
                $piece .= 's';
            }
            
            if ($first) {
                $table .= strtolower($piece);
                $first = false;
            } else {
                $table .= $piece;
            }
        }
        
        return $table;
    }
}
