<?php
/**
 * Plugin Name: OP Addon for WP Courseware
 * Plugin URI:  www.optimizepress.com
 * Description: Plugin adds option to meta box for rendering a WP Courseware unit details over shortcode and fixes rendering complete box multiple time over the page
 * Version:     1.2
 * Author:      OptimizePress <info@optimizepress.com>
 * Author URI:  optimizepress.com
 */

class OptimizePress_WPCourseware_Integration
{
    /**
     * @var OptimizePress_WPCourseware_Integration
     */
    protected static $instance;

    /**
     * Registering actions and filters
     */
    protected function __construct()
    {
        add_action("wp_head", array($this, 'removeWPCoursewareContentFilter'));

        add_action("add_meta_boxes", array($this, 'addOPCoursewareMetaBox'));
        add_action("save_post", array($this, 'saveCustomMetaBox'), 9, 3);

        add_shortcode('op-wpcourseware', array($this, 'renderCoursewareUnitDetails'));

        add_filter('the_content', array($this, 'WPCWrenderUnitContent'));  
    }

    /**
     * Removes WP_Courseware the_content filter that renders unit_details
     * @return void
     */
    public function removeWPCoursewareContentFilter()
    {
        global $post;
        $checkbox_value = get_post_meta($post->ID, "op-courseware-checkbox", true);
        if (intval($checkbox_value) == 1) {
            remove_filter('the_content', 'WPCW_units_processUnitContent' , 20);
        }
    }

    /**
     * Adds Meta Box with OptimizePress and WP_Courseware compatibility
     * @return void
     */
    public function addOPCoursewareMetaBox()
    {
        add_meta_box(
            "op-courseware-box",
            __("OptimizePress & WP Courseware", "op_courseware"),
            array($this, "customMetaBoxMarkup"),
            "course_unit",
            "side",
            "default"
        );
    }

    /**
     * Renders WP-Coursware unit detail over [op-courseware] shortcode
     * @return string
     */
    public static function renderCoursewareUnitDetails()
    {
        global $post;
        if (class_exists('WPCW_UnitFrontend')) {
            $fe = new WPCW_UnitFrontend($post);
            return $fe->render_detailsForUnit("");
        }
    }

    /**
    * Display content with with WPCW Shortcode
    * @return string
    */
    public static function WPCWrenderUnitContent($content)
    {
        
        // #### Ensure we're only showing a course unit, a single item
        if (!is_single() || 'course_unit' !=  get_post_type()) {
            return $content;
        }

        global $post;
        $fe = new WPCW_UnitFrontend($post);


        // #### Get associated data for this unit. No course/module data, then it's not a unit
        if (!$fe->check_unit_doesUnitHaveParentData()) {
            return $content;
        }

        // Check for drip content
        $lockDetails = $fe->render_completionBox_contentLockedDueToDripfeed();
        if ($lockDetails['content_locked']) {
            // Do not return any content
            $content = false;

            // Get parent data
            $parentData = WPCW_units_getAssociatedParentData($post->ID);
            // Prepare drip access message
            $lockedMsg = $fe->message_createMessage_error($parentData->course_message_unit_not_yet_dripfeed);
            // Replace variable with the actual time delay before the unit is unlocked.
            $completionBox = str_ireplace('{UNIT_UNLOCKED_TIME}', WPCW_date_getHumanTimeDiff($lockDetails['unlock_date']), $lockedMsg);
            // Show the message and navigation box.
            return $completionBox . $fe->render_navigation_getNavigationBox();
        }

        // #### Ensure we're logged in
        if (!$fe->check_user_isUserLoggedIn()) {
            return $fe->message_user_notLoggedIn();
        }

        // #### User not allowed access to content, so certainly can't say they've done this unit.
        if (!$fe->check_user_canUserAccessCourse()) {
            return $fe->message_user_cannotAccessCourse();
        }

        // #### Is user allowed to access this unit yet?
        if (!$fe->check_user_canUserAccessUnit())
        {
            // DJH 2015-08-18 - Added capability for a previous button if we've stumbled
            // on a unit that we're not able to complete just yet.
            $navigationBox = $fe->render_navigation_getNavigationBox();

            // Show the navigation box AFTErR the cannot progress message.
            return $fe->message_user_cannotAccessUnit() . $navigationBox;
        }

        // #### Has user completed course prerequisites
        if (!$fe->check_user_hasCompletedCoursePrerequisites())
        {
            // on a unit that we're not able to complete just yet.
            $navigationBox = $fe->render_navigation_getNavigationBox();

            // Show navigation box after the cannot process message.
            return $fe->message_user_hasNotCompletedCoursePrerequisites() . $navigationBox;
        }

        //return $content;
        return $content;

    }

    /**
     * Renders checkbox inside Meta box
     * @return string
     */
    public function customMetaBoxMarkup($object)
    {
        wp_nonce_field("op-courseware-box-nonce", "op-courseware-box-nonce");
        $checkbox_value = get_post_meta($object->ID, "op-courseware-checkbox", true);
        ?>
        <div>
            <input name="op-courseware-checkbox" type="checkbox" value="1" <?php checked($checkbox_value, 1); ?>>
            <label for="op-courseware-checkbox"><?php _e("Use shortcode [op-wpcourseware] for rendering WP Courseware complete box", "op_courseware"); ?></label>
        </div>
        <?php
    }

    /**
     * Saves op-coursware meta
     * @return string
     */
    public function saveCustomMetaBox($post_id, $post, $update)
    {
        if ((!isset($_POST["op-courseware-box-nonce"]) || !wp_verify_nonce($_POST["op-courseware-box-nonce"], "op-courseware-box-nonce"))
            || (!current_user_can("edit_post", $post_id))
            || (defined("DOING_AUTOSAVE") && DOING_AUTOSAVE)
            || ("course_unit" !== $post->post_type)) {
            return $post_id;
        }

        $meta_box_checkbox_value = "";

        if (isset($_POST["op-courseware-checkbox"])) {
            $meta_box_checkbox_value = intval($_POST["op-courseware-checkbox"]);
        }
        update_post_meta($post_id, "op-courseware-checkbox", $meta_box_checkbox_value);
    }

    /**
     * Singleton
     * @return OptimizePress_WPCourseware_Integration
     */
    public static function getInstance()
    {
        if (null == self::$instance) {
            self::$instance = new self;
        }
        return self::$instance;
    }
}

OptimizePress_WPCourseware_Integration::getInstance();