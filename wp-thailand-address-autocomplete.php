<?php
/*
Plugin Name: WP Autocomplete Thailand Address
Plugin URI: https://github.com/mynameispond/wp-thailand-address-autocomplete
Description: Autocomplete Address Thailand
Version: 0.0.1
Author: mynameispond
Author URI: https://github.com/mynameispond
*/

define('WPATA_VERSION', '0.0.1');
define('WPATA_SLUG', 'wp-thailand-address-autocomplete');

function wpata_meta_tags()
{
    echo '<meta name="wpata-admin-url" content="' . admin_url('/') . '">';
}
add_action('wp_head', 'wpata_meta_tags', 2);
add_action('admin_head', 'wpata_meta_tags', 2);

function wpata_plugin_activate()
{
    // เมื่อ active plugin

    $wpata_plugin_version = get_option('wpata_plugin_version');
    if (empty($wpata_plugin_version)) {
        add_option('wpata_plugin_version', WPATA_VERSION);

        // สร้างฐานข้อมูล
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE `{$wpdb->prefix}wpata_province` (
            `pv_id` INT(11) NOT NULL AUTO_INCREMENT, 
            `pv_idx` INT(11) NOT NULL, 
            `pv_name_th` VARCHAR(255) NOT NULL, 
            `pv_name_en` VARCHAR(255) NOT NULL, 
            PRIMARY KEY (`pv_id`)
        ) ENGINE = MyISAM {$charset_collate};

        CREATE TABLE `{$wpdb->prefix}wpata_district` (
            `dt_id` INT(11) NOT NULL AUTO_INCREMENT, 
            `dt_idx` INT(11) NOT NULL, 
            `dt_name_th` VARCHAR(255) NOT NULL, 
            `dt_name_en` VARCHAR(255) NOT NULL, 
            `dt_pv_id` INT(11) NOT NULL, 
            PRIMARY KEY (`dt_id`)
        ) ENGINE = MyISAM {$charset_collate};

        CREATE TABLE `{$wpdb->prefix}wpata_subdistrict` (
            `sdt_id` INT(11) NOT NULL AUTO_INCREMENT, 
            `sdt_idx` INT(11) NOT NULL, 
            `sdt_name_th` VARCHAR(255) NOT NULL, 
            `sdt_name_en` VARCHAR(255) NOT NULL, 
            `sdt_pv_id` INT(11) NOT NULL, 
            `sdt_dt_id` INT(11) NOT NULL, 
            PRIMARY KEY (`sdt_id`)
        ) ENGINE = MyISAM {$charset_collate};

        CREATE TABLE `{$wpdb->prefix}wpata_postalcode` (
            `ptc_id` INT(11) NOT NULL AUTO_INCREMENT, 
            `ptc_idx` INT(11) NOT NULL, 
            `ptc_pv_id` INT(11) NOT NULL, 
            `ptc_dt_id` INT(11) NOT NULL, 
            `ptc_sdt_id` INT(11) NOT NULL, 
            PRIMARY KEY (`ptc_id`)
        ) ENGINE = MyISAM {$charset_collate};

        ";

        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta($sql);

        $json_address = file_get_contents(WP_PLUGIN_DIR . '/' . WPATA_SLUG . '/address.json');
        $arr_address = json_decode($json_address, true);
        foreach ($arr_address as $pv_idx => $pv_data) {
            $wpdb->insert(
                "{$wpdb->prefix}wpata_province",
                array(
                    'pv_idx' => $pv_idx,
                    'pv_name_th' => $pv_data['pv_name_th'],
                    'pv_name_en' => $pv_data['pv_name_en']
                )
            );
            $pv_id = $wpdb->insert_id;

            unset($pv_data['pv_name_th']);
            unset($pv_data['pv_name_en']);

            foreach ($pv_data as $dt_idx => $dt_data) {
                $wpdb->insert(
                    "{$wpdb->prefix}wpata_district",
                    array(
                        'dt_idx' => $dt_idx,
                        'dt_name_th' => $dt_data['dt_name_th'],
                        'dt_name_en' => $dt_data['dt_name_en'],
                        'dt_pv_id' => $pv_id
                    )
                );
                $dt_id = $wpdb->insert_id;

                unset($dt_data['dt_name_th']);
                unset($dt_data['dt_name_en']);

                foreach ($dt_data as $sdt_idx => $sdt_data) {
                    $wpdb->insert(
                        "{$wpdb->prefix}wpata_subdistrict",
                        array(
                            'sdt_idx' => $sdt_idx,
                            'sdt_name_th' => $sdt_data['sdt_name_th'],
                            'sdt_name_en' => $sdt_data['sdt_name_en'],
                            'sdt_pv_id' => $pv_id,
                            'sdt_dt_id' => $dt_id
                        )
                    );
                    $sdt_id = $wpdb->insert_id;

                    unset($pv_data['sdt_name_th']);
                    unset($pv_data['sdt_name_en']);

                    foreach ($sdt_data['sdt_postal_code'] as $postal_code) {
                        $wpdb->insert(
                            "{$wpdb->prefix}wpata_postalcode",
                            array(
                                'ptc_idx' => $postal_code,
                                'ptc_pv_id' => $pv_id,
                                'ptc_dt_id' => $dt_id,
                                'ptc_sdt_id' => $sdt_id
                            )
                        );
                    }
                }
            }
        }
        // @unlink(WP_PLUGIN_DIR . '/' . WPATA_SLUG . '/address.json');
    }
}
register_activation_hook(__FILE__, 'wpata_plugin_activate');

function wpata_enqueue_scripts()
{
    $dir = WP_PLUGIN_URL . '/' . WPATA_SLUG . '/assets/';
    wp_enqueue_script('wpata-js', $dir . 'wpata.js', array(), WPATA_VERSION, true);
}
add_action('admin_enqueue_scripts', 'wpata_enqueue_scripts');
add_action('wp_enqueue_scripts', 'wpata_enqueue_scripts');

function wpata_load_province_option()
{
    $lang = isset($_GET['wpatalang']) ? wp_strip_all_tags($_GET['wpatalang']) : 'th';
    global $wpdb;
    $strSql = "SELECT pv_id, pv_name_{$lang} as pv_name FROM {$wpdb->prefix}wpata_province ORDER BY pv_name_{$lang} ASC";
    $rs = $wpdb->get_results($strSql);
    if (empty($rs)) {
        echo '<option value="">' . get_option("wpata_nodata_{$lang}") . '</option>';
    } else {
        echo '<option value="">' . get_option("wpata_province_{$lang}") . '</option>';
        foreach ($rs as $data) {
            echo '<option value="' . $data->pv_id . '">' . $data->pv_name . '</option>';
        }
    }
    die();
}
add_action('wp_ajax_wpata_load_province_option', 'wpata_load_province_option');
add_action('wp_ajax_nopriv_wpata_load_province_option', 'wpata_load_province_option');

function wpata_load_district_option()
{
    $lang = isset($_GET['wpatalang']) ? wp_strip_all_tags($_GET['wpatalang']) : 'th';
    $pv_id = isset($_GET['wpataprov']) ? wp_strip_all_tags($_GET['wpataprov']) : '';
    global $wpdb;
    $strSql = "SELECT dt_id, dt_name_{$lang} as dt_name FROM {$wpdb->prefix}wpata_district";
    if (!empty($pv_id)) {
        $strSql .= " WHERE dt_pv_id='{$pv_id}'";
    }
    $strSql .= " ORDER BY dt_name_{$lang} ASC";
    $rs = $wpdb->get_results($strSql);
    if (empty($rs)) {
        echo '<option value="">' . get_option("wpata_nodata_{$lang}") . '</option>';
    } else {
        echo '<option value="">' . get_option("wpata_district_{$lang}") . '</option>';
        foreach ($rs as $data) {
            echo '<option value="' . $data->dt_id . '">' . $data->dt_name . '</option>';
        }
    }
    die();
}
add_action('wp_ajax_wpata_load_district_option', 'wpata_load_district_option');
add_action('wp_ajax_nopriv_wpata_load_district_option', 'wpata_load_district_option');

function wpata_load_subdistrict_option()
{
    $lang = isset($_GET['wpatalang']) ? wp_strip_all_tags($_GET['wpatalang']) : 'th';
    $pv_id = isset($_GET['wpataprov']) ? wp_strip_all_tags($_GET['wpataprov']) : '';
    $dt_id = isset($_GET['wpatadist']) ? wp_strip_all_tags($_GET['wpatadist']) : '';
    global $wpdb;
    $strSql = "SELECT sdt_id, sdt_name_{$lang} as sdt_name FROM {$wpdb->prefix}wpata_subdistrict WHERE 1=1";
    if (!empty($pv_id)) {
        $strSql .= " AND sdt_pv_id='{$pv_id}'";
    }
    if (!empty($dt_id)) {
        $strSql .= " AND sdt_dt_id='{$dt_id}'";
    }
    $strSql .= " ORDER BY sdt_name_{$lang} ASC";
    $rs = $wpdb->get_results($strSql);
    if (empty($rs)) {
        echo '<option value="">' . get_option("wpata_nodata_{$lang}") . '</option>';
    } else {
        echo '<option value="">' . get_option("wpata_subdistrict_{$lang}") . '</option>';
        foreach ($rs as $data) {
            echo '<option value="' . $data->sdt_id . '">' . $data->sdt_name . '</option>';
        }
    }
    die();
}
add_action('wp_ajax_wpata_load_subdistrict_option', 'wpata_load_subdistrict_option');
add_action('wp_ajax_nopriv_wpata_load_subdistrict_option', 'wpata_load_subdistrict_option');

function wpata_load_postalcode_option()
{
    $lang = isset($_GET['wpatalang']) ? wp_strip_all_tags($_GET['wpatalang']) : 'th';
    $pv_id = isset($_GET['wpataprov']) ? wp_strip_all_tags($_GET['wpataprov']) : '';
    $dt_id = isset($_GET['wpatadist']) ? wp_strip_all_tags($_GET['wpatadist']) : '';
    $sdt_id = isset($_GET['wpatasubdist']) ? wp_strip_all_tags($_GET['wpatasubdist']) : '';
    global $wpdb;
    $strSql = "SELECT ptc_id, ptc_idx FROM {$wpdb->prefix}wpata_postalcode WHERE 1=1";
    if (!empty($pv_id)) {
        $strSql .= " AND ptc_pv_id='{$pv_id}'";
    }
    if (!empty($dt_id)) {
        $strSql .= " AND ptc_dt_id='{$dt_id}'";
    }
    if (!empty($sdt_id)) {
        $strSql .= " AND ptc_sdt_id='{$sdt_id}'";
    }
    $strSql .= " ORDER BY ptc_idx ASC";
    $rs = $wpdb->get_results($strSql);
    if (empty($rs)) {
        echo '<option value="">' . get_option("wpata_nodata_{$lang}") . '</option>';
    } else {
        echo '<option value="">' . get_option("wpata_postalcode_{$lang}") . '</option>';
        foreach ($rs as $data) {
            echo '<option value="' . $data->ptc_id . '">' . $data->ptc_idx . '</option>';
        }
    }
    die();
}
add_action('wp_ajax_wpata_load_postalcode_option', 'wpata_load_postalcode_option');
add_action('wp_ajax_nopriv_wpata_load_postalcode_option', 'wpata_load_postalcode_option');
