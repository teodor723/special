<?php
/* Belloo Software by https://premiumdatingscript.com */
session_start();

// CORS headers to allow cross-origin requests
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}


header('Content-type:application/json;charset=utf-8');
require('../includes/config.php');
$check_bar = substr($site_url, -1);
if($check_bar != '/'){
    $site_url = $site_url.'/';
}
$mysqli = new mysqli($db_host, $db_username, $db_password,$db_name);
$mysqli->set_charset('utf8mb4');
if (mysqli_connect_errno()) {
    exit(mysqli_connect_error());
}
function getData($table,$col,$filter=''){
    global $mysqli;
    $q = $mysqli->query("SELECT $col FROM $table $filter");
    $result = 'noData';
    if($q->num_rows >= 1) {
        $r = $q->fetch_object();
        $result = $r->$col;
    }
    return $result;
}
function getArray($table,$filter='',$order='',$limit=''){
    global $mysqli;
    $result = array();
    $query = $mysqli->query("SELECT * FROM $table $filter ORDER BY $order $limit");
    if(isset($query->num_rows) && !empty($query->num_rows)){
        while($row = $query->fetch_assoc()){
            $result[] = $row;
        }       
    }
    return $result; 
}
function json_clean_decode($json, $assoc = false, $depth = 512, $options = 0) {
    $json = preg_replace("#(/\*([^*]|[\r\n]|(\*+([^*/]|[\r\n])))*\*+/)|([\s\t]//.*)|(^//.*)#", '', $json);
    if(version_compare(phpversion(), '5.4.0', '>=')) { 
        return json_decode($json, $assoc, $depth, $options);
    }
    elseif(version_compare(phpversion(), '5.3.0', '>=')) { 
        return json_decode($json, $assoc, $depth);
    }
    else {
        return json_decode($json, $assoc);
    }
}
function aws($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'amazon' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
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

function gemini($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'geminiplugin' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}

function watermark($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'watermark' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}

function secureEncode($string) {
    $str = preg_replace('/[^A-Za-z0-9\. -]/', '', $string);
    return $str;
}

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
                'region'  => aws('region'),
                'http'    => ['verify' => false]
            )
        );
    } catch (Exception $e) {
        die("Error: " . $e->getMessage());
    }
}
//Sightengine
if(sightengine('enable_sightengine') == 'Yes'){
    $se_api_user = sightengine('sightengine_api_user');
    $se_api_secret = sightengine('sightengine_api_secret');
}
if(isset($_GET['fromUrl'])){
    die('no longer supported');
    exit;   
} else {
    if (!isset($_FILES['file'])) {
       die("There is no file to upload.");
    }
    try {
        $ai_gen=false;
        $image_desc="";
        if (
            !isset($_FILES['file']['error']) ||
            is_array($_FILES['file']['error'])
        ) {
            throw new RuntimeException('Invalid parameters.');
        }
        
        switch ($_FILES['file']['error']) {
            case UPLOAD_ERR_OK:
                break;
            case UPLOAD_ERR_NO_FILE:
                throw new RuntimeException('No file sent.');
            case UPLOAD_ERR_INI_SIZE:
            case UPLOAD_ERR_FORM_SIZE:
                throw new RuntimeException('Exceeded filesize limit.');
            default:
                throw new RuntimeException('Unknown errors.');
        }

        $file_name = trim(basename(stripslashes($_FILES['file']['name'])), ".\x00..\x20");
        $file_name = str_replace(" ", "", $file_name);
        $file_name = str_replace("(", "", $file_name);
        $file_name = str_replace(")", "", $file_name);
        $file_name = secureEncode($file_name);
        $temp_filepath = sprintf('uploads/%s_%s', uniqid(), $file_name);
        $filepath = $temp_filepath;
        $thumbpath = sprintf('uploads/thumb_%s_%s', uniqid(), $file_name);
        if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false) { 
            $file = $_FILES['file']['tmp_name'];
            if(filesize($file) > (20 * 1024 * 1024)){
                echo json_encode(['chatcontetnerror' => "This picture size is more than 20mb."]);
                die();
            }
            if(gemini('enabled') == 'Yes'){
                try {
                    $ch = curl_init($site_url.'assets/sources/test.php?file_path='.$filepath);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Content-Type: application/json"
                    ]);
                    $response = curl_exec($ch);
                    if (curl_errno($ch)) {
                        echo json_encode(['chatcontetnerror' => curl_error($ch)]);die();
                    }
                    curl_close($ch);
                    $result = json_decode($response, true);
                    if(isset($result['contetnerror']) && !empty($result['contetnerror'])) {
                        echo json_encode(['chatcontetnerror' => $result['contetnerror']]);
                        die();
                    }
                } catch (Exception $e) {
                    echo json_encode(['chatcontetnerror' => $e->getMessage()]);
                    die();
                }
                
            }
        }
        if(sightengine('enable_sightengine')=="Yes" && strpos($filepath, '.json') == false) {
           /* 
            $filepath = strtolower($filepath);
            if(strpos($filepath, '.php') !== false || strpos($filepath, '.py') !== false || strpos($filepath, '.htaccess') !== false || strpos($filepath, '.rb') !== false){
                die('gtfo');
                exit;
            }
            if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false) {
                $file = $_FILES['file']['tmp_name'];
                $params = array(
                  'media' => new CurlFile($file),
                  'models' => 'nudity-2.0,genai',
                  'api_user' => $se_api_user,
                  'api_secret' => $se_api_secret,
                );
                $ch = curl_init('https://api.sightengine.com/1.0/check.json');
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                $response = curl_exec($ch);
                curl_close($ch);
                $output = json_decode($response, true);
                if(isset($output['status']) && $output['status']=="success") {
                    if(isset($output['nudity'])) {
                        if($output['nudity']['sexual_activity'] >= 0.1 || $output['nudity']['sexual_display'] >= 0.1 || $output['nudity']['erotica'] >= 0.1 || $output['nudity']['sextoy'] >= 0.1 || $output['nudity']['suggestive'] >= 0.2){
                            
                            echo json_encode(['contetnerror' => "This picture contains sensitive content which some people may find offensive or disturbing."]);
                            die();
                        }
                    }
                    if(isset($output['type']['ai_generated'])){
                        if($output['type']['ai_generated'] < 0.5) {
                            $ai_gen=false;
                        } else {
                            $ai_gen=true;
                        }
                        $url = 'https://api.openai.com/v1/images/describe';
                        $headers = [
                            'Authorization: Bearer ' . $sm['plugins']['aiautoresponder']['secret'],
                            'Content-Type: application/json'
                        ];
                        $data = [
                            'image' => base64_encode(file_get_contents($file))
                        ];
                    
                        $ch = curl_init();
                        curl_setopt($ch, CURLOPT_URL, $url);
                        curl_setopt($ch, CURLOPT_POST, true);
                        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
                    
                        $response = curl_exec($ch);
                        curl_close($ch);
                    
                        $result = json_decode($response, true);
                        $image_desc=$result['description'] ?? 'No description available.';
                    }
                }
            }
            if (strpos($filepath, 'mp4') !== false || strpos($filepath, 'webm') !== false || strpos($filepath, 'ogg') !== false) {
                try{
                    require_once __DIR__.'/libs/getid3/getid3.php';
                    $file = $_FILES['file']['tmp_name'];
                    $getID3 = new getID3();
                    $fileInfo = $getID3->analyze($file);
                    if (isset($fileInfo['playtime_seconds'])) {
                        $durationSeconds = $fileInfo['playtime_seconds'];
                        if ($durationSeconds > 60) {
                            echo json_encode([
                                'contetnerror' => 'Video duration exceeds 1 minute.'
                            ]);
                            die();
                        }
                    } else {
                        echo json_encode([
                            'contetnerror' => 'Unable to retrieve video duration.'
                        ]);
                        die();
                    }
                    $params = array(
                      'media' => new CurlFile($file),
                      'models' => 'nudity-2.1',
                      'api_user' => $se_api_user,
                      'api_secret' => $se_api_secret,
                    );
                    $ch = curl_init('https://api.sightengine.com/1.0/video/check-sync.json');
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, $params);
                    $response = curl_exec($ch);
                    curl_close($ch);
                    $output = json_decode($response, true);
                    if(isset($output['status']) && $output['status']=="success") {
                        $nudity=false;
                        foreach($output['data']['frames'] as $result) {
                            if($result['nudity']['sexual_activity'] >= 0.1 || $result['nudity']['sexual_display'] >= 0.1 || (isset($result['nudity']['sextoy']) && $result['nudity']['sextoy'] >= 0.1)) {
                                $nudity=true;
                            }
                        }
                        if($nudity) {
                            echo json_encode(['contetnerror' => "This video contains sensitive content which some people may find offensive or disturbing."]);
                            die();
                        }
                    }
                } catch(\Exception $e){}
            }
           */
        }
        if(aws('enabled') == 'Yes' && strpos($filepath, '.json') == false){
            $keyName = time().basename($file_name);
            $pathInS3 = 'https://s3.'.aws('region').'.amazonaws.com/' . $bucketName . '/' . $keyName;

            $filepath = strtolower($filepath);
            if(strpos($filepath, '.php') !== false || strpos($filepath, '.py') !== false || strpos($filepath, '.htaccess') !== false || strpos($filepath, '.rb') !== false){
                die('gtfo');
                exit;
            }
            // try {
            //     $file = $_FILES['file']['tmp_name'];            
            //     $s3->putObject(
            //         array(
            //             'Bucket'=> $bucketName,
            //             'Key' =>  $keyName,
            //             'SourceFile' => $file,
            //             'ACL'    => 'public-read'
            //         )
            //     );
            // } catch (Aws\S3\Exception\S3Exception $e) {
            //     echo json_encode([
            //         'erorr' => $e
            //     ]);
            // }
            
            $filepath = $pathInS3;


            // Save to reels_aws_upload table if it's a video file (for reels)            

            if (strpos($filepath, 'mp4') !== false || strpos($filepath, 'webm') !== false || strpos($filepath, 'ogg') !== false) {                
                try {
                    $awsCols = 'file_name,s3_url,hls_url,rekognition';
                    $awsVals = '"'.$keyName.'","'.$pathInS3.'","",""';                    
                    $mysqli->query("INSERT INTO reels_aws_upload ($awsCols) VALUES ($awsVals)");
                } catch (Exception $e) {
                    echo("Failed to save to reels_aws_upload: " . $e->getMessage());
                }
            }
            
            
            if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false) {        
                $thumbpath = awsThumb($pathInS3,$thumbpath);
                $thumbpath = $site_url.'assets/sources/'.$thumbpath; 
            }   
        }else if(bunny('enabled') == 'Yes' && strpos($filepath, '.json') == false){
            $keyName = time().basename($file_name);
            $file_name = $_FILES['file']['name'];
            $tmp_file = $_FILES['file']['tmp_name'];
            $filepath = strtolower($file_name);
            if (strpos($filepath, '.php') !== false || strpos($filepath, '.py') !== false || strpos($filepath, '.htaccess') !== false || strpos($filepath, '.rb') !== false) {
                die('gtfo');
                exit;
            }
            $bunnyBaseUrl = bunny('base_url');
            $bunnyApiKey = bunny('api_key');
            $keyName = time() . basename($file_name);
            $uploadUrl = "$bunnyBaseUrl/$keyName";
            $ch = curl_init($uploadUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "AccessKey: $bunnyApiKey"
            ]);
            curl_setopt($ch, CURLOPT_POSTFIELDS, file_get_contents($tmp_file));
            $response = curl_exec($ch);
            $httpStatus = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);
            if ($httpStatus === 201) {
                $uploadedFileUrl = "$bunnyBaseUrl/$keyName";
                // echo json_encode([
                //     'status' => 'success',
                //     'file_url' => $uploadedFileUrl
                // ]);
            } else {
                echo json_encode([
                    'status' => 'error',
                    'erorr' => 'Upload failed',
                    'http_status' => $httpStatus
                ]);
            }
            $filepath = $uploadUrl;
            if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false) {        
                $thumbpath = awsThumb($uploadUrl,$thumbpath);
                $thumbpath = $site_url.'assets/sources/'.$thumbpath; 
            }   
        } else {
            $filepath = strtolower($filepath);
            if(strpos($filepath, '.php') !== false || strpos($filepath, '.py') !== false || strpos($filepath, '.htaccess') !== false || strpos($filepath, '.rb') !== false){
                die('gtfo');
                exit;
            }
            $filetmp_path = $_FILES['file']['tmp_name'];
            $fileSize = filesize($filetmp_path);
            $fileinfo = finfo_open(FILEINFO_MIME_TYPE);
            $filetype = finfo_file($fileinfo, $filetmp_path);

            $allowedTypes = [
               'image/png' => 'png',
               'image/jpeg' => 'jpg',
               'image/webp' => 'webp',
               'image/gif' => 'gif',
               'image/svg+xml' => 'svg',
               'video/x-msvideo' => 'avi',
               'video/mpeg' => 'mpeg',
               'video/mp4' => 'mp4',
               'video/webm' => 'webm',
               'application/json' => 'json'               
            ];
            
            if(!in_array($filetype, array_keys($allowedTypes))) {
               die("File not allowed.");
            }
            if (!copy(
                $_FILES['file']['tmp_name'],
                $filepath
            )) {
                throw new RuntimeException('Failed to move uploaded file.');
            }
            
            unlink($filetmp_path);

            //generate thumb
            if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false || strpos($filepath, 'JPG') !== false || strpos($filepath, 'JPEG') !== false || strpos($filepath, 'PNG') !== false) {
                make_thumb($filepath, $thumbpath, 200);
            }
            $clearfilepath = $filepath;
            $filepath = $site_url.'assets/sources/'.$filepath;
            $thumbpath = $site_url.'assets/sources/'.$thumbpath;
        }
        
        if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false || strpos($filepath, 'JPG') !== false || strpos($filepath, 'JPEG') !== false || strpos($filepath, 'PNG') !== false) {
            $ai_img=($ai_gen)?"Yes":"No";
            $mysqli->query('INSERT INTO image_descriptions(photo_path,description) VALUES ("'.$ai_img.'","'.$image_desc.'")');
        }

        $result = array();
        if(strpos($filepath, 'json') !== false || strpos($filepath, 'JSON') !== false){
            $fileContents = file_get_contents($clearfilepath);
            if(strpos($_SERVER['HTTP_REFERER'], 'p=themes') !== false || strpos($_SERVER['HTTP_REFERER'], 'p=themesLanding') !== false){
                $json = json_clean_decode($fileContents);
                $settings = $json->theme_settings;
                $decoded = json_decode($fileContents,true);
                $settings = str_replace('\"', '"', $settings);
                $result['type'] = 'preset';
                $result['settings'] = $settings;
                $name = $decoded['preset'].rand(0,9999);
                $result['name'] = $name;
                $result['alias'] = $decoded['preset_alias'];
                $result['landing'] = $decoded['landing'];
                $result['data'] = $settings;
                $result['theme'] = $decoded['theme'];
                $result['base'] = $decoded['preset_base'];
                foreach ($decoded['fonts'] as $data) {
                    $mysqli->query('INSERT INTO theme_preset_fonts(preset,font,setting) VALUES 
                    ("'.$name.'","'.$data['font'].'","'.$data['setting'].'")');
                }
            }
            if(strpos($_SERVER['HTTP_REFERER'], 'p=languages') !== false){
                $decoded = json_decode($fileContents,true);
                $result=[];
                $result['type'] = 'language';
                $result['name'] = $decoded['name'];
                $result['site_lang'] = $decoded['site_lang'];
                $query = 'INSERT INTO languages (name,prefix) VALUES ("'.$decoded['name'].'","'.$decoded['prefix'].'")';
                if ($mysqli->query($query) === TRUE) {
                    $last_id = $mysqli->insert_id;
                    $result['id']=$last_id;
                    if(isset($decoded['site_lang'])) {
                        foreach ($decoded['site_lang'] as $data) {
                            $mysqli->query('INSERT INTO site_lang(id,lang_id,text) VALUES 
                                ("'.$data['id'].'","'.$last_id.'","'.$data['text'].'")');
                        }
                    }
                    try {
                        if (isset($decoded['app_lang']) && !empty($decoded['app_lang'])) {
                            $stmt = $mysqli->prepare("INSERT INTO app_lang(id, lang_id, text) VALUES (?, ?, ?)");
                            foreach ($decoded['app_lang'] as $data) {
                                $stmt->bind_param("sss", $data['id'], $last_id, $data['text']);
                                $stmt->execute();
                            }
                            $stmt->close();
                        }
                    } catch (\Exception $e) {
                        $result["msg"] = $e->getMessage();
                    }
                    // Insert into email_lang
                    if (isset($decoded['email_lang'])) {
                        $stmt = $mysqli->prepare("INSERT INTO email_lang(id, lang_id, text) VALUES (?, ?, ?)");
                        foreach ($decoded['email_lang'] as $data) {
                            $stmt->bind_param("sss", $data['id'], $last_id, $data['text']);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    // Insert into seo_lang
                    if (isset($decoded['seo_lang'])) {
                        $stmt = $mysqli->prepare("INSERT INTO seo_lang(id, lang_id, text, page) VALUES (?, ?, ?, ?)");
                        foreach ($decoded['seo_lang'] as $data) {
                            $stmt->bind_param("ssss", $data['id'], $last_id, $data['text'], $data['page']);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    // Insert into config_profile_questions
                    if (isset($decoded['questions_lang'])) {
                        $stmt = $mysqli->prepare("INSERT INTO config_profile_questions(id, question, lang_id, method, q_order, gender) VALUES (?, ?, ?, ?, ?, ?)");
                        foreach ($decoded['questions_lang'] as $data) {
                            $stmt->bind_param("ssssss", $data['id'], $data['question'], $last_id, $data['method'], $data['q_order'], $data['gender']);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    // Insert into config_profile_answers
                    if (isset($decoded['answer_lang'])) {
                        $stmt = $mysqli->prepare("INSERT INTO config_profile_answers(id, qid, answer, lang_id) VALUES (?, ?, ?, ?)");
                        foreach ($decoded['answer_lang'] as $data) {
                            $stmt->bind_param("ssss", $data['id'], $data['qid'], $data['answer'], $last_id);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    // Insert into config_genders
                    if (isset($decoded['gender_lang'])) {
                        $stmt = $mysqli->prepare("INSERT INTO config_genders(id, name, lang_id, sex) VALUES (?, ?, ?, ?)");
                        foreach ($decoded['gender_lang'] as $data) {
                            $stmt->bind_param("ssss", $data['id'], $data['name'], $last_id, $data['sex']);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }

                    // Insert into landing_lang
                    if (isset($decoded['landing_lang'])) {
                        $stmt = $mysqli->prepare("INSERT INTO landing_lang(id, lang_id, text, theme, preset) VALUES (?, ?, ?, ?, ?)");
                        foreach ($decoded['landing_lang'] as $data) {
                            $stmt->bind_param("sssss", $data['id'], $last_id, $data['text'], $data['theme'], $data['preset']);
                            $stmt->execute();
                        }
                        $stmt->close();
                    }
                }
            }            
            echo json_encode($result);
        } else {
            if (strpos($temp_filepath, 'mp4') !== false || strpos($temp_filepath, 'ogg') !== false || strpos($temp_filepath, 'webm') !== false) {
                $result['status'] = 'ok';
                $result['video'] = 1;
                $result['path'] = $filepath;
                $result['thumb'] = '';
                echo json_encode($result);       
            } else {
                if(watermark('enabled') == 'Yes' && aws('enabled') == 'No' && strpos($_SERVER['HTTP_REFERER'], 'admin&p=plugin') === false && strpos($_SERVER['HTTP_REFERER'], 'editor') === false){
                    if(isset($_GET['fromEditor'])){
                        $result['fromEditor'] = true;
                    } else {
                        $watermarkImg = watermark('watermark');
                        $watermarkImg = str_replace($site_url.'assets/sources/', '', $watermarkImg);
                        $watermarkTarget = str_replace($site_url.'assets/sources/', '', $filepath); 
                        watermark_image($watermarkTarget,$watermarkImg,$watermarkTarget);                        
                    }
                }
                $result['status'] = 'ok';
                $result['video'] = 0;
                $result['path'] = $filepath;
                $result['thumb'] = $thumbpath;
                echo json_encode($result);       
            }        
        }
    } catch (RuntimeException $e) {
        http_response_code(400);
        echo json_encode(['status' => 'error','message' => $e->getMessage(),'path' => $filepath]);
    }
}

function watermark_image($target, $wtrmrk_file, $newcopy) {
    $watermark = imagecreatefrompng($wtrmrk_file);
    imagealphablending($watermark, false);
    imagesavealpha($watermark, true);
    $imgType = get_image_type($target);
    if(strpos($imgType, 'png') !== false) {
       $img = imagecreatefrompng($target);
    } else {
       $img = imagecreatefromjpeg($target); 
    }    
    $img_w = imagesx($img);
    $img_h = imagesy($img);
    $wtrmrk_w = imagesx($watermark);
    $wtrmrk_h = imagesy($watermark);
    $position = watermark('position');
    if($position == 'Bottom left'){
        $dst_x = 25;
        $dst_y = $img_h - $wtrmrk_h - 15;          
    }
    if($position == 'Bottom right'){
        $dst_x = $img_w - $wtrmrk_w - 25; 
        $dst_y = $img_h - $wtrmrk_h - 15;          
    }
    if($position == 'Top left'){
        $dst_x = 25; 
        $dst_y = 15;         
    }
    if($position == 'Top right'){
        $dst_x = $img_w - $wtrmrk_w - 25; 
        $dst_y = 15;        
    }
    if($position == 'Center'){
        $dst_x = ($img_w / 2) - ($wtrmrk_w / 2);
        $dst_y = ($img_h / 2) - ($wtrmrk_h / 2);       
    }                
    imagecopy($img, $watermark, $dst_x, $dst_y, 0, 0, $wtrmrk_w, $wtrmrk_h);
    imagejpeg($img, $newcopy, 100);
    imagedestroy($img);
    imagedestroy($watermark);
}
function make_thumb($src, $dest, $desired_width) {
    $imgType = get_image_type($src);
    if(strpos($imgType, 'png') !== false) {
       $source_image = imagecreatefrompng($src);
    } else {
       $source_image = imagecreatefromjpeg($src); 
    }   

    if(strpos($imgType, 'png') === false) {
        $exif = exif_read_data($src);
        if(!empty($exif['Orientation'])) {
            switch($exif['Orientation']) {
                case 8:
                    $source_image = imagerotate($source_image,90,0);
                    break;
                case 3:
                    $source_image = imagerotate($source_image,180,0);
                    break;
                case 6:
                    $source_image = imagerotate($source_image,-90,0);
                    break;
            }
        }
    }

    $width = imagesx($source_image);
    $height = imagesy($source_image);
    $desired_height = floor($height * ($desired_width / $width));
    $virtual_image = imagecreatetruecolor($desired_width, $desired_height);
    imagecopyresampled($virtual_image, $source_image, 0, 0, 0, 0, $desired_width, $desired_height, $width, $height);
    imagejpeg($virtual_image, $dest);
}

function awsThumb($url, $filename, $width = 200, $height = true) {
    $image = ImageCreateFromString(file_get_contents($url)); 

    $height = $height === true ? (ImageSY($image) * $width / ImageSX($image)) : $height;
    $output = ImageCreateTrueColor($width, $height);
    ImageCopyResampled($output, $image, 0, 0, 0, 0, $width, $height, ImageSX($image), ImageSY($image));
    ImageJPEG($output, $filename, 95); 

    return $filename; 
}

function get_image_type( $filename ) {
    $img = getimagesize( $filename );
    if ( !empty( $img[2] ) )
        return image_type_to_mime_type( $img[2] );
    return false;
}

