# รัน Frontend เดิมบนเครื่องนี้

Stack ใน `docker-compose.yml` ใช้สำหรับพัฒนาบน Mac เครื่องนี้ โค้ด PHP ถูก mount จากโฟลเดอร์ `frontend` จึงแก้ไฟล์แล้วรีเฟรชเบราว์เซอร์ได้เลย

## บริการ

| บริการ | ภาพ | พอร์ตบนเครื่องนี้ |
|---|---|---|
| TimescaleDB 2.13.1 / PostgreSQL 14 | `timescale/timescaledb:2.13.1-pg14` | `5432` |
| PHP-FPM 8.3 | `jconnect-php:8.3` สร้างจาก `frontend/Dockerfile` | ไม่เปิดออกนอก Docker |
| Nginx 1.27 | `nginx:1.27-alpine` | `80` |
| pgAdmin | `dpage/pgadmin4` | `5051` |

PHP เชื่อมฐานข้อมูลผ่านตัวแปร `DB_HOST=timescaledb` ซึ่ง `frontend/application/config/database.php` อ่านอยู่แล้ว ชื่อผู้ใช้ รหัสผ่าน และชื่อฐานข้อมูลอยู่ใน `docker-compose.yml`

คอนฟิก Nginx ของ stack นี้คือ `nginx/frontend.conf` ส่งไฟล์ `.php` ไปที่คอนเทนเนอร์ `php:9000` ไฟล์ `nginx/default.conf` เป็นคอนฟิกของเครื่องเซิร์ฟเวอร์เดิม ไม่ได้ใช้กับ Docker ชุดนี้

## คำสั่ง

จากรากโปรเจกต์:

```bash
docker compose up -d --build
docker compose ps
docker compose logs -f nginx php
docker compose down
```

เปิด Frontend ที่ [http://127.0.0.1/user/login](http://127.0.0.1/user/login) และ pgAdmin ที่ [http://127.0.0.1:5051](http://127.0.0.1:5051)

## ฐานข้อมูล local

ข้อมูลถูก restore จาก `Database/jconnect_2026-10-05.sql` ลง volume `timescale_data` แล้ว ถ้าลบ volume แล้วต้อง restore ใหม่ ให้รอ TimescaleDB พร้อมก่อน แล้วรันจากรากโปรเจกต์:

```bash
docker exec timescaledb psql -U jconnect -d jconnect -c "SELECT timescaledb_pre_restore();"
docker exec -i timescaledb psql -U jconnect -d jconnect -v ON_ERROR_STOP=1 < Database/jconnect_2026-10-05.sql
docker exec timescaledb psql -U jconnect -d jconnect -c "SELECT timescaledb_post_restore();"
```

ชื่อคอนเทนเนอร์ PHP และ Nginx คือ `jconnectgt7000-php` กับ `jconnectgt7000-nginx` เพื่อไม่ให้ชนกับคอนเทนเนอร์ชื่อ `jconnect-php` ที่มีอยู่แล้วบนเครื่องนี้
