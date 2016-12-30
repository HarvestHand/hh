<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id: API.php 169 2008-01-14 05:41:15Z matt $
 * 
 * @package Piwik_GeoIP
 */

require_once PIWIK_INCLUDE_PATH .'/core/DataFiles/Countries.php';
require_once PIWIK_INCLUDE_PATH .'/plugins/UserCountry/functions.php';

/**
 * 
 * @package Piwik_GeoIP
 */
class Piwik_GeoIP_API 
{
	static private $instance = null;
	static public function getInstance()
	{
		if (self::$instance == null)
		{            
			$c = __CLASS__;
			self::$instance = new $c();
		}
		return self::$instance;
	}

	private function getDataTable($name, $idSite, $period, $date, $idSubtable = null)
	{
		Piwik::checkUserHasViewAccess( $idSite );
		$archive = Piwik_Archive::build($idSite, $period, $date );
		$dataTable = $archive->getDataTable($name, $idSubtable);
		$dataTable->filter('Sort', array(Piwik_Archive::INDEX_NB_VISITS));
		$dataTable->queuefilter('ReplaceColumnNames');
		return $dataTable;
	}
	
	public function getGeoIPCountry( $idSite, $period, $date )
	{
		$dataTable = $this->getDataTable('GeoIP_cityByCountry', $idSite, $period, $date);
		$dataTable->queuefilter('ColumnCallbackAddMetadata', array('label', 'code', create_function('$label', 'return $label;')));
		$dataTable->queuefilter('ColumnCallbackAddMetadata', array('label', 'logo', 'Piwik_getFlagFromCode'));
		$dataTable->queuefilter('ColumnCallbackReplace', array('label', 'Piwik_CountryTranslate'));
		$dataTable->queuefilter('AddConstantMetadata', array('logoWidth', 18));
		$dataTable->queuefilter('AddConstantMetadata', array('logoHeight', 12));
		return $dataTable;
	}
	
	public function getGeoIPContinent( $idSite, $period, $date )
	{
		$dataTable = $this->getDataTable('GeoIP_continent', $idSite, $period, $date);
		$dataTable->queuefilter('ColumnCallbackAddMetadata', array('label', 'code', create_function('$label', 'return $label;')));
		$dataTable->queuefilter('ColumnCallbackReplace', array('label', 'Piwik_ContinentTranslate'));
		return $dataTable;
	}

	function getNumberOfDistinctCountries($idSite, $period, $date)
	{
		Piwik::checkUserHasViewAccess( $idSite );
		$archive = Piwik_Archive::build($idSite, $period, $date );
		return $archive->getNumeric('GeoIP_distinctCountries');
	}
	
	function getCitiesFromCountryId($idSite, $period, $date, $idSubtable)
	{
		$dataTable = $this->getDataTable('GeoIP_cityByCountry', $idSite, $period, $date, $idSubtable);
		return $dataTable;		
	}
}


