<?php
if (isset($_SERVER['HTTP_ORIGIN'])) {
    header("Access-Control-Allow-Origin: {$_SERVER['HTTP_ORIGIN']}");
    header('Access-Control-Allow-Credentials: true');
    header('Access-Control-Max-Age: 86400');
}
if ($_SERVER['REQUEST_METHOD'] == 'OPTIONS') {
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_METHOD']))
        header("Access-Control-Allow-Methods: GET, POST, OPTIONS");         
    if (isset($_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']))
        header("Access-Control-Allow-Headers: {$_SERVER['HTTP_ACCESS_CONTROL_REQUEST_HEADERS']}");
    exit(0);
}
header('Content-Type: application/json');
require_once('../assets/includes/core.php');

$response = array();
$response['success'] = false;
$response['valid'] = 'No';
$response['message'] = '';


if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $input = json_decode(file_get_contents('php://input'), true);
    
    if (!isset($input['action']) || $input['action'] !== 'firebaseAuth') {
        $response['message'] = 'Invalid action';
        echo json_encode($response);
        exit;
    }
    
    if (!isset($input['token']) || empty($input['token'])) {
        $response['message'] = 'Firebase token is required';
        echo json_encode($response);
        exit;
    }
    
    $token = $input['token'];
    $email = isset($input['email']) ? secureEncode($input['email']) : '';
    $name = isset($input['name']) ? secureEncode($input['name']) : '';
    $photo = isset($input['photo']) ? secureEncode($input['photo']) : '';
    $provider = isset($input['provider']) ? secureEncode($input['provider']) : 'email';
    $gender = isset($input['gender']) ? secureEncode($input['gender']) : 1;
    $age = isset($input['age']) ? secureEncode($input['age']) : '';
    $day = isset($input['day']) ? secureEncode($input['day']) : '';
    $month = isset($input['month']) ? secureEncode($input['month']) : '';
    $year = isset($input['year']) ? secureEncode($input['year']) : '';
    $birthday = isset($input['birthday']) ? secureEncode($input['birthday']) : '';
    $looking = isset($input['looking']) ? secureEncode($input['looking']) : '';
    $city = isset($input['city']) ? secureEncode($input['city']) : '';
    $country = isset($input['country']) ? secureEncode($input['country']) : '';
    $lat = isset($input['lat']) ? secureEncode($input['lat']) : '';
    $lng = isset($input['lng']) ? secureEncode($input['lng']) : '';
    $ref = isset($input['ref']) ? secureEncode($input['ref']) : '';
    $checkbox = isset($input['checkbox']) ? secureEncode($input['checkbox']) : '';
    
    if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $response['message'] = 'Valid email is required';
        echo json_encode($response);
        exit;
    }
    
    try {
        $firebase_api_key = 'AIzaSyD3d2nGVAgcChfSvnDWT_r-r_Hvnm5FOZ8';
        $verify_url = 'https://www.googleapis.com/identitytoolkit/v3/relyingparty/getAccountInfo?key=' . $firebase_api_key;
        $verify_data = array('idToken' => $token);
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $verify_url);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($verify_data));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        
        $verify_response = curl_exec($ch);
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curl_error = curl_error($ch);
        curl_close($ch);
        
        if (!empty($curl_error)) {
            throw new Exception('Token verification error: ' . $curl_error);
        }
        
        if ($http_code !== 200) {
            throw new Exception('Invalid or expired Firebase token (HTTP ' . $http_code . ')');
        }
        
        $verify_result = json_decode($verify_response, true);
        
        if (isset($verify_result['error'])) {
            $error_msg = isset($verify_result['error']['message']) ? $verify_result['error']['message'] : 'Unknown error';
            throw new Exception('Token verification failed: ' . $error_msg);
        }
        
        if (!isset($verify_result['users']) || empty($verify_result['users'])) {
            throw new Exception('Token verification failed: No user found');
        }
        
        $verified_user = $verify_result['users'][0];
        $verified_email = isset($verified_user['email']) ? $verified_user['email'] : '';
        $verified_uid = isset($verified_user['localId']) ? $verified_user['localId'] : '';
        $verified_provider = 'password';
        
        if (isset($verified_user['providerUserInfo']) && !empty($verified_user['providerUserInfo'])) {
            $verified_provider = $verified_user['providerUserInfo'][0]['providerId'];
        }
        
        if (empty($verified_email) || $verified_email !== $email) {
            throw new Exception('Token email does not match provided email');
        }
        
        if ($provider == 'google' && $verified_provider !== 'google.com') {
            throw new Exception('Token provider does not match (expected google.com, got ' . $verified_provider . ')');
        }
        
        if ($provider == 'email' && $verified_provider !== 'password') {
            throw new Exception('Token provider does not match (expected password, got ' . $verified_provider . ')');
        }
        
        $token = secureEncode($verified_uid);
        
    } catch (Exception $e) {
        $response['message'] = 'Token verification error: ' . $e->getMessage();
        $response['error_details'] = $e->getFile() . ':' . $e->getLine();
        echo json_encode($response);
        exit;
    }
    
    // print_r($token);die();

    
    $dID = isset($input['dID']) ? secureEncode($input['dID']) : 0;
    
    $email_check = $mysqli->query("SELECT id, email, name, verified FROM users WHERE email = '".$email."'");
    
    if ($email_check->num_rows > 0) {
        $user = $email_check->fetch_object();
        $user_id = $user->id;
        
        $firebase_id_field = $provider . '_id';
        $check_firebase = $mysqli->query("SELECT id FROM users WHERE ".$firebase_id_field." = '".$token."' OR email = '".$email."'");
        
        if ($check_firebase->num_rows == 0 || $check_firebase->fetch_object()->id == $user_id) {
            if ($provider == 'google' && !empty($token)) {                
                $mysqli->query("UPDATE users SET google_id = '".$token."' WHERE id = '".$user_id."'");
            } elseif ($provider == 'email' && !empty($token)) {
                $mysqli->query("UPDATE users SET firebase_uid = '".$token."' WHERE id = '".$user_id."'");
            }
            
            // if (!empty($photo) && $photo != '') {
            //     $mysqli->query("UPDATE users SET photo = '".$photo."' WHERE id = '".$user_id."'");
            // }
            
            $_SESSION['user'] = $user_id;
            setcookie("user", $user_id, 2147483647);
            getUserInfo($user_id, 0);
            
            $mysqli->query("UPDATE users SET app_id = '".$dID."', last_access = '".time()."' WHERE id = '".$user_id."'");
            
            $response['success'] = true;
            $response['valid'] = 'Yes';
            $response['message'] = 'Login successful';
            $response['user_id'] = $user_id;
            echo json_encode($response);
            exit;
        }
    } else {
        if(empty($name) == true || empty($email) == true){
            $response['message'] = 'Error'.$sm['lang'][182]['text'];
            echo json_encode($response);
            exit;
        }
        
        if(!isset($gender) || empty($gender)){
            $gender = 1;
        }
        
        if(!isset($looking) || empty($looking)){
            $looking=2;
        }
        if(empty($day) || empty($month) || empty($year)){
            $year = 2000;$month = 1;$day = 1;            
        }
        
        
        $ip = getUserIpAddr();
        $date = date('m/d/Y', time());
        $country_code = "";
        
        if($city == "" || $city == NULL){
            $city = $country;
        }
        
        if($lat == "" || $lat == NULL){
            if(isset($sm['plugins']['settings']['geolocation']) && !empty($sm['plugins']['settings']['geolocation'])) {
                $details=@file_get_contents('https://api.geoapify.com/v1/ipinfo?ip='.$ip.'&apiKey='.$sm['plugins']['settings']['geolocation']);
                $details=json_decode($details);
                if(isset($details->city->name)){
                    $city=$details->city->name;
                }
                if(isset($details->country->name_native)){
                    $country=$details->country->name_native;
                }
                if(isset($details->country->iso_code)){
                    $country_code=$details->country->iso_code;
                }
                if(isset($details->location->latitude)){
                    $lat=$details->location->latitude;
                }
                if(isset($details->location->longitude)){
                    $lng=$details->location->longitude;
                }
            } else {
                $city = '';
                $country = '';
                $lat = '34.05223';
                $lng = '-118.24368';
            }
        }      
        
        
        if(empty($photo)){
            $photo = '';
        } else {
            $photo = secureEncode($photo);
        }
        
        $birthday = date('F', mktime(0, 0, 0, $month, 10)).' '.$day.', '.$year;
        $age = date('Y') - intval($year);
        
        $bio = $sm['lang'][322]['text']." ".$name.", ".$age." ".$sm['lang'][323]['text']." ".$city." ".$country;
        
        $sage = '18,60,1';
        $username = preg_replace('/([^@]*).*/', '$1', $email);
        $checkUsername = checkIfExist('users','username',$username);
        if($checkUsername == 1){
            $username = preg_replace('/([^@]*).*/', '$1', $email).uniqid();
        }
        
        if(checkIfExist('blocked_ips','ip',$ip) == 1){
            $response['message'] = 'Error'.$sm['lang'][656]['text'];
            echo json_encode($response);
            exit;
        }
        
        if(checkIfExist('blocked_users','email',$email) == 1){
            $response['message'] = 'Error'.$sm['lang'][656]['text'];
            echo json_encode($response);
            exit;
        }
        
        
        $lang = getData('languages','id','WHERE id = '.$_SESSION['lang']);
        if($lang == 'noData'){
            $lang = $sm['plugins']['settings']['defaultLang'];
        }
        
        if(empty($ref)){
            if(isset($_COOKIE['ref'])){
                $ref = $_COOKIE['ref'];
            }
        }
        
        $salt = base64_encode($name.$email);
        $pswd = crypt($token, $salt);
        
        $firebase_uid = '';
        $google_id = '';
        if ($provider == 'google') {
            $google_id = $token;
        } else {
            $firebase_uid = $token;
        }
        
        $query = "INSERT INTO users (name,email,pass,age,birthday,gender,city,country,lat,lng,looking,lang,join_date,bio,s_gender,s_age,credits,online_day,ip,last_access,username,join_date_time,referral,country_code,firebase_uid,google_id,verified) VALUES ('".$name."', '".$email."','".$pswd."','".$age."','".$birthday."','".$gender."','".$city."','".$country."','".$lat."','".$lng."','".$looking."','".$lang."','".$date."','".$bio."','".$looking."','".$sage."',120,0,'".$ip."','".time()."','".$username."','".time()."','".$ref."','".$country_code."','".$firebase_uid."','".$google_id."',1)";
        
        //echo $query;

        if ($mysqli->query($query) === TRUE) {
            $last_id = $mysqli->insert_id;
            
            $mysqli->query("INSERT INTO users_videocall (u_id,peer_id) VALUES ('".$last_id."',0)");
            
            $free_premium = 0;
            $allG = count(siteGenders($lang));
            $allG = $allG + 1;
            if($sm['plugins']['rewards']['freePremiumGender'] == $gender || $sm['plugins']['rewards']['freePremiumGender'] == $allG){
                $free_premium = $sm['plugins']['rewards']['freePremium'];
            }
            $time = time();
            $extra = 86400 * $free_premium;
            $premium = $time + $extra;
            $mysqli->query("INSERT INTO users_premium (uid,premium) VALUES ('".$last_id."','".$premium."')");
            
            if($photo != ''){
                $query2 = "INSERT INTO users_photos (u_id,photo,profile,thumb,approved) VALUES ('".$last_id."','".$photo."',1,'".$photo."',1)";
                $mysqli->query($query2);
            }
            
            $mysqli->query("INSERT INTO users_notifications (uid) VALUES ('".$last_id."')");
            $mysqli->query("INSERT INTO users_extended (uid) VALUES ('".$last_id."')");
            
            setcookie("user", $last_id, 2147483647);
            $_SESSION['user'] = $last_id;
            
            if($sm['plugins']['email']['enabled'] == 'Yes'){
                if($sm['plugins']['settings']['forceEmailVerification'] == 'Yes'){
                    welcomeMailVerification($name,$last_id,$email,'');
                } else {
                    welcomeMailNotification($name,$email,'');
                }
            }
            
            $response['success'] = true;
            $response['valid'] = 'Yes';
            $response['message'] = 'Registration successful';
            $response['user_id'] = $last_id;
            echo json_encode($response);
            exit;
        } else {
            $response['message'] = 'Registration failed: ' . $mysqli->error;
            echo json_encode($response);
            exit;
        }
    }
} else {
    $response['message'] = 'Invalid request method';
    echo json_encode($response);
    exit;
}


