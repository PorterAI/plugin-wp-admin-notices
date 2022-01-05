<?php
/**
 *
 * Plugin Name:       WP Admin Notices
 * Description:       Helper plugin for developers to easily time-based admin notices for posts, comments, or taxonomies.
 * Version:           1.0.1
 * Author:            PorterAI
 * Author URI: 		  https://porterai.com
 * License:           GPL-2.0+
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Text Domain:       simple-admin-branding
 * Domain Path: 	  /languages
 */

namespace PorterAI;

defined( 'ABSPATH' ) or die( 'Access forbidden!' );

if (!class_exists('PorterAI\Admin_Notices')) :

class Admin_Notices {

    public $object_id;
    public $object_type;

    var $notice_type;
    var $notice_message;
    var $notice_key;
    var $is_dismissable;
    var $time;
    var $expiry; // in secconds
    
    const AJAX_DISMISS_HOOK = 'pai_dismiss_notice';

	/**
	 * Meta
	 */
    const ADMIN_NOTICES_META_KEY = 'pai_admin_notices';

    /**
     * Notice to class mappings
     */
    private static $notice_types = array(
        'info' => 'notice-info', // blue
        'warning' => 'notice-warning', // orange
        'error' => 'notice-error', // red
        'success' => 'notice-success', // green
    );

    private static $object_types = array(
        'post',
        'comment',
        'term',
    );

    /**
	 * Setup our action hooks.
	 *
	 * @return void
	 */
	public static function init_hooks() {
       add_action( 'admin_notices', array(__CLASS__, 'display_notices') );
       add_action( 'admin_footer', array(__CLASS__, 'admin_footer_script') );
       add_action( 'wp_ajax_'.self::AJAX_DISMISS_HOOK, array(__CLASS__, 'ajax_dismiss_notice') );
    }

    /**
     * Create a notice board globally or specifc to certain object types.
     *
     * @param integer $object_id
     * @param string $type
     */
    function __construct( $object_id = false, $type = false ) {
        if (is_int($object_id) && $object_id > 0 && in_array($type, self::$object_types)) {
            $this->object_id = (int) $object_id;
            $this->object_type = $type;
        } else {
            $this->object_id = false;
            $this->object_type = false;
        }
    }

    /**
     * Create a new notice.
     *
     * @param string $notice_key Unique key to save the notice by.
     * @param string $notice_msg Message to display.
     * @param string $notice_type options are info|warning|success|error
     * @param boolean $is_dismissable
     * @param boolean $expiry - time in seconds to delete if it's past
     * @param boolean $error_log - if true, will also log to the error log.
     */
	public function add_notice( $notice_key = '', $notice_message = '', $notice_type = 'info', $is_dismissable = true, $expiry = 0, $error_log = false ) {
        $this->notice_key = $notice_key;
        $this->notice_message = $notice_message;
        $this->notice_type = isset( self::$notice_types[ $notice_type ] ) ? self::$notice_types[ $notice_type ] :  self::$notice_types[ 'info' ]; 
        $this->is_dismissable = $is_dismissable;
        $this->time = current_time('timestamp');
        $this->expiry = $expiry;

        $existing_notices = $this->get_notices();
        $existing_notices[ $this->notice_key ] = $this;

        if ($this->object_id > 0 && !empty($this->object_type)) {
            $function = "update_{$this->object_type}_meta";
            $result = $function($this->object_id, self::ADMIN_NOTICES_META_KEY, $existing_notices);
        } else {
            $result = update_option(self::ADMIN_NOTICES_META_KEY, $existing_notices, false);
        }

        if ($error_log) {
            error_log($notice_message);
        }

        return $result;
    }

    /**
     * Delete new notice.
     *
     * @param string $notice_key
     * @param boolean $is_dismissable
     */
	public function delete_notice( $notice_key = '' ) {
        $existing_notices = $this->get_notices();        
        if ( isset($existing_notices[ $notice_key ]) ) {
            unset( $existing_notices[ $notice_key ] );
        }

        if ($this->object_id > 0 && !empty($this->object_type)) {
            $function = "update_{$this->object_type}_meta";
            $result = $function($this->object_id, self::ADMIN_NOTICES_META_KEY, $existing_notices);
        } else {
            $result = update_option(self::ADMIN_NOTICES_META_KEY, $existing_notices, false);
        }
        return $result;
    }

    /**
     * Get all notices for objects or general.
     *
     * @return array
     */
    public function get_notices() {
        if ($this->object_id > 0 && !empty($this->object_type)) {
            $function = "get_{$this->object_type}_meta";
            $existing_notices = $function($this->object_id, self::ADMIN_NOTICES_META_KEY, true);
        } else {
            $existing_notices = get_option(self::ADMIN_NOTICES_META_KEY, array());
        }
        return !empty($existing_notices) && is_array($existing_notices) ? $existing_notices : array();
    }

    /**
     * Delete all notices for this object.
     *
     * @param integer $post_id
     * @return void
     */
    public function clear_notices() {
        if ($this->object_id > 0 && !empty($this->object_type)) {
            $function = "delete_{$this->object_type}_meta";
            $result = $function($this->object_id, self::ADMIN_NOTICES_META_KEY);
        } else {
            $result = delete_option(self::ADMIN_NOTICES_META_KEY);
        }
        return $result;
    }

	/**
     * Hook to display our notices.
     *
     * @return void
     */
    public static function display_notices() {
        
        // parent_base - edit
        // base - post
        global $pagenow;
        $screen = get_current_screen();
        $notices = array();
        $object_notices = array();
                
        // If it's object specific.
        // Comments
        if (isset($screen->parent_base) && ($screen->parent_base == 'edit-comments')) {
            // Comment archive
            if ($screen->base === 'edit-comments') {
                
            } // Comment single
            elseif ($screen->base === 'comment') {
                $commentID = !empty( $_REQUEST['c'] ) ? (int) $_REQUEST['c'] : 0;
                $board = new self($commentID, 'comment');
                $object_notices = $board->get_notices();
            }
        }
        // Posts
		elseif (isset($screen->parent_base) && ($screen->parent_base == 'edit')) {
            global $post;
            if (isset($post->ID) && ($pagenow === 'post.php') && ($screen->post_type === $post->post_type)) {
                $board = new self($post->ID, 'post');
                $object_notices = $board->get_notices();
            }
            else if (isset($_REQUEST['tag_ID']) && ($pagenow === 'term.php') && isset($screen->taxonomy)) {
                $term_id = (int) $_REQUEST['tag_ID'];
                $board = new self($term_id, 'term');
                $object_notices = $board->get_notices();
            }
        }
        
        $option_board = new self(0, false);
        $all_notices = $option_board->get_notices();
        $notices = array_merge($all_notices, $object_notices);
        if (empty($notices)) {
            return;
        }

        foreach($notices as $notice_key => $notice) :
            // Skip and delete if the notice is expired.
            if ( is_int($notice->expiry) && ($notice->expiry > 0) ) {
                $total_elapsed = current_time('timestamp') - $notice->time;
                if ($total_elapsed > $notice->expiry) {
                    if ( !empty($notice->object_type) ) {
                        $board->delete_notice($notice->notice_key);
                    }
                    else {
                        $option_board->delete_notice($notice->notice_key);
                    }
                    continue;
                }
            }

            // If it's a screen specific notice.
            // if (!$notice->is_post_notice && (is_string($notice->notice_post_id)) ) {
            //     if ($notice->notice_post_id !== $screen->base) {
            //         continue;
            //     }
            // }

            $notice_classes = " {$notice->notice_type}";
            if ($notice->is_dismissable) {
                $notice_classes .= " is-dismissible";
            }
            $object_type = !empty($notice->object_type) ? $notice->object_type : 'option';
            $object_id = !empty($notice->object_id) ? $notice->object_id : -1;

            ?>
            <div class="notice pai-notice<?php echo $notice_classes ?>" data-notice-object-type="<?php echo $object_type ?>" data-notice-object-id="<?php echo $object_id ?>" data-notice-key="<?php echo $notice->notice_key ?>">
                <p><?php _e( $notice->notice_message, 'wp-admin-notices' ); ?></p>
            </div>
        <?php
        endforeach;
    }

    /**
     * AJAX handler to delete the notice from client-side.
     *
     * @return void
     */
    public static function ajax_dismiss_notice() {

        if ( ! wp_verify_nonce( $_POST['nonce'], 'wp-admin-notices' ) ) {
            die ('Failed');
        }

        if ( ! isset($_REQUEST['object_id']) || ! isset($_REQUEST['object_type']) || ! isset($_REQUEST['key']) ) {
            wp_send_json_error(null, 404);
        }

        $object_type = $_REQUEST['object_type'];
        if ( ! in_array($object_type, self::$object_types) && ($object_type !== 'option') ) {
            wp_send_json_error(null, 500);
        }
        
        $object_id = (int) $_REQUEST['object_id'];
        $object_type = ($object_type !== 'option') ? $object_type : false;
        $key = esc_attr( $_REQUEST['key'] );

        $board = new self( $object_id, $object_type );
        $result = $board->delete_notice($key);
        wp_send_json_success($result);
    }
    
    /**
     * Footer JS to send the AJAX request to delete a notice.
     *
     * @return void
     */
    public static function admin_footer_script() {
        $nonce = wp_create_nonce('wp-admin-notices');
        ?>
        <script type="text/javascript">
		(function ($) {
            $(document).on( 'click', '.pai-notice .notice-dismiss', function() {
                var $el = $(this).closest('.pai-notice');
                var key = $el.data('notice-key');
                var object_id = $el.data('notice-object-id');
                var object_type = $el.data('notice-object-type');
                
		        var data = { 
                    action: '<?php echo self::AJAX_DISMISS_HOOK ?>', 
                    nonce: '<?php echo $nonce ?>', 
                    object_id: object_id,
                    object_type: object_type, 
                    key: key 
                };
                console.log("data", data);
		        $.post( '<?php echo get_admin_url() . 'admin-ajax.php' ?>', data, function() {});
            });
		})(jQuery);
        </script>
        <?php
    }
}

Admin_Notices::init_hooks();

endif;