<?php
/**
 * economist_to_kindle.php
 *  Based on http://fatknowledge.blogspot.com/2008/09/economist-in-kindle-format.html
 *
 * Contributions from: "Josh" http://nicj.net/2010/01/03/the-economist-and-the-kindle-take-2#comments
 *
 * Contributions from: "slifox" http://www.revlogic.net/public/economist_to_kindle.phps
 *   - updated to work with latest Economist website (as of 06-27-2010)
 *   - uses HTTPS (SSL encryption) for site login
 *   - notes at http://nicj.net/2010/01/03/the-economist-and-the-kindle-take-2#comment-4575
 *
 * Contributions from: "CrossCode" http://www.crosscode.org/public/economist_to_kindle.phps
 *   - notes at http://nicj.net/2010/01/03/the-economist-and-the-kindle-take-2#comment-4593
 *
 * PHP version 5
 *
 * @package   EconomistToKindle
 * @author    Nic Jansma <nic@nicj.net>
 * @copyright 2010 Nic Jansma
 * @link      http://www.nicj.net
 */

// ****************** fill in these options ******************

//
// base working directory
//
$baseDir = '/home/foo/economist';

//
// directory to store issues
//
$issueDir = $baseDir . '/issues';

//
// location of mobigen_linux executable
//
//$mobigenFile = $baseDir . '/mobigen_linux';
$mobigenExec = '/usr/local/bin/mobigen_linux';

//
// get images for the articles
//
$withImages = true;

//
// enable creating a link to the latest issue?
//  (this allows you to access the latest issue via the same URL,
//   if this is put in a webserver-accessible directory)
//
$enableLink = 0;

//
// location of the latest-issue link
//
$linkFile = $baseDir . '/latest.mobi';

//
// enable sending email?
//
$enableEmail = 1;

//
// enable magazine features?
//
$magazineFeatures = 1;

//
// sender's email
//
$fromEmail = 'foo@foo.com';

//
// your kindle's email
//
$toEmail = 'foo@kindle.com';

//
// if true, gets a specific back-issue
//
$getBackIssue      = false;
$backIssueDate     = '20090110';
$backIssueDateLong = 'Jan 10, 2009';

//
// economist.com login and password
//
$GLOBALS['loginEmail'] = 'foo@foo.com';
$GLOBALS['loginPass']  = 'password';

//
// Load config from file (optional)
//
$configFile = dirname(__FILE__) . '/economist-to-kindle.config.php';

if (file_exists($configFile)) {
    echo "Reading configuration from economist-to-kindle.config.php\n";
    include_once $configFile;
}

// ****************** fill in above options ******************

// where to store cookies
$GLOBALS['cookieJarFile'] = $baseDir . '/economist-cookies.txt';

// unlimited execution time
set_time_limit(0);

//
// log in and set cookies
//
economistLogin();

//
// validate we have access
//
if (economistValidateAccess() === false) {
    echo "Could not log in to economist.com: Username or password mismatch!\n";
    exit;
}

//
// set $date and $dateLong of issue to get
//
$date     = '';
$dateLong = '';

if ($getBackIssue) {
    $date     = $backIssueDate;
    $dateLong = $backIssueDateLong;
} else {
    economistGetCurrentIssueDate($date, $dateLong);
}

// create issue dir if necessary
if (!is_dir($issueDir)) {
    echo "Creating $issueDir\n";
    mkdir($issueDir);
}

// create work dir
$dateDirectory = $issueDir . "/economist_$date";
if (is_dir($dateDirectory)) {
    echo "No new Economists!  Already have economist_$date.\n";
    exit;
}

echo "Economist $dateLong ($date)\n";
mkdir($dateDirectory);

// work vars
$urls = array();
$ids  = array();

//
// page generation
//
createOPF($dateDirectory, $date, $dateLong, $withImages, $magazineFeatures);
createTOC($dateDirectory, $date, $dateLong, $urls, $ids);
createHTML($dateDirectory, $withImages, $urls, $ids);

//
// mobi conversion
//
$opfFile  = "{$dateDirectory}/economist_$date.opf";
$mobiFile = "{$dateDirectory}/economist_$date.mobi";
echo "Running: $mobigenExec -c1 $opfFile... ";
system($mobigenExec . " -c1 $opfFile");
echo " done!\n";

//
// add latest-issue link if requested
//
if ($enableLink == 1) {
    system("rm $linkFile");
    system("ln -s $mobiFile $linkFile");
    system("chmod -R 755 $linkFile $issueDir");
    echo "Latest Issue Link: $linkFile\n";
}

//
// email
//
if ($enableEmail == 1) {
    emailEconomist($fromEmail, $toEmail, $mobiFile, "economist_$date.mobi");
    echo "Sent email to: $toEmail\n";
}

//
/**
 * Validate access to economist.com
 *
 * @return bool True if access to economist.com is valid
 *
 */
function economistValidateAccess()
{
    $homePageContents = economistGetUrl('http://www.economist.com/printedition/');

    if (strpos($homePageContents, 'This page is now available to subscribers only.') !== false) {
        return false;
    } else {
        return true;
    }
}

/**
 * Gets the current Economist issue date
 *
 * @param string &$date     Date (20100102 form)
 * @param string &$dateLong Long date (January 2, 2010 form)
 *
 * @return void
 *
 */
function economistGetCurrentIssueDate(&$date, &$dateLong)
{
    $homePageContents = economistGetUrl('http://www.economist.com/printedition/');

    // <span class="article-date">January 17th 2009</span>
    $start = strpos($homePageContents, '<span class="article-date">');
    if ($start !== false) {
        $start += strlen('<span class="article-date">');
    } else {
        // <span class="issue-date">Feb 4th, 2012</span>
        $start = strpos($homePageContents, '<span class="issue-date">');
        if ($start !== false) {
            $start += strlen('<span class="issue-date">');
        } else {
            echo 'Cannot find the article date!';
            exit;
        }
    }

    $end = strpos($homePageContents, '</', $start);

    // get date
    $pageDate = substr($homePageContents, $start, $end - $start);
    $date     = convertDate($pageDate);
    $dateLong = convertDateLong($pageDate);
}

/**
 * Converts old-style: January 17th 2009 to 20090117
 *          new-style: Feb 4th, 2012 to 20120204
 *
 * @param string $pageDate The page's date (eg January 17th 2009)
 *
 * @return string yyyymmdd date format
 *
 */
function convertDate($pageDate)
{
    $monthnames = array(
                   'Jan' => '01',
                   'Feb' => '02',
                   'Mar' => '03',
                   'Apr' => '04',
                   'May' => '05',
                   'Jun' => '06',
                   'Jul' => '07',
                   'Aug' => '08',
                   'Sep' => '09',
                   'Oct' => '10',
                   'Nov' => '1',
                   'Dec' => '12',
                  );

    $pieces = explode(' ', $pageDate);

    $month = $pieces[0];
    $day   = $pieces[1];
    $year  = $pieces[2];

    // strip comma from day
    $day = str_replace(',', '', $day);

    // remove 'th', 'nd', etc
    $day = (strlen($day) === 4) ? substr($day, 0, 2) : '0' . substr($day, 0, 1);

    return $year . $monthnames[substr($month, 0, 3)] . $day;
}

/**
 * Converts January 17th 2009 to Jan 17, 2009
 *
 * @param string $pageDate The page's date (eg. January 17th 2009)
 *
 * @return string Long date format (eg. Jan 17, 2009)
 *
 */
function convertDateLong($pageDate)
{
    $pieces = explode(' ', $pageDate);

    $month = $pieces[0];
    $day   = $pieces[1];
    $year  = $pieces[2];

    // strip comma from day
    $day = str_replace(',', '', $day);

    return substr($month, 0, 3) . ' ' . substr($day, 0, -2) . ', ' . $year;
}

/**
 * Creates an OPF file
 *
 * @param string  $dateDirectory    Issue's directory
 * @param string  $date             Issue's date
 * @param string  $dateLong         Issue's long date
 * @param boolean $withImages       With images
 * @param boolean $magazineFeatures Use magazine features
 *
 * @return void
 *
 */
function createOPF($dateDirectory, $date, $dateLong, $withImages, $magazineFeatures)
{
    echo "Creating OPF file: economist_$date.opf...\n";

    $opfFile = $dateDirectory . "/economist_$date.opf";

    $opfh = fopen($opfFile, 'w');

    $coverMeta = '';
    $coverItem = '';
    if ($withImages) {
        $coverFile = economistGetCover($dateDirectory, $date);
        $coverMeta = '<meta name="cover" content="my-cover-image"/>';
        $coverItem = '<item href="'.$coverFile.'" id="my-cover-image" media-type="image/jpeg"/>';
    }

    if ($magazineFeatures) {
        $ctype = 'content-type="application/x-mobipocket-subscription-magazine"';
    } else {
        $ctype = '';
    }

    //
    // TODO: Nic Jansma @ 2011-05-02: NCX TOC is creating error messages in mobigen:
    //    "Error(prcgen): TOC section scope is not included in the parent chapter: Blah"
    // Disabled until I can figure out why
    //
    $useNcxToc = false;
    $spineToc  = $useNcxToc ? 'toc="ncxtoc"' : '';

    fwrite($opfh,
    '<?xml version="1.0" encoding="utf-8"?>
    <package unique-identifier="uid">
        <metadata>
            <dc-metadata xmlns:dc="http://purl.org/metadata/dublin_core"
                         xmlns:oebpackage="http://openebook.org/namespaces/oeb-package/1.0/">
                <dc:Title>The Economist</dc:Title>
                <dc:Language>en-us</dc:Language>
                <dc:Identifier id="uid">69D99D4B30</dc:Identifier>
                <dc:Creator>'.$dateLong.'</dc:Creator>
            </dc-metadata>
            <x-metadata>
                <output encoding="UTF-8" '.$ctype.'></output>
            </x-metadata>
            '.$coverMeta.'
        </metadata>
    <manifest>
      <item id="ncxtoc" media-type="application/x-dtbncx+xml" href="toc.ncx"/>
      <item id="toc" media-type="text/x-mbp-manifest-item" href="mbp_toc.html"></item>
      <item id="item1" media-type="text/x-oeb1-document" href="economist.html"></item>
      '.$coverItem.'
    </manifest>
    <spine '.$spineToc.'>
    <itemref idref="toc"/>
    <itemref idref="item1"/></spine>
    <tours></tours>
    <guide><reference type="toc" title="Economist" href="mbp_toc.html"></reference></guide>
    </package>');

    fclose($opfh);

    echo " done!\n";
}

/**
 * Creates a Table of Contents
 *
 * @param string $dateDirectory Issue's directory
 * @param string $date          Issue's date
 * @param string $dateLong      Issue's long date
 * @param array  &$urls         URLs of all articles (output)
 * @param array  &$ids          Article IDs (output)
 *
 * @return void
 *
 */
function createTOC($dateDirectory, $date, $dateLong, &$urls, &$ids)
{
    echo "Creating table of contents file: mbp_toc.html...\n";

    //
    // NOTE: Old-style URLs were: http://www.economist.com/printedition/index.cfm?d=20120102;
    //  Now they are http://www.economist.com/printedition/2012-01-02
    //

    // Download this edition's page
    $dateNew = substr($date, 0, 4) . '-' . substr($date, 4, 2) . '-' . substr($date, 6, 2);

    $tocUrl = "http://www.economist.com/printedition/$dateNew";

    $pageContents = economistGetUrl($tocUrl);

    // sanity check we have valid content
    if (strpos($pageContents, 'Page not found') !== false) {
        echo "DEBUG: Page not found!!!\n";
        exit;
    }

    //
    // chop to the beginning of the article list
    //
    if (strpos($pageContents, '<!-- section box --><div class="box style-2">') !== false) {
        // old-style
        $pageContents = strstr($pageContents, '<!-- section box --><div class="box style-2">');
    } else if (strpos($pageContents, '<div id="column-content"') !== false) {
        // new-style
        $pageContents = strstr($pageContents, '<div id="column-content"');
    } else {
        echo "DEBUG: Unknown begginning of articles!!!\n";
        exit;
    }

    //
    // chop off the end of the article list
    //

    // old-style
    $searchPos = false;
    $endPos    = 0;
    while (($searchPos = strpos($pageContents, '</div><!-- end section box -->', $endPos)) !== false) {
        $endPos = $searchPos + strlen('</div><!-- end section box -->');
    }

    if ($endPos > 0) {
        $pageContents = substr($pageContents, 0, $endPos);
    }

    // new-style
    $endPos = strpos($pageContents, '<!-- /#column-content -->');
    if ($endPos > 0) {
        $pageContents = substr($pageContents, 0, $endPos);
    }

    // remove banners
    $pageContents = preg_replace('/<div class="box">([[:space:]])*<div class="content-banner">([[:space:]])*<div align="center">(.*?)<\/div>([[:space:]])*<\/div>([[:space:]])*<\/div>/s',
                                 '',
                                 $pageContents);

    // remove "premium" icons
    $pageContents = preg_replace('#<img src="/images/icon_premium.png"[^>]+>#',
                                 '',
                                 $pageContents);

    // remove british flags
    $pageContents = preg_replace('/<img src="\/images\/icon_britain.png" alt="\[Britain only\]">/',
                                 '',
                                 $pageContents);

    $pageContents = preg_replace('/<p class="britian">\s*Articles flagged with this icon are printed only in the British edition of <em>The Economist<\/em><\/p>/s',
                                 '',
                                 $pageContents);

    // remove right column stuff
    $pageContents = preg_replace('/<div class="col-right"><div class="box topbox style-2"><div class="link-box-double clear">' .
                                  '<div class="left">(.*?)<\/div>([[:space:]])*<\/div>([[:space:]])*<\/div>([[:space:]])*<\/div>/s',
                                 '',
                                 $pageContents);

    // get urls of all article pages, for use in createHTML func
    preg_match_all('/<a href="(\/node\/[0-9]+)"/', $pageContents, $matches);
    $urls = $matches[1];

    // get ids like 10808848 that we will use for <a name=10808848> to link TOC with html file
    preg_match_all('/<a href="\/node\/([0-9]+)"/', $pageContents, $ids);
    $ids = $ids[1];

    // get the IDs and titles of each page, for the XML TOC
    preg_match_all('#<a href=".*?(\w*)">(.*?)</a>#', $pageContents, $names_ids);

    // replace /node/123 links to economist.html#123
    $pageContents = preg_replace('/<a href="\/node\//s', '<a href="economist.html#', $pageContents);

    // regex for June 2010 and on (pattern repeated two more times below)
    $pageContentsTmp = preg_replace('/<span class="type"><em><\/em><\/span><h2><a href=".*?(\d*)">(.*?)<\/a>&nbsp;<\/h2>/',
                                 '<a href="economist.html#$1">$2</a><br>',
                                 $pageContents);

    if (strlen($pageContentsTmp) < 5) {
        // regex for pre-June 2010 (pattern repeated two more times below)
        $pageContentsTmp = preg_replace('/<span class="type"><em><\/em><\/span><h2><a href=".*?story_id=(\w*)">(.*?)<\/a> /',
             '<a href="economist.html#$1">$2</a><br>',
             $pageContents);
    }

    $pageContents = $pageContentsTmp;

    // Change formatting of article titles to fit on one line and all be an href so that you just have to click on link once
    $pageContentsTmp = preg_replace('/<span class="type"><em>[[:space:]]*(.*?)<\/em><\/span><h2><a href=".*?(\d*)">(.*?)<\/a>&nbsp;<\/h2>/',
                                 '<a href="economist.html#$2"><em>$1:</em> $3</a>',
                                 $pageContents);

    if (strlen($pageContentsTmp) < 5) {
        $pageContentsTmp = preg_replace('/<span class="type"><em>[[:space:]]*(.*?)<\/em><\/span><h2><a href=".*?(\w*)">(.*?)<\/a> /',
             '<a href="economist.html#$2"><em>$1:</em> $3</a>',
             $pageContents);
    }

    $pageContents = $pageContentsTmp;

    // handle letters section
    $pageContentsTmp = preg_replace('/<h2><a href=".*?(\d*)">(.*?)<\/a>&nbsp;<\/h2>/',
             '<a href="economist.html#$1">$2</a>',
             $pageContents);

    if (strlen($pageContentsTmp) < 5) {
        $pageContentsTmp = preg_replace('/<h2><a href=".*?(\w*)">(.*?)<\/a> /',
             '<a href="economist.html#$1">$2</a>',
             $pageContents);
    }

    // change h1s into h2s
    $pageContents = preg_replace('/<h1>(.*?)<\/h1>/', '<h2>$1</h2>', $pageContents);

    // get the names of each section and a count of their hrefs
    $sections    = explode('class="section"', $pageContents);
    $sectionList = array();

    foreach ($sections as $section) {
        if (preg_match('/<h4>([^<]+)<\/h4>/', $section, $matches) != 0) {
            $article_count = substr_count($section, 'href=');
            $sectionList[] = array($matches[1], $article_count);
            echo 'Sections: ' . $matches[1] .  " has $article_count articles\n";
        }
    }

    //
    // write XML TOC file
    //
    $ncxFile = $dateDirectory . '/toc.ncx';

    $fh = fopen($ncxFile, 'w');

    fwrite($fh, '<?xml version="1.0" encoding="UTF-8"?>
    <!DOCTYPE ncx PUBLIC "-//NISO//DTD ncx 2005-1//EN"
        "http://www.daisy.org/z3986/2005/ncx-2005-1.dtd">

    <ncx xmlns="http://www.daisy.org/z3986/2005/ncx/" version="2005-1" xml:lang="en-US">
    <head>
    <meta name="dtb:uid" content="uid"/>
    <meta name="dtb:depth" content="1"/>
    <meta name="dtb:totalPageCount" content="0"/>
    <meta name="dtb:maxPageNumber" content="0"/>
    </head>
    <docTitle><text>The Economist</text></docTitle>
    <docAuthor><text>The Economist</text></docAuthor>');

    fwrite($fh, "<navMap>\n");

    // pre-pend the TOC for convenience
    fwrite($fh, '<navPoint class="chapter" id="toc" playOrder="1"><navLabel><text>Table of Contents</text></navLabel><content src="mbp_toc.html"/>
    <navPoint class="article" id="tocint" playOrder="2"><navLabel><text>Table of Contents</text></navLabel><content src="mbp_toc.html"/></navPoint>
    </navPoint>'."\n\n");

    $playOrder  = 3;
    $articleIdx = 0;

    foreach ($sectionList as $section) {
        fprintf($fh,
                '<navPoint class="chapter" id="chapter%d" playOrder="%d"><navLabel><text>%s</text></navLabel><content src="economist.html#%s"/>%s',
                $playOrder,
                $playOrder,
                htmlentities($section[0]),
                $names_ids[1][$articleIdx],
                "\n");

        $playOrder++;

        for ($i = 0; $i < $section[1]; $i++) {
            fprintf($fh,
                    '%s<navPoint class="article" id="article%d" playOrder="%d"><navLabel><text>%s</text></navLabel><content src="economist.html#%s"/></navPoint>%s',
                    "\t",
                    $playOrder,
                    $playOrder,
                    htmlentities($names_ids[2][$articleIdx]),
                    $names_ids[1][$articleIdx],
                    "\n");

            $playOrder++;
            $articleIdx++;
        }

        fwrite($fh, "</navPoint>\n");
    }

    fwrite($fh, "</navMap></ncx>\n");
    fclose($fh);

    //
    // write TOC file
    //
    $tocFile = $dateDirectory . '/mbp_toc.html';

    $fh = fopen($tocFile, 'w');
    fwrite($fh,
    '<?xml version="1.0" encoding="UTF-16"?>
    <html xmlns:mbp="http://www.mobipocket.com/mbp"
          xmlns:xlink="http://www.w3.org/1999/xlink"
          xmlns:idx="http://www.mobipocket.com/idx">
    <head>
        <meta http-equiv="Content-Type" content="text/html; charset=utf-16" />
        <style type="text/css">
        h2 {
            font-weight: bold;
            margin-top: 15px;
            margin-bottom: 0px;
        }
        </style>
    </head>
    <body>
        <center>
        <h2>The Economist: ' . $dateLong . '</h2>
        </center>
        <p>');
    fwrite($fh, $pageContents);
    fwrite($fh, '</body></html>');
    fclose($fh);

    echo " done!\n";
}

/**
 * Creates an issue's HTML
 *
 * @param string $dateDirectory Issue's directory
 * @param bool   $withImages    Gather images as well
 * @param array  $urls          Article URLs
 * @param array  $ids           Article IDs
 *
 * @return void
 *
 */
function createHTML($dateDirectory, $withImages, $urls, $ids)
{
    $indexFile  = $dateDirectory . '/economist.html';
    $indexFileH = fopen($indexFile, 'w');

    fwrite($indexFileH, '<head><meta http-equiv="content-type" content="text/html; charset=UTF-8"/></head>');

    fwrite($indexFileH, '<body>');

    // write all urls
    echo "Creating articles file: economist.html:\n";
    for ($i = 0; $i < count($urls); $i++) {

        // filter out some URLs
        if (   stristr($urls[$i], '/comments') !== false
            || stristr($urls[$i], '/subscribe') !== false
            || stristr($urls[$i], '/covers') !== false) {
            continue;
        }

        $url = (substr($urls[$i], 0, 5) === 'http:') ? $urls[$i] : 'http://www.economist.com/' . $urls[$i];
        $url = str_replace('.com//', '.com/', $url);

        echo "\t$url\n";

        // download article
        $article   = economistGetUrl($url);
        $articleId = $ids[$i];

        if ($article === '') {
            echo "\t\tERROR in downloading!\n";
        }

        $startPos = strpos($article, '<div id="ec-article-body"');
        $endPos   = strpos($article, '<!-- /#ec-article-body -->');

        $fh = fopen('/tmp/article', 'w');
        fwrite($fh, $article);
        fclose($fh);

        if ($startPos > 0 && $endPos > 0) {
            // Parse regular content
            $content = substr($article, $startPos, ($endPos - $startPos));

            //
            // 2011-07-26:
            // An update to economist.com changed
            //    <div id="ec-article-body">
            // to <div id="ec-article-body" class="clearfix">
            // To be more resilient to changes like this, only match the start of the DIV, then
            //   eat characters up to the first '>'
            //
            $closeTag = strpos($content, '>');
            if ($closeTag > 0) {
                $content = substr($content, $closeTag + 1);
            }

            $headline = '(unknown headline)';
            if (preg_match('#<div class=.headline.>([^\<]+)#', $content, $matches) > 0) {
                $headline = $matches[1];
            } else if (preg_match('#<h[1-5] class=.headline.>([^\<]+)#', $content, $matches) > 0) {
                $headline = $matches[1];
            }

            // add TOC link to article
            $content = preg_replace('/class="headline"/', 'class="headline" id="' . $articleId . '"', $content);

            echo "\t\t=> " . $headline ."\n";

        } else {
            // Parse other content (e.g. KAL's cartoon)
            $startPos = strpos($article, '<div id="content">');
            $endPos   = strpos($article, '<div id="add-comment-container">');
            $content  = substr($article, $startPos, ($endPos - $startPos));

            $startPos = strpos($content, '<h1>');
            $content  = substr($content, $startPos);
            $endPos   = strpos($content, '</div>');
            $content  = substr($content, 0, $endPos) . '</div>';

            // add TOC link to article
            $content = preg_replace('/<h1>/', '<h1 id="' . $articleId . '">', $content);
        }

        // get rid of this: <p class="info">Feb 28th 2008<br>From <em>The Economist</em> print edition</p>
        $content = preg_replace('/<p class="info">(.*?)<\/p>/', '', $content);

        // get rid of banner ads
        $content = preg_replace('/<div class="banner">([[:space:]])*<div align="center">(.*?)<\/div>([[:space:]])*<\/div>/s', '', $content);

        // fix topics links
        $content = preg_replace('/href="\/topics\//s', 'href="http://www.economist.com/topics/', $content);

        // allow inside-links
        $content = preg_replace('/<a href="displaystory.cfm\?story_id=/s', '<a href="#', $content);
        $content = preg_replace('/<a href="\/node\//s', '<a href="#', $content);
        $content = preg_replace('/<a href="http:\/\/www.economist.com\/node\//s', '<a href="#', $content);

        // add extra line above section headers
        $content = preg_replace('#</a><br \/>#', '</a><br><br>', $content);

        if ($withImages) {
            $content = handleImages($content, $dateDirectory);
        }

        if ($content === '') {
            echo "\t\tERROR in processing!\n";
        }

        fwrite($indexFileH, "\n\n");
        fwrite($indexFileH, $content);

        //add page break to bottom of each article
        fwrite($indexFileH, "\n<mbp:pagebreak/>");

    }

    fwrite($indexFileH, '</body>');
    fclose($indexFileH);
}

/**
 * Grabs an article's images
 *
 * @param string $content       Article's contents
 * @param string $dateDirectory Issue's directory
 *
 * @return string Content with images
 *
 */
function handleImages($content, $dateDirectory)
{
    preg_match_all('/<img src="(.*?)"/', $content, $matches);
    $imgUrls = $matches[1];

    // space added after ", to handle this case where it was grabbing " in a name
    // $content = '<div class="content-image-float" style="width: 200px;">
    // <span>Illustration by Petra Stefankova</span>
    // <img src="http://media.economist.com/images/20080308/D1008TQ10.jpg" alt=" " title='' width="200" height="292"></div><a name="analyse_this">';
    preg_match_all('/<img src=".*\/(.*?)" /', $content, $matches);
    $imgNames = $matches[1];

    // download images
    for ($j = 0; $j < count($imgUrls); $j++) {

        if (substr($imgUrls[$j], 0, 1) === '/') {
            $imgUrls[$j] = 'http://www.economist.com' . $imgUrls[$j];
        }

        $imgsrc  = economistGetUrl($imgUrls[$j]);
        $imgFile = $dateDirectory. '/' . $imgNames[$j];

        // write file
        $imgFh = fopen($imgFile, 'w');
        fwrite($imgFh, $imgsrc);
        fclose($imgFh);
    }

    // reformat img html: change image url, center, remove picture taker info from the top, remove div and width and height
    $content = preg_replace('/<div class="content-image.*?<img src="[^"]*?\/([^\/]+)".*?>(.*?)<\/div>/', '<center><img src="$1">$2</center>', $content);

    // change format of caption to be italics
    $content = preg_replace('/<span class="caption">(.*?)<\/span>/', '<br><em>$1</em>', $content);

    // keep img URLs just as their names without a path
    $content = preg_replace('/<img src="[^"]*?\/([^\/]+)".*?>/', '<img src="$1" />', $content);

    return $content;
}

/**
 * Emails the Economist to a Kindle device
 *
 * @param string $fromEmail FROM: email
 * @param string $toEmail   TO: email (@kindle.com)
 * @param string $mobiFile  .mobi file to send
 * @param string $fileName  .mobi file name (only)
 *
 * @return void
 *
 */
function emailEconomist($fromEmail, $toEmail, $mobiFile, $fileName)
{
    echo 'Emailing... ';
    // Generate a boundary string
    $semi_rand = md5(time());

    $mime_boundary = "==Multipart_Boundary_x{$semi_rand}x";

    // Add the headers for a file attachment
    $headers  = "From: $fromEmail";
    $headers .= "\nMIME-Version: 1.0\n" .
                "Content-Type: multipart/mixed;\n" .
                " boundary=\"{$mime_boundary}\"";

    // Add a multipart boundary above the plain message
    $message = "This is a multi-part message in MIME format.\n\n" .
               "--{$mime_boundary}\n" .
               "Content-Type: text/plain; charset=\"iso-8859-1\"\n" .
               "Content-Transfer-Encoding: 7bit\n\n" .
               "Economist\n\n";

    // Base64 encode the file data
    $fh = fopen($mobiFile, 'rb');

    $dataBase64 = chunk_split(base64_encode(fread($fh, filesize($mobiFile))));
    fclose($fh);

    // Add file attachment to the message
    $message .= "--{$mime_boundary}\n" .
                "Content-Type: application/binary;\n" .
                " name=\"{$fileName}\"\n" .
                "Content-Disposition: attachment;\n" .
                " filename=\"{$fileName}\"\n" .
                "Content-Transfer-Encoding: base64\n\n" .
                $dataBase64 . "\n\n" .
                "--{$mime_boundary}--\n";

    mail($toEmail, 'Kindle', $message, $headers);
    echo " done!\n";
}

/**
 * Get the cover for a specific issue
 *
 * @param string $dateDirectory Date directory
 * @param string $date          Date
 *
 * @return String filename of the cover image
 *
 */
function economistGetCover($dateDirectory, $date)
{
    $year  = substr($date, 0, 4);
    $month = substr($date, 4, 2);
    $day   = substr($date, 6, 2);

    // this is ugly, but I don't trust math today
    if ($month <= 3) {
        $quarter = 1;
    } else if ($month <= 6) {
        $quarter = 2;
    } else if ($month <= 9) {
        $quarter = 3;
    } else {
        $quarter = 4;
    }

    $coverIdx = economistGetUrl("http://www.economist.com/printedition/cover_index.cfm?edition=US&year=$year&quarter=$quarter&submit=Go");

    if (preg_match('#img src="([^"]+/'.$date.'issue[^"]+)"#', $coverIdx, $matches) == 0) {
        return '';
    }

    $imgsrc  = economistGetUrl('http://www.economist.com/'.$matches[1]);
    $imgFile = $dateDirectory . '/cover.jpg';
    $imgFh   = fopen($imgFile, 'w');
    fwrite($imgFh, $imgsrc);
    fclose($imgFh);

    return 'cover.jpg';
}

/**
 * Log into the economist.com
 *
 * @return void
 *
 */
function economistLogin()
{
    $ch = curl_init();

    // connect timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    // get response as string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // HTTP POST
    curl_setopt($ch, CURLOPT_POST, 1);
    curl_setopt($ch,
                CURLOPT_POSTFIELDS,
                array(
                 'cms_object_id' => 'Y',
                 'email_address' => $GLOBALS['loginEmail'],
                 'logging_in'    => 'Y',
                 'paybarrier'    => '1',
                 'pword'         => $GLOBALS['loginPass'],
                 'returnURL'     => '/printedition/index.cfm?source=login_payBarrier',
                 'save_password' => 'Y',
                ));

    // URL
    curl_setopt($ch, CURLOPT_URL, 'https://www.economist.com/printedition/index.cfm?source=login_payBarrier');

    // cookie output file
    curl_setopt($ch, CURLOPT_COOKIEJAR, $GLOBALS['cookieJarFile']);

    // go
    curl_exec($ch);

    // close
    curl_close($ch);
}

/**
 * Gets an economist.com URL
 *
 * @param string $url URL to get
 *
 * @return string HTML of URL
 *
 */
function economistGetUrl($url)
{
    echo "Downloading $url ...";
    $ch = curl_init();

    // connect timeout
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);

    // get response as string
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);

    // follow redirects
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);

    // send cookies
    curl_setopt($ch, CURLOPT_COOKIEFILE, $GLOBALS['cookieJarFile']);

    // URL
    curl_setopt($ch, CURLOPT_URL, $url);

    $html = curl_exec($ch);

    curl_close($ch);

    echo " done!\n";

    return $html;
}

?>
