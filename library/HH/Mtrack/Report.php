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
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 * @package
 */

/**
 * Description of Report
 *
 * @package   
 * @author    Michael Caplan <farmnik@harvesthand.com>
 * @version   $Id: Report.php 323 2011-09-22 22:22:20Z farmnik $
 * @copyright $Date: 2011-09-22 19:22:20 -0300 (Thu, 22 Sep 2011) $
 * @license   http://opensource.org/licenses/gpl-3.0.html  GPL 3
 */
class HH_Mtrack_Report extends HH_Object
{
    /**
     * Get data (lazy loader)
     */
    protected function _get()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Insert data into object
     *
     * @param array $data
     * @return boolean
     * @throws HH_Object_Exception_Id If primary key needs to be defined
     * @throws HH_Object_Exception_NoData If no data to insert
     */
    public function insert($data)
    {
        throw new Exception('Not implemented');
    }

    /**
     * Update data in current object
     *
     * @param array|null $data
     * @return boolean
     * @throws HH_Object_Exception_Id if object ID is not set
     */
    public function update($data = null)
    {
        throw new Exception('Not implemented');
    }

    /**
     * Delete current object
     *
     * @throws HH_Object_Exception_Id if object ID is not set
     * @return boolean
     */
    public function delete()
    {
        throw new Exception('Not implemented');
    }

    /**
     * Get Zend_Filter_Input for model
     *
     * @param string $filter Filter to get
     * @param array $options Filter options
     * @return Zend_Filter_Input
     */
    public static function getFilter($filter = null, $options = array())
    {
        throw new Exception('Not implemented');
    }
    
    /**
     * Fetch report
     *
     * @param array $options
     * @return array
     */
    public static function fetchReportData($options = array())
    {
        $cache = Bootstrap::getZendCache();

        $reportNo = (isset($options['report'])) ? $options['report'] : 3;
        
        if (isset ($options['clearCache']) && $options['clearCache'] == true) {
            $cache->remove('mtrack_report_' . $reportNo);
        }
        
        if (($result = $cache->load('mtrack_report_' . $reportNo)) !== false) {
            $result;
        } else {

            $result = array();

            $mtrack = Bootstrap::getZendConfig()->resources->website
                ->mtrack->toArray();
            
            $url = $mtrack['url'] . 'report.php/' 
                . $reportNo . '?format=tab';
            
            $client = new Zend_Http_Client($url);
            $client->setAuth(
                $mtrack['username'],
                $mtrack['password']
            );

            try {

                $responce = $client->request();

                if (!$responce->isError()) {

                    $rows = str_getcsv($responce->getBody(), "\n");

                    $keys = array();
                    
                    foreach (str_getcsv(array_shift($rows), "\t") as $v) {
                        $keys[] = strtolower($v);
                    }

                    foreach ($rows as $row) {
                        $result[] = array_combine(
                            $keys,
                            str_getcsv($row, "\t")
                        );
                    }

                }

            } catch (Exception $e) {
                HH_Error::exceptionHandler($e, E_USER_WARNING);
            }

            $cache->save($result, 'mtrack_report_' . $reportNo);
        }

        
        
        foreach ($result as $record) {
            $return[] = new self(
                $record['id'],
                $record,
                $options
            );
        }

        return $return;
    }
}