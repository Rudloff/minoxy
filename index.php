<?php
/**
 * Minoxy
 * Proxy to adapt web pages to mobile browsing
 * 
 * PHP version 5.4.6
 * 
 * @category Proxy
 * @package  Minoxy
 * @author   Pierre Rudloff <contact@rudloff.pro>
 * @license  AGPL http://www.gnu.org/licenses/agpl-3.0.html
 * @link     https://svn.strasweb.fr/listing.php?
 *           repname=Pierre+Rudloff&path=%2Fproxy%2F
 * */
//Config
//define('COMPRESS_IMAGES', true);
//define('GZIP', true);
define('FAKE_UA', true);
define('VERSION', 0.1);

/**
 * Compress a string and outputs it to the browser
 * 
 * @param string $string The string to output
 * 
 * @return void
 * */
function output ($string)
{
    if (defined('GZIP')) {
        print(gzencode($string));
    } else {
        print(($string));
    }
}

/**
 * Remove a specific CSS property from CSS code
 * 
 * @param string $prop Property to remove
 * @param string $css  CSS code
 * 
 * @return string Modifie CSS code
 * */
function removeCSSProp($prop, $css)
{
    //We need a real CSS parser.
    return preg_replace('/\s*'.$prop.':\s*\w*;/', '', $css);
}

/**
 * Remove unwanted properties in CSS code
 * 
 * @param string $css CSS code
 * 
 * @return string
 * */
function cleanCSS($css)
{
    $css=removeCSSProp('float', $css);
    $css=removeCSSProp('width', $css);
    $css=removeCSSProp('height', $css);
    $css=removeCSSProp('position', $css);
    $css=removeCSSProp('margin\-\w+', $css);
    $css=removeCSSProp('background-image', $css);
    //We should target only the image in background.
    //$css=removeCSSProp('background', $css);
    return $css;
}

header_remove('Content-Type');
$url=$_SERVER['REQUEST_URI'];
if ($url!='/') {
    $headers=(get_headers($url, 1));
    $contentType=$headers['Content-Type'];
    if (is_array($contentType)) {
        $contentType=$contentType[0];
    }
    $contentType=explode(';', $contentType);
    header('Content-Type: '.$contentType[0]);
    $system=posix_uname();
    if (defined('FAKE_UA')) {
        $useragent='Minoxy/'.VERSION.' ('.$system['sysname'].
        ' '.$system['machine'].';)';
        if ($system['sysname']!='Android') {
            $useragent.=' like Android';
        }
    } else {
        $useragent=$_SERVER['HTTP_USER_AGENT'];
    }
    ini_set('user_agent', $useragent);
    //Cache
    header('Cache-Control: max-age=2678400');
    header('Vary: Accept-Encoding');
    if (defined('COMPRESS_IMAGES') && explode('/', $contentType[0])[0]=='image') {
        if ($contentType[0]=='image/jpeg') {
            $image = imagecreatefromjpeg($url);
        } else if ($contentType[0]=='image/gif') {
            $image = imagecreatefromgif($url);
        } else if ($contentType[0]=='image/png') {
            $image = imagecreatefrompng($url);
        }
        imagejpeg($image, null, 50);
    } else {
        if (defined('GZIP')) {
            header('Content-Encoding: gzip');
        }
        $content=file_get_contents($url);
        header('ETag: '.md5($content));
        if ($contentType[0]=='text/html') {
            $dom=new DOMDocument();
            $dom->preserveWhiteSpace=false;
            $dom->formatOutput=false;
            $dom->strictErrorChecking=false;
            if (isset($contentType[1])) {
                $dom->encoding = explode('=', $contentType[1])[1];
            }
            @$dom->loadHTML($content);
            $scripts=$dom->getElementsByTagName('script');
            for ($i=0;$i<$scripts->length;$i++) {
                $scripts->item($i)->removeAttribute('src');
                $scripts->item($i)->nodeValue='';
            }
            $styles=$dom->getElementsByTagName('style');
            for ($i=0;$i<$styles->length;$i++) {
                $styles->item($i)
                    ->nodeValue=cleanCSS($styles->item($i)->nodeValue);
            }
            output($dom->saveHTML());
        } else if ($contentType[0]=='text/javascript'
            || $contentType[0]=='text/css'
        ) {
            if ($contentType[0]=='text/css') {
                $content=cleanCSS($content);
            }
            output(preg_replace('/\v/', '', $content));
        } else {
            output($content);
        }
    }
}
?>
