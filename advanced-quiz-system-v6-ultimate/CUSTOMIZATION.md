# دليل التخصيص - CUSTOMIZATION GUIDE

## 🎨 تخصيص الألوان

### تغيير لون Leaderboard (الحالي: #0B417C)

#### الطريقة 1: من ملف CSS
```css
/* في: assets/css/enhanced-style.css */

/* تغيير لون Header */
.aqs-leaderboard-header {
    background: linear-gradient(135deg, #YOUR_COLOR 0%, #YOUR_COLOR2 100%);
}

/* تغيير لون الأيقونات */
.aqs-leaderboard-rank {
    color: #YOUR_COLOR;
}

/* تغيير لون الدرجات */
.aqs-leaderboard-score {
    color: #YOUR_COLOR;
}

/* تغيير لون Avatar Border */
.aqs-leaderboard-avatar {
    border-color: #YOUR_COLOR;
}

/* تغيير لون Hover */
.aqs-leaderboard-item:hover {
    border-right-color: #YOUR_COLOR;
}
```

#### الطريقة 2: من Customizer
```
1. اذهب إلى Appearance > Customize
2. Additional CSS
3. أضف الكود التالي:

.aqs-leaderboard-header {
    background: linear-gradient(135deg, #FF5722 0%, #F44336 100%) !important;
}

.aqs-leaderboard-rank,
.aqs-leaderboard-score {
    color: #FF5722 !important;
}
```

### تغيير لون الشات

```css
/* Chat Header */
.aqs-chat-header {
    background: linear-gradient(135deg, #YOUR_COLOR 0%, #YOUR_COLOR2 100%);
}

/* Send Button */
.aqs-chat-send-btn {
    background: #YOUR_COLOR;
}

/* Own Message */
.aqs-chat-message.own .aqs-chat-message-text {
    background: #YOUR_COLOR;
}
```

### تغيير لون Standalone Quiz

```css
/* Quiz Header */
.aqs-quiz-intro-title {
    color: #YOUR_COLOR;
}

/* Timer */
.aqs-quiz-timer {
    color: #YOUR_COLOR;
}

/* Progress Bar */
.aqs-quiz-progress-fill {
    background: linear-gradient(90deg, #YOUR_COLOR 0%, #YOUR_COLOR2 100%);
}

/* Buttons */
.aqs-quiz-btn-primary {
    background: #YOUR_COLOR;
}

.aqs-quiz-btn-primary:hover {
    background: #YOUR_DARKER_COLOR;
}

/* Current Question in Navigator */
.aqs-quiz-nav-btn.current {
    background: #YOUR_COLOR;
}
```

### تغيير لون Score Predictor

```css
/* Header */
.aqs-predictor-header {
    background: linear-gradient(135deg, #YOUR_COLOR 0%, #YOUR_COLOR2 100%);
}

/* Prediction Score */
.aqs-prediction-value {
    color: #YOUR_COLOR;
}

/* Stats */
.aqs-stat-value {
    color: #YOUR_COLOR;
}
```

---

## 📐 تخصيص الأحجام

### تكبير الشات

```css
.aqs-chat-widget {
    width: 450px !important;  /* الافتراضي: 380px */
    max-height: 700px !important;  /* الافتراضي: 600px */
}
```

### تكبير Leaderboard

```css
.aqs-leaderboard {
    padding: 35px !important;  /* الافتراضي: 25px */
}

.aqs-leaderboard-title {
    font-size: 32px !important;  /* الافتراضي: 24px */
}

.aqs-leaderboard-avatar {
    width: 60px !important;  /* الافتراضي: 50px */
    height: 60px !important;
}
```

### تكبير خط الأسئلة

```css
.aqs-question-text {
    font-size: 24px !important;  /* الافتراضي: 20px */
}

.aqs-option-text {
    font-size: 18px !important;  /* الافتراضي: 16px */
}
```

---

## 🎯 تخصيص المواقع

### نقل الشات لأعلى

```css
.aqs-chat-widget {
    bottom: 100px !important;  /* الافتراضي: 20px */
}
```

### نقل الشات لليمين بدل اليسار

```css
.aqs-chat-widget {
    left: auto !important;
    right: 20px !important;
}
```

### مركز Leaderboard

```css
.aqs-leaderboard {
    margin: 30px auto !important;
    max-width: 900px;
}
```

---

## 🌓 الوضع الليلي (Dark Mode)

### تفعيل Dark Mode للنظام بالكامل

```css
/* Dark Mode للـ Leaderboard */
body.dark-mode .aqs-leaderboard {
    background: #1e1e1e;
    color: #ffffff;
}

body.dark-mode .aqs-leaderboard-item {
    background: #2d2d2d;
}

/* Dark Mode للـ Chat */
body.dark-mode .aqs-chat-widget {
    background: #1e1e1e;
}

body.dark-mode .aqs-chat-message-text {
    background: #2d2d2d;
    color: #ffffff;
}

/* Dark Mode للـ Quiz */
body.dark-mode .aqs-quiz-questions-container {
    background: #1e1e1e;
}

body.dark-mode .aqs-question-option {
    background: #2d2d2d;
    color: #ffffff;
}
```

---

## 🖼️ تخصيص الأيقونات

### استبدال أيقونة الشات

```css
.aqs-chat-title::before {
    content: "🎓" !important;  /* بدل 💬 */
}
```

### استبدال أيقونة Leaderboard

```css
.aqs-leaderboard-title::before {
    content: "👑" !important;  /* بدل 🏆 */
}
```

---

## 📱 تخصيص للموبايل

### تحسين الشات على الموبايل

```css
@media (max-width: 768px) {
    .aqs-chat-widget {
        width: calc(100% - 20px) !important;
        bottom: 10px !important;
        left: 10px !important;
        max-height: 500px !important;
    }
}
```

### تحسين Quiz Navigator للموبايل

```css
@media (max-width: 768px) {
    .aqs-quiz-nav-grid {
        grid-template-columns: repeat(4, 1fr) !important;
    }
}
```

---

## 🔤 تخصيص الخطوط

### تغيير خط النظام

```css
.aqs-leaderboard,
.aqs-chat-widget,
.aqs-standalone-quiz,
.aqs-mistakes-container,
.aqs-predictor-container {
    font-family: 'Cairo', 'Tajawal', sans-serif !important;
}
```

### تحميل خط من Google Fonts

```php
// في functions.php
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style('google-font-cairo', 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap');
});
```

---

## 🎭 تخصيص الأنيميشن

### إضافة أنيميشن للرسائل

```css
@keyframes slideIn {
    from {
        opacity: 0;
        transform: translateX(20px);
    }
    to {
        opacity: 1;
        transform: translateX(0);
    }
}

.aqs-chat-message {
    animation: slideIn 0.3s ease;
}
```

### إضافة أنيميشن للـ Leaderboard Items

```css
@keyframes fadeIn {
    from {
        opacity: 0;
        transform: translateY(10px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.aqs-leaderboard-item {
    animation: fadeIn 0.4s ease;
    animation-fill-mode: both;
}

.aqs-leaderboard-item:nth-child(1) { animation-delay: 0.1s; }
.aqs-leaderboard-item:nth-child(2) { animation-delay: 0.2s; }
.aqs-leaderboard-item:nth-child(3) { animation-delay: 0.3s; }
```

---

## 🎨 مجموعات ألوان جاهزة

### مجموعة 1: الأزرق الملكي (الحالي)
```css
--primary: #0B417C;
--secondary: #1565C0;
--accent: #2196F3;
```

### مجموعة 2: الأخضر
```css
--primary: #2E7D32;
--secondary: #388E3C;
--accent: #4CAF50;
```

### مجموعة 3: البرتقالي
```css
--primary: #E65100;
--secondary: #F57C00;
--accent: #FF9800;
```

### مجموعة 4: الأحمر
```css
--primary: #C62828;
--secondary: #D32F2F;
--accent: #F44336;
```

### مجموعة 5: البنفسجي
```css
--primary: #6A1B9A;
--secondary: #7B1FA2;
--accent: #9C27B0;
```

### كيفية التطبيق
```css
/* في Additional CSS */
:root {
    --aqs-primary: #2E7D32;
    --aqs-secondary: #388E3C;
}

.aqs-leaderboard-header,
.aqs-chat-header,
.aqs-predictor-header {
    background: linear-gradient(135deg, var(--aqs-primary) 0%, var(--aqs-secondary) 100%) !important;
}

.aqs-leaderboard-rank,
.aqs-leaderboard-score,
.aqs-quiz-timer,
.aqs-prediction-value {
    color: var(--aqs-primary) !important;
}
```

---

## 📝 تخصيص النصوص

### تغيير النصوص بدون تعديل الكود

```php
// في functions.php
add_filter('gettext', function($translation, $text, $domain) {
    if ($domain === 'advanced-quiz-system') {
        // تغيير "دردشة الطلاب" إلى "المحادثة"
        if ($text === 'دردشة الطلاب') {
            return 'المحادثة';
        }
        
        // تغيير "لوحة المتصدرين" إلى "قائمة الشرف"
        if ($text === 'لوحة المتصدرين') {
            return 'قائمة الشرف';
        }
    }
    return $translation;
}, 10, 3);
```

---

## 💡 نصائح التخصيص

1. **استخدم !important بحذر:** فقط عند الضرورة
2. **اختبر على أجهزة متعددة:** Desktop, Mobile, Tablet
3. **احفظ نسخة من التخصيصات:** في ملف منفصل
4. **استخدم Child Theme:** لتجنب فقدان التخصيصات عند التحديث
5. **راجع الأداء:** التخصيصات الكثيرة قد تبطئ الموقع

---

## 🔄 إنشاء Theme مخصص

### في functions.php للـ Child Theme:
```php
// تحميل CSS مخصص
add_action('wp_enqueue_scripts', function() {
    wp_enqueue_style(
        'aqs-custom', 
        get_stylesheet_directory_uri() . '/aqs-custom.css',
        array('aqs-style'),
        '1.0.0'
    );
}, 99);
```

### في aqs-custom.css:
```css
/* جميع تخصيصاتك هنا */
.aqs-leaderboard {
    /* تخصيصات Leaderboard */
}

.aqs-chat-widget {
    /* تخصيصات Chat */
}

.aqs-standalone-quiz {
    /* تخصيصات Quiz */
}
```

---

**استمتع بالتخصيص! 🎨**
