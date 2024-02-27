# PHP Weather Provider for OpenWeatherMap

![Packagist Version](https://img.shields.io/packagist/v/php-weather/openweathermap)  
![PHP Weather Common Version](https://img.shields.io/badge/phpweather--core-0.4.*-brightgreen)
![PHP Weather HTTP Provider Version](https://img.shields.io/badge/phpweather--http--provider-0.6.*-brightgreen)  
![GitHub Release Date](https://img.shields.io/github/release-date/php-weather/openweathermap)
![GitHub commits since tagged version](https://img.shields.io/github/commits-since/php-weather/openweathermap/0.2.1)
![GitHub last commit](https://img.shields.io/github/last-commit/php-weather/openweathermap)  
![GitHub Workflow Status](https://img.shields.io/github/actions/workflow/status/php-weather/openweathermap/php.yml?branch=main)
![GitHub](https://img.shields.io/github/license/php-weather/openweathermap)
![Packagist PHP Version Support](https://img.shields.io/packagist/php-v/php-weather/openweathermap)

This is the [OpenWeatherMap](https://openweathermap.org/) provider from PHP Weather.

## Installation

Via Composer

```shell
composer require php-weather/openweathermap
```

## Usage

```php
$openWeatherMapKey = 'key';

$httpClient = new \Http\Adapter\Guzzle7\Client();
$openweathermap = new \PhpWeather\Provider\OpenWeatherMap\OpenWeatherMap($httpClient, $openWeatherMapKey);

$latitude = 47.873;
$longitude = 8.004;

$currentWeatherQuery = \PhpWeather\Common\WeatherQuery::create($latitude, $longitude);
$currentWeather = $openweathermap->getCurrentWeather($currentWeatherQuery);
```