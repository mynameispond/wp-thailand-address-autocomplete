var wpata_admin_url = jQuery('meta[name="wpata-admin-url"]').attr('content');
jQuery(function ($) {
	wpata_setup($);
});

function wpata_lang($) {
	let lang = 'th';
	if ($('[class*="wpata-lang-"]').length > 0) {
		const wpata_lang = $('[class*="wpata-lang-"]')[0].getAttribute('class');
		const arr_lang = wpata_lang.split(" ");
		let str2 = '';
		arr_lang.forEach((str) => {
			if (str.includes("wpata-lang-")) {
				if (str != undefined && str != '' && str != null) {
					str2 = str;
				}
			}
		});
		if (str2 != '') {
			const regex = /wpata-lang-/i;
			lang = str2.replace(regex, '');
		}
	} else {
		const html_lang = $('html').attr('lang');
		if (html_lang != undefined && html_lang != '' && html_lang != null) {
			lang = html_lang.slice(0, 2);
		}
	}
	return lang;
}

function wpata_group($, obj) {
	const wpata_group = obj.attr('class');
	const arr_group = wpata_group.split(" ");
	let str2 = '';
	arr_group.forEach((str) => {
		if (str.includes("wpata-group-")) {
			if (str != undefined && str != '' && str != null) {
				str2 = str;
			}
		}
	});
	if (str2 != '') {
		const regex = /wpata-group-/i;
		return str2.replace(regex, '');
	}
}

function wpata_setup($) {
	const province_selector = 'select.wpata-select-province, .wpata-select-province select';
	const district_selector = 'select.wpata-select-district, .wpata-select-district select';
	const subdistrict_selector = 'select.wpata-select-subdistrict, .wpata-select-subdistrict select';
	const postalcode_selector = 'select.wpata-select-postalcode, .wpata-select-postalcode select';
	const loading_option = '<option value="">Loading...</option>';
	let lang = wpata_lang($);

	// find maximum of address select group
	let group_max = 0;
	$('[class*="wpata-group-"]').each(function (index) {
		const group = parseInt(wpata_group($, $(this)));
		if (group > group_max) {
			group_max = group;
		}
	});

	if (group_max > 0) {

		// find province ungroup
		let group_i = group_max;
		$(province_selector).each(function (index) {
			const group = parseInt(wpata_group($, $(this)));
			if (isNaN(group)) {
				++group_i;
				$(this).addClass('wpata-group-' + group_i);
			}
		});

		// find district ungroup
		group_i = group_max;
		$(district_selector).each(function (index) {
			const group = parseInt(wpata_group($, $(this)));
			if (isNaN(group)) {
				++group_i;
				$(this).addClass('wpata-group-' + group_i);
			}
		});

		// find subdistrict ungroup
		group_i = group_max;
		$(subdistrict_selector).each(function (index) {
			const group = parseInt(wpata_group($, $(this)));
			if (isNaN(group)) {
				++group_i;
				$(this).addClass('wpata-group-' + group_i);
			}
		});

		// find postalcode ungroup
		group_i = group_max;
		$(postalcode_selector).each(function (index) {
			const group = parseInt(wpata_group($, $(this)));
			if (isNaN(group)) {
				++group_i;
				$(this).addClass('wpata-group-' + group_i);
			}
		});

		// recalc group maximum
		$('[class*="wpata-group-"]').each(function (index) {
			const group = parseInt(wpata_group($, $(this)));
			if (group > group_max) {
				group_max = group;
			}
		});

		for (let group_ii = 1; group_ii <= group_max; ++group_ii) {
			const province_group_selector = province_selector.replace(".wpata-select", '.wpata-group-' + group_ii + '.wpata-select');
			const district_group_selector = district_selector.replace(".wpata-select", '.wpata-group-' + group_ii + '.wpata-select');
			const subdistrict_group_selector = subdistrict_selector.replace(".wpata-select", '.wpata-group-' + group_ii + '.wpata-select');
			const postalcode_group_selector = postalcode_selector.replace(".wpata-select", '.wpata-group-' + group_ii + '.wpata-select');

			const province_value = $(province_group_selector).val();
			$(province_group_selector).prop('disabled', true);
			$.ajax({
				url: wpata_admin_url + 'admin-ajax.php',
				type: 'GET',
				data: {
					action: 'wpata_load_province_option',
					wpatalang: lang
				},
				success: function (data) {
					$(province_group_selector).html(data).prop('disabled', false).val(province_value);
				},
				error: function (errorThrown) { console.error(errorThrown); }
			});

			if (province_value == '' || province_value == null) {
			} else {
				const district_value = $(district_group_selector).val();
				$(district_group_selector).prop('disabled', true);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_district_option',
						wpatalang: lang,
						wpataprov: province_value
					},
					success: function (data) {
						$(district_group_selector).html(data).prop('disabled', false).val(district_value);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});

				if (district_value == '' || district_value == null) {
				} else {
					const subdistrict_value = $(subdistrict_group_selector).val();
					$(subdistrict_group_selector).prop('disabled', true);
					$.ajax({
						url: wpata_admin_url + 'admin-ajax.php',
						type: 'GET',
						data: {
							action: 'wpata_load_subdistrict_option',
							wpatalang: lang,
							wpatadist: district_value
						},
						success: function (data) {
							$(subdistrict_group_selector).html(data).prop('disabled', false).val(subdistrict_value);
						},
						error: function (errorThrown) { console.error(errorThrown); }
					});

					if (subdistrict_value == '' || subdistrict_value == null) {
					} else {
						const postalcode_value = $(postalcode_group_selector).val();
						$(postalcode_group_selector).prop('disabled', true);
						$.ajax({
							url: wpata_admin_url + 'admin-ajax.php',
							type: 'GET',
							data: {
								action: 'wpata_load_postalcode_option',
								wpatalang: lang,
								wpatasubdist: subdistrict_value
							},
							success: function (data) {
								$(postalcode_group_selector).html(data).prop('disabled', false).val(postalcode_value);
							},
							error: function (errorThrown) { console.error(errorThrown); }
						});

						if (postalcode_value == '' || postalcode_value == null) {

						}
					}
				}
			}
		}
	}

	if ($(province_selector).length > 0) {
		$(province_selector).change(function () {
			const group = wpata_group($, $(this));
			const district_group_selector = district_selector.replace(".wpata-select", '.wpata-group-' + group + '.wpata-select');
			const subdistrict_group_selector = subdistrict_selector.replace(".wpata-select", '.wpata-group-' + group + '.wpata-select');
			const postalcode_group_selector = postalcode_selector.replace(".wpata-select", '.wpata-group-' + group + '.wpata-select');

			$(subdistrict_group_selector).html('').prop('disabled', true);
			$(postalcode_group_selector).html('').prop('disabled', true);
			let province_value = $(this).val();
			if (province_value == '') {
				$(district_group_selector).html('').prop('disabled', true);
			} else {
				$(district_group_selector).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_district_option',
						wpatalang: lang,
						wpataprov: province_value
					},
					success: function (data) {
						$(district_group_selector).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			}
		});

		$(district_selector).change(function () {
			const group = wpata_group($, $(this));
			const subdistrict_group_selector = subdistrict_selector.replace(".wpata-select", '.wpata-group-' + group + '.wpata-select');
			const postalcode_group_selector = postalcode_selector.replace(".wpata-select", '.wpata-group-' + group + '.wpata-select');
			
			$(postalcode_group_selector).html('').prop('disabled', true);
			let district_value = $(this).val();
			if (district_value == '') {
				$(subdistrict_group_selector).html('').prop('disabled', true);
			} else {
				$(subdistrict_group_selector).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_subdistrict_option',
						wpatalang: lang,
						wpatadist: district_value
					},
					success: function (data) {
						$(subdistrict_group_selector).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			}
		});

		$(subdistrict_selector).change(function () {
			const group = wpata_group($, $(this));
			const postalcode_group_selector = postalcode_selector.replace(".wpata-select", '.wpata-group-' + group + '.wpata-select');

			let subdistrict_value = $(this).val();
			if (subdistrict_value == '') {
				$(postalcode_group_selector).html('').prop('disabled', true);
			} else {
				$(postalcode_group_selector).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_postalcode_option',
						wpatalang: lang,
						wpatasubdist: subdistrict_value
					},
					success: function (data) {
						$(postalcode_group_selector).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			}
		});

	}
}