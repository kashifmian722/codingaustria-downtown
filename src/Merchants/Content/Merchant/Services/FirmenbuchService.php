<?php

declare(strict_types=1);

namespace Shopware\Production\Merchants\Content\Merchant\Services;

use GuzzleHttp\ClientInterface;
use Psr\Http\Message\ResponseInterface;

class FirmenbuchService
{
    private const BEARER_TOKEN = 'Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJzdWIiOiJvY2EtOTk5MTAwIiwiaXNzIjoiVVZTVCBEYXRlbmRpZW5zdGUgR21iSCIsImV4cCI6MTY1OTI3NzAyMywiaWF0IjoxNjU5MjMzODIzfQ.nd96QsPVIkVI0uHHQaQs5RQT5KXwIMhjBXWO-fN4OXk';
    private const X_API_TOKEN = 'ACH-T27RCD94UP5RHMAPT27HMCNUTDCUPCPQ5QN5CNE5R4CHZ27DQDSV7VZYS7W3FAX4';
    private const URL = 'https://sws-test.verrechnungsstelle.at/api/v1/fb/auszug';

    /**
     * @var ClientInterface
     */
    private $httpClient;

    public function __construct(ClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;
    }

    public function getFirmenbuchEintrag(string $firmenbuchNummer, string $firmenName): string
    {
        $body = [
            'fnr' => $firmenbuchNummer,
            'stichtag' => '31.07.2022',
            'umfang' => 'aktuell',
            'uvstInfo' => [
                'softwareName' => 'string',
                'softwareVersion' => 'string',
                'usewareKosten' => 0,
                'usewareProdukt' => 'string',
                'betriebssystem' => 'string',
                'geraeteName' => 'string',
                'weitereInfo' => 'string',
            ],
        ];

        /** @var ResponseInterface $response */
        $response = $this->httpClient->post(self::URL, [
            'headers' => [
                'Content-Type' => 'application/json',
                'X-API-KEY' => self::X_API_TOKEN,
                'Authorization' => self::BEARER_TOKEN,
            ],
            'json' => $body,
        ]);

        $jsonResponse = json_decode($response->getBody()->getContents(), true);

        $soapResponse = base64_decode($jsonResponse['response']);

        $xml = simplexml_load_string($soapResponse);

        $companyNameElement = $xml->xpath('/ns13:AUSZUG_V1_RESPONSE/ns13:FIRMA/ns13:FI_DKZ02/ns13:BEZEICHNUNG');

        return (string) $companyNameElement[0];
    }
}
