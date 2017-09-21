# Installing WordPress Import Module

The weblog module can be installed via composer:

```
composer require axllent/silverstripe-weblog-wp-importer
```

## Exporting your WordPress data

**Note:** You need to be logged in as an administrator to do this.

- `Tools` -> `Export`
- Select `Posts` (other exports not supported)


## Importing your data

- Make sure you have at least one blog set up and published
- Go to example.com/wp-import/ (you will need to log in if not already)
- Follow the instructions


## Notes

The module will physically attempt to download all full-sized images from your WordPress blog.
Depending on the number images you have, the import process can take a while.

WordPress does not include the featured image for posts in the XML export file, so the module will
scrape every page off the website and try get the last `<meta property="og:image">` value from the page.
I am aware this this is unrealiable as it depends entirely on your WordPress template & plugins.
It's messy, I know.
