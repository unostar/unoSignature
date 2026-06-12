<?php
/**
 * Admin settings for unoSignature.
 *
 * @package UnoSignature
 */

namespace UnoSignature;

if (!defined('ABSPATH')) {
	exit;
}

final class Settings {
	public static function init(): void {
		add_action('admin_menu', [self::class, 'add_page']);
		add_action('admin_init', [self::class, 'register']);
	}

	public static function add_page(): void {
		add_options_page(
			'unoSignature',
			'unoSignature',
			'manage_options',
			'unosignature',
			[self::class, 'render']
		);
	}

	public static function register(): void {
		register_setting(
			'unosignature',
			Config::OPTION_KEY,
			[
				'type'              => 'array',
				'sanitize_callback' => [self::class, 'sanitize'],
				'default'           => [],
			]
		);
	}

	public static function sanitize($input): array {
		$input = is_array($input) ? $input : [];
		$current = get_option(Config::OPTION_KEY, []);
		$current = is_array($current) ? $current : [];
		$github_token = sanitize_text_field((string) ($input['github_token'] ?? ''));
		if ($github_token === '' && !empty($current['github_token'])) {
			$github_token = (string) $current['github_token'];
		}

		return [
			'firma_api_key'            => sanitize_text_field((string) ($input['firma_api_key'] ?? '')),
			'firma_webhook_secret'     => sanitize_text_field((string) ($input['firma_webhook_secret'] ?? '')),
			'firma_owner_copy_email'   => sanitize_email((string) ($input['firma_owner_copy_email'] ?? '')),
			'paid_consultation_en'     => sanitize_text_field((string) ($input['paid_consultation_en'] ?? '')),
			'paid_consultation_ru_en'  => sanitize_text_field((string) ($input['paid_consultation_ru_en'] ?? '')),
			'firma_debug'              => !empty($input['firma_debug']) ? '1' : '',
			'github_repo'              => sanitize_text_field((string) ($input['github_repo'] ?? '')),
			'github_token'             => $github_token,
			'github_release_asset'     => sanitize_file_name((string) ($input['github_release_asset'] ?? 'unosignature.zip')),
		];
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$options = get_option(Config::OPTION_KEY, []);
		$options = is_array($options) ? $options : [];
		?>
		<div class="wrap">
			<h1>unoSignature</h1>
			<p>Firma API credentials and private GitHub Releases update settings.</p>
			<form method="post" action="options.php">
				<?php settings_fields('unosignature'); ?>
				<table class="form-table" role="presentation">
					<?php self::text_row('firma_api_key', 'Firma API key', $options); ?>
					<?php self::text_row('firma_webhook_secret', 'Firma webhook secret', $options); ?>
					<?php self::text_row('firma_owner_copy_email', 'Owner copy email', $options, 'email'); ?>
					<?php self::text_row('paid_consultation_en', 'Paid Consultation EN template ID', $options); ?>
					<?php self::text_row('paid_consultation_ru_en', 'Paid Consultation RU/EN template ID', $options); ?>
					<tr>
						<th scope="row">Firma debug</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[firma_debug]" value="1" <?php checked(!empty($options['firma_debug'])); ?> />
								Enable debug logging
							</label>
						</td>
					</tr>
					<?php self::text_row('github_repo', 'GitHub repo', $options, 'text', 'unostar/unosignature'); ?>
					<?php self::text_row('github_token', 'GitHub token', $options, 'password', !empty($options['github_token']) ? 'Token is saved; leave blank to keep it' : ''); ?>
					<?php self::text_row('github_release_asset', 'Release asset name', $options, 'text', 'unosignature.zip'); ?>
				</table>
				<?php submit_button(); ?>
			</form>
		</div>
		<?php
	}

	private static function text_row(string $key, string $label, array $options, string $type = 'text', string $placeholder = ''): void {
		$value = $type === 'password' ? '' : (string) ($options[$key] ?? '');
		?>
		<tr>
			<th scope="row">
				<label for="unosignature-<?php echo esc_attr($key); ?>"><?php echo esc_html($label); ?></label>
			</th>
			<td>
				<input
					id="unosignature-<?php echo esc_attr($key); ?>"
					class="regular-text"
					type="<?php echo esc_attr($type); ?>"
					name="<?php echo esc_attr(Config::OPTION_KEY); ?>[<?php echo esc_attr($key); ?>]"
					value="<?php echo esc_attr($value); ?>"
					placeholder="<?php echo esc_attr($placeholder); ?>"
				/>
			</td>
		</tr>
		<?php
	}
}
