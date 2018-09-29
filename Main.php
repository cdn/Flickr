<?php

    /**
     * Flickr pages
     *
     * PHP version 5.6
     *
     * @category Plug-in
     * @package  Photo_POSSE
     * @author   Known, Inc <hello@withknown.com>
     * @license  https://github.com/idno/Known/blob/master/LICENSE Apache
     * @link     https://github.com/Idno/Flickr
     */

namespace IdnoPlugins\Flickr {

    /**
     * Default class to serve Flickr-related account actions
     *
     * @category Class
     * @package  Flickr
     * @author   @cdn <cn@domain.tld>
     * @license  https://github.com/idno/Flickr/blob/master/LICENCE Above
     * @link     https://github.com/cdn
     */
    class Main extends \Idno\Common\Plugin
    {

        /**
         * Setup page handling
         *
         * @return not applicable
         */
        function registerPages()
        {
//                \Idno\Core\Idno::site()->addPageHandler('admin/webhooks/?', 'IdnoPlugins\Webhooks\Pages\Admin');
            // Deauth URL
                \Idno\Core\site()->addPageHandler(
                    'flickr/deauth', '\IdnoPlugins\Flickr\Pages\Deauth', true
                );
            // Register the callback URL
                \Idno\Core\site()->addPageHandler(
                    'flickr/callback', '\IdnoPlugins\Flickr\Pages\Callback', true
                );
            // Register admin settings
                \Idno\Core\site()->addPageHandler(
                    'admin/flickr', '\IdnoPlugins\Flickr\Pages\Admin'
                );
            // Register settings page
                \Idno\Core\site()->addPageHandler(
                    'account/flickr', '\IdnoPlugins\Flickr\Pages\Account'
                );

            /**
              * Template extensions
              */

            // Add menu items to account & administration screens
                \Idno\Core\site()->template()->extendTemplate(
                    'admin/menu/items', 'admin/flickr/menu'
                );
                \Idno\Core\site()->template()->extendTemplate(
                    'account/menu/items', 'account/flickr/menu'
                );
                \Idno\Core\site()->template()->extendTemplate(
                    'onboarding/connect/networks', 'onboarding/connect/flickr'
                );
        }

        /**
         * Setup actions
         *
         * @return via another function
         */
        function registerEventHooks()
        {

            \Idno\Core\site()->syndication()->registerService(
                'flickr', function () {
                        return $this->hasFlickr();
                }, array('image')
            );

            \Idno\Core\site()->addEventHook(
                'user/auth/success', function (\Idno\Core\Event $event) {
                    if ($this->hasFlickr()) {
                        $site = \Idno\Core\site();
                        $currentUser = $site->session()->currentUser();
                        if (is_array($currentUser->flickr)) {
                            foreach ($currentUser->flickr as $username => $details) {
                                if (!in_array($username, ['access_token','username'])
                                ) {
                                    $site->syndication()->registerServiceAccount(
                                        'flickr', $username, $details['username']
                                    );
                                }
                            }
                            if (!empty($currentUser->flickr['username'])) {
                                $site->syndication()->registerServiceAccount(
                                    'flickr', $currentUser->flickr['username'],
                                    $currentUser->flickr['username']
                                );
                            }
                        }
                    }
                }
            );

            // Push "images" to Flickr
            \Idno\Core\site()->addEventHook(
                'post/image/flickr', function (\Idno\Core\Event $event) {
                        $eventdata = $event->data();
                        $object = $eventdata['object'];
                    if ($attachments = $object->getAttachments()) {
                        foreach ($attachments as $attachment) {
                            if ($this->hasFlickr()) {
                                $site = \Idno\Core\site();
                                $currentUser = $site->session()->currentUser();
                                if (!empty($eventdata['syndication_account'])) {
                                    $flickrAPI  = $this->connect(
                                        $eventdata['syndication_account']
                                    );
                                    $evtd_sa = $eventdata['syndication_account'];
                                    $user_details = $currentUser->flickr[$evtd_sa];
                                } else {
                                    $flickrAPI  = $this->connect();
                                    $user_details = $currentUser->flickr;
                                }

                                if (!empty($user_details['username'])) {
                                    $name = $user_details['username'];
                                } else {
                                    $name = 'Flickr';
                                }

                                if (!$flickrAPI) {
                                    error_log('Failed to connect to Flickr API');
                                } else if (empty($user_details)) {
                                    error_log('Failed to get user_details');
                                } else {
                                    // Get string of non-hashtagged tags
                                    $tags = str_replace(
                                        '#', '', implode(' ', $object->getTags())
                                    );
                                    try {

                                        $title = html_entity_decode(
                                            $object->getTitle()
                                        );
                                        $photo = $attachment['url'];
                                        $perms = array("is_public"=>1);
                                        $description = html_entity_decode(
                                            $object->getDescription()
                                        ) . "\n\nOriginal: " . $object->getURL();

                                        if ($bytes = \Idno\Entities\File::getFileDataFromAttachment(
                                            $attachment
                                        )
                                        ) {
                                            $media = array();
                                            $filename = tempnam(
                                                sys_get_temp_dir(), 'idnoflickr'
                                            );
                                            file_put_contents($filename, $bytes);

                                            if (version_compare(
                                                phpversion(), '5.5', '>='
                                            )
                                            ) {
                                                $params['photo'] = new \CURLFile(
                                                    $filename
                                                );
                                            } else {
                                                $params['photo'] = '@'.$filename;
                                            }

                                            $info = filesize($params['photo']);
                                            if ($title) {
                                                $params['title']       = $title;
                                            }
                                            if ($description) {
                                                $params['description'] = $description;
                                            }
                                            if ($tags) {
                                                // Space-separated string
                                                $params['tags']        = $tags;
                                            }
                                            if ($perms) {
                                                if (isset($perms['is_public'])) {
                                                    $params['is_public'] = $perms['is_public'];
                                                }
                                                if (isset($perms['is_friend'])) {
                                                    $params['is_friend'] = $perms['is_friend'];
                                                }
                                                if (isset($perms['is_family'])) {
                                                    $params['is_family'] = $perms['is_family'];
                                                }
                                            }

                                            if ($async) {
                                                $params['async']       = $async;
                                            }

                                            $photo_id = $flickrAPI->upload($params);

                                            $ok = @$photo_id['stat'];

                                            if ($ok == 'ok') {
                                                $photo = $flickrAPI->call(
                                                    'flickr.photos.getInfo',
                                                    array('photo_id' => $photo_id['photoid']['_content'])
                                                );

                                                if ($photo['photo']['urls']['url'][0]['type'] == 'photopage') {
                                                    $object->setPosseLink(
                                                        'flickr',
                                                        $photo['photo']['urls']['url'][0]['_content'],
                                                        $name, $photo_id['photoid']['_content'],
                                                        $photo['photo']['owner']['username']
                                                    );
                                                    $object->save();
                                                }
                                                \Idno\Core\site()->logging()->log(
                                                    $photo_id['photoid']['_content'] .
                                                    ' pushed to Flickr.'
                                                );
                                            } else {
                                                $code = $flickrAPI->getErrorCode();
                                                $msg  = $flickrAPI->getErrorMessage();
                                                error_log(
                                                    "Failed to upload image to " .
                                                    "Flickr. code={$code}, error={$msg}"
                                                );
                                            }

                                        }

                                    }
                                    catch (\Exception $e) { // General Exception
                                        error_log(
                                            'Could not post image to Flickr: ' . $e->getMessage()
                                        );
                                    }
                                }
                            }
                        }
                    }
                }
            );
        }

        /**
         * Return the URL required to authenticate with the API
         *
         * @return string
         */
        function getAuthURL()
        {

            $login_url = \Idno\Core\site()->config()->getURL() .
            'flickr/callback';
            return $login_url;

        }

        /**
         * Connect to Flickr
         *
         * @param Username $username of Known/Flickr user
         *
         * @return bool|\Flickr
         */
        function connect($username = false)
        {
            if (!empty(\Idno\Core\site()->config()->flickr)) {
                include_once dirname(__FILE__) . '/external/DPZ/Flickr.php';
                $flickr = new \DPZ\Flickr(
                    \Idno\Core\site()->config()->flickr['apiKey'],
                    \Idno\Core\site()->config()->flickr['secret']
                );
                if ($this->hasFlickr()) {
                    $currentUser = \Idno\Core\site()->session()->currentUser();
                    if (empty($username)) {
                        if (!empty(\Idno\Core\site()->session()->currentUser()->flickr['access_token'])) {
                            // https://github.com/dopiaza/DPZFlickr/pull/8 https://github.com/lucasgd/DPZFlickr

                            $flickr->isValidOauthToken(
                                \Idno\Core\site()->session()->currentUser()->flickr['access_token'],
                                \Idno\Core\site()->session()->currentUser()->flickr['secret']
                            );
                        }
                    } else {
                        if (!empty(\Idno\Core\site()->session()->currentUser()->flickr[$username])
                        ) {
                            $flickr->isValidOauthToken(
                                \Idno\Core\site()->session()->currentUser()->flickr[$username]['access_token'],
                                \Idno\Core\site()->session()->currentUser()->flickr[$username]['secret']
                            );
                        } else if (!empty($currentUser->flickr['username']) 
                            && $username == $currentUser->flickr['username']
                        ) {
                            $flickr->isValidOauthToken(
                                \Idno\Core\site()->session()->currentUser()->flickr['access_token'],
                                \Idno\Core\site()->session()->currentUser()->flickr['secret']
                            );
                        }
                    }
                }

                return $flickr;
            }
            return false;
        }

        /**
         * Can the current user use Flickr?
         *
         * @return bool
         */
        function hasFlickr()
        {
            if (!\Idno\Core\site()->session()->currentUser()) {
                return false;
            }
            if (\Idno\Core\site()->session()->currentUser()->flickr) {
                return true;
            }
            return false;
        }

    }

}
