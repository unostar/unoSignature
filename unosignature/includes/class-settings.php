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
		add_action('admin_enqueue_scripts', [self::class, 'enqueue_admin_assets']);
		add_action('admin_head-settings_page_unosignature', [self::class, 'admin_styles']);
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

	public static function enqueue_admin_assets(string $hook): void {
		if ($hook !== 'settings_page_unosignature' || !class_exists('WooCommerce')) {
			return;
		}

		wp_enqueue_style('woocommerce_admin_styles');
		wp_enqueue_script('wc-enhanced-select');
		wp_enqueue_script(
			'unosignature-settings',
			UNOSIGNATURE_URL . 'assets/js/settings.js',
			['jquery', 'wc-enhanced-select'],
			UNOSIGNATURE_VERSION,
			true
		);
		wp_localize_script('unosignature-settings', 'unosignatureSettings', [
			'optionKey' => Config::OPTION_KEY,
		]);
	}

	public static function sanitize($input): array {
		$input = is_array($input) ? $input : [];
		$current = get_option(Config::OPTION_KEY, []);
		$current = is_array($current) ? $current : [];
		$firma_api_key = sanitize_text_field((string) ($input['firma_api_key'] ?? ''));
		if ($firma_api_key === '' && !empty($current['firma_api_key'])) {
			$firma_api_key = (string) $current['firma_api_key'];
		}

		$firma_webhook_secret = sanitize_text_field((string) ($input['firma_webhook_secret'] ?? ''));
		if ($firma_webhook_secret === '' && !empty($current['firma_webhook_secret'])) {
			$firma_webhook_secret = (string) $current['firma_webhook_secret'];
		}

		$firma_test_api_key = sanitize_text_field((string) ($input['firma_test_api_key'] ?? ''));
		if ($firma_test_api_key === '' && !empty($current['firma_test_api_key'])) {
			$firma_test_api_key = (string) $current['firma_test_api_key'];
		}

		$github_token = sanitize_text_field((string) ($input['github_token'] ?? ''));
		if ($github_token === '' && !empty($current['github_token'])) {
			$github_token = (string) $current['github_token'];
		}

		return [
			'firma_api_key'            => $firma_api_key,
			'firma_test_api_key'       => $firma_test_api_key,
			'firma_use_test_key'       => !empty($input['firma_use_test_key']) ? '1' : '',
			'firma_webhook_secret'     => $firma_webhook_secret,
			'firma_owner_copy_email'   => sanitize_email((string) ($input['firma_owner_copy_email'] ?? '')),
			'template_map'             => self::sanitize_template_map($input['template_map'] ?? []),
			'firma_debug'              => !empty($input['firma_debug']) ? '1' : '',
			'github_repo'              => sanitize_text_field((string) ($input['github_repo'] ?? '')),
			'github_token'             => $github_token,
			'github_release_asset'     => sanitize_file_name((string) ($input['github_release_asset'] ?? 'unosignature.zip')),
		];
	}

	private static function sanitize_template_map($input): array {
		if (!is_array($input)) {
			return [];
		}

		$map = [];

		foreach ($input as $entry) {
			if (!is_array($entry)) {
				continue;
			}

			$template_id = sanitize_text_field((string) ($entry['template_id'] ?? ''));
			if ($template_id === '') {
				continue;
			}

			$map[] = [
				'categories'      => array_values(array_filter(array_map('sanitize_title', (array) ($entry['categories'] ?? [])))),
				'product_ids'     => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['product_ids'] ?? []))))),
				'excluded_ids'    => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['excluded_ids'] ?? []))))),
				'agreement_group' => sanitize_key((string) ($entry['agreement_group'] ?? '')),
				'template_id'     => $template_id,
			];
		}

		return $map;
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$options = get_option(Config::OPTION_KEY, []);
		$options = is_array($options) ? $options : [];
		$template_map = self::get_display_template_map($options);
		$product_categories = get_terms([
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
		]);
		if (is_wp_error($product_categories)) {
			$product_categories = [];
		}
		?>
		<div class="wrap">
			<h1>unoSignature</h1>
			<p><?php esc_html_e('Firma API credentials and checkout signing rules.', 'unosignature'); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields('unosignature'); ?>
				<table class="form-table" role="presentation">
					<?php self::text_row('firma_api_key', 'Firma API key (live)', $options, 'password', !empty($options['firma_api_key']) ? 'Key is saved; leave blank to keep it' : ''); ?>
					<?php self::text_row('firma_test_api_key', 'Firma API key (test)', $options, 'password', !empty($options['firma_test_api_key']) ? 'Key is saved; leave blank to keep it' : ''); ?>
					<tr>
						<th scope="row">Firma test mode</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[firma_use_test_key]" value="1" <?php checked(!empty($options['firma_use_test_key'])); ?> />
								<?php esc_html_e('Use test API key (no live credits; watermarked signing requests)', 'unosignature'); ?>
							</label>
						</td>
					</tr>
					<?php self::text_row('firma_webhook_secret', 'Firma webhook secret', $options, 'password', !empty($options['firma_webhook_secret']) ? 'Secret is saved; leave blank to keep it' : ''); ?>
					<?php self::text_row('firma_owner_copy_email', 'Owner copy email', $options, 'email'); ?>
					<tr>
						<th scope="row">Firma debug</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[firma_debug]" value="1" <?php checked(!empty($options['firma_debug'])); ?> />
								Enable debug logging
							</label>
						</td>
					</tr>
				</table>

				<h2><?php esc_html_e('Signing agreement rules', 'unosignature'); ?></h2>
				<p class="description">
					<?php esc_html_e('Map WooCommerce products or categories to Firma templates. First matching rule wins.', 'unosignature'); ?>
				</p>
				<table class="widefat unosignature-template-map" id="unosignature-template-map">
					<thead>
						<tr>
							<th><?php esc_html_e('Products', 'unosignature'); ?></th>
							<th><?php esc_html_e('Categories', 'unosignature'); ?></th>
							<th><?php esc_html_e('Excluded products', 'unosignature'); ?></th>
							<th><?php esc_html_e('Agreement group', 'unosignature'); ?></th>
							<th><?php esc_html_e('Firma template ID', 'unosignature'); ?></th>
							<th></th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ($template_map as $index => $row) : ?>
							<?php self::render_template_map_row((int) $index, $row, $product_categories); ?>
						<?php endforeach; ?>
					</tbody>
				</table>
				<p>
					<button type="button" class="button" id="unosignature-add-template-map-row">
						<?php esc_html_e('Add rule', 'unosignature'); ?>
					</button>
				</p>

				<details class="unosignature-settings-panel">
					<summary><?php esc_html_e('Plugin updates (GitHub)', 'unosignature'); ?></summary>
					<p class="description"><?php esc_html_e('Private GitHub Releases updater. Leave collapsed unless you need to change update credentials.', 'unosignature'); ?></p>
					<table class="form-table" role="presentation">
						<?php self::text_row('github_repo', 'GitHub repo', $options, 'text', 'unostar/unoSignature'); ?>
						<?php self::text_row('github_token', 'GitHub token', $options, 'password', !empty($options['github_token']) ? 'Token is saved; leave blank to keep it' : ''); ?>
						<?php self::text_row('github_release_asset', 'Release asset name', $options, 'text', 'unosignature.zip'); ?>
					</table>
				</details>

				<?php submit_button(); ?>
			</form>

			<script type="text/template" id="unosignature-template-map-row-template">
				<?php self::render_template_map_row('__INDEX__', [], $product_categories); ?>
			</script>
		</div>
		<?php
	}

	private static function get_display_template_map(array $options): array {
		$map = $options['template_map'] ?? [];
		if (is_array($map) && !empty($map)) {
			return $map;
		}

		$en_template = (string) ($options['paid_consultation_en'] ?? '');
		$ru_template = (string) ($options['paid_consultation_ru_en'] ?? '');
		$defaults = [];

		if ($en_template !== '') {
			$defaults[] = [
				'product_ids'     => [20203, 20047],
				'categories'      => [],
				'excluded_ids'    => [],
				'agreement_group' => 'paid_consultation',
				'template_id'     => $en_template,
			];
		}

		if ($ru_template !== '') {
			$defaults[] = [
				'product_ids'     => [19741, 20202],
				'categories'      => [],
				'excluded_ids'    => [],
				'agreement_group' => 'paid_consultation',
				'template_id'     => $ru_template,
			];
		}

		if (!empty($defaults)) {
			return $defaults;
		}

		return [
			[
				'product_ids'     => [],
				'categories'      => [],
				'excluded_ids'    => [],
				'agreement_group' => '',
				'template_id'     => '',
			],
		];
	}

	/**
	 * @param int|string $index
	 */
	private static function render_template_map_row($index, array $row, array $product_categories): void {
		$option_key = Config::OPTION_KEY;
		$name_prefix = $option_key . '[template_map][' . $index . ']';
		$product_ids = array_map('absint', (array) ($row['product_ids'] ?? []));
		$excluded_ids = array_map('absint', (array) ($row['excluded_ids'] ?? []));
		$categories = array_map('sanitize_title', (array) ($row['categories'] ?? []));
		$agreement_group = (string) ($row['agreement_group'] ?? '');
		$template_id = (string) ($row['template_id'] ?? '');
		?>
		<tr class="unosignature-template-map-row">
			<td>
				<select
					class="wc-product-search"
					multiple="multiple"
					style="width: 100%;"
					name="<?php echo esc_attr($name_prefix); ?>[product_ids][]"
					data-placeholder="<?php esc_attr_e('Search for products…', 'unosignature'); ?>"
					data-action="woocommerce_json_search_products_and_variations"
					data-allow_clear="true"
				>
					<?php foreach ($product_ids as $product_id) : ?>
						<?php
						$product = wc_get_product($product_id);
						if (!$product) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr((string) $product_id); ?>" selected="selected">
							<?php echo esc_html(wp_strip_all_tags($product->get_formatted_name())); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select
					class="wc-enhanced-select"
					multiple="multiple"
					style="width: 100%;"
					name="<?php echo esc_attr($name_prefix); ?>[categories][]"
					data-placeholder="<?php esc_attr_e('Select categories…', 'unosignature'); ?>"
				>
					<?php foreach ($product_categories as $category) : ?>
						<option
							value="<?php echo esc_attr($category->slug); ?>"
							<?php selected(in_array($category->slug, $categories, true)); ?>
						>
							<?php echo esc_html($category->name); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<select
					class="wc-product-search"
					multiple="multiple"
					style="width: 100%;"
					name="<?php echo esc_attr($name_prefix); ?>[excluded_ids][]"
					data-placeholder="<?php esc_attr_e('Search for products…', 'unosignature'); ?>"
					data-action="woocommerce_json_search_products_and_variations"
					data-allow_clear="true"
				>
					<?php foreach ($excluded_ids as $product_id) : ?>
						<?php
						$product = wc_get_product($product_id);
						if (!$product) {
							continue;
						}
						?>
						<option value="<?php echo esc_attr((string) $product_id); ?>" selected="selected">
							<?php echo esc_html(wp_strip_all_tags($product->get_formatted_name())); ?>
						</option>
					<?php endforeach; ?>
				</select>
			</td>
			<td>
				<input
					class="regular-text"
					type="text"
					name="<?php echo esc_attr($name_prefix); ?>[agreement_group]"
					value="<?php echo esc_attr($agreement_group); ?>"
					placeholder="<?php esc_attr_e('e.g. paid_consultation', 'unosignature'); ?>"
				/>
			</td>
			<td>
				<input
					class="regular-text"
					type="text"
					name="<?php echo esc_attr($name_prefix); ?>[template_id]"
					value="<?php echo esc_attr($template_id); ?>"
					placeholder="<?php esc_attr_e('Firma template ID', 'unosignature'); ?>"
					required
				/>
			</td>
			<td>
				<button type="button" class="button-link-delete unosignature-remove-template-map-row">
					<?php esc_html_e('Remove', 'unosignature'); ?>
				</button>
			</td>
		</tr>
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

	public static function admin_styles(): void {
		?>
		<style>
			.unosignature-settings-panel {
				margin: 1.5em 0;
				max-width: 960px;
			}

			.unosignature-settings-panel > summary {
				cursor: pointer;
				font-size: 14px;
				font-weight: 600;
				line-height: 1.4;
				list-style: revert;
			}

			.unosignature-settings-panel[open] > summary {
				margin-bottom: 0.75em;
			}

			.unosignature-settings-panel > .description {
				margin: 0 0 1em;
			}

			.unosignature-template-map {
				max-width: 1200px;
			}

			.unosignature-template-map th,
			.unosignature-template-map td {
				vertical-align: top;
			}

			.unosignature-template-map td {
				min-width: 160px;
			}

			.unosignature-template-map .unosignature-template-map-row td:last-child {
				width: 72px;
				min-width: 72px;
			}
		</style>
		<?php
	}
}
