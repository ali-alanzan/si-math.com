# Plugin Structure - Saved Questions for Tutor LMS

## Directory Structure

```
saved-questions-tutor/
├── saved-questions-tutor.php    # Main plugin file
├── uninstall.php                 # Uninstall script
├── index.php                     # Security file
├── README.md                     # Documentation (EN/AR)
├── INSTALL.md                    # Installation guide
├── HOW-TO-USE.md                # Usage guide (EN/AR)
├── STRUCTURE.md                  # This file
│
├── includes/                     # PHP classes
│   ├── index.php
│   ├── class-sqt-storage.php    # Storage handler (user meta + custom table)
│   ├── class-sqt-rest-api.php   # REST API endpoints
│   └── class-sqt-frontend.php   # Frontend handler
│
└── assets/                        # Frontend assets
    ├── index.php
    ├── js/
    │   ├── index.php
    │   └── frontend.js           # JavaScript for save buttons & UI
    └── css/
        ├── index.php
        └── frontend.css          # Styles for buttons & saved questions list
```

## File Descriptions

### Core Files

- **saved-questions-tutor.php**: Main plugin file, initializes all components
- **uninstall.php**: Handles cleanup when plugin is uninstalled
- **index.php**: Security files to prevent direct access

### Classes (includes/)

1. **class-sqt-storage.php**
   - Handles saving/retrieving saved questions
   - Supports both user meta and custom database table
   - Methods: `save_question()`, `get_saved_questions()`, `remove_question()`, etc.

2. **class-sqt-rest-api.php**
   - Registers REST API endpoints
   - Endpoints: `/save`, `/list`, `/remove`, `/export`
   - Handles authentication and permissions

3. **class-sqt-frontend.php**
   - Enqueues scripts and styles
   - Adds save buttons to quiz questions
   - Adds profile tab for saved questions
   - Registers shortcode `[saved_questions_tab]`

### Assets

- **frontend.js**: JavaScript for injecting save buttons, handling clicks, AJAX requests
- **frontend.css**: Styles for buttons, saved questions list, mobile responsive

## Key Features

### Storage Options
- Default: WordPress user meta (`wp_usermeta` table, `meta_key = 'saved_questions'`)
- Optional: Custom database table (`wp_saved_questions`)
- Migration function available

### REST API Endpoints
- `POST /wp-json/saved-questions/v1/save` - Save a question
- `GET /wp-json/saved-questions/v1/list` - Get saved questions
- `DELETE /wp-json/saved-questions/v1/remove` - Remove a question
- `GET /wp-json/saved-questions/v1/export` - Export as JSON/CSV

### Hooks & Filters

**Actions:**
- `sqt_question_saved` - Fired after question is saved
- `sqt_question_removed` - Fired after question is removed

**Filters:**
- `sqt_before_save_question` - Modify data before saving
- `sqt_saved_questions_list` - Modify list before display
- `sqt_export_data` - Modify export data

### Tutor LMS Integration

The plugin tries multiple methods to inject save buttons:
1. Uses Tutor LMS hooks (if available):
   - `tutor_quiz/question_content`
   - `tutor_single_quiz/question_content`
   - `tutor_quiz_question_content`
2. Falls back to JavaScript DOM injection if hooks not available

## Data Structure

### Saved Question Item

```php
array(
    'saved_id'         => 'sq_1234567890',  // Unique ID
    'user_id'          => 123,               // User ID
    'quiz_id'          => 456,               // Quiz ID
    'question_id'      => 789,               // Question ID (optional)
    'question_content' => '<p>Question...</p>', // HTML content
    'meta'             => array(             // Optional metadata
        'type'    => 'single_choice',
        'choices' => array('A', 'B', 'C'),
        'correct' => 0
    ),
    'source_url'       => 'https://...',    // Original quiz URL
    'saved_at'         => '2025-12-06 12:34:56' // Timestamp
)
```

## Security Features

- Nonce verification on all REST requests
- User capability checks
- Data sanitization (input)
- Output escaping (display)
- SQL injection protection (prepared statements)
- User isolation (users can only access their own saved questions)

## Translation Support

- Text domain: `saved-questions-tutor`
- All strings use `__()`, `_e()`, `esc_html__()`, etc.
- RTL CSS support included
- Translation files location: `/languages/`

## Browser Compatibility

- Modern browsers (Chrome, Firefox, Safari, Edge)
- Mobile responsive
- Graceful degradation (works without JS, shows message)

## Performance

- AJAX operations (no page reloads)
- Efficient database queries
- Optional caching via transients
- Minimal JavaScript footprint
- Lazy loading of saved questions list

## Testing Checklist

- [ ] Save button appears on quiz pages
- [ ] Clicking save button saves question
- [ ] Saved questions appear in profile tab
- [ ] Remove button works
- [ ] Export JSON works
- [ ] Export CSV works
- [ ] Mobile responsive
- [ ] RTL support (if needed)
- [ ] Works with latest Tutor LMS
- [ ] No JavaScript errors in console
- [ ] REST API endpoints accessible
- [ ] Security checks working

## Notes for Developers

1. **Custom Table Migration:**
   ```php
   $storage = new SQT_Storage();
   $storage->create_custom_table();
   $migrated = $storage->migrate_to_custom_table();
   ```

2. **Extending Functionality:**
   - Use hooks/filters to modify behavior
   - Override methods in child classes if needed
   - Add custom endpoints in REST API class

3. **Debugging:**
   - Enable WordPress debug mode
   - Check browser console for JS errors
   - Review REST API responses
   - Check server error logs

## Version History

- **1.0.0** - Initial release
  - Save questions from quizzes
  - View in profile tab
  - Remove questions
  - Export JSON/CSV
  - REST API
  - RTL support
  - Mobile responsive

