(function ($) {
	'use strict';

	var WPATA_SELECTORS = {
		province: 'select.wpata-select-province, .wpata-select-province select',
		district: 'select.wpata-select-district, .wpata-select-district select',
		subdistrict: 'select.wpata-select-subdistrict, .wpata-select-subdistrict select',
		postalcode: 'select.wpata-select-postalcode, .wpata-select-postalcode select'
	};
	var WPATA_LOADING_OPTION = '<option value="">Loading...</option>';
	var wpata_instance_counter = 0;
	var wpata_events_bound = false;

	function wpata_resolved_promise() {
		return $.Deferred().resolve().promise();
	}

	function wpata_normalize_value(value) {
		if (Array.isArray(value)) {
			return value.length > 0 ? String(value[0]) : '';
		}
		if (value === undefined || value === null) {
			return '';
		}
		return String(value);
	}

	function wpata_set_initializing_flag(set, isInitializing) {
		var flagValue = isInitializing ? '1' : '0';
		[set.province, set.district, set.subdistrict, set.postalcode].forEach(function ($field) {
			if ($field && $field.length) {
				$field.attr('data-wpata-initializing', flagValue);
			}
		});
	}

	function wpata_is_initializing($field) {
		return !!($field && $field.length && $field.attr('data-wpata-initializing') === '1');
	}

	function wpata_default_ajax_url() {
		var adminUrl = $('meta[name="wpata-admin-url"]').attr('content');
		if (typeof adminUrl === 'string' && adminUrl !== '') {
			adminUrl = adminUrl.replace(/\/+$/, '');
			return adminUrl + '/admin-ajax.php';
		}

		return '/wp-admin/admin-ajax.php';
	}

	function wpata_ensure_config() {
		if (typeof window.wpataConfig !== 'object' || window.wpataConfig === null) {
			window.wpataConfig = {};
		}

		if (typeof window.wpataConfig.ajaxUrl !== 'string' || window.wpataConfig.ajaxUrl === '') {
			window.wpataConfig.ajaxUrl = wpata_default_ajax_url();
		}
	}

	function wpata_get_ajax_url() {
		wpata_ensure_config();
		return window.wpataConfig.ajaxUrl;
	}

	function wpata_extract_by_prefix(className, prefix) {
		if (typeof className !== 'string' || className === '') {
			return '';
		}

		var tokens = className.split(/\s+/);
		for (var i = 0; i < tokens.length; i++) {
			if (tokens[i].indexOf(prefix) === 0 && tokens[i].length > prefix.length) {
				return tokens[i].replace(prefix, '');
			}
		}

		return '';
	}

	function wpata_lang() {
		var lang = 'th';
		var $langElement = $('[class*="wpata-lang-"]').first();

		if ($langElement.length > 0) {
			var className = $langElement.attr('class') || '';
			var classLang = wpata_extract_by_prefix(className, 'wpata-lang-');
			if (classLang !== '') {
				lang = classLang;
			}
		} else {
			var htmlLang = $('html').attr('lang');
			if (typeof htmlLang === 'string' && htmlLang !== '') {
				lang = htmlLang.slice(0, 2);
			}
		}

		return lang;
	}

	function wpata_group($obj) {
		if (!$obj || !$obj.length) {
			return '';
		}

		var ownGroup = wpata_extract_by_prefix($obj.attr('class') || '', 'wpata-group-');
		if (ownGroup !== '') {
			return ownGroup;
		}

		var $parentWithGroup = $obj.parents('[class*="wpata-group-"]').first();
		if ($parentWithGroup.length > 0) {
			return wpata_extract_by_prefix($parentWithGroup.attr('class') || '', 'wpata-group-');
		}

		return '';
	}

	function wpata_context(context) {
		if (!context) {
			return $(document);
		}
		if (typeof context === 'function' && context.fn && context.fn.jquery) {
			return $(document);
		}
		if (context instanceof jQuery) {
			return context;
		}
		return $(context);
	}

	function wpata_find_fields($context, type) {
		var selector = WPATA_SELECTORS[type];
		if (!selector) {
			return $();
		}
		return $context.find(selector).add($context.filter(selector));
	}

	function wpata_uninitialized_fields($context, type) {
		return wpata_find_fields($context, type).filter(function () {
			return !$(this).attr('data-wpata-instance');
		});
	}

	function wpata_pool_by_group($context, type) {
		var pool = {
			grouped: {},
			ungrouped: []
		};

		wpata_uninitialized_fields($context, type).each(function () {
			var $field = $(this);
			var group = wpata_group($field);
			if (group !== '') {
				if (!pool.grouped[group]) {
					pool.grouped[group] = [];
				}
				pool.grouped[group].push($field);
			} else {
				pool.ungrouped.push($field);
			}
		});

		return pool;
	}

	function wpata_take_grouped(pool, group) {
		if (!pool.grouped[group] || pool.grouped[group].length === 0) {
			return $();
		}
		return pool.grouped[group].shift();
	}

	function wpata_take_ungrouped(pool) {
		if (pool.ungrouped.length === 0) {
			return $();
		}
		return pool.ungrouped.shift();
	}

	// จับคู่ select เป็นชุดเดียวกัน เพื่อให้ 1 หน้าใช้งานได้หลายชุดพร้อมกัน
	function wpata_discover_sets($context) {
		var provinceGrouped = {};
		var provinceUngrouped = [];
		var sets = [];

		wpata_uninitialized_fields($context, 'province').each(function () {
			var $province = $(this);
			var group = wpata_group($province);
			if (group !== '') {
				if (!provinceGrouped[group]) {
					provinceGrouped[group] = [];
				}
				provinceGrouped[group].push($province);
			} else {
				provinceUngrouped.push($province);
			}
		});

		Object.keys(provinceGrouped).forEach(function (group) {
			provinceGrouped[group].forEach(function ($province) {
				sets.push({
					group: group,
					mode: 'grouped',
					province: $province
				});
			});
		});

		provinceUngrouped.forEach(function ($province) {
			sets.push({
				group: '',
				mode: 'ungrouped',
				province: $province
			});
		});

		if (sets.length === 0) {
			return sets;
		}

		var districtPool = wpata_pool_by_group($context, 'district');
		var subdistrictPool = wpata_pool_by_group($context, 'subdistrict');
		var postalcodePool = wpata_pool_by_group($context, 'postalcode');

		sets.forEach(function (set) {
			if (set.mode === 'grouped') {
				set.district = wpata_take_grouped(districtPool, set.group);
				set.subdistrict = wpata_take_grouped(subdistrictPool, set.group);
				set.postalcode = wpata_take_grouped(postalcodePool, set.group);
			} else {
				set.district = wpata_take_ungrouped(districtPool);
				set.subdistrict = wpata_take_ungrouped(subdistrictPool);
				set.postalcode = wpata_take_ungrouped(postalcodePool);
			}
		});

		return sets;
	}

	function wpata_get_initial_value($field) {
		if (!$field || !$field.length) {
			return '';
		}

		var dataValue = $field.attr('data-wpata-value');
		if (typeof dataValue === 'string') {
			return dataValue;
		}

		return wpata_normalize_value($field.val());
	}

	function wpata_apply_value($field, value) {
		if (!$field || !$field.length) {
			return '';
		}

		var normalized = wpata_normalize_value(value);
		if (normalized === '') {
			$field.val('');
			return '';
		}

		$field.val(normalized);
		if ($field.val() === null) {
			$field.val('');
			return '';
		}

		return wpata_normalize_value($field.val());
	}

	function wpata_reset_field($field) {
		if (!$field || !$field.length) {
			return;
		}
		$field.html('').prop('disabled', true);
	}

	function wpata_request_options(action, data) {
		var requestData = $.extend({}, data || {}, { action: action });
		return $.ajax({
			url: wpata_get_ajax_url(),
			type: 'GET',
			data: requestData
		});
	}

	function wpata_load_options($field, action, data, selectedValue) {
		if (!$field || !$field.length) {
			return wpata_resolved_promise();
		}

		$field.prop('disabled', true).html(WPATA_LOADING_OPTION);

		return wpata_request_options(action, data)
			.done(function (html) {
				$field.html(html).prop('disabled', false);
				wpata_apply_value($field, selectedValue);
			})
			.fail(function (errorThrown) {
				$field.prop('disabled', false);
				console.error(errorThrown);
			});
	}

	function wpata_mark_field($field, instanceId, fieldName, lang) {
		if (!$field || !$field.length) {
			return;
		}

		$field.attr('data-wpata-instance', instanceId);
		$field.attr('data-wpata-field', fieldName);
		$field.attr('data-wpata-lang', lang);
	}

	function wpata_get_instance_fields(instanceId) {
		if (!instanceId) {
			return null;
		}

		var selector = '[data-wpata-instance="' + instanceId + '"]';
		return {
			instanceId: instanceId,
			province: $(selector + '[data-wpata-field="province"]').first(),
			district: $(selector + '[data-wpata-field="district"]').first(),
			subdistrict: $(selector + '[data-wpata-field="subdistrict"]').first(),
			postalcode: $(selector + '[data-wpata-field="postalcode"]').first()
		};
	}

	function wpata_lang_from_field($field) {
		var fieldLang = $field && $field.length ? ($field.attr('data-wpata-lang') || '') : '';
		return fieldLang !== '' ? fieldLang : wpata_lang();
	}

	function wpata_initialize_set(set, lang) {
		var instanceId = 'wpata-' + (++wpata_instance_counter);
		var initialProvince = wpata_get_initial_value(set.province);
		var initialDistrict = wpata_get_initial_value(set.district);
		var initialSubdistrict = wpata_get_initial_value(set.subdistrict);
		var initialPostalcode = wpata_get_initial_value(set.postalcode);

		wpata_mark_field(set.province, instanceId, 'province', lang);
		wpata_mark_field(set.district, instanceId, 'district', lang);
		wpata_mark_field(set.subdistrict, instanceId, 'subdistrict', lang);
		wpata_mark_field(set.postalcode, instanceId, 'postalcode', lang);
		wpata_set_initializing_flag(set, true);

		return wpata_load_options(set.province, 'wpata_load_province_option', { wpatalang: lang }, initialProvince)
			.then(function () {
				var provinceValue = wpata_normalize_value(set.province.val());
				if (!set.district || !set.district.length) {
					return wpata_resolved_promise();
				}

				if (provinceValue === '') {
					wpata_reset_field(set.district);
					wpata_reset_field(set.subdistrict);
					wpata_reset_field(set.postalcode);
					return wpata_resolved_promise();
				}

				return wpata_load_options(set.district, 'wpata_load_district_option', {
					wpatalang: lang,
					wpataprov: provinceValue
				}, initialDistrict).then(function () {
					var districtValue = wpata_normalize_value(set.district.val());
					if (!set.subdistrict || !set.subdistrict.length) {
						return wpata_resolved_promise();
					}

					if (districtValue === '') {
						wpata_reset_field(set.subdistrict);
						wpata_reset_field(set.postalcode);
						return wpata_resolved_promise();
					}

					return wpata_load_options(set.subdistrict, 'wpata_load_subdistrict_option', {
						wpatalang: lang,
						wpataprov: provinceValue,
						wpatadist: districtValue
					}, initialSubdistrict).then(function () {
						var subdistrictValue = wpata_normalize_value(set.subdistrict.val());
						if (!set.postalcode || !set.postalcode.length) {
							return wpata_resolved_promise();
						}

						if (subdistrictValue === '') {
							wpata_reset_field(set.postalcode);
							return wpata_resolved_promise();
						}

						return wpata_load_options(set.postalcode, 'wpata_load_postalcode_option', {
							wpatalang: lang,
							wpataprov: provinceValue,
							wpatadist: districtValue,
							wpatasubdist: subdistrictValue
						}, initialPostalcode);
					});
				});
			})
			.always(function () {
			wpata_set_initializing_flag(set, false);
		});
	}

	function wpata_bind_events() {
		if (wpata_events_bound) {
			return;
		}
		wpata_events_bound = true;

		$(document).on('change.wpata', WPATA_SELECTORS.province, function () {
			var $province = $(this);
			if (wpata_is_initializing($province)) {
				return;
			}
			var fields = wpata_get_instance_fields($province.attr('data-wpata-instance'));
			if (!fields) {
				return;
			}

			var lang = wpata_lang_from_field($province);
			var provinceValue = wpata_normalize_value($province.val());

			wpata_reset_field(fields.subdistrict);
			wpata_reset_field(fields.postalcode);

			if (!fields.district || !fields.district.length) {
				return;
			}

			if (provinceValue === '') {
				wpata_reset_field(fields.district);
				return;
			}

			wpata_load_options(fields.district, 'wpata_load_district_option', {
				wpatalang: lang,
				wpataprov: provinceValue
			}, '');
		});

		$(document).on('change.wpata', WPATA_SELECTORS.district, function () {
			var $district = $(this);
			if (wpata_is_initializing($district)) {
				return;
			}
			var fields = wpata_get_instance_fields($district.attr('data-wpata-instance'));
			if (!fields) {
				return;
			}

			var lang = wpata_lang_from_field($district);
			var districtValue = wpata_normalize_value($district.val());
			var provinceValue = fields.province && fields.province.length ? wpata_normalize_value(fields.province.val()) : '';

			wpata_reset_field(fields.postalcode);

			if (!fields.subdistrict || !fields.subdistrict.length) {
				return;
			}

			if (districtValue === '') {
				wpata_reset_field(fields.subdistrict);
				return;
			}

			wpata_load_options(fields.subdistrict, 'wpata_load_subdistrict_option', {
				wpatalang: lang,
				wpataprov: provinceValue,
				wpatadist: districtValue
			}, '');
		});

		$(document).on('change.wpata', WPATA_SELECTORS.subdistrict, function () {
			var $subdistrict = $(this);
			if (wpata_is_initializing($subdistrict)) {
				return;
			}
			var fields = wpata_get_instance_fields($subdistrict.attr('data-wpata-instance'));
			if (!fields) {
				return;
			}

			var lang = wpata_lang_from_field($subdistrict);
			var subdistrictValue = wpata_normalize_value($subdistrict.val());
			var provinceValue = fields.province && fields.province.length ? wpata_normalize_value(fields.province.val()) : '';
			var districtValue = fields.district && fields.district.length ? wpata_normalize_value(fields.district.val()) : '';

			if (!fields.postalcode || !fields.postalcode.length) {
				return;
			}

			if (subdistrictValue === '') {
				wpata_reset_field(fields.postalcode);
				return;
			}

			wpata_load_options(fields.postalcode, 'wpata_load_postalcode_option', {
				wpatalang: lang,
				wpataprov: provinceValue,
				wpatadist: districtValue,
				wpatasubdist: subdistrictValue
			}, '');
		});
	}

	function wpata_setup(context) {
		var $context = wpata_context(context);
		var lang = wpata_lang();
		var sets = wpata_discover_sets($context);

		wpata_bind_events();
		sets.forEach(function (set) {
			wpata_initialize_set(set, lang);
		});
	}

	// รองรับการเรียกซ้ำหลัง append element ใหม่ด้วย JS/AJAX
	window.wpataInit = function (context) {
		wpata_setup(context || document);
	};
	window.wpataRefresh = window.wpataInit;

	// คงชื่อฟังก์ชันเดิมไว้เพื่อไม่ให้โค้ดเก่าแตก
	window.wpata_setup = wpata_setup;
	window.wpata_lang = wpata_lang;
	window.wpata_group = function ($unused, obj) {
		return wpata_group($(obj));
	};

	jQuery(function () {
		window.wpataInit(document);
	});

	$(document).on('wpata:init', function (event, context) {
		window.wpataInit(context || document);
	});
})(jQuery);
