This is a simple script used to serve and manage the images for an Oppaitime-Gazelle based tracker

It is an image host, but it works more like a proxy with a permanent cache. Users are expected to upload their images to any host they desire, including temporary hosts, or find an image already available on the web and use the links to those images normally (as if the tracker has no host at all). Oppaitime-Gazelle will pass those image URLs to this host script, which will download them the first time access is attempted and continue serving the image from its cache from then on.

The primary purposes of this script are: 
* Longevity
  * The original image source becoming unavailable does not affect this host's ability to continue serving the image.
* Privacy
  * The original image url is never directly embedded on any page served to users. The first time an image is accessed, the host acts as a proxy between the user and the source. All subsequent accesses are exclusively between the user and this host. In this way, the source is never able to identify any users.

Because of the way this script works, it can be dropped into place at any Gazelle tracker with minimal effort, and it will start seamlessly proxying and caching existing images as they are accessed.

Note: This script does not handle thumbnailing, instead preferring to use the highly performant nginx-image-filter module to thumbnail on the fly. An example nginx configuration for handling thumbnailing in a way expected by Oppaitime-Gazelle is provided in this repo.
