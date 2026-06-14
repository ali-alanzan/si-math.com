# ميزة Dashboard - Saved Questions

## الوصف
تم إضافة صفحة في Dashboard لعرض الأسئلة المحفوظة بنفس شكل الـ quiz (السؤال + الإجابات فقط).

## الميزات

### 1. زر في Sidebar
- يظهر زر "Saved Questions" في sidebar الـ Dashboard
- عند الضغط عليه، يفتح صفحة الأسئلة المحفوظة

### 2. عرض الأسئلة
- كل سؤال يظهر بنفس الشكل الذي كان عليه في الـ quiz
- السؤال + الإجابات فقط (بدون معلومات إضافية)
- الإجابة الصحيحة محددة بوضوح
- تصميم مشابه لصفحة الـ quiz

### 3. الإجراءات
- زر "View in Quiz" للعودة للاختبار الأصلي
- زر "Remove" لحذف السؤال من المحفوظات
- أزرار Export (JSON/CSV)

## كيفية الاستخدام

1. **حفظ سؤال:**
   - أثناء حل الاختبار، اضغط على "Save Question" بجانب السؤال

2. **عرض الأسئلة المحفوظة:**
   - اذهب إلى Dashboard
   - اضغط على "Saved Questions" في الـ sidebar
   - ستظهر جميع الأسئلة المحفوظة

3. **إدارة الأسئلة:**
   - اضغط "Remove" لحذف سؤال
   - اضغط "View in Quiz" للعودة للاختبار

## التصميم

- الأسئلة تظهر بنفس شكل الـ quiz
- الإجابات تظهر كـ radio buttons أو checkboxes (حسب نوع السؤال)
- الإجابة الصحيحة محددة بخلفية خضراء وعلامة "Correct"
- تصميم متجاوب (mobile-friendly)

## الملفات المحدثة

- `includes/class-sqt-frontend.php` - إضافة dashboard page
- `assets/css/frontend.css` - إضافة styles للـ dashboard
- `assets/js/frontend.js` - إضافة event handlers

## ملاحظات

- الصفحة تعمل داخل Tutor Dashboard
- لا تحتاج إلى صفحة منفصلة
- التصميم متوافق مع Tutor LMS theme

---

## Dashboard Feature - English

### Description
Added a dashboard page to display saved questions in the same format as the quiz (question + answers only).

### Features

1. **Sidebar Button**
   - "Saved Questions" button appears in Dashboard sidebar
   - Clicking opens the saved questions page

2. **Question Display**
   - Each question appears in the same format as in the quiz
   - Question + answers only (no extra information)
   - Correct answer is clearly marked
   - Design similar to quiz page

3. **Actions**
   - "View in Quiz" button to return to original quiz
   - "Remove" button to delete from saved
   - Export buttons (JSON/CSV)

### Usage

1. Save a question from quiz
2. Go to Dashboard → Click "Saved Questions"
3. View all saved questions
4. Manage questions (remove, view original)

### Design

- Questions appear in same format as quiz
- Answers shown as radio/checkbox buttons
- Correct answer highlighted in green with "Correct" badge
- Mobile responsive design

