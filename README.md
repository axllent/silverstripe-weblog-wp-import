# WordPress Importer for SilverStripe Weblog

A module to import WordPress XML into [SilverStripe Weblog](https://github.com/axllent/silverstripe-weblog).

This module is somewhat of a hack as WordPress integrates plugin shortcodes (requiring these WP plugins) inti the the XML export file.

This tool currently parses images, YouTube videos and links, plus the standard text formatting.
All other shortcodes are optionally stripped from the content.

Once imported, this module can be removed.


## Requirements

- A working version of the WordPress blog you wish to import (for images)
- PHP with simplexml support
- silverstripe/cms: ^4.0
- axllent/silverstripe-weblog
- axllent/silverstripe-weblog-categories (optional)
- axllent/simplehtmldom (included with composer install)
- guzzlehttp/guzzle (included with composer install)


## Features

- Interactive importer with options
- Import of all _published_ blog posts
- Import categories (if `axllent/silverstripe-weblog-categories` is installed)
- Image classes re-mapped to default SilverStripe image classes
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
