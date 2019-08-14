<?php
namespace Medienreaktor\Xom\Service;

/**
 * This file is part of the "xom" extension for TYPO3 CMS
 *
 * Copyright © 2019 medienreaktor GmbH
 */

class XomService implements \TYPO3\CMS\Core\SingletonInterface
{
	const CACHE_LIFETIME_TOKEN = 3600; // 1 hour
	const CACHE_LIFETIME_GENERAL = 86400; // 1 day
	const CACHE_IDENTIFIER_TOKEN = 'token';

	protected $configuration;
	protected $cache;
	protected $requestFactory;

	public function __construct(array $configuration)
    {
		$this->configuration = $configuration;
		$this->cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('xom_assets');
		$this->requestFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Http\RequestFactory::class);
    }

	protected function apiCall($method, $identifier = NULL, $params = [])
	{
        if ( ! $params['format']) $params['format'] = 'property';

        $response = $this->rawApiCall($method, $identifier, $params);

        if ($response) {
            return json_decode($response, TRUE);
        }

        return FALSE;
	}

    protected function rawApiCall($method, $identifier = NULL, $params = [])
    {
        if (isset($identifier)) {
            $identifier = rawurlencode($identifier);
            $method = str_replace('{id}', $identifier, $method);
        }

        try {
            $params['access_token'] = $this->getToken();
            $uri = $this->configuration['apiEndpoint'].$method.'?'.http_build_query($params);

            $response = $this->requestFactory->request($uri, 'GET');

            if (200 === $response->getStatusCode()) {
                $contents = $response->getBody()->getContents();
                return $contents;
            }
        } catch (\Exception $e) {

        }

        return FALSE;
    }

    private function getToken()
    {
        if ($this->cache->get(self::CACHE_IDENTIFIER_TOKEN)) {
            return $this->cache->get(self::CACHE_IDENTIFIER_TOKEN);
        }
        try {
            $clientId = $this->configuration['apiClientId'];
            $clientSecret = $this->configuration['apiClientSecret'];
            $oauthEndpoint = $this->configuration['apiOAuthEndpoint'];

            $options = [
                'headers' => [
                    'Authorization' => 'Basic '.base64_encode($clientId.':'.$clientSecret),
                    'Accept' => 'application/json',
                    'Content-Type' => 'application/x-www-form-urlencoded'
                ]
            ];

            $response = $this->requestFactory->request($oauthEndpoint.'?grant_type=client_credentials', 'POST', $options);

            if (200 === $response->getStatusCode()) {
                $contents = json_decode($response->getBody()->getContents(), TRUE);
                $token = $contents['access_token'];

                $this->cache->set(self::CACHE_IDENTIFIER_TOKEN, $token, ['xom'], self::CACHE_LIFETIME_TOKEN);
                return $token;
            }
        } catch (\Exception $e) {

        }

        return FALSE;
    }
}
