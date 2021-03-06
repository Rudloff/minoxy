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
//Includes
@require_once 'HTML/CSS.php';
//Config
define('COMPRESS_IMAGES', true);
//define('GZIP', true);
define('FAKE_UA', true);
define('VERSION', 0.1);

$url=parse_url($_GET['url']);

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
 * Remove unwanted properties in CSS code
 * 
 * @param string $css CSS code
 * @param string $tag Tag the style attribute is on
 * 
 * @return string
 * */
function cleanCSS($css, $tag=null, $dom=null)
{
    $properties=array('position', 'display', 'float', 'max-width', 'min-width',
    'top', 'left', 'right', 'bottom', 'min-height', 'max-height',
    'border-collapse', 'background-image');
    if (isset($tag)) {
        $css=$tag.' { '.$css.' }';
    }
    
    $CSSObject = new HTML_CSS();
    $CSSObject->setCharset('UTF-8');
    @$CSSObject->parseString($css);
    foreach ($properties as $property) {
        $results=$CSSObject->grepStyle('/.*/', '/^'.$property.'$/');
        foreach ($results as $name=>$values) {
            unset($CSSObject->_css[$name][$property]);
            foreach ($CSSObject->_groups as $key=>$group) {
                if (in_array($name, $group)) {
                    $CSSObject->removeGroupSelector(substr($key, 2), $name);
                }
            }
            foreach ($CSSObject->_groups as $key=>$group) {
                if (empty($group)) {
                    $CSSObject->unsetGroup(substr($key, 2));
                }
            }
        }
    }
    //Remove pseudo-classes
    $pseudoClasses=array('hover', 'last-child', 'after', 'first-letter');  
    foreach ($pseudoClasses as $pseudo) {
        $results=$CSSObject->grepStyle('/:'.$pseudo.'/');
        foreach ($results as $name=>$values) {
            unset($CSSObject->_css[$name]);
            foreach ($CSSObject->_groups as $key=>$group) {
                if (in_array($name, $group)) {
                    $CSSObject->removeGroupSelector(substr($key, 2), $name);
                }
            }
            foreach ($CSSObject->_groups as $key=>$group) {
                if (empty($group)) {
                    $CSSObject->unsetGroup(substr($key, 2));
                }
            }
        }
    }
    if (isset($tag)) {
        $head=$dom->getElementsByTagName('head')->item(0);
        $style=$dom->createElement('style');
        $style->setAttribute('type', 'text/css');
        $style->nodeValue=$CSSObject->toString();
        $head->appendChild($style);
        return;
    } else {
        $css=$CSSObject->toString();
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
 * Replace URLs in DOM to use the proxy
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function replaceURLs($dom)
{
    $url=parse_url($_GET['url']);
    $finder = new DomXPath($dom);
    $attributes=array('href', 'src');
    foreach ($attributes as $attribute) {
        $items = $finder->query('//*/@'.$attribute);
        for ($i=0;$i<$items->length;$i++) {
            $ressource=parse_url(
                $items->item($i)->ownerElement->getAttribute($attribute)
            );
            if (!isset($ressource['scheme']) || $ressource['scheme']!='mailto') {
                if (!isset($ressource['scheme'])) {
                    $ressource['scheme']=$url['scheme'];
                }
                if ($ressource['scheme']=='http' || $ressource['scheme']=='https') {
                    if (!isset($ressource['host'])) {
                        $ressource['host']=$url['host'];
                    }
                    if (!isset($ressource['path'])) {
                        $ressource['path']='/';
                    }
                    if (isset($ressource['query'])) {
                        $query='?'.$ressource['query'];
                    } else {
                        $query='';
                    }
                    if (isset($ressource['fragment'])) {
                        $query.='#'.$ressource['fragment'];
                    }
                    if (substr($ressource['path'], 0, 1)!='/') {
                        $ressource['path']='/'.$ressource['path'];
                    }
                    if (substr($ressource['path'], 0, 2)=='//') {
                        //Strange URLs on Wikipedia
                        $items->item($i)->ownerElement->setAttribute(
                            $attribute, 'http://'.$_SERVER['HTTP_HOST'].
                            $_SERVER['SCRIPT_NAME'].'?url='.urlencode(
                                'http://'.substr($ressource['path'], 2).$query
                            )
                        );
                    } else {
                        $items->item($i)->ownerElement->setAttribute(
                            $attribute, 'http://'.$_SERVER['HTTP_HOST'].
                            $_SERVER['SCRIPT_NAME'].'?url='.$ressource['scheme'].
                            '://'.$ressource['host'].$ressource['path'].$query
                        );
                    }
                }
            }
        }
    }
}

function getImgSize($dom)
{
    $url=parse_url($_GET['url']);
    $images=$dom->getElementsByTagName('img');
    for ($i=0;$i<$images->length;$i++) {
        $height=$images->item($i)->getAttribute('height');
        $width=$images->item($i)->getAttribute('width');
        if (empty($height) || empty($width)) {
            $image=parse_url($images->item($i)->getAttribute('src'));
            if(!isset($image['host'])) {
                $image['host']=$url['host'];
            }
            if (substr($image['path'], 0, 1)!='/') {
                $image['path']='/'.$image['path'];
            }
            $infos=(getimagesize('http://'.$image['host'].$image['path']));
            $images->item($i)->setAttribute('height', $infos[1]);
            $images->item($i)->setAttribute('width', $infos[0]);
        }
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
 * Remove HTML5 input types from the DOM
 * 
 * @param DOMDocument $dom DOM
 * 
 * @return void
 * */
function removeHTML5InputTypes($dom)
{
    $finder = new DomXPath($dom);
    $types=array('search', 'number');
    foreach ($types as $type) {
        $items = $finder->query('//*[@type="'.$type.'"]');
        for ($i=0;$i<$items->length;$i++) {
            $items->item($i)->removeAttribute('type');
        }
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
        $meta->setAttribute('content', 'application/xhtml+xml; charset=UTF-8');
        $meta->removeAttribute('charset');
    }
    $meta = $finder->query('//meta[@http-equiv="Content-Type"]');
    $meta=$meta->item(0);
    if (isset($meta)) {
        $meta->setAttribute('content', 'application/xhtml+xml; charset=UTF-8');
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
    $attributes=array('itemprop', 'itemscope', 'itemtype',
    'border', 'colspan', 'dir', 'role', 'cellpadding', 'cellspacing',
    'srcset', 'align', 'target', 'style');
    foreach ($attributes as $attribute) {
        removeAttribute($dom, $attribute);
    }
    removeHTML5Data($dom);
    removeHTML5InputTypes($dom);
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
        $class=uniqid();
        cleanCSS($element->getAttribute('style'), '.'.$class, $dom);
        $element->setAttribute('class', $element->getAttribute('class').$class);
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
    $tags=array('header', 'footer', 'nav', 'section', 'tr', 'table', 'td', 'th');
    foreach ($tags as $tag) {
        $elements=$dom->getElementsByTagName($tag);
        for ($i=$elements->length-1; $i>=0; $i--) {
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

/**
 * Convert text to UTF-8
 * 
 * @param string $content Text to convert
 * 
 * @return string
 * */
function convertToUTF8($content)
{
    $oldencoding = mb_detect_encoding($content, 'auto');
    return mb_convert_encoding($content, 'HTML-ENTITIES', $oldencoding);
}

header_remove('Content-Type');
$url=$_SERVER['REQUEST_URI'];
$urlInfo=parse_url($url);
if (!isset($urlInfo['host'])) {
    if (isset($_GET['url'])) {
        $url=$_GET['url'];
    } else {
        die(
            'Please specify an url (?url=example.com) '.
            'or use me in proxy mode !'.PHP_EOL
        );
    }
}
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
$basicType=explode('/', $contentType[0]);
$basicType=$basicType[0];
if (defined('COMPRESS_IMAGES') && $basicType=='image') {
    if ($contentType[0]=='image/jpeg') {
        $image = imagecreatefromjpeg($url);
    } else if ($contentType[0]=='image/gif') {
        $image = imagecreatefromgif($url);
    } else if ($contentType[0]=='image/png') {
        $image = imagecreatefrompng($url);
    } else if ($contentType[0]=='image/svg+xml') {
        //$image = imagecreatefromsvg($url);
    }
    header('Content-Type: image/jpeg');
    imagejpeg($image, null, 50);
} else {
    if (defined('GZIP')) {
        header('Content-Encoding: gzip');
    }
    if ($content=file_get_contents($url)) {
        $etag='"'.md5($content).'"';
        header('ETag: '.$etag);
        if (isset($_SERVER['HTTP_IF_NONE_MATCH']) 
            && $_SERVER['HTTP_IF_NONE_MATCH']==$etag
        ) {
            header("HTTP/1.1 304 Not Modified");
        } else {
            if ($contentType[0]=='text/html') {
                header('Content-Type: application/xhtml+xml; charset=UTF-8');
                libxml_use_internal_errors(true);
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
                $dom->encoding='utf-8';
                $olddom=new DOMDocument();
                $olddom->XMLencoding='utf-8';
                $olddom->loadHTML(convertToUTF8($content));
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
                getImgSize($dom);
                replaceURLs($dom);
                output($dom->saveXML());
            } else if ($contentType[0]=='text/javascript'
                || $contentType[0]=='text/css'
            ) {
                if ($contentType[0]=='text/css') {
                    $content=cleanCSS($content);
                }
                header('Content-Type: text/css; charset=UTF-8');
                convertToUTF8($content);
                output(preg_replace('/\v/', '', $content));
            } else {
                output($content);
            }
        }
    } else {
        header('HTTP/1.0 404 Not Found');
        die("Can't find page !".PHP_EOL);
    }
}
?>
