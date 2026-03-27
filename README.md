# WP Autocomplete Thailand Address
ปลั๊กอินสำหรับช่วยเลือกที่อยู่ไทยแบบไล่ลำดับ:
`จังหวัด -> อำเภอ/เขต -> ตำบล/แขวง -> รหัสไปรษณีย์`

## 1) ติดตั้งปลั๊กอิน
1. อัปโหลดโฟลเดอร์ปลั๊กอินไปที่ `wp-content/plugins/`
2. เปิดใช้งานปลั๊กอินจากหน้า WordPress Admin

## 2) วางฟอร์มพื้นฐาน
ใส่ class ให้กับ `select` โดยใส่ที่ตัว `select` โดยตรง หรือใส่ที่ parent ก็ได้

```html
<select class="wpata-select-province"></select>
<select class="wpata-select-district"></select>
<select class="wpata-select-subdistrict"></select>
<select class="wpata-select-postalcode"></select>
```

## 3) การจัดกลุ่ม (`wpata-group-*`)
`wpata-group-*` คือ class ที่ผู้ใช้กำหนดเอง เพื่อบอกว่า select 4 ตัวไหนเป็น "ชุดเดียวกัน"

### เมื่อไหร่ "ต้อง" ใส่ `wpata-group-*`
- มีหลายชุดใน context เดียวกัน และจะ init พร้อมกัน (เช่น `wpataInit(document)` หรือ `wpataInit('#container')` ที่มีหลายชุดใหม่)
- ต้องการให้จับคู่ field ได้แน่นอน ไม่พึ่งลำดับ DOM

### เมื่อไหร่ "ไม่ใส่ก็ได้"
- มีแค่ 1 ชุดในหน้านั้น
- append ทีละ 1 ชุด แล้วเรียก `wpataInit()` ทันทีหลัง append (ไม่ว่าจะส่ง `#new-set`, `#container`, หรือ `$nodes`)
  เพราะตอนนั้นมีเพียงชุดใหม่ที่ยังไม่ถูก init

### ตัวอย่างแบบแนะนำ (หลายชุดในหน้าเดียว)
ใส่ group ที่ parent ได้เลย ไม่จำเป็นต้องใส่ซ้ำทุก select

```html
<div class="address-set-a wpata-group-1">
  <select class="wpata-select-province"></select>
  <select class="wpata-select-district"></select>
  <select class="wpata-select-subdistrict"></select>
  <select class="wpata-select-postalcode"></select>
</div>

<div class="address-set-b wpata-group-2">
  <select class="wpata-select-province"></select>
  <select class="wpata-select-district"></select>
  <select class="wpata-select-subdistrict"></select>
  <select class="wpata-select-postalcode"></select>
</div>
```

## 4) ตั้งค่าเริ่มต้น (Initial Value)
สามารถกำหนดค่าเริ่มต้นได้ 2 แบบ:
1. มีค่า `value` อยู่แล้วใน `select` ก่อนปลั๊กอินเริ่มทำงาน
2. ใส่ `data-wpata-value` เพื่อบังคับค่าเริ่มต้น

```html
<select class="wpata-select-province wpata-group-1" data-wpata-value="{{province_id}}"></select>
<select class="wpata-select-district wpata-group-1" data-wpata-value="{{district_id}}"></select>
<select class="wpata-select-subdistrict wpata-group-1" data-wpata-value="{{subdistrict_id}}"></select>
<select class="wpata-select-postalcode wpata-group-1" data-wpata-value="{{postalcode_id}}"></select>
```

หมายเหตุ: ค่าใน `data-wpata-value` ต้องเป็นค่า `value` ของ option ในแต่ละระดับ
และต้องเป็นชุดข้อมูลที่สัมพันธ์กัน (จังหวัด/อำเภอ/ตำบล/รหัสไปรษณีย์จากเส้นเดียวกัน)

## 5) เพิ่ม input ภายหลัง (AJAX / append) และการเรียก `wpataInit()`
เมื่อมีการเพิ่มฟอร์มใหม่ทีหลังหน้าโหลดเสร็จ ให้เรียก:

```js
window.wpataInit(containerElement);
```

`context` ที่ส่งเข้า `wpataInit()` ได้:
- ไม่ส่งค่า / `document` = init ทั้งหน้า
- string selector เช่น `#id`, `.class`
- DOM element
- jQuery object (แนะนำที่สุดหลัง append เพราะชี้เฉพาะ node ใหม่)
- ไม่แนะนำส่ง HTML string ตรงๆ

### ตัวอย่าง 1: append ทีละ 1 ชุด (ไม่ใส่ group ก็ได้)

```js
const html = `
  <div id="new-address-set">
    <select class="wpata-select-province"></select>
    <select class="wpata-select-district"></select>
    <select class="wpata-select-subdistrict"></select>
    <select class="wpata-select-postalcode"></select>
  </div>
`;

$('#target').append(html);
window.wpataInit('#target'); // ได้ เพราะ append ทีละชุด และ init ทันที
```

### ตัวอย่าง 2: append หลายชุดพร้อมกัน (ควรใส่ group)

```js
const html = `
  <div class="wpata-group-a">
    <select class="wpata-select-province"></select>
    <select class="wpata-select-district"></select>
    <select class="wpata-select-subdistrict"></select>
    <select class="wpata-select-postalcode"></select>
  </div>
  <div class="wpata-group-b">
    <select class="wpata-select-province"></select>
    <select class="wpata-select-district"></select>
    <select class="wpata-select-subdistrict"></select>
    <select class="wpata-select-postalcode"></select>
  </div>
`;

const $nodes = $(html);
$('#target').append($nodes);
window.wpataInit($nodes); // แนะนำ
```

หรือ trigger event ได้เช่นกัน:

```js
$(document).trigger('wpata:init', ['#target']);
```

หมายเหตุ:
- ระบบตั้งค่า `window.wpataConfig.ajaxUrl` ให้อัตโนมัติแล้ว
- ไม่ต้องกำหนดเองทุกหน้า เว้นแต่ต้องการ override endpoint

### Cheat Sheet (ตัดสินใจเร็ว)

| สถานการณ์ | ต้องใส่ `wpata-group-*` ไหม | ควรเรียก `wpataInit(...)` แบบไหน | หมายเหตุ |
|---|---|---|---|
| มีฟอร์มแค่ 1 ชุดในหน้า | ไม่ต้องใส่ก็ได้ | `wpataInit(document)` หรือ `wpataInit('#container')` | ใช้งานได้ปกติ |
| Append ทีละ 1 ชุด แล้ว init ทันที | ไม่ต้องใส่ก็ได้ | `wpataInit('#container')` หรือ `wpataInit($newNodes)` | แบบที่ปลอดภัยสุดคือ `$newNodes` |
| Append หลายชุดพร้อมกันในครั้งเดียว | ควรใส่ | `wpataInit($newNodes)` | ช่วยให้จับคู่ 4 select ต่อชุดได้ชัดเจน |
| หน้าเดียวมีหลายชุดอยู่แล้ว แล้ว init ทั้งหน้า | ควรใส่ | `wpataInit(document)` | ไม่ควรพึ่งลำดับ DOM |
| เพิ่มชุดหลายรอบ แล้วค่อย init ทีเดียวภายหลัง | ควรใส่ | `wpataInit('#container')` | ลดโอกาสจับคู่ข้ามชุด |

ตัวอย่างสั้น:

```js
// 1) Append ทีละชุด (ไม่ใส่ group ก็ได้)
$('#target').append(oneSetHtml);
window.wpataInit('#target');

// 2) Append หลายชุดพร้อมกัน (ควรใส่ group ใน HTML ทุกชุด)
const $nodes = $(manySetsHtml);
$('#target').append($nodes);
window.wpataInit($nodes);
```

## 6) การเลือกภาษา
ปลั๊กอินจะเลือกภาษาตามลำดับความสำคัญ:
1. class `wpata-lang-*` บนหน้า เช่น `wpata-lang-th` หรือ `wpata-lang-en`
2. ค่า `lang` ของ `<html>` เช่น `th` หรือ `en-US`
3. ค่าเริ่มต้นเป็น `th`

ตัวอย่าง:

```html
<div class="wpata-lang-en"></div>
```

## 7) ปรับข้อความหัวข้อใน dropdown (ไม่บังคับ)
สามารถตั้งค่า option ใน WordPress เพื่อเปลี่ยนข้อความได้:

- `wpata_nodata_th`, `wpata_nodata_en`
- `wpata_province_th`, `wpata_province_en`
- `wpata_district_th`, `wpata_district_en`
- `wpata_subdistrict_th`, `wpata_subdistrict_en`
- `wpata_postalcode_th`, `wpata_postalcode_en`

ถ้าไม่ได้ตั้งค่า ปลั๊กอินจะใช้ข้อความ default ให้อัตโนมัติ

## 8) ตั้งค่าข้อความผ่านหลังบ้าน (Admin)
ไปที่เมนู `Tools > WP Thailand Address` แล้วเลือกแท็บ `ตั้งค่าข้อความ`

หน้านี้สามารถแก้ค่าได้โดยตรงสำหรับ:
- `wpata_nodata_th`, `wpata_nodata_en`
- `wpata_province_th`, `wpata_province_en`
- `wpata_district_th`, `wpata_district_en`
- `wpata_subdistrict_th`, `wpata_subdistrict_en`
- `wpata_postalcode_th`, `wpata_postalcode_en`

## 9) จัดการข้อมูลที่อยู่ผ่านหลังบ้าน (Admin)
ไปที่เมนู `Tools > WP Thailand Address` แล้วเลือกแท็บ `จัดการข้อมูลที่อยู่`

สามารถทำได้:
1. เพิ่ม/แก้ไขจังหวัด
2. เพิ่ม/แก้ไขอำเภอ/เขต (อ้างอิงจังหวัด)
3. เพิ่ม/แก้ไขตำบล/แขวง (อ้างอิงอำเภอ/เขต และจังหวัด)
4. เพิ่ม/แก้ไขรหัสไปรษณีย์ (อ้างอิงตำบล/แขวง)
5. ลบข้อมูลแบบปลอดภัย โดยระบบจะไม่ให้ลบ parent ถ้ายังมีข้อมูลลูกเชื่อมอยู่
6. ฟิลเตอร์จังหวัด/อำเภอ/ตำบลในหน้า Admin เป็นแบบ live dependent dropdown (dropdown ลูกเปลี่ยนทันที โดยไม่ auto submit)

ระบบจะตรวจลำดับความสัมพันธ์ของข้อมูลให้เสมอ เพื่อป้องกันการโยงข้อมูลข้ามลำดับผิด

## 10) ขั้นตอนการ Deactivate ปลั๊กอิน
เมื่อกด Deactivate ในหน้า Plugins ระบบจะแสดงคำถามว่า:
- ต้องการลบข้อมูลทั้งหมดด้วยหรือไม่

การทำงาน:
- ถ้ากด `ตกลง` จะ deactivate และลบข้อมูลทั้งหมดของปลั๊กอิน (รวมตาราง/option)
- ถ้ากด `ยกเลิก` จะ deactivate แต่เก็บข้อมูลไว้

## 11) ตัวอย่างการใช้งานเพิ่มเติม
ดูโฟลเดอร์ `examples/` สำหรับตัวอย่างพร้อมใช้:
- หลายชุดในหน้าเดียว
- ตั้งค่า default value
- เพิ่มชุดฟอร์มผ่าน AJAX/append (jQuery CDN) แล้วเรียก `wpataInit()` ซ้ำ

## 12) Helper: รับ ID แล้วคืนชื่อ
ปลั๊กอินมี helper function สำหรับแปลง `id -> ชื่อ` ดังนี้:

- `wpata_get_province_name_by_id($pv_id, $lang = 'th')`
- `wpata_get_district_name_by_id($dt_id, $lang = 'th')`
- `wpata_get_subdistrict_name_by_id($sdt_id, $lang = 'th')`
- `wpata_get_postalcode_name_by_id($ptc_id)`

ตัวอย่าง:

```php
$province_name_th = wpata_get_province_name_by_id(1, 'th');
$district_name_en = wpata_get_district_name_by_id(1, 'en');
$subdistrict_name_th = wpata_get_subdistrict_name_by_id(1, 'th');
$postal_code = wpata_get_postalcode_name_by_id(1);
```

หมายเหตุ:
- ถ้าไม่พบข้อมูล หรือส่ง `id` ไม่ถูกต้อง ฟังก์ชันจะคืนค่าว่าง `''`
- พารามิเตอร์ `$lang` รองรับ `th` และ `en`

## 13) อัปเดตปลั๊กอินผ่านหน้า Plugins (GitHub Tag/Release)
ปลั๊กอินนี้รองรับการเช็กอัปเดตจาก GitHub โดยใช้ `YahnisElsts/plugin-update-checker`
และแสดงปุ่มอัปเดตในหน้า `Plugins` ของ WordPress ได้ทันที

### 13.1 เตรียมไลบรารี `plugin-update-checker`
วิธีที่แนะนำ (Composer):

```bash
composer install --no-dev
```

โดยในโปรเจกต์มี `composer.json` ให้แล้ว และจะโหลดไฟล์จาก `vendor/autoload.php` อัตโนมัติ

ทางเลือก (ไม่ใช้ Composer):
- คัดลอกโฟลเดอร์ไลบรารีมาไว้ที่:
  - `plugin-update-checker/plugin-update-checker.php` หรือ
  - `lib/plugin-update-checker/plugin-update-checker.php`

### 13.2 ตัวแปรเสริมที่ปรับได้
ตั้งค่าใน `wp-config.php` ได้ (ไม่บังคับ):

```php
define('WPATA_GITHUB_BRANCH', 'main'); // ค่าเริ่มต้นคือ main
define('WPATA_GITHUB_TOKEN', 'ghp_xxx'); // ใส่เมื่อเป็น private repo
```

### 13.3 ขั้นตอนปล่อยเวอร์ชันใหม่ด้วย Tag/Release
1. แก้เวอร์ชันในไฟล์ปลั๊กอินให้ตรงกันทั้ง 2 จุด:
`Version:` และ `WPATA_VERSION`
2. Commit โค้ด แล้วสร้าง tag ใหม่ (เช่น `1.0.3` หรือ `v1.0.3`)
3. สร้าง GitHub Release จาก tag นั้น
4. แนบไฟล์ zip ของปลั๊กอินใน Release (ควรมีโฟลเดอร์ `vendor` หรือไลบรารีที่จำเป็นครบ)
5. ไปที่ WordPress Admin > `Dashboard > Updates` หรือหน้า `Plugins` แล้วเช็กอัปเดต
6. เมื่อพบเวอร์ชันใหม่ จะกด `Update now` ได้จากหน้า Plugins ทันที

### 13.4 หมายเหตุสำคัญ
- เวอร์ชันใน tag/release ต้องมากกว่าเวอร์ชันที่ติดตั้งอยู่
- ถ้าเป็น private repo ต้องกำหนด `WPATA_GITHUB_TOKEN` ให้ WordPress เข้าถึงข้อมูล release ได้
- ถ้าไม่พบไลบรารี `plugin-update-checker` ระบบจะข้ามการเช็กอัปเดต (ไม่กระทบการทำงานส่วนอื่นของปลั๊กอิน)
