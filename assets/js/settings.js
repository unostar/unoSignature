(function ($) {
	'use strict';

	function initEnhancedSelects($context) {
		$context.find('.wc-product-search').filter(':not(.enhanced)').each(function () {
			var $select = $(this);
			var searchAction = window.unosignatureSettings?.productSearchAction || 'unosignature_search_products';
			var selectArgs = {
				allowClear: $select.data('allow_clear') ? true : false,
				placeholder: $select.data('placeholder') || '',
				minimumInputLength: $select.data('minimum_input_length') ? parseInt($select.data('minimum_input_length'), 10) : 2,
				width: '100%',
				escapeMarkup: function (markup) {
					return markup;
				},
				ajax: {
					url: window.wc_enhanced_select_params?.ajax_url || window.ajaxurl,
					dataType: 'json',
					delay: 250,
					data: function (params) {
						return {
							term: params.term,
							action: searchAction,
							security: window.wc_enhanced_select_params?.search_products_nonce || ''
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
				}
			};

			if (window.wc_enhanced_select_params) {
				selectArgs.language = {
					noResults: function () {
						return wc_enhanced_select_params.i18n_no_matches;
					},
					searching: function () {
						return wc_enhanced_select_params.i18n_searching;
					}
				};
			}

			$select.selectWoo(selectArgs).addClass('enhanced');
		});

		$context.find('.wc-enhanced-select').filter(':not(.enhanced)').each(function () {
			var $select = $(this);
			$select.selectWoo({
				allowClear: false,
				placeholder: $select.data('placeholder') || '',
				minimumResultsForSearch: 10,
				width: '100%'
			}).addClass('enhanced');
		});
	}

	function getRuleSummaryLabel($row, ruleNumber) {
		var label = 'Rule ' + ruleNumber;
		var agreementGroup = $.trim($row.find('[name*="[agreement_group]"]').val() || '');
		var templateId = $.trim($row.find('[name*="[template_id]"]').val() || '');

		if (agreementGroup) {
			label += ' — ' + agreementGroup;
		}

		if (templateId) {
			label += ' — ' + templateId;
		}

		return label;
	}

	function reindexTemplateMapRows() {
		var optionKey = window.unosignatureSettings?.optionKey || 'unosignature_settings';

		$('#unosignature-template-map .unosignature-template-map-row').each(function (index) {
			var ruleNumber = index + 1;
			var $row = $(this);

			$row.children('summary').first().text(getRuleSummaryLabel($row, ruleNumber));

			$row.find('[name]').each(function () {
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

	function toggleEmptyState($container) {
		var $empty = $container.find('.unosignature-template-map-empty');
		var hasRows = $container.find('.unosignature-template-map-row').length > 0;

		if (hasRows) {
			$empty.remove();
			return;
		}

		if (!$empty.length) {
			$container.append(
				'<p class="unosignature-template-map-empty description">No signing rules configured.</p>'
			);
		}
	}

	$(function () {
		var $container = $('#unosignature-template-map');
		var $template = $('#unosignature-template-map-row-template');

		if (!$container.length || !$template.length) {
			return;
		}

		initEnhancedSelects($container);

		$container.on('input', '[name*="[agreement_group]"], [name*="[template_id]"]', function () {
			var $row = $(this).closest('.unosignature-template-map-row');
			var index = $container.find('.unosignature-template-map-row').index($row);

			$row.children('summary').first().text(getRuleSummaryLabel($row, index + 1));
		});

		$('#unosignature-add-template-map-row').on('click', function () {
			var index = $container.find('.unosignature-template-map-row').length;
			var html = $template.html().replace(/__INDEX__/g, String(index));
			var $row = $(html);

			$container.append($row);
			toggleEmptyState($container);
			reindexTemplateMapRows();
			initEnhancedSelects($row);
		});

		$container.on('click', '.unosignature-remove-template-map-row', function () {
			$(this).closest('.unosignature-template-map-row').remove();
			reindexTemplateMapRows();
			toggleEmptyState($container);
		});
	});
})(jQuery);
