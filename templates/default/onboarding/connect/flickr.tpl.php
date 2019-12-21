<?php

if ($flickr = \Idno\Core\Idno::site()->plugins()->get('Flickr')) {
    if (empty(\Idno\Core\Idno::site()->session()->currentUser()->flickr)) {
        $login_url = \Idno\Core\Idno::site()->config()->getURL() . 'flickr/callback';
    } else {
        $login_url = \Idno\Core\Idno::site()->config()->getURL() . 'flickr/deauth';
    }
}

?>
<div class="social">
    <a href="<?php echo $login_url?>" class="connect fl <?php

    if (!empty(\Idno\Core\Idno::site()->session()->currentUser()->flickr)) {
        echo 'connected';
    }

    ?>" target="_top">Flickr<?php

if (!empty(\Idno\Core\Idno::site()->session()->currentUser()->flickr)) {
    echo ' - connected!';
}

?></a>
    <label class="control-label"><?php echo \Idno\Core\Idno::site()->language()->_('Share pictures to Flickr.'); ?></label>
</div>
