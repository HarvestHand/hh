<?php
class Piwik_GeoIP_Controller extends Piwik_Controller 
{
	function index()
	{
		$view = new Piwik_View('GeoIP/templates/index.tpl');
		//if(Piwik_PluginsManager::getInstance()->isPluginEnabled('Provider'))
		//{
			$view->provider = Piwik_FrontController::getInstance()->fetchDispatch('Provider','getProvider');
		//}
		$view->urlSparklineCountries = $this->getUrlSparkline('getLastDistinctCountriesGraph');
		$view->numberDistinctCountries = $this->getNumberOfDistinctCountries(true);
		
		$view->dataTableCountry = $this->getGeoIPCountry(true);
		$view->dataTableContinent = $this->getGeoIPContinent(true);
		
		echo $view->render();
	}
	
	function getGeoIPCountry( $fetch = false)
	{
		$view = $this->getStandardDataTableGeoIp(__FUNCTION__, 'GeoIP.getGeoIPCountry', 'getCitiesFromCountryId');
		$view->setColumnTranslation('label', Piwik_Translate('UserCountry_Country'));
		return $this->renderView($view, $fetch);
	}
	
	function getGeoIPContinent( $fetch = false)
	{
		$view = $this->getStandardDataTableGeoIp(__FUNCTION__, "GeoIP.getGeoIPContinent");
		$view->disableOffsetInformation();
		$view->disableSort();
		$view->setColumnTranslation('label', Piwik_Translate('UserCountry_Continent'));
		return $this->renderView($view, $fetch);
	}
	
	function getCitiesFromCountryId( $fetch = false )
	{
		$view = $this->getStandardDataTableGeoIp(__FUNCTION__, 'GeoIP.getCitiesFromCountryId');
		$view->setColumnTranslation('label', Piwik_Translate('GeoIP_ColumnCity'));
		return $this->renderView($view, $fetch);
	}

	protected function getStandardDataTableGeoIp( $currentControllerAction, 
												$APItoCall,
												$controllerActionCalledWhenRequestSubTable = null )
	{
		$view = Piwik_ViewDataTable::factory();
		$view->init( $this->pluginName, $currentControllerAction, $APItoCall, $controllerActionCalledWhenRequestSubTable);
		$view->disableSearchBox();
		$view->disableExcludeLowPopulation();
		
		$this->setPeriodVariablesView($view);
		$column = 'nb_visits';
		if($view->period == 'day')
		{
			$column = 'nb_uniq_visitors';
		}
		$view->setColumnsToDisplay( array('label',$column) );
		$view->setSortedColumn( $column );
		return $view;
	}
	
	function getNumberOfDistinctCountries( $fetch = false)
	{
		return $this->getNumericValue('GeoIP.getNumberOfDistinctCountries');
	}

	function getLastDistinctCountriesGraph( $fetch = false )
	{
		$view = $this->getLastUnitGraph('UserCountry',__FUNCTION__, "GeoIP.getNumberOfDistinctCountries");
		// Note: this shouldn't be 0 there, but somehow the column name is not recorded properly
		$view->setColumnsToDisplay('0');
		return $this->renderView($view, $fetch);
	}
}
