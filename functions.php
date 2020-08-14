<?php

// https://www.php.net/manual/en/function.imagecreatefromgif.php#104473
function isAnimatedGIF($gif) {
    if(!($fh = @fopen($gif, 'rb')))
        return false;

    $count = 0;
    //an animated gif contains multiple "frames", with each frame having a
    //header made up of:
    // * a static 4-byte sequence (\x00\x21\xF9\x04)
    // * 4 variable bytes
    // * a static 2-byte sequence (\x00\x2C) (some variants may use \x00\x21 ?)

    // We read through the file til we reach the end of the file, or we've found
    // at least 2 frame headers
    while(!feof($fh) && $count < 2) {
        $chunk = fread($fh, 1024 * 100); //read 100kb at a time
        $count += preg_match_all('#\x00\x21\xF9\x04.{4}\x00(\x2C|\x21)#s', $chunk, $matches);
    }

    fclose($fh);

    return $count > 1;
}

function convert_gif2mp4($content)
{
    $images = array();

    $upload_info = wp_upload_dir();
    $upload_dir = $upload_info['basedir'];
    $upload_url = $upload_info['baseurl'];

    $pattern = '#<img[^>]*src=[\'"]?(.+\.gif)[\'"]?[^>]*>#i';

    preg_match_all($pattern, $content, $matches);

    $count = count($matches[1]);
    $urls  = array();

    if ( $count > 0 ) {
        for ( $i=0; $i<$count; $i++ ) {
            $url  = $matches[1][$i];
            $url2 = preg_replace('#^https?:#i', '', $url);

            if(in_array($url2, $urls))
                continue;

            $urls[] = $url;

            // 로컬 파일인지 체크
            if(strpos( $url, $upload_url ) === false)
                continue;

            // 이미지 경로 설정
            $rel_path = str_replace( $upload_url, '', $url);
            $img_file = $upload_dir . $rel_path;

            // gif 파일인지 체크
            if( !is_file($img_file))
                continue;

            $size = @getimagesize($img_file);
            if($size[2] != 1)
                continue;

            // 애니메이션 gif 체크
            if (!isAnimatedGIF($img_file))
                continue;

            // mp4 파일 생성
            $pinfo = pathinfo($img_file);

            $mp4 = $pinfo['dirname'].'/'.$pinfo['filename'].'.mp4';
            $webm = $pinfo['dirname'].'/'.$pinfo['filename'].'.webm';
            $poster = $pinfo['dirname'].'/poster_'.$pinfo['filename'].'.jpg';

            if (is_writable($pinfo['dirname']) && !is_file($mp4)) {
                try {
                    $poster = $pinfo['dirname'].'/poster_'.$pinfo['filename'].'.jpg';
                    $image = @imagecreatefromgif($img_file);

                    imagejpeg($image, $poster, 90);

                    @exec('ffmpeg -i '.escapeshellcmd(preg_replace('/[^0-9A-Za-z_\-\.\\\\\/]/i', '', $img_file)).' -vf "scale=trunc(iw/2)*2:trunc(ih/2)*2" -c:v libx264 -pix_fmt yuv420p -movflags +faststart  '.escapeshellcmd(preg_replace('/[^0-9A-Za-z_\-\.\\\\\/]/i', '', $mp4)));

                    @exec('ffmpeg -i '.escapeshellcmd(preg_replace('/[^0-9A-Za-z_\-\.\\\\\/]/i', '', $img_file)).' -c vp9 -b:v 0 -crf 41  '.escapeshellcmd(preg_replace('/[^0-9A-Za-z_\-\.\\\\\/]/i', '', $webm)));
                } catch(Exception $e) {
                    continue;
                }
            }

            if (is_file($mp4)) {
                $video = '<video poster="'.str_replace($upload_dir, $upload_url, $poster).'" autoplay="autoplay" loop="loop" preload="auto" playsinline webkit-playsinline muted>';
                if (is_file($webm))
                    $video .= '<source src="'.str_replace($upload_dir, $upload_url, $webm).'" type="video/webm">';
                $video .= '<source src="'.str_replace($upload_dir, $upload_url, $mp4).'" type="video/mp4">';
                $video .= '</video>';

                $content = str_replace($matches[0][$i], $video, $content);
            }
        }
    }

    return $content;
}