Copyright (c) 2012 Nic Jansma
[http://nicj.net](http://nicj.net)

Downloads magazines from the [Economist](http://economist.com), converts them to .mobi, and optionally emails them to your Kindle.

Details at [nicj.net](http://nicj.net/2009/04/13/the-economist-and-the-kindle).

Instructions
------------
1. Open `economist-to-kinde.php` and change any settings you need underneath this section:

    `// ****************** fill in these options ******************`

2. Run `economist-to-kinde.php` to generate a .mobi:

    `php -f economist-to-kinde.php`

Version History
---------------
* 2009-04-13: Initial release
* 2010-01-03: Uses your login credentials to download articles
* 2010-01-25: Several bugfixes
* 2011-05-02: Updates based on slifox and crosscode’s fixes.
* 2011-07-26: Small update to work with the economist.com's latest updates

Credits
-------
* Based on http://fatknowledge.blogspot.com/2008/09/economist-in-kindle-format.html
* Updated and maintained by Nic Jansma
* Contributions from: "Josh" http://nicj.net/2010/01/03/the-economist-and-the-kindle-take-2#comments
* Contributions from: "slifox" http://www.revlogic.net/public/economist_to_kindle.phps
 - Updated to work with latest Economist website (as of 06-27-2010)
 - Uses HTTPS (SSL encryption) for site login
 - Notes at http://nicj.net/2010/01/03/the-economist-and-the-kindle-take-2#comment-4575
* Contributions from: "CrossCode" http://www.crosscode.org/public/economist_to_kindle.phps
 - Notes at http://nicj.net/2010/01/03/the-economist-and-the-kindle-take-2#comment-4593
