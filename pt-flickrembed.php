<?php
/*
Plugin Name: PT Flickr Embed
Plugin URI: #
Description: Allows embedding of Flickr photos using a shortcode: [flickr id="12345" title="This is a nice photo" align="right" size="t"].
Author: Mark Howells-Mead
Version: 1.0
Author URI: http://permanenttourist.ch
*/

/*

Designed for use on an Apache server running PHP5 with CURL enabled.

Options
=======

Example usage. (Place shortcode on its own line in the WordPress editor.)
[flickr id="12345"]
[flickr id="12345" title="This is a nice photo"]
[flickr id="12345" title="This is a nice photo" size="t"]
[flickr id="12345" title="This is a nice photo" size="t" align="right"]

Sizes
=====
http://www.flickr.com/services/api/flickr.photos.getSizes.html

Caching
=======
Image data is cached on the local web server in order to improve performance.
UPDATEMODE "W" means that the data will be cached weekly. XML files are stored
in a subfolder "pt-flickrembed" of the standard upload directory specified
in the default WordPress Admin tool (Settings » Media).

In the absence of an options page in the current version, you'll need to add
the Flickr API key and secret manually. Get these from http://www.flickr.com/services/api/keys/

Attributes LINK, TITLE, ALIGN and SIZE are optional. ID is required.

If LINK is empty, the image will be linked to the Flickr page for the photo.

Usual ALIGN values: right, left. (If no alignment left/right is required, leave blank)

If SIZE is empty, the 500px version will be displayed.
Refer to http://www.flickr.com/services/api/misc.urls.html for valid sizes.
*/

require_once('class.pt-flickrembed.php');

$pt_flickrembed = new PTFLICKREMBED();

$pt_flickrembed->flickrUserName = 'mhowells';