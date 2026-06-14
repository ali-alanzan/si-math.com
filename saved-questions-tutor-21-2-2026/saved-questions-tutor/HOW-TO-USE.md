# كيفية استخدام البلجن - Saved Questions for Tutor LMS

## خطوات التثبيت

### الطريقة الأولى: رفع ZIP مباشرة

1. **إنشاء ملف ZIP:**
   - اضغط بزر الماوس الأيمن على مجلد `saved-questions-tutor`
   - اختر "Send to" → "Compressed (zipped) folder" (Windows)
   - أو استخدم الأمر: `zip -r saved-questions-tutor.zip saved-questions-tutor/` (Linux/Mac)

2. **رفع إلى WordPress:**
   - اذهب إلى لوحة تحكم WordPress
   - الإضافات → إضافة جديد
   - اضغط "رفع إضافة"
   - اختر ملف ZIP
   - اضغط "تثبيت الآن"

3. **تفعيل البلجن:**
   - بعد التثبيت، اضغط "تفعيل الإضافة"

### الطريقة الثانية: رفع عبر FTP

1. **استخرج المجلد:**
   - استخرج مجلد `saved-questions-tutor` من ZIP

2. **ارفع المجلد:**
   - استخدم FTP client (مثل FileZilla)
   - ارفع المجلد إلى: `/wp-content/plugins/`

3. **فعّل البلجن:**
   - اذهب إلى الإضافات في WordPress
   - ابحث عن "Saved Questions for Tutor LMS"
   - اضغط "تفعيل"

## التحقق من التثبيت

1. **تأكد من وجود Tutor LMS:**
   - يجب أن تكون إضافة Tutor LMS مثبتة ومفعّلة
   - إذا لم تكن موجودة، سيظهر تنبيه في لوحة التحكم

2. **اختبر الوظيفة:**
   - اذهب إلى أي صفحة اختبار (Quiz) في Tutor LMS
   - يجب أن ترى أيقونة "Save Question" بجانب كل سؤال
   - اضغط على الأيقونة لحفظ السؤال

3. **تحقق من الأسئلة المحفوظة:**
   - اذهب إلى بروفايلك (Profile)
   - ابحث عن تبويب "Saved Questions"
   - يجب أن ترى السؤال الذي حفظته

## الاستخدام

### للطلاب:

1. **حفظ سؤال:**
   - أثناء حل الاختبار، اضغط على أيقونة "Save Question" بجانب أي سؤال
   - ستتحول الأيقونة إلى "Saved" عند النجاح

2. **عرض الأسئلة المحفوظة:**
   - اذهب إلى بروفايلك
   - اضغط على تبويب "Saved Questions"
   - ستظهر جميع الأسئلة المحفوظة مرتبة حسب التاريخ

3. **إدارة الأسئلة:**
   - اضغط "Remove" لحذف سؤال محفوظ
   - استخدم "Export JSON" أو "Export CSV" لتنزيل الأسئلة

### للمطورين:

#### استخدام Shortcode:

```
[saved_questions_tab]
```

ضع هذا الكود في أي صفحة لعرض الأسئلة المحفوظة.

#### استخدام REST API:

**حفظ سؤال:**
```javascript
fetch('/wp-json/saved-questions/v1/save', {
    method: 'POST',
    headers: {
        'Content-Type': 'application/json',
        'X-WP-Nonce': nonce
    },
    body: JSON.stringify({
        quiz_id: 123,
        question_id: 456,
        question_content: '<p>نص السؤال</p>',
        source_url: window.location.href
    })
});
```

**جلب الأسئلة:**
```javascript
fetch('/wp-json/saved-questions/v1/list?nonce=' + nonce)
    .then(response => response.json())
    .then(data => console.log(data));
```

## الإعدادات المتقدمة

### التبديل إلى Custom Database Table:

إذا كنت تريد استخدام جدول قاعدة بيانات مخصص (للمواقع الكبيرة):

```php
// في functions.php أو في plugin
add_action('init', function() {
    // تفعيل الجدول المخصص
    update_option('sqt_use_custom_table', true);
    
    // إنشاء الجدول
    $storage = new SQT_Storage();
    $storage->create_custom_table();
    
    // ترحيل البيانات الموجودة (اختياري)
    $storage->migrate_to_custom_table();
});
```

### حذف البيانات عند إلغاء التثبيت:

افتراضيًا، البيانات لا تُحذف عند إلغاء التثبيت. للحذف:

```php
// في wp-config.php
define('SQT_DELETE_DATA_ON_UNINSTALL', true);
```

ثم قم بتعديل `uninstall.php` لقراءة هذا الثابت.

## استكشاف الأخطاء

### الأيقونة لا تظهر:
- تأكد من تسجيل الدخول
- تأكد من أنك على صفحة اختبار
- امسح ذاكرة التخزين المؤقت للمتصفح
- تحقق من وحدة تحكم المتصفح للأخطاء

### السؤال لا يُحفظ:
- تحقق من وحدة تحكم المتصفح
- تأكد من تفعيل REST API
- تحقق من صلاحيات المستخدم
- راجع سجلات الأخطاء في الخادم

### التصدير لا يعمل:
- تأكد من وجود أسئلة محفوظة
- تحقق من إعدادات التنزيل في المتصفح
- جرب متصفحًا آخر

## الدعم

للمساعدة أو الإبلاغ عن مشاكل، يرجى التواصل مع المطور.

---

## How to Use (English)

### Installation Steps

1. **Create ZIP file:**
   - Right-click on `saved-questions-tutor` folder
   - Select "Send to" → "Compressed (zipped) folder"
   - Or use: `zip -r saved-questions-tutor.zip saved-questions-tutor/`

2. **Upload to WordPress:**
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin"
   - Select the ZIP file
   - Click "Install Now"

3. **Activate:**
   - After installation, click "Activate Plugin"

### Testing

1. Go to any Tutor LMS quiz page
2. You should see "Save Question" buttons
3. Click to save a question
4. Go to Profile → "Saved Questions" tab to verify

### For Developers

Use the shortcode: `[saved_questions_tab]`

Or use REST API endpoints documented in README.md

