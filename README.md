Installation
---------------

- Upload all files into plugins directory
- Activate plugin

How to Use
---------------
- เพิ่ม class *wpata-select-province* ไปยัง select หรือ parent ลำดับใดก็ได้ของ select ที่ต้องการให้ดึงข้อมูลจังหวัด
- เพิ่ม class *wpata-select-district* ไปยัง select หรือ parent ลำดับใดก็ได้ของ select ที่ต้องการให้ดึงข้อมูลเขต/อำเภอ
- เพิ่ม class *wpata-select-subdistrict* ไปยัง select หรือ parent ลำดับใดก็ได้ของ select ที่ต้องการให้ดึงข้อมูลแขวง/ตำบล
- เพิ่ม class *wpata-select-postalcode* ไปยัง select หรือ parent ลำดับใดก็ได้ของ select ที่ต้องการให้ดึงข้อมูลรหัสไปรษณีย์

รองรับหลายภาษา โดยจะลำดับความสำคัญจาก
- ถ้ามีคลาส wpata-lang- อยู่ในหน้าเว็บ จะถูกใช้ง่านก่อน วิธีการตั้งค่าคือ `<div class="wpata-lang-th"></div>` จะถือว่าภาษาคือ "th" หรือ `<div class="wpata-lang-en"></div>` จะถือว่าภาษาคือ "en"
- จะดึง Attribute lang ที่อยู่ใน tag html มาใช้ เช่น `<html lang="th">` จะถือว่าภาษาคือ "th" หรือ `<html lang="en-US">` จะถือว่าภาษาคือ "en"
- default จะเป็น "th"

Good luck!