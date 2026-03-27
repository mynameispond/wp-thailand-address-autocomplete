<?php
/**
 * Example: default value with saved address ids
 *
 * Usage:
 * 1) Put this snippet in your theme template / custom plugin.
 * 2) Replace `$saved_address` with your own saved IDs (from user meta/order meta).
 * 3) Province/District/Subdistrict/Postalcode must be from the same chain.
 * 4) Ensure this plugin is active.
 */

if (!defined('ABSPATH')) {
	exit;
}

// ค่าเริ่มต้นว่างไว้ก่อน เผื่อกรณีไม่มีข้อมูลเดิมของผู้ใช้
$saved_address = array(
	'province_id' => '',
	'district_id' => '',
	'subdistrict_id' => '',
	'postalcode_id' => '',
);

/**
 * ตัวอย่าง fallback:
 * ถ้ายังไม่มีข้อมูลเดิมของผู้ใช้ ให้ดึง "ชุดแรกที่สัมพันธ์กันจริง" จากฐานข้อมูล
 * เพื่อให้ทดสอบตัวอย่างได้ทันทีโดยไม่เจอปัญหา parent/child ไม่ตรงกัน
 */
global $wpdb;
$province_table = $wpdb->prefix . 'wpata_province';
$district_table = $wpdb->prefix . 'wpata_district';
$subdistrict_table = $wpdb->prefix . 'wpata_subdistrict';
$postalcode_table = $wpdb->prefix . 'wpata_postalcode';

$has_province_table = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $province_table));
if ($has_province_table === $province_table) {
	$pv_id = (int) $wpdb->get_var("SELECT pv_id FROM {$province_table} ORDER BY pv_id ASC LIMIT 1");
	if ($pv_id > 0) {
		$dt_id = (int) $wpdb->get_var(
			$wpdb->prepare("SELECT dt_id FROM {$district_table} WHERE dt_pv_id=%d ORDER BY dt_id ASC LIMIT 1", $pv_id)
		);
		$sdt_id = $dt_id > 0 ? (int) $wpdb->get_var(
			$wpdb->prepare("SELECT sdt_id FROM {$subdistrict_table} WHERE sdt_pv_id=%d AND sdt_dt_id=%d ORDER BY sdt_id ASC LIMIT 1", $pv_id, $dt_id)
		) : 0;
		$ptc_id = $sdt_id > 0 ? (int) $wpdb->get_var(
			$wpdb->prepare("SELECT ptc_id FROM {$postalcode_table} WHERE ptc_pv_id=%d AND ptc_dt_id=%d AND ptc_sdt_id=%d ORDER BY ptc_id ASC LIMIT 1", $pv_id, $dt_id, $sdt_id)
		) : 0;

		$saved_address = array(
			'province_id' => $pv_id > 0 ? (string) $pv_id : '',
			'district_id' => $dt_id > 0 ? (string) $dt_id : '',
			'subdistrict_id' => $sdt_id > 0 ? (string) $sdt_id : '',
			'postalcode_id' => $ptc_id > 0 ? (string) $ptc_id : '',
		);
	}
}
?>

<div class="customer-address-set wpata-group-customer">
	<p><strong>Customer Address (Default Value)</strong></p>

	<p>
		<label for="customer-province">Province</label><br>
		<select
			id="customer-province"
			name="customer_province"
			class="wpata-select-province"
			data-wpata-value="<?php echo esc_attr($saved_address['province_id']); ?>">
		</select>
	</p>

	<p>
		<label for="customer-district">District</label><br>
		<select
			id="customer-district"
			name="customer_district"
			class="wpata-select-district"
			data-wpata-value="<?php echo esc_attr($saved_address['district_id']); ?>">
		</select>
	</p>

	<p>
		<label for="customer-subdistrict">Subdistrict</label><br>
		<select
			id="customer-subdistrict"
			name="customer_subdistrict"
			class="wpata-select-subdistrict"
			data-wpata-value="<?php echo esc_attr($saved_address['subdistrict_id']); ?>">
		</select>
	</p>

	<p>
		<label for="customer-postalcode">Postal code</label><br>
		<select
			id="customer-postalcode"
			name="customer_postalcode"
			class="wpata-select-postalcode"
			data-wpata-value="<?php echo esc_attr($saved_address['postalcode_id']); ?>">
		</select>
	</p>
</div>
