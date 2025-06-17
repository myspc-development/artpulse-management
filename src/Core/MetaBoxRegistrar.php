<?php
namespace ArtPulse\Core;

use ArtPulse\Admin\MetaBoxesArtist;
use ArtPulse\Admin\MetaBoxesArtwork;
use ArtPulse\Admin\MetaBoxesEvent;
use ArtPulse\Admin\MetaBoxesOrganisation;
use ArtPulse\Admin\MetaBoxesAddress;

class MetaBoxRegistrar {
    public static function register() {
        MetaBoxesArtist::register();
        MetaBoxesArtwork::register();
        MetaBoxesEvent::register();
        MetaBoxesOrganisation::register();
        MetaBoxesAddress::register(['artpulse_event', 'artpulse_org']);
    }
}
