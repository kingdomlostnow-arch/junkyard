# forsureitscrazy

موقع يعرض كل يوم **5 قصص حقيقية مجنونة** مع مقال تفصيلي وصورة لكل قصة.
يعمل بـ **PHP + MySQL**، ويستخدم **DeepSeek** للكتابة و**Firecrawl** للبحث في الإنترنت.

## كيف يعمل؟

كل يوم عند الساعة 00:00 تعمل المهمة `cron/generate.php`، وهي:

1. **DeepSeek يختار المواضيع:** يقترح 5 قصص غريبة وحقيقية فقط (تاريخية وحديثة)، ولا يكرر ما نُشر خلال السنة الماضية.
2. **Firecrawl يبحث في الإنترنت:** يجلب محتوى أفضل 4 صفحات عن كل موضوع مع صورها.
3. **DeepSeek يكتب المقال من المصادر فقط:** عنوان وملخص و4–6 أقسام وحقيقة غريبة. ويرفض الموضوع إذا كانت المصادر لا تؤكده.
4. **DeepSeek يختار شكل الصفحة:** واحد من 6 ألوان (neon، blood، ocean، toxic، royal، sand) وواحد من 3 تخطيطات (classic، magazine، timeline).
5. **اختيار الصورة:** تُؤخذ الصورة الرئيسية من صفحات المصادر، وإن لم تكن مناسبة يبحث Firecrawl عن صورة.
6. **الاستبدال:** تُحذف قصص الأمس وصورها وتوضع القصص الخمس الجديدة مكانها في عملية واحدة (transaction).

**إذا فشل جزء من العملية:**
- إذا لم يكتمل عدد القصص من الإنترنت، تُكمَّل من ويكيبيديا («في مثل هذا اليوم»).
- إذا تعذّر الوصول إلى 5 قصص كاملة، تبقى قصص الأمس معروضة ولا يفرغ الموقع.

## التثبيت

المتطلبات: PHP 8.1 أو أحدث (مع إضافات `curl` و`pdo_mysql` و`mbstring` و`gd`)، وMySQL أو MariaDB.

```bash
# 1) قاعدة البيانات
mysql -u root -p < schema.sql
mysql -u root -p -e "CREATE USER 'forsureitscrazy'@'localhost' IDENTIFIED BY 'كلمة_سر_قوية';
  GRANT ALL ON forsureitscrazy.* TO 'forsureitscrazy'@'localhost';"

# 2) الإعدادات
cp config.example.php config.php
#   ثم عدّل في config.php: كلمة سر قاعدة البيانات، ومفتاحَي DeepSeek وFirecrawl، والمنطقة الزمنية،
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
src/                 Http / Firecrawl / DeepSeek / Wikipedia
public/              ملفات الموقع (index.php, event.php, assets/, images/)
storage/             السجل وملف القفل
```

## تخصيص

- `events_per_day`: عدد القصص اليومية.
- `topic_mode`: `mixed` (تاريخية وحديثة)، أو `history`، أو `modern`.
- يمكن وضع المفتاحين في متغيرَي البيئة `DEEPSEEK_API_KEY` و`FIRECRAWL_API_KEY` بدل `config.php`.
- `output_language`: لغة المقالات (مثلاً `English`).
- `deepseek.model`: `deepseek-chat` (افتراضي) أو `deepseek-reasoner`.
