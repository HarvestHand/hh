{postEvent name="template_headerGeoIPCountry"}

<script type="text/javascript" src="plugins/CoreHome/templates/sparkline.js"></script>
<div id="leftcolumn">
<h2>Country (GeoIP)</h2>
{$dataTableCountry}

<h2>Continent (GeoIP)</h2>
{$dataTableContinent}
<div class="sparkline">{sparkline src=$urlSparklineCountries} <strong>{$numberDistinctCountries} </strong> distinct countries</div>
</div>

<div id="rightcolumn">
<h2>Provider</h2>
{if isset($provider)}
{$provider}
{/if}
</div>
	

{postEvent name="template_footerGeoIPCountry"}