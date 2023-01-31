<?php
 /**
 * Feed Them Social - Youtube Access Functions
 *
 * This page is used to retrieve and set access tokens for Youtube.
 *
 * @package     feedthemsocial
 * @copyright   Copyright (c) 2012-2022, SlickRemix
 * @license     http://opensource.org/licenses/gpl-2.0.php GNU Public License
 * @since       1.0.0
 */

namespace feedthemsocial;

// Exit if accessed directly!
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Class Youtube_Access_Functions
 *
 * @package feedthemsocial
 * @since 4.0.0
 */
class Youtube_Access_Functions {

	/**
	 * Feed Functions
	 *
	 * The Feed Functions Class
	 *
	 * @var object
	 */
	public $feed_functions;

	/**
	 * Data Protection
	 *
	 * Data Protection Class for encryption.
	 *
	 * @var object
	 */
	public $data_protection;

	/**
	 * Construct
	 *
	 * Youtube Style Options Page constructor.
	 *
	 * @since 4.0.0
	 */
	public function __construct( $feed_functions, $data_protection ) {
		// Feed Functions.
		$this->feed_functions = $feed_functions;

		// Data Protection.
		$this->data_protection = $data_protection;
	}

	/**
	 *  Get Access Token Button
     *
     * @param $feed_cpt_id integer Feed CPT ID
	 * @since 4.0.0
	 */
	public function get_access_token_button( $feed_cpt_id ) {

        $post_url = add_query_arg( array(
            'post' => $feed_cpt_id,
        ), admin_url( 'post.php' ) );

        $youtube_api_key        = $this->feed_functions->get_feed_option( $feed_cpt_id, 'youtube_custom_api_token' );
        $youtube_refresh_token  = isset( $_GET['code'], $_GET['feed_type']  ) && 'youtube' === $_GET['feed_type'] ? sanitize_text_field( $_GET['refresh_token'] ) : $this->feed_functions->get_feed_option( $feed_cpt_id, 'youtube_custom_refresh_token' );
        $youtube_access_token   = isset( $_GET['code'], $_GET['feed_type']  ) && 'youtube' === $_GET['feed_type'] ? sanitize_text_field( $_GET['code'] ) : $this->feed_functions->get_feed_option( $feed_cpt_id, 'youtube_custom_access_token' );
        $expiration_time        = isset( $_GET['code'], $_GET['feed_type']  ) && 'youtube' === $_GET['feed_type'] ? sanitize_text_field( $_GET['expires_in'] ) : $this->feed_functions->get_feed_option( $feed_cpt_id, 'youtube_custom_token_exp_time' );

        ?>
        <script>
            jQuery(document).ready(function ($) {

                <?php if ( !empty( $_GET['code'] ) && isset( $_GET['feed_type'] ) && 'youtube' === $_GET['feed_type'] ) {?>
                    $('#youtube_custom_refresh_token').val('');
                    $('#youtube_custom_refresh_token').val($('#youtube_custom_refresh_token').val() + '<?php echo esc_js( $youtube_refresh_token ); ?>');

                    $('#youtube_custom_access_token').val('');
                    $('#youtube_custom_access_token').val($('#youtube_custom_access_token').val() + '<?php echo sanitize_text_field( $youtube_access_token ); ?>');

                    $('#youtube_custom_token_exp_time').val('');
                    // Set the time * 1000 because js uses milliseconds not seconds and that is what youtube gives us is a 3600 seconds of time
                    $('#youtube_custom_token_exp_time').val($('#youtube_custom_token_exp_time').val() + <?php echo strtotime( '+' . $expiration_time . ' seconds' ) ?> * 1000 );

                    setTimeout(
                        function(){
                            fts_ajax_cpt_save_token();
                        },
                        500
                    );

                <?php } ?>
            });
        </script>

        <?php

        if ( isset( $youtube_api_key ) && ! empty( $youtube_api_key ) ) {
            $youtube_api_key_or_token = 'key=' . $youtube_api_key;
        } elseif ( isset( $youtube_api_key ) && empty( $youtube_api_key ) && isset( $youtube_access_token ) && ! empty( $youtube_access_token ) ) {
            $youtube_api_key_or_token = 'access_token=' . $youtube_access_token;
        } else {
            $youtube_api_key_or_token = '';
        }

        if( !empty( $youtube_api_key ) || !empty( $youtube_access_token ) ) {


           // $youtube_user_id_data = esc_url_raw( 'https://www.googleapis.com/youtube/v3/search?pageToken=' . $videos->nextPageToken . '&part=snippet&channelId=' . $saved_feed_options['youtube_channelID'] . '&order=date&maxResults=' . $vid_count . '&' . $youtube_api_key_or_token );

            $youtube_user_id_data = esc_url_raw( 'https://www.googleapis.com/youtube/v3/channels?part=contentDetails&forUsername=gopro&' . $youtube_api_key_or_token );
            // echo '$youtube_user_id_data';
            // echo $youtube_user_id_data;

            // Get Data for Youtube!
            $response = wp_remote_fopen( $youtube_user_id_data );
            // Error Check!
            $test_app_token_response = json_decode( $response );

           // print_r( $test_app_token_response );
        }
            echo sprintf(
                esc_html__( '%1$sLogin and Get my Access Token %2$s', 'feed-them-social' ),
                '<div class="fts-clear fts-token-spacer"></div><a href="' . esc_url( 'https://www.slickremix.com/youtube-token/?redirect_url=' . $post_url ) . '" class="fts-youtube-get-access-token">',
                '</a>'
            );
            ?>

        <div class="fts-settings-does-not-work-wrap">
            <span class="fts-admin-token-settings"><?php esc_html_e( 'Settings', 'feed-them-social' ); ?></span>
            <a href="javascript:;" class="fts-admin-button-no-work" onclick="fts_beacon_support_click()"><?php esc_html_e( 'Not working?', 'feed-them-social' ); ?></a>
        </div>

            <?php
            $expiration_time = $this->feed_functions->get_feed_option( $feed_cpt_id, 'youtube_custom_token_exp_time' );
           // echo $expiration_time;
           // echo ' asdfasdfasdf ';

            // Test Liner!
            if ( time() < $expiration_time && empty( $youtube_api_key ) ) {
                ?>
                <script>

                    var countDownDate = new Date( <?php echo esc_js( $expiration_time ); ?> );

                    console.log(countDownDate);

                    // Update the count down every 1 second
                    var x = setInterval(function () {

                        // Get todays date and time
                        var now = new Date().getTime();

                        // console.log(now);

                        // Find the distance between now an the count down date
                        var distance = countDownDate - now;

                        // Time calculations for days, hours, minutes and seconds
                        var minutes = Math.floor((distance % (1000 * 60 * 60)) / (1000 * 60));
                        var seconds = Math.floor((distance % (1000 * 60)) / 1000);

                        jQuery('.fts-tab-content1-youtube.fts-token-wrap .fts-exp-time-wrapper .feed-them-social-admin-input-label').append('<br/><span id="fts-timer"></span>');
                        jQuery('.fts-tab-content1-youtube.fts-token-wrap #fts-timer').html( minutes + "m " + seconds + "s " );

                        // If the count down is finished, write some text
                        if (distance < 0) {
                            clearInterval(x);
                            jQuery('.fts-tab-content1-youtube.fts-token-wrap .fts-success').fadeIn();
                            jQuery('.fts-tab-content1-youtube.fts-token-wrap #fts-timer').html( 'Token Expired, refresh page to get new a token.' )
                            }
                    }, 1000);

               </script>
               <?php
           }

           if ( time() > $expiration_time && empty( $youtube_api_key ) ) {
               // LEAVING OFF HERE NEED TO FIGURE OU WHY THIS IS NOT REFRESHING PROPER.
               // COPY CODE FROM INSTAGRAM TO SIMPLIFY THE JS ABOVE TOO.
               // SRL: 5-6-22: using API token till I get this figured out.
               // I also made the refresh token option on fts.com error. MUST undo error there to get this to work.
               // Right now though it is getting a refresh after a few seconds from a shit ton of people and it's made the
               // app reach it's limit... access tokens blow for youtube. API key is the best way still.
               $this->feed_functions->feed_them_youtube_refresh_token( $feed_cpt_id );
            }   ?>

            <div class="clear"></div>
            <div class="fts-token-wrap" id="fts-youtube-token-wrap"><?php

                $user_id = !empty( $test_app_token_response ) ? $test_app_token_response : '';
                $error_response = isset( $test_app_token_response->error->errors[0]->message ) ? 'true' : 'false';

                // Error Check!
                if ( 'false' === $error_response && ! empty( $youtube_api_key ) || 'false' === $error_response && ! empty( $youtube_access_token ) && empty( $youtube_api_key ) ) {

                    if( 'combine-streams-feed-type' === $this->feed_functions->get_feed_option( $feed_cpt_id, 'feed_type' ) ){
                        echo sprintf(
                            esc_html__( '%1$s%2$sCreate Combined Feed%3$s', 'feed-them-social' ),
                            '<div id="fts-combined-youtube-success" class="fts-successful-api-token fts-special-working-wrap" style="display: none">',
                            '<a class="fts-youtube-combine-successful-api-token fts-success-token-content fts-combine-successful-api-token" href="#combine_streams_feed">',
                            '</a></div>'
                        );
                    }
                    else {
                        echo sprintf(
                            esc_html__( '%1$s%2$sCreate YouTube Feed%3$s', 'feed-them-social' ),
                            '<div class="fts-successful-api-token fts-special-working-wrap" >',
                            '<a class="fts-youtube-successful-api-token fts-success-token-content" href="#youtube_feed">',
                            '</a></div>'
                        );
                    }
                }
                elseif ( empty( $youtube_api_key ) && 'true' === $error_response && ! empty( $youtube_access_token ) ) {
                    echo sprintf(
                        esc_html__( '%1$sYouTube responded with: %2$s %3$s ', 'feed-them-social' ),
                        '<div class="fts-failed-api-token">',
                        wp_kses(
                            $user_id->error->errors[0]->message,
                            array(
                                'small'  => array(),
                            )
                        ),
                        '</div>'
                    );
                }
                elseif ( 'true' === $error_response && ! empty( $youtube_api_key ) ) {
                    echo sprintf(
                        esc_html__( '%1$sYouTube responded with: %2$s %3$s ', 'feed-them-social' ),
                        '<div class="fts-failed-api-token">',
                        esc_html( $user_id->error->errors[0]->message ),
                        '</div>'
                    );
                }
                ?>
            </div>
        <?php
    }
}//end class