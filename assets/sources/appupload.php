<?php
header("Access-Control-Allow-Origin: *");
require_once("../includes/core.php");
require_once("../includes/custom/app_core.php");

//AWS
require 'aws/aws-autoloader.php';
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
if(aws('enabled') == 'Yes'){
    // AWS Info
    $bucketName = aws('bucket');
    $IAM_KEY = aws('s3');
    $IAM_SECRET = aws('secret');
    // Connect to AWS
    try {
        $s3 = S3Client::factory(
            array(
                'credentials' => array(
                    'key' => $IAM_KEY,
                    'secret' => $IAM_SECRET
                ),
                'version' => 'latest',
                'region'  => aws('region')
            )
        );
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}
function bunny($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'bunny' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}
function sightengine($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'settings' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}
function aws($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'amazon' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}
function watermark($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'watermark' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}

function getPhotoType($data){
    if (preg_match('/^data:image\/(\w+);base64,/', $data, $type)) {
        $data = substr($data, strpos($data, ',') + 1);
        $type = strtolower($type[1]); // jpg, png, gif

        if (!in_array($type, [ 'jpg', 'jpeg', 'gif', 'png','wav','mpeg','mp4' ])) {
            throw new \Exception('invalid image type');
        }

        $data = base64_decode($data);

        if ($data === false) {
            throw new \Exception('base64_decode failed');
        } else {
            return $type;    
        }
    } else {
        throw new \Exception('did not match data URI with image data');
        return false;
    }    
}
function base64_to_jpeg($base64_string, $output_file) {
    // open the output file for writing
    $ifp = fopen( $output_file, 'wb' ); 

    // split the string on commas
    // $data[ 0 ] == "data:image/png;base64"
    // $data[ 1 ] == <actual base64 string>
    $data = explode( ',', $base64_string );

    // we could add validation here with ensuring count( $data ) > 1
    fwrite( $ifp, base64_decode( $data[ 1 ] ) );

    // clean up the file resource
    fclose( $ifp ); 

    return $output_file; 
}
function regImage($base64img,$uid){
    global $sm;
    $arr = array();
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64img));
    $time = time();

    $filepath = 'uploads/'.$uid.$time.'.'.getPhotoType($base64img);
    $thumbpath = 'uploads/thumb_'.$uid.$time.'.'.getPhotoType($base64img);   
    
    $filepath = strtolower($filepath);
    if(strpos($filepath, '.php') !== false || strpos($filepath, '.py') !== false || strpos($filepath, '.htaccess') !== false || strpos($filepath, '.rb') !== false){
        exit;
    }    
    file_put_contents($filepath, $data);

    if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false || strpos($filepath, 'JPG') !== false || strpos($filepath, 'JPEG') !== false || strpos($filepath, 'PNG') !== false) {
        make_thumb($filepath, $thumbpath, 200);
    }

    $purl = $sm['config']['site_url'].'assets/sources/'.$filepath;
    $thumburl = $sm['config']['site_url'].'assets/sources/'.$thumbpath;


    $arr['photo'] = $purl;
    $arr['thumb'] = $thumburl;
    echo json_encode($arr);
}


function uploadImage($base64img,$uid){
    global $mysqli,$sm;
    $arr = array();
    $data = base64_decode(preg_replace('#^data:image/\w+;base64,#i', '', $base64img));
    $time = time();
    
    $filepath = 'uploads/'.$uid.$time.'.'.getPhotoType($base64img);
    $name = $uid.$time.'.'.getPhotoType($base64img);
    $thumbpath = 'uploads/thumb_'.$uid.$time.'.'.getPhotoType($base64img);   
    
    $filepath = strtolower($filepath);
    if(strpos($filepath, '.php') !== false || strpos($filepath, '.py') !== false || strpos($filepath, '.htaccess') !== false || strpos($filepath, '.rb') !== false){
        exit;
    }
    file_put_contents(__DIR__.'/tmp/'.$name, file_get_contents($base64img));
    if(bunny('enabled') == 'Yes'){
        $keyName = $name;
        $filepath = strtolower($keyName);
        $bunnyBaseUrl = bunny('base_url');
        $bunnyApiKey = bunny('api_key');
        $uploadUrl = "$bunnyBaseUrl/$keyName";
        $ch = curl_init($uploadUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "AccessKey: $bunnyApiKey"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, new CurlFile(__DIR__.'/tmp/'.$name));
        $response = curl_exec($ch);
        $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        unlink(__DIR__.'/tmp/'.$name);
        if ($httpStatus === 201) {
            $filepath = "$bunnyBaseUrl/$keyName";
        } else {
            echo json_encode([
                'status' => 'error',
                'erorr' => 'Upload failed',
                'http_status' => $httpStatus
            ]);
        }
    } else {
        file_put_contents($filepath, $data);
    }

    if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false || strpos($filepath, 'JPG') !== false || strpos($filepath, 'JPEG') !== false || strpos($filepath, 'PNG') !== false) {
        make_thumb($filepath, $thumbpath, 200);
    }

 
    $purl = $sm['config']['site_url'].'assets/sources/'.$filepath;
    $thumburl = $sm['config']['site_url'].'assets/sources/'.$thumbpath;

    $photoReview = 1;
    if($sm['plugins']['settings']['photoReview'] == 'Yes' && !isset($_POST['adminPanel'])){
        $photoReview = 0;           
    }

    $mysqli->query("INSERT INTO users_photos(u_id,photo,thumb,approved)
    VALUES ('$uid','$purl', '$thumburl','".$photoReview."')");                                                     
    $arr['user']['photos'] = userAppPhotos($uid);
    echo json_encode($arr);
}

switch ($_POST['action']) {
    case 'register':
        if(sightengine('enable_sightengine')=="Yes") {
                $se_api_user = sightengine('sightengine_api_user');
                $se_api_secret = sightengine('sightengine_api_secret');
                $name="temp_".uniqid().'.jpeg';
                file_put_contents(__DIR__.'/tmp/'.$name, file_get_contents(secureEncode($_POST['base64'])));
                $params = array(
                  'media' => new CurlFile(__DIR__.'/tmp/'.$name),
                  'models' => 'nudity-2.0',
                  'api_user' => $se_api_user,
                  'api_secret' => $se_api_secret,
                );
                $ch = curl_init('https://api.sightengine.com/1.0/check.json');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                $response = curl_exec($ch);
                curl_close($ch);
                unlink(__DIR__.'/tmp/'.$name);
                // unlink(__DIR__.'/uploads/'.$name);
                $output = json_decode($response, true);
                if(isset($output['status']) && $output['status']=="success") {
                    if(isset($output['nudity'])) {
                        if($output['nudity']['sexual_activity'] >= 0.1 || $output['nudity']['sexual_display'] >= 0.1 || $output['nudity']['erotica'] >= 0.1 || $output['nudity']['sextoy'] >= 0.1 || $output['nudity']['suggestive'] >= 0.2){
                            
                            echo json_encode(['contetnerror' => $sm['lang'][1036]['text']]);
                            die();
                        }
                    }
                     
                }
        }
        regImage(secureEncode($_POST['base64']),secureEncode($_POST['uid']));
    break;
    case 'videoRecord':
        $arr = array();
        $data = base64_decode(preg_replace('#^data:video/\w+;base64,#i','', secureEncode($_POST['base64'])));
        $time = time();
        $file = 'uploads/'.secureEncode($_POST['uid']).$time.'.webm';
        $name=secureEncode($_POST['uid']).$time.'.webm';
        $video = $sm['config']['site_url'].'assets/sources/'.$file;
        file_put_contents(__DIR__.'/tmp/'.$name, file_get_contents(secureEncode($_POST['base64'])));
        if(bunny('enabled') == 'Yes'){
            $keyName = secureEncode($_POST['uid']).$time.'.webm';
            $filepath = strtolower($keyName);
            $bunnyBaseUrl = bunny('base_url');
            $bunnyApiKey = bunny('api_key');
            $uploadUrl = "$bunnyBaseUrl/$keyName";
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "AccessKey: $bunnyApiKey"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, new CurlFile(__DIR__.'/tmp/'.$name));
            $response = curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            unlink(__DIR__.'/tmp/'.$name);
            if ($httpStatus === 201) {
                $video = "$bunnyBaseUrl/$keyName";
            } else {
                echo json_encode([
                    'status' => 'error',
                    'erorr' => 'Upload failed',
                    'http_status' => $httpStatus
                ]);
            }
        } else {
            file_put_contents($file, $data);
        }

        $mysqli->query("UPDATE videocall set r_id_video = '".$video."' where call_id = '".secureEncode($_POST['callId'])."' and r_id = '".secureEncode($_POST['uid'])."'"); 
        $mysqli->query("UPDATE videocall set c_id_video = '".$video."' where call_id = '".secureEncode($_POST['callId'])."' and c_id = '".secureEncode($_POST['uid'])."'");
         
        $arr['videoRecord'] = $video;
        $arr['called'] = secureEncode($_POST['called']);
        $arr['uid'] = secureEncode($_POST['uid']);
        echo json_encode($arr);    
    break;    
    case 'upload':
        uploadImage(secureEncode($_POST['base64']),secureEncode($_POST['uid']));
    break;
    case 'sendChat':
        $uid = secureEncode($_POST['uid']);
        $rid = secureEncode($_POST['rid']);
        $base64img = str_replace('data:image/jpeg;base64,', '', $_POST['base64']);
        $base64img = str_replace('data:image/png;base64,', '', $_POST['base64']);
        $data = base64_decode($base64img);
        $time = time();
        $file = 'uploads/'.$uid.$time.'.jpg';
        $name = $uid.$time.'.jpg';
        $photo = $sm['config']['site_url'].'/assets/sources/'.$file;
        file_put_contents(__DIR__.'/tmp/'.$name, file_get_contents(secureEncode($_POST['base64'])));
        if(bunny('enabled') == 'Yes'){
            $keyName = $uid.$time.'.jpg';
            $filepath = strtolower($keyName);
            $bunnyBaseUrl = bunny('base_url');
            $bunnyApiKey = bunny('api_key');
            $uploadUrl = "$bunnyBaseUrl/$keyName";
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "AccessKey: $bunnyApiKey"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, new CurlFile(__DIR__.'/tmp/'.$name));
            $response = curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            unlink(__DIR__.'/tmp/'.$name);
            if ($httpStatus === 201) {
                $photo = "$bunnyBaseUrl/$keyName";
            } else {
                echo json_encode([
                    'status' => 'error',
                    'erorr' => 'Upload failed',
                    'http_status' => $httpStatus
                ]);
            }
        } else {
            file_put_contents($file, $data);
        }
        $mysqli->query("INSERT INTO chat (s_id,r_id,time,message,photo) VALUES ('".$uid."','".$rid."','".$time."','".$photo."' , 1)");  
        $event = 'chat'.$rid.$uid;
        $arr['type'] = 'image';
        $arr['message'] = $photo;
        $arr['id'] = $uid;
        $arr['chatHeaderRight']='<div class="js-message-block" id="you">
                <div class="message">
                    <div class="brick brick--xsm brick--hover">
                        <div class="brick-img profile-photo" data-src="'.$photo.'"></div>
                    </div>
                    <div class="message__txt">
                        <span class="lgrey message__time" style="margin-right: 15px;">'.date("H:i", $time).'</span>
                        <div class="message__name lgrey"></div>
                        <a href="#img'.$time.'">
                            <p class="montserrat chat-text">
                                <div class="message__pic_ js-wrap" style="cursor:pointer;">
                                    <img  src="'.$photo.'" />
                                </div>
                            </p>
                        </a>
                    </div>
                </div>
            </div>  
        ';  
        if(is_numeric($sm['plugins']['pusher']['id'])){ 
            $sm['push']->trigger($sm['plugins']['pusher']['key'], $event, $arr );   
        } 
    break;  
}


function make_thumb($src, $dest, $desired_width) {
    $imgType = get_image_type($src);
    if(strpos($imgType, 'png') !== false) {
       $source_image = imagecreatefrompng($src);
    } else {
       $source_image = imagecreatefromjpeg($src); 
    }   
    $width = imagesx($source_image);
    $height = imagesy($source_image);
    $desired_height = floor($height * ($desired_width / $width));
    $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
    imagejpeg($virtual_image, $dest);
}

function get_image_type( $filename ) {
    $img = getimagesize( $filename );
    if ( !empty( $img[2] ) )
        return image_type_to_mime_type( $img[2] );
    return false;
}

function awsThumb($url, $filename, $width = 200, $height = true) {

    $image = ImageCreateFromString(file_get_contents($url));
    $height = $height === true ? (ImageSY($image) * $width / ImageSX($image)) : $height;
    $output = ImageCreateTrueColor($width, $height);
    ImageCopyResampled($output, $image, 0, 0, 0, 0, $width, $height, ImageSX($image), ImageSY($image));
    ImageJPEG($output, $filename, 95); 
    return $filename; 
}