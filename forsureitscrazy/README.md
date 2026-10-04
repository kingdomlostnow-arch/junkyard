# forsureitscrazy

موقع يعرض كل يوم **5 أحداث حقيقية مجنونة من التاريخ** مع مقال تفصيلي وصورة لكل حدث.
يعمل بـ **PHP + MySQL**، ويكتب المقالات بالذكاء الاصطناعي **DeepSeek**.

## كيف يعمل؟

كل يوم عند الساعة 00:00 تعمل المهمة `cron/generate.php`، وهي:

1. تجلب أحداث «في مثل هذا اليوم» من ويكيبيديا، ثم تختار منها 5 أحداث عشوائياً.
2. تقرأ نص مقال ويكيبيديا الخاص بكل حدث، وتنزّل صورته إلى `public/images/events/`.
3. ترسل النص إلى DeepSeek، فيكتب مقالاً عربياً مشوّقاً: عنواناً وملخصاً و5–7 فقرات وحقيقة غريبة وتصنيفاً، معتمداً على الحقائق الموجودة في المصدر فقط.
4. تحذف قصص الأمس وصورها وتضع القصص الجديدة مكانها في عملية واحدة (transaction).

إذا فشل جلب البيانات أو تعذّر توليد القصص الخمس كاملة، **تبقى قصص الأمس معروضة** ولا يفرغ الموقع.

> واجهة DeepSeek لا تتصفح الإنترنت بنفسها، لذلك يتولى الموقع جلب الأحداث والصور من ويكيبيديا ثم يمرّرها للذكاء الاصطناعي ليكتب المقالات.

## التثبيت

المتطلبات: PHP 8.1 أو أحدث (مع إضافات `curl` و`pdo_mysql` و`mbstring` و`gd`)، وMySQL أو MariaDB.

```bash
# 1) قاعدة البيانات
mysql -u root -p < schema.sql
mysql -u root -p -e "CREATE USER 'forsureitscrazy'@'localhost' IDENTIFIED BY 'كلمة_سر_قوية';
  GRANT ALL ON forsureitscrazy.* TO 'forsureitscrazy'@'localhost';"

# 2) الإعدادات
cp config.example.php config.php
#   ثم عدّل في config.php: كلمة سر قاعدة البيانات، ومفتاح DeepSeek، والمنطقة الزمنية،
#   وأضف بريدك في user_agent (شرط من ويكيبيديا)

# 3) صلاحيات الكتابة
chmod 775 storage public/images/events

# 4) أول تشغيل يدوي
php cron/generate.php
```

اجعل **جذر الموقع (Document Root)** يشير إلى مجلد `public/` فقط، حتى لا يكون `config.php` مكشوفاً للزوار.

## الجدولة اليومية (Cron)

```bash
crontab -e
```

```
0 0 * * * php /path/to/forsureitscrazy/cron/generate.php >> /path/to/forsureitscrazy/storage/cron.log 2>&1
```

يُحسب وقت الـ cron بتوقيت الخادم. إذا كان توقيت الخادم مختلفاً عن منطقتك، اكتب في أول ملف crontab: `CRON_TZ=Asia/Riyadh`، أو اضبط الساعة في السطر بما يقابل منتصف الليل عندك.

في الاستضافات المشتركة (cPanel)، أضف الأمر نفسه من قسم **Cron Jobs**.

## هيكل الملفات

```
config.example.php   الإعدادات (انسخه إلى config.php)
schema.sql           جدول قاعدة البيانات
cron/generate.php    مهمة التوليد اليومية
src/                 Http / Wikipedia / DeepSeek
public/              ملفات الموقع (index.php, event.php, assets/, images/)
storage/             السجل وملف القفل
```

## تخصيص

- `events_per_day`: عدد القصص اليومية.
- `output_language`: لغة المقالات (مثلاً `English`).
- `deepseek.model`: `deepseek-chat` (افتراضي) أو `deepseek-reasoner`.
