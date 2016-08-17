<?php

require_once 'VootTokens.class.php';

use fkooman\OAuth\Client\Provider;
use fkooman\OAuth\Client\OAuth2Client;
use fkooman\OAuth\Client\GuzzleHttpClient;
use GuzzleHttp\Client;

class VootGroups extends Groups
{
    /** @var string */
    private $apiUrl;

    /** @var string */
    private $userId;

    /** @var VootTokens */
    private $db;

    public function __construct($config, $auth = null)
    {
        parent::__construct($config, $auth);

        $this->apiUrl = getConfig($config, 'voot_api_endpoint', true);
        $this->userId = $auth->getUserId();
        $this->db = new VootTokens(
            new PDO('sqlite:'.dirname(dirname(__DIR__)).DIRECTORY_SEPARATOR.'data'.DIRECTORY_SEPARATOR.'client.sqlite')
        );

        if (false === $this->db->get($this->userId)) {
            // no access_token yet
            $provider = new Provider(
                getConfig($config, 'voot_client_id', true),
                getConfig($config, 'voot_client_secret', true),
                getConfig($config, 'voot_authorize_uri', true),
                getConfig($config, 'voot_token_uri', true)
            );

            $oauthClient = new OAuth2Client(
                $provider,
                new GuzzleHttpClient()
            );

            if (isset($_GET['code']) && isset($_GET['state'])) {
                // it is a callback, verify the request and obtain access_token
                $accessToken = $oauthClient->getAccessToken(
                    $_SESSION['oauth2_session'],
                    $_GET['code'],
                    $_GET['state']
                );

                // unset as to not allow additional redirects to the same URI to attempt to
                // get another access token with this code
                unset($_SESSION['oauth2_session']);

                // store the access_token
                $this->db->store($this->userId, $accessToken->getToken());
            } else {
                // no callback, request authorization
                $redirectUri = getConfig($config, 'voot_redirect_uri', true);

                $authorizationRequestUri = $oauthClient->getAuthorizationRequestUri(
                    'groups',
                    $redirectUri
                );

                $_SESSION['oauth2_session'] = $authorizationRequestUri;
                header(sprintf('Location: %s', $authorizationRequestUri));
                exit(0);
            }
        }
    }

    public function getUserGroups()
    {
        $accessToken = $this->db->get($this->userId);

        $httpClient = new Client();
        try {
            $response = $httpClient->get(
                $this->apiUrl,
                [
                    'headers' => [sprintf('Authorization: Bearer %s', $accessToken)],
                ]
            )->json();
        } catch (ClientResponseException $e) {
            $this->db->delete($this->userId);
            die('whoa, unable to retrieve groups, please refresh');
        }

        $groups = [];
        foreach ($response as $entry) {
            $groups[$entry['id']] = $entry['displayName'];
        }

        return $groups;
    }

    public function addActivity($title = null, $body = null, $groupId = null)
    {
        // NOP
    }
}
