<?php
/**
 * Piwik - Open source web analytics
 * 
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html Gpl v3 or later
 * @version $Id:  $
 */
	
/**
 * @package Piwik_GeoIP
 */
class Piwik_GeoIP extends Piwik_Plugin
{	
	public function getInformation()
	{
		$info = array(
			'name' => 'GeoIP',
			'description' => 'The GeoIP plugin improves user country detection algorithm by using the IP to country database. Also, it will record cities: you can click on a country name to view all stats by city.',
			'author' => 'Mikael Letang, Maciej Zawadziński, Matthieu Aubry',
			'homepage' => 'http://dev.piwik.org/trac/ticket/45',
			'version' => '0.17',
			'translationAvailable' => true,
			'TrackerPlugin' => true, // this plugin must be loaded during the stats logging
		);
		return $info;
	}
	
	protected $geoIpDb = null;
	
	public function __destruct()
	{
		if(!is_null($this->geoIpDb))
		{
			geoip_close($this->geoIpDb);
		}
	}
	
	public function install()
	{
		// add column location_geoip_city, location_geoip_latitude, location_geoip_longitude in the visit table
		$query = "ALTER IGNORE TABLE `".Piwik_Common::prefixTable('log_visit')."` " .
				"ADD `location_geoip_continent` VARCHAR( 100 ) NULL, " .
				"ADD `location_geoip_country` VARCHAR( 100 ) NULL, " .
				"ADD `location_geoip_city` VARCHAR( 100 ) NULL, " . 
				"ADD `location_geoip_latitude` DECIMAL(7,4) NULL, " .
				"ADD `location_geoip_longitude` DECIMAL(7,4) NULL";
		
		// if the column already exist do not throw error. Could be installed twice...
		try {
			Piwik_Exec($query);
		}
		catch(Exception $e){
		}
	}
	
	function uninstall()
	{
		$query = "ALTER TABLE `".Piwik_Common::prefixTable('log_visit')."` " . 
			"DROP `location_geoip_continent`, " . 
			"DROP `location_geoip_country`, " .
			"DROP `location_geoip_city`, " . 
			"DROP `location_geoip_latitude`, " . 
			"DROP `location_geoip_longitude`";
		Piwik_Exec($query);
	}
	
	function getListHooksRegistered()
	{
		$hooks = array(
			'ArchiveProcessing_Day.compute' => 'archiveDay',
			'ArchiveProcessing_Period.compute' => 'archivePeriod',
			'Tracker.newVisitorInformation' => 'logGeoIPInfo',
			'Menu.add' => 'addMenu',
			'WidgetsList.add' => 'addWidget',
		);
		return $hooks;
	}

	function addWidget()
	{
		Piwik_AddWidget( 'GeoIP', 'GeoIP_WidgetCountries', 'GeoIP', 'getGeoIPCountry');
		Piwik_AddWidget( 'GeoIP', 'GeoIP_WidgetContinents', 'GeoIP', 'getGeoIPContinent');
	}
	
	function addMenu()
	{
		Piwik_EditMenuUrl('General_Visitors', 'UserCountry_SubmenuLocations', array('module' => 'GeoIP'));
	}

	function archivePeriod( $notification )
	{
		$archiveProcessing = $notification->getNotificationObject();
		$dataTableToSum = array( 
				'GeoIP_cityByCountry',
				'GeoIP_continent',
		);
		$nameToCount = $archiveProcessing->archiveDataTable($dataTableToSum);
		$mappingFromArchiveName = array(
			'GeoIP_distinctCountries' => 
						array( 	'typeCountToUse' => 'level0',
								'nameTableToUse' => 'GeoIP_cityByCountry',
							),
			'GeoIP_distinctContinents' => 
						array( 	'typeCountToUse' => 'level0',
								'nameTableToUse' => 'GeoIP_continent',
							)
		);
		foreach($mappingFromArchiveName as $name => $infoMapping)
		{
			$typeCountToUse = $infoMapping['typeCountToUse'];
			$nameTableToUse = $infoMapping['nameTableToUse'];
			
			$countValue = $nameToCount[$nameTableToUse]['level0'];
			$archiveProcessing->insertNumericRecord($name, $countValue);
		}
	}

	function archiveDay($notification)
	{
		$archiveProcessing = $notification->getNotificationObject();
		$this->archiveDayAggregateVisits($archiveProcessing);
//		$this->archiveDayAggregateGoals($archiveProcessing);
		$this->archiveDayRecordInDatabase($archiveProcessing);
	}
	
	protected function archiveDayAggregateVisits(Piwik_ArchiveProcessing $archiveProcessing)
	{
		// Continent: for now we just consider it plain; no interaction with country or city
		$labelSQL = 'log_visit.location_geoip_continent';
		$this->interestByContinent = $archiveProcessing->getArrayInterestForLabel($labelSQL);
		
		// For country and city, we need to build our own stats row, with recursion 
		$query = "SELECT 	location_geoip_country, 
							location_geoip_city, 
							count(distinct idvisitor) as nb_uniq_visitors,
							count(*) as nb_visits,
							sum(visit_total_actions) as nb_actions,
							max(visit_total_actions) as max_actions, 
							sum(visit_total_time) as sum_visit_length,							
							sum(case visit_total_actions when 1 then 1 else 0 end) as bounce_count,
							sum(case visit_goal_converted when 1 then 1 else 0 end) as nb_visits_converted  
				 	FROM ".$archiveProcessing->logTable."
				 	WHERE visit_last_action_time >= ?
						AND visit_last_action_time <= ?
				 		AND idsite = ?
				 	GROUP BY location_geoip_country, location_geoip_city";
		
		$query = $archiveProcessing->db->query($query, array( $archiveProcessing->getStartDatetimeUTC(), $archiveProcessing->getEndDatetimeUTC(), $archiveProcessing->idsite ));
		
		$this->interestByCountry = 
			$this->interestByCity =
			$this->interestByCountryAndCity = array();
		
		while($row = $query->fetch() )
		{
			if(!isset($this->interestByCountry[$row['location_geoip_country']])) $this->interestByCountry[$row['location_geoip_country']]= $archiveProcessing->getNewInterestRow();
			if(!isset($this->interestByCity[$row['location_geoip_city']])) $this->interestByCity[$row['location_geoip_city']]= $archiveProcessing->getNewInterestRow();
			if(!isset($this->interestByCountryAndCity[$row['location_geoip_country']][$row['location_geoip_city']])) $this->interestByCountryAndCity[$row['location_geoip_country']][$row['location_geoip_city']]= $archiveProcessing->getNewInterestRow();
			
			$archiveProcessing->updateInterestStats( $row, $this->interestByCountry[$row['location_geoip_country']]);
			$archiveProcessing->updateInterestStats( $row, $this->interestByCity[$row['location_geoip_city']]);
			$archiveProcessing->updateInterestStats( $row, $this->interestByCountryAndCity[$row['location_geoip_country']][$row['location_geoip_city']]);
		}
	}

	protected function archiveDayAggregateGoals($archiveProcessing)
	{
		//- add location_city in log_conversion table
		//- run update for this plugin
		//- add icon in datatable footer
		//- add hook in tracker/goalManager to record city as well
		//- add explanation in plugin description on how to download geoip.dat etc.
		//- on enable the plugin, check that DB is present, otherwise fail activating plugin
		/*
		$query = $archiveProcessing->queryConversionsByDimension("location_geoip_continent,location_geoip_country,location_geoip_city");
		while($row = $query->fetch() )
		{
			if(!isset($this->interestByContinent[$row['location_geoip_continent']][Piwik_Archive::INDEX_GOALS][$row['idgoal']])) $this->interestByContinent[$row['location_geoip_continent']][Piwik_Archive::INDEX_GOALS][$row['idgoal']] = $archiveProcessing->getNewGoalRow();
			$archiveProcessing->updateGoalStats( $row, $this->interestByContinent[$row['location_geoip_continent']][Piwik_Archive::INDEX_GOALS][$row['idgoal']]);

			if(!isset($this->interestByCountry[$row['location_geoip_country']][Piwik_Archive::INDEX_GOALS][$row['idgoal']])) $this->interestByCountry[$row['location_geoip_country']][Piwik_Archive::INDEX_GOALS][$row['idgoal']] = $archiveProcessing->getNewGoalRow();
			$archiveProcessing->updateGoalStats( $row, $this->interestByCountry[$row['location_geoip_country']][Piwik_Archive::INDEX_GOALS][$row['idgoal']]);
			
			if(!isset($this->interestByCountryAndCity[$row['location_geoip_country']][$row['location_geoip_city']][Piwik_Archive::INDEX_GOALS][$row['idgoal']])) $this->interestByCountryAndCity[$row['location_geoip_country']][$row['location_geoip_city']][Piwik_Archive::INDEX_GOALS][$row['idgoal']] = $archiveProcessing->getNewGoalRow();
			$archiveProcessing->updateGoalStats( $row, $this->interestByCountryAndCity[$row['location_geoip_country']][$row['location_geoip_city']][Piwik_Archive::INDEX_GOALS][$row['idgoal']]);
		}
		$archiveProcessing->enrichConversionsByLabelArray($this->interestByContinent);
		$archiveProcessing->enrichConversionsByLabelArray($this->interestByCountry);
		$archiveProcessing->enrichConversionsByLabelArrayHasTwoLevels($this->interestByCountryAndCity);
		*/
	}
	
	protected function archiveDayRecordInDatabase($archiveProcessing)
	{
		$numericRecords = array(
			'GeoIP_distinctCountries'	=> count($this->interestByCountry),
			'GeoIP_distinctCities' 		=> count($this->interestByCity),
		);
		foreach($numericRecords as $name => $value)
		{
			$archiveProcessing->insertNumericRecord($name, $value);
		}

		$dataTable = $archiveProcessing->getDataTableSerialized($this->interestByContinent);
		$archiveProcessing->insertBlobRecord('GeoIP_continent', $dataTable);
		destroy($dataTable);
		
		$dataTable = $archiveProcessing->getDataTableSerialized($this->interestByCountry);
		$archiveProcessing->insertBlobRecord('GeoIP_country', $dataTable);
		destroy($dataTable);
		
		$dataTable = $archiveProcessing->getDataTableWithSubtablesFromArraysIndexedByLabel($this->interestByCountryAndCity, $this->interestByCountry);
		$archiveProcessing->insertBlobRecord('GeoIP_cityByCountry', $dataTable->getSerialized());
		destroy($dataTable);
	}
	
	static protected $defaultLocationInfo = array(
			'country_code' => 'xx',
			'continent' => 'unk',
			'city' => 'Unknown',
			'latitude' => null,
			'longitude' => null
	);
	
	public function logGeoIPInfo($notification)
	{
		$visitorInfo =& $notification->getNotificationObject();
		
		if( !empty($_SERVER['GEOIP_COUNTRY_CODE']) )
		{
			$locationInfo = $this->getLocationInfoModGeoip();
		}
		else
		{
			try {
				$this->initGeoIpDatabase();	
				$locationInfo = $this->getLocationInfo($visitorInfo['location_ip']);
			} catch(Exception $e) {
				$locationInfo = self::$defaultLocationInfo;
			}
		}
		
		$mappingVisitorInfoToGeoIp = array(
			'location_geoip_country' => 'country_code',
			'location_geoip_continent' => 'continent',
			'location_geoip_city' => 'city',
			'location_geoip_latitude' => 'latitude',
			'location_geoip_longitude' => 'longitude',
		);
		foreach($mappingVisitorInfoToGeoIp as $visitorInfoKey => $geoIpKey)
		{
			if(!empty($locationInfo[$geoIpKey]))
			{
				$visitorInfo[$visitorInfoKey] = $locationInfo[$geoIpKey];
			}
		}
		
	}

	/**
	 * Returns various location information (continent, country, city, latitude, longitude)
	 * based on mod_geoip from apache
	 * 
	 * @return array
	 * 
	 */
	protected function getLocationInfoModGeoip()
	{
		$locationInfo = array();
		$locationInfo['country_code'] = (isset($_SERVER['GEOIP_COUNTRY_CODE'])) ? strtolower($_SERVER['GEOIP_COUNTRY_CODE']) : self::$defaultLocationInfo['country_code'];
		$locationInfo['city'] = (isset($_SERVER['GEOIP_CITY'])) ? utf8_encode($_SERVER['GEOIP_CITY']) : self::$defaultLocationInfo['city'];
		$locationInfo['latitude'] = (isset($_SERVER['GEOIP_LATITUDE'])) ? round($_SERVER['GEOIP_LATITUDE'],4) : self::$defaultLocationInfo['latitude'];
		$locationInfo['longitude'] = (isset($_SERVER['GEOIP_LONGITUDE'])) ? round($_SERVER['GEOIP_LONGITUDE'],4) : self::$defaultLocationInfo['longitude'];
		$locationInfo['continent'] = Piwik_Common::getContinent($locationInfo['country_code']);
		return $locationInfo;
	}
	
	/**
	 * Returns various location information (continent, country, city, latitude, longitude)
	 * given the IP
	 * 
	 * @param long $ip
	 * 
	 * @return array
	 */
	protected function getLocationInfo($ip)
	{
		$record = GeoIP_record_by_addr($this->geoIpDb, Piwik_Common::long2ip($ip));
		$locationInfo = self::$defaultLocationInfo;
		if( empty($record) )
		{
			return $locationInfo;
		}
		if(isset($record->country_code) && !empty($record->country_code))
		{
			$locationInfo['country_code'] = strtolower($record->country_code);
		}
		if(isset($record->country_code))	$locationInfo['continent'] = Piwik_Common::getContinent($locationInfo['country_code']);
		if(isset($record->city)) 			$locationInfo['city'] = utf8_encode($record->city);
		if(isset($record->latitude)) 		$locationInfo['latitude'] = round($record->latitude,4);
		if(isset($record->longitude)) 		$locationInfo['longitude'] = round($record->longitude,4);
		return $locationInfo;
	}
	
	protected function initGeoIpDatabase()
	{
		static $cache = false;

		require_once PIWIK_INCLUDE_PATH .'/plugins/GeoIP/libs/geoipcity.inc';
		$geoIPDataFile = PIWIK_INCLUDE_PATH . '/plugins/GeoIP/libs/GeoLiteCity.dat';
		// backward compatibility, old instructions asked to rename to GeoIP.dat
		if(!is_file($geoIPDataFile))
		{
			$geoIPDataFile = PIWIK_INCLUDE_PATH . '/plugins/GeoIP/libs/GeoIP.dat';
		}
		if(!is_file($geoIPDataFile))
		{
			echo "ERROR: GeoIp file ".$geoIPDataFile." could not be found!
								Make sure you downloaded and extract the GeoIp city database as explained on http://dev.piwik.org/trac/ticket/45";
			exit;
		}

		if($this->geoIpDb)
		{
			// load the .dat file into memory (e.g., for bulk requests)
			if(!$cache)
			{
				geoip_close($this->geoIpDb);
				$this->geoIpDb = geoip_open($geoIPDataFile, GEOIP_MEMORY_CACHE);
				$cache = true;
			}
			return;
		}

		// default, assume a single request, don't load the .dat file into memory
		$this->geoIpDb = geoip_open($geoIPDataFile, GEOIP_STANDARD);
	}
	
	public function footerUserCountry($notification)
	{
		$out =& $notification->getNotificationObject();
		$out .= '<h2>GeoIP Country</h2>';
		$out .= Piwik_FrontController::getInstance()->fetchDispatch('GeoIP','getGeoIPCountry');
		$out .= '<h2>GeoIP Continent</h2>';
		$out .= Piwik_FrontController::getInstance()->fetchDispatch('GeoIP','getGeoIPContinent');
	}

	public function updateExistingVisitsWithGeoIpData()
	{
		$query = "SELECT count(*) as cnt FROM ".Piwik_Common::prefixTable('log_visit');
		$count = Piwik_FetchOne($query);
		$start = 0;
		$limit = 1000;
		
		$this->initGeoIpDatabase();
		Piwik::log("$count rows to process in ".Piwik_Common::prefixTable('log_visit')."...");
		flush();
		while( $start < $count )
		{
			$rows = Piwik_FetchAll("SELECT idvisit, location_ip 
								FROM ".Piwik_Common::prefixTable('log_visit')." 
								LIMIT $start, $limit");
			if(!count($rows))
			{
				continue;
			}

			foreach ( $rows as $row )
			{
				$locationInfo = $this->getLocationInfo($row['location_ip']);
				Piwik_Query("
						UPDATE ".Piwik_Common::prefixTable('log_visit')." 
						SET location_geoip_country = ?, 
							location_geoip_continent = ?, 
							location_geoip_city = ?, 
							location_geoip_latitude = ?, 
							location_geoip_longitude = ? 
						WHERE idvisit = ?", 
						array(	$locationInfo['country_code'],
								$locationInfo['continent'],
								$locationInfo['city'],
								$locationInfo['latitude'], 
								$locationInfo['longitude'], 
								$row['idvisit']
						)
				);
			}
			Piwik::log(round($start * 100 / $count) . "% done...");
			flush();
			$start += $limit;
		}
		Piwik::log("done!");
	}
}
