# ตัวอย่างการใช้งาน (`/examples`)

โฟลเดอร์นี้รวมตัวอย่างการใช้งานปลั๊กอินหลายรูปแบบ โดยเน้นกรณีที่ใช้จริงบ่อย:

1. หลายชุดในหน้าเดียว
2. ตั้งค่า `default value`
3. เพิ่มชุดฟอร์มผ่าน AJAX/append และเรียก `wpataInit()` ซ้ำ

## ไฟล์ตัวอย่าง

- `01-basic-multi-set.html`
  ตัวอย่างฟอร์ม 2 ชุดในหน้าเดียว (Shipping/Billing)
- `02-default-value.php`
  ตัวอย่างตั้งค่าเริ่มต้นด้วย `data-wpata-value` จากค่าที่บันทึกไว้
- `03-ajax-append-set-jquery-cdn.html`
  ตัวอย่างโหลดชุดฟอร์มจากไฟล์ JSON ด้วย AJAX (ใช้ jQuery CDN)
  พร้อมเดโมการเรียก `wpataInit()` หลายแบบ (`#id`, `.class`, DOM element, jQuery object)
  และมีเดโมกรณี append ทีละ 1 ชุดแบบไม่ใส่ `wpata-group-*`
- `data/address-sets.json`
  ข้อมูลตัวอย่างสำหรับไฟล์ข้อ 3

## ก่อนทดลอง

1. เปิดใช้งานปลั๊กอิน `WP Autocomplete Thailand Address` แล้ว
2. ถ้าเปิดไฟล์ `.html` แบบ standalone ให้ตรวจ path script ให้ถูก:
   - `<script src="/wp-content/plugins/wp-thailand-address-autocomplete/assets/wpata.js"></script>`
3. `window.wpataConfig.ajaxUrl` ถูกตั้งค่าอัตโนมัติแล้ว
   - ปกติไม่ต้องตั้งค่าเอง
   - ตั้งค่าเองเฉพาะตอนต้องการ override endpoint เช่นเว็บอยู่ subdirectory พิเศษ

## หมายเหตุเรื่องค่าเริ่มต้น (`data-wpata-value`)

- ต้องใส่เป็นค่า `ID` ของแต่ละระดับ ไม่ใช่ชื่อแสดงผล
- ต้องเป็นชุดข้อมูลที่สัมพันธ์กัน เช่น `district_id` ต้องอยู่ใต้ `province_id` ที่เลือก
- ถ้าใส่ค่าไม่ถูกต้อง ระบบจะ fallback เป็นค่าว่างอัตโนมัติ
