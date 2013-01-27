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
    $properties=array('position', 'display', 'float', 'max-width');
    foreach ($properties as $property) {
        $css=removeCSSProp($property, $css);
    }
    return $css;
}

/**
 * Remove a specifice attribute from every element in the DOM
 * 
 * @param DOMDocument $dom       DOM
 * @param string      $attribute Attribute
 * 
 * @return void
 * */
function removeAttribute($dom, $attribute)
{
    $finder = new DomXPath($dom);
    $items = $finder->query('//*/@'.$attribute);
    for ($i=0;$i<$items->length;$i++) {
        $items->item($i)->ownerElement->removeAttribute($attribute);
    }
}

/**
 * Remove HTML5 data- attributes from the DOM
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function removeHTML5Data($dom)
{
    $finder = new DomXPath($dom);
    $items = $finder->query('//*/@*[contains(name(), "data")]');
    for ($i=0;$i<$items->length;$i++) {
        $items->item($i)->ownerElement->removeAttribute($items->item($i)->name);
    }
}

/**
 * Replace HTML5 charset declaration with the older method
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function cleanMetaCharset($dom)
{
    global $encoding;
    $finder = new DomXPath($dom);
    $meta = $finder->query('//meta[@charset]');
    $meta=$meta->item(0);
    if (isset($meta)) {
        $meta->setAttribute('http-equiv', 'Content-Type'); 
        $meta->setAttribute('content', 'text/html; charset=UTF-8');
        $meta->removeAttribute('charset');
    }
}

/**
 * Clean unwanted attributes in order to comply with XHTML Basic 1.1
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function cleanAttributes($dom)
{
    $attributes=array('itemprop', 'itemscope', 'itemtype');
    foreach ($attributes as $attribute) {
        removeAttribute($dom, $attribute);
    }
    removeHTML5Data($dom);
    cleanMetaCharset($dom);
}

/**
 * Remove unwanted properties from style attributes
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function cleanStyleAttributes($dom)
{
    $finder = new DomXPath($dom);
    $styles = $finder->query('//*[@style]');
    for ($i=0;$i<$styles->length;$i++) {
        $element=$styles->item($i);
        $element->setAttribute('style', cleanCSS($element->getAttribute('style')));
    }
}

/**
 * Remove every script tag from the DOM
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function removeScripts($dom)
{
    $finder = new DomXPath($dom);
    $scripts = $finder->query('//script');
    for ($i=0;$i<$scripts->length;$i++) {
        $script=$scripts->item($i);
        $script->parentNode->removeChild($script);
    }
}


/**
 * Replace HTML5 semantic blocks with standard divs
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function replaceHTML5Blocks($dom)
{
    $tags=array('header', 'footer', 'nav', 'section');
    foreach ($tags as $tag) {
        $elements=$dom->getElementsByTagName($tag);
        for ($i=0;$i<$elements->length;$i++) {
            $oldtag=$elements->item($i);
            $newtag=$dom->createElement('div');
            foreach ($oldtag->attributes as $attribute) {
                $newtag->setAttribute($attribute->name, $attribute->value);
                
            }
            foreach ($oldtag->childNodes as $child) {
                $newtag->appendChild($child->cloneNode(true));
            }
            $oldtag->parentNode->replaceChild($newtag, $oldtag);
        }
    }
}

header_remove('Content-Type');
$url=$_SERVER['REQUEST_URI'];
//$url='http://rudloff.pro';
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
            header('Content-Type: application/xhtml+xml');
            $domimpl=new DOMImplementation();
            $dom = $domimpl->createDocument(
                null, 'html',
                $domimpl->createDocumentType(
                    'html',
                    '-//W3C//DTD XHTML Basic 1.1//EN',
                    'http://www.w3.org/TR/xhtml-basic/xhtml-basic11.dtd'
                )
            );
            $dom->preserveWhiteSpace=false;
            $dom->formatOutput=false;
            $dom->strictErrorChecking=false;
            $dom->encoding = 'UTF-8';
            $olddom=new DOMDocument();
            $oldencoding = mb_detect_encoding($content, 'auto');
            @$olddom->loadHTML(mb_convert_encoding($content, 'UTF-8', $oldencoding));
            $dom->removeChild($dom->documentElement);
            $newHTML = $dom->importNode($olddom->documentElement, true);
            $dom->appendChild($newHTML);
            
            $finder = new DOMXPath($dom);
            $xmlns=$finder->evaluate('string(@xmlns)');
            if (empty($xmlns)) {
                $dom->documentElement
                    ->setAttribute('xmlns', 'http://www.w3.org/1999/xhtml');
            }
            
            removeScripts($dom);
            $styles=$dom->getElementsByTagName('style');
            cleanStyleAttributes($dom);
            for ($i=0;$i<$styles->length;$i++) {
                $styles->item($i)->setAttribute('type', 'text/css');
                $styles->item($i)
                    ->nodeValue=cleanCSS($styles->item($i)->nodeValue);
            }
            cleanAttributes($dom);
            replaceHTML5Blocks($dom);
            output($dom->saveXML());
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
