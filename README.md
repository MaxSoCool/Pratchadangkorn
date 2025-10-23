# KU-FTD | ระบบขอใช้อุปกรณ์และสถานที่ 🏛️✨

สวัสดีครับ ผมนายปรัชชฎางค์กรณ์ แก้วมณีโชติ ผู้พัฒนา **ระบบขอใช้อุปกรณ์และสถานที่ | KU-FTD** ซึ่งเป็นโปรเจคจบการศึกษาของหลักสูตรวิทยาศาสตรบัณฑิต สาขาวิทยาการคอมพิวเตอร์ ภาควิชาวิทนาการคอมพิวเตอร์และสารสนเทศ คณะวิทยาศาสตร์และวิศวกรรมศาสตร์

## 🎯 เกี่ยวกับโปรเจกต์

ระบบนี้เกิดขึ้นจากความต้องการแก้ไขปัญหาความล่าช้า ⏳ ในการดำเนินเอกสารต่าง ๆ 📄 เพื่อขอใช้สถานที่และอุปกรณ์ในการจัดกิจกรรมภายในมหาวิทยาลัย

เป้าหมายคือการยกระดับการดำเนินการผ่านเอกสารแบบเดิม ๆ มาสู่การดำเนินการบนเว็บแอปพลิเคชัน 💻 ที่มีความง่าย สะดวก และรวดเร็วต่อการใช้งานมากยิ่งขึ้น 🚀

## 🛠️ เทคโนโลยีที่ใช้ (Tech Stack)

* 🐘 **Backend:** PHP
* 📄 **Frontend:** Bootstrap 5, HTML, JavaScript (JS)
* 🔑 **Authentication:** LDAP API (KUCSC-API) สำหรับบัญชี Nontri
* 🖥️ **Server Environment:** XAMPP (Apache, MySQL)

## ⚠️ คำเตือน (Warning)

> **ข้อควรทราบ:** ระบบนี้ถูกพัฒนาขึ้นสำหรับผู้ใช้ภายใน **มหาวิทยาลัยเกษตรศาสตร์ วิทยาเขตเฉลิมพระเกียรติ จังหวัดสกลนคร** เท่านั้น! 📍
>
> เนื่องจากระบบต้องใช้ `KUCSC-API Key` ซึ่งจะมีเพียงนิสิตหรือบุคลากรภายในเท่านั้นจึงจะสามารถสร้างและใช้งานได้

---

## 📚 สารบัญ (Table of Contents)

- [ขั้นตอนการติดตั้ง](#-ขั้นตอนการติดตั้ง-installation)
- [วิธีการใช้งาน](#-วิธีการใช้งาน-usage)
- [คลิปวิดีโอแนะนำระบบ](#-คลิปวิดีโอแนะนำระบบ-demo-video)

---

## 🚀 ขั้นตอนการติดตั้ง (Installation)

1.  **โคลนโปรเจค** 📂
    ```bash
    git clone [https://github.com/MaxSoCool/Pratchadangkorn-Kaewmaneechot.git](https://github.com/MaxSoCool/Pratchadangkorn-Kaewmaneechot.git)
    cd Pratchadangkorn-Kaewmaneechot
    ```

2.  **ติดตั้ง Composer** 📦
    ```bash
    composer install
    ```
    *หากยังไม่เคยติดตั้ง สามารถดาวน์โหลดได้ที่: [getcomposer.org](https://getcomposer.org/download/)*

3.  **ตั้งค่า Environment File** ⚙️

    คัดลอกไฟล์ `.env.example` ไปเป็นไฟล์ใหม่ชื่อ `.env`
    ```bash
    cp .env.example .env
    ```
    จากนั้น เปิดไฟล์ `.env` ขึ้นมาแก้ไข:
    ```env
    KEY_APP=your_ku_csc_api_key_here
    ```
    *แทนที่ `your_ku_csc_api_key_here` ด้วย Key ที่คุณสร้างจาก [KUCSC API](https://inv.csc.ku.ac.th/cscapi/)*

4.  **Import ฐานข้อมูล** 🗃️

    * เปิด `phpmyadmin`
    * สร้างฐานข้อมูลใหม่
    * Import ไฟล์ `database/ku_ftd_proto.sql` เข้าไปในฐานข้อมูลนั้น

## 💻 วิธีการใช้งาน (Usage)

1.  **เปิด XAMPP Control Panel** (หากไม่มี [ติดตั้ง Xampp ที่นี่](https://www.apachefriends.org/download.html))
2.  กด **Start** ที่ `Apache` และ `MySQL`
3.  เปิดเบราว์เซอร์ของคุณและไปที่:
    [http://localhost/Pratchadangkorn-Kaewmaneechot](http://localhost/Pratchadangkorn-Kaewmaneechot)

## 🎥 คลิปวิดีโอแนะนำระบบ (Demo Video)

[คลิกที่นี่เพื่อรับชมวิดีโอการแนะนำการใช้งานระบบ KU-FTD](https://youtu.be/9kHj2rzRm0w)
