<?php
// Set the content-type
header('Content-Type: image/png');

// Create the image
$im = imagecreate(580, 580);

// Create some colors
$white = imagecolorallocate($im, 255, 255, 255);

$QR = imagecreatefromstring(file_get_contents('ID1415585675001181081_nb.png'));
$black = imagecolorallocate($im, 0, 0, 0);
//imagefilledrectangle($im, 0, 0, 400, 100, $white);

// The text to draw
$text = '此二维码2016-09-18 失效!';
// Replace path by your own font path
$font = '/Library/Fonts/华文仿宋.ttf';

// Add the text
imagecopyresampled($im, $QR, 0, 0, 0, 0, 530,
  530, 530, 530);
imagettftext($im, 20, 0, 100, 540, $black, $font, $text);
$text = '刘章';
imagestring($im, 5, 0, 0, $text, $black);
// Using imagepng() results in clearer text compared with imagejpeg()
imagepng($im);
imagedestroy($im);