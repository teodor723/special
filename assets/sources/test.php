<?php
/* Belloo Software by https://premiumdatingscript.com */
session_start();
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
function gemini($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'geminiplugin' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}

function secureEncode($string) {
    $str = preg_replace('/[^A-Za-z0-9\. -]/', '', $string);
    return $str;
}


try {
    $image_unsafe=false;
    $error_image="";
    $filepath = $_GET['file_path'];
    if($filepath) {
        if (strpos($filepath, 'jpg') !== false || strpos($filepath, 'jpeg') !== false || strpos($filepath, 'png') !== false) {
            if(gemini('enabled') == 'Yes'){
                try {
                    $gemini_filters = explode(",",gemini('filters'));
                    $gemini_api_key = gemini('api_key');
                    $url = 'https://vision.googleapis.com/v1/images:annotate?key='.$gemini_api_key;
                    $imagePath = $filepath;
                    $imageContent = base64_encode(file_get_contents($imagePath));
                    $data = [
                        'requests' => [
                            [
                                'image' => ['content' => $imageContent],
                                'features' => [
                                    ['type' => 'SAFE_SEARCH_DETECTION']
                                ]
                            ]
                        ]
                    ];
                    $ch = curl_init($url);
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_POST, true);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, [
                        "Content-Type: application/json"
                    ]);
                    $response = curl_exec($ch);
                    if (curl_errno($ch)) {
                        echo json_encode(['contetnerror' => curl_error($ch)]);die();
                    }
                    curl_close($ch);
                    $result = json_decode($response, true);
                    if (isset($result['responses'][0]['safeSearchAnnotation'])) {
                        $safeSearch = $result['responses'][0]['safeSearchAnnotation'];
                        $threshold = ['LIKELY', 'VERY_LIKELY'];
                        if (in_array("Adult", $gemini_filters) && in_array($safeSearch['adult'], $threshold)) {
                            $image_unsafe=true;
                            $error_image="The image contains adult content.";
                        }
                        if (in_array("Spoof", $gemini_filters) && in_array($safeSearch['spoof'], $threshold)) {
                            $image_unsafe=true;
                            $error_image="The image appears to be spoofed or manipulated.";
                        }
                        if (in_array("Medical", $gemini_filters) && in_array($safeSearch['medical'], $threshold)) {
                            $image_unsafe=true;
                            $error_image="The image contains medical content that may be inappropriate.";
                        }
                        if (in_array("Violence", $gemini_filters) && in_array($safeSearch['violence'], $threshold)) {
                            $image_unsafe=true;
                            $error_image="The image contains violent content.";
                        }
                        if (in_array("Racy", $gemini_filters) && in_array($safeSearch['racy'], $threshold)) {
                            $image_unsafe=true;
                            $error_image="The image contains racy or suggestive content.";
                        }
                        
                        if($image_unsafe==true && !empty($error_image)) {
                            echo json_encode(['contetnerror' => $error_image]);
                            die();
                        } else {
                            echo json_encode(['success' => "ok"]);
                            die();
                        }
                    
                    } else {
                        echo json_encode(['contetnerror' => "No valid response received. Response: " . (isset(json_decode($response)->error->message)?json_decode($response)->error->message:'')]);die();
                    }
                } catch (Exception $e) {
                    echo json_encode(['contetnerror' => $e->getMessage()]);
                    die();
                }
                
            }
        }
    }

} catch (RuntimeException $e) {
    echo json_encode(['contetnerror' => $e->getMessage()]);
    die();
}


?>
