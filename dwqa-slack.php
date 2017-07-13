<?php
/**
 * Plugin Name: DWQA Slack Integration
 * Description: Send notifications of new questions and answers to a Slack channel.
 * Author: DesignWall
 * Author URI: https://www.designwall.com
 * Version: 1.0.0
 * Plugin URI: https://www.designwall.com/wordpress/dwqa-extensions/dwqa-slack/
 * License: GNU GPLv2+
 */

add_action( 'dwqa_add_answer',  'dwqa_slack_integration', 20 );
add_action( 'dwqa_add_question',  'dwqa_slack_integration', 20 );
add_action( 'dwqa_add_comment', 'dwqa_comment_slack_integration', 20 );
add_action( 'plugins_loaded', 'dwqa_slack_load_textdomain' );

/**
 * Plugin load text domain
 */
function dwqa_slack_load_textdomain() {
	$locale = get_locale();
	$mo = 'dwqa-slack-' . $locale . '.mo';
	
	load_textdomain( 'dwqa-slack', WP_LANG_DIR . '/dwqa-slack/' . $mo );
	load_textdomain( 'dwqa-slack', plugin_dir_path( __FILE__ ) . 'languages/' . $mo );
	load_plugin_textdomain( 'dwqa-slack' );
}

/**
 * Intergration with new question and answer
 *
 * @param int $id
 */
function dwqa_slack_integration( $id ) {
	
	if ( 'dwqa-answer' == get_post_type( $id ) ) {
		$answer = get_option( 'dwqa_slack_answer_notify', 'on' );
		if ( $answer !== 'on' ) {
			return;
		}

		$question_id = get_post_meta( $id, '_question', true );
		$link = get_permalink( $question_id ) . '#answer-' . $id;
		$type = 'answer';
	} else {
		$question = get_option( 'dwqa_slack_question_notify', 'on' );
		if ( $question !== 'on' ) {
			return;
		}

		$link = get_permalink( $id );
		$type = 'question';
	}

	$link = htmlspecialchars( $link );
	$excerpt = get_post_field( 'post_content', $id );
	$excerpt = wp_trim_words( $excerpt );

	if ( 500 < strlen( $excerpt ) ) {
		$excerpt = substr( $excerpt, 0, 500 );
	}

	dwqa_send_message( $link, $excerpt, $type );
}

/**
 * Intergration with new comment
 *
 * @param int $id
 */
function dwqa_comment_slack_integration( $id ) {
	$comment = get_option( 'dwqa_slack_comment_notify', 'on' );
	if ( $comment !== 'on' ) {
		return;
	}

	$comment = get_comment( $id );
	$question_id = absint( $comment->comment_post_ID );
	
	if ( 'dwqa-answer' == get_post_type( $question_id ) ) {
		$question_id = get_post_meta( $question_id, '_question', true );
		$link = get_permalink( $question_id ) . '#answer-' . $id;
	} else {
		$link = get_permalink( $question_id );
	}

	$excerpt = wp_trim_words( $comment->comment_content );

	if ( 500 < strlen( $excerpt ) ) {
		$excerpt = substr( $excerpt, 0, 500 );
	}

	dwqa_send_message( $link, $excerpt, 'comment' );
}

/**
 * Send message to BOT Slack
 *
 * @param string $link link webhook
 * @param string $content post content
 * @param string $type notify type
 */
function dwqa_send_message( $link, $content, $type = 'question' ) {
	$url = get_option( 'dwqa_slack_webhook', false );

	if ( $type == 'question' ) {
		$text = __( 'Question', 'dwqa-slack' );
	} else if ( $type == 'answer' ) {
		$text = __( 'Answer', 'dwqa-slack' );
	} else {
		$text = __( 'Comment', 'dwqa-slack' );
	}

	if ( $url ) {
		$payload = array(
			'text'        => sprintf( __( 'New %s', 'dwqa-slack' ), $text ),
			'attachments' => array(
				'fallback' => $link,
				'color'    => '#ff000',
				'fields'   => array(
					'title' => $link,
					'value' => $link,
					'text'  => $content,
				)
			),
		);
		$output  = 'payload=' . json_encode( $payload );

		$response = wp_remote_post( $url, array(
			'body' => $output,
		) );

		/**
		 * Runs after the data is sent.
		 *
		 * @param array $response Response from server.
		 *
		 * @since 1.0.0
		 */
		do_action( 'dwqa_slack_integration_post_send', $response );

		return $response;
	}
}

/**
 * Load admin class if admin
 *
 * @since 1.0.0
 */
if ( is_admin() ) {
	new dwqa_slack_integration_admin();
}

class dwqa_slack_integration_admin {

	function __construct() {
		add_action( 'admin_menu', array( $this, 'menu' ) );
	}

	/**
	 * Add the menu
	 *
	 * @since 1.0.0
	 */
	function menu() {
		add_submenu_page(
			'edit.php?post_type=dwqa-question',
			__( 'Slack Integration', 'dwqa-slack' ),
			__( 'Slack Integration', 'dwqa-slack' ),
			'manage_options',
			'dwqa_slack',
			array( $this, 'page' )
		);
	}

	/**
	 * Render admin page and handle saving.
	 *
	 * @TODO Use AJAX for saving
	 *
	 * @since 1.0.0
	 *
	 * @return string the admin page.
	 */
	function page() {
		echo $this->instructions();
		echo $this->form();
	}

	/**
	 * Admin form
	 *
	 * @since 1.0.0
	 *
	 * @return string The form.
	 */
	function form() {
		if ( isset( $_POST['_wpnonce'] ) && wp_verify_nonce( sanitize_text_field( $_POST['_wpnonce'] ), 'dwqa-slack' ) ) {
			update_option( 'dwqa_slack_webhook', esc_url( $_POST['slack-hook'] ) );

			if ( isset( $_POST['slack-notify-question'] ) ) {
				update_option( 'dwqa_slack_question_notify', 'on' );
			} else {
				update_option( 'dwqa_slack_question_notify', 'off' );
			}

			if ( isset( $_POST['slack-notify-answer'] ) ) {
				update_option( 'dwqa_slack_answer_notify', 'on' );
			} else {
				update_option( 'dwqa_slack_answer_notify', 'off' );
			}

			if ( isset( $_POST['slack-notify-comment'] ) ) {
				update_option( 'dwqa_slack_comment_notify', 'on' );
			} else {
				update_option( 'dwqa_slack_comment_notify', 'off' );
			}
		} else {

		}

		$slack_hook = get_option( 'dwqa_slack_webhook', '' );
		$question = get_option( 'dwqa_slack_question_notify', 'on' );
		$answer = get_option( 'dwqa_slack_answer_notify', 'on' );
		$comment = get_option( 'dwqa_slack_comment_notify', 'on' );

		?>
		<form method="post">
			<table class="form-table">
				<tr>
					<th><?php _e( 'Webhook URL', 'dwqa-slack' ) ?></th>
					<td><input type="text" name="slack-hook" id="slack-hook" value="<?php echo esc_url( $slack_hook ) ?>"></td>
				</tr>
				<tr>
					<th><?php _e( 'Enable Notify For', 'dwqa-slack' ) ?></th>
					<td><input type="checkbox" name="slack-notify-question" id="slack-notify-for-question" <?php checked( 'on', $question ) ?>><span class="description"><?php _e( 'Question', 'dwqa-slack' ) ?></span></td>
				</tr>
				<tr>
					<th></th>
					<td><input type="checkbox" name="slack-notify-answer" id="slack-notify-for-answer" <?php checked( 'on', $answer ) ?>><span class="description"><?php _e( 'Answer', 'dwqa-slack' ) ?></td>
				</tr>
				<tr>
					<th></th>
					<td><input type="checkbox" name="slack-notify-comment" id="slack-notify-for-comment" <?php checked( 'on', $comment ) ?>><span class="description"><?php _e( 'Comment', 'dwqa-slack' ) ?></td>
				</tr>
			</table>
			<?php wp_nonce_field( 'dwqa-slack' ); ?>
			<input type="submit" class="button button-primary" value="<?php _e( 'Save Change', 'dwqa-slack' ) ?>">
		</form>
		<?php
	}

	/**
	 * Show instructions.
	 *
	 * @since 1.0.0
	 *
	 * @return string The instructions.
	 */
	function instructions() {
		$header = '<h3>' . __( 'Instructions:', 'dwqa-slack' ) .'</h3>';
		$instructions = array(
			__( 'Go To https://<your-team-name>.slack.com/services/new/incoming-webhook', 'dwqa-slack' ),
			__( ' Create a new webhook', 'dwqa-slack' ),
			__( 'Set a channel to receive the notifications', 'dwqa-slack' ),
			__( 'Copy the URL for the webhook	', 'dwqa-slack' ),
			__( 'Past the URL into the field below and click submit', 'dwqa-slack' ),
		);

		return $header. "<ol><li>" .implode( "</li><li>", $instructions ) . "</li></ol>";

	}

}
