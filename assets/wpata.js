var wpata_admin_url = jQuery('meta[name="wpata-admin-url"]').attr('content');
jQuery(function ($) {
    wpata_setup($);
});

function wpata_setup($) {
    const province_selector = 'select.wpata-select-province, .wpata-select-province select';
    const district_selector = 'select.wpata-select-district, .wpata-select-district select';
    const subdistrict_selector = 'select.wpata-select-subdistrict, .wpata-select-subdistrict select';
    const postalcode_selector = 'select.wpata-select-postalcode, .wpata-select-postalcode select';
    const loading_option = '<option value="">Loading...</option>';
    let lang = 'th';

    if ($('[class*="wpata-lang-"]').length > 0) {
        const wpata_lang = $('[class*="wpata-lang-"]')[0].getAttribute('class');
        const arr_lang = wpata_lang.split(" ");
        let str2 = '';
        arr_lang.forEach((str) => {
            if (str.includes("wpata-lang-")) {
                if (str != '' && str != null && str != undefined) {
                    str2 = str;
                }
            }
        });
        if (str2 != '') {
            const regex = /wpata-lang-/i;
            lang = str2.replace(regex, '');
        }
    } else {
        if (html_lang != '' && html_lang != null && html_lang != undefined) {
            const html_lang = $('html').attr('lang');
            lang = html_lang.slice(0, 2);
        }
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
            $(subdistrict_selector).html('').prop('disabled', true);
            $(postalcode_selector).html('').prop('disabled', true);
            let province_value = $(this).val();
            if (province_value == '') {
                $(district_selector).html('').prop('disabled', true);
            } else {
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
            }
        });

        $(district_selector).change(function () {
            $(postalcode_selector).html('').prop('disabled', true);
            let district_value = $(this).val();
            if (district_value == '') {
                $(subdistrict_selector).html('').prop('disabled', true);
            } else {
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
            }
        });

        $(subdistrict_selector).change(function () {
            let subdistrict_value = $(this).val();
            if (subdistrict_value == '') {
                $(postalcode_selector).html('').prop('disabled', true);
            } else {
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
        });
    }
}