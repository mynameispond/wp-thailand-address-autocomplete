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

	if ($('[class*="wpata-group-"]').length < 1) {
		$(province_selector).addClass('wpata-group-1');
		$(district_selector).addClass('wpata-group-1');
		$(subdistrict_selector).addClass('wpata-group-1');
		$(postalcode_selector).addClass('wpata-group-1');
	}



	if ($(province_selector).length > 0) {
		const province_value = $(province_selector).val();
		if (province_value == '' || province_value == null) {
			$(province_selector).prop('disabled', true).html(loading_option);
			$.ajax({
				url: wpata_admin_url + 'admin-ajax.php',
				type: 'GET',
				data: {
					action: 'wpata_load_province_option',
					wpatalang: lang
				},
				success: function (data) {
					$(province_selector).html(data).prop('disabled', false);
				},
				error: function (errorThrown) { console.error(errorThrown); }
			});
		} else {
			const district_value = $(district_selector).val();
			if (district_value == '' || district_value == null) {
				$(district_selector).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_district_option',
						wpatalang: lang,
						wpataprov: province_value
					},
					success: function (data) {
						$(district_selector).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			} else {
				const subdistrict_value = $(subdistrict_selector).val();
				if (subdistrict_value == '' || subdistrict_value == null) {
					$(subdistrict_selector).prop('disabled', true).html(loading_option);
					$.ajax({
						url: wpata_admin_url + 'admin-ajax.php',
						type: 'GET',
						data: {
							action: 'wpata_load_subdistrict_option',
							wpatalang: lang,
							wpatadist: district_value
						},
						success: function (data) {
							$(subdistrict_selector).html(data).prop('disabled', false);
						},
						error: function (errorThrown) { console.error(errorThrown); }
					});
				} else {
					const postalcode_value = $(postalcode_selector).val();
					if (postalcode_value == '' || postalcode_value == null) {
						$(postalcode_selector).prop('disabled', true).html(loading_option);
						$.ajax({
							url: wpata_admin_url + 'admin-ajax.php',
							type: 'GET',
							data: {
								action: 'wpata_load_postalcode_option',
								wpatalang: lang,
								wpatasubdist: subdistrict_value
							},
							success: function (data) {
								$(postalcode_selector).html(data).prop('disabled', false);
							},
							error: function (errorThrown) { console.error(errorThrown); }
						});
					}
				}
			}
		}

		$(province_selector).change(function () {
			const group = wpata_group($, $(this));
			$(subdistrict_selector.replace('-subdistrict', '-subdistrict.wpata-group-' + group)).html('').prop('disabled', true);
			$(postalcode_selector.replace('-postalcode', '-postalcode.wpata-group-' + group)).html('').prop('disabled', true);
			let province_value = $(this).val();
			if (province_value == '') {
				$(district_selector.replace('-district', '-district.wpata-group-' + group)).html('').prop('disabled', true);
			} else {
				$(district_selector.replace('-district', '-district.wpata-group-' + group)).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_district_option',
						wpatalang: lang,
						wpataprov: province_value
					},
					success: function (data) {
						$(district_selector.replace('-district', '-district.wpata-group-' + group)).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			}
		});

		$(district_selector).change(function () {
			const group = wpata_group($, $(this));
			$(postalcode_selector.replace('-postalcode', '-postalcode.wpata-group-' + group)).html('').prop('disabled', true);
			let district_value = $(this).val();
			if (district_value == '') {
				$(subdistrict_selector.replace('-subdistrict', '-subdistrict.wpata-group-' + group)).html('').prop('disabled', true);
			} else {
				$(subdistrict_selector.replace('-subdistrict', '-subdistrict.wpata-group-' + group)).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_subdistrict_option',
						wpatalang: lang,
						wpatadist: district_value
					},
					success: function (data) {
						$(subdistrict_selector.replace('-subdistrict', '-subdistrict.wpata-group-' + group)).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			}
		});

		$(subdistrict_selector).change(function () {
			const group = wpata_group($, $(this));
			let subdistrict_value = $(this).val();
			if (subdistrict_value == '') {
				$(postalcode_selector.replace('-postalcode', '-postalcode.wpata-group-' + group)).html('').prop('disabled', true);
			} else {
				$(postalcode_selector.replace('-postalcode', '-postalcode.wpata-group-' + group)).prop('disabled', true).html(loading_option);
				$.ajax({
					url: wpata_admin_url + 'admin-ajax.php',
					type: 'GET',
					data: {
						action: 'wpata_load_postalcode_option',
						wpatalang: lang,
						wpatasubdist: subdistrict_value
					},
					success: function (data) {
						$(postalcode_selector.replace('-postalcode', '-postalcode.wpata-group-' + group)).html(data).prop('disabled', false);
					},
					error: function (errorThrown) { console.error(errorThrown); }
				});
			}
		});

	}
}