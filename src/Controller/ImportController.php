<?php

namespace Axllent\WeblogWPImport\Control;

use Axllent\Weblog\Model\Blog;
use Axllent\Weblog\Model\BlogCategory;
use Axllent\Weblog\Model\BlogPost;
use Axllent\WeblogWPImport\Lib\WPXMLParser;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use SilverStripe\Assets\File;
use SilverStripe\Assets\FileNameFilter;
use SilverStripe\Assets\Image;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Session;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Forms\CheckboxSetField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\FileField;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\NumericField;
use SilverStripe\Forms\ReadonlyField;
use SilverStripe\Forms\RequiredFields;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\FieldType\DBHTMLText;
use SilverStripe\Security\Permission;
use SilverStripe\Security\Security;
use SilverStripe\View\ArrayData;
use SilverStripe\View\Requirements;
use SimpleHtmlDom;

set_time_limit(0);

class ImportController extends Controller
{
    private static $allowed_actions = [
        'index',
        'Cancel',
        'UploadForm',
        'Options',
        'OptionsForm',
    ];

    private static $url_segment = 'wp-import';

    private static $blog = false;

    public function init()
    {
        parent::init();
        if (!Permission::check('ADMIN')) {
            Security::permissionFailure(null, 'You must be logged in as an administrator to use this tool.');
        }

        Requirements::css('https://fonts.googleapis.com/css?family=Roboto+Slab');
        Requirements::css($this->getModuleDir() . '/css/milligram.min.css');
        Requirements::css($this->getModuleDir() . '/css/stylesheet.css');

        $this->session = $this->request->getSession();
    }

    public function index($request)
    {
        if ($this->hasValidUpload()) {
            return $this->redirect(self::$url_segment . '/options/', 302);
        }
        return $this;
    }

    public function Options($request)
    {
        if (!$this->hasValidUpload() || !$this->getBlog()) {
            return $this->redirect(self::$url_segment . '/cancel/', 302);
        }
        return $this->renderWith(['Axllent\\WeblogWPImport\\Control\\SelectionOptions']);
    }

    public function Cancel()
    {
        $this->session->clear('WPExport');
        $this->session->clear('WPImportFieldsSelected');
        return $this->redirect(self::$url_segment . '/', 302);
    }

    public function UploadForm()
    {
        if (!extension_loaded('simplexml')) {
            return DBHTMLText::create()
                ->setValue('<p class="message error">This module requires PHP with simplexml</p>');
        }

        $fields = new FieldList(
            $ul = FileField::create('XMLFile', 'Select your WordPress export XML file:'),
            DropdownField::create('BlogID', 'Select your weblog:', Blog::get()->Map('ID', 'MenuTitle'))
        );

        $ul->getValidator()->setAllowedExtensions(['xml']);

        $actions = new FieldList(
            FormAction::create('SaveFile')->setTitle('Upload and analize WordPress XML file »')
        );

        $required = new RequiredFields('XMLFile');

        $form = new Form($this, 'UploadForm', $fields, $actions, $required);

        return $form;
    }

    public function getBlog()
    {
        if (self::$blog) {
            return self::$blog;
        }
        $blog_id = $this->session->get('WPBlog');
        self::$blog = Blog::get()->byID($blog_id);
        return self::$blog;
    }

    public function OptionsForm()
    {
        $options = [
            'remove_styles_and_classes' => 'Remove all styles & classes',
            'remove_shortcodes' => 'Remove unparsed WordPress shortcodes after all filters',
            'scrape_for_featured_images' => 'Scrape the original site for featured images (<meta property="og:image">)',
        ];

        /* Add option for importing BlogCategory if it exists */
        if (class_exists('Axllent\\Weblog\\Model\\BlogCategory')) {
            $options = array_reverse($options, true);
            $options['categories'] = 'Import blog categories';
            $options = array_reverse($options, true);
        }

        if (BlogPost::get()->filter('ParentID', $this->getBlog()->ID)->Count()) {
            $options = array_reverse($options, true);
            $options['overwrite'] = 'Overwrite/update existing posts (matched by post URLSegment)';
            $options = array_reverse($options, true);
        }

        $default_options = [
            'categories',
            'remove_styles_and_classes',
            'remove_shortcodes'
        ];

        // Must match to html_<variable>($html)
        $filters = [
            'embed_youtube' => 'Link YouTube videos',
            'remove_divs' => 'Remove div elements (not innerHTML)',
            'remove_spans' => 'Remove span elements (not innerHTML)',
            'clean_trim' => 'Remove leading/trailing empty paragraphs',
        ];

        $default_filters = array_keys($filters);

        if ($this->session->get('WPImportFieldsSelected')) {
            $default_options = [];
            $default_filters = [];
        };

        $fields = new FieldList(
            CheckboxSetField::create(
                'WPImportOptions',
                'Choose the import options:',
                $options,
                $default_options
            ),
            NumericField::create('set_image_width', 'Set post image widths: (If used all then ' .
                'locally hosted images will be set to this size provided they are large enough)'),
            CheckboxSetField::create(
                'WPImportFilters',
                'Choose the import filters:',
                $filters,
                $default_filters
            )
        );

        $actions = new FieldList(
            FormAction::create('ProcessImport')->setTitle('Process Options »')
        );

        $required = new RequiredFields([]);

        $form = new Form($this, 'OptionsForm', $fields, $actions, $required);

        return $form;
    }

    public function ProcessImport($data, $form)
    {
        $form->setSessionData($data);
        $this->session->set('WPImportFieldsSelected', 'true');

        $import = $this->getImportData();

        if (!$import) {
            $form->sessionMessage('No valid data found');
            return $this->redirectBack();
        }

        $process_categories = !empty($data['WPImportOptions']['categories']) ? : false;
        $overwrite = !empty($data['WPImportOptions']['overwrite']) ? true : false;
        $remove_shortcodes = !empty($data['WPImportOptions']['remove_shortcodes']) ? true : false;
        $remove_styles_and_classes = !empty($data['WPImportOptions']['remove_styles_and_classes']) ? true : false;
        $scrape_for_featured_images = !empty($data['WPImportOptions']['scrape_for_featured_images']) ? true : false;
        $set_image_width = !empty($data['set_image_width']) ? $data['set_image_width'] : false;
        $import_filters = !empty($data['WPImportFilters']) ? $data['WPImportFilters'] : false;


        $status = []; // Form return

        $blog = $this->getBlog();

        if ($process_categories) {
            $categories_created = 0;
            /* Check all categories exist */
            foreach ($import->Categories as $category) {
                $cat = $blog->Categories()->filter('Title', $category->Title)->first();
                if (!$cat) {
                    $cat = BlogCategory::create([
                        'Title' => $category->Title
                    ]);
                    $blog->Categories()->add($cat);
                    $categories_created++;
                }
            }
            $status[] = $categories_created . ' categories created';
        }

        // Counters for form return
        $blog_posts_added = 0;
        $blog_posts_updated = 0;
        $assets_downloaded = 0;

        $this->featured_image_folder = Config::inst()->get('Axllent\\Weblog\\Model\\BlogPost', 'featured_image_folder');

        foreach ($import->Posts as $orig) {
            $blog_post = BlogPost::get()->filter('URLSegment', $orig->URLSegment)->first();
            if ($blog_post && !$overwrite) {
                continue;
            }

            if (!$blog_post) {
                $blog_post = BlogPost::create([
                    'URLSegment' => $orig->URLSegment
                ]);
                $blog_posts_added++;
            } else {
                $blog_posts_updated++;
            }

            $blog_post->Title = $orig->Title;
            $blog_post->PublishDate = $orig->PublishDate;
            $blog_post->ParentID = $blog->ID;
            $blog_post->HasBrokenLink = 0;
            $blog_post->HasBrokenFile = 0;

            // Now we parse the hell out of the content
            $content = $orig->Content;

            // Format WordPress code
            $content = $this->wpautop($content);

            if ($import_filters) {
                foreach ($import_filters as $fcn) {
                    $html_fcn = 'html_' . $fcn;
                    if (ClassInfo::hasMethod($this, $html_fcn)) {
                        $content = $this->$html_fcn($content, $data);
                    }
                }
            }

            $dom = SimpleHtmlDom\str_get_html(
                $content,
                $lowercase=true,
                $forceTagsClosed=true,
                $target_charset = 'UTF-8',
                $stripRN=false
            );

            if ($dom) {
                if ($remove_styles_and_classes) {
                    // remove all styles
                    foreach ($dom->find('*[style]') as $el) {
                        $el->style = false;
                    }
                    // remove all classes except for images
                    foreach ($dom->find('*[class]') as $el) {
                        if ($el->tag != 'img') {
                            $el->class = false;
                        }
                    }
                }

                /**
                 * IMAGES
                 * Downloads hosted images and sets SilverStrypoe classes
                 * leftAlone|center|left|right ss-htmleditorfield-file image
                 */
                foreach ($dom->find('img') as $img) {
                    if ($class = $img->class) {
                        if (preg_match('/\balignright\b/', $class)) {
                            $img->class = 'right ss-htmleditorfield-file image';
                        } elseif (preg_match('/\balignleft\b/', $class)) {
                            $img->class = 'left ss-htmleditorfield-file image';
                        } elseif (preg_match('/\baligncenter\b/', $class)) {
                            $img->class = 'center ss-htmleditorfield-file image';
                        } else {
                            $img->class = 'leftAlone ss-htmleditorfield-file image';
                        }
                    } else {
                        $img->class = false;
                    }

                    /* Import Images */
                    $orig_src = $img->src;
                    if (!$orig_src) {
                        continue;
                    }

                    $parts = parse_url($orig_src);
                    if (empty($parts['path'])) {
                        continue;
                    }

                    if (!preg_match('/^' . preg_quote($import->SiteURL, '/') . '/', $orig_src)) {
                        continue; // don't download remote images - too problematic re: filenames
                    }

                    $orig_src = rtrim($import->SiteURL, '/') . $parts['path'];

                    $non_scaled = preg_replace('/^(.*)(\-\d\d\d?\d?x\d\d\d?\d?)\.([a-z]{3,4})$/', '${1}.${3}', $orig_src);

                    $file_name = @pathinfo($non_scaled, PATHINFO_BASENAME);
                    $nameFilter = FileNameFilter::create();
                    $file_name = $nameFilter->filter($file_name);

                    if (!$file_name) {
                        $blog_post->HasBrokenFile = 1;
                        continue;
                    }

                    $file = Image::get()->filter('FileFilename', $this->featured_image_folder .'/' . $file_name)->first();
                    if (!$file) {
                        // Download asset
                        $data = $this->getRemoteFile($non_scaled);

                        if (!$data) {
                            if ($non_scaled != $orig_src) {
                                // Try download the image directly (maybe scaling params are in the original filename?)
                                $data = $this->getRemoteFile($orig_src);
                            }
                            if (!$data) {
                                // Create a a broken image
                                $new_tag = '[image src="' . $orig_src . '" id="0"';
                                if ($v = $img->width) {
                                    $new_tag .= ' width="' . $v .'"';
                                }
                                if ($v = $img->height) {
                                    $new_tag .= ' height="' . $v .'"';
                                }
                                $new_tag .= ' class="' . $img->class .'"';
                                $new_tag .= ' alt="' . $img->alt .'"';
                                if ($v = $img->title) {
                                    $new_tag .= ' title="' . $img->title .'"';
                                }
                                $new_tag .= ']';
                                $img->outertext = $new_tag;
                                $blog_post->HasBrokenFile = 1;
                                continue; // 404
                            }
                        }

                        $assets_downloaded++;

                        $file = new Image();
                        $file->setFromString($data, $this->featured_image_folder .'/' . $file_name);
                        if ($img->title) {
                            // $file->Name = $file_name;
                            $file->Title = $img->title;
                        } elseif ($img->alt) {
                            // $file->Name = $file_name;
                            $file->Title = $img->alt;
                        }
                        $file->write();
                        $file->doPublish();
                    }

                    if ($file) {
                        // Manually create shortcode
                        $img_width = $img->width ? $img->width : false;
                        $img_height = $img->height ? $img->height : false;

                        // Rescale if set & image is large enough and options set
                        $src_width = $file->getWidth();
                        $src_height = $file->getHeight();
                        if ($set_image_width && $file->getWidth() >= $set_image_width) {
                            $ratio = $src_width / $src_height;
                            $img_width = $set_image_width;
                            $img_height = round($set_image_width / $ratio);
                        }

                        $src = $file->Link();
                        $new_tag = '[image src="' . $src . '" id="' . $file->ID . '"';
                        if ($img_width) {
                            $new_tag .= ' width="' . $img_width .'"';
                        }
                        if ($img_height) {
                            $new_tag .= ' height="' . $img_height .'"';
                        }
                        $new_tag .= ' class="' . $img->class .'"';
                        $new_tag .= ' alt="' . $img->alt .'"';
                        if ($v = $img->title) {
                            $new_tag .= ' title="' . $img->title .'"';
                        }
                        $new_tag .= ']';
                        $img->outertext = $new_tag;
                    }
                }

                /**
                 * Internal LINKS
                 * Re-link internal links where possible
                 * downloading resources where necessary
                 */
                foreach ($dom->find('a[href^=' . $import->SiteURL . ']') as $a) {
                    if ($href = $a->href) {
                        $parts = parse_url($href);

                        $link_file = @pathinfo($parts['path'], PATHINFO_BASENAME);

                        if ($link_file == '') { // home link
                            $link_file = 'home';
                        }

                        /* Set link to broken unless we find it */
                        $a->href = '[sitetree_link,id=0]';
                        $a->class = 'ss-broken';

                        /* Try match to SiteTree */
                        $page = SiteTree::get()->filter('URLSegment', $link_file)->first();

                        if ($page) {
                            $a->href = '[sitetree_link,id=' . $page->ID. ']';
                            $a->class = false;
                            continue;
                        }

                        /* Try match to a file */
                        $nameFilter = FileNameFilter::create();
                        $file_name = $nameFilter->filter($link_file);

                        $has_ext = preg_match('/\.([a-z0-9]{3,4})$/i', $file_name, $matches);

                        if (!$has_ext) {
                            $blog_post->HasBrokenLink = 1;
                            continue; // No extension - not a File
                        }

                        $ext = strtolower($matches[1]);

                        $file = File::get()->filter('Name', $file_name)->first();

                        if (!$file) {
                            $data = $this->getRemoteFile($href);

                            if (!$data) { // 404
                                $a->href = '[file_link,id=0]';
                                $a->class = 'ss-broken';
                                $blog_post->HasBrokenFile = 1;
                                continue;
                            }

                            $assets_downloaded++;

                            /* Create image if image */
                            if (in_array($ext, ['gif', 'jpeg', 'jpg', 'png', 'bmp'])) {
                                $file = new Image();
                            } else {
                                $file = new File();
                            }

                            $file->setFromString($data, $this->featured_image_folder .'/' . $file_name);
                            $file->write();
                            $file->doPublish();
                        }
                        if ($file) {
                            // re-link to file
                            $a->href = '[file_link,id=' . $file->ID . ']';
                            $a->class = false;
                        }
                    }
                }

                $content = trim($dom->save());
            }

            /**
             * Remove shortcodes
             */
            if ($remove_shortcodes) {
                $content = $this->html_remove_shortcodes($content);
            }

            $blog_post->Content = $content;

            /**
             * Scrape original site for Featured Images
             */
            if ($scrape_for_featured_images && !$blog_post->FeaturedImage()->exists()) {
                $remote_html = $this->getRemoteFile($orig->Link);
                if ($remote_html && $dom = SimpleHtmlDom\str_get_html(
                    $remote_html,
                    $lowercase=true,
                    $forceTagsClosed=true,
                    $target_charset = 'UTF-8',
                    $stripRN=false
                )) {
                    // Find the last meta[property=og:image] as some sites also include the author's image
                    if ($img = $dom->find('meta[property=og:image]', -1)) {
                        $featured_image_src = $img->content;

                        $file_name = @pathinfo($featured_image_src, PATHINFO_BASENAME);
                        $nameFilter = FileNameFilter::create();
                        $file_name = $nameFilter->filter($file_name);

                        if (!$file_name) {
                            continue;
                        }

                        $file = Image::get()->filter('FileFilename', $this->featured_image_folder .'/' . $file_name)->first();
                        if (!$file) {
                            $data = $this->getRemoteFile($featured_image_src);
                            if (!$data) {
                                continue; // 404
                            }
                            $assets_downloaded++;
                            $file = new Image();
                            $file->setFromString($data, $this->featured_image_folder .'/' . $file_name);
                            $file->write();
                            $file->doPublish();
                        }

                        if ($file) {
                            $blog_post->FeaturedImageID = $file->ID;
                        }
                    }
                }
            }

            $blog_post->write();
            $blog_post->doPublish();

            // Add categories
            if ($process_categories) {
                $categories = $orig->Categories;
                foreach ($categories as $category) {
                    if (!$blog_post->Categories()->filter('Title', $category->Title)->first()) {
                        $cat_obj = $blog_post->Parent()->Categories()->filter('Title', $category->Title)->first();
                        if ($cat_obj->exists()) {
                            $blog_post->Categories()->add($cat_obj);
                        }
                    }
                }
            }
        }

        $status[] = $blog_posts_added . ' posts added';

        if ($overwrite) {
            $status[] = $blog_posts_updated . ' posts updated';
        }

        $status[] = $assets_downloaded . ' assets downloaded';

        $form->sessionMessage(implode($status, ', '), 'good');

        return $this->redirectBack();
    }

    public function hasValidUpload()
    {
        $session = $this->request->getSession();

        return $session->get('WPExport') ? true : false;
    }

    public function getSessionXML()
    {
        $session = $this->request->getSession();

        return $session->get('WPExport');
    }

    public function SaveFile($data, $form)
    {
        $blog_id = $data['BlogID'];

        $blog = Blog::get()->byID($data['BlogID']);
        if (!$blog) {
            $form->sessionMessage('Please select a valid Blog.');
            return $this->redirectBack();
        }

        $file = $data['XMLFile'];

        if (empty($file['tmp_name'])) {
            $form->sessionMessage('Please upload a valid XML file.');
            return $this->redirectBack();
        }
        // raw xml string
        $content = file_get_contents($file['tmp_name']);

        $parser = new WPXMLParser($content);

        if (!$parser->xml) {
            $form->sessionMessage('File could not be parsed. Please make sure the file is a valid WordPress export XML file.');
            return $this->redirectBack();
        }

        $session = $this->request->getSession();

        $session->set('WPExport', $content);
        $session->set('WPBlog', $blog_id);

        return $this->redirect(self::$url_segment . '/options/');
    }

    public function getImportData()
    {
        $xml = $this->session->get('WPExport');
        if (!$xml) {
            return false;
        }
        $parser = new WPXMLParser($xml);

        $data = $parser->XML2Data();
        if (!$data) {
            return false;
        }

        $categories_lookup = [];
        $categories = ArrayList::create();
        foreach ($data->Posts as $post) {
            foreach ($post->Categories as $cat) { //}$url => $title) {
                if (!isset($categories_lookup[$cat->URLSegment])) {
                    $categories_lookup[$cat->URLSegment] = $cat->Title;
                    $categories->push(ArrayData::create([
                        'URLSegment' => $cat->URLSegment,
                        'Title' => $cat->Title
                    ]));
                }
            }
        }

        return ArrayData::create([
            'SiteURL' => $data->SiteURL,
            'Posts' => $data->Posts->filter('Status', 'publish'),
            'Categories' => $categories
        ]);
    }

    /**
     * HTTP wrapper
     * @param String
     * @return String
     */
    public function getRemoteFile($url)
    {
        $body = false;

        $client = new Client([
            'timeout'  => 10,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 6.1; rv:49.0) Gecko/20100101 Firefox/49.0',
                'Accept-Language' => 'en-US,en;q=0.5'
            ],
        ]);
        try {
            $response = $client->get($url);
            $code = @$response->getStatusCode();
            if ($code == 200 || $this->force_cache) { // don't cache others
                $body = $response->getBody()->getContents();
                return $body;
            }
        } catch (RequestException $e) {
            // ignore
        }

        unset($client);
        $client = null;

        return $body;
    }

    public function getModuleDir()
    {
        return basename(dirname(dirname(dirname(__FILE__))));
    }
}
