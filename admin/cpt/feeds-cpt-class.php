<?php
/**
 * Feeds CPT Class
 *
 * This class is what initiates the Feed Them Social class
 *
 * @version  1.0.0
 * @package  FeedThemSocial/Core
 * @author   SlickRemix
 */

namespace feedthemsocial;

// Exit if accessed directly!
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}


/**
 * Feeds
 *
 * @package FeedThemSocial/Core
 */
class Feeds_CPT {

    /**
     * Parent Post ID
     * used to set Gallery ID
     *
     * @var string
     */
    public $parent_post_id = '';

    /**
     * Feed Functions
     *
     * The Feed Functions Class
     *
     * @var object
     */
    public $feed_functions;


    /**
     * Feed Settings Array
     * An array of Feed Settings. Set in admin/cpt/options/feeds-cpt-options.php
     *
     * @var array
     */
    public $feed_settings_array = array();

    /**
     * Global Prefix
     * Sets Prefix for global options
     *
     * @var string
     */
    public $global_prefix = 'global_';

    /**
     * ZIP Gallery Class
     * initiates ZIP Gallery Class
     *
     * @var string
     */
    public $zip_gallery_class = '';

    /**
     * Gallery Options
     * initiates Gallery Options Class
     *
     * @var string
     */
    public $gallery_options_class = '';

    /**
     * Setting Options JS
     * initiates Setting Options JS Class
     *
     * @var object
     */
    public $setting_options_js;

    /**
     * Access Token Options
     * initiates Access Token Options class.
     *
     * @var object
     */
    public $access_token_options;

    /**
     * Metabox Settings Class
     * initiates Metabox Settings Class
     *
     * @var string
     */
    public $metabox_settings_class;

    /**
     * Feeds_CPT constructor.
     *
     * @param object  $feed_cpt_options All options.
     */
    public function __construct( $feed_functions, $feed_cpt_options, $setting_options_js, $settings_functions, $access_token_options) {


        $this->add_actions_filters();

        // Set Feed Functions object.
        $this->feed_functions = $feed_functions;


        $this->feed_settings_array = $feed_cpt_options->get_all_options();

        $this->setting_options_js = $setting_options_js;

        // we set current_user_can so our backend functions don't get loaded to the front end.
        // this came about after a ticket we received about our plugin being active and
        // causing a woo booking plugin to not be able to checkout proper, when checking out it would show the cart was empty.
        // this current_user_can resolves that problem.
        if ( ! function_exists( 'wp_get_current_user' ) ) {
            include ABSPATH . 'wp-includes/pluggable.php';
        }

        if ( current_user_can( 'manage_options' ) ) {
            // Load Metabox Setings Class (including all of the scripts and styles attached).
            $this->metabox_settings_class = new Metabox_Settings( $this, $this->feed_settings_array, $settings_functions );

            // Set Main Post Type.
            $this->metabox_settings_class->set_main_post_type( FEED_THEM_SOCIAL_POST_TYPE );

            // Set Metabox Specific Form Inputs.
            $this->metabox_settings_class->set_metabox_specific_form_inputs( true );
        }

        // If Premium add Functionality!
        if ( is_plugin_active( 'feed-them-social-premium/feed-them-social-premium.php' ) ) {
            //Premium Features here.
        }


        //Access Token Options
        $this->access_token_options = $access_token_options;

    }

    /**
     * Add Actions & Filters
     *
     * Adds the Actions and filters for the class.
     *
     * @since 1.1.8
     */
    public function add_actions_filters() {

        // Scripts.
        add_action( 'admin_enqueue_scripts', array( $this, 'fts_scripts' ) );

        // Register Feed CPT!
        add_action( 'init', array( $this, 'fts_cpt' ) );

        // Response Messages!
        add_filter( 'post_updated_messages', array( $this, 'fts_updated_messages' ) );

        // Feed List function!
        add_filter( 'manage_fts_posts_columns', array( $this, 'fts_set_custom_edit_columns' ) );
        add_action( 'manage_fts_posts_custom_column', array( $this, 'fts_custom_edit_column' ), 10, 2 );

        // Change Button Text!
        add_filter( 'gettext', array( $this, 'fts_set_button_text' ), 20, 3 );

        // Add Meta Boxes!
        add_action( 'add_meta_boxes', array( $this, 'fts_add_metaboxes' ) );

        // Rename Submenu Item to Feeds!
        add_filter( 'attribute_escape', array( $this, 'fts_rename_submenu_name' ), 10, 2 );

        // Add Shortcode!
        add_shortcode( 'fts_list', array( $this, 'fts_display_list' ) );

        // Check Current Page we are on.
        add_action( 'current_screen', array( $this, 'fts_check_page' ) );

        if ( '' === get_option( 'fts_duplicate_post_show' ) ) {

            add_action( 'admin_action_fts_duplicate_post_as_draft', array( $this, 'fts_duplicate_post_as_draft' ) );
            add_filter( 'page_row_actions', array( $this, 'fts_duplicate_post_link' ), 10, 2 );
            add_filter( 'fts_row_actions', array( $this, 'fts_duplicate_post_link' ), 10, 2 );
            add_action( 'post_submitbox_start', array( $this, 'fts_duplicate_post_add_duplicate_post_button' ) );

        }
    }

    /**
     *  Tab Notice HTML
     *
     * Creates notice html for return
     *
     * @since 1.0.0
     */
    public function fts_tab_premium_msg() {
        echo sprintf(
            esc_html__( '%1$sPlease purchase, install and activate %2$sFeed Them Social Premium%3$s for these additional awesome features!%4$s', 'feed_them_social' ),
            '<div class="ft-gallery-premium-mesg">',
            '<a href="' . esc_url( 'https://www.slickremix.com/downloads/feed-them-social/' ) . '" target="_blank">',
            '</a>',
            '</div>'
        );
    }

    /**
     * Check Page
     *
     * What page are we on?
     *
     * @since 1.0.0
     */
    public function fts_check_page() {
        $current_screen = get_current_screen();

        $my_get  = stripslashes_deep( $_GET );
        $my_post = stripslashes_deep( $_POST );

        if ( 'fts' === $current_screen->post_type && 'post' === $current_screen->base && is_admin() ) {
            if ( isset( $my_get['post'] ) ) {
                $this->parent_post_id = $my_get['post'];
            }
            if ( isset( $my_post['post'] ) ) {
                $this->parent_post_id = $my_post['post'];
            }
        }
    }

    /**
     * Create Feed Them Social Custom Post Type
     *
     * Create custom post type.
     *
     * @since 1.0.0
     */
    public function fts_cpt() {
        $responses_cpt_args = array(
            'label'               => esc_html__( 'Feed Them Social', 'feed_them_social' ),
            'labels'              => array(
                'menu_name'          => esc_html__( 'Feeds', 'feed_them_social' ),
                'name'               => esc_html__( 'Feeds', 'feed_them_social' ),
                'singular_name'      => esc_html__( 'Feed', 'feed_them_social' ),
                'add_new'            => esc_html__( 'Add Feed', 'feed_them_social' ),
                'add_new_item'       => esc_html__( 'Add New Feed', 'feed_them_social' ),
                'edit_item'          => esc_html__( 'Edit Feed', 'feed_them_social' ),
                'new_item'           => esc_html__( 'New Feed', 'feed_them_social' ),
                'view_item'          => esc_html__( 'View Feed', 'feed_them_social' ),
                'search_items'       => esc_html__( 'Search Feeds', 'feed_them_social' ),
                'not_found'          => esc_html__( 'No Feeds Found', 'feed_them_social' ),
                'not_found_in_trash' => esc_html__( 'No Feeds Found In Trash', 'feed_them_social' ),
            ),

            'public'              => true,
            'publicly_queryable'  => true,
            'show_ui'             => true,
            'capability_type'     => 'post',
            'show_in_menu'        => true,
            'show_in_nav_menus'   => false,
            'exclude_from_search' => true,

            'capabilities'        => array(
                'create_posts' => true, // Removes support for the "Add New" function ( use 'do_not_allow' instead of false for multisite set ups )
            ),
            'map_meta_cap'        => true, // Allows Users to still edit Payments
            'has_archive'         => true,
            'hierarchical'        => true,
            'query_var'           => 'fts',
            'rewrite'             => array( 'slug' => 'fts-cpt' ),

            'menu_icon'           => '',
            'supports'            => array( 'title', 'revisions', 'thumbnail' ),
            'order'               => 'DESC',
            // Set the available taxonomies here
            // 'taxonomies' => array('fts_topics')
        );
        register_post_type( 'fts', $responses_cpt_args );
    }

    /**
     *  Categories (Custom Taxonomy)
     *
     * Create Custom Taxonomy.
     *
     * @since 1.0.2
     */
    public function fts_categories() {

        $labels = array(
            'name'              => esc_html__( 'Categories', 'feed_them_social' ),
            'singular_name'     => esc_html__( 'Category', 'feed_them_social' ),
            'search_items'      => esc_html__( 'Search Categories', 'feed_them_social' ),
            'all_items'         => esc_html__( 'All Categories', 'feed_them_social' ),
            'parent_item'       => esc_html__( 'Parent Category', 'feed_them_social' ),
            'parent_item_colon' => esc_html__( 'Parent Category:', 'feed_them_social' ),
            'edit_item'         => esc_html__( 'Edit Category', 'feed_them_social' ),
            'update_item'       => esc_html__( 'Update Category', 'feed_them_social' ),
            'add_new_item'      => esc_html__( 'Add New Category', 'feed_them_social' ),
            'new_item_name'     => esc_html__( 'New Category Name', 'feed_them_social' ),
            'menu_name'         => esc_html__( 'Categories', 'feed_them_social' ),
        );

        register_taxonomy(
            'fts_cats',
            array( 'fts' ),
            array(
                'hierarchical'          => false,
                'labels'                => $labels,
                'show_ui'               => true,
                'show_admin_column'     => true,
                'query_var'             => true,
                'rewrite'               => true,
                'update_count_callback' => '_update_generic_term_count',
            )
        );
    }

    /**
     * Register Taxonomy for Attachments
     *
     * Registers
     *
     * @since 1.0.2
     */
    public function fts_add_cats_to_attachments() {
        register_taxonomy_for_object_type( 'fts_cats', 'attachment' );
        // add_post_type_support('attachment', 'fts_cats');
    }

    /**
     * Rename Submenu Name
     * Renames the submenu item in the WordPress dashboard's menu
     *
     * @param $safe_text
     * @param $text
     * @return string
     * @since 1.0.0
     */
    public function fts_rename_submenu_name( $safe_text, $text ) {
        if ( 'Feeds' !== $text ) {
            return $safe_text;
        }
        // We are on the main menu item now. The filter is not needed anymore.
        remove_filter( 'attribute_escape', array( $this, 'fts_rename_submenu_name' ) );

        return esc_html( 'FT Social' );
    }

    /**
     * Updated Messages
     * Updates the messages in the admin area so they match plugin
     *
     * @param $messages
     * @return mixed
     * @since 1.0.0
     */
    public function fts_updated_messages( $messages ) {
        // global $post, $post_ID;
        $messages['fts'] = array(
            0  => '', // Unused. Messages start at index 1.
            1  => esc_html__( 'Feed updated.', 'feed_them_social' ),
            2  => esc_html__( 'Custom field updated.', 'feed_them_social' ),
            3  => esc_html__( 'Custom field deleted.', 'feed_them_social' ),
            4  => esc_html__( 'Feed updated.', 'feed_them_social' ),
            /* translators: %s: date and time of the revision */
            5  => isset( $_GET['revision'] ) ? sprintf( esc_html__( 'Response restored to revision from %s', 'feed_them_social' ), wp_post_revision_title( (int) $_GET['revision'], false ) ) : false,
            6  => esc_html__( 'Feed created.', 'feed_them_social' ),
            7  => esc_html__( 'Feed saved.', 'feed_them_social' ),
            8  => esc_html__( 'Feed submitted.', 'feed_them_social' ),
            9  => esc_html__( 'Feed scheduled for:', 'feed_them_social' ),
            // translators: Publish box date format, see http://php.net/date
            // date_i18n( ( 'M j, Y @ G:i' ), strtotime( $post->post_date ) ), esc_url( get_permalink($post_ID) ) ),
            10 => esc_html__( 'Feed draft updated.', 'feed_them_social' ),
        );

        return $messages;
    }

    /**
     *  Set Custom Edit Columns
     *
     * Sets the custom admin columns for gallery list page
     *
     * @param $columns
     * @return array
     * @since 1.0.0
     */
    public function fts_set_custom_edit_columns( $columns ) {

        $new = array();

        foreach ( $columns as $key => $value ) {
            // when we find the date column.
            if ( 'title' === $key ) {
                $new[ $key ] = $value;
                $new['feed_shortcode'] = esc_html__( 'Feed Shortcode', 'feed_them_social' );
                $new[ $key ] = $value;
                $new['shortcode_location'] = esc_html__( 'Shortcode Location', 'feed_them_social' );

            } else {
                $new[ $key ] = $value;
            }
        }

        return $new;
    }

    /**
     * FT Galley Custom Edit Column
     * Put info in matching coloumns we set
     *
     * @param $column
     * @param $post_id
     * @since 1.0.0
     */
    public function fts_custom_edit_column( $column, $post_id ) {

        switch ( $column ) {

            // Display the Shortcode.
            case 'feed_shortcode':
                ?>
                <input value="[feed_them_social cpt_id=<?php echo esc_html( $post_id ); ?>]" onclick="this.select()" readonly="readonly" />
                <?php
                break;
            // Display the Shortcode Location.
            case 'shortcode_location':

                // Things to make exceptions and checks for still.
                // 1. What if the shortcode is added to a widget not on a page or post?

                // We take the ID that we store in the fts_shortcode_location post meta key and return the page title and permalink
                // so users can click to the page the shortcode is on and replace it or remove it.
                $shortcode_location_id = get_post_meta( $post_id, 'fts_shortcode_location', true );

                // Get the post content so we can double check to see if it has a specific shortcode.
                $post = get_post( $shortcode_location_id );
                $the_content = $post->post_content;
                $shortcode = '[feed_them_social cpt_id=' . $post_id .']';

                // Check to see if the shortcode_location_id has been set with an ID and if so lets double check that content has a shortcode in it.
                // IF so then we will display a page title and link to it so the user can see where there shortcode is being used.
                if( strpos($the_content, $shortcode) !== false && !empty( $shortcode_location_id ) ){
                    $shortcode_location = '<a href="' . get_the_permalink( $shortcode_location_id )  . '" target="_blank">' . get_the_title( $shortcode_location_id ) . '</a>';
                    echo $shortcode_location;
                }
                else {
                    echo __( 'Not Set', 'feed-them-social' );
                }
                break;
        }
    }

    /**
     *  Set Button Text
     * Set Edit Post buttons for Feed custom post type
     *
     * @param $translated_text
     * @param $text
     * @param $domain
     * @return mixed
     * @since 1.0.0
     */
    public function fts_set_button_text( $translated_text, $text, $domain ) {
        $post_id          = isset( $_GET['post'] ) ? $_GET['post'] : '';
        $custom_post_type = get_post_type( $post_id );
        if ( ! empty( $post_id ) && 'fts_responses' === $custom_post_type ) {
            switch ( $translated_text ) {
                case 'Publish':
                    $translated_text = esc_html__( 'Save Feed', 'feed_them_social' );
                    break;
                case 'Update':
                    $translated_text = esc_html__( 'Update Feed', 'feed_them_social' );
                    break;
                case 'Save Draft':
                    $translated_text = esc_html__( 'Save Feed Draft', 'feed_them_social' );
                    break;
                case 'Edit Payment':
                    $translated_text = esc_html__( 'Edit Feed', 'feed_them_social' );
                    break;
            }
        }

        return $translated_text;
    }

    /**
     *  Scripts
     *
     * Create Feed custom post type
     *
     * @since 1.0.0
     */
    public function fts_scripts() {

        global $id, $post;

        // Get current screen.
        $current_screen = get_current_screen();

        if ( is_admin() && 'fts' === $current_screen->post_type && 'post' === $current_screen->base || is_admin() && 'fts' === $current_screen->post_type && isset( $_GET['page'] ) && 'template_settings_page' === $_GET['page'] || is_admin() && 'fts_albums' === $current_screen->post_type && 'post' === $current_screen->base ) {

            // Set the post_id for localization.
            $post_id = isset( $post->ID ) ? array( 'post' => $post->ID ) : '';

            // Image Uploader!
            wp_enqueue_media( $post_id );

            add_filter( 'plupload_init', array( $this, 'plupload_init' ) );

            // Enqueue Magnific Popup CSS.
            // wp_enqueue_style( 'magnific-popup-css', plugins_url( 'feed-them-social/includes/feeds/css/magnific-popup.css' ), array(), FTS_CURRENT_VERSION );

            // Enqueue Magnific Popup JS.
            // wp_enqueue_script( 'magnific-popup-js', plugins_url( 'feed-them-social/includes/feeds/js/magnific-popup.js' ), array(), FTS_CURRENT_VERSION );

            // wp_enqueue_style( 'ft-gallery-admin-ui-css', 'https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css', array(), FTS_CURRENT_VERSION );

        } else {
            return;
        }
    }

    /**
     * Add Meta Boxes
     *
     * Add metaboxes to the edit page.
     *
     * @since 1.0.0
     */
    public function fts_add_metaboxes() {
        global $post;
        // Check we are using Feed Them Social Custom Post type.
        if ( 'fts' !== $post->post_type ) {
            return;
        }

        // Image Uploader and Gallery area in admin.
        add_meta_box( 'ft-galleries-upload-mb', esc_html__( 'Feed Settings', 'feed_them_social' ), array( $this, 'fts_tab_menu_metabox' ), 'fts', 'normal', 'high', null );

        // Link Settings Meta Box.
        add_meta_box( 'ft-galleries-shortcode-side-mb', esc_html__( 'Feed Shortcode', 'feed_them_social' ), array( $this, 'fts_shortcode_meta_box' ), 'fts', 'side', 'high', null );

        // Old Shortcode Meta Box.
        add_meta_box( 'ft-galleries-old-shortcode-side-mb', esc_html__( 'Convert Old Shortcode', 'feed_them_social' ), array( $this, 'fts_old_shortcode_meta_box' ), 'fts', 'side', 'high', null );
    }

    /**
     *  Metabox Tabs List
     *
     * The list of tabs Items for settings page metaboxes.
     *
     * @return array
     * @since 1.1.6
     */
    public function metabox_tabs_list() {

        $metabox_tabs_list = array(
            // Base of each tab! The array keys are the base name and the array value is a list of tab keys.
            'base_tabs' => array(
                'post' => array( 'feed_setup', 'layout', 'colors', 'facebook_feed', 'instagram_feed', 'twitter_feed', 'youtube_feed', 'combine_streams_feed' ),
            ),
            // Tabs List! The cont_func item is relative the the Function name for that tabs content. The array Keys for each tab are also relative to classes and ID on wraps of display_metabox_content function.
            'tabs_list' => array(
                // Images Tab!
                'feed_setup'      => array(
                    'menu_li_class'      => 'tab1',
                    'menu_a_text'        => esc_html__( 'Feed Setup', 'feed_them_social' ),
                    'menu_a_class'       => 'account-tab-highlight',
                    'menu_aria_expanded' => 'true',
                    'cont_wrap_id'       => 'ftg-tab-content1',
                    'cont_func'          => 'tab_feed_setup',
                ),
                // Layout Tab!
                'layout'      => array(
                    'menu_li_class' => 'tab2',
                    'menu_a_text'   => esc_html__( 'Layout', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content2',
                    'cont_func'     => 'tab_layout_content',
                ),
                // Colors Tab!
                'colors'      => array(
                    'menu_li_class' => 'tab3',
                    'menu_a_text'   => esc_html__( 'Colors', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content3',
                    'cont_func'     => 'tab_colors_content',
                ),
                // Facebook Feed Settings Tab!
                'facebook_feed'        => array(
                    'menu_li_class' => 'tab4',
                    'menu_a_text'   => esc_html__( 'Facebook', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content6',
                    'cont_func'     => 'tab_facebook_feed',
                ),
                // Instagram Feed Settings Tab!
                'instagram_feed' => array(
                    'menu_li_class' => 'tab5',
                    'menu_a_text'   => esc_html__( 'Instagram', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content5',
                    'cont_func'     => 'tab_instagram_feed',
                ),
                // Twitter Feed Settings Tab!
                'twitter_feed'   => array(
                    'menu_li_class' => 'tab6',
                    'menu_a_text'   => esc_html__( 'Twitter', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content7',
                    'cont_func'     => 'tab_twitter_feed',
                ),
                // Youtube Feed Settings Tab!
                'youtube_feed'  => array(
                    'menu_li_class' => 'tab7',
                    'menu_a_text'   => esc_html__( 'Youtube', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content8',
                    'cont_func'     => 'tab_youtube_feed',
                ),
                // Combined Streams Feed Settings Tab!
                'combine_streams_feed'        => array(
                    'menu_li_class' => 'tab8',
                    'menu_a_text'   => esc_html__( 'Combined', 'feed_them_social' ),
                    'cont_wrap_id'  => 'ftg-tab-content9',
                    'cont_func'     => 'tab_combine_streams_feed',
                ),
            ),
        );





        return $metabox_tabs_list;
    }

    /**
     *  Tab Menu Metabox
     *
     * Creates the Tabs Menu Metabox
     *
     * @param $object
     * @since 1.0.0
     */
    public function fts_tab_menu_metabox( $object ) {

        $params['object'] = $object;

        $this->metabox_settings_class->display_metabox_content( $this->metabox_tabs_list(), $params );

        if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
            ?>
            <script>
                jQuery('#ftg_sorting_options, #ftg_free_download_size').attr('disabled', 'disabled');
                jQuery('#ftg_sorting_options option[value="no"], #ftg_free_download_size option:first').text('Premium Required');
                jQuery('.ftg-pagination-notice-colored').remove();
            </script>
        <?php } ?>


        <div class="clear"></div>

        <?php
    }

    /**
     * Tab Feed Type Content
     *
     * Outputs Feed Type Selection tab's content for metabox.
     *
     * @param $params
     * @since 1.1.6
     */
    public function tab_feed_setup( ) {

        echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['feed_type_options'], null, $this->parent_post_id );
        //echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['instagram_token_options'], null, $this->parent_post_id );
        //echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['instagram_business_token_options'], null, $this->parent_post_id );

        //echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['facebook_token_options'], null, $this->parent_post_id );
        echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['twitter_token_options'], null, $this->parent_post_id );
        //echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['youtube_token_options'], null, $this->parent_post_id );

        ?>
        <div class="ftg-section">
            <?php
            // Happens in JS file
            $this->metabox_settings_class->fts_tab_notice_html(); ?>

            <script>
                jQuery('.metabox_submit').click(function (e) {
                    e.preventDefault();
                    //  jQuery('#publish').click();
                    jQuery('#post').click();
                });
            </script>
        </div>

        <div class="ftg-access-token">
            <?php
            // Get Access Token Options.
            $this->access_token_options->get_access_token_options( $this->feed_functions->get_feed_type( $this->parent_post_id ) );
            ?>
        </div>
        <?php
    }

    /**
     *  Tab Layout Content
     *
     * Outputs Layout tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_layout_content() {

        echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['layout'], null, $this->parent_post_id );
    }

    /**
     * Tab Colors Content
     *
     * Outputs Colors tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_colors_content() {

        echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['colors'], null, $this->parent_post_id );
    }

    /**
     * Tab Facebook Feed
     *
     * Outputs Feed's settings tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_facebook_feed() {

        if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
            ?>

            <div class="ftg-section">
                <?php $this->fts_tab_premium_msg(); ?>
            </div>
        <?php } ?>
<div class="fts-cpt-main-options">
    <?php

        echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['facebook'], null, $this->parent_post_id );

    ?>

    <div class="clear"></div>
</div>

        <div class="fts-cpt-extra-options">
            <?php

            $facebook_additional_options = new Facebook_Additional_Options();

            $facebook_add_all_options = $facebook_additional_options->get_all_options();

            //Facebook Reviews text and styles.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_reviews_text_styles'], null, $this->parent_post_id );

            //Facebook Reviews and Overall Ratings styles.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_reviews_overall_rating_styles'], null, $this->parent_post_id );

            //Facebook Language Options.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_languages_options'], null, $this->parent_post_id );

            //Facebook Like Button or Box Options.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_like_button_box_options'], null, $this->parent_post_id );

            //Facebook Style Options.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_style_options'], null, $this->parent_post_id );

            //Facebook Grid Style Options.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_grid_style_options'], null, $this->parent_post_id );

            //Facebook Loadmore Options.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_load_more_options'], null, $this->parent_post_id );

            //Facebook Error Messages.
            echo $this->metabox_settings_class->settings_html_form( $facebook_add_all_options['facebook_error_messages_options'], null, $this->parent_post_id );

		    $this->setting_options_js->facebook_js();
            ?>

            <div class="clear"></div>
        </div>
		<?php
	}

    /**
     * Tab Instagram Feed
     *
     * Outputs Feed's settings tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_instagram_feed() {
        if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
            ?>

            <div class="ftg-section">
                <?php $this->fts_tab_premium_msg(); ?>
            </div>
		<?php } ?>
        <div class="fts-cpt-main-options">
        <?php
            echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['instagram'], null, $this->parent_post_id );
            $this->setting_options_js->instagram_js();
        ?>
        </div>

        <div class="fts-cpt-extra-options">
	        <?php
	        $instagram_additional_options = new Instagram_Additional_Options();

            $instagram_add_all_options = $instagram_additional_options->get_all_options();

            //Instagram Follow Button Options.
            echo $this->metabox_settings_class->settings_html_form( $instagram_add_all_options['instagram_follow_btn_options'], null, $this->parent_post_id );

            // FTS Premium ACTIVE
            if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
                //Instagram Load More Options
                echo $this->metabox_settings_class->settings_html_form( $instagram_add_all_options['instagram_load_more_options'], null, $this->parent_post_id );
            }?>

            <div class="clear"></div>
        </div>

        <?php
    }

    /**
     * Tab Twitter Feed
     *
     * Outputs Feed's settings tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_twitter_feed() {

        if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
            ?>

            <div class="ftg-section">
                <?php $this->fts_tab_premium_msg(); ?>
            </div>
        <?php } ?>
        <div class="fts-cpt-main-options">
            <?php
            echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['twitter'], null, $this->parent_post_id );

            //JS for Twitter Options.
            $this->setting_options_js->twitter_js();
            ?>
            <div class="clear"></div>
        </div>
        <div class="fts-cpt-extra-options">
	        <?php
	        $twitter_additional_options = new Twitter_Additional_Options();

            $twitter_add_all_options = $twitter_additional_options->get_all_options();

            // Twitter Follow Button Options
            echo $this->metabox_settings_class->settings_html_form( $twitter_add_all_options['twitter_follow_btn_options'], null, $this->parent_post_id );
            // Twitter Video Player Options
            echo $this->metabox_settings_class->settings_html_form( $twitter_add_all_options['twitter_video_player_options'], null, $this->parent_post_id );
            // Twitter Profile Photo Options
            echo $this->metabox_settings_class->settings_html_form( $twitter_add_all_options['twitter_profile_photo_options'], null, $this->parent_post_id );
            // Twitter Style Options
            echo $this->metabox_settings_class->settings_html_form( $twitter_add_all_options['twitter_style_options'], null, $this->parent_post_id );

            // FTS Premium ACTIVE
            if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
                // Twitter Grid Styles
                echo $this->metabox_settings_class->settings_html_form( $twitter_add_all_options['twitter_grid_style_options'], null, $this->parent_post_id );
                // Twitter Load More Button Styles & Options
                echo $this->metabox_settings_class->settings_html_form( $twitter_add_all_options['twitter_load_more_options'], null, $this->parent_post_id );
            }?>

            <div class="clear"></div>
        </div>

        <?php
    }


    /**
     * Tab Youtube Feed
     *
     * Outputs Feed's settings tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_youtube_feed() {
        if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
            ?>

                    <div class="ftg-section">
				<?php $this->fts_tab_premium_msg(); ?>
					</div>
        <?php } ?>
        <div class="fts-cpt-main-options">
        <?php
            echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['youtube'], null, $this->parent_post_id );

            $this->setting_options_js->youtube_js();
        ?>
            <div class="clear"></div>
        </div>
        <div class="fts-cpt-extra-options">
            <?php
            $youtube_additional_options = new Youtube_Additional_Options();

            $youtube_add_all_options = $youtube_additional_options->get_all_options();

            //Youtube Follow Button Options.
            echo $this->metabox_settings_class->settings_html_form( $youtube_add_all_options['youtube_follow_btn_options'], null, $this->parent_post_id );

            // FTS Premium ACTIVE
            if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
                //Youtube Load More Options.
                echo $this->metabox_settings_class->settings_html_form( $youtube_add_all_options['youtube_load_more_options'], null, $this->parent_post_id );
            }?>
            <div class="clear"></div>
        </div>

        <?php
    }

    /**
     * Tab Combined Streams Feed
     *
     * Outputs Feed's settings tab's content for metabox.
     *
     * @since 1.0.0
     */
    public function tab_combine_streams_feed() {
        if ( ! is_plugin_active( 'feed_them_social-premium/feed_them_social-premium.php' ) ) {
            ?>

            <div class="ftg-section">
                <?php $this->fts_tab_premium_msg(); ?>
            </div>
        <?php } ?>
<div class="fts-cpt-main-options-combined">
    <?php

        echo $this->metabox_settings_class->settings_html_form( $this->feed_settings_array['combine'], null, $this->parent_post_id );
        $this->setting_options_js->combine_js();

    ?>
    <div class="clear"></div>
</div>

		<?php
	}

    /**
     *  Old Shortcode Meta Box
     *
     *  copy & paste shortcode input box
     *
     * @param $object
     * @since 1.0.0
     */
    public function fts_old_shortcode_meta_box() {
        ?>
        <div class="ft-gallery-meta-wrap">
            <?php

            $gallery_id = isset( $_GET['post'] ) ? $_GET['post'] : '';

            $screen = get_current_screen();

            if ( 'edit.php?post_type=fts' === $screen->parent_file && 'add' === $screen->action ) {
                ?>
                <p>
                    <label><label><?php echo esc_html__( 'Add a Title for this Feed and click Publish, then you will be able to convert an old shortcode.', 'feed_them_social' ); ?></label>
                </p>
                <?php
            } else {
                // Copy Shortcode
                // [fts_facebook hide_date_likes_comments=yes type=page id=1562664650673366 access_token=EAAP9hArvboQBAM2dmJtxprnC6XnDeWfkEbgHPnhZBgvQ79OZA3Q9C3dsTTN9RsrvFpSB3MKBjIg4LhT5QWZAntzrL2tgZAjJh8STYCrsIjVqR0j9gM0yZAbW2mkWJUd78sCKxkKCWHKtgOt7kwZCzOwaxZAarvRFZCFSDizEAXpUhqZAOjRTbwRiP posts=6 title=no title_align=center description=no height=350px show_media=top show_thumbnail=no show_date=yes show_name=yes words=45 popup=yes grid=yes posts_displayed=page_only center_container=yes image_stack_animation=no colmn_width=310px images_align=center album_id=photo_stream image_width=250px image_height=250px space_between_photos=1px space_between_posts=10px show_follow_btn_where=below_title like_option_align=center like_box_width=500px hide_like_option=no hide_comments_popup=no loadmore=autoscroll loadmore_btn_maxwidth=300px loadmore_btn_margin=10px reviews_type_to_show=4 reviews_rating_format=3 overall_rating=yes remove_reviews_no_description=yes hide_see_more_reviews_link=yes play_btn_size=400px play_btn_visible=yes play_btn=yes scrollhorz_or_carousel=carousel slides_visible=55 slider_spacing=33px slider_margin=&quot;-6px auto 1px auto&quot; slider_speed=1000 slider_timeout=1000 slider_controls=arrows_above_feed slider_controls_text_color=#FFF slider_controls_bar_color=320px slider_controls_width=320px ]
                // [fts_twitter twitter_name=gopro tweets_count=6 twitter_height=240px cover_photo=yes stats_bar=yes show_retweets=yes show_replies=yes grid=yes search=sadfsdf popup=yes loadmore=button loadmore_count=5 loadmore_btn_maxwidth=300px loadmore_btn_margin=10px colmn_width=310px space_between_posts=10px]
                // [fts_instagram instagram_id=17841417310560005 hashtag=erwer type=business profile_wrap=yes search=top-media profile_photo=yes profile_stats=yes profile_name=yes profile_description=yes  access_token=IGQVJXeVNoMUNkeURQbFdobVljSm5MNkdHOW92LW1UU2I0SnZAEZAGk5Q0s2bUxIWkdoOXFyRkJyN2RlUjFjeURObGJrVjB6by1RV0xVUTQ5QWxiN203UnYzU3JYdm5CcWhRV3JUUjhn pics_count=6 width=240px height=450px popup=yes super_gallery=yes columns=5 force_columns=yes space_between_photos=1px icon_size=65px hide_date_likes_comments=yes loadmore=autoscroll loadmore_count=5 loadmore_btn_maxwidth=300px loadmore_btn_margin=10px]
                // [fts_youtube vid_count=23 youtube_name2=asas youtube_channelID2=jhgjgh youtube_singleVideoID=mnbmnb youtube_name=oiuuoouiuio youtube_playlistID=sadfsadfsadf youtube_playlistID2=hjkkhj large_vid=no large_vid_title=yes large_vid_description=yes thumbs_play_in_iframe=popup vids_in_row=3 omit_first_thumbnail=yes space_between_videos=1px force_columns=yes maxres_thumbnail_images=no wrap_single=right video_wrap_display_single=2 video_wrap_display_single=3 thumbs_wrap_color=#333 wrap=left video_wrap_display=2 comments_count=56 channel_id=erqwtwertwert loadmore=autoscroll loadmore_count=2 loadmore_btn_maxwidth=300px loadmore_btn_margin=10px]
                // [fts_mashup posts=12 social_network_posts=4 words=55 center_container=no height=450px background_color=#75a3ff show_social_icon=left show_media=top show_date=no show_name=no padding=20px facebook_name=1562664650673366 twitter_name=twittername hashtag=tytytyty instagram_search=top-media grid=yes instagram_type=business hashtag=asdfasdfasdf instagram_name=17841400646076739  channel_id=mnmnmnm playlist_id=vasdfbvbvb column_width=310px space_between_posts=10px]
                ?>
                <p>
                    <label><label><?php echo esc_html__( 'Paste your Old shortcode here and click the blue Convert button. This will map your old options to the new input fields.', 'feed_them_social' ); ?></label>
                        <input value="" />
                </p><div class="publishing-action" style="text-align: right;"><a href="#feed_setup" id="fts-convert-old-shortcode" class="button button-primary button-large"><?php echo esc_html__( 'Convert', 'feed_them_social' ); ?></a></div>

                <small>
                    for testing:<br/>
                    <br/>[fts_facebook hide_date_likes_comments=yes type=page id=1562664650673366 access_token=asasdf posts=6 title=no title_align=center description=no height=350px show_media=top show_thumbnail=no show_date=yes show_name=yes words=45 popup=yes grid=yes posts_displayed=page_only center_container=yes image_stack_animation=no colmn_width=310px images_align=center album_id=photo_stream image_width=250px image_height=250px space_between_photos=1px space_between_posts=10px show_follow_btn_where=below_title like_option_align=center like_box_width=500px hide_like_option=no hide_comments_popup=no loadmore=autoscroll loadmore_btn_maxwidth=300px loadmore_btn_margin=10px reviews_type_to_show=4 reviews_rating_format=3 overall_rating=yes remove_reviews_no_description=yes hide_see_more_reviews_link=yes play_btn_size=400px play_btn_visible=yes play_btn=yes scrollhorz_or_carousel=carousel slides_visible=55 slider_spacing=33px slider_margin=&quot;-6px auto 1px auto&quot; slider_speed=1000 slider_timeout=1000 slider_controls=arrows_above_feed slider_controls_text_color=#FFF slider_controls_bar_color=320px slider_controls_width=320px ]
                    <br/><br/>[fts_twitter twitter_name=gopro tweets_count=6 twitter_height=240px cover_photo=yes stats_bar=yes show_retweets=yes show_replies=yes grid=yes search=sadfsdf popup=yes loadmore=button loadmore_count=5 loadmore_btn_maxwidth=300px loadmore_btn_margin=10px colmn_width=310px space_between_posts=10px]
                    <br/><br/>[fts_instagram instagram_id=17841417310560005 hashtag=erwer type=business profile_wrap=yes search=top-media profile_photo=yes profile_stats=yes profile_name=yes profile_description=yes access_token=asdf pics_count=6 width=240px height=450px popup=yes super_gallery=yes columns=5 force_columns=yes space_between_photos=1px icon_size=65px hide_date_likes_comments=yes loadmore=autoscroll loadmore_count=5 loadmore_btn_maxwidth=300px loadmore_btn_margin=10px]
                    <br/><br/>[fts_youtube vid_count=23 youtube_name2=asas youtube_channelID2=jhgjgh youtube_singleVideoID=mnbmnb youtube_name=oiuuoouiuio youtube_playlistID=sadfsadfsadf youtube_playlistID2=hjkkhj large_vid=no large_vid_title=yes large_vid_description=yes thumbs_play_in_iframe=popup vids_in_row=3 omit_first_thumbnail=yes space_between_videos=1px force_columns=yes maxres_thumbnail_images=no wrap_single=right video_wrap_display_single=2 video_wrap_display_single=3 thumbs_wrap_color=#333 wrap=left video_wrap_display=2 comments_count=56 channel_id=erqwtwertwert loadmore=autoscroll loadmore_count=2 loadmore_btn_maxwidth=300px loadmore_btn_margin=10px]
                    <br/><br/>[fts_mashup posts=12 social_network_posts=4 words=55 center_container=no height=450px background_color=#75a3ff show_social_icon=left show_media=top show_date=no show_name=no padding=20px facebook_name=1562664650673366 twitter_name=twittername hashtag=tytytyty instagram_search=top-media grid=yes instagram_type=business hashtag=asdfasdfasdf instagram_name=17841400646076739  channel_id=mnmnmnm playlist_id=vasdfbvbvb column_width=310px space_between_posts=10px]

                </small>

                <?php
            }

            ?>
        </div>
        <?php
    }


    /**
     *  Shortcode Meta Box
     *
     *  copy & paste shortcode input box
     *
     * @param $object
     * @since 1.0.0
     */
    public function fts_shortcode_meta_box() {
        ?>
        <div class="ft-gallery-meta-wrap">
            <?php

            $gallery_id = isset( $_GET['post'] ) ? $_GET['post'] : '';

            $screen = get_current_screen();

            if ( 'edit.php?post_type=fts' === $screen->parent_file && 'add' === $screen->action ) {
                ?>
                <p>
                    <label><label><?php echo esc_html__( 'Save or Publish this Feed then you will be able to copy the shortcode.', 'feed_them_social' ); ?></label>
                </p>
                <?php
            } else {
                // Copy Shortcode
                ?>
                <p>
                    <label><label><?php echo esc_html__( 'Copy and Paste this shortcode to any page, post or widget.', 'feed_them_social' ); ?></label>
                        <input readonly="readonly" value="[feed_them_social cpt_id=<?php echo esc_html( $gallery_id ); ?>]" onclick="this.select();"/>
                </p>
                <?php
            }

            ?>
        </div>
        <?php
    }

    /**
     *  Format Attachment Title
     * Format the title for attachments to ensure awesome titles (options on settings page)
     *
     * @param $title
     * @param null  $attachment_id
     * @param null  $update_post
     * @return mixed|string
     * @since 1.0.0
     */
    public function fts_format_attachment_title( $title, $attachment_id = null, $update_post = null ) {

        $options     = get_option( 'fts_format_attachment_titles_options' );
        $cap_options = isset( $options['fts_cap_options'] ) ? $options['fts_cap_options'] : 'dont_alter';

        if ( ! empty( $attachment_id ) ) {
            $uploaded_post_id = get_post( $attachment_id );
            // $title = $uploaded_post_id->post_title;
        }

        /* Update post. */
        $char_array = array();
        if ( isset( $options['fts_fat_hyphen'] ) && $options['fts_fat_hyphen'] ) {
            $char_array[] = '-';
        }
        if ( isset( $options['fts_fat_underscore'] ) && $options['fts_fat_underscore'] ) {
            $char_array[] = '_';
        }
        if ( isset( $options['fts_fat_period'] ) && $options['fts_fat_period'] ) {
            $char_array[] = '.';
        }
        if ( isset( $options['fts_fat_tilde'] ) && $options['fts_fat_tilde'] ) {
            $char_array[] = '~';
        }
        if ( isset( $options['fts_fat_plus'] ) && $options['fts_fat_plus'] ) {
            $char_array[] = '+';
        }

        /* Replace chars with spaces, if any selected. */
        if ( ! empty( $char_array ) ) {
            $title = str_replace( $char_array, ' ', $title );
        }

        /* Trim multiple spaces between words. */
        $title = preg_replace( '/\s+/', ' ', $title );

        /* Capitalize Title. */
        switch ( $cap_options ) {
            case 'cap_all':
                $title = ucwords( $title );
                break;
            case 'cap_first':
                $title = ucfirst( strtolower( $title ) );
                break;
            case 'all_lower':
                $title = strtolower( $title );
                break;
            case 'all_upper':
                $title = strtoupper( $title );
                break;
            case 'dont_alter':
                /* Leave title as it is. */
                break;
        }

        // Return Clean Title otherwise update post!
        if ( 'true' !== $update_post ) {
            return esc_html( $title );
        }

        // add formatted title to the alt meta field
        if ( isset( $options['fts_fat_alt'] ) && $options['fts_fat_alt'] ) {
            update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $title ) );
        }

        // update the post
        $uploaded_post = array(
            'ID'         => sanitize_text_field( $attachment_id ),
            'post_title' => sanitize_text_field( $title ),
        );

        // add formatted title to the description meta field
        if ( isset( $options['fts_fat_description'] ) && $options['fts_fat_description'] ) {
            $uploaded_post['post_content'] = sanitize_text_field( $title );
        }

        // add formatted title to the caption meta field
        if ( isset( $options['fts_fat_caption'] ) && $options['fts_fat_caption'] ) {
            $uploaded_post['post_excerpt'] = sanitize_text_field( $title );
        }

        wp_update_post( $uploaded_post );

        return $title;
    }

    /**
     *  Duplicate Post As Draft
     * Function creates post duplicate as a draft and redirects then to the edit post screen
     *
     * @since 1.0.0
     */
    public function fts_duplicate_post_as_draft() {
        global $wpdb;
        if ( ! ( isset( $_GET['post'] ) || isset( $_POST['post'] ) || ( isset( $_REQUEST['action'] ) && 'fts_duplicate_post_as_draft' === $_REQUEST['action'] ) ) ) {
            wp_die( esc_html__( 'No Gallery to duplicate has been supplied!', 'feed_them_social' ) );
        }

        /*
         * Nonce verification
         */
        if ( ! isset( $_GET['duplicate_nonce'] ) || ! wp_verify_nonce( $_GET['duplicate_nonce'], basename( __FILE__ ) ) ) {
            return;
        }

        /*
         * get the original post id
         */
        $post_id = ( isset( $_GET['post'] ) ? absint( $_GET['post'] ) : absint( $_POST['post'] ) );
        /*
         * and all the original post data then
         */
        $post = get_post( $post_id );

        /*
         * if you don't want current user to be the new post author,
         * then change next couple of lines to this: $new_post_author = $post->post_author;
         */
        $current_user    = wp_get_current_user();
        $new_post_author = $current_user->ID;

        /*
         * if post data exists, create the post duplicate
         */
        if ( isset( $post ) && null !== $post ) {

            /*
             * new post data array
             */
            $args = array(
                'comment_status' => $post->comment_status,
                'ping_status'    => $post->ping_status,
                'post_author'    => $new_post_author,
                'post_content'   => $post->post_content,
                'post_excerpt'   => $post->post_excerpt,
                'post_name'      => $post->post_name,
                'post_parent'    => $post->post_parent,
                'post_password'  => $post->post_password,
                'post_status'    => 'draft',
                'post_title'     => $post->post_title,
                'post_type'      => $post->post_type,
                'to_ping'        => $post->to_ping,
                'menu_order'     => $post->menu_order,
            );

            /*
             * insert the post by wp_insert_post() function
             */
            $new_post_id = wp_insert_post( $args );

            /*
             * get all current post terms ad set them to the new post draft
             */
            $taxonomies = get_object_taxonomies( $post->post_type ); // returns array of taxonomy names for post type, ex array("category", "post_tag");
            foreach ( $taxonomies as $taxonomy ) {
                $post_terms = wp_get_object_terms( $post_id, $taxonomy, array( 'fields' => 'slugs' ) );
                wp_set_object_terms( $new_post_id, $post_terms, $taxonomy, false );
            }

            /*
             * duplicate all post meta just in two SQL queries
             */
            $post_meta_results = $wpdb->get_results( $wpdb->prepare( "SELECT meta_key, meta_value FROM $wpdb->postmeta WHERE post_id = %d", $post_id ) );

            if ( 0 !== count( $post_meta_results ) ) {
                foreach ( $post_meta_results as $meta_info ) {
                    if ( '_wp_old_slug' === $meta_info->meta_value ) {
                        continue;
                    }
                    $wpdb->query(
                        $wpdb->prepare(
                            "INSERT INTO $wpdb->postmeta ( post_id, meta_key, meta_value ) VALUES ( %d, %s, %s )",
                            $new_post_id,
                            $meta_info->meta_key,
                            $meta_info->meta_value
                        )
                    );
                }
            }

            /*
             * finally, redirect to the edit post screen for the new draft
             */
            wp_safe_redirect( admin_url( 'post.php?action=edit&post=' . $new_post_id ) );
            exit;
        }

        wp_die( esc_html__( 'Gallery duplication failed, could not find original Gallery: ' . $post_id, 'feed_them_social' ) );
    }

    /**
     * Metabox Specific Form Inputs
     *
     * This adds to the output of the metabox output forms for settings_html_form function in the Metabox Settings class.
     *
     * @param $params
     * @param $input_option
     * @return
     * @since 1.1.6
     */
    public function metabox_specific_form_inputs( $params ) {
        // Gallery ID.
        $gallery_id = isset( $_GET['post'] ) ? $_GET['post'] : '';
        // Gallery Options.
        $gallery_options_returned = $this->feed_functions->get_feed_settings( $gallery_id );
        // Option Info.
        $option = $params['input_option'];

        $output = '';

        if ( isset( $option['option_type'] ) ) {
            switch ( $option['option_type'] ) {

                // Checkbox for image sizes used so you can check the image sizes you want to be water marked after you save the page.
                case 'checkbox-dynamic-image-sizes':
                    $final_value_images = isset( $gallery_options_returned['ft_watermark_image_sizes']['image_sizes'] ) ? $gallery_options_returned['ft_watermark_image_sizes']['image_sizes'] : array();
                    $output            .= '<div class="clear"></div>';

                    global $_wp_additional_image_sizes;

                    $sizes = array();
                    foreach ( get_intermediate_image_sizes() as $_size ) {
                        if ( in_array( $_size, array( 'thumbnail', 'medium', 'medium_large', 'large' ) ) ) {
                            $sizes[ $_size ]['width']  = get_option( "{$_size}_size_w" );
                            $sizes[ $_size ]['height'] = get_option( "{$_size}_size_h" );
                            $sizes[ $_size ]['crop']   = (bool) get_option( "{$_size}_crop" );
                        } elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
                            $sizes[ $_size ] = array(
                                'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
                                'height' => $_wp_additional_image_sizes[ $_size ]['height'],
                                'crop'   => $_wp_additional_image_sizes[ $_size ]['crop'],
                            );
                        }
                        $output .= '<label for="' . esc_attr( $_size ) . '"><input type="checkbox" val="' . esc_attr( $_size ) . '" name="ft_watermark_image_sizes[image_sizes][' . esc_attr( $_size ) . ']" id="' . esc_attr( $option['id'] ) . '-' . esc_attr( $_size ) . '" ' . ( array_key_exists( $_size, $final_value_images ) ? ' checked="checked"' : '' ) . '/>' . esc_html( $_size ) . ' ' . esc_html( $sizes[ $_size ]['width'] ) . ' x ' . esc_html( $sizes[ $_size ]['height'] ) . '</label><br/>';

                    }
                    $output .= '<label for="full"><input type="checkbox" val="full" id="ft_watermark_image_-full" name="ft_watermark_image_sizes[image_sizes][full]" ' . ( array_key_exists( 'full', $final_value_images ) ? 'checked="checked"' : '' ) . '/>full</label><br/>';
                    $output .= '<br/><br/>';
                    // TESTING AREA
                    // echo $final_value_images;
                    // echo '<pre>';
                    // print_r($sizes);
                    // echo '</pre>';
                    break;

            }
        }

        return $output;
    }

    /**
     * Duplicate Post Link
     *
     * Add the duplicate link to action list for post_row_actions
     *
     * @param $actions
     * @param $post
     * @return mixed
     * @since 1.0.0
     */
    public function fts_duplicate_post_link( $actions, $post ) {
        // make sure we only show the duplicate gallery link on our pages
        if ( current_user_can( 'edit_posts' ) && 'fts' === $_GET['post_type'] ) {
            $actions['duplicate'] = '<a id="ft-gallery-duplicate-action" href="' . esc_url( wp_nonce_url( 'admin.php?action=fts_duplicate_post_as_draft&post=' . $post->ID, basename( __FILE__ ), 'duplicate_nonce' ) ) . '" title="Duplicate this item" rel="permalink">' . esc_html__( 'Duplicate', 'feed_them_social' ) . '</a>';
        }

        return $actions;
    }

    /**
     *  Duplicate Post ADD Duplicate Post Button
     *
     *  Add a button in the post/page edit screen to create a clone
     *
     * @since 1.0.0
     */
    public function fts_duplicate_post_add_duplicate_post_button() {
        $current_screen = get_current_screen();
        $verify         = isset( $_GET['post_type'] ) ? $_GET['post_type'] : '';
        // check to make sure we are not on a new fts post, because what is the point of duplicating a new one until we have published it?
        if ( 'fts' === $current_screen->post_type && 'fts' !== $verify ) {
            $id = $_GET['post'];
            ?>
            <div id="ft-gallery-duplicate-action">
                <a href="<?php echo esc_url( wp_nonce_url( 'admin.php?action=fts_duplicate_post_as_draft&post=' . $id, basename( __FILE__ ), 'duplicate_nonce' ) ); ?>"
                   title="Duplicate this item"
                   rel="permalink"><?php esc_html_e( 'Duplicate Gallery', 'feed_them_social' ); ?></a>
            </div>
            <?php
        }
    }
} ?>
