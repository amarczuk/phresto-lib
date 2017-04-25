<?php

namespace Phresto\Modules;

use Phresto\Utils;
use OAuth\ServiceFactory;
use OAuth\OAuth2\Service\Facebook;
use OAuth\Common\Storage\Session;
use OAuth\Common\Consumer\Credentials;

class FBApi {

    // Session storage
    protected $storage;
    protected $credentials;
    protected $token;
    protected $fbService;

    public function __construct( $key, $secret ) {
        $url = $_SERVER["HTTP_HOST"] . '/user/facebook';
        if ( !empty( $_SERVER["HTTPS"] ) && $_SERVER["HTTPS"] == 'on' ) {
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
        $this->fbService = $serviceFactory->createService( 'facebook', $this->credentials, $this->storage, [Facebook::SCOPE_PUBLIC_PROFILE, Facebook::SCOPE_EMAIL] );
    }

    public function getUserDetails() {

        if ( !empty( $_GET['error'] ) ) {
            throw new \Exception( $_GET['error'] );
        }

        if ( !empty($_GET['code']) ) {
            // retrieve the CSRF state parameter
            $state = isset( $_GET['state'] ) ? $_GET['state'] : null;
            $this->fbService->requestAccessToken( $_GET['code'], $state );
            $user = [];
            $result1 = json_decode( $this->fbService->request('/me'), true );

            $user['email'] = $result1['email'];
            $user['name'] = $result1['name'];
            return $user;

        } else {
            Utils::Redirect( $this->fbService->getAuthorizationUri() );
        }
    }
}