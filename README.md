# footballmanger

پروژه یک بازی آنلاین مدیریت باشگاه فوتبال است.

## اسناد

- تحلیل پروژه و نقشه راه تکمیل: `docs/PROJECT_ANALYSIS_AND_ROADMAP_FA.md`

## تغییر جدید

- سرویس `GameCycleService` برای مدیریت چرخه روزانه بازی (تک‌بازی/دو‌بازی) اضافه شده است.
- تنظیمات فازهای روزانه در `config/config.php` اضافه شده است.
- سرویس `DailySchedulerService` برای اجرای خودکار مسابقات موعدرسیده اضافه شده است.

## اجرای Scheduler (CLI)

برای شبیه‌سازی تمام بازی‌هایی که زمانشان رسیده است:

```bash
php bin/run-daily-scheduler.php
```
