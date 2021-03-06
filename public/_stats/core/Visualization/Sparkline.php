<?php
/**
 * Piwik - Open source web analytics
 *
 * @link http://piwik.org
 * @license http://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 * @version $Id: Sparkline.php 5999 2012-03-08 02:56:21Z matt $
 *
 * @category Piwik
 * @package Piwik
 */

/**
 * @see libs/sparkline/lib/Sparkline_Line.php
 * @link http://sparkline.org
 */
require_once PIWIK_INCLUDE_PATH . '/libs/sparkline/lib/Sparkline_Line.php';

/**
 * Renders a sparkline image given a PHP data array.
 * Using the Sparkline PHP Graphing Library sparkline.org 
 * 
 * @package Piwik
 * @subpackage Piwik_Visualization
 */
class Piwik_Visualization_Sparkline implements Piwik_View_Interface
{
	/**
	 * Array with format: array( x, y, z, ... )
	 * @param array $data
	 */
	function setValues($data)
	{
		$this->values = $data;
	}
	
	static public function getWidth()
	{
		return 100;
	}
	
	static public function getHeight()
	{
		return 25;
	}
	
	function main()
	{
		$width = self::getWidth();
		$height = self::getHeight();
		
		$sparkline = new Sparkline_Line();
		$sparkline->SetColor('lineColor', 22, 44, 74); // dark blue
		$sparkline->SetColorHtml('red', '#FF7F7F');
		$sparkline->SetColorHtml('blue', '#55AAFF');
		$sparkline->SetColorHtml('green', '#75BF7C');
		
		$min = $max = $last = null;
		$i = 0;
		$toRemove = array('%', str_replace('%s', '', Piwik_Translate('General_Seconds')));
		foreach($this->values as $value)
		{
			// 50% and 50s should be plotted as 50
			$value = str_replace($toRemove, '', $value);
			// replace localized decimal separator
			$value = str_replace(',', '.', $value);
			if ($value == '')
			{
				$value = 0;
			}
			
			$sparkline->SetData($i, $value);
			
			if(	null == $min || $value <= $min[1])
			{
				$min = array($i, $value);
			}
			if(null == $max || $value >= $max[1]) 
			{
				$max = array($i, $value);
			}
			$last = array($i, $value);
			$i++;
		}
		$sparkline->SetYMin(0);
		$sparkline->SetYMax($max[1]);
		$sparkline->SetPadding( 3, 0, 2, 0 ); // top, right, bottom, left
//		$font = FONT_2;
		$sparkline->SetFeaturePoint($min[0],  $min[1],  'red', 5);
		$sparkline->SetFeaturePoint($max[0],  $max[1],  'green', 5);
		$sparkline->SetFeaturePoint($last[0], $last[1], 'blue', 5);
		$sparkline->SetLineSize(3); // for renderresampled, linesize is on virtual image
		$ratio = 1;
//		var_dump($min);var_dump($max);var_dump($lasts);exit;
		$sparkline->RenderResampled($width*$ratio, $height*$ratio);
		
		$this->sparkline = $sparkline;
	}
	
	function render()
	{
		$this->sparkline->Output();
	}
}
