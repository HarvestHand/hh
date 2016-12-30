<?php
Piwik_Query("ALTER TABLE ". Piwik_Common::prefixTable('log_visit') . " 
				CHANGE `location_geoip_latitude` `location_geoip_latitude` DECIMAL(7,4) NULL");
Piwik_Query("ALTER TABLE ". Piwik_Common::prefixTable('log_visit') . " 
				CHANGE `location_geoip_longitude` `location_geoip_longitude` DECIMAL(7,4) NULL");
?>