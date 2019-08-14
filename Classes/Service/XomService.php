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
	const CACHE_PREFIX_REQUEST = 'request';

	protected $configuration;
	protected $cache;
	protected $requestFactory;

	public function __construct()
    {
		$this->configuration = unserialize($GLOBALS['TYPO3_CONF_VARS']['EXT']['extConf']['xom']);

		$this->cache = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Cache\CacheManager::class)->getCache('xom');
		$this->requestFactory = \TYPO3\CMS\Core\Utility\GeneralUtility::makeInstance(\TYPO3\CMS\Core\Http\RequestFactory::class);
    }

	public function setConfiguration($configuration)
	{
		$this->configuration = $configuration;
	}

	protected function apiCall($method, $identifier = NULL, $params = [], $localized = TRUE, $cached = TRUE)
	{
        if ( ! $params['format']) $params['format'] = 'object';
		if ($localized) $params['locale'] = $this->getLocale();

		if ($cached) {
			$cacheIdentifier = $this->calculateCacheIdentifier([$method, $identifier, $params], self::CACHE_PREFIX_REQUEST, TRUE);

			if ($this->cache->get($cacheIdentifier)) {
	            return $this->cache->get($cacheIdentifier);
	        }
		}

        $response = $this->rawApiCall($method, $identifier, $params);

        if ($response) {
            $response = json_decode($response, TRUE);

			if ($cached) {
				$this->cache->set($cacheIdentifier, $response, ['xom'], self::CACHE_LIFETIME_GENERAL);
			}

			return $response;
        }

        return FALSE;
	}

    protected function rawApiCall($method, $identifier = NULL, $params = [])
    {
        if (isset($identifier)) {
            $identifier = rawurlencode($identifier);
            $method = str_replace('{id}', $identifier, $method);
        } else {
			// method contains {id}, but identifier is not set
			if (strpos($method, '{id}') !== FALSE) {
				return FALSE;
			}
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
			echo $e->getMessage();
        }

        return FALSE;
    }

    protected function getToken()
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
			echo $e->getMessage();
        }

        return FALSE;
    }

	protected function getLocale()
	{
		if ($GLOBALS['TYPO3_REQUEST']->getAttribute('siteLanguage')) {
			return $GLOBALS['TYPO3_REQUEST']->getAttribute('siteLanguage')->getTwoLetterIsoCode();
		}

		return $GLOBALS['TYPO3_REQUEST']->getAttribute('language')->getTwoLetterIsoCode();
	}

	protected function calculateCacheIdentifier($data, $prefix = NULL, $hash = FALSE) {
		if (is_array($data)) {
			$data = json_encode($data);
		}
		if ($hash) {
			$data = sha1($data);
		}
		if ($prefix) {
			$data = $prefix.'-'.$data;
		}
		return $data;
	}
}
