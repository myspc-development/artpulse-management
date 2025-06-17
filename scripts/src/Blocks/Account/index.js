import { registerBlockType } from '@wordpress/blocks';

registerBlockType('artpulse/account', {
  edit: () => {
    return (
      <div className="ap-block-account-preview">
        <p>ArtPulse Membership Account will render here on the front end.</p>
      </div>
    );
  },
  save: () => {
    return (
      <div>
        {'<!-- wp:shortcode -->[ap_membership_account]<!-- /wp:shortcode -->'}
      </div>
    );
  }
});
