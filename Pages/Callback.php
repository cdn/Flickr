<?php

    /**
     * Flickr pages
     *
     * @category Plug-in
     * @package  Photo_POSSE
     * @author   Known, Inc <hello@withknown.com>
     * @license  https://github.com/idno/Known/blob/master/LICENSE Apache
     * @link     https://github.com/Idno/Flickr
     */

namespace IdnoPlugins\Flickr\Pages {

        /**
         * Default class to serve the Flickr callback
         *
         * @category Class
         * @package  Flickr
         * @author   @cdn <cn@domain.tld>
         * @license  https://github.com/idno/Flickr/blob/master/LICENCE Above
         * @link     https://github.com/cdn
         */
    class Callback extends \Idno\Common\Page
    {

        /**
         * HTTP GET Action
         *
         * @return Of_The_Jedi
         */
        function getContent()
        {
                $this->gatekeeper(); // Logged-in users only
            if ($flickr = \Idno\Core\Idno::site()->plugins()->get('Flickr')) {

                    $config = \Idno\Core\Idno::site()->config();

                    $api_key = $config->flickr['apiKey'];
                    $api_secret = $config->flickr['secret'];

                    $callback = $config->getURL() . 'flickr/callback';

                    include_once dirname(__FILE__) . '/../external/DPZ/Flickr.php';
                    $flickr = new \DPZ\Flickr($api_key, $api_secret, $callback);

                if (empty($_SESSION['faccess_oauth_token'])) {

                    if (!$flickr->authenticate('write')) {
                        die('Laughing');
                    }

                        // $userNsid = $flickr->getOauthData(\DPZ\Flickr::USER_NSID);
                        $userName = $flickr->getOauthData('user_name');

                      $result['fullname'] = $flickr->getOauthData('user_full_name');
                      $result['username'] = $userName;

                      $secret = 'oauth_access_token_secret';
                     $result['token'] = $flickr->getOauthData('oauth_access_token');
                    $result['secret'] = $flickr->getOauthData($secret);
                }

                if (!empty($result['token'])) {
                     $user = \Idno\Core\Idno::site()->session()->currentUser();
                     $user->flickr[$result['username']] = array(
                    'access_token' => $result['token'],
                    'secret' => $result['secret'],
                    'username' => $result['fullname']
                    );
                     $user->save();
                     $message = 'Flickr user '.$result['username'].' authenticated.';
                     \Idno\Core\Idno::site()->logging()->log($message);
                }

            }
            if (!empty($_SESSION['onboarding_passthrough'])) {
                unset($_SESSION['onboarding_passthrough']);
                $this->forward($config->getURL() . 'begin/connect-forwarder');
            }
                $this->forward($config->getDisplayURL() . 'account/flickr/');
        }

    }

}
