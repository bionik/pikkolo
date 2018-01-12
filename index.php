<?php

//Configuration
$config = array(
    'title' => 'Pikkolo',
    'description' => 'Photoblog',
    'shareimage' => '', //If not set, site will use the first image
    'directory' => 'photos',
    'thumb_directory' => 'thumbs',
    'thumb_width' => 2500,
    'password' => 'pikkolo321',
    'debug' => true
);

//Allow more time for thumb resizing
set_time_limit(0);

//Increase memory limit for large images
ini_set('memory_limit', '256M');

//Enable error reporting
error_reporting(E_ALL);

//API response
if(isset($_GET['api']) && isset($_POST['a'])){
  if($_POST['a'] == "checkHash"){
    //Method for checking saved hash validity
    if(isset($_POST['hash']) && trim($_POST['hash']) != ''){
      if($_POST['hash'] == sha1($config['password'])){
        $response['status'] = "OK";
      } else {
        die(json_encode(array('status'=>'ERROR', 'message'=>'INCORRECT_HASH')));
      }
    }
  } else if($_POST['a'] == "uploadFile" && isset($_FILES["file"])){
    //Method for uploading image over AJAX
    $hash = sha1($config['password']);
    if(
      (!isset($_POST['hash']) && !isset($_POST['password'])) ||
      (isset($_POST['hash']) && $_POST['hash'] !== $hash) ||
      (isset($_POST['password']) && $_POST['password'] !== $config['password'])
    ){
      die(json_encode(array('status'=>'ERROR', 'message'=>'AUTHENTICATION_FAILURE')));
    }
    $image_size = getimagesize($_FILES["file"]["tmp_name"]);
    if($image_size === false){
      die(json_encode(array('status'=>'ERROR', 'message'=>'FILE_NOT_AN_IMAGE')));
    }
    $target_file = $config['directory'].'/'.time().'_'.basename($_FILES["file"]["name"]);
    if(file_exists($target_file)) {
      die(json_encode(array('status'=>'ERROR', 'message'=>'FILE_EXISTS')));
    }
    if(move_uploaded_file($_FILES["file"]["tmp_name"], $target_file)) {
      $response['hash'] = $hash;
      $response['status'] = "OK";
    } else {
      die(json_encode(array('status'=>'ERROR', 'message'=>'FILE_MOVE_FAILED')));
    }
  }
  if(!isset($response['status'])){
    die(json_encode(array('status'=>'ERROR', 'message'=>'PARAMETER_ERROR')));
  }
  die(json_encode($response));
}

//Regular response
$show_upload_form = false;
if(isset($_GET['upload'])){
  $show_upload_form = true;
}

//Figure out the base URL
$base_url = "http://".$_SERVER['SERVER_NAME'].dirname($_SERVER["REQUEST_URI"].'?').'/';

//Function for checking if a file looks like an image
function looksLikeImage($filename){
  $extension = strtolower( substr( strrchr($filename,'.'), 1) );
  if( $extension == "jpg" || $extension == "jpeg" ) return true;
  return false;
}

function openImage($file) {
  $image = @imagecreatefromjpeg($file);
  if ($image != false) {
    return $image;
  }
  unset($image);
  return false;
}

function cacheImage($filename) {
  global $config;

  if(file_exists($config['thumb_directory'].'/'.$filename)) {
    return array(
      'src' => $config['directory'].'/'.$filename,
      'thumb' => $config['thumb_directory'].'/'.$filename
    );
  }

  $image = openImage( $config['directory'].'/'.$filename );

  if ($image === false || is_null($image)) {
      return false;
  }

  $orig_width = imagesx($image);
  $orig_height = imagesy($image);

  $new_width = $config['thumb_width'];
  $new_height = $new_width / ($orig_width/$orig_height);

  $tmpimg = false;

  if($new_width < $orig_width){
    $tmpimg = imagecreatetruecolor( $new_width, $new_height );
    imagecopyresampled($tmpimg, $image, 0, 0, 0, 0, $new_width, $new_height, $orig_width, $orig_height );
    $tmpimg = UnsharpMask($tmpimg, 40, 1, 1);

  } else {
    $tmpimg = imagecreatetruecolor( $orig_width, $orig_height );
    imagecopyresampled($tmpimg, $image, 0, 0, 0, 0, $orig_width, $orig_height, $orig_width, $orig_height );

  }

  if (!file_exists($config['thumb_directory'])) mkdir($config['thumb_directory']);

  imagejpeg($tmpimg, $config['thumb_directory'].'/'.$filename, 70);

  imagedestroy($tmpimg);
  imagedestroy($image);

  return array(
    'src' => $config['directory'].'/'.$filename,
    'thumb' => $config['thumb_directory'].'/'.$filename
  );

}

$files = array();

//Get images and sort them by modification time
if($handle = opendir($config['directory'])) {
  while (false !== ($file = readdir($handle))) {
    $filename = $config['directory'].'/'.$file;
    if (substr($file, 0, 1) != "." && looksLikeImage($filename)) {
      $files[filemtime($filename)] = $file;
    }
  }
  closedir($handle);
  ksort($files);
  $files = array_reverse($files, true);
}

$data = array();

//Cache images
if (!empty($files)) {
  foreach ($files as $filename) {
    $cached = cacheImage($filename);
    if ($cached !== false && $cached != "") {
      $data[] = $cached;
    }
  }
}

//Use oldest image as shareimage
if((!isset($config['shareimage']) || $config['shareimage'] == '') && isset($data[0])){
  $config['shareimage'] = $base_url.$data[(count($data) - 1)]['thumb'];
}

?><!doctype html>
<html>
  <head>
    <meta charset="utf-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge,chrome=1">

    <title><?php echo $config['title']; ?></title>
    <meta name="description" content="<?php echo $config['description']; ?>">
    <meta property="og:title" content="<?php echo $config['title']; ?>"/>
    <meta property="og:image" content="<?php echo $config['shareimage']; ?>">
    <meta property="og:site_name" content="<?php echo $config['title']; ?>"/>
    <meta property="og:description" content="<?php echo $config['description']; ?>"/>

    <meta name="viewport" content="width=device-width, user-scalable=no"/>

    <link rel="stylesheet" type="text/css" href="css/main.css"/>

    <meta name="format-detection" content="telephone=no">

    <title>Pikkolo</title>

  </head>
  <body>

<?php
  if($show_upload_form === true){
    echo '<form id="upload" style="display: none;">
      <div class="close" role="upload-close"></div>
      <input type="password" name="password" placeholder="Password"/>
      <input type="file" name="file"/><br>
      <input type="submit" value="Upload"/><br>
    </form>';
  }

?>
    <div class="root"></div>

    <script src="js/libs/jquery-3.2.1.min.js"></script>
    <script src="js/scripts.js"></script>

    <script type="text/javascript">
      window.data = JSON.parse('<?php echo json_encode($data); ?>');
      window.debug = <?php echo $config['debug'] ? 'true' : 'false'; ?>;
    </script>

  </body>
</html><?php

function UnsharpMask($img, $amount, $radius, $threshold) {

  // Attempt to calibrate the parameters to Photoshop:
  if ($amount > 500) $amount = 500;
  $amount = $amount * 0.016;

  if ($radius > 50) $radius = 50;
  $radius = $radius * 2;

  if ($threshold > 255) $threshold = 255;

  $radius = abs(round($radius));     // Only integers make sense.

  if ($radius == 0) {
    return $img;
    imagedestroy($img);
    return;
  }

  $w = imagesx($img); $h = imagesy($img);
  $imgCanvas = imagecreatetruecolor($w, $h);
  $imgBlur = imagecreatetruecolor($w, $h);

  if (function_exists('imageconvolution')) { // PHP >= 5.1
      $matrix = array(
      array( 1, 2, 1 ),
      array( 2, 4, 2 ),
      array( 1, 2, 1 )
    );
    imagecopy ($imgBlur, $img, 0, 0, 0, 0, $w, $h);
    imageconvolution($imgBlur, $matrix, 16, 0);

  }  else {

  // Move copies of the image around one pixel at the time and merge them with weight
  // according to the matrix. The same matrix is simply repeated for higher radii.
    for ($i = 0; $i < $radius; $i++)    {
      imagecopy ($imgBlur, $img, 0, 0, 1, 0, $w - 1, $h); // left
      imagecopymerge ($imgBlur, $img, 1, 0, 0, 0, $w, $h, 50); // right
      imagecopymerge ($imgBlur, $img, 0, 0, 0, 0, $w, $h, 50); // center
      imagecopy ($imgCanvas, $imgBlur, 0, 0, 0, 0, $w, $h);

      imagecopymerge ($imgBlur, $imgCanvas, 0, 0, 0, 1, $w, $h - 1, 33.33333 ); // up
      imagecopymerge ($imgBlur, $imgCanvas, 0, 1, 0, 0, $w, $h, 25); // down
    }
  }

  if($threshold>0){
    // Calculate the difference between the blurred pixels and the original
    // and set the pixels
    for ($x = 0; $x < $w-1; $x++)    { // each row
      for ($y = 0; $y < $h; $y++)    { // each pixel

        $rgbOrig = ImageColorAt($img, $x, $y);
        $rOrig = (($rgbOrig >> 16) & 0xFF);
        $gOrig = (($rgbOrig >> 8) & 0xFF);
        $bOrig = ($rgbOrig & 0xFF);

        $rgbBlur = ImageColorAt($imgBlur, $x, $y);

        $rBlur = (($rgbBlur >> 16) & 0xFF);
        $gBlur = (($rgbBlur >> 8) & 0xFF);
        $bBlur = ($rgbBlur & 0xFF);

        // When the masked pixels differ less from the original
        // than the threshold specifies, they are set to their original value.
        $rNew = (abs($rOrig - $rBlur) >= $threshold)
          ? max(0, min(255, ($amount * ($rOrig - $rBlur)) + $rOrig))
          : $rOrig;
        $gNew = (abs($gOrig - $gBlur) >= $threshold)
          ? max(0, min(255, ($amount * ($gOrig - $gBlur)) + $gOrig))
          : $gOrig;
        $bNew = (abs($bOrig - $bBlur) >= $threshold)
          ? max(0, min(255, ($amount * ($bOrig - $bBlur)) + $bOrig))
          : $bOrig;

        if (($rOrig != $rNew) || ($gOrig != $gNew) || ($bOrig != $bNew)) {
          $pixCol = ImageColorAllocate($img, $rNew, $gNew, $bNew);
          ImageSetPixel($img, $x, $y, $pixCol);
        }
      }
    }
  } else {

    for ($x = 0; $x < $w; $x++)    { // each row
      for ($y = 0; $y < $h; $y++)    { // each pixel
        $rgbOrig = ImageColorAt($img, $x, $y);
        $rOrig = (($rgbOrig >> 16) & 0xFF);
        $gOrig = (($rgbOrig >> 8) & 0xFF);
        $bOrig = ($rgbOrig & 0xFF);

        $rgbBlur = ImageColorAt($imgBlur, $x, $y);

        $rBlur = (($rgbBlur >> 16) & 0xFF);
        $gBlur = (($rgbBlur >> 8) & 0xFF);
        $bBlur = ($rgbBlur & 0xFF);

        $rNew = ($amount * ($rOrig - $rBlur)) + $rOrig;
          if($rNew>255){$rNew=255;}
          elseif($rNew<0){$rNew=0;}
        $gNew = ($amount * ($gOrig - $gBlur)) + $gOrig;
          if($gNew>255){$gNew=255;}
          elseif($gNew<0){$gNew=0;}
        $bNew = ($amount * ($bOrig - $bBlur)) + $bOrig;
          if($bNew>255){$bNew=255;}
          elseif($bNew<0){$bNew=0;}
        $rgbNew = ($rNew << 16) + ($gNew <<8) + $bNew;
          ImageSetPixel($img, $x, $y, $rgbNew);
      }
    }

  }

  imagedestroy($imgCanvas);
  imagedestroy($imgBlur);

  return $img;

}