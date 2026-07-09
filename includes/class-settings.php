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
		add_action('wp_ajax_unosignature_search_products', [self::class, 'ajax_search_products']);
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
			'productSearchAction' => 'unosignature_search_products',
		]);
	}

	public static function ajax_search_products(): void {
		check_ajax_referer('search-products', 'security');

		if (!current_user_can('manage_options')) {
			wp_send_json_error(null, 403);
		}

		$term = isset($_GET['term']) ? wc_clean(wp_unslash((string) $_GET['term'])) : '';
		if ($term === '') {
			wp_send_json([]);
		}

		wp_send_json(self::search_products_for_settings($term));
	}

	private static function search_products_for_settings(string $term): array {
		global $wpdb;

		$limit = (int) apply_filters('unosignature_settings_product_search_limit', 30);
		$statuses = current_user_can('edit_private_products') ? ['publish', 'private'] : ['publish'];
		$products = [];

		$ids = self::query_product_ids_by_phrase($term, $statuses, $limit);
		if (empty($ids)) {
			$ids = self::query_product_ids_by_words($term, $statuses, $limit);
		}

		if (empty($ids) && ctype_digit($term)) {
			$product = wc_get_product((int) $term);
			if ($product && in_array($product->get_status(), $statuses, true)) {
				$ids = [(int) $term];
			}
		}

		foreach ($ids as $product_id) {
			$product = wc_get_product((int) $product_id);
			if (!$product) {
				continue;
			}

			$products[$product->get_id()] = rawurldecode(wp_strip_all_tags($product->get_formatted_name()));
		}

		return apply_filters('unosignature_settings_found_products', $products, $term);
	}

	private static function query_product_ids_by_phrase(string $term, array $statuses, int $limit): array {
		global $wpdb;

		$like = '%' . $wpdb->esc_like($term) . '%';
		$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->wc_product_meta_lookup} lookup ON lookup.product_id = p.ID
			WHERE p.post_type = 'product'
			AND p.post_status IN ($status_placeholders)
			AND (
				p.post_title LIKE %s
				OR lookup.sku LIKE %s
			)
			ORDER BY p.post_title ASC
			LIMIT %d
		";

		$prepare_args = array_merge($statuses, [$like, $like, $limit]);

		return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, ...$prepare_args)));
	}

	private static function query_product_ids_by_words(string $term, array $statuses, int $limit): array {
		global $wpdb;

		$words = preg_split('/\s+/u', $term, -1, PREG_SPLIT_NO_EMPTY);
		if (!$words || count($words) < 2) {
			return [];
		}

		$status_placeholders = implode(', ', array_fill(0, count($statuses), '%s'));
		$word_conditions = [];
		$prepare_args = $statuses;

		foreach ($words as $word) {
			$like = '%' . $wpdb->esc_like($word) . '%';
			$word_conditions[] = '(p.post_title LIKE %s OR lookup.sku LIKE %s)';
			$prepare_args[] = $like;
			$prepare_args[] = $like;
		}

		$prepare_args[] = $limit;
		$sql = "
			SELECT DISTINCT p.ID
			FROM {$wpdb->posts} p
			LEFT JOIN {$wpdb->wc_product_meta_lookup} lookup ON lookup.product_id = p.ID
			WHERE p.post_type = 'product'
			AND p.post_status IN ($status_placeholders)
			AND (" . implode(' OR ', $word_conditions) . ")
			ORDER BY p.post_title ASC
			LIMIT %d
		";

		return array_map('intval', (array) $wpdb->get_col($wpdb->prepare($sql, ...$prepare_args)));
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
				'admin_label'     => sanitize_text_field((string) ($entry['admin_label'] ?? '')),
				'categories'      => array_values(array_filter(array_map('sanitize_title', (array) ($entry['categories'] ?? [])))),
				'product_ids'     => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['product_ids'] ?? []))))),
				'excluded_ids'    => array_values(array_unique(array_filter(array_map('absint', (array) ($entry['excluded_ids'] ?? []))))),
				'agreement_group' => sanitize_key((string) ($entry['agreement_group'] ?? '')),
				'template_id'     => $template_id,
				'field_additional_applicants' => self::sanitize_uuid_field((string) ($entry['field_additional_applicants'] ?? '')),
				'field_representative'        => self::sanitize_uuid_field((string) ($entry['field_representative'] ?? '')),
				'field_sponsor'               => self::sanitize_uuid_field((string) ($entry['field_sponsor'] ?? '')),
			];
		}

		return $map;
	}

	private static function sanitize_uuid_field(string $value): string {
		$value = sanitize_text_field($value);
		if ($value === '') {
			return '';
		}

		if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $value)) {
			return strtolower($value);
		}

		return '';
	}

	public static function render(): void {
		if (!current_user_can('manage_options')) {
			return;
		}

		$options = get_option(Config::OPTION_KEY, []);
		$options = is_array($options) ? $options : [];
		$template_map = self::get_display_template_map($options);
		$required_category_slugs = [];
		foreach ($template_map as $row) {
			if (!is_array($row)) {
				continue;
			}
			foreach ((array) ($row['categories'] ?? []) as $slug) {
				$required_category_slugs[] = (string) $slug;
			}
		}
		$product_categories = self::get_product_categories_for_settings($required_category_slugs);
		?>
		<div class="wrap">
			<h1>unoSignature</h1>
			<p><?php esc_html_e('Firma API credentials and checkout signing rules.', 'unosignature'); ?></p>
			<form method="post" action="options.php">
				<?php settings_fields('unosignature'); ?>
				<table class="form-table" role="presentation">
					<?php self::text_row('firma_api_key', 'Firma API key (live)', $options, 'password', !empty($options['firma_api_key']) ? 'Key is saved; leave blank to keep it' : ''); ?>
					<?php self::text_row('firma_test_api_key', 'Firma API key (test)', $options, 'password', !empty($options['firma_test_api_key']) ? 'Key is saved; leave blank to keep it' : ''); ?>
					<?php self::text_row('firma_webhook_secret', 'Firma webhook secret', $options, 'password', !empty($options['firma_webhook_secret']) ? 'Secret is saved; leave blank to keep it' : ''); ?>
					<?php self::text_row('firma_owner_copy_email', 'Owner copy email', $options, 'email'); ?>
				</table>

				<h2><?php esc_html_e('Signing agreement rules', 'unosignature'); ?></h2>
				<p class="description">
					<?php esc_html_e('Map WooCommerce products or categories to Firma templates. First matching rule wins. Rules without a template ID are ignored on save. For visa_services rules, set the three Firma textarea field UUIDs on the same row.', 'unosignature'); ?>
				</p>
				<div class="unosignature-template-map" id="unosignature-template-map">
					<?php if (empty($template_map)) : ?>
						<p class="unosignature-template-map-empty description">
							<?php esc_html_e('No signing rules configured.', 'unosignature'); ?>
						</p>
					<?php else : ?>
						<?php foreach ($template_map as $index => $row) : ?>
							<?php self::render_template_map_row((int) $index, $row, $product_categories); ?>
						<?php endforeach; ?>
					<?php endif; ?>
				</div>
				<p class="unosignature-template-map-actions">
					<button type="button" class="button" id="unosignature-add-template-map-row">
						<?php esc_html_e('Add rule', 'unosignature'); ?>
					</button>
				</p>

				<table class="form-table" role="presentation">
					<tr>
						<th scope="row">Firma debug</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[firma_debug]" value="1" <?php checked(!empty($options['firma_debug'])); ?> />
								Enable debug logging
							</label>
						</td>
					</tr>
					<tr>
						<th scope="row">Firma test mode</th>
						<td>
							<label>
								<input type="checkbox" name="<?php echo esc_attr(Config::OPTION_KEY); ?>[firma_use_test_key]" value="1" <?php checked(!empty($options['firma_use_test_key'])); ?> />
								<?php esc_html_e('Use test API key (no live credits; watermarked signing requests)', 'unosignature'); ?>
							</label>
						</td>
					</tr>
				</table>

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
		if (!is_array($map)) {
			return [];
		}

		return $map;
	}

	/**
	 * Product categories for signing rules (all WPML languages when available).
	 *
	 * @param array<int, string> $required_slugs Saved slugs to keep visible even if filtered out.
	 * @return array<int, \WP_Term>
	 */
	private static function get_product_categories_for_settings(array $required_slugs = []): array {
		$wpml_active = has_filter('wpml_active_languages');
		if ($wpml_active) {
			$terms = self::query_product_categories_unfiltered();
		} else {
			$terms = get_terms([
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
			]);
			if (is_wp_error($terms)) {
				$terms = [];
			}
		}

		$by_slug = [];
		foreach ($terms as $term) {
			if ($term instanceof \WP_Term && $term->slug !== '') {
				$by_slug[$term->slug] = $term;
			}
		}

		foreach ($required_slugs as $slug) {
			$slug = sanitize_title($slug);
			if ($slug === '' || isset($by_slug[$slug])) {
				continue;
			}

			$term = $wpml_active
				? self::get_product_category_by_slug_unfiltered($slug)
				: get_term_by('slug', $slug, 'product_cat');
			if ($term instanceof \WP_Term) {
				$by_slug[$slug] = $term;
			}
		}

		$categories = array_values($by_slug);
		usort(
			$categories,
			static function (\WP_Term $a, \WP_Term $b): int {
				return strcasecmp($a->name, $b->name);
			}
		);

		return $categories;
	}

	/**
	 * WPML can still filter get_terms() by the current admin language. The settings
	 * page needs every product category so rules can be configured cross-language.
	 *
	 * @return array<int, \WP_Term>
	 */
	private static function query_product_categories_unfiltered(): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"
				SELECT t.term_id, t.name, t.slug, t.term_group,
					tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				WHERE tt.taxonomy = %s
				ORDER BY t.name ASC
				",
				'product_cat'
			)
		);

		return self::product_category_rows_to_terms((array) $rows);
	}

	private static function get_product_category_by_slug_unfiltered(string $slug): ?\WP_Term {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare(
				"
				SELECT t.term_id, t.name, t.slug, t.term_group,
					tt.term_taxonomy_id, tt.taxonomy, tt.description, tt.parent, tt.count
				FROM {$wpdb->terms} t
				INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_id = t.term_id
				WHERE tt.taxonomy = %s
					AND t.slug = %s
				LIMIT 1
				",
				'product_cat',
				$slug
			)
		);

		$terms = self::product_category_rows_to_terms($row ? [$row] : []);

		return $terms[0] ?? null;
	}

	/**
	 * @param array<int, object> $rows
	 * @return array<int, \WP_Term>
	 */
	private static function product_category_rows_to_terms(array $rows): array {
		$terms = [];

		foreach ($rows as $row) {
			$row->term_id = (int) $row->term_id;
			$row->term_taxonomy_id = (int) $row->term_taxonomy_id;
			$row->term_group = (int) $row->term_group;
			$row->parent = (int) $row->parent;
			$row->count = (int) $row->count;
			$row->filter = 'raw';
			$terms[] = new \WP_Term($row);
		}

		return $terms;
	}

	private static function format_category_option_label(\WP_Term $category): string {
		$name = $category->name;
		$lang = apply_filters(
			'wpml_element_language_code',
			null,
			[
				'element_id'   => (int) $category->term_taxonomy_id,
				'element_type' => 'tax_product_cat',
			]
		);

		if (is_string($lang) && $lang !== '') {
			return $name . ' (' . strtoupper($lang) . ')';
		}

		return $name;
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
		$admin_label = (string) ($row['admin_label'] ?? '');
		$agreement_group = (string) ($row['agreement_group'] ?? '');
		$template_id = (string) ($row['template_id'] ?? '');
		$field_additional_applicants = (string) ($row['field_additional_applicants'] ?? '');
		$field_representative = (string) ($row['field_representative'] ?? '');
		$field_sponsor = (string) ($row['field_sponsor'] ?? '');
		$rule_number = is_numeric($index) ? ((int) $index + 1) : 1;
		$is_new_row = $index === '__INDEX__';
		?>
		<details class="unosignature-template-map-row"<?php echo $is_new_row ? ' open' : ''; ?>>
			<summary><?php echo esc_html(self::get_rule_summary_label($rule_number, $row)); ?></summary>
			<div class="unosignature-template-map-row__body">
				<div class="unosignature-template-map-row__field">
					<label><?php esc_html_e('Rule name', 'unosignature'); ?></label>
					<input
						class="regular-text unosignature-rule-label-input"
						type="text"
						name="<?php echo esc_attr($name_prefix); ?>[admin_label]"
						value="<?php echo esc_attr($admin_label); ?>"
						placeholder="<?php esc_attr_e('e.g. Paid consultation EN', 'unosignature'); ?>"
					/>
					<p class="description"><?php esc_html_e('Admin label only. Shown in the list above; not used on checkout.', 'unosignature'); ?></p>
				</div>

				<div class="unosignature-template-map-row__field">
					<label><?php esc_html_e('Products', 'unosignature'); ?></label>
					<select
						class="wc-product-search"
						multiple="multiple"
						name="<?php echo esc_attr($name_prefix); ?>[product_ids][]"
						data-placeholder="<?php esc_attr_e('Search for products…', 'unosignature'); ?>"
						data-action="unosignature_search_products"
						data-minimum_input_length="2"
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
				</div>

				<div class="unosignature-template-map-row__field">
					<label><?php esc_html_e('Categories', 'unosignature'); ?></label>
					<select
						class="wc-enhanced-select"
						multiple="multiple"
						name="<?php echo esc_attr($name_prefix); ?>[categories][]"
						data-placeholder="<?php esc_attr_e('Select categories…', 'unosignature'); ?>"
					>
						<?php foreach ($product_categories as $category) : ?>
							<option
								value="<?php echo esc_attr($category->slug); ?>"
								<?php selected(in_array($category->slug, $categories, true)); ?>
							>
								<?php echo esc_html(self::format_category_option_label($category)); ?>
							</option>
						<?php endforeach; ?>
					</select>
				</div>

				<div class="unosignature-template-map-row__field">
					<label><?php esc_html_e('Excluded products', 'unosignature'); ?></label>
					<select
						class="wc-product-search"
						multiple="multiple"
						name="<?php echo esc_attr($name_prefix); ?>[excluded_ids][]"
						data-placeholder="<?php esc_attr_e('Search for products…', 'unosignature'); ?>"
						data-action="unosignature_search_products"
						data-minimum_input_length="2"
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
				</div>

				<div class="unosignature-template-map-row__inline">
					<div class="unosignature-template-map-row__field">
						<label><?php esc_html_e('Agreement group', 'unosignature'); ?></label>
						<input
							class="regular-text"
							type="text"
							name="<?php echo esc_attr($name_prefix); ?>[agreement_group]"
							value="<?php echo esc_attr($agreement_group); ?>"
							placeholder="<?php esc_attr_e('e.g. paid_consultation', 'unosignature'); ?>"
						/>
					</div>
					<div class="unosignature-template-map-row__field">
						<label><?php esc_html_e('Firma template ID', 'unosignature'); ?></label>
						<input
							class="regular-text"
							type="text"
							name="<?php echo esc_attr($name_prefix); ?>[template_id]"
							value="<?php echo esc_attr($template_id); ?>"
							placeholder="<?php esc_attr_e('Firma template ID', 'unosignature'); ?>"
						/>
					</div>
				</div>

				<div class="unosignature-template-map-row__visa-fields">
					<p class="description"><?php esc_html_e('Visa textarea fields (optional). Used when agreement group is visa_services.', 'unosignature'); ?></p>
					<div class="unosignature-template-map-row__field">
						<label><?php esc_html_e('Field: additional applicants', 'unosignature'); ?></label>
						<input
							class="regular-text"
							type="text"
							name="<?php echo esc_attr($name_prefix); ?>[field_additional_applicants]"
							value="<?php echo esc_attr($field_additional_applicants); ?>"
							placeholder="<?php esc_attr_e('Firma template_field_id UUID', 'unosignature'); ?>"
						/>
					</div>
					<div class="unosignature-template-map-row__field">
						<label><?php esc_html_e('Field: representative', 'unosignature'); ?></label>
						<input
							class="regular-text"
							type="text"
							name="<?php echo esc_attr($name_prefix); ?>[field_representative]"
							value="<?php echo esc_attr($field_representative); ?>"
							placeholder="<?php esc_attr_e('Firma template_field_id UUID', 'unosignature'); ?>"
						/>
					</div>
					<div class="unosignature-template-map-row__field">
						<label><?php esc_html_e('Field: sponsor', 'unosignature'); ?></label>
						<input
							class="regular-text"
							type="text"
							name="<?php echo esc_attr($name_prefix); ?>[field_sponsor]"
							value="<?php echo esc_attr($field_sponsor); ?>"
							placeholder="<?php esc_attr_e('Firma template_field_id UUID', 'unosignature'); ?>"
						/>
					</div>
				</div>

				<p class="unosignature-template-map-row__actions">
					<button type="button" class="button unosignature-remove-template-map-row">
						<?php esc_html_e('Remove rule', 'unosignature'); ?>
					</button>
				</p>
			</div>
		</details>
		<?php
	}

	private static function get_rule_summary_label(int $rule_number, array $row): string {
		$admin_label = sanitize_text_field((string) ($row['admin_label'] ?? ''));
		if ($admin_label !== '') {
			return $admin_label;
		}

		return sprintf(__('Rule %d', 'unosignature'), $rule_number);
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

			.unosignature-template-map,
			.unosignature-template-map-actions,
			.unosignature-template-map-empty {
				max-width: 480px;
			}

			.unosignature-template-map {
				display: flex;
				flex-direction: column;
				gap: 8px;
				margin: 0;
			}

			.unosignature-template-map-row > summary {
				cursor: pointer;
				font-size: 14px;
				font-weight: 600;
				line-height: 1.4;
				list-style: revert;
			}

			.unosignature-template-map-row[open] > summary {
				margin-bottom: 8px;
			}

			.unosignature-template-map-row__body {
				border: 1px solid #c3c4c7;
				border-radius: 4px;
				background: #fff;
				padding: 12px 14px;
			}

			.unosignature-template-map-row__field {
				margin-bottom: 10px;
			}

			.unosignature-template-map-row__field:last-child {
				margin-bottom: 0;
			}

			.unosignature-template-map-row__field > label {
				display: block;
				font-weight: 600;
				font-size: 12px;
				margin-bottom: 4px;
				color: #1d2327;
			}

			.unosignature-template-map-row__field .description {
				margin: 4px 0 0;
			}

			.unosignature-template-map-row__field .select2-container,
			.unosignature-template-map-row__field .regular-text {
				width: 100% !important;
				max-width: 100%;
				box-sizing: border-box;
			}

			.unosignature-template-map-row__inline {
				display: grid;
				grid-template-columns: 1fr 1fr;
				gap: 10px;
			}

			.unosignature-template-map-row__visa-fields {
				margin-top: 12px;
				padding-top: 12px;
				border-top: 1px solid #dcdcde;
			}

			.unosignature-template-map-row__visa-fields > .description {
				margin: 0 0 10px;
			}

			.unosignature-template-map-row__actions {
				margin: 12px 0 0;
			}

			.unosignature-template-map-row .select2-selection--multiple .select2-selection__choice {
				width: fit-content;
				max-width: 100%;
			}

			.unosignature-template-map-empty {
				margin: 0 0 1em;
				padding: 10px 12px;
				border: 1px dashed #c3c4c7;
				border-radius: 4px;
				background: #f6f7f7;
			}

			@media (max-width: 782px) {
				.unosignature-template-map-row__inline {
					grid-template-columns: 1fr;
				}
			}
		</style>
		<?php
	}
}
