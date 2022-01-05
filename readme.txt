=== WP Admin Notices ===
Contributors: jbouganim
Tags: custom dev tools, notices, admin notices
Requires at least: 4.3
Tested up to: 5.8
Stable tag: trunk
Requires PHP: 5.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
Donate link: https://www.paypal.com/donate/?hosted_button_id=T96GUHGVDQ4EY

Helper plugin for developers to easily time-based admin notices for posts, comments, or taxonomies.

== Description ==

Example Usage: 

Post Type:
<code>
$notice_board = new WP_Admin_Notices( $post_id, 'post' );
$notice_board->add_notice("attachments-move-failed", "Failed to update this post", 'error', true);
</code>

Comment:
<code>
$notice_board = new WP_Admin_Notices( $commend_id, 'comment' );
$notice_board->add_notice("attachments-move-failed", "Updated this comment", 'info', true);
</code>

Term:
<code>
$notice_board = new WP_Admin_Notices( $term_id, 'term' );
$notice_board->add_notice("attachments-move-failed", "Failed to upload image", 'warning', true);
</code>

Global:
<code>
$notice_board = new WP_Admin_Notices();
$notice_board->add_notice("attachments-move-failed", "Enabled X Feature", 'success', true);
</code>

== FAQ ==

= Where are different notice types?

`info`, `warning`, `error`, `success`.

= Can I contribute/report an issue/request a feature? =

Yes. You can [submit issues](https://github.com/PorterAI/plugin-wp-admin-notices/issues) on Github or add questions to the support forum. Happy to accept pull requests as well.

== Installation ==

1. Install and activate the plugin through the plugins page, or upload the zip file in WordPress Admin > Plugins > Add New.

== Changelog ==

= 1.0 - 4th January 2022 =