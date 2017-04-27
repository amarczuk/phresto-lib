<?php

namespace Phresto\Modules;

use Phresto\Utils;

use OAuth\ServiceFactory;
use OAuth\OAuth2\Service\Linkedin;
use OAuth\Common\Storage\Session;
use OAuth\Common\Consumer\Credentials;;

class LinkedinApi {

    // Session storage
    protected $storage;
    protected $credentials;
    protected $token;
    protected $aService;

    public function __construct( $key, $secret ) {
        $url = $_SERVER["HTTP_HOST"] . '/user/auth/linkedin';
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
        $this->aService = $serviceFactory->createService( 'linkedin', $this->credentials, $this->storage, [ 'r_basicprofile', 'r_emailaddress' ] );
    }

    public function getUserDetails() {

        if ( !empty( $_GET['error'] ) ) {
            throw new \Exception( $_GET['error'] );
        }

        if ( !empty($_GET['code']) ) {
            // retrieve the CSRF state parameter
            $state = isset( $_GET['state'] ) ? $_GET['state'] : null;
            $this->aService->requestAccessToken( $_GET['code'], $state );
            $user = [];
            $result = json_decode( $this->aService->request( '/people/~:(first-name,last-name,picture-urls::(original),email-address)?format=json' ), true );
            $user['email'] = $result['emailAddress'];
            $user['name'] = $result['firstName'] . ' ' . $result['lastName'];
            return $user;

        } else {
            Utils::Redirect( $this->aService->getAuthorizationUri() );
        }
    }
}