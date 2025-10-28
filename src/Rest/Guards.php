<?php

namespace ArtPulse\Rest;

use ArtPulse\Core\Capabilities;
use ArtPulse\Frontend\Shared\PortfolioAccess;
use WP_Error;
use WP_REST_Request;

/**
 * Shared permission guards for REST endpoints.
 */
final class Guards
{
    /**
     * Allow only the portfolio owner (or admin) to mutate data.
     *
     * @param WP_REST_Request $request Request instance.
     *
     * @return bool|WP_Error
     */
    public static function own_portfolio_only(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error(
                'auth_required',
                __('Authentication required', 'artpulse-management'),
                ['status' => 401]
            );
        }

        $post_id = (int) $request->get_param('id');
        if (!PortfolioAccess::can_manage_portfolio($user_id, $post_id)) {
            return new WP_Error(
                'forbidden',
                __('You cannot edit this portfolio', 'artpulse-management'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * Allow only logged-in users with portfolio permissions to create drafts.
     *
     * @return bool|WP_Error
     */
    public static function portfolio_creator_only(WP_REST_Request $request)
    {
        $user_id = get_current_user_id();
        if ($user_id <= 0) {
            return new WP_Error(
                'auth_required',
                __('Authentication required', 'artpulse-management'),
                ['status' => 401]
            );
        }

        if (!user_can($user_id, Capabilities::CAP_MANAGE_PORTFOLIO)) {
            return new WP_Error(
                'forbidden',
                __('You do not have permission to create an artist profile.', 'artpulse-management'),
                ['status' => 403]
            );
        }

        return true;
    }
}
