<?php
$toUpdate = array("location_geoip_continent", "location_geoip_country", "location_geoip_city");
foreach($toUpdate as $field)
{
	Piwik_Query("ALTER TABLE ". Piwik_Common::prefixTable('log_visit') . " 
				CHANGE `".$field."` `".$field."` VARCHAR( 100 ) NULL");
}				
