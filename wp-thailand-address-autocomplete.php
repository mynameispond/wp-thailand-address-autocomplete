<?php
/*
Plugin Name: WP Autocomplete Thailand Address
Plugin URI: https://github.com/mynameispond/wp-thailand-address-autocomplete
Description: Autocomplete Address Thailand
Version: 1.0.14
Author: mynameispond
Author URI: https://github.com/mynameispond
Update URI: https://github.com/mynameispond/wp-thailand-address-autocomplete
*/

if (!defined('ABSPATH')) {
	exit;
}

define('WPATA_VERSION', '1.0.14');
define('WPATA_SLUG', 'wp-thailand-address-autocomplete');
define('WPATA_GITHUB_URL', 'https://github.com/mynameispond/wp-thailand-address-autocomplete');
if (!defined('WPATA_GITHUB_BRANCH')) {
	define('WPATA_GITHUB_BRANCH', 'main');
}

function wpata_boot_update_checker()
{
	if (!is_admin()) {
		return;
	}

	static $initialized = false;
	if ($initialized) {
		return;
	}

	$plugin_dir = plugin_dir_path(__FILE__);
	$autoload_candidates = array(
		$plugin_dir . 'vendor/autoload.php',
		$plugin_dir . 'plugin-update-checker/plugin-update-checker.php',
		$plugin_dir . 'lib/plugin-update-checker/plugin-update-checker.php',
	);

	// โหลดไลบรารีเฉพาะตอนยังไม่พบคลาส เพื่อรองรับทั้งแบบ Composer และแบบวางโฟลเดอร์เอง
	if (!class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory') && !class_exists('Puc_v4_Factory')) {
		$loaded = false;
		foreach ($autoload_candidates as $path) {
			if (file_exists($path)) {
				require_once $path;
				$loaded = true;
				break;
			}
		}

		if (!$loaded) {
			return;
		}
	}

	$checker = null;
	if (class_exists('\YahnisElsts\PluginUpdateChecker\v5\PucFactory')) {
		$checker = \YahnisElsts\PluginUpdateChecker\v5\PucFactory::buildUpdateChecker(
			WPATA_GITHUB_URL,
			__FILE__,
			WPATA_SLUG
		);
	} elseif (class_exists('Puc_v4_Factory')) {
		$checker = Puc_v4_Factory::buildUpdateChecker(
			WPATA_GITHUB_URL,
			__FILE__,
			WPATA_SLUG
		);
	}

	if (!$checker) {
		return;
	}

	$initialized = true;

	if (method_exists($checker, 'setBranch')) {
		$checker->setBranch(WPATA_GITHUB_BRANCH);
	}

	if (method_exists($checker, 'getVcsApi')) {
		$vcs_api = $checker->getVcsApi();
		if (is_object($vcs_api)) {
			$zip_asset_pattern = '/\.zip($|[?&#])/i';
			if (method_exists($vcs_api, 'enableReleaseAssets')) {
				// บังคับเลือกเฉพาะ release asset ที่เป็นไฟล์ .zip เพื่อลดปัญหาอัปเดตแตกไฟล์ไม่ได้
				$vcs_api->enableReleaseAssets($zip_asset_pattern);
			} elseif (method_exists($vcs_api, 'setEnableReleaseAssets')) {
				$vcs_api->setEnableReleaseAssets(true);
			}
		}
	}

	$github_token = defined('WPATA_GITHUB_TOKEN') ? trim((string) WPATA_GITHUB_TOKEN) : '';
	if ($github_token !== '' && method_exists($checker, 'setAuthentication')) {
		$checker->setAuthentication($github_token);
	}
}
add_action('plugins_loaded', 'wpata_boot_update_checker', 20);

function wpata_meta_tags()
{
	echo '<meta name="wpata-admin-url" content="' . admin_url('/') . '">';
}
add_action('wp_head', 'wpata_meta_tags', 2);
add_action('admin_head', 'wpata_meta_tags', 2);

// รองรับเฉพาะภาษา th / en เพื่อความปลอดภัยตอนเลือกคอลัมน์ใน SQL
function wpata_sanitize_lang($lang)
{
	$lang = strtolower(substr((string) $lang, 0, 2));
	return in_array($lang, array('th', 'en'), true) ? $lang : 'th';
}

function wpata_request_lang()
{
	$lang = isset($_GET['wpatalang']) ? sanitize_text_field(wp_unslash($_GET['wpatalang'])) : 'th';
	return wpata_sanitize_lang($lang);
}

function wpata_require_login_for_public_ajax()
{
	$require_login = false;

	if (defined('WPATA_REQUIRE_LOGIN_FOR_PUBLIC_AJAX')) {
		$require_login = (bool) WPATA_REQUIRE_LOGIN_FOR_PUBLIC_AJAX;
	} else {
		$option_value = get_option('wpata_require_login_for_public_ajax', '0');
		$require_login = $option_value === '1';
	}

	return (bool) apply_filters('wpata_require_login_for_public_ajax', $require_login);
}

function wpata_block_public_ajax_if_required()
{
	if (!wpata_require_login_for_public_ajax() || is_user_logged_in()) {
		return false;
	}

	status_header(403);
	wp_die(esc_html__('You must be logged in to use this address endpoint.', 'default'));
	return true;
}

function wpata_option_label($key, $lang)
{
	$defaults = array(
		'nodata' => array('th' => 'ไม่พบข้อมูล', 'en' => 'No data'),
		'province' => array('th' => 'เลือกจังหวัด', 'en' => 'Select province'),
		'district' => array('th' => 'เลือกอำเภอ/เขต', 'en' => 'Select district'),
		'subdistrict' => array('th' => 'เลือกตำบล/แขวง', 'en' => 'Select subdistrict'),
		'postalcode' => array('th' => 'เลือกรหัสไปรษณีย์', 'en' => 'Select postal code'),
	);

	$option_value = get_option("wpata_{$key}_{$lang}");
	if (is_string($option_value) && $option_value !== '') {
		return $option_value;
	}

	return isset($defaults[$key][$lang]) ? $defaults[$key][$lang] : '';
}

function wpata_table_name($name)
{
	global $wpdb;
	return "{$wpdb->prefix}wpata_{$name}";
}

function wpata_register_admin_menu()
{
	add_management_page(
		'WP Thailand Address',
		'WP Thailand Address',
		'manage_options',
		'wpata-admin',
		'wpata_render_admin_router_page'
	);
}
add_action('admin_menu', 'wpata_register_admin_menu', 99);

function wpata_move_admin_menu_to_bottom()
{
	global $submenu;

	if (!isset($submenu['tools.php']) || !is_array($submenu['tools.php'])) {
		return;
	}

	foreach ($submenu['tools.php'] as $index => $item) {
		if (!isset($item[2]) || $item[2] !== 'wpata-admin') {
			continue;
		}

		// ย้ายเมนูปลั๊กอินนี้ไปไว้ท้ายสุดของเมนู Tools
		$target_menu = $item;
		unset($submenu['tools.php'][$index]);
		$submenu['tools.php'][] = $target_menu;
		$submenu['tools.php'] = array_values($submenu['tools.php']);
		break;
	}
}
add_action('admin_menu', 'wpata_move_admin_menu_to_bottom', 9999);

function wpata_enqueue_admin_assets()
{
	$page = isset($_GET['page']) ? sanitize_key(wp_unslash($_GET['page'])) : '';
	$view = isset($_GET['wpata_view']) ? sanitize_key(wp_unslash($_GET['wpata_view'])) : 'overview';
	$allowed_pages = array('wpata-admin');
	if (!in_array($page, $allowed_pages, true)) {
		return;
	}

	$dir = WP_PLUGIN_URL . '/' . WPATA_SLUG . '/assets/';
	wp_enqueue_style('wpata-admin-css', $dir . 'wpata-admin.css', array(), WPATA_VERSION);

	if ($page === 'wpata-admin' && $view === 'data') {
		wp_enqueue_script('wpata-admin-js', $dir . 'wpata-admin.js', array('jquery'), WPATA_VERSION, true);
		wp_localize_script(
			'wpata-admin-js',
			'wpataAdminConfig',
			array(
				'ajaxUrl' => admin_url('admin-ajax.php'),
				'lang' => 'th',
				'noDistrictText' => '-- ไม่มีอำเภอ/เขต --',
				'noSubdistrictText' => '-- ไม่มีตำบล/แขวง --',
				'loadingText' => 'Loading...',
			)
		);
	}
}
add_action('admin_enqueue_scripts', 'wpata_enqueue_admin_assets');

function wpata_render_admin_view_tabs($active_view)
{
	$views = array(
		'overview' => array(
			'label' => 'ภาพรวม',
			'description' => 'ดูภาพรวมการใช้งานและจำนวนข้อมูล',
		),
		'settings' => array(
			'label' => 'ตั้งค่าข้อความ',
			'description' => 'ปรับข้อความเริ่มต้นของ dropdown',
		),
		'data' => array(
			'label' => 'จัดการข้อมูลที่อยู่',
			'description' => 'เพิ่ม แก้ไข และลบข้อมูลจังหวัด/อำเภอ/ตำบล',
		),
	);

	echo '<nav class="wpata-main-nav" aria-label="WP Thailand Address Main Navigation">';
	foreach ($views as $view_key => $view) {
		$url = add_query_arg(
			array(
				'page' => 'wpata-admin',
				'wpata_view' => $view_key,
			),
			admin_url('tools.php')
		);
		$class = $active_view === $view_key ? 'wpata-main-nav-item is-active' : 'wpata-main-nav-item';
		echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">';
		echo '<span class="wpata-main-nav-title">' . esc_html($view['label']) . '</span>';
		echo '<span class="wpata-main-nav-desc">' . esc_html($view['description']) . '</span>';
		echo '</a>';
	}
	echo '</nav>';
}

function wpata_render_admin_page_header($title, $description = '', $active_view = 'overview')
{
	echo '<div class="wrap wpata-admin-wrap">';
	echo '<div class="wpata-admin-head">';
	echo '<div>';
	echo '<h1>' . esc_html($title) . '</h1>';
	if (!empty($description)) {
		echo '<p class="wpata-admin-subtitle">' . esc_html($description) . '</p>';
	}
	echo '</div>';
	echo '</div>';
	wpata_render_admin_view_tabs($active_view);
}

function wpata_render_admin_page_footer()
{
	echo '</div>';
}

function wpata_render_admin_notice_from_query()
{
	$message = isset($_GET['wpata_notice']) ? sanitize_text_field(wp_unslash($_GET['wpata_notice'])) : '';
	if ($message === '') {
		return;
	}

	$status = isset($_GET['wpata_status']) ? sanitize_key(wp_unslash($_GET['wpata_status'])) : 'success';
	$notice_class = $status === 'error' ? 'notice-error' : 'notice-success';
	echo '<div class="notice ' . esc_attr($notice_class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
}

function wpata_count_table_rows($name)
{
	global $wpdb;
	$table = wpata_table_name($name);
	return (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
}

function wpata_render_admin_dashboard_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}

	$cards = array(
		'จังหวัด' => wpata_count_table_rows('province'),
		'อำเภอ/เขต' => wpata_count_table_rows('district'),
		'ตำบล/แขวง' => wpata_count_table_rows('subdistrict'),
		'รหัสไปรษณีย์' => wpata_count_table_rows('postalcode'),
	);

	wpata_render_admin_page_header('WP Thailand Address', 'หน้าจัดการหลังบ้านสำหรับตั้งค่าข้อความและแก้ไขข้อมูลที่อยู่', 'overview');
	wpata_render_admin_notice_from_query();

	echo '<div class="wpata-admin-guide-link">';
	echo '<p><strong>แนะนำการเริ่มใช้งาน:</strong> อ่านคู่มือการติดตั้ง, ตัวอย่างการใช้งาน และแนวทางปรับแต่งเพิ่มเติมได้ที่ <a href="' . esc_url(WPATA_GITHUB_URL) . '" target="_blank" rel="noopener noreferrer">คู่มือบน GitHub</a></p>';
	echo '</div>';

	echo '<div class="wpata-card wpata-card-metrics">';
	echo '<h2>สรุปจำนวนข้อมูล</h2>';
	echo '<div class="wpata-metric-grid">';
	foreach ($cards as $label => $count) {
		echo '<div class="wpata-metric-item">';
		echo '<span class="wpata-metric-label">' . esc_html($label) . '</span>';
		echo '<strong class="wpata-metric-value">' . esc_html(number_format_i18n($count)) . '</strong>';
		echo '</div>';
	}
	echo '</div>';
	echo '</div>';

	echo '<p><strong>เวอร์ชันปลั๊กอิน:</strong> <code>' . esc_html(WPATA_VERSION) . '</code></p>';

	wpata_render_admin_page_footer();
}

function wpata_setting_rows()
{
	return array(
		array(
			'label' => 'ข้อความเมื่อไม่พบข้อมูล',
			'key_th' => 'wpata_nodata_th',
			'key_en' => 'wpata_nodata_en',
			'placeholder_th' => wpata_option_label('nodata', 'th'),
			'placeholder_en' => wpata_option_label('nodata', 'en'),
		),
		array(
			'label' => 'ข้อความหัวข้อจังหวัด',
			'key_th' => 'wpata_province_th',
			'key_en' => 'wpata_province_en',
			'placeholder_th' => wpata_option_label('province', 'th'),
			'placeholder_en' => wpata_option_label('province', 'en'),
		),
		array(
			'label' => 'ข้อความหัวข้ออำเภอ/เขต',
			'key_th' => 'wpata_district_th',
			'key_en' => 'wpata_district_en',
			'placeholder_th' => wpata_option_label('district', 'th'),
			'placeholder_en' => wpata_option_label('district', 'en'),
		),
		array(
			'label' => 'ข้อความหัวข้อตำบล/แขวง',
			'key_th' => 'wpata_subdistrict_th',
			'key_en' => 'wpata_subdistrict_en',
			'placeholder_th' => wpata_option_label('subdistrict', 'th'),
			'placeholder_en' => wpata_option_label('subdistrict', 'en'),
		),
		array(
			'label' => 'ข้อความหัวข้อรหัสไปรษณีย์',
			'key_th' => 'wpata_postalcode_th',
			'key_en' => 'wpata_postalcode_en',
			'placeholder_th' => wpata_option_label('postalcode', 'th'),
			'placeholder_en' => wpata_option_label('postalcode', 'en'),
		),
	);
}

function wpata_handle_settings_submit()
{
	if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['wpata_save_settings'])) {
		return;
	}

	$page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : '';
	$view = isset($_REQUEST['wpata_view']) ? sanitize_key(wp_unslash($_REQUEST['wpata_view'])) : '';
	if ($page !== 'wpata-admin' || $view !== 'settings') {
		return;
	}

	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to manage this plugin.', 'default'));
	}

	check_admin_referer('wpata_save_settings_action');

	$rows = wpata_setting_rows();
	foreach ($rows as $row) {
		$key_th = $row['key_th'];
		$key_en = $row['key_en'];
		$value_th = isset($_POST[$key_th]) ? sanitize_text_field(wp_unslash($_POST[$key_th])) : '';
		$value_en = isset($_POST[$key_en]) ? sanitize_text_field(wp_unslash($_POST[$key_en])) : '';
		update_option($key_th, $value_th);
		update_option($key_en, $value_en);
	}

	$redirect_url = add_query_arg(
		array(
			'page' => 'wpata-admin',
			'wpata_view' => 'settings',
			'wpata_status' => 'success',
			'wpata_notice' => 'บันทึกการตั้งค่าเรียบร้อยแล้ว',
		),
		admin_url('tools.php')
	);
	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_init', 'wpata_handle_settings_submit');

function wpata_render_admin_settings_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}

	wpata_render_admin_page_header('ตั้งค่าข้อความ Dropdown', 'กำหนดข้อความที่ใช้ใน option แรกของแต่ละ dropdown ทั้งภาษาไทยและภาษาอังกฤษ', 'settings');
	wpata_render_admin_notice_from_query();

	echo '<div class="wpata-card">';
	echo '<h2>ข้อความที่รองรับ</h2>';
	echo '<form method="post">';
	wp_nonce_field('wpata_save_settings_action');
	echo '<input type="hidden" name="page" value="wpata-admin">';
	echo '<input type="hidden" name="wpata_view" value="settings">';
	echo '<input type="hidden" name="wpata_save_settings" value="1">';
	echo '<table class="widefat fixed striped wpata-settings-table">';
	echo '<thead><tr><th>รายการ</th><th>ไทย (TH)</th><th>อังกฤษ (EN)</th></tr></thead><tbody>';

	$rows = wpata_setting_rows();
	foreach ($rows as $row) {
		$key_th = $row['key_th'];
		$key_en = $row['key_en'];
		$value_th = get_option($key_th, '');
		$value_en = get_option($key_en, '');

		echo '<tr>';
		echo '<td><strong>' . esc_html($row['label']) . '</strong><br><code>' . esc_html($key_th) . '</code><br><code>' . esc_html($key_en) . '</code></td>';
		echo '<td><input type="text" class="regular-text" name="' . esc_attr($key_th) . '" value="' . esc_attr($value_th) . '" placeholder="' . esc_attr($row['placeholder_th']) . '"></td>';
		echo '<td><input type="text" class="regular-text" name="' . esc_attr($key_en) . '" value="' . esc_attr($value_en) . '" placeholder="' . esc_attr($row['placeholder_en']) . '"></td>';
		echo '</tr>';
	}

	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">บันทึกการตั้งค่า</button></p>';
	echo '</form>';
	echo '</div>';

	wpata_render_admin_page_footer();
}

function wpata_get_admin_int($scope, $key, $default = 0)
{
	$raw_value = null;
	if ($scope === 'post' && isset($_POST[$key])) {
		$raw_value = wp_unslash($_POST[$key]);
	} elseif ($scope === 'get' && isset($_GET[$key])) {
		$raw_value = wp_unslash($_GET[$key]);
	}

	if ($raw_value === null) {
		return $default;
	}

	return absint($raw_value);
}

function wpata_get_provinces()
{
	global $wpdb;
	$table = wpata_table_name('province');
	return $wpdb->get_results("SELECT pv_id, pv_idx, pv_name_th, pv_name_en FROM {$table} ORDER BY pv_name_th ASC");
}

function wpata_get_province_by_id($pv_id)
{
	global $wpdb;
	$table = wpata_table_name('province');
	return $wpdb->get_row($wpdb->prepare("SELECT pv_id, pv_idx, pv_name_th, pv_name_en FROM {$table} WHERE pv_id=%d", $pv_id));
}

function wpata_get_districts_by_province($pv_id)
{
	global $wpdb;
	$table = wpata_table_name('district');
	if ($pv_id <= 0) {
		return array();
	}
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT dt_id, dt_idx, dt_name_th, dt_name_en, dt_pv_id FROM {$table} WHERE dt_pv_id=%d ORDER BY dt_name_th ASC",
			$pv_id
		)
	);
}

function wpata_get_district_by_id($dt_id)
{
	global $wpdb;
	$table = wpata_table_name('district');
	return $wpdb->get_row($wpdb->prepare("SELECT dt_id, dt_idx, dt_name_th, dt_name_en, dt_pv_id FROM {$table} WHERE dt_id=%d", $dt_id));
}

function wpata_get_subdistricts_by_district($dt_id)
{
	global $wpdb;
	$table = wpata_table_name('subdistrict');
	if ($dt_id <= 0) {
		return array();
	}
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT sdt_id, sdt_idx, sdt_name_th, sdt_name_en, sdt_pv_id, sdt_dt_id FROM {$table} WHERE sdt_dt_id=%d ORDER BY sdt_name_th ASC",
			$dt_id
		)
	);
}

function wpata_get_subdistrict_by_id($sdt_id)
{
	global $wpdb;
	$table = wpata_table_name('subdistrict');
	return $wpdb->get_row($wpdb->prepare("SELECT sdt_id, sdt_idx, sdt_name_th, sdt_name_en, sdt_pv_id, sdt_dt_id FROM {$table} WHERE sdt_id=%d", $sdt_id));
}

function wpata_get_postalcodes_by_subdistrict($sdt_id)
{
	global $wpdb;
	$table = wpata_table_name('postalcode');
	if ($sdt_id <= 0) {
		return array();
	}
	return $wpdb->get_results(
		$wpdb->prepare(
			"SELECT ptc_id, ptc_idx, ptc_pv_id, ptc_dt_id, ptc_sdt_id FROM {$table} WHERE ptc_sdt_id=%d ORDER BY ptc_idx ASC",
			$sdt_id
		)
	);
}

function wpata_get_postalcode_by_id($ptc_id)
{
	global $wpdb;
	$table = wpata_table_name('postalcode');
	return $wpdb->get_row($wpdb->prepare("SELECT ptc_id, ptc_idx, ptc_pv_id, ptc_dt_id, ptc_sdt_id FROM {$table} WHERE ptc_id=%d", $ptc_id));
}

function wpata_get_province_name_by_id($pv_id, $lang = 'th')
{
	$pv_id = absint($pv_id);
	if ($pv_id <= 0) {
		return '';
	}

	$lang = wpata_sanitize_lang($lang);
	$row = wpata_get_province_by_id($pv_id);
	if (!$row) {
		return '';
	}

	$field = "pv_name_{$lang}";
	if (!isset($row->$field) || $row->$field === '') {
		return '';
	}

	return (string) $row->$field;
}

function wpata_get_district_name_by_id($dt_id, $lang = 'th')
{
	$dt_id = absint($dt_id);
	if ($dt_id <= 0) {
		return '';
	}

	$lang = wpata_sanitize_lang($lang);
	$row = wpata_get_district_by_id($dt_id);
	if (!$row) {
		return '';
	}

	$field = "dt_name_{$lang}";
	if (!isset($row->$field) || $row->$field === '') {
		return '';
	}

	return (string) $row->$field;
}

function wpata_get_subdistrict_name_by_id($sdt_id, $lang = 'th')
{
	$sdt_id = absint($sdt_id);
	if ($sdt_id <= 0) {
		return '';
	}

	$lang = wpata_sanitize_lang($lang);
	$row = wpata_get_subdistrict_by_id($sdt_id);
	if (!$row) {
		return '';
	}

	$field = "sdt_name_{$lang}";
	if (!isset($row->$field) || $row->$field === '') {
		return '';
	}

	return (string) $row->$field;
}

function wpata_get_postalcode_name_by_id($ptc_id)
{
	$ptc_id = absint($ptc_id);
	if ($ptc_id <= 0) {
		return '';
	}

	$row = wpata_get_postalcode_by_id($ptc_id);
	if (!$row || !isset($row->ptc_idx)) {
		return '';
	}

	return (string) $row->ptc_idx;
}

function wpata_data_tabs()
{
	return array(
		'province' => 'จังหวัด',
		'district' => 'อำเภอ/เขต',
		'subdistrict' => 'ตำบล/แขวง',
		'postalcode' => 'รหัสไปรษณีย์',
	);
}

function wpata_save_province_entity()
{
	global $wpdb;
	$table = wpata_table_name('province');
	$pv_id = wpata_get_admin_int('post', 'pv_id', 0);
	$pv_idx = wpata_get_admin_int('post', 'pv_idx', 0);
	$pv_name_th = isset($_POST['pv_name_th']) ? sanitize_text_field(wp_unslash($_POST['pv_name_th'])) : '';
	$pv_name_en = isset($_POST['pv_name_en']) ? sanitize_text_field(wp_unslash($_POST['pv_name_en'])) : '';

	if ($pv_name_th === '' || $pv_name_en === '') {
		return array('success' => false, 'message' => 'กรุณากรอกชื่อจังหวัดทั้งภาษาไทยและภาษาอังกฤษ', 'tab' => 'province');
	}

	if ($pv_idx <= 0) {
		$pv_idx = (int) $wpdb->get_var("SELECT COALESCE(MAX(pv_idx), 0) + 1 FROM {$table}");
	}

	$data = array(
		'pv_idx' => $pv_idx,
		'pv_name_th' => $pv_name_th,
		'pv_name_en' => $pv_name_en,
	);
	$format = array('%d', '%s', '%s');

	if ($pv_id > 0) {
		$exists = wpata_get_province_by_id($pv_id);
		if (!$exists) {
			return array('success' => false, 'message' => 'ไม่พบจังหวัดที่ต้องการแก้ไข', 'tab' => 'province');
		}

		$result = $wpdb->update($table, $data, array('pv_id' => $pv_id), $format, array('%d'));
		if ($result === false) {
			return array('success' => false, 'message' => 'เกิดข้อผิดพลาดระหว่างบันทึกจังหวัด', 'tab' => 'province');
		}

		return array('success' => true, 'message' => 'บันทึกจังหวัดเรียบร้อยแล้ว', 'tab' => 'province');
	}

	$result = $wpdb->insert($table, $data, $format);
	if ($result === false) {
		return array('success' => false, 'message' => 'เกิดข้อผิดพลาดระหว่างเพิ่มจังหวัด', 'tab' => 'province');
	}

	return array('success' => true, 'message' => 'เพิ่มจังหวัดเรียบร้อยแล้ว', 'tab' => 'province');
}

function wpata_save_district_entity()
{
	global $wpdb;
	$table = wpata_table_name('district');
	$dt_id = wpata_get_admin_int('post', 'dt_id', 0);
	$dt_idx = wpata_get_admin_int('post', 'dt_idx', 0);
	$dt_pv_id = wpata_get_admin_int('post', 'dt_pv_id', 0);
	$dt_name_th = isset($_POST['dt_name_th']) ? sanitize_text_field(wp_unslash($_POST['dt_name_th'])) : '';
	$dt_name_en = isset($_POST['dt_name_en']) ? sanitize_text_field(wp_unslash($_POST['dt_name_en'])) : '';

	if ($dt_pv_id <= 0 || !wpata_get_province_by_id($dt_pv_id)) {
		return array('success' => false, 'message' => 'กรุณาเลือกจังหวัดให้ถูกต้องก่อนบันทึกอำเภอ/เขต', 'tab' => 'district');
	}
	if ($dt_name_th === '' || $dt_name_en === '') {
		return array(
			'success' => false,
			'message' => 'กรุณากรอกชื่ออำเภอ/เขตทั้งภาษาไทยและภาษาอังกฤษ',
			'tab' => 'district',
			'args' => array('pv_id' => $dt_pv_id),
		);
	}

	if ($dt_idx <= 0) {
		$dt_idx = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(dt_idx), 0) + 1 FROM {$table} WHERE dt_pv_id=%d", $dt_pv_id));
	}

	$data = array(
		'dt_idx' => $dt_idx,
		'dt_name_th' => $dt_name_th,
		'dt_name_en' => $dt_name_en,
		'dt_pv_id' => $dt_pv_id,
	);
	$format = array('%d', '%s', '%s', '%d');

	if ($dt_id > 0) {
		$exists = wpata_get_district_by_id($dt_id);
		if (!$exists) {
			return array(
				'success' => false,
				'message' => 'ไม่พบอำเภอ/เขตที่ต้องการแก้ไข',
				'tab' => 'district',
				'args' => array('pv_id' => $dt_pv_id),
			);
		}

		$result = $wpdb->update($table, $data, array('dt_id' => $dt_id), $format, array('%d'));
		if ($result === false) {
			return array(
				'success' => false,
				'message' => 'เกิดข้อผิดพลาดระหว่างบันทึกอำเภอ/เขต',
				'tab' => 'district',
				'args' => array('pv_id' => $dt_pv_id),
			);
		}

		return array(
			'success' => true,
			'message' => 'บันทึกอำเภอ/เขตเรียบร้อยแล้ว',
			'tab' => 'district',
			'args' => array('pv_id' => $dt_pv_id),
		);
	}

	$result = $wpdb->insert($table, $data, $format);
	if ($result === false) {
		return array(
			'success' => false,
			'message' => 'เกิดข้อผิดพลาดระหว่างเพิ่มอำเภอ/เขต',
			'tab' => 'district',
			'args' => array('pv_id' => $dt_pv_id),
		);
	}

	return array(
		'success' => true,
		'message' => 'เพิ่มอำเภอ/เขตเรียบร้อยแล้ว',
		'tab' => 'district',
		'args' => array('pv_id' => $dt_pv_id),
	);
}

function wpata_save_subdistrict_entity()
{
	global $wpdb;
	$table = wpata_table_name('subdistrict');
	$sdt_id = wpata_get_admin_int('post', 'sdt_id', 0);
	$sdt_idx = wpata_get_admin_int('post', 'sdt_idx', 0);
	$sdt_pv_id = wpata_get_admin_int('post', 'sdt_pv_id', 0);
	$sdt_dt_id = wpata_get_admin_int('post', 'sdt_dt_id', 0);
	$sdt_name_th = isset($_POST['sdt_name_th']) ? sanitize_text_field(wp_unslash($_POST['sdt_name_th'])) : '';
	$sdt_name_en = isset($_POST['sdt_name_en']) ? sanitize_text_field(wp_unslash($_POST['sdt_name_en'])) : '';

	$district = wpata_get_district_by_id($sdt_dt_id);
	if (!$district) {
		return array('success' => false, 'message' => 'กรุณาเลือกอำเภอ/เขตให้ถูกต้องก่อนบันทึกตำบล/แขวง', 'tab' => 'subdistrict');
	}

	// บังคับให้ตำบล/แขวงอ้างอิงจังหวัดจากอำเภอ/เขตเสมอ เพื่อลดข้อมูลคลาดเคลื่อน
	$resolved_pv_id = (int) $district->dt_pv_id;
	if ($sdt_pv_id > 0 && $sdt_pv_id !== $resolved_pv_id) {
		return array(
			'success' => false,
			'message' => 'อำเภอ/เขตที่เลือกไม่สัมพันธ์กับจังหวัดที่เลือก',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $sdt_pv_id, 'dt_id' => $sdt_dt_id),
		);
	}

	if ($sdt_name_th === '' || $sdt_name_en === '') {
		return array(
			'success' => false,
			'message' => 'กรุณากรอกชื่อตำบล/แขวงทั้งภาษาไทยและภาษาอังกฤษ',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $resolved_pv_id, 'dt_id' => $sdt_dt_id),
		);
	}

	if ($sdt_idx <= 0) {
		$sdt_idx = (int) $wpdb->get_var($wpdb->prepare("SELECT COALESCE(MAX(sdt_idx), 0) + 1 FROM {$table} WHERE sdt_dt_id=%d", $sdt_dt_id));
	}

	$data = array(
		'sdt_idx' => $sdt_idx,
		'sdt_name_th' => $sdt_name_th,
		'sdt_name_en' => $sdt_name_en,
		'sdt_pv_id' => $resolved_pv_id,
		'sdt_dt_id' => $sdt_dt_id,
	);
	$format = array('%d', '%s', '%s', '%d', '%d');

	if ($sdt_id > 0) {
		$exists = wpata_get_subdistrict_by_id($sdt_id);
		if (!$exists) {
			return array(
				'success' => false,
				'message' => 'ไม่พบตำบล/แขวงที่ต้องการแก้ไข',
				'tab' => 'subdistrict',
				'args' => array('pv_id' => $resolved_pv_id, 'dt_id' => $sdt_dt_id),
			);
		}

		$result = $wpdb->update($table, $data, array('sdt_id' => $sdt_id), $format, array('%d'));
		if ($result === false) {
			return array(
				'success' => false,
				'message' => 'เกิดข้อผิดพลาดระหว่างบันทึกตำบล/แขวง',
				'tab' => 'subdistrict',
				'args' => array('pv_id' => $resolved_pv_id, 'dt_id' => $sdt_dt_id),
			);
		}

		return array(
			'success' => true,
			'message' => 'บันทึกตำบล/แขวงเรียบร้อยแล้ว',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $resolved_pv_id, 'dt_id' => $sdt_dt_id),
		);
	}

	$result = $wpdb->insert($table, $data, $format);
	if ($result === false) {
		return array(
			'success' => false,
			'message' => 'เกิดข้อผิดพลาดระหว่างเพิ่มตำบล/แขวง',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $resolved_pv_id, 'dt_id' => $sdt_dt_id),
		);
	}

	return array(
		'success' => true,
		'message' => 'เพิ่มตำบล/แขวงเรียบร้อยแล้ว',
		'tab' => 'subdistrict',
		'args' => array('pv_id' => $resolved_pv_id, 'dt_id' => $sdt_dt_id),
	);
}

function wpata_save_postalcode_entity()
{
	global $wpdb;
	$table = wpata_table_name('postalcode');
	$ptc_id = wpata_get_admin_int('post', 'ptc_id', 0);
	$ptc_sdt_id = wpata_get_admin_int('post', 'ptc_sdt_id', 0);
	$ptc_idx_raw = isset($_POST['ptc_idx']) ? sanitize_text_field(wp_unslash($_POST['ptc_idx'])) : '';
	$ptc_idx = preg_replace('/\D+/', '', $ptc_idx_raw);

	$subdistrict = wpata_get_subdistrict_by_id($ptc_sdt_id);
	if (!$subdistrict) {
		return array('success' => false, 'message' => 'กรุณาเลือกตำบล/แขวงให้ถูกต้องก่อนบันทึกรหัสไปรษณีย์', 'tab' => 'postalcode');
	}
	if ($ptc_idx === '') {
		return array(
			'success' => false,
			'message' => 'กรุณากรอกรหัสไปรษณีย์',
			'tab' => 'postalcode',
			'args' => array(
				'pv_id' => (int) $subdistrict->sdt_pv_id,
				'dt_id' => (int) $subdistrict->sdt_dt_id,
				'sdt_id' => (int) $subdistrict->sdt_id,
			),
		);
	}

	// รหัสไปรษณีย์จะอ้างอิง parent จากตำบล/แขวงที่เลือกโดยตรง
	$data = array(
		'ptc_idx' => (int) $ptc_idx,
		'ptc_pv_id' => (int) $subdistrict->sdt_pv_id,
		'ptc_dt_id' => (int) $subdistrict->sdt_dt_id,
		'ptc_sdt_id' => (int) $subdistrict->sdt_id,
	);
	$format = array('%d', '%d', '%d', '%d');

	if ($ptc_id > 0) {
		$exists = wpata_get_postalcode_by_id($ptc_id);
		if (!$exists) {
			return array(
				'success' => false,
				'message' => 'ไม่พบรหัสไปรษณีย์ที่ต้องการแก้ไข',
				'tab' => 'postalcode',
				'args' => array(
					'pv_id' => (int) $subdistrict->sdt_pv_id,
					'dt_id' => (int) $subdistrict->sdt_dt_id,
					'sdt_id' => (int) $subdistrict->sdt_id,
				),
			);
		}

		$result = $wpdb->update($table, $data, array('ptc_id' => $ptc_id), $format, array('%d'));
		if ($result === false) {
			return array(
				'success' => false,
				'message' => 'เกิดข้อผิดพลาดระหว่างบันทึกรหัสไปรษณีย์',
				'tab' => 'postalcode',
				'args' => array(
					'pv_id' => (int) $subdistrict->sdt_pv_id,
					'dt_id' => (int) $subdistrict->sdt_dt_id,
					'sdt_id' => (int) $subdistrict->sdt_id,
				),
			);
		}

		return array(
			'success' => true,
			'message' => 'บันทึกรหัสไปรษณีย์เรียบร้อยแล้ว',
			'tab' => 'postalcode',
			'args' => array(
				'pv_id' => (int) $subdistrict->sdt_pv_id,
				'dt_id' => (int) $subdistrict->sdt_dt_id,
				'sdt_id' => (int) $subdistrict->sdt_id,
			),
		);
	}

	$result = $wpdb->insert($table, $data, $format);
	if ($result === false) {
		return array(
			'success' => false,
			'message' => 'เกิดข้อผิดพลาดระหว่างเพิ่มรหัสไปรษณีย์',
			'tab' => 'postalcode',
			'args' => array(
				'pv_id' => (int) $subdistrict->sdt_pv_id,
				'dt_id' => (int) $subdistrict->sdt_dt_id,
				'sdt_id' => (int) $subdistrict->sdt_id,
			),
		);
	}

	return array(
		'success' => true,
		'message' => 'เพิ่มรหัสไปรษณีย์เรียบร้อยแล้ว',
		'tab' => 'postalcode',
		'args' => array(
			'pv_id' => (int) $subdistrict->sdt_pv_id,
			'dt_id' => (int) $subdistrict->sdt_dt_id,
			'sdt_id' => (int) $subdistrict->sdt_id,
		),
	);
}

function wpata_delete_province_entity()
{
	global $wpdb;

	$pv_id = wpata_get_admin_int('post', 'pv_id', 0);
	if ($pv_id <= 0) {
		return array('success' => false, 'message' => 'ไม่พบจังหวัดที่ต้องการลบ', 'tab' => 'province');
	}

	$province = wpata_get_province_by_id($pv_id);
	if (!$province) {
		return array('success' => false, 'message' => 'ไม่พบจังหวัดที่ต้องการลบ', 'tab' => 'province');
	}

	$district_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . wpata_table_name('district') . " WHERE dt_pv_id=%d", $pv_id));
	$subdistrict_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . wpata_table_name('subdistrict') . " WHERE sdt_pv_id=%d", $pv_id));
	$postalcode_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . wpata_table_name('postalcode') . " WHERE ptc_pv_id=%d", $pv_id));

	$total_children = $district_count + $subdistrict_count + $postalcode_count;
	if ($total_children > 0) {
		return array(
			'success' => false,
			'message' => 'ลบจังหวัดไม่ได้ เนื่องจากยังมีข้อมูลลูกเชื่อมอยู่',
			'tab' => 'province',
		);
	}

	$result = $wpdb->delete(wpata_table_name('province'), array('pv_id' => $pv_id), array('%d'));
	if ($result === false) {
		return array('success' => false, 'message' => 'เกิดข้อผิดพลาดระหว่างลบจังหวัด', 'tab' => 'province');
	}

	return array('success' => true, 'message' => 'ลบจังหวัดเรียบร้อยแล้ว', 'tab' => 'province');
}

function wpata_delete_district_entity()
{
	global $wpdb;

	$dt_id = wpata_get_admin_int('post', 'dt_id', 0);
	$selected_pv_id = wpata_get_admin_int('post', 'pv_id', 0);
	if ($dt_id <= 0) {
		return array('success' => false, 'message' => 'ไม่พบอำเภอ/เขตที่ต้องการลบ', 'tab' => 'district', 'args' => array('pv_id' => $selected_pv_id));
	}

	$district = wpata_get_district_by_id($dt_id);
	if (!$district) {
		return array('success' => false, 'message' => 'ไม่พบอำเภอ/เขตที่ต้องการลบ', 'tab' => 'district', 'args' => array('pv_id' => $selected_pv_id));
	}

	$selected_pv_id = (int) $district->dt_pv_id;
	$subdistrict_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . wpata_table_name('subdistrict') . " WHERE sdt_dt_id=%d", $dt_id));
	$postalcode_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . wpata_table_name('postalcode') . " WHERE ptc_dt_id=%d", $dt_id));
	if (($subdistrict_count + $postalcode_count) > 0) {
		return array(
			'success' => false,
			'message' => 'ลบอำเภอ/เขตไม่ได้ เนื่องจากยังมีข้อมูลลูกเชื่อมอยู่',
			'tab' => 'district',
			'args' => array('pv_id' => $selected_pv_id),
		);
	}

	$result = $wpdb->delete(wpata_table_name('district'), array('dt_id' => $dt_id), array('%d'));
	if ($result === false) {
		return array(
			'success' => false,
			'message' => 'เกิดข้อผิดพลาดระหว่างลบอำเภอ/เขต',
			'tab' => 'district',
			'args' => array('pv_id' => $selected_pv_id),
		);
	}

	return array(
		'success' => true,
		'message' => 'ลบอำเภอ/เขตเรียบร้อยแล้ว',
		'tab' => 'district',
		'args' => array('pv_id' => $selected_pv_id),
	);
}

function wpata_delete_subdistrict_entity()
{
	global $wpdb;

	$sdt_id = wpata_get_admin_int('post', 'sdt_id', 0);
	$selected_pv_id = wpata_get_admin_int('post', 'pv_id', 0);
	$selected_dt_id = wpata_get_admin_int('post', 'dt_id', 0);
	if ($sdt_id <= 0) {
		return array(
			'success' => false,
			'message' => 'ไม่พบตำบล/แขวงที่ต้องการลบ',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id),
		);
	}

	$subdistrict = wpata_get_subdistrict_by_id($sdt_id);
	if (!$subdistrict) {
		return array(
			'success' => false,
			'message' => 'ไม่พบตำบล/แขวงที่ต้องการลบ',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id),
		);
	}

	$selected_pv_id = (int) $subdistrict->sdt_pv_id;
	$selected_dt_id = (int) $subdistrict->sdt_dt_id;
	$postalcode_count = (int) $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM " . wpata_table_name('postalcode') . " WHERE ptc_sdt_id=%d", $sdt_id));
	if ($postalcode_count > 0) {
		return array(
			'success' => false,
			'message' => 'ลบตำบล/แขวงไม่ได้ เนื่องจากยังมีรหัสไปรษณีย์เชื่อมอยู่',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id),
		);
	}

	$result = $wpdb->delete(wpata_table_name('subdistrict'), array('sdt_id' => $sdt_id), array('%d'));
	if ($result === false) {
		return array(
			'success' => false,
			'message' => 'เกิดข้อผิดพลาดระหว่างลบตำบล/แขวง',
			'tab' => 'subdistrict',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id),
		);
	}

	return array(
		'success' => true,
		'message' => 'ลบตำบล/แขวงเรียบร้อยแล้ว',
		'tab' => 'subdistrict',
		'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id),
	);
}

function wpata_delete_postalcode_entity()
{
	global $wpdb;

	$ptc_id = wpata_get_admin_int('post', 'ptc_id', 0);
	$selected_pv_id = wpata_get_admin_int('post', 'pv_id', 0);
	$selected_dt_id = wpata_get_admin_int('post', 'dt_id', 0);
	$selected_sdt_id = wpata_get_admin_int('post', 'sdt_id', 0);
	if ($ptc_id <= 0) {
		return array(
			'success' => false,
			'message' => 'ไม่พบรหัสไปรษณีย์ที่ต้องการลบ',
			'tab' => 'postalcode',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id, 'sdt_id' => $selected_sdt_id),
		);
	}

	$postalcode = wpata_get_postalcode_by_id($ptc_id);
	if (!$postalcode) {
		return array(
			'success' => false,
			'message' => 'ไม่พบรหัสไปรษณีย์ที่ต้องการลบ',
			'tab' => 'postalcode',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id, 'sdt_id' => $selected_sdt_id),
		);
	}

	$selected_pv_id = (int) $postalcode->ptc_pv_id;
	$selected_dt_id = (int) $postalcode->ptc_dt_id;
	$selected_sdt_id = (int) $postalcode->ptc_sdt_id;

	$result = $wpdb->delete(wpata_table_name('postalcode'), array('ptc_id' => $ptc_id), array('%d'));
	if ($result === false) {
		return array(
			'success' => false,
			'message' => 'เกิดข้อผิดพลาดระหว่างลบรหัสไปรษณีย์',
			'tab' => 'postalcode',
			'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id, 'sdt_id' => $selected_sdt_id),
		);
	}

	return array(
		'success' => true,
		'message' => 'ลบรหัสไปรษณีย์เรียบร้อยแล้ว',
		'tab' => 'postalcode',
		'args' => array('pv_id' => $selected_pv_id, 'dt_id' => $selected_dt_id, 'sdt_id' => $selected_sdt_id),
	);
}

function wpata_handle_data_entity_submit()
{
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		return;
	}

	$is_save = isset($_POST['wpata_save_data']);
	$is_delete = isset($_POST['wpata_delete_data']);
	if (!$is_save && !$is_delete) {
		return;
	}

	$page = isset($_REQUEST['page']) ? sanitize_key(wp_unslash($_REQUEST['page'])) : '';
	$view = isset($_REQUEST['wpata_view']) ? sanitize_key(wp_unslash($_REQUEST['wpata_view'])) : '';
	if ($page !== 'wpata-admin' || $view !== 'data') {
		return;
	}

	if (!current_user_can('manage_options')) {
		wp_die(esc_html__('You do not have permission to manage this plugin.', 'default'));
	}

	if ($is_save) {
		check_admin_referer('wpata_save_data_action');
	} else {
		check_admin_referer('wpata_delete_data_action');
	}

	$entity = isset($_POST['wpata_entity']) ? sanitize_key(wp_unslash($_POST['wpata_entity'])) : 'province';
	$result = array(
		'success' => false,
		'message' => $is_delete ? 'ไม่สามารถลบข้อมูลได้' : 'ไม่สามารถบันทึกข้อมูลได้',
		'tab' => 'province',
		'args' => array(),
	);

	if ($is_save) {
		switch ($entity) {
			case 'district':
				$result = wpata_save_district_entity();
				break;
			case 'subdistrict':
				$result = wpata_save_subdistrict_entity();
				break;
			case 'postalcode':
				$result = wpata_save_postalcode_entity();
				break;
			case 'province':
			default:
				$result = wpata_save_province_entity();
				break;
		}
	} else {
		switch ($entity) {
			case 'district':
				$result = wpata_delete_district_entity();
				break;
			case 'subdistrict':
				$result = wpata_delete_subdistrict_entity();
				break;
			case 'postalcode':
				$result = wpata_delete_postalcode_entity();
				break;
			case 'province':
			default:
				$result = wpata_delete_province_entity();
				break;
		}
	}

	$redirect_args = array(
		'page' => 'wpata-admin',
		'wpata_view' => 'data',
		'tab' => isset($result['tab']) ? $result['tab'] : 'province',
		'wpata_status' => !empty($result['success']) ? 'success' : 'error',
		'wpata_notice' => isset($result['message']) ? $result['message'] : 'ไม่สามารถบันทึกข้อมูลได้',
	);

	if (!empty($result['args']) && is_array($result['args'])) {
		$redirect_args = array_merge($redirect_args, $result['args']);
	}

	$redirect_url = add_query_arg($redirect_args, admin_url('tools.php'));
	wp_safe_redirect($redirect_url);
	exit;
}
add_action('admin_init', 'wpata_handle_data_entity_submit');

function wpata_render_admin_data_tabs($active_tab)
{
	$tabs = wpata_data_tabs();
	echo '<h2 class="nav-tab-wrapper wpata-nav-tab-wrapper">';
	foreach ($tabs as $tab_key => $label) {
		$url = add_query_arg(
			array(
				'page' => 'wpata-admin',
				'wpata_view' => 'data',
				'tab' => $tab_key,
			),
			admin_url('tools.php')
		);
		$class = $active_tab === $tab_key ? 'nav-tab nav-tab-active' : 'nav-tab';
		echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
	}
	echo '</h2>';
}

function wpata_render_province_tab()
{
	$edit_id = wpata_get_admin_int('get', 'edit_id', 0);
	$editing = $edit_id > 0 ? wpata_get_province_by_id($edit_id) : null;
	$provinces = wpata_get_provinces();
	$cancel_url = add_query_arg(
		array(
			'page' => 'wpata-admin',
			'wpata_view' => 'data',
			'tab' => 'province',
		),
		admin_url('tools.php')
	);

	echo '<div class="wpata-card">';
	echo '<h2>' . ($editing ? 'แก้ไขจังหวัด' : 'เพิ่มจังหวัด') . '</h2>';
	echo '<form method="post">';
	wp_nonce_field('wpata_save_data_action');
	echo '<input type="hidden" name="page" value="wpata-admin">';
	echo '<input type="hidden" name="wpata_view" value="data">';
	echo '<input type="hidden" name="wpata_save_data" value="1">';
	echo '<input type="hidden" name="wpata_entity" value="province">';
	echo '<input type="hidden" name="pv_id" value="' . esc_attr($editing ? $editing->pv_id : 0) . '">';
	echo '<table class="form-table wpata-form-table"><tbody>';
	echo '<tr><th scope="row"><label for="wpata-pv-idx">รหัสลำดับจังหวัด (pv_idx)</label></th><td><input id="wpata-pv-idx" type="number" min="0" name="pv_idx" class="regular-text" value="' . esc_attr($editing ? $editing->pv_idx : '') . '"><p class="description">ปล่อยว่างได้ ระบบจะสร้างรหัสถัดไปให้</p></td></tr>';
	echo '<tr><th scope="row"><label for="wpata-pv-th">ชื่อจังหวัด (ไทย)</label></th><td><input id="wpata-pv-th" type="text" name="pv_name_th" class="regular-text" value="' . esc_attr($editing ? $editing->pv_name_th : '') . '" required></td></tr>';
	echo '<tr><th scope="row"><label for="wpata-pv-en">ชื่อจังหวัด (อังกฤษ)</label></th><td><input id="wpata-pv-en" type="text" name="pv_name_en" class="regular-text" value="' . esc_attr($editing ? $editing->pv_name_en : '') . '" required></td></tr>';
	echo '</tbody></table>';
	echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html($editing ? 'บันทึกการแก้ไขจังหวัด' : 'เพิ่มจังหวัด') . '</button>';
	if ($editing) {
		echo ' <a class="button" href="' . esc_url($cancel_url) . '">ยกเลิกแก้ไข</a>';
	}
	echo '</p>';
	echo '</form>';
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>รายการจังหวัด</h2>';
	echo '<table class="widefat striped">';
	echo '<thead><tr><th>pv_idx</th><th>ชื่อ (ไทย)</th><th>ชื่อ (อังกฤษ)</th><th>จัดการ</th></tr></thead><tbody>';
	if (empty($provinces)) {
		echo '<tr><td colspan="4">ยังไม่มีข้อมูลจังหวัด</td></tr>';
	} else {
		foreach ($provinces as $province) {
			$edit_url = add_query_arg(
				array(
					'page' => 'wpata-admin',
					'wpata_view' => 'data',
					'tab' => 'province',
					'edit_id' => $province->pv_id,
				),
				admin_url('tools.php')
			);
			echo '<tr>';
			echo '<td>' . esc_html($province->pv_idx) . '</td>';
			echo '<td>' . esc_html($province->pv_name_th) . '</td>';
			echo '<td>' . esc_html($province->pv_name_en) . '</td>';
			echo '<td><div class="wpata-row-actions">';
			echo '<a class="button button-small" href="' . esc_url($edit_url) . '">แก้ไข</a>';
			echo '<form method="post" class="wpata-inline-delete-form" onsubmit="return confirm(\'ยืนยันการลบจังหวัดนี้?\');">';
			echo wp_nonce_field('wpata_delete_data_action', '_wpnonce', true, false);
			echo '<input type="hidden" name="page" value="wpata-admin">';
			echo '<input type="hidden" name="wpata_view" value="data">';
			echo '<input type="hidden" name="wpata_delete_data" value="1">';
			echo '<input type="hidden" name="wpata_entity" value="province">';
			echo '<input type="hidden" name="pv_id" value="' . esc_attr($province->pv_id) . '">';
			echo '<button type="submit" class="button button-small button-link-delete">ลบ</button>';
			echo '</form>';
			echo '</div></td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
	echo '</div>';
}

function wpata_render_district_tab()
{
	$provinces = wpata_get_provinces();
	$province_map = array();
	foreach ($provinces as $province) {
		$province_map[(int) $province->pv_id] = $province;
	}

	$selected_pv_id = wpata_get_admin_int('get', 'pv_id', 0);

	$edit_id = wpata_get_admin_int('get', 'edit_id', 0);
	$editing = $edit_id > 0 ? wpata_get_district_by_id($edit_id) : null;
	if ($editing) {
		$selected_pv_id = (int) $editing->dt_pv_id;
	}

	$districts = $selected_pv_id > 0 ? wpata_get_districts_by_province($selected_pv_id) : array();
	$cancel_url = add_query_arg(
		array(
			'page' => 'wpata-admin',
			'wpata_view' => 'data',
			'tab' => 'district',
			'pv_id' => $selected_pv_id,
		),
		admin_url('tools.php')
	);

	echo '<div class="wpata-card">';
	echo '<h2>เลือกจังหวัดสำหรับจัดการอำเภอ/เขต</h2>';
	echo '<form method="get" class="wpata-inline-filter wpata-filter-form" data-wpata-filter="district">';
	echo '<input type="hidden" name="page" value="wpata-admin">';
	echo '<input type="hidden" name="wpata_view" value="data">';
	echo '<input type="hidden" name="tab" value="district">';
	echo '<select name="pv_id" class="wpata-filter-province">';
	echo '<option value="0" ' . selected($selected_pv_id, 0, false) . '>-- เลือกจังหวัด --</option>';
	foreach ($provinces as $province) {
		echo '<option value="' . esc_attr($province->pv_id) . '" ' . selected($selected_pv_id, (int) $province->pv_id, false) . '>' . esc_html($province->pv_name_th) . '</option>';
	}
	echo '</select> ';
	echo '<button type="submit" class="button">เปลี่ยนจังหวัด</button>';
	echo '</form>';
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>' . ($editing ? 'แก้ไขอำเภอ/เขต' : 'เพิ่มอำเภอ/เขต') . '</h2>';
	if ($selected_pv_id <= 0 || !isset($province_map[$selected_pv_id])) {
		echo '<p>กรุณาเลือกจังหวัดก่อน</p>';
	} else {
		echo '<p class="description">จังหวัดที่กำลังจัดการ: <strong>' . esc_html($province_map[$selected_pv_id]->pv_name_th) . '</strong></p>';
		echo '<form method="post">';
		wp_nonce_field('wpata_save_data_action');
		echo '<input type="hidden" name="page" value="wpata-admin">';
		echo '<input type="hidden" name="wpata_view" value="data">';
		echo '<input type="hidden" name="wpata_save_data" value="1">';
		echo '<input type="hidden" name="wpata_entity" value="district">';
		echo '<input type="hidden" name="dt_id" value="' . esc_attr($editing ? $editing->dt_id : 0) . '">';
		echo '<input type="hidden" name="dt_pv_id" value="' . esc_attr($selected_pv_id) . '">';
		echo '<table class="form-table wpata-form-table"><tbody>';
		echo '<tr><th scope="row"><label for="wpata-dt-idx">รหัสลำดับอำเภอ/เขต (dt_idx)</label></th><td><input id="wpata-dt-idx" type="number" min="0" name="dt_idx" class="regular-text" value="' . esc_attr($editing ? $editing->dt_idx : '') . '"><p class="description">ปล่อยว่างได้ ระบบจะสร้างรหัสถัดไปให้</p></td></tr>';
		echo '<tr><th scope="row"><label for="wpata-dt-th">ชื่ออำเภอ/เขต (ไทย)</label></th><td><input id="wpata-dt-th" type="text" name="dt_name_th" class="regular-text" value="' . esc_attr($editing ? $editing->dt_name_th : '') . '" required></td></tr>';
		echo '<tr><th scope="row"><label for="wpata-dt-en">ชื่ออำเภอ/เขต (อังกฤษ)</label></th><td><input id="wpata-dt-en" type="text" name="dt_name_en" class="regular-text" value="' . esc_attr($editing ? $editing->dt_name_en : '') . '" required></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html($editing ? 'บันทึกการแก้ไขอำเภอ/เขต' : 'เพิ่มอำเภอ/เขต') . '</button>';
		if ($editing) {
			echo ' <a class="button" href="' . esc_url($cancel_url) . '">ยกเลิกแก้ไข</a>';
		}
		echo '</p>';
		echo '</form>';
	}
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>รายการอำเภอ/เขต</h2>';
	echo '<table class="widefat striped">';
	echo '<thead><tr><th>dt_idx</th><th>ชื่อ (ไทย)</th><th>ชื่อ (อังกฤษ)</th><th>จัดการ</th></tr></thead><tbody>';
	if (empty($districts)) {
		$empty_message = $selected_pv_id > 0 ? 'ยังไม่มีข้อมูลอำเภอ/เขตในจังหวัดที่เลือก' : 'กรุณาเลือกจังหวัดก่อนเพื่อดูรายการอำเภอ/เขต';
		echo '<tr><td colspan="4">' . esc_html($empty_message) . '</td></tr>';
	} else {
		foreach ($districts as $district) {
			$edit_url = add_query_arg(
				array(
					'page' => 'wpata-admin',
					'wpata_view' => 'data',
					'tab' => 'district',
					'pv_id' => $selected_pv_id,
					'edit_id' => $district->dt_id,
				),
				admin_url('tools.php')
			);
			echo '<tr>';
			echo '<td>' . esc_html($district->dt_idx) . '</td>';
			echo '<td>' . esc_html($district->dt_name_th) . '</td>';
			echo '<td>' . esc_html($district->dt_name_en) . '</td>';
			echo '<td><div class="wpata-row-actions">';
			echo '<a class="button button-small" href="' . esc_url($edit_url) . '">แก้ไข</a>';
			echo '<form method="post" class="wpata-inline-delete-form" onsubmit="return confirm(\'ยืนยันการลบอำเภอ/เขตนี้?\');">';
			echo wp_nonce_field('wpata_delete_data_action', '_wpnonce', true, false);
			echo '<input type="hidden" name="page" value="wpata-admin">';
			echo '<input type="hidden" name="wpata_view" value="data">';
			echo '<input type="hidden" name="wpata_delete_data" value="1">';
			echo '<input type="hidden" name="wpata_entity" value="district">';
			echo '<input type="hidden" name="pv_id" value="' . esc_attr($selected_pv_id) . '">';
			echo '<input type="hidden" name="dt_id" value="' . esc_attr($district->dt_id) . '">';
			echo '<button type="submit" class="button button-small button-link-delete">ลบ</button>';
			echo '</form>';
			echo '</div></td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
	echo '</div>';
}

function wpata_render_subdistrict_tab()
{
	$provinces = wpata_get_provinces();
	$province_map = array();
	foreach ($provinces as $province) {
		$province_map[(int) $province->pv_id] = $province;
	}

	$selected_pv_id = wpata_get_admin_int('get', 'pv_id', 0);

	$districts = $selected_pv_id > 0 ? wpata_get_districts_by_province($selected_pv_id) : array();
	$selected_dt_id = wpata_get_admin_int('get', 'dt_id', 0);

	$edit_id = wpata_get_admin_int('get', 'edit_id', 0);
	$editing = $edit_id > 0 ? wpata_get_subdistrict_by_id($edit_id) : null;
	if ($editing) {
		$selected_pv_id = (int) $editing->sdt_pv_id;
		$districts = wpata_get_districts_by_province($selected_pv_id);
		$selected_dt_id = (int) $editing->sdt_dt_id;
	}

	$district_map = array();
	foreach ($districts as $district) {
		$district_map[(int) $district->dt_id] = $district;
	}

	$subdistricts = $selected_dt_id > 0 ? wpata_get_subdistricts_by_district($selected_dt_id) : array();
	$cancel_url = add_query_arg(
		array(
			'page' => 'wpata-admin',
			'wpata_view' => 'data',
			'tab' => 'subdistrict',
			'pv_id' => $selected_pv_id,
			'dt_id' => $selected_dt_id,
		),
		admin_url('tools.php')
	);

	echo '<div class="wpata-card">';
	echo '<h2>เลือกจังหวัดและอำเภอ/เขตสำหรับจัดการตำบล/แขวง</h2>';
	echo '<form method="get" class="wpata-inline-filter wpata-filter-form" data-wpata-filter="subdistrict">';
	echo '<input type="hidden" name="page" value="wpata-admin">';
	echo '<input type="hidden" name="wpata_view" value="data">';
	echo '<input type="hidden" name="tab" value="subdistrict">';
	echo '<select name="pv_id" class="wpata-filter-province">';
	echo '<option value="0" ' . selected($selected_pv_id, 0, false) . '>-- เลือกจังหวัด --</option>';
	foreach ($provinces as $province) {
		echo '<option value="' . esc_attr($province->pv_id) . '" ' . selected($selected_pv_id, (int) $province->pv_id, false) . '>' . esc_html($province->pv_name_th) . '</option>';
	}
	echo '</select> ';
	echo '<select name="dt_id" class="wpata-filter-district">';
	echo '<option value="0" ' . selected($selected_dt_id, 0, false) . '>-- เลือกอำเภอ/เขต --</option>';
	foreach ($districts as $district) {
		echo '<option value="' . esc_attr($district->dt_id) . '" ' . selected($selected_dt_id, (int) $district->dt_id, false) . '>' . esc_html($district->dt_name_th) . '</option>';
	}
	echo '</select> ';
	echo '<button type="submit" class="button">เปลี่ยนรายการ</button>';
	echo '</form>';
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>' . ($editing ? 'แก้ไขตำบล/แขวง' : 'เพิ่มตำบล/แขวง') . '</h2>';
	if ($selected_pv_id <= 0 || $selected_dt_id <= 0 || !isset($district_map[$selected_dt_id])) {
		echo '<p>กรุณาเลือกจังหวัดและอำเภอ/เขตก่อน</p>';
	} else {
		echo '<p class="description">จังหวัด: <strong>' . esc_html(isset($province_map[$selected_pv_id]) ? $province_map[$selected_pv_id]->pv_name_th : '-') . '</strong> | อำเภอ/เขต: <strong>' . esc_html($district_map[$selected_dt_id]->dt_name_th) . '</strong></p>';
		echo '<form method="post">';
		wp_nonce_field('wpata_save_data_action');
		echo '<input type="hidden" name="page" value="wpata-admin">';
		echo '<input type="hidden" name="wpata_view" value="data">';
		echo '<input type="hidden" name="wpata_save_data" value="1">';
		echo '<input type="hidden" name="wpata_entity" value="subdistrict">';
		echo '<input type="hidden" name="sdt_id" value="' . esc_attr($editing ? $editing->sdt_id : 0) . '">';
		echo '<input type="hidden" name="sdt_pv_id" value="' . esc_attr($selected_pv_id) . '">';
		echo '<input type="hidden" name="sdt_dt_id" value="' . esc_attr($selected_dt_id) . '">';
		echo '<table class="form-table wpata-form-table"><tbody>';
		echo '<tr><th scope="row"><label for="wpata-sdt-idx">รหัสลำดับตำบล/แขวง (sdt_idx)</label></th><td><input id="wpata-sdt-idx" type="number" min="0" name="sdt_idx" class="regular-text" value="' . esc_attr($editing ? $editing->sdt_idx : '') . '"><p class="description">ปล่อยว่างได้ ระบบจะสร้างรหัสถัดไปให้</p></td></tr>';
		echo '<tr><th scope="row"><label for="wpata-sdt-th">ชื่อตำบล/แขวง (ไทย)</label></th><td><input id="wpata-sdt-th" type="text" name="sdt_name_th" class="regular-text" value="' . esc_attr($editing ? $editing->sdt_name_th : '') . '" required></td></tr>';
		echo '<tr><th scope="row"><label for="wpata-sdt-en">ชื่อตำบล/แขวง (อังกฤษ)</label></th><td><input id="wpata-sdt-en" type="text" name="sdt_name_en" class="regular-text" value="' . esc_attr($editing ? $editing->sdt_name_en : '') . '" required></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html($editing ? 'บันทึกการแก้ไขตำบล/แขวง' : 'เพิ่มตำบล/แขวง') . '</button>';
		if ($editing) {
			echo ' <a class="button" href="' . esc_url($cancel_url) . '">ยกเลิกแก้ไข</a>';
		}
		echo '</p>';
		echo '</form>';
	}
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>รายการตำบล/แขวง</h2>';
	echo '<table class="widefat striped">';
	echo '<thead><tr><th>sdt_idx</th><th>ชื่อ (ไทย)</th><th>ชื่อ (อังกฤษ)</th><th>จัดการ</th></tr></thead><tbody>';
	if (empty($subdistricts)) {
		$empty_message = ($selected_pv_id > 0 && $selected_dt_id > 0) ? 'ยังไม่มีข้อมูลตำบล/แขวงในอำเภอ/เขตที่เลือก' : 'กรุณาเลือกจังหวัดและอำเภอ/เขตก่อนเพื่อดูรายการตำบล/แขวง';
		echo '<tr><td colspan="4">' . esc_html($empty_message) . '</td></tr>';
	} else {
		foreach ($subdistricts as $subdistrict) {
			$edit_url = add_query_arg(
				array(
					'page' => 'wpata-admin',
					'wpata_view' => 'data',
					'tab' => 'subdistrict',
					'pv_id' => $selected_pv_id,
					'dt_id' => $selected_dt_id,
					'edit_id' => $subdistrict->sdt_id,
				),
				admin_url('tools.php')
			);
			echo '<tr>';
			echo '<td>' . esc_html($subdistrict->sdt_idx) . '</td>';
			echo '<td>' . esc_html($subdistrict->sdt_name_th) . '</td>';
			echo '<td>' . esc_html($subdistrict->sdt_name_en) . '</td>';
			echo '<td><div class="wpata-row-actions">';
			echo '<a class="button button-small" href="' . esc_url($edit_url) . '">แก้ไข</a>';
			echo '<form method="post" class="wpata-inline-delete-form" onsubmit="return confirm(\'ยืนยันการลบตำบล/แขวงนี้?\');">';
			echo wp_nonce_field('wpata_delete_data_action', '_wpnonce', true, false);
			echo '<input type="hidden" name="page" value="wpata-admin">';
			echo '<input type="hidden" name="wpata_view" value="data">';
			echo '<input type="hidden" name="wpata_delete_data" value="1">';
			echo '<input type="hidden" name="wpata_entity" value="subdistrict">';
			echo '<input type="hidden" name="pv_id" value="' . esc_attr($selected_pv_id) . '">';
			echo '<input type="hidden" name="dt_id" value="' . esc_attr($selected_dt_id) . '">';
			echo '<input type="hidden" name="sdt_id" value="' . esc_attr($subdistrict->sdt_id) . '">';
			echo '<button type="submit" class="button button-small button-link-delete">ลบ</button>';
			echo '</form>';
			echo '</div></td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
	echo '</div>';
}

function wpata_render_postalcode_tab()
{
	$provinces = wpata_get_provinces();
	$province_map = array();
	foreach ($provinces as $province) {
		$province_map[(int) $province->pv_id] = $province;
	}

	$selected_pv_id = wpata_get_admin_int('get', 'pv_id', 0);

	$districts = $selected_pv_id > 0 ? wpata_get_districts_by_province($selected_pv_id) : array();
	$selected_dt_id = wpata_get_admin_int('get', 'dt_id', 0);

	$subdistricts = $selected_dt_id > 0 ? wpata_get_subdistricts_by_district($selected_dt_id) : array();
	$selected_sdt_id = wpata_get_admin_int('get', 'sdt_id', 0);

	$edit_id = wpata_get_admin_int('get', 'edit_id', 0);
	$editing = $edit_id > 0 ? wpata_get_postalcode_by_id($edit_id) : null;
	if ($editing) {
		$selected_pv_id = (int) $editing->ptc_pv_id;
		$districts = wpata_get_districts_by_province($selected_pv_id);
		$selected_dt_id = (int) $editing->ptc_dt_id;
		$subdistricts = wpata_get_subdistricts_by_district($selected_dt_id);
		$selected_sdt_id = (int) $editing->ptc_sdt_id;
	}

	$district_map = array();
	foreach ($districts as $district) {
		$district_map[(int) $district->dt_id] = $district;
	}
	$subdistrict_map = array();
	foreach ($subdistricts as $subdistrict) {
		$subdistrict_map[(int) $subdistrict->sdt_id] = $subdistrict;
	}

	$postalcodes = $selected_sdt_id > 0 ? wpata_get_postalcodes_by_subdistrict($selected_sdt_id) : array();
	$cancel_url = add_query_arg(
		array(
			'page' => 'wpata-admin',
			'wpata_view' => 'data',
			'tab' => 'postalcode',
			'pv_id' => $selected_pv_id,
			'dt_id' => $selected_dt_id,
			'sdt_id' => $selected_sdt_id,
		),
		admin_url('tools.php')
	);

	echo '<div class="wpata-card">';
	echo '<h2>เลือกพื้นที่สำหรับจัดการรหัสไปรษณีย์</h2>';
	echo '<form method="get" class="wpata-inline-filter wpata-filter-form" data-wpata-filter="postalcode">';
	echo '<input type="hidden" name="page" value="wpata-admin">';
	echo '<input type="hidden" name="wpata_view" value="data">';
	echo '<input type="hidden" name="tab" value="postalcode">';
	echo '<select name="pv_id" class="wpata-filter-province">';
	echo '<option value="0" ' . selected($selected_pv_id, 0, false) . '>-- เลือกจังหวัด --</option>';
	foreach ($provinces as $province) {
		echo '<option value="' . esc_attr($province->pv_id) . '" ' . selected($selected_pv_id, (int) $province->pv_id, false) . '>' . esc_html($province->pv_name_th) . '</option>';
	}
	echo '</select> ';
	echo '<select name="dt_id" class="wpata-filter-district">';
	echo '<option value="0" ' . selected($selected_dt_id, 0, false) . '>-- เลือกอำเภอ/เขต --</option>';
	foreach ($districts as $district) {
		echo '<option value="' . esc_attr($district->dt_id) . '" ' . selected($selected_dt_id, (int) $district->dt_id, false) . '>' . esc_html($district->dt_name_th) . '</option>';
	}
	echo '</select> ';
	echo '<select name="sdt_id" class="wpata-filter-subdistrict">';
	echo '<option value="0" ' . selected($selected_sdt_id, 0, false) . '>-- เลือกตำบล/แขวง --</option>';
	foreach ($subdistricts as $subdistrict) {
		echo '<option value="' . esc_attr($subdistrict->sdt_id) . '" ' . selected($selected_sdt_id, (int) $subdistrict->sdt_id, false) . '>' . esc_html($subdistrict->sdt_name_th) . '</option>';
	}
	echo '</select> ';
	echo '<button type="submit" class="button">เปลี่ยนรายการ</button>';
	echo '</form>';
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>' . ($editing ? 'แก้ไขรหัสไปรษณีย์' : 'เพิ่มรหัสไปรษณีย์') . '</h2>';
	if ($selected_sdt_id <= 0 || !isset($subdistrict_map[$selected_sdt_id])) {
		echo '<p>กรุณาเลือกตำบล/แขวงก่อน</p>';
	} else {
		echo '<p class="description">จังหวัด: <strong>' . esc_html(isset($province_map[$selected_pv_id]) ? $province_map[$selected_pv_id]->pv_name_th : '-') . '</strong> | อำเภอ/เขต: <strong>' . esc_html(isset($district_map[$selected_dt_id]) ? $district_map[$selected_dt_id]->dt_name_th : '-') . '</strong> | ตำบล/แขวง: <strong>' . esc_html($subdistrict_map[$selected_sdt_id]->sdt_name_th) . '</strong></p>';
		echo '<form method="post">';
		wp_nonce_field('wpata_save_data_action');
		echo '<input type="hidden" name="page" value="wpata-admin">';
		echo '<input type="hidden" name="wpata_view" value="data">';
		echo '<input type="hidden" name="wpata_save_data" value="1">';
		echo '<input type="hidden" name="wpata_entity" value="postalcode">';
		echo '<input type="hidden" name="ptc_id" value="' . esc_attr($editing ? $editing->ptc_id : 0) . '">';
		echo '<input type="hidden" name="ptc_sdt_id" value="' . esc_attr($selected_sdt_id) . '">';
		echo '<table class="form-table wpata-form-table"><tbody>';
		echo '<tr><th scope="row"><label for="wpata-ptc-idx">รหัสไปรษณีย์</label></th><td><input id="wpata-ptc-idx" type="text" name="ptc_idx" class="regular-text" value="' . esc_attr($editing ? $editing->ptc_idx : '') . '" required><p class="description">ระบบจะเก็บเฉพาะตัวเลข</p></td></tr>';
		echo '</tbody></table>';
		echo '<p class="submit"><button type="submit" class="button button-primary">' . esc_html($editing ? 'บันทึกการแก้ไขรหัสไปรษณีย์' : 'เพิ่มรหัสไปรษณีย์') . '</button>';
		if ($editing) {
			echo ' <a class="button" href="' . esc_url($cancel_url) . '">ยกเลิกแก้ไข</a>';
		}
		echo '</p>';
		echo '</form>';
	}
	echo '</div>';

	echo '<div class="wpata-card">';
	echo '<h2>รายการรหัสไปรษณีย์</h2>';
	echo '<table class="widefat striped">';
	echo '<thead><tr><th>รหัสไปรษณีย์</th><th>จัดการ</th></tr></thead><tbody>';
	if (empty($postalcodes)) {
		$empty_message = $selected_sdt_id > 0 ? 'ยังไม่มีข้อมูลรหัสไปรษณีย์ในตำบล/แขวงที่เลือก' : 'กรุณาเลือกตำบล/แขวงก่อนเพื่อดูรายการรหัสไปรษณีย์';
		echo '<tr><td colspan="2">' . esc_html($empty_message) . '</td></tr>';
	} else {
		foreach ($postalcodes as $postalcode) {
			$edit_url = add_query_arg(
				array(
					'page' => 'wpata-admin',
					'wpata_view' => 'data',
					'tab' => 'postalcode',
					'pv_id' => $selected_pv_id,
					'dt_id' => $selected_dt_id,
					'sdt_id' => $selected_sdt_id,
					'edit_id' => $postalcode->ptc_id,
				),
				admin_url('tools.php')
			);
			echo '<tr>';
			echo '<td>' . esc_html($postalcode->ptc_idx) . '</td>';
			echo '<td><div class="wpata-row-actions">';
			echo '<a class="button button-small" href="' . esc_url($edit_url) . '">แก้ไข</a>';
			echo '<form method="post" class="wpata-inline-delete-form" onsubmit="return confirm(\'ยืนยันการลบรหัสไปรษณีย์นี้?\');">';
			echo wp_nonce_field('wpata_delete_data_action', '_wpnonce', true, false);
			echo '<input type="hidden" name="page" value="wpata-admin">';
			echo '<input type="hidden" name="wpata_view" value="data">';
			echo '<input type="hidden" name="wpata_delete_data" value="1">';
			echo '<input type="hidden" name="wpata_entity" value="postalcode">';
			echo '<input type="hidden" name="pv_id" value="' . esc_attr($selected_pv_id) . '">';
			echo '<input type="hidden" name="dt_id" value="' . esc_attr($selected_dt_id) . '">';
			echo '<input type="hidden" name="sdt_id" value="' . esc_attr($selected_sdt_id) . '">';
			echo '<input type="hidden" name="ptc_id" value="' . esc_attr($postalcode->ptc_id) . '">';
			echo '<button type="submit" class="button button-small button-link-delete">ลบ</button>';
			echo '</form>';
			echo '</div></td>';
			echo '</tr>';
		}
	}
	echo '</tbody></table>';
	echo '</div>';
}

function wpata_render_admin_data_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}

	$tabs = wpata_data_tabs();
	$active_tab = isset($_GET['tab']) ? sanitize_key(wp_unslash($_GET['tab'])) : 'province';
	if (!isset($tabs[$active_tab])) {
		$active_tab = 'province';
	}

	wpata_render_admin_page_header('จัดการข้อมูลที่อยู่', 'เพิ่ม/แก้ไขข้อมูลแบบลำดับชั้น โดยมีการตรวจความสัมพันธ์ จังหวัด -> อำเภอ/เขต -> ตำบล/แขวง -> รหัสไปรษณีย์', 'data');
	wpata_render_admin_notice_from_query();
	wpata_render_admin_data_tabs($active_tab);

	switch ($active_tab) {
		case 'district':
			wpata_render_district_tab();
			break;
		case 'subdistrict':
			wpata_render_subdistrict_tab();
			break;
		case 'postalcode':
			wpata_render_postalcode_tab();
			break;
		case 'province':
		default:
			wpata_render_province_tab();
			break;
	}

	wpata_render_admin_page_footer();
}

function wpata_render_admin_router_page()
{
	if (!current_user_can('manage_options')) {
		return;
	}

	$view = isset($_GET['wpata_view']) ? sanitize_key(wp_unslash($_GET['wpata_view'])) : 'overview';
	switch ($view) {
		case 'settings':
			wpata_render_admin_settings_page();
			break;
		case 'data':
			wpata_render_admin_data_page();
			break;
		case 'overview':
		default:
			wpata_render_admin_dashboard_page();
			break;
	}
}

function wpata_plugin_activate()
{
	$wpata_plugin_version = get_option('wpata_plugin_version');
	if (!empty($wpata_plugin_version)) {
		return;
	}

	add_option('wpata_plugin_version', WPATA_VERSION);

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
	if (!is_array($arr_address)) {
		return;
	}

	foreach ($arr_address as $pv_idx => $pv_data) {
		if (!isset($pv_data['pv_name_th'], $pv_data['pv_name_en'])) {
			continue;
		}

		$wpdb->insert(
			"{$wpdb->prefix}wpata_province",
			array(
				'pv_idx' => $pv_idx,
				'pv_name_th' => $pv_data['pv_name_th'],
				'pv_name_en' => $pv_data['pv_name_en'],
			)
		);
		$pv_id = $wpdb->insert_id;

		unset($pv_data['pv_name_th']);
		unset($pv_data['pv_name_en']);

		foreach ($pv_data as $dt_idx => $dt_data) {
			if (!isset($dt_data['dt_name_th'], $dt_data['dt_name_en'])) {
				continue;
			}

			$wpdb->insert(
				"{$wpdb->prefix}wpata_district",
				array(
					'dt_idx' => $dt_idx,
					'dt_name_th' => $dt_data['dt_name_th'],
					'dt_name_en' => $dt_data['dt_name_en'],
					'dt_pv_id' => $pv_id,
				)
			);
			$dt_id = $wpdb->insert_id;

			unset($dt_data['dt_name_th']);
			unset($dt_data['dt_name_en']);

			foreach ($dt_data as $sdt_idx => $sdt_data) {
				if (!isset($sdt_data['sdt_name_th'], $sdt_data['sdt_name_en'])) {
					continue;
				}

				$wpdb->insert(
					"{$wpdb->prefix}wpata_subdistrict",
					array(
						'sdt_idx' => $sdt_idx,
						'sdt_name_th' => $sdt_data['sdt_name_th'],
						'sdt_name_en' => $sdt_data['sdt_name_en'],
						'sdt_pv_id' => $pv_id,
						'sdt_dt_id' => $dt_id,
					)
				);
				$sdt_id = $wpdb->insert_id;

				if (!isset($sdt_data['sdt_postal_code']) || !is_array($sdt_data['sdt_postal_code'])) {
					continue;
				}

				foreach ($sdt_data['sdt_postal_code'] as $postal_code) {
					$wpdb->insert(
						"{$wpdb->prefix}wpata_postalcode",
						array(
							'ptc_idx' => $postal_code,
							'ptc_pv_id' => $pv_id,
							'ptc_dt_id' => $dt_id,
							'ptc_sdt_id' => $sdt_id,
						)
					);
				}
			}
		}
	}

	// @unlink(WP_PLUGIN_DIR . '/' . WPATA_SLUG . '/address.json');
}
register_activation_hook(__FILE__, 'wpata_plugin_activate');

function wpata_delete_all_plugin_data()
{
	global $wpdb;

	$tables = array(
		wpata_table_name('postalcode'),
		wpata_table_name('subdistrict'),
		wpata_table_name('district'),
		wpata_table_name('province'),
	);

	foreach ($tables as $table) {
		$wpdb->query("DROP TABLE IF EXISTS `{$table}`");
	}

	delete_option('wpata_plugin_version');

	$rows = wpata_setting_rows();
	foreach ($rows as $row) {
		if (isset($row['key_th'])) {
			delete_option($row['key_th']);
		}
		if (isset($row['key_en'])) {
			delete_option($row['key_en']);
		}
	}
}

function wpata_plugin_deactivate()
{
	$cleanup = isset($_GET['wpata_cleanup']) ? sanitize_key(wp_unslash($_GET['wpata_cleanup'])) : 'keep';
	if ($cleanup !== 'all') {
		return;
	}

	wpata_delete_all_plugin_data();
}
register_deactivation_hook(__FILE__, 'wpata_plugin_deactivate');

function wpata_deactivate_confirm_script()
{
	if (!current_user_can('activate_plugins')) {
		return;
	}

	$plugin_slug = plugin_basename(__FILE__);
	$message = "ต้องการลบข้อมูลปลั๊กอินทั้งหมดด้วยหรือไม่?\nกด \"ตกลง\" = ปิดปลั๊กอินและลบข้อมูลทั้งหมด\nกด \"ยกเลิก\" = ปิดปลั๊กอินแต่เก็บข้อมูลไว้";
	?>
	<script>
		jQuery(function ($) {
			var pluginSlug = <?php echo wp_json_encode($plugin_slug); ?>;
			var confirmMessage = <?php echo wp_json_encode($message); ?>;
			var selector = 'tr[data-plugin="' + pluginSlug + '"] .deactivate a';

			$(document).on('click', selector, function (event) {
				var href = $(this).attr('href') || '';
				if (href.indexOf('action=deactivate') === -1) {
					return;
				}
				if (/(?:\?|&)wpata_cleanup=/.test(href)) {
					return;
				}

				event.preventDefault();
				var shouldCleanup = window.confirm(confirmMessage);
				var separator = href.indexOf('?') === -1 ? '?' : '&';
				window.location.href = href + separator + 'wpata_cleanup=' + (shouldCleanup ? 'all' : 'keep');
			});
		});
	</script>
	<?php
}
add_action('admin_footer-plugins.php', 'wpata_deactivate_confirm_script');

function wpata_enqueue_scripts()
{
	$dir = WP_PLUGIN_URL . '/' . WPATA_SLUG . '/assets/';
	wp_enqueue_script('wpata-js', $dir . 'wpata.js', array('jquery'), WPATA_VERSION, true);
	wp_localize_script(
		'wpata-js',
		'wpataConfig',
		array(
			'ajaxUrl' => admin_url('admin-ajax.php'),
		)
	);
}
add_action('admin_enqueue_scripts', 'wpata_enqueue_scripts');
add_action('wp_enqueue_scripts', 'wpata_enqueue_scripts');

function wpata_load_province_option()
{
	if (wpata_block_public_ajax_if_required()) {
		return;
	}

	$lang = wpata_request_lang();
	global $wpdb;
	$name_col = "pv_name_{$lang}";
	$strSql = "SELECT pv_id, {$name_col} as pv_name FROM {$wpdb->prefix}wpata_province ORDER BY {$name_col} ASC";
	$rs = $wpdb->get_results($strSql);
	if (empty($rs)) {
		echo '<option value="">' . esc_html(wpata_option_label('nodata', $lang)) . '</option>';
	} else {
		echo '<option value="">' . esc_html(wpata_option_label('province', $lang)) . '</option>';
		foreach ($rs as $data) {
			echo '<option value="' . esc_attr($data->pv_id) . '">' . esc_html($data->pv_name) . '</option>';
		}
	}
	wp_die();
}
add_action('wp_ajax_wpata_load_province_option', 'wpata_load_province_option');
add_action('wp_ajax_nopriv_wpata_load_province_option', 'wpata_load_province_option');

function wpata_load_district_option()
{
	if (wpata_block_public_ajax_if_required()) {
		return;
	}

	$lang = wpata_request_lang();
	$pv_id = isset($_GET['wpataprov']) ? absint(wp_unslash($_GET['wpataprov'])) : 0;
	global $wpdb;
	$name_col = "dt_name_{$lang}";
	$strSql = "SELECT dt_id, {$name_col} as dt_name FROM {$wpdb->prefix}wpata_district";
	if ($pv_id > 0) {
		$strSql .= $wpdb->prepare(' WHERE dt_pv_id=%d', $pv_id);
	}
	$strSql .= " ORDER BY {$name_col} ASC";
	$rs = $wpdb->get_results($strSql);
	if (empty($rs)) {
		echo '<option value="">' . esc_html(wpata_option_label('nodata', $lang)) . '</option>';
	} else {
		echo '<option value="">' . esc_html(wpata_option_label('district', $lang)) . '</option>';
		foreach ($rs as $data) {
			echo '<option value="' . esc_attr($data->dt_id) . '">' . esc_html($data->dt_name) . '</option>';
		}
	}
	wp_die();
}
add_action('wp_ajax_wpata_load_district_option', 'wpata_load_district_option');
add_action('wp_ajax_nopriv_wpata_load_district_option', 'wpata_load_district_option');

function wpata_load_subdistrict_option()
{
	if (wpata_block_public_ajax_if_required()) {
		return;
	}

	$lang = wpata_request_lang();
	$pv_id = isset($_GET['wpataprov']) ? absint(wp_unslash($_GET['wpataprov'])) : 0;
	$dt_id = isset($_GET['wpatadist']) ? absint(wp_unslash($_GET['wpatadist'])) : 0;
	global $wpdb;
	$name_col = "sdt_name_{$lang}";
	$strSql = "SELECT sdt_id, {$name_col} as sdt_name FROM {$wpdb->prefix}wpata_subdistrict WHERE 1=1";
	$params = array();
	if ($pv_id > 0) {
		$strSql .= ' AND sdt_pv_id=%d';
		$params[] = $pv_id;
	}
	if ($dt_id > 0) {
		$strSql .= ' AND sdt_dt_id=%d';
		$params[] = $dt_id;
	}
	$strSql .= " ORDER BY {$name_col} ASC";
	if (!empty($params)) {
		$strSql = $wpdb->prepare($strSql, $params);
	}
	$rs = $wpdb->get_results($strSql);
	if (empty($rs)) {
		echo '<option value="">' . esc_html(wpata_option_label('nodata', $lang)) . '</option>';
	} else {
		echo '<option value="">' . esc_html(wpata_option_label('subdistrict', $lang)) . '</option>';
		foreach ($rs as $data) {
			echo '<option value="' . esc_attr($data->sdt_id) . '">' . esc_html($data->sdt_name) . '</option>';
		}
	}
	wp_die();
}
add_action('wp_ajax_wpata_load_subdistrict_option', 'wpata_load_subdistrict_option');
add_action('wp_ajax_nopriv_wpata_load_subdistrict_option', 'wpata_load_subdistrict_option');

function wpata_load_postalcode_option()
{
	if (wpata_block_public_ajax_if_required()) {
		return;
	}

	$lang = wpata_request_lang();
	$pv_id = isset($_GET['wpataprov']) ? absint(wp_unslash($_GET['wpataprov'])) : 0;
	$dt_id = isset($_GET['wpatadist']) ? absint(wp_unslash($_GET['wpatadist'])) : 0;
	$sdt_id = isset($_GET['wpatasubdist']) ? absint(wp_unslash($_GET['wpatasubdist'])) : 0;
	global $wpdb;
	$strSql = "SELECT ptc_id, ptc_idx FROM {$wpdb->prefix}wpata_postalcode WHERE 1=1";
	$params = array();
	if ($pv_id > 0) {
		$strSql .= ' AND ptc_pv_id=%d';
		$params[] = $pv_id;
	}
	if ($dt_id > 0) {
		$strSql .= ' AND ptc_dt_id=%d';
		$params[] = $dt_id;
	}
	if ($sdt_id > 0) {
		$strSql .= ' AND ptc_sdt_id=%d';
		$params[] = $sdt_id;
	}
	$strSql .= " ORDER BY ptc_idx ASC";
	if (!empty($params)) {
		$strSql = $wpdb->prepare($strSql, $params);
	}
	$rs = $wpdb->get_results($strSql);
	if (empty($rs)) {
		echo '<option value="">' . esc_html(wpata_option_label('nodata', $lang)) . '</option>';
	} else {
		echo '<option value="">' . esc_html(wpata_option_label('postalcode', $lang)) . '</option>';
		foreach ($rs as $data) {
			echo '<option value="' . esc_attr($data->ptc_id) . '" ' . (count($rs) === 1 ? 'selected' : '') . '>' . esc_html($data->ptc_idx) . '</option>';
		}
	}
	wp_die();
}
add_action('wp_ajax_wpata_load_postalcode_option', 'wpata_load_postalcode_option');
add_action('wp_ajax_nopriv_wpata_load_postalcode_option', 'wpata_load_postalcode_option');
