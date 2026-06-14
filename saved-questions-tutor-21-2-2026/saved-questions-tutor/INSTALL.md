# Installation Guide - Saved Questions for Tutor LMS

## Quick Start

1. **Download the plugin**
   - Download the `saved-questions-tutor` folder as a ZIP file
   - Or clone the repository

2. **Install in WordPress**
   - Go to WordPress Admin → Plugins → Add New
   - Click "Upload Plugin"
   - Select the ZIP file
   - Click "Install Now"

3. **Activate**
   - After installation, click "Activate Plugin"

4. **Verify Requirements**
   - Make sure Tutor LMS is installed and activated
   - The plugin will show a notice if Tutor LMS is missing

## Manual Installation via FTP

1. Extract the plugin folder
2. Upload `saved-questions-tutor` to `/wp-content/plugins/`
3. Go to WordPress Admin → Plugins
4. Find "Saved Questions for Tutor LMS" and activate

## Post-Installation

1. **Test the Plugin:**
   - Create or open a quiz in Tutor LMS
   - You should see "Save Question" buttons next to questions
   - Click to save a question
   - Go to your profile → "Saved Questions" tab to verify

2. **Configure (Optional):**
   - By default, questions are stored in user meta
   - To use custom database table, add this to your theme's `functions.php`:
     ```php
     add_action('init', function() {
         update_option('sqt_use_custom_table', true);
         $storage = new SQT_Storage();
         $storage->create_custom_table();
     });
     ```

## Troubleshooting

### Plugin not working?
- Check that Tutor LMS is active
- Clear browser cache
- Check browser console for JavaScript errors
- Verify REST API is enabled (Settings → Permalinks → Save)

### Buttons not appearing?
- Make sure you're logged in
- Check that you're on a quiz page
- Try a different browser
- Disable other plugins to check for conflicts

### Questions not saving?
- Check browser console for errors
- Verify REST API endpoints are accessible
- Check user permissions
- Review server error logs

## Uninstallation

1. Go to Plugins → Installed Plugins
2. Find "Saved Questions for Tutor LMS"
3. Click "Deactivate" then "Delete"

**Note:** By default, saved questions data is NOT deleted on uninstall. To delete data:
1. Before uninstalling, add this to `wp-config.php`:
   ```php
   define('SQT_DELETE_DATA_ON_UNINSTALL', true);
   ```
2. Then uninstall the plugin

## Support

For issues or questions, please contact the plugin developer.

