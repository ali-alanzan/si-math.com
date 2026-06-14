# Saved Questions for Tutor LMS

**Plugin Name:** Saved Questions for Tutor LMS  
**Version:** 1.0.0  
**Requires at least:** WordPress 5.8  
**Requires PHP:** 7.4  
**Tested up to:** WordPress 6.4  
**License:** GPL v2 or later

## Description

Saved Questions for Tutor LMS allows students to save quiz questions while taking tests and access them later from their profile. The plugin provides a seamless way for students to bookmark important questions for review.

### Features

- ✅ Save questions directly from quiz pages
- ✅ View saved questions in profile tab
- ✅ Remove saved questions
- ✅ Export saved questions as JSON or CSV
- ✅ Mobile-friendly interface
- ✅ REST API endpoints for developers
- ✅ Supports both user meta and custom database table storage
- ✅ Fully translatable (RTL support included)
- ✅ Secure (nonces, capability checks, data sanitization)

## Installation

### Method 1: Manual Installation

1. Download the plugin ZIP file
2. Go to WordPress Admin → Plugins → Add New
3. Click "Upload Plugin"
4. Choose the ZIP file and click "Install Now"
5. Activate the plugin

### Method 2: Via FTP

1. Extract the plugin folder
2. Upload `saved-questions-tutor` folder to `/wp-content/plugins/` directory
3. Go to WordPress Admin → Plugins
4. Find "Saved Questions for Tutor LMS" and click "Activate"

## Requirements

- WordPress 5.8 or higher
- PHP 7.4 or higher
- Tutor LMS plugin (must be installed and activated)

## Usage

### For Students

1. **Saving Questions:**
   - While taking a quiz, you'll see a "Save Question" button next to each question
   - Click the button to save the question
   - The button will change to "Saved" to indicate success

2. **Viewing Saved Questions:**
   - Go to your profile page
   - Click on the "Saved Questions" tab
   - View all your saved questions in chronological order

3. **Managing Saved Questions:**
   - Click "Remove" to delete a saved question
   - Use "Export JSON" or "Export CSV" to download your saved questions

### For Developers

#### Shortcode

Display saved questions anywhere using the shortcode:

```
[saved_questions_tab]
```

#### REST API Endpoints

All endpoints are under the namespace: `wp-json/saved-questions/v1/`

**Save Question:**
```
POST /wp-json/saved-questions/v1/save
Body: {
  "quiz_id": 123,
  "question_id": 456,
  "question_content": "<p>Question text</p>",
  "meta": {},
  "source_url": "https://example.com/quiz/123"
}
Headers: X-WP-Nonce: {nonce}
```

**Get Saved Questions:**
```
GET /wp-json/saved-questions/v1/list?limit=10&offset=0
Headers: X-WP-Nonce: {nonce}
```

**Remove Question:**
```
DELETE /wp-json/saved-questions/v1/remove
Body: {
  "saved_id": "sq_1234567890"
}
Headers: X-WP-Nonce: {nonce}
```

**Export Questions:**
```
GET /wp-json/saved-questions/v1/export?format=json
GET /wp-json/saved-questions/v1/export?format=csv
Headers: X-WP-Nonce: {nonce}
```

#### Hooks and Filters

**Filters:**

```php
// Modify saved question data before saving
apply_filters( 'sqt_before_save_question', $data, $user_id );

// Modify saved questions list before display
apply_filters( 'sqt_saved_questions_list', $questions, $user_id );

// Modify export data
apply_filters( 'sqt_export_data', $data, $format );
```

**Actions:**

```php
// Fired after a question is saved
do_action( 'sqt_question_saved', $saved_item, $user_id );

// Fired after a question is removed
do_action( 'sqt_question_removed', $saved_id, $user_id );
```

#### Storage Options

By default, the plugin uses WordPress user meta. To switch to a custom database table:

```php
// Enable custom table
update_option( 'sqt_use_custom_table', true );

// Migrate existing data
$storage = new SQT_Storage();
$migrated = $storage->migrate_to_custom_table();
```

## Configuration

### Settings

The plugin works out of the box with default settings. Advanced users can configure:

- **Storage Method:** User meta (default) or custom database table
- **Data Deletion:** Choose whether to delete data on uninstall (see uninstall.php)

## Translation

The plugin is fully translatable. Translation files should be placed in:
```
wp-content/plugins/saved-questions-tutor/languages/
```

To translate:
1. Use a translation plugin like Loco Translate
2. Or manually create `.po` and `.mo` files
3. Text domain: `saved-questions-tutor`

### RTL Support

The plugin includes RTL (Right-to-Left) CSS support for Arabic, Hebrew, and other RTL languages.

## Troubleshooting

### Save button not appearing

1. Make sure you're logged in as a student
2. Check that Tutor LMS is active
3. Clear browser cache
4. Check browser console for JavaScript errors

### Questions not saving

1. Check browser console for errors
2. Verify REST API is enabled in WordPress
3. Check user permissions
4. Review server error logs

### Export not working

1. Make sure you have saved questions
2. Check browser download settings
3. Try a different browser

## Security

- All requests use WordPress nonces
- User capability checks on all endpoints
- Data sanitization on input
- Output escaping on display
- SQL injection protection via prepared statements

## Performance

- Uses AJAX for all operations (no page reloads)
- Efficient database queries
- Optional caching via transients
- Minimal JavaScript footprint

## Changelog

### 1.0.0
- Initial release
- Save questions from quizzes
- View saved questions in profile
- Remove questions
- Export as JSON/CSV
- REST API endpoints
- RTL support
- Mobile responsive

## Support

For support, please contact the plugin developer or create an issue in the repository.

## Credits

Developed for Tutor LMS compatibility.

---

## التثبيت والاستخدام (بالعربية)

### التثبيت

1. قم بتحميل ملف ZIP الخاص بالبلجن
2. اذهب إلى لوحة تحكم WordPress → الإضافات → إضافة جديد
3. اضغط "رفع إضافة"
4. اختر ملف ZIP واضغط "تثبيت الآن"
5. فعّل البلجن

### المتطلبات

- WordPress 5.8 أو أحدث
- PHP 7.4 أو أحدث
- إضافة Tutor LMS (يجب تثبيتها وتفعيلها)

### الاستخدام

**للطلاب:**

1. أثناء حل الاختبار، ستظهر أيقونة "حفظ السؤال" بجانب كل سؤال
2. اضغط على الأيقونة لحفظ السؤال
3. لعرض الأسئلة المحفوظة، اذهب إلى بروفايلك واختر تبويب "Saved Questions"
4. يمكنك حذف الأسئلة أو تصديرها كـ JSON أو CSV

**للمطورين:**

استخدم الـ shortcode لعرض الأسئلة المحفوظة:
```
[saved_questions_tab]
```

## الترخيص

هذا البلجن مرخص تحت رخصة GPL v2 أو أحدث.

