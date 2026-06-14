# دليل التثبيت والتشغيل السريع - Quick Start Guide

## 🚀 التثبيت في 5 خطوات

### الخطوة 1: رفع الملفات
```bash
# عبر FTP أو cPanel File Manager
1. افتح /wp-content/plugins/
2. ارفع مجلد advanced-quiz-system-v5
```

### الخطوة 2: التفعيل
```
1. اذهب إلى WordPress Admin > Plugins
2. ابحث عن "Advanced Quiz System Pro V5"
3. اضغط "Activate"
```

### الخطوة 3: التحقق من الجداول
```sql
-- افتح phpMyAdmin وتأكد من وجود:
wp_aqs_leaderboard
wp_aqs_chat_messages
wp_aqs_quiz_attempts
wp_aqs_mistakes
```

### الخطوة 4: الإعدادات الأساسية
```
اذهب إلى: Tutor LMS > AQS Settings

فعّل:
✅ Chat System
✅ Leaderboard  
✅ Mistakes Tracker
✅ Score Predictor
✅ Standalone Quiz

احفظ التغييرات
```

### الخطوة 5: الاختبار
```
1. سجل دخول كطالب
2. افتح أي كورس
3. تأكد من ظهور:
   - أيقونة الشات أسفل اليسار
   - Leaderboard في صفحة الكورس
   - قائمة "الأخطاء" في Dashboard
   - قائمة "توقع الدرجات" في Dashboard
```

---

## 🎯 الميزات الأساسية

### 1. Mistakes Review
**الوصول:** Dashboard > الأخطاء

**الاستخدام:**
- تُجمع تلقائياً بعد كل امتحان
- اضغط على أي خطأ لمراجعة التفسير
- استخدم الفلاتر للبحث حسب الكورس

**Shortcode:**
```php
[aqs_mistakes course_id="123"]
```

### 2. Score Predictor
**الوصول:** Dashboard > توقع الدرجات

**المتطلبات:**
- 3 امتحانات منتهية على الأقل

**الاستخدام:**
- يعرض التوقع تلقائياً
- يحدث مع كل امتحان جديد

**Shortcode:**
```php
[aqs_score_predictor course_id="123"]
```

### 3. Standalone Quiz
**الإنشاء:**
```
1. Tutor LMS > الامتحانات المستقلة > Add New
2. العنوان: "امتحان الرياضيات"
3. الإعدادات:
   - الوقت: 60 دقيقة
   - درجة النجاح: 70%
   - المحاولات: 3
4. إضافة الأسئلة
5. النشر
```

**العرض:**
```php
// في أي صفحة
[aqs_standalone_quiz id="456"]

// أو شارك الرابط:
yourdomain.com/standalone-quiz/quiz-name/
```

### 4. Chat System
**الاستخدام:**
- يظهر تلقائياً للطلاب المسجلين
- اضغط على الأيقونة للفتح/الإغلاق
- يتحدث كل 3 ثوانٍ

**التخصيص:**
```css
/* في theme CSS */
.aqs-chat-widget {
    bottom: 80px; /* تغيير الموقع */
}
```

### 5. Leaderboard
**العرض:**
- تلقائياً في صفحة الكورس
- أو استخدم Shortcode

**Shortcode:**
```php
[aqs_leaderboard course_id="123" period="week"]
<!-- period: all, week, month -->
```

---

## 🔧 حل المشاكل الشائعة

### المشكلة 1: الشات لا يُرسل
**الحل:**
```php
// في functions.php
add_filter('heartbeat_settings', function($settings) {
    $settings['interval'] = 15; // تقليل الفترة
    return $settings;
});
```

### المشكلة 2: Mistakes لا تُحفظ
**الحل:**
```sql
-- تأكد من وجود الجدول
SHOW TABLES LIKE 'wp_aqs_mistakes';

-- إذا لم يكن موجود:
-- أعد تفعيل البلجن
```

### المشكلة 3: Score Predictor يعطي خطأ
**الحل:**
```php
// في wp-config.php
define('WP_DEBUG', true);
define('WP_DEBUG_LOG', true);

// راجع: wp-content/debug.log
```

### المشكلة 4: Standalone Quiz لا يعمل
**الحل:**
```
1. اذهب إلى Settings > Permalinks
2. اضغط "Save Changes" (بدون تغيير)
3. امسح الكاش
```

### المشكلة 5: Leaderboard فارغ
**الحل:**
```
1. تأكد من وجود امتحانات منتهية
2. راجع جدول wp_aqs_leaderboard
3. تأكد من درجات الامتحانات
```

---

## 📊 أكواد SQL مفيدة

### عرض جميع الأخطاء
```sql
SELECT 
    u.display_name,
    m.question_title,
    m.user_answer,
    m.correct_answer,
    m.created_at
FROM wp_aqs_mistakes m
JOIN wp_users u ON m.user_id = u.ID
ORDER BY m.created_at DESC
LIMIT 50;
```

### عرض Leaderboard
```sql
SELECT 
    u.display_name,
    AVG(l.score) as avg_score,
    COUNT(*) as attempts
FROM wp_aqs_leaderboard l
JOIN wp_users u ON l.user_id = u.ID
WHERE l.course_id = 123
GROUP BY l.user_id
ORDER BY avg_score DESC
LIMIT 10;
```

### مسح رسائل الشات القديمة
```sql
DELETE FROM wp_aqs_chat_messages
WHERE created_at < DATE_SUB(NOW(), INTERVAL 30 DAY);
```

---

## 🎨 التخصيص السريع

### تغيير لون Leaderboard
```css
/* في Customizer > Additional CSS */
.aqs-leaderboard-header {
    background: linear-gradient(135deg, #YOUR_COLOR 0%, #YOUR_COLOR2 100%) !important;
}
```

### تغيير حجم الشات
```css
.aqs-chat-widget {
    width: 450px !important;
    max-height: 700px !important;
}
```

### إخفاء ميزة معينة
```php
// في functions.php
add_filter('aqs_show_mistakes', '__return_false');
add_filter('aqs_show_predictor', '__return_false');
```

---

## 📱 اختبار الموبايل

### iOS Safari:
```
1. افتح الموقع في Safari
2. اضغط على أيقونة المشاركة
3. "Add to Home Screen"
4. اختبر جميع الميزات
```

### Android Chrome:
```
1. افتح الموقع في Chrome
2. Menu > "Add to Home Screen"
3. اختبر جميع الميزات
```

---

## 🔐 الأمان

### تحديثات مهمة:
```php
// في wp-config.php
define('DISALLOW_FILE_EDIT', true);
define('WP_AUTO_UPDATE_CORE', true);
```

### Backup منتظم:
```bash
# يومياً
mysqldump -u USER -p DATABASE > backup_$(date +%Y%m%d).sql

# احفظ في مكان آمن
```

---

## 📞 الدعم

### قبل التواصل:
1. ✅ تأكد من آخر إصدار
2. ✅ امسح الكاش
3. ✅ عطّل البلجنز الأخرى للاختبار
4. ✅ راجع debug.log
5. ✅ اعمل Backup

### التواصل:
- 📧 Email: support@concretegroup.eg
- 🌐 Website: https://concretegroup.eg/support
- 📱 WhatsApp: [رقم الدعم]

---

## ✅ Checklist بعد التثبيت

- [ ] تم تفعيل البلجن بنجاح
- [ ] الجداول موجودة في قاعدة البيانات
- [ ] الإعدادات محفوظة
- [ ] الشات يعمل ويُرسل
- [ ] Leaderboard تظهر البيانات
- [ ] Mistakes تُحفظ بعد الامتحان
- [ ] Score Predictor يعطي توقعات
- [ ] Standalone Quiz يعمل
- [ ] الموقع سريع وبدون أخطاء
- [ ] التصميم responsive على الموبايل

---

## 🎓 نصائح للنجاح

1. **ابدأ صغيراً:** فعّل ميزة واحدة أولاً واختبرها
2. **اختبر دائماً:** قبل وبعد كل تحديث
3. **احفظ Backup:** يومياً على الأقل
4. **راقب الأداء:** استخدم Query Monitor
5. **استمع للطلاب:** خذ feedback وحسّن

**Happy Teaching! 🚀**
