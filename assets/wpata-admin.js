(function ($) {
	'use strict';

	if (typeof wpataAdminConfig === 'undefined') {
		return;
	}

	function wpataAdminLang() {
		var configuredLang = (wpataAdminConfig.lang || '').toString().toLowerCase();
		if (configuredLang === 'th' || configuredLang === 'en') {
			return configuredLang;
		}
		return 'th';
	}

	function wpataRequest(action, data) {
		return $.ajax({
			url: wpataAdminConfig.ajaxUrl,
			type: 'GET',
			data: $.extend({}, data || {}, { action: action })
		});
	}

	function wpataParseOptions(html) {
		var options = [];
		var $tmp = $('<select></select>').html(html);

		$tmp.find('option').each(function () {
			var value = $(this).attr('value');
			if (value === undefined || value === '') {
				return;
			}
			options.push({
				value: String(value),
				label: $(this).text()
			});
		});

		return options;
	}

	function wpataSetLoading($select) {
		if (!$select.length) {
			return;
		}
		$select.prop('disabled', true);
		$select.html('<option value="0">' + wpataAdminConfig.loadingText + '</option>');
	}

	function wpataFillSelect($select, options, emptyLabel) {
		if (!$select.length) {
			return;
		}

		$select.empty();
		if (!options.length) {
			$select.append($('<option></option>').val('0').text(emptyLabel));
		} else {
			options.forEach(function (item) {
				$select.append($('<option></option>').val(item.value).text(item.label));
			});
		}
		$select.prop('disabled', false);
	}

	function wpataSelectValue($select) {
		if (!$select.length) {
			return '0';
		}
		var value = $select.val();
		if (value === undefined || value === null || value === '') {
			return '0';
		}
		return String(value);
	}

	function wpataLoadDistrict($form, provinceId) {
		var deferred = $.Deferred();
		var $district = $form.find('.wpata-filter-district');

		if (!$district.length) {
			deferred.resolve('0');
			return deferred.promise();
		}

		if (provinceId === '0') {
			wpataFillSelect($district, [], wpataAdminConfig.noDistrictText);
			deferred.resolve('0');
			return deferred.promise();
		}

		wpataSetLoading($district);
		wpataRequest('wpata_load_district_option', {
			wpatalang: wpataAdminLang(),
			wpataprov: provinceId
		}).done(function (html) {
			var options = wpataParseOptions(html);
			wpataFillSelect($district, options, wpataAdminConfig.noDistrictText);
			deferred.resolve(wpataSelectValue($district));
		}).fail(function () {
			wpataFillSelect($district, [], wpataAdminConfig.noDistrictText);
			deferred.resolve('0');
		});

		return deferred.promise();
	}

	function wpataLoadSubdistrict($form, provinceId, districtId) {
		var deferred = $.Deferred();
		var $subdistrict = $form.find('.wpata-filter-subdistrict');

		if (!$subdistrict.length) {
			deferred.resolve('0');
			return deferred.promise();
		}

		if (provinceId === '0' || districtId === '0') {
			wpataFillSelect($subdistrict, [], wpataAdminConfig.noSubdistrictText);
			deferred.resolve('0');
			return deferred.promise();
		}

		wpataSetLoading($subdistrict);
		wpataRequest('wpata_load_subdistrict_option', {
			wpatalang: wpataAdminLang(),
			wpataprov: provinceId,
			wpatadist: districtId
		}).done(function (html) {
			var options = wpataParseOptions(html);
			wpataFillSelect($subdistrict, options, wpataAdminConfig.noSubdistrictText);
			deferred.resolve(wpataSelectValue($subdistrict));
		}).fail(function () {
			wpataFillSelect($subdistrict, [], wpataAdminConfig.noSubdistrictText);
			deferred.resolve('0');
		});

		return deferred.promise();
	}

	$(document).on('change', '.wpata-filter-form .wpata-filter-province', function () {
		var $form = $(this).closest('.wpata-filter-form');
		var provinceId = wpataSelectValue($(this));
		var $district = $form.find('.wpata-filter-district');
		var $subdistrict = $form.find('.wpata-filter-subdistrict');

		if (!$district.length) {
			return;
		}

		wpataLoadDistrict($form, provinceId).done(function (districtId) {
			if (!$subdistrict.length) {
				return;
			}

			wpataLoadSubdistrict($form, provinceId, districtId);
		});
	});

	$(document).on('change', '.wpata-filter-form .wpata-filter-district', function () {
		var $form = $(this).closest('.wpata-filter-form');
		var $subdistrict = $form.find('.wpata-filter-subdistrict');
		var provinceId = wpataSelectValue($form.find('.wpata-filter-province'));
		var districtId = wpataSelectValue($(this));

		if (!$subdistrict.length) {
			return;
		}

		wpataLoadSubdistrict($form, provinceId, districtId);
	});
})(jQuery);
