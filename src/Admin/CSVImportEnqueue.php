<?php
namespace EAD\Admin;

class CSVImportEnqueue {
    public static function register() {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue($hook) {
        // Only load scripts on the CSV Import/Export screen
        if ( ! isset( $_GET['page'] ) ) {
            return;
        }
        $is_csv_page = $_GET['page'] === 'artpulse-csv-import-export';
        $is_settings_tab = $_GET['page'] === 'artpulse-settings' && ( $_GET['tab'] ?? '' ) === 'import_export';
        if ( ! $is_csv_page && ! $is_settings_tab ) {
            return;
        }
        // Enqueue advanced JS/CSS
        wp_enqueue_style('ead-csv-advanced-import', plugins_url('../../assets/css/csv-advanced-import.css', __FILE__), [], '1.0.0');
        wp_enqueue_script('ead-csv-advanced-import', plugins_url('../../assets/js/csv-advanced-import.js', __FILE__), ['jquery'], '1.0.0', true);
        // Localize REST info & available meta fields
        wp_localize_script('ead-csv-advanced-import', 'eadMetaFields', self::get_meta_fields());
        wp_localize_script('ead-csv-advanced-import', 'eadMappingPresets', CSVImportExport::get_all_saved_mappings());

        // Pass REST URL and nonce using wp_add_inline_script to avoid incorrect parameter notices
        $inline_data = 'var eadRestUrl = ' . wp_json_encode( rest_url() ) . '; ' .
                       'var eadRestNonce = ' . wp_json_encode( wp_create_nonce( 'wp_rest' ) ) . ';';
        wp_add_inline_script( 'ead-csv-advanced-import', $inline_data, 'before' );
    }

    public static function get_meta_fields() {
        // Merge all available fields for event/org
        $event_fields = [
            'event_title','event_type','venue_name','event_start_date','event_end_date','event_address','event_webaddress',
            'admission_fee_general','admission_fee_students','admission_fee_seniors','admission_fee_children','venue_email','venue_phone',
            'organiser_profile_short_description','organiser_email','organiser_phone','event_highlight_one','event_highlight_two','event_highlight_three','event_highlight_four',
            'featured_artists_text','opening_reception_date','opening_reception_time','opening_reception_text',
            'talk_date','talk_time','talk_text','walkabout_date','walkabout_time','walkabout_text','workshop_date','workshop_time','workshop_text','workshop_fee',
            'special_instructions','feature_image_landscape','additional_image_1','additional_image_2','additional_image_3','additional_image_4','additional_image_5','additional_image_6',
            'favourite_event','online_exhibition','online_art_auction','art_competition','organisation_facebook_page','organisation_twitter_handle',
            'organisation_instagram_handle','organisation_linkedin','organisation_video_url','organisation_other_platform','_ead_gallery_images'
        ];
        $org_fields = [
            'ead_org_name','org_name','org_profile_short_description','org_email','org_phone','org_website','org_address','org_logo_id','org_banner_id','org_featured_image_id',
            'org_facebook_page','org_twitter_handle','org_instagram_handle','org_linkedin','org_video_url','org_other_platform','org_type_notes','_ead_gallery_images'
        ];
        return array_values(array_unique(array_merge($event_fields, $org_fields)));
    }
}
