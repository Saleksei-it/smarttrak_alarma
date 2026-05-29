<?php
/**
 * Main plugin class.
 *
 * @package Smarttrak_Alarma
 */

defined( 'ABSPATH' ) || exit;

final class Smarttrak_Alarma {

	/**
	 * Singleton instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Plugin options key.
	 *
	 * @var string
	 */
	private $option_key = 'smarttrak_alarma_options';

	/**
	 * Get instance.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Activation defaults.
	 *
	 * @return void
	 */
	public static function activate(): void {
		$defaults = self::default_options();
		$current  = get_option( 'smarttrak_alarma_options', [] );

		if ( ! is_array( $current ) ) {
			$current = [];
		}

		update_option( 'smarttrak_alarma_options', array_merge( $defaults, $current ) );
	}

	/**
	 * Constructor.
	 *
	 * @return void
	 */
	private function __construct() {
		add_action( 'admin_menu', [ $this, 'register_admin_menu' ] );
		add_action( 'admin_init', [ $this, 'register_settings' ] );
		add_action( 'admin_notices', [ $this, 'render_admin_notices' ] );
		add_action( 'admin_post_smarttrak_alarma_fetch_chat', [ $this, 'handle_fetch_chat_id' ] );

		add_action( 'wp_enqueue_scripts', [ $this, 'enqueue_assets' ] );
		add_shortcode( 'smarttrak_alarma_button', [ $this, 'render_button_shortcode' ] );
		add_action( 'wp_footer', [ $this, 'render_global_callback_markup' ] );

		add_action( 'wp_ajax_smarttrak_alarma_callback', [ $this, 'handle_callback_form' ] );
		add_action( 'wp_ajax_nopriv_smarttrak_alarma_callback', [ $this, 'handle_callback_form' ] );

		add_filter( 'srfm_form_submission_response', [ $this, 'handle_contact_form_alarm' ], 10, 3 );
	}

	/**
	 * Default options.
	 *
	 * @return array<string,mixed>
	 */
	private static function default_options(): array {
		return [
			'bot_token'             => '8741922008:AAERpQRDQCCJA3_5Y7MDyLNvYX-MMCvqPa0',
			'chat_id'               => '',
			'contact_form_id'       => 1793,
			'owner_email'           => get_option( 'admin_email', '' ),
			'callback_enabled'      => 1,
			'floating_button_label' => 'Передзвоніть мені',
			'popup_title'           => 'Замовити зворотний дзвінок',
		];
	}

	/**
	 * Get options merged with defaults.
	 *
	 * @return array<string,mixed>
	 */
	private function get_options(): array {
		$options = get_option( $this->option_key, [] );

		if ( ! is_array( $options ) ) {
			$options = [];
		}

		return array_merge( self::default_options(), $options );
	}

	/**
	 * Register settings page.
	 *
	 * @return void
	 */
	public function register_admin_menu(): void {
		add_options_page(
			'Smarttrak Alarma',
			'Smarttrak Alarma',
			'manage_options',
			'smarttrak-alarma',
			[ $this, 'render_settings_page' ]
		);
	}

	/**
	 * Register settings.
	 *
	 * @return void
	 */
	public function register_settings(): void {
		register_setting(
			'smarttrak_alarma_settings',
			$this->option_key,
			[
				'type'              => 'array',
				'sanitize_callback' => [ $this, 'sanitize_options' ],
				'default'           => self::default_options(),
			]
		);
	}

	/**
	 * Sanitize settings.
	 *
	 * @param array<string,mixed> $input Raw input.
	 * @return array<string,mixed>
	 */
	public function sanitize_options( $input ): array {
		$defaults = self::default_options();

		return [
			'bot_token'             => isset( $input['bot_token'] ) ? sanitize_text_field( wp_unslash( $input['bot_token'] ) ) : $defaults['bot_token'],
			'chat_id'               => isset( $input['chat_id'] ) ? sanitize_text_field( wp_unslash( $input['chat_id'] ) ) : '',
			'contact_form_id'       => isset( $input['contact_form_id'] ) ? absint( $input['contact_form_id'] ) : $defaults['contact_form_id'],
			'owner_email'           => isset( $input['owner_email'] ) ? sanitize_email( wp_unslash( $input['owner_email'] ) ) : $defaults['owner_email'],
			'callback_enabled'      => empty( $input['callback_enabled'] ) ? 0 : 1,
			'floating_button_label' => isset( $input['floating_button_label'] ) ? sanitize_text_field( wp_unslash( $input['floating_button_label'] ) ) : $defaults['floating_button_label'],
			'popup_title'           => isset( $input['popup_title'] ) ? sanitize_text_field( wp_unslash( $input['popup_title'] ) ) : $defaults['popup_title'],
		];
	}

	/**
	 * Render settings page.
	 *
	 * @return void
	 */
	public function render_settings_page(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		$options = $this->get_options();
		$bot_url = 'https://t.me/Smarttrak_my_bot';
		?>
		<div class="wrap">
			<h1>Smarttrak Alarma</h1>
			<p>Плагін надсилає аларми у Telegram і на пошту.</p>
			<p>Щоб Telegram почав працювати, відкрий бота, натисни Start, потім натисни кнопку нижче.</p>

			<?php if ( empty( $options['chat_id'] ) ) : ?>
				<div class="notice notice-warning"><p>Chat ID ще не збережений. Telegram-алярми підуть одразу після підключення чату.</p></div>
			<?php else : ?>
				<div class="notice notice-success"><p>Chat ID підключений: <?php echo esc_html( $options['chat_id'] ); ?></p></div>
			<?php endif; ?>

			<p><a class="button button-secondary" href="<?php echo esc_url( $bot_url ); ?>" target="_blank" rel="noopener noreferrer">Відкрити бота</a></p>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="margin:16px 0;">
				<?php wp_nonce_field( 'smarttrak_alarma_fetch_chat' ); ?>
				<input type="hidden" name="action" value="smarttrak_alarma_fetch_chat">
				<button type="submit" class="button button-primary">Підтягнути Chat ID з бота</button>
			</form>

			<form method="post" action="options.php">
				<?php settings_fields( 'smarttrak_alarma_settings' ); ?>
				<table class="form-table" role="presentation">
					<tr>
						<th scope="row"><label for="smarttrak-alarma-bot-token">Bot Token</label></th>
						<td><input id="smarttrak-alarma-bot-token" type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[bot_token]" value="<?php echo esc_attr( $options['bot_token'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="smarttrak-alarma-chat-id">Chat ID</label></th>
						<td><input id="smarttrak-alarma-chat-id" type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[chat_id]" value="<?php echo esc_attr( $options['chat_id'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="smarttrak-alarma-contact-form-id">ID форми контактів</label></th>
						<td><input id="smarttrak-alarma-contact-form-id" type="number" class="small-text" name="<?php echo esc_attr( $this->option_key ); ?>[contact_form_id]" value="<?php echo esc_attr( (string) $options['contact_form_id'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="smarttrak-alarma-owner-email">Пошта власника</label></th>
						<td><input id="smarttrak-alarma-owner-email" type="email" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[owner_email]" value="<?php echo esc_attr( $options['owner_email'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="smarttrak-alarma-button-label">Текст кнопки</label></th>
						<td><input id="smarttrak-alarma-button-label" type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[floating_button_label]" value="<?php echo esc_attr( $options['floating_button_label'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row"><label for="smarttrak-alarma-popup-title">Заголовок popup</label></th>
						<td><input id="smarttrak-alarma-popup-title" type="text" class="regular-text" name="<?php echo esc_attr( $this->option_key ); ?>[popup_title]" value="<?php echo esc_attr( $options['popup_title'] ); ?>"></td>
					</tr>
					<tr>
						<th scope="row">Плаваюча кнопка</th>
						<td><label><input type="checkbox" name="<?php echo esc_attr( $this->option_key ); ?>[callback_enabled]" value="1" <?php checked( (int) $options['callback_enabled'], 1 ); ?>> Увімкнути кнопку на сайті</label></td>
					</tr>
				</table>
				<?php submit_button( 'Зберегти' ); ?>
			</form>
			<p>Шорткод кнопки: <code>[smarttrak_alarma_button]</code></p>
		</div>
		<?php
	}

	/**
	 * Render admin notices for chat connection.
	 *
	 * @return void
	 */
	public function render_admin_notices(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			return;
		}

		if ( ! isset( $_GET['page'] ) || 'smarttrak-alarma' !== sanitize_text_field( wp_unslash( $_GET['page'] ) ) ) {
			return;
		}

		$status = isset( $_GET['smarttrak_alarma_chat'] ) ? sanitize_text_field( wp_unslash( $_GET['smarttrak_alarma_chat'] ) ) : '';

		if ( '' === $status ) {
			return;
		}

		$class   = 'notice notice-info';
		$message = '';

		if ( 'saved' === $status ) {
			$class   = 'notice notice-success';
			$message = 'Chat ID знайдено і збережено.';
		} elseif ( 'not_found' === $status ) {
			$class   = 'notice notice-warning';
			$message = 'Бот ще не бачить жодного чату. Відкрий бота і натисни Start.';
		} elseif ( 'missing_token' === $status ) {
			$class   = 'notice notice-error';
			$message = 'Спочатку збережи Bot Token.';
		} elseif ( 'request_error' === $status ) {
			$class   = 'notice notice-error';
			$message = 'Не вдалося звернутися до Telegram API.';
		}

		if ( '' === $message ) {
			return;
		}
		?>
		<div class="<?php echo esc_attr( $class ); ?>"><p><?php echo esc_html( $message ); ?></p></div>
		<?php
	}

	/**
	 * Load assets.
	 *
	 * @return void
	 */
	public function enqueue_assets(): void {
		$options = $this->get_options();

		if ( empty( $options['callback_enabled'] ) && ! is_singular() ) {
			return;
		}

		wp_enqueue_style(
			'smarttrak-alarma',
			SMARTTRAK_ALARMA_URL . 'assets/css/smarttrak-alarma.css',
			[],
			SMARTTRAK_ALARMA_VERSION
		);

		wp_enqueue_script(
			'smarttrak-alarma',
			SMARTTRAK_ALARMA_URL . 'assets/js/smarttrak-alarma.js',
			[],
			SMARTTRAK_ALARMA_VERSION,
			true
		);

		wp_localize_script(
			'smarttrak-alarma',
			'smarttrakAlarma',
			[
				'ajaxUrl'    => admin_url( 'admin-ajax.php' ),
				'nonce'      => wp_create_nonce( 'smarttrak_alarma_callback' ),
				'success'    => 'Дякуємо. Ми зателефонуємо вам найближчим часом.',
				'error'      => 'Не вдалося надіслати заявку. Спробуй ще раз.',
				'buttonText' => $options['floating_button_label'],
			]
		);
	}

	/**
	 * Shortcode output.
	 *
	 * @return string
	 */
	public function render_button_shortcode(): string {
		$options = $this->get_options();

		return $this->get_button_markup( $options['floating_button_label'], false );
	}

	/**
	 * Global markup.
	 *
	 * @return void
	 */
	public function render_global_callback_markup(): void {
		$options = $this->get_options();

		if ( empty( $options['callback_enabled'] ) ) {
			return;
		}

		echo $this->get_button_markup( $options['floating_button_label'], true ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from escaped values.
		echo $this->get_modal_markup( $options['popup_title'] ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- Markup is built from escaped values.
	}

	/**
	 * Button markup.
	 *
	 * @param string $label Button label.
	 * @param bool   $floating Is floating button.
	 * @return string
	 */
	private function get_button_markup( string $label, bool $floating ): string {
		$classes = 'smarttrak-alarma__open';

		if ( $floating ) {
			$classes .= ' smarttrak-alarma__open--floating';
		}

		return sprintf(
			'<button type="button" class="%1$s" data-smarttrak-alarma-open="1">%2$s</button>',
			esc_attr( $classes ),
			esc_html( $label )
		);
	}

	/**
	 * Modal markup.
	 *
	 * @param string $title Popup title.
	 * @return string
	 */
	private function get_modal_markup( string $title ): string {
		ob_start();
		?>
		<div class="smarttrak-alarma" hidden>
			<div class="smarttrak-alarma__backdrop" data-smarttrak-alarma-close="1"></div>
			<div class="smarttrak-alarma__dialog" role="dialog" aria-modal="true" aria-labelledby="smarttrak-alarma-title">
				<button type="button" class="smarttrak-alarma__close" aria-label="Закрити" data-smarttrak-alarma-close="1">×</button>
				<h3 id="smarttrak-alarma-title" class="smarttrak-alarma__title"><?php echo esc_html( $title ); ?></h3>
				<form class="smarttrak-alarma__form">
					<label class="smarttrak-alarma__field">
						<span>Ім’я</span>
						<input type="text" name="name" autocomplete="name">
					</label>
					<label class="smarttrak-alarma__field">
						<span>Телефон</span>
						<input type="tel" name="phone" autocomplete="tel" required>
					</label>
					<button type="submit" class="smarttrak-alarma__submit">Передзвоніть мені</button>
					<p class="smarttrak-alarma__message" hidden></p>
				</form>
			</div>
		</div>
		<?php
		return (string) ob_get_clean();
	}

	/**
	 * Handle callback request.
	 *
	 * @return void
	 */
	public function handle_callback_form(): void {
		check_ajax_referer( 'smarttrak_alarma_callback', 'nonce' );

		$name  = isset( $_POST['name'] ) ? sanitize_text_field( wp_unslash( $_POST['name'] ) ) : '';
		$phone = isset( $_POST['phone'] ) ? sanitize_text_field( wp_unslash( $_POST['phone'] ) ) : '';

		if ( '' === $phone ) {
			wp_send_json_error(
				[
					'message' => 'Телефон є обов’язковим.',
				],
				400
			);
		}

		$this->send_callback_email( $name, $phone );
		$this->send_telegram_message(
			"НОВАЯ ПОЗВОНИ МНЕ АЛЯРМА!!!\n\nІм’я: " . ( $name ?: 'Не вказано' ) . "\nТелефон: {$phone}\nСторінка: " . esc_url_raw( wp_get_referer() ?: home_url( '/' ) )
		);

		wp_send_json_success(
			[
				'message' => 'Дякуємо. Ми зателефонуємо вам найближчим часом.',
			]
		);
	}

	/**
	 * Send callback email.
	 *
	 * @param string $name Name.
	 * @param string $phone Phone.
	 * @return void
	 */
	private function send_callback_email( string $name, string $phone ): void {
		$options = $this->get_options();
		$to      = $options['owner_email'];

		if ( ! is_email( $to ) ) {
			$to = get_option( 'admin_email', '' );
		}

		$subject = 'Нова заявка на зворотний дзвінок';
		$body    = "Ім’я: " . ( $name ?: 'Не вказано' ) . "\n";
		$body   .= "Телефон: {$phone}\n";
		$body   .= 'Сторінка: ' . ( wp_get_referer() ?: home_url( '/' ) );

		wp_mail( $to, $subject, $body );
	}

	/**
	 * Handle SureForms contact form submission.
	 *
	 * @param array<string,mixed> $response Response.
	 * @param array<string,mixed> $form_data Raw form data.
	 * @param array<string,mixed> $submission_data Processed data.
	 * @return array<string,mixed>
	 */
	public function handle_contact_form_alarm( $response, $form_data, $submission_data ) {
		$options = $this->get_options();
		$form_id = isset( $form_data['form-id'] ) ? absint( $form_data['form-id'] ) : 0;

		if ( $form_id !== absint( $options['contact_form_id'] ) ) {
			return $response;
		}

		$name    = $this->find_value_by_suffix( $submission_data, '-first-name' );
		$phone   = $this->find_value_by_suffix( $submission_data, '-phone' );
		$message = $this->find_value_by_suffix( $submission_data, '-comment-or-message' );

		$telegram = "НОВАЯ ФОРМА В КОНТАКТАХ АЛЯРМА!!!\n\n";
		$telegram .= 'Ім’я: ' . ( $name ?: 'Не вказано' ) . "\n";
		$telegram .= 'Телефон: ' . ( $phone ?: 'Не вказано' ) . "\n";
		$telegram .= 'Повідомлення: ' . ( $message ?: 'Не вказано' );

		$this->send_telegram_message( $telegram );

		return $response;
	}

	/**
	 * Find submitted field by key suffix.
	 *
	 * @param array<string,mixed> $data Submission data.
	 * @param string              $suffix Field suffix.
	 * @return string
	 */
	private function find_value_by_suffix( array $data, string $suffix ): string {
		foreach ( $data as $key => $value ) {
			if ( ! is_string( $key ) ) {
				continue;
			}

			if ( substr( $key, -strlen( $suffix ) ) !== $suffix ) {
				continue;
			}

			if ( is_array( $value ) ) {
				return implode( ', ', array_map( 'sanitize_text_field', $value ) );
			}

			return sanitize_text_field( (string) $value );
		}

		return '';
	}

	/**
	 * Send Telegram message.
	 *
	 * @param string $message Text.
	 * @return bool
	 */
	private function send_telegram_message( string $message ): bool {
		$options   = $this->get_options();
		$bot_token = trim( (string) $options['bot_token'] );
		$chat_id   = trim( (string) $options['chat_id'] );

		if ( '' === $bot_token || '' === $chat_id ) {
			return false;
		}

		$response = wp_remote_post(
			'https://api.telegram.org/bot' . rawurlencode( $bot_token ) . '/sendMessage',
			[
				'timeout' => 15,
				'body'    => [
					'chat_id'    => $chat_id,
					'text'       => $message,
					'parse_mode' => 'HTML',
				],
			]
		);

		return ! is_wp_error( $response ) && 200 === wp_remote_retrieve_response_code( $response );
	}

	/**
	 * Fetch latest bot chat ID.
	 *
	 * @return void
	 */
	public function handle_fetch_chat_id(): void {
		if ( ! current_user_can( 'manage_options' ) ) {
			wp_die( 'Access denied' );
		}

		check_admin_referer( 'smarttrak_alarma_fetch_chat' );

		$options   = $this->get_options();
		$bot_token = trim( (string) $options['bot_token'] );

		if ( '' === $bot_token ) {
			wp_safe_redirect( add_query_arg( 'smarttrak_alarma_chat', 'missing_token', admin_url( 'options-general.php?page=smarttrak-alarma' ) ) );
			exit;
		}

		$response = wp_remote_get(
			'https://api.telegram.org/bot' . rawurlencode( $bot_token ) . '/getUpdates',
			[
				'timeout' => 15,
			]
		);

		if ( is_wp_error( $response ) ) {
			wp_safe_redirect( add_query_arg( 'smarttrak_alarma_chat', 'request_error', admin_url( 'options-general.php?page=smarttrak-alarma' ) ) );
			exit;
		}

		$body = json_decode( wp_remote_retrieve_body( $response ), true );

		if ( empty( $body['ok'] ) || empty( $body['result'] ) || ! is_array( $body['result'] ) ) {
			wp_safe_redirect( add_query_arg( 'smarttrak_alarma_chat', 'not_found', admin_url( 'options-general.php?page=smarttrak-alarma' ) ) );
			exit;
		}

		$latest = end( $body['result'] );
		$chat   = $latest['message']['chat']['id'] ?? $latest['callback_query']['message']['chat']['id'] ?? '';

		if ( '' === (string) $chat ) {
			wp_safe_redirect( add_query_arg( 'smarttrak_alarma_chat', 'not_found', admin_url( 'options-general.php?page=smarttrak-alarma' ) ) );
			exit;
		}

		$options['chat_id'] = (string) $chat;
		update_option( $this->option_key, $options );

		$this->send_telegram_message( 'Smarttrak Alarma підключено. Алерти готові до роботи.' );

		wp_safe_redirect( add_query_arg( 'smarttrak_alarma_chat', 'saved', admin_url( 'options-general.php?page=smarttrak-alarma' ) ) );
		exit;
	}
}
