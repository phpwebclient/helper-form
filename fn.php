<?php

if (!function_exists('mime_content_type')) {
    function mime_content_type($filename)
    {
        if (function_exists('finfo_open')) {
            /** @noinspection PhpComposerExtensionStubsInspection */
            $info = finfo_open(FILEINFO_MIME);
            /** @noinspection PhpComposerExtensionStubsInspection */
            $mime = finfo_file($info, $filename);
            /** @noinspection PhpComposerExtensionStubsInspection */
            finfo_close($info);
            return $mime;
        }
        $types = [
            'txt' => 'text/plain',
            'htm' => 'text/html',
            'html' => 'text/html',
            'php' => 'text/html',
            'css' => 'text/css',
            'js' => 'application/javascript',
            'json' => 'application/json',
            'xml' => 'application/xml',
            'swf' => 'application/x-shockwave-flash',
            'flv' => 'video/x-flv',

            // images
            'png' => 'image/png',
            'jpe' => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'jpg' => 'image/jpeg',
            'gif' => 'image/gif',
            'bmp' => 'image/bmp',
            'ico' => 'image/vnd.microsoft.icon',
            'tiff' => 'image/tiff',
            'tif' => 'image/tiff',
            'svg' => 'image/svg+xml',
            'svgz' => 'image/svg+xml',

            // archives
            'zip' => 'application/zip',
            'rar' => 'application/x-rar-compressed',
            'exe' => 'application/x-msdownload',
            'msi' => 'application/x-msdownload',
            'cab' => 'application/vnd.ms-cab-compressed',

            // audio/video
            'mp3' => 'audio/mpeg',
            'qt' => 'video/quicktime',
            'mov' => 'video/quicktime',

            // adobe
            'pdf' => 'application/pdf',
            'psd' => 'image/vnd.adobe.photoshop',
            'ai' => 'application/postscript',
            'eps' => 'application/postscript',
            'ps' => 'application/postscript',

            // ms office
            'doc' => 'application/msword',
            'rtf' => 'application/rtf',
            'xls' => 'application/vnd.ms-excel',
            'ppt' => 'application/vnd.ms-powerpoint',

            // open office
            'odt' => 'application/vnd.oasis.opendocument.text',
            'ods' => 'application/vnd.oasis.opendocument.spreadsheet',
        ];
        $arr = explode('.', $filename);
        $ext = strtolower(array_pop($arr));
        if (array_key_exists($ext, $types)) {
            return $types[$ext];
        }
        return 'application/octet-stream';
    }
}

function mime(string $file)
{
    if (file_exists($file)) {
        return mime_content_type($file);
    }
    if (function_exists('finfo_open')) {
        /** @noinspection PhpComposerExtensionStubsInspection */
        $info = finfo_open(FILEINFO_MIME);
        /** @noinspection PhpComposerExtensionStubsInspection */
        $mime = finfo_buffer($info, $file);
        /** @noinspection PhpComposerExtensionStubsInspection */
        finfo_close($info);
        return $mime;
    }
    return 'application/octet-stream';
}
