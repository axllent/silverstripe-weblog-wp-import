# WordPress Importer for Silverstripe Weblog

A module to import WordPress XML into [Silverstripe Weblog](https://github.com/axllent/silverstripe-weblog).

**Please note** that I have not used this import module in over 6 years, so it may or may not work as expected.

This module is somewhat of a hack as WordPress integrates plugin shortcodes (requiring these WP plugins) inti the the XML export file.

This tool currently parses images, YouTube videos and links, plus the standard text formatting.
All other shortcodes are optionally stripped from the content.

Once the blog has been imported, this module can be uninstalled as it serves no further purpose.


## Requirements

- A working version of the WordPress blog you wish to import (for images)
- PHP with simplexml support
- axllent/silverstripe-weblog
- axllent/silverstripe-weblog-categories (optional)
- axllent/simplehtmldom (included with composer install)
- guzzlehttp/guzzle (included with composer install)


## Features

- Interactive importer with options
- Import of all _published_ blog posts
- Import categories (if `axllent/silverstripe-weblog-categories` is installed)
- Image classes re-mapped to default Silverstripe image classes
- Downloads (full-sized) hosted images and re-links (using SS shortcode) them in content
- Includes YouTube videos created in default WordPress as well as the fusion plugin
- Option to set the imported blog post image widths
- Options to remove (strip) all divs, spans, classes & styles from imported data
- Auto-links to internal pages
- Missing files / broken links reported in the CMS reports utility


## Documentation

- [Installation & Usage](docs/en/Installation.md)


## Suggested Modules

- [axllent/silverstripe-weblog-categories](https://github.com/axllent/silverstripe-weblog-categories) - Blog categories module
