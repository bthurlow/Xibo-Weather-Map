/**
 * File: c:\Projects\xibo-weather-map\web\modules\vendor\weather-map\scripts\owmmap.js
 * 
 * File Overview: 
 * 
 * Project: scripts
 * 
 * Project Path: c:\Projects\xibo-weather-map\web\modules\vendor\weather-map\scripts
 * 
 * Created Date: Thursday, April 16th 2020, 9:04:09 am
 * 
 * Author: Brian Thurlow
 * ___
 * Last Modified: Wednesday, June 2nd 2021, 2:59:04 pm
 * 
 * Modified By: Brian Thurlow
 * ___
 * Copyright (c) 2020 Brian Thurlow
 * ___
 */
//TODO Cleanup
//TODO Documentation
initMap = (
	owmAPI,
	mbAPI,
	gmAPI,
	mapEle,
	lat,
	long,
	zoom,
	mapType,
	overlayType,
	isInteractive,
	showZoom,
	lang,
	unit,
	iconSet,
	icons,
	showPin,
	pinLat,
	pinLong,
	pinImage,
	pinShadow,
	pinWidth,
	pinHeight,
	pinShadowWidth,
	pinShadowHeight,
	showCities,
) => {
	//TODO Google Maps
	//Load Mapbox
	var mymap = L.map(mapEle, {
		center: new L.LatLng(lat, long),
		zoom: zoom,
		interactive: isInteractive,
		boxZoom: false,
		zoomControl: showZoom,
		scrollWheelZoom: false,
		fadeAnimation: true,
	});
	mymap.attributionControl.setPrefix("");

	var mt;
	if (mapType === "street") {
		mt = "mapbox/streets-v11";
	} else {
		mt = "mapbox/satellite-streets-v11";
	}

	L.tileLayer('https://api.mapbox.com/styles/v1/{id}/tiles/{z}/{x}/{y}?access_token={accessToken}', {
		attribution: 'Map data &copy; <a href="https://www.openstreetmap.org/">OpenStreetMap</a> contributors, <a href="https://creativecommons.org/licenses/by-sa/2.0/">CC-BY-SA</a>, Imagery &copy <a href="https://www.mapbox.com/">Mapbox</a>',
		maxZoom: 18,
		id: mt,
		tileSize: 512,
		zoomOffset: -1,
		accessToken: mbAPI
	}).addTo(mymap);

	//Handle Pin
	if (showPin) {
		var myIcon;
		if (pinShadow && pinShadow !== "") {
			myIcon = L.icon({
				iconUrl: pinImage,
				iconSize: [pinWidth, pinHeight],
				iconAnchor: [pinWidth / 2, pinHeight / 2],
				popupAnchor: [-3, -76],
				shadowUrl: pinShadow,
				shadowSize: [pinShadowWidth, pinShadowHeight],
				shadowAnchor: [pinWidth / 2, pinHeight / 2]
			});
		} else {
			myIcon = L.icon({
				iconUrl: pinImage,
				iconSize: [pinWidth, pinHeight],
				iconAnchor: [pinWidth / 2, pinHeight / 2],
				popupAnchor: [-3, -76],
			});
		}

		// var marker = 
		L.marker([pinLat, pinLong], {
			//title:"Jason's Deli",
			icon: myIcon,
		}).addTo(mymap);
	}

	// Get your own free OWM API key at https://www.openweathermap.org/appid - please do not re-use mine!
	// You don't need an API key for this to work at the moment, but this will change eventually.
	//Overlay Layer Options
	switch (overlayType) {
		case 'precipitation':
			var overlay = L.OWM.precipitation({
				opacity: 0.5,
				temperatureUnit: unit === 'imperial' ? 'F' : 'C',
				appId: owmAPI,
				lang: lang,
				unit: unit,
				showLegend: true,//TODO Need Settable Option
				legendPosition: 'bottomright',//TODO Need Settable Option
			});
			overlay.addTo(mymap);
			break;
		case 'radar':
			var overlay = L.OWM.radar({
				opacity: 0.5,
				temperatureUnit: unit === 'imperial' ? 'F' : 'C',
				appId: owmAPI,
				lang: lang,
				unit: unit,
				showLegend: true,//TODO Need Settable Option
				legendPosition: 'bottomright',//TODO Need Settable Option
			});
			overlay.addTo(mymap);
			break;
		case 'pressure':
			var overlay = L.OWM.pressure({
				opacity: 0.5,
				temperatureUnit: unit === 'imperial' ? 'F' : 'C',
				appId: owmAPI,
				lang: lang,
				unit: unit,
				showLegend: true,//TODO Need Settable Option
				legendPosition: 'bottomright',//TODO Need Settable Option
			});
			overlay.addTo(mymap);
			break;
		case 'clouds':
			var overlay = L.OWM.clouds({
				opacity: 0.5,
				temperatureUnit: unit === 'imperial' ? 'F' : 'C',
				appId: owmAPI,
				lang: lang,
				unit: unit,
				showLegend: true,//TODO Need Settable Option
				legendPosition: 'bottomright',//TODO Need Settable Option
			});
			overlay.addTo(mymap);
			break;
		case 'temperature':
			var overlay = L.OWM.temperature({
				opacity: 0.5,
				temperatureUnit: unit === 'imperial' ? 'F' : 'C',
				appId: owmAPI,
				lang: lang,
				unit: unit,
				showLegend: true,//TODO Need Settable Option
				legendPosition: 'bottomright',//TODO Need Settable Option
			});
			overlay.addTo(mymap);
			break;
		case 'windspeed':
			var overlay = L.OWM.windspeed({
				opacity: 0.5,
				temperatureUnit: unit === 'imperial' ? 'F' : 'C',
				appId: owmAPI,
				lang: lang,
				unit: unit,
				showLegend: true,//TODO Need Settable Option
				legendPosition: 'bottomright',//TODO Need Settable Option
			});
			overlay.addTo(mymap);
			break;
		default:
			break;
	}

	//Handle Cities
	if (showCities) {
		var cities = L.OWM.cities({
			lang: lang,
			minZoom: 5,
			appId: owmAPI,
			unit: unit,
			iconSet: iconSet,
			icons: icons,
			overlayTemplate: 2,
		});
		// console.log(cities);

		cities.addTo(mymap);
	}
}
