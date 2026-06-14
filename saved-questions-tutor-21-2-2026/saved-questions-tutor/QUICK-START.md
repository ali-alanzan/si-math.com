# Quick Start Guide - Saved Questions for Tutor LMS

## 📦 إنشاء ملف ZIP للتثبيت

### Windows:
1. اضغط بزر الماوس الأيمن على مجلد `saved-questions-tutor`
2. اختر "Send to" → "Compressed (zipped) folder"
3. سيتم إنشاء `saved-questions-tutor.zip`

### Linux/Mac:
```bash
zip -r saved-questions-tutor.zip saved-questions-tutor/
```

### PowerShell:
```powershell
Compress-Archive -Path saved-questions-tutor -DestinationPath saved-questions-tutor.zip
```

## 🚀 التثبيت السريع

1. **ارفع البلجن:**
   - WordPress Admin → Plugins → Add New → Upload Plugin
   - اختر `saved-questions-tutor.zip`
   - اضغط "Install Now"

2. **فعّل البلجن:**
   - بعد التثبيت، اضغط "Activate Plugin"

3. **اختبر:**
   - اذهب إلى أي صفحة اختبار في Tutor LMS
   - يجب أن ترى أيقونة "Save Question" بجانب كل سؤال
   - اضغط لحفظ السؤال
   - اذهب إلى Profile → "Saved Questions" للتحقق

## ✅ التحقق من التثبيت

- ✅ Tutor LMS مثبت ومفعّل
- ✅ البلجن مفعّل
- ✅ الأيقونات تظهر في صفحات الاختبارات
- ✅ الأسئلة تُحفظ بنجاح
- ✅ تبويب "Saved Questions" يظهر في البروفايل

## 📝 الملفات المهمة

- `saved-questions-tutor.php` - الملف الرئيسي
- `includes/` - Classes (Storage, REST API, Frontend)
- `assets/js/frontend.js` - JavaScript
- `assets/css/frontend.css` - Styles
- `README.md` - التوثيق الكامل

## 🆘 مشاكل شائعة

**الأيقونة لا تظهر:**
- تأكد من تسجيل الدخول
- امسح cache المتصفح
- تحقق من console المتصفح

**السؤال لا يُحفظ:**
- تحقق من REST API (Settings → Permalinks → Save)
- راجع console للأخطاء

## 📞 الدعم

راجع `README.md` و `HOW-TO-USE.md` للمزيد من التفاصيل.

