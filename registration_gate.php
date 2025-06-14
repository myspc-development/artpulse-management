<?php
use EAD\Shortcodes\ArtistRegistrationForm;
use EAD\Shortcodes\OrganizationRegistrationForm;

add_action( 'init', function () {
    add_shortcode( 'ap_artist_registration_form', function () {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in.</p>';
        }
        if ( get_user_meta( get_current_user_id(), 'membership_level', true ) !== 'pro' ) {
            return '<p>You must be a Pro Artist member to apply.</p>';
        }
        return ArtistRegistrationForm::render_form();
    } );

    add_shortcode( 'ead_organization_registration_form', function () {
        if ( ! is_user_logged_in() ) {
            return '<p>Please log in.</p>';
        }
        if ( get_user_meta( get_current_user_id(), 'membership_level', true ) !== 'org' ) {
            return '<p>Only Organization members may submit this form.</p>';
        }
        return OrganizationRegistrationForm::render_form();
    } );
} );
