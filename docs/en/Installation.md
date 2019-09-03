# Installing WordPress Import Module

The weblog module can be installed via composer:

```
composer require axllent/silverstripe-weblog-wp-import
```

## Exporting your WordPress data

**Note:** You need to be logged in as an administrator to do this.

- `Tools` -> `Export`
- Select `Posts` (other exports not supported)


## Importing your data

- Make sure you have at least one blog set up and published
- Go to example.com/wp-import/ (you will need to log in if not already)
- Follow the instructions


## Internal linking / URL rewriting

When the module imports the content, all internal links are rewritten to the first page matching the
URLSegment (regardless of tree structure). This works fine in most cases unless you have more than one
page with the same URLSegment.

If you have any URLSegments that you know have changed, eg: `contact-us` has become `contact`, you are able
to map these in a yaml config for SiteTree lookups (remember to flush before re-processing your import).

```yaml
Axllent\WeblogWPImport\Control\ImportController:
  urlsegment_link_rewrite:
    'contact-us': 'contact'
    'product-gallery': 'products'
```


## Notes

The module will physically attempt to download all full-sized images from your WordPress blog.
Depending on the number images you have, the import process can take a while.

WordPress does not include the featured image for posts in the XML export file, so the module will
scrape every page off the website and try get the last `<meta property="og:image">` value from the page.
I am aware this this is unrealiable as it depends entirely on your WordPress template & plugins.
It's messy, I know.
