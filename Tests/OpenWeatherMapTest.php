<?php
declare(strict_types=1);

namespace PhpWeather\Provider\OpenWeatherMap;

use DateTime;
use DateTimeZone;
use PHPUnit\Framework\TestCase;
use Http\Client\HttpClient;
use PHPUnit\Framework\MockObject\MockObject;
use PhpWeather\Common\WeatherQuery;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

class OpenWeatherMapTest extends TestCase
{
    private MockObject|HttpClient $client;
    private MockObject|RequestFactoryInterface $requestFactory;
    private OpenWeatherMap $provider;

    private string $key = 'key';

    public function setUp(): void
    {
        $this->client = $this->createMock(HttpClient::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);

        $this->provider = new OpenWeatherMap($this->client, $this->key, $this->requestFactory);
    }

    public function testCurrentWeather(): void
    {
        $latitude = 47.8739259;
        $longitude = 8.0043961;
        $datetime = (new DateTime())->setTimezone(new DateTimeZone('UTC'))->setDate(2022, 07, 31)->setTime(16, 00);
        $testQuery = WeatherQuery::create($latitude, $longitude, $datetime);
        $testString = 'https://api.openweathermap.org/data/2.5/weather?lat=47.8739259&lon=8.0043961&appid=key&units=metric';

        $request = $this->createMock(RequestInterface::class);
        $this->requestFactory->expects(self::once())->method('createRequest')->with('GET', $testString)->willReturn($request);

        $responseBodyString = file_get_contents(__DIR__.'/resources/currentWeather.json');
        $response = $this->createMock(ResponseInterface::class);
        $response->expects(self::once())->method('getBody')->willReturn($responseBodyString);
        $this->client->expects(self::once())->method('sendRequest')->with($request)->willReturn($response);

        $currentWeather = $this->provider->getCurrentWeather($testQuery);
        self::assertSame(20.03, $currentWeather->getTemperature());
        self::assertSame('night-clear', $currentWeather->getIcon());
        self::assertCount(1, $currentWeather->getSources());
    }

}