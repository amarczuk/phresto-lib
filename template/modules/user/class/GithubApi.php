<?php

namespace Phresto\Modules;

use Phresto\Utils;

use OAuth\ServiceFactory;
use OAuth\OAuth2\Service\GitHub;
use OAuth\Common\Storage\Session;
use OAuth\Common\Consumer\Credentials;;

class GithubApi {

    // Session storage
    protected $storage;
    protected $credentials;
    protected $token;
    protected $githubService;

    public function __construct( $key, $secret ) {
        $url = $_SERVER["HTTP_HOST"] . '/user/auth/github';
        if (  !empty( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == 'on' ) {
            $url = 'https://' . $url;
        } else {
            $url = 'http://' . $url;
        }

        $this->storage = new Session();
        $this->credentials = new Credentials(
            $key,
            $secret,
            $url
        );

        $serviceFactory = new ServiceFactory();
        $this->githubService = $serviceFactory->createService( 'GitHub', $this->credentials, $this->storage, [ 'user:email' ] );
    }

    public function getUserDetails() {

        if ( !empty( $_GET['error'] ) ) {
            throw new \Exception( $_GET['error'] );
        }

        if ( !empty($_GET['code']) ) {
            // retrieve the CSRF state parameter
            $state = isset( $_GET['state'] ) ? $_GET['state'] : null;
            $this->githubService->requestAccessToken( $_GET['code'], $state );
            $user = [];
            $result1 = json_decode( $this->githubService->request( 'user' ), true );
            $result2 = json_decode( $this->githubService->request( 'user/emails' ), true );
            $user['email'] = $result2[0];
            $user['given_name'] = $result1['login'];
            $user['name'] = $result1['name'];
            return $user;

        } else {
            Utils::Redirect( $this->githubService->getAuthorizationUri() );
        }
    }
}