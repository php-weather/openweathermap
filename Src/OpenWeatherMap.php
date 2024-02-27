<?php
declare(strict_types=1);

namespace PhpWeather\Provider\OpenWeatherMap;

use DateTime;
use DateTimeZone;
use PhpWeather\Common\Source;
use PhpWeather\Common\UnitConverter;
use PhpWeather\Constants\Type;
use PhpWeather\Constants\Unit;
use PhpWeather\Exception\NoWeatherData;
use PhpWeather\HttpProvider\AbstractHttpProvider;
use PhpWeather\Weather;
use PhpWeather\WeatherCollection;
use PhpWeather\WeatherQuery;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Throwable;

class OpenWeatherMap extends AbstractHttpProvider
{
    /**
     * @var Source[]|null
     */
    private ?array $sources = null;
    private string $key;

    public function __construct(ClientInterface $client, string $key, ?RequestFactoryInterface $requestFactory = null)
    {
        $this->key = $key;
        parent::__construct($client, $requestFactory);
    }

    /**
     * @param  WeatherQuery  $query
     * @return Weather
     * @throws Throwable
     */
    public function getHistorical(WeatherQuery $query): Weather
    {
        throw new NoWeatherData();
    }

    /**
     * @param  WeatherQuery  $query
     * @return WeatherCollection
     * @throws Throwable
     */
    public function getHistoricalTimeLine(WeatherQuery $query): WeatherCollection
    {
        throw new NoWeatherData();
    }

    protected function getCurrentWeatherQueryString(WeatherQuery $query): string
    {
        return sprintf(
            "https://api.openweathermap.org/data/2.5/weather?lat=%s&lon=%s&appid=%s&units=%s",
            $query->getLatitude(),
            $query->getLongitude(),
            $this->key,
            Unit::METRIC
        );
    }

    protected function getForecastWeatherQueryString(WeatherQuery $query): string
    {
        return sprintf(
            "https://api.openweathermap.org/data/2.5/forecast?lat=%s&lon=%s&appid=%s&units=%s",
            $query->getLatitude(),
            $query->getLongitude(),
            $this->key,
            Unit::METRIC
        );
    }

    /**
     * @param  WeatherQuery  $query
     * @return string
     * @throws Throwable
     */
    protected function getHistoricalWeatherQueryString(WeatherQuery $query): string
    {
        throw new NoWeatherData();
    }

    /**
     * @param  WeatherQuery  $query
     * @return string
     * @throws Throwable
     */
    protected function getHistoricalTimeLineWeatherQueryString(WeatherQuery $query): string
    {
        throw new NoWeatherData();
    }

    protected function mapRawData(float $latitude, float $longitude, array $rawData, ?string $type = null, ?string $units = null): Weather|WeatherCollection
    {
        if (!array_key_exists('list', $rawData)) {
            return $this->mapItemRawdata($latitude, $longitude, $rawData, $type, $units);
        }

        if (
            array_key_exists('city', $rawData) &&
            is_array($rawData['city']) &&
            array_key_exists('coord', $rawData['city']) &&
            is_array($rawData['city']['coord'])
        ) {
            if (array_key_exists('lat', $rawData['city']['coord'])) {
                $latitude = $rawData['city']['coord']['lat'];
            }
            if (array_key_exists('lon', $rawData['city']['coord'])) {
                $longitude = $rawData['city']['coord']['lon'];
            }
        }

        $weatherCollection = new \PhpWeather\Common\WeatherCollection();

        $now = time();
        foreach ($rawData['list'] as $weatherRawDataItem) {
            $isForecast = $weatherRawDataItem['dt'] > $now;
            $weatherCollection->add(
                $this->mapItemRawdata(
                    $latitude,
                    $longitude,
                    $weatherRawDataItem,
                    $isForecast ? Type::FORECAST : Type::HISTORICAL,
                    $units
                )
            );
        }

        return $weatherCollection;
    }

    /**
     * @param  float  $latitude
     * @param  float  $longitude
     * @param  array<string, mixed>  $rawData
     * @param  string|null  $type
     * @param  string|null  $units
     * @return Weather
     */
    private function mapItemRawdata(float $latitude, float $longitude, array $rawData, ?string $type, ?string $units): Weather
    {
        if ($units === null) {
            $units = Unit::METRIC;
        }

        if (array_key_exists('coord', $rawData) && is_array($rawData['coord'])) {
            if (array_key_exists('lon', $rawData['coord'])) {
                $longitude = $rawData['coord']['lon'];
            }
            if (array_key_exists('lat', $rawData['coord'])) {
                $latitude = $rawData['coord']['lat'];
            }
        }
        $weather = (new \PhpWeather\Common\Weather())
            ->setLatitude($latitude)
            ->setLongitude($longitude)
            ->setType($type);
        foreach ($this->getSources() as $source) {
            $weather->addSource($source);
        }

        $utcDateTime = (new DateTime())->setTimezone(new DateTimeZone('UTC'));
        if (array_key_exists('dt', $rawData)) {
            $utcDateTime->setTimestamp($rawData['dt']);
        }
        $weather->setUtcDateTime($utcDateTime);

        if (array_key_exists('main', $rawData) && is_array($rawData['main'])) {
            if (array_key_exists('temp', $rawData['main'])) {
                $weather->setTemperature(UnitConverter::mapTemperature($rawData['main']['temp'], Unit::TEMPERATURE_CELSIUS, $units));
            }
            if (array_key_exists('feels_like', $rawData['main'])) {
                $weather->setFeelsLike(UnitConverter::mapTemperature($rawData['main']['feels_like'], Unit::TEMPERATURE_CELSIUS, $units));
            }
            if (array_key_exists('pressure', $rawData['main'])) {
                $weather->setPressure(UnitConverter::mapPressure($rawData['main']['pressure'], Unit::PRESSURE_HPA, $units));
            }
            if (array_key_exists('humidity', $rawData['main'])) {
                $weather->setHumidity($rawData['main']['humidity']);
            }
        }

        if (array_key_exists('wind', $rawData) && is_array($rawData['wind'])) {
            if (array_key_exists('speed', $rawData['wind'])) {
                $weather->setWindSpeed(UnitConverter::mapSpeed($rawData['wind']['speed'], Unit::SPEED_MS, $units));
            }
            if (array_key_exists('deg', $rawData['wind'])) {
                $weather->setWindDirection($rawData['wind']['deg']);
            }
        }

        if (
            array_key_exists('clouds', $rawData) &&
            is_array($rawData['clouds']) &&
            array_key_exists('all', $rawData['clouds'])
        ) {
            $weather->setCloudCover($rawData['clouds']['all'] / 100);
        }

        if (array_key_exists('pop', $rawData)) {
            $weather->setPrecipitationProbability($rawData['pop']);
        }

        if (
            array_key_exists('weather', $rawData) &&
            is_array($rawData['weather']) &&
            count($rawData['weather']) > 0 &&
            is_array($rawData['weather'][0]) &&
            array_key_exists('id', $rawData['weather'][0])) {
            $weather->setWeathercode($this->mapWeatherCode((int)$rawData['weather'][0]['id']));
            $weather->setIcon($this->mapIcon((int)$rawData['weather'][0]['id'], $utcDateTime, $latitude, $longitude));
        }

        return $weather;
    }

    public function getSources(): array
    {
        if ($this->sources === null) {
            $this->sources = [
                new Source(
                    'openweathermap',
                    'OpenWeatherMap',
                    'https://openweathermap.org/'
                ),
            ];
        }

        return $this->sources;
    }

    private function mapWeatherCode(int $id): int
    {
        return match ($id) {
            200, 210, 221, 230 => 95,
            201, 211, 231 => 96,
            202, 212, 232 => 99,
            300, 310 => 51,
            301, 311 => 53,
            302, 312, 313, 314, 321 => 55,
            500 => 61,
            501 => 63,
            502, 503, 504 => 65,
            511 => 66,
            520 => 80,
            521 => 81,
            522, 531 => 82,
            600 => 71,
            601 => 73,
            602 => 75,
            611, 612, 613, 615, 616, 620 => 85,
            621, 622 => 86,
            701, 711, 721, 731, 741, 751, 761, 762, 771, 781 => 45,
            801, 802 => 1,
            803 => 2,
            804 => 3,
            default => 0,
        };
    }

    private function mapIcon(int $id, DateTime $weatherDateTime, float $latitude, float $longitude): ?string
    {
        $dateSunInfo = date_sun_info($weatherDateTime->getTimestamp(), $latitude, $longitude);
        $isNight = $weatherDateTime->getTimestamp() < $dateSunInfo['sunrise'] || $weatherDateTime->getTimestamp() > $dateSunInfo['sunset'];

        if ($isNight) {
            return match ($id) {
                200, 232, 231, 230, 202, 201 => 'night-alt-thunderstorm',
                210, 221, 212, 211 => 'night-alt-lightning',
                300, 500, 321, 301 => 'night-alt-sprinkle',
                302, 504, 503, 502, 501, 314, 313, 312, 311, 310 => 'night-alt-rain',
                511, 620, 616, 615, 612, 611 => 'night-alt-rain-mix',
                520, 701, 522, 521 => 'night-alt-showers',
                531 => 'night-alt-storm-showers',
                600, 622, 621, 602 => 'night-alt-snow',
                601 => 'night-alt-sleet',
                711 => 'smoke',
                721 => 'day-haze',
                731, 762, 761 => 'dust',
                741 => 'night-fog',
                781, 900 => 'tornado',
                800 => 'night-clear',
                801, 803, 802 => 'night-alt-cloudy-gusts',
                804 => 'night-alt-cloudy',
                902 => 'hurricane',
                903 => 'snowflake-cold',
                904 => 'hot',
                906 => 'night-alt-hail',
                957 => 'strong-wind',
                default => null
            };
        }

        return match ($id) {
            200, 232, 231, 230, 202, 201 => 'day-thunderstorm',
            210, 221, 212, 211 => 'day-lightning',
            300, 500, 321, 301 => 'day-sprinkle',
            302, 504, 503, 502, 501, 314, 313, 312, 311, 310 => 'day-rain',
            511, 620, 616, 615, 612, 611 => 'day-rain-mix',
            520, 701, 522, 521 => 'day-showers',
            531 => 'day-storm-showers',
            600, 622, 621, 602 => 'day-snow',
            601 => 'day-sleet',
            711 => 'smoke',
            721 => 'day-haze',
            731, 762, 761 => 'dust',
            741 => 'day-fog',
            781, 900 => 'tornado',
            800 => 'day-sunny',
            801, 803, 802 => 'day-cloudy-gusts',
            804 => 'day-sunny-overcast',
            902 => 'hurricane',
            903 => 'snowflake-cold',
            904 => 'hot',
            906 => 'day-hail',
            957 => 'strong-wind',
            default => null
        };
    }

}