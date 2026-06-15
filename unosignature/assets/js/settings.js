(function ($) {
	'use strict';

	function initEnhancedSelects($context) {
		$context.find('.wc-product-search, .wc-enhanced-select').filter(':not(.enhanced)').each(function () {
			var $select = $(this);
			var isProductSearch = $select.hasClass('wc-product-search');
			var selectArgs = {
				allowClear: $select.data('allow_clear') ? true : false,
				placeholder: $select.data('placeholder') || '',
				minimumInputLength: isProductSearch ? 3 : 0,
				escapeMarkup: function (markup) {
					return markup;
				}
			};

			if (isProductSearch && window.wc_enhanced_select_params) {
				selectArgs.ajax = {
					url: window.wc_enhanced_select_params.ajax_url,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							term: params.term,
							action: $select.data('action') || 'woocommerce_json_search_products_and_variations',
							security: window.wc_enhanced_select_params.search_products_nonce
						};
					},
					processResults: function (data) {
						var results = [];

						if (data) {
							$.each(data, function (id, text) {
								results.push({
									id: id,
									text: text
								});
							});
						}

						return { results: results };
					},
					cache: true
				};
			}

			$select.selectWoo(selectArgs).addClass('enhanced');
		});
	}

	function reindexTemplateMapRows() {
		var optionKey = window.unosignatureSettings?.optionKey || 'unosignature_settings';

		$('#unosignature-template-map tbody .unosignature-template-map-row').each(function (index) {
			$(this)
				.find('[name]')
				.each(function () {
					var name = $(this).attr('name');

					if (!name) {
						return;
					}

					$(this).attr(
						'name',
						name.replace(
							new RegExp(optionKey.replace(/[.*+?^${}()|[\]\\]/g, '\\$&') + '\\[template_map\\]\\[[^\\]]+\\]'),
							optionKey + '[template_map][' + index + ']'
						)
					);
				});
		});
	}

	$(function () {
		var $table = $('#unosignature-template-map');
		var $template = $('#unosignature-template-map-row-template');

		if (!$table.length || !$template.length) {
			return;
		}

		initEnhancedSelects($table);

		$('#unosignature-add-template-map-row').on('click', function () {
			var index = $table.find('tbody .unosignature-template-map-row').length;
			var html = $template.html().replace(/__INDEX__/g, String(index));
			var $row = $(html);

			$table.find('tbody').append($row);
			initEnhancedSelects($row);
		});

		$table.on('click', '.unosignature-remove-template-map-row', function () {
			var $rows = $table.find('tbody .unosignature-template-map-row');

			if ($rows.length <= 1) {
				$rows.first().find('select').val(null).trigger('change');
				$rows.first().find('input[type="text"]').val('');
				return;
			}

			$(this).closest('.unosignature-template-map-row').remove();
			reindexTemplateMapRows();
		});
	});
})(jQuery);
