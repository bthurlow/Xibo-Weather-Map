<?php
/*
 * File: c:\Projects\xibo-weather-map\custom\weather-map\weather-map.php
 * 
 * File Overview: Weather Map Module Main
 * 
 * Project: weather-map
 * 
 * Project Path: c:\Projects\xibo-weather-map\custom\weather-map
 * 
 * Created Date: Thursday, May 7th 2020, 3:22:47 pm
 * 
 * Author: Brian Thurlow
 * ___
 * Last Modified: Sunday, June 6th 2021, 11:49:25 am
 * 
 * Modified By: Brian Thurlow
 * ___
 * Copyright (c) 2020 Brian Thurlow
 * ___
 */

namespace Xibo\Custom\weathermap;

use Carbon\Carbon;
use Carbon\Factory;
use GuzzleHttp\Client;
use Illuminate\Support\Str;
use Respect\Validation\Validator as v;
use Slim\Http\Response as Response;
use Slim\Http\ServerRequest as Request;
use Xibo\Entity\Media;
use Xibo\Helper\DateFormatHelper;
use Xibo\Helper\Translate;
use Xibo\Support\Exception\ConfigurationException;
use Xibo\Support\Exception\GeneralException;
use Xibo\Support\Exception\InvalidArgumentException;

class weathermap extends \Xibo\Widget\ModuleWidget
{
	/**
	 * weathermap constructor.
	 * @Override
	 */
	public function init()
	{
		$this->resourceFolder = '../custom/weathermap/resources';

		// Initialise extra validation rules
		v::with('Xibo\\Validation\\Rules\\');
	}

	/** @inheritDoc */
	public function layoutDesignerJavaScript()
	{
		return 'weathermap-designer-javascript';
	}

	/**
	 * Install or Update this module
	 * @param ModuleFactory $moduleFactory
	 * @Override
	 */
	public function installOrUpdate($moduleFactory)
	{
		// Install
		if ($this->module == null) {
			$module = $moduleFactory->createEmpty();
			$module->name = 'Weather Map';
			$module->type = 'weathermap';
			$module->viewPath = '../custom/weathermap';
			$module->class = 'Xibo\Custom\weathermap\weathermap';
			$module->description = 'A module to map OpenWeatherMap';
			$module->enabled = 1;
			$module->previewEnabled = 1;
			$module->assignable = 1;
			$module->regionSpecific = 1;
			$module->renderAs = 'html';
			$module->schemaVersion = $this->codeSchemaVersion;
			$module->defaultDuration = 240;
			$module->settings = [];

			$this->setModule($module);
			$this->installModule();
		}

		// Check we are all installed
		$this->installFiles();
	}
	/**
	 * Install all files
	 */
	public function installFiles()
	{
		// Load Xibo Resources
		$this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/jquery-1.11.1.min.js')->save();
		$this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-layout-scaler.js')->save();
		$this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/xibo-image-render.js')->save();
		$this->mediaFactory->createModuleSystemFile(PROJECT_ROOT . '/modules/vendor/bootstrap.min.css')->save();

		// Install files from a folder
		foreach ($this->mediaFactory->createModuleFileFromFolder($this->resourceFolder) as $media) {
			/* @var Media $media */
			$media->save();
		}
	}
	/**
	 * Form for updating the module settings
	 * @return Name of the Settings-Form
	 * @Override
	 */
	public function settingsForm()
	{
		// Return the name of the TWIG file to render the settings form
		return 'weathermap-form-settings';
	}

	/**
	 * Process any module settings
	 * @return An array of the processed settings.
	 * @Override
	 */
	public function settings()
	{
		$sanitizedParams = $this->getSanitizer();

		// Process any module settings you asked for.
		$owmApiKey = $sanitizedParams->getString('owmApiKey');
		$mbApiKey = $sanitizedParams->getString('mbApiKey');
		$gmApiKey = $sanitizedParams->getString('gmApiKey');
		$cachePeriod = $sanitizedParams->getInt('cachePeriod'); //, ['default' => 15]

		if ($this->module->enabled != 0) {
			if ($owmApiKey == '') {
				throw new \InvalidArgumentException(__('Missing OpenWeatherMaps API Key'));
			}

			if ($mbApiKey == '' && $gmApiKey == '') {
				throw new \InvalidArgumentException(__('You must enter atleast one map key. Choose either mapbox or google maps'));
			}

			// throw new \InvalidArgumentException(__('Cache Period: ' & strval($cachePeriod)));

			if ($cachePeriod <= 0) {
				throw new \InvalidArgumentException(__('Cache period must be a positive number'));
				// , 'cachePeriod'
			}
		}

		$this->module->settings['owmApiKey'] = $owmApiKey;
		$this->module->settings['mbApiKey'] = $mbApiKey;
		$this->module->settings['gmApiKey'] = $gmApiKey;
		$this->module->settings['cachePeriod'] = $cachePeriod;

		return $this->module->settings;
		// return $response;
	}

	/**
	 * Validates the settings
	 * @Override
	 */
	public function validate()
	{
		if ($this->getUseDuration() == 1 && $this->getDuration() == 0)
			throw new \InvalidArgumentException(__('You must enter a duration.'));

		if ($this->getOption('zoom') == '' || $this->getOption('zoom') < 0 || $this->getOption('zoom') > 18) {
			throw new \InvalidArgumentException(__('Zoom should be between 0 and 18'));
		}
		// TODO More validation
	}

	//Not needed for xibo v2
	// /**
	//  * Adds a Widget
	//  * @Override
	//  */
	// public function add()
	// {
	// 	$this->setCommonOptions();
	// 	$this->validate();
	// 	$this->saveWidget();
	// }

	/**
	 * Edit the Widget
	 * @Override
	 */
	public function edit()
	{
		$this->setCommonOptions();
		$this->validate();
		$this->saveWidget();
	}

	/**
	 * Set common options from Request Params
	 */
	private function setCommonOptions()
	{
		$sanitizedParams = $this->getSanitizer();

		$this->setOption('name', $sanitizedParams->getString('name'));
		$this->setDuration($sanitizedParams->getInt('duration', $this->getDuration()));
		$this->setUseDuration($sanitizedParams->getCheckbox('useDuration'));
		$this->setOption('enableStat', $sanitizedParams->getString('enableStat'));

		// Map Options
		$this->setOption('useDisplayLocation', $sanitizedParams->getCheckbox('useDisplayLocation'));
		$this->setOption('longitude', $sanitizedParams->getDouble('longitude'));
		$this->setOption('latitude', $sanitizedParams->getDouble('latitude'));
		$this->setOption('zoom', $sanitizedParams->getInt('zoom'));
		$this->setOption('language', $sanitizedParams->getString('language'));
		$this->setOption('mapType', $sanitizedParams->getString('mapType'));
		$this->setOption('isInteractive', $sanitizedParams->getCheckbox('isInteractive'));
		$this->setOption('showZoom', $sanitizedParams->getCheckbox('showZoom'));
		$this->setOption('overlayType', $sanitizedParams->getString('overlayType'));
		$this->setOption('showLegend', $sanitizedParams->getCheckbox('showLegend'));
		$this->setOption('legendPosition', $sanitizedParams->getString('legendPosition'));

		//Pin Options
		$this->setOption('showPin', $sanitizedParams->getCheckbox('showPin'));
		$this->setOption('useMapLatLong', $sanitizedParams->getCheckbox('useMapLatLong'));
		if ($this->getOption('useMapLatLong')) {
			$this->setOption('pinLatitude', $this->getOption('latitude'));
			$this->setOption('pinLongitude', $this->getOption('longitude'));
		} else {
			$this->setOption('pinLatitude', $sanitizedParams->getDouble('pinLatitude'));
			$this->setOption('pinLongitude', $sanitizedParams->getDouble('pinLongitude'));
		}
		$this->setOption('customPinShadowImage', $sanitizedParams->getInt('customPinShadowImage'));
		$this->setOption('customPinImage', $sanitizedParams->getInt('customPinImage'));
		$this->setOption('usePinDefault', $sanitizedParams->getCheckbox('usePinDefault'));
		$this->setOption('pinWidth', $sanitizedParams->getInt('pinWidth'));
		$this->setOption('pinHeight', $sanitizedParams->getInt('pinHeight'));
		$this->setOption('pinShadowWidth', $sanitizedParams->getInt('pinShadowWidth'));
		$this->setOption('pinShadowHeight', $sanitizedParams->getInt('pinShadowHeight'));

		//City Options
		$this->setOption('showCities', $sanitizedParams->getCheckbox('showCities'));
		$this->setOption('units', $sanitizedParams->getString('units'));
		$this->setOption('iconSet', $sanitizedParams->getString('iconSet'));
	}

	/**
	 * Preview code for a module
	 * 
	 * @param int $width
	 * @param int $height
	 * @param int $scaleOverride The Scale Override
	 * @return string The Rendered Content
	 * @Override
	 */
	public function preview($width, $height, $scaleOverride = 0)
	{
		return $this->previewAsClient($width, $height, $scaleOverride);
	}

	/**
	 * GetResource for the Graph page
	 * 
	 * @param int $displayId
	 * @return mixed|string
	 * @Override
	 */
	public function getResource($displayId = 0)
	{
		// Get the Lat/Long
		$defaultLat = $this->getConfig()->getSetting('DEFAULT_LAT');
		$defaultLong = $this->getConfig()->getSetting('DEFAULT_LONG');

		//Device Location
		if ($this->getOption('useDisplayLocation') == 0) {
			// Use the display ID or the default.
			if ($displayId != 0) {

				$display = $this->displayFactory->getById($displayId);

				if (
					$display->latitude != '' && $display->longitude != ''
					&& v::latitude()->validate($display->latitude)
					&& v::longitude()->validate($display->longitude)
				) {
					$defaultLat = $display->latitude;
					$defaultLong = $display->longitude;
				} else {
					$this->getLog()->info('Warning, display ' .  $display->display . ' does not have a lat/long or they are invalid!');
				}
			}
		} else {
			$defaultLat = $this->getOption('latitude', 35.670962);
			$defaultLong = $this->getOption('longitude', -88.852231);
		}

		// Load in the template
		$data = [];

		// Options XIBO needs for rendering - see JavaScript at the end of this function
		$options = array(
			'type' => $this->getModuleType(),
			'fx' => $this->getOption('effect', 'noAnim'),
			'speed' => $this->getOption('speed', 500),
			'useDuration' => $this->getUseDuration(),
			'duration' => $this->getDuration(),
			'originalWidth' => $this->region->width,
			'originalHeight' => $this->region->height,
			'previewWidth' => $this->getSanitizer()->getDouble('width', 0),
			'previewHeight' => $this->getSanitizer()->getDouble('height', 0),
			'scaleOverride' => $this->getSanitizer()->getDouble('scale_override', 10),
		);

		$containerId = 'wm-map-' . $displayId;

		// Head Content contains all needed scripts and styles
		$headContent  = '<meta name="viewport" content="width=device-width, initial-scale=1">';
		$headContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('library/wm-leaflet.js') . '"></script>' . "\n";
		$headContent  .= '<script type="text/javascript" src="' . $this->getResourceUrl('library/wm-owmleaflet.js') . '"></script>' . "\n";
		$headContent  .= '<script type="text/javascript" src="' . $this->getResourceUrl('library/wm-owmmap.js') . '"></script>' . "\n";
		$headContent  .= '<link rel="stylesheet" href="' . $this->getResourceUrl('library/wm-leaflet.css') . '">' . "\n";
		$headContent  .= '<link rel="stylesheet" href="' . $this->getResourceUrl('vendor/bootstrap.min.css') . '">' . "\n";
		$headContent  .= '<link rel="stylesheet" href="' . $this->getResourceUrl('library/wm-cities.css') . '">' . "\n";
		$headContent .= '<style type="text/css">html,body,#' . $containerId . ' {width: 100%;height: 100%;margin: 0;} #content{height:100%;}';
		$headContent .= '</style>';
		$data['head'] = $headContent;

		// Body content
		$data['body'] = '<div id="' . $containerId . '"></div>';

		$icons = $this->getIcons($this->getOption('iconSet', 'default'));


		//After Body Javascript
		//Xibo Items
		$javaScriptContent  = '<script type="text/javascript" src="' . $this->getResourceUrl('vendor/jquery-1.11.1.min.js') . '"></script>';
		$javaScriptContent .= '<script type="text/javascript" src="' . $this->getResourceUrl('xibo-layout-scaler.js') . '"></script>';

		if ($this->getOption('usePinDefault')) {
			$defaultMarker = $this->getResourceUrl('library/wm-marker-icon.png');
			$defaultMarkerShadow = $this->getResourceUrl('library/wm-marker-shadow.png');
			$this->setOption('pinImage', $defaultMarker);
			$this->setOption('pinShadow', $defaultMarkerShadow);
		} else {
			$media = $this->mediaFactory->getById($this->getOption('customPinImage'));
			$this->setOption('pinImage', $this->getResourceUrl($media->name));

			if (strlen($this->getOption('customPinShadowImage')) > 0) {
				$media = $this->mediaFactory->getById($this->getOption('customPinShadowImage'));
				$this->setOption('pinShadow', $this->getResourceUrl($media->name));
			} else {
				$this->setOption('pinShadow', null);
			}
		}

		$javaScriptContent .= "<script>
		$(document).ready(function() {
			var options = " . json_encode($options) . "
			// $('#" . $containerId . "').xiboLayoutScaler(options);
			//console.log('" . $this->getOption('pinImage') . "');		
			initMap(
				'" . $this->getSetting('owmApiKey', '') . "', //OpenWeather Key
				'" . $this->getSetting('mbApiKey', '') . "', //MapBox Key
				'" . $this->getSetting('gmApiKey', '') . "', //Google Maps Key
				'" . $containerId . "', //Map Element
				" . $defaultLat . ", //Map Center Lat
				" . $defaultLong . ", //Map Center Long
				" . $this->getOption('zoom', 10) . ", //Zoom
				'" . $this->getOption('mapType', "street") . "', //Map Type
				'" . $this->getOption('overlayType', "precipitation") . "', //Weather Overlay Type
				" . $this->getOption('isInteractive', false) . ", //Make map interactive
				" . $this->getOption('showZoom', false) . ", //Show the zoom controls
				'" . $this->getOption('language', "en") . "', 
				'" . $this->getOption('units', 'imperial') . "',
				'" . $this->getOption('iconSet', 'default') . "',
				" . json_encode($icons) . ",
				" . $this->getOption('showPin', false) . ",
				" . $this->getOption('pinLatitude', false) . ",
				" . $this->getOption('pinLongitude', false) . ",
				'" . $this->getOption('pinImage', '') . "',
				'" . $this->getOption('pinShadow', '') . "',
				" . $this->getOption('pinWidth', 25) . ",
				" . $this->getOption('pinHeight', 41) . ",
				" . $this->getOption('pinShadowWidth', 41) . ",
				" . $this->getOption('pinShadowHeight', 41) . ",
				" . $this->getOption('showCities', false) . ",
				" . $this->getOption('showLegend', false) . ", // Show Overlay Legend
				'" . $this->getOption('legendPosition', 'bottomright') . "', // Overlay Legend Position
			);
		});
		</script>";

		$data['body'] .= $javaScriptContent;

		//Return
		return $this->renderTemplate($data);
	}
	/**
	 * Returns if this module is valid or not.
	 *   0 => Invalid
	 *   1 => Valid
	 *   2 => Unknown
	 * @return Validation-Level
	 * @Override
	 */
	public function isValid()
	{
		// Can't be sure because the client does the rendering
		return 2;
	}

	private function getIcons(string $iconSet)
	{
		$arr = [];
		switch ($iconSet) {
			case 'amchartsAnimated':
				$arr = [
					'01d' => $this->getResourceUrl('library/wm-amc-ani-day.svg'), //'clear sky day'
					'01n' => $this->getResourceUrl('library/wm-amc-ani-night.svg'), //clear sky night
					'02d' => $this->getResourceUrl('library/wm-amc-ani-cloudy-day-1.svg'), //few clouds day
					'02n' => $this->getResourceUrl('library/wm-amc-ani-cloudy-night-1.svg'), //few clouds night
					'03d' => $this->getResourceUrl('library/wm-amc-ani-cloudy-day-3.svg'), //scattered clouds day
					'03n' => $this->getResourceUrl('library/wm-amc-ani-cloudy-night-3.svg'), //scattered clouds night
					'04d' => $this->getResourceUrl('library/wm-amc-ani-cloudy.svg'), //broken clouds day
					'04n' => $this->getResourceUrl('library/wm-amc-ani-cloudy.svg'), //broken clouds night
					'09d' => $this->getResourceUrl('library/wm-amc-ani-rainy-6.svg'), //shower rain day
					'09n' => $this->getResourceUrl('library/wm-amc-ani-rainy-6.svg'), //shower rain night
					'10d' => $this->getResourceUrl('library/wm-amc-ani-rainy-1.svg'), //rain day
					'10n' => $this->getResourceUrl('library/wm-amc-ani-rainy-6.svg'), //rain night
					'11d' => $this->getResourceUrl('library/wm-amc-ani-thunder.svg'), //thunderstorm day
					'11n' => $this->getResourceUrl('library/wm-amc-ani-thunder.svg'), //thunderstorm night
					'13d' => $this->getResourceUrl('library/wm-amc-ani-snowy-1.svg'), //snow day
					'13n' => $this->getResourceUrl('library/wm-amc-ani-snowy-5.svg'), //snow night
					'50d' => $this->getResourceUrl('library/wm-amc-ani-rainy-2.svg'), //mist day
					'50n' => $this->getResourceUrl('library/wm-amc-ani-rainy-4.svg'), //mist night
				];
				break;
			case 'amchartsStatic':
				$arr = [
					'01d' => $this->getResourceUrl('library/wm-amc-sta-day.svg'), //'clear sky day'
					'01n' => $this->getResourceUrl('library/wm-amc-sta-night.svg'), //clear sky night
					'02d' => $this->getResourceUrl('library/wm-amc-sta-cloudy-day-1.svg'), //few clouds day
					'02n' => $this->getResourceUrl('library/wm-amc-sta-cloudy-night-1.svg'), //few clouds night
					'03d' => $this->getResourceUrl('library/wm-amc-sta-cloudy-day-3.svg'), //scattered clouds day
					'03n' => $this->getResourceUrl('library/wm-amc-sta-cloudy-night-3.svg'), //scattered clouds night
					'04d' => $this->getResourceUrl('library/wm-amc-sta-cloudy.svg'), //broken clouds day
					'04n' => $this->getResourceUrl('library/wm-amc-sta-cloudy.svg'), //broken clouds night
					'09d' => $this->getResourceUrl('library/wm-amc-sta-rainy-6.svg'), //shower rain day
					'09n' => $this->getResourceUrl('library/wm-amc-sta-rainy-6.svg'), //shower rain night
					'10d' => $this->getResourceUrl('library/wm-amc-sta-rainy-1.svg'), //rain day
					'10n' => $this->getResourceUrl('library/wm-amc-sta-rainy-6.svg'), //rain night
					'11d' => $this->getResourceUrl('library/wm-amc-sta-thunder.svg'), //thunderstorm day
					'11n' => $this->getResourceUrl('library/wm-amc-sta-thunder.svg'), //thunderstorm night
					'13d' => $this->getResourceUrl('library/wm-amc-sta-snowy-1.svg'), //snow day
					'13n' => $this->getResourceUrl('library/wm-amc-sta-snowy-5.svg'), //snow night
					'50d' => $this->getResourceUrl('library/wm-amc-sta-rainy-2.svg'), //mist day
					'50n' => $this->getResourceUrl('library/wm-amc-sta-rainy-4.svg'), //mist night
				];
				break;
			default:
				break;
		}
		return $arr;
	}
}
