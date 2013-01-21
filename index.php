<?php
function output ($string) {
    //print(gzencode($string));
    print(($string));
}
header_remove('Content-Type');
$url=$_SERVER['REQUEST_URI'];
if ($url!='/') {
    $headers=(get_headers($url, 1));
    $contentType=$headers['Content-Type'];
    if (is_array($contentType)) {
        $contentType=$contentType[0];
    }
    $contentType=explode(';',$contentType);
    header('Content-Type: '.$contentType[0]);
    ini_set('user_agent', $_SERVER['HTTP_USER_AGENT']);
    //Cache
    header('Cache-Control: max-age=2678400');
    header('Vary: Accept-Encoding');
    if(explode('/', $contentType[0])[0]=='image') {
        if($contentType[0]=='image/jpeg') {
            $image = imagecreatefromjpeg($url);
        } else if($contentType[0]=='image/gif') {
            $image = imagecreatefromgif($url);
        } else if($contentType[0]=='image/png') {
            $image = imagecreatefrompng($url);
        }
        imagejpeg($image, null, 50);
    } else {
        //header('Content-Encoding: gzip');
        $content=file_get_contents($_SERVER['REQUEST_URI']);
        header('ETag: '.md5($content));
        if($contentType[0]=='text/html') {
            $dom=new DOMDocument();
            $dom->preserveWhiteSpace=false;
            $dom->formatOutput=false;
            $dom->strictErrorChecking=false;
            if (isset($contentType[1])) {
                $dom->encoding = explode('=', $contentType[1])[1];
            }
            @$dom->loadHTML($content);
            $styles=$dom->getElementsByTagName('script');
            for ($i=0;$i<$styles->length;$i++) {
                
            }
            output($dom->saveHTML());
        } else if ($contentType[0]=='text/javascript' || $contentType[0]=='text/css') {
            output(preg_replace('/\v/', '', $content));
        } else {
            output($content);
        }
    }
}
?>
