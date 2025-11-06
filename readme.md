# Alt Text Updater

Contributors: Chris Banks  
Tags: media, alt text, accessibility, seo, images  
Requires at least: WordPress 5.0  
Tested up to: WordPress 6.4  
Stable tag: 1.2.0  
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Updates missing alt text and title text for all media library images using intelligent filename parsing.

## Description 

This plugin helps improve your site's accessibility and SEO by automatically updating missing alt text and title text for images in your media library. 

When you run this plugin, it will:
* Scan all images in your WordPress media library
* Find images with empty alt text
* If a title exists, use it to populate the alt text
* If no title exists, generate formatted text from the filename and update both title and alt text
* Intelligently format filenames by adding spaces before capital letters and replacing underscores/hyphens with spaces

**Filename Formatting Examples:**
* `my-awesome-image.jpg` → "My Awesome Image"
* `ProductPhoto_Final.png` → "Product Photo Final"
* `BeachSunset2024.jpg` → "Beach Sunset 2024"

This is particularly useful if you've uploaded many images without alt text or titles, or if you want to quickly improve your site's accessibility and SEO.

## Installation 

1. Upload the plugin files to the `/wp-content/plugins/` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. Navigate to Media > Alt Text Updater to use the plugin

## Frequently Asked Questions 

### Will this overwrite existing alt text or titles? 

No, this plugin only updates images that have empty or missing alt text. It only generates titles from filenames when no title exists.

### How does the filename formatting work?

The plugin extracts the filename (without the file extension), replaces underscores and hyphens with spaces, adds spaces before capital letters, and converts the text to proper title case.

### Can I undo the changes? 

The plugin doesn't have an undo feature, so it's recommended to backup your database before running it.

### Does this work with all image types? 

Yes, it works with all image types in your WordPress media library.

## Changelog 

### 1.1.0
* Added intelligent filename parsing to generate title text when missing
* Automatically formats filenames with proper spacing and capitalization
* Updates both title and alt text from filename when title is empty

### 1.0.0 
* Initial release