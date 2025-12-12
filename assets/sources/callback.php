<?php
require('../includes/config.php');
require 'aws/aws-autoloader.php';
use Aws\S3\S3Client;
use Aws\S3\Exception\S3Exception;
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
function aws($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT setting_val FROM plugins_settings where plugin = 'amazon' and setting = '".$val."'");
    $result = $config->fetch_object();
    return $result->setting_val;
}
function getphoto($val) {
    global $mysqli;
    $config = $mysqli->query("SELECT photo FROM users_photos where media_id = '".$val."'");
    $result = $config->fetch_object();
    return $result->photo;
}
$payload = @file_get_contents('php://input');
$content = json_decode($payload, true);
$media_id = $content['media']['id'];
$request_id = $content['request'];
$data = $content['data'];
$status = $content['data']['status'];
if($content['data']['status']=="finished") {
    $nudity=false;
    foreach($content['data']['frames'] as $output) {
        if($output['nudity']['sexual_activity'] >= 0.1 || $output['nudity']['sexual_display'] >= 0.1 || $output['nudity']['erotica'] >= 0.1 || $output['nudity']['sextoy'] >= 0.1 || $output['nudity']['suggestive'] >= 0.2) {
            $nudity=true;
        }
    }
    if($nudity) {
        $mysqli->query("DELETE FROM users_photos where media_id = '".$media_id."'");
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
                
                $result = $s3->deleteObject(array(
                    'Bucket' => $bucketName,
                    'Key'    => basename(getphoto($media_id))
                ));
            } catch (Exception $e) {
                die("Error: " . $e->getMessage());
            }
        }
    } else {
        $mysqli->query("UPDATE users_photos SET approved = 1,status='Approved' WHERE media_id = '".$media_id."'");
    }
    
}


?>