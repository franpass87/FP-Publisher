# GitHub Actions Workflow - Build WordPress Plugin

## Build WordPress Plugin ZIP

This workflow automatically builds a WordPress-ready ZIP package of the FP Publisher plugin.

### What it does:

1. **Copies Plugin Files**: Extracts all plugin files from `wp-content/plugins/trello-social-auto-publisher/`
2. **Includes Documentation**: Adds relevant documentation files (README, guides, etc.)
3. **Cleans Build**: Removes development files, logs, and unnecessary artifacts
4. **Creates ZIP**: Packages everything into `fp-publisher-wordpress-plugin.zip`
5. **Uploads Artifact**: Makes the ZIP available for download from the Actions tab

### When it runs:

- On push to main/master branch
- On pull requests to main/master branch  
- Manually via workflow dispatch
- On release creation (automatically attaches ZIP to release)

### Using the ZIP:

The generated ZIP file can be directly uploaded to WordPress:
1. Download the ZIP from the Actions artifacts
2. Go to WordPress Admin > Plugins > Add New > Upload Plugin
3. Upload the ZIP file and activate

### File Structure:

The ZIP contains:
- Main plugin file: `trello-social-auto-publisher.php`
- All PHP class files in `/includes/`
- Admin interface files in `/admin/`
- CSS and JS assets
- Documentation files

This ensures the plugin is ready for immediate WordPress installation.