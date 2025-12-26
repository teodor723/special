<?php 
/* Belloo Software by https://premiumdatingscript.com */

// Enable CORS for React app
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Max-Age: 3600');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    header('Content-Type: application/json');
    http_response_code(200);
    exit;
}

require_once('../assets/includes/core.php');
if(!isset($_GET['page'])){
    $_GET['page'] = 'index';
}

if($sm['plugins']['autoRegister']['enabled'] == 'Yes' && $logged == false){
    $ip = getUserIpAddr();
    $checkIp = getData('users','id','WHERE ip = "'.$ip.'"');
    if($checkIp == 'noData'){
        $_SESSION['user'] = $checkIp;
        getUserInfo($checkIp,0);
        checkUserPremium($checkIp);
        $sm['user_notifications'] = userNotifications($checkIp);
        
        $sm['lang'] = siteLang($sm['user']['lang']);
        $sm['alang'] = appLang($sm['user']['lang']);
        $sm['elang'] = emailLang($sm['user']['lang']);
        $sm['seoLang'] = seoLang($sm['user']['lang']);
        $sm['landingLang'] = landingLang($sm['user']['lang'],$landingTheme,$_SESSION['landingPreset']);
        $sm['genders'] = siteGenders($sm['user']['lang']);      

        $modPermission = array();
        if($sm['user']['admin'] >= 1){
            $moderationList = getArray('moderation_list','','moderation ASC');
            foreach ($moderationList as $mod) {  
                if($sm['user']['admin'] == 1){
                    $modPermission[$mod['moderation']] = 'Yes';
                } else {
                    $modVal = getData('moderators_permission','setting_val','WHERE setting = "'.$mod['moderation'].'" AND id = "'.$sm['user']['moderator'].'"');
                    $modPermission[$mod['moderation']] = $modVal;
                }
            }      
        }
        $sm['moderator'] = $modPermission;

        $time = time();
        $logged = true; 
        $ip = getUserIpAddr();
        if($sm['user']['ip'] != $ip){
            $mysqli->query("UPDATE users set ip = '".$ip."' where id = '".$checkIp."'");
        }
        if($sm['user']['last_access'] < $time || $sm['user']['last_access'] == 0){  
            $mysqli->query("UPDATE users set last_access = '".$time."' where id = '".$checkIp."'"); 
        }
    } else {
        
        $rand = rand(0,1012451);
        $rand2 = rand(0,1012451);
        $rand3 = rand(0,1012451);
        $salt = base64_encode($rand);
        $pswd = crypt($sm['plugins']['autoRegister']['guestDefaultPswd'],$salt);
        $lang = getData('languages','id','WHERE id = '.$_SESSION['lang']);
        if($lang == 'noData'){
            $lang = $sm['plugins']['settings']['defaultLang'];
        }  

        $name = $sm['plugins']['autoRegister']['guestDefaultName'].' '.$rand;
        $username = $sm['plugins']['autoRegister']['guestDefaultName'].$rand.$rand2;

        $siteEmail = explode('@',$sm['plugins']['settings']['siteEmail']);
        $email = $username.'@'.$siteEmail[1];
        $age = 29;
        $birthday = date('F', mktime(0, 0, 0, 06, 10)).' 15, 1990';

        if($_GET['page'] == $sm['plugins']['autoRegister']['guestCustomOneUrl']){
            $gender = $sm['plugins']['autoRegister']['guestCustomOneGender'];
            $looking = $sm['plugins']['autoRegister']['guestCustomOneLooking'];
        } else if($_GET['page'] == $sm['plugins']['autoRegister']['guestCustomTwoUrl']){
            $gender = $sm['plugins']['autoRegister']['guestCustomTwoGender'];
            $looking = $sm['plugins']['autoRegister']['guestCustomTwoLooking'];         
        } else {
            $gender = $sm['plugins']['autoRegister']['guestDefaultGender'];
            $looking = $sm['plugins']['autoRegister']['guestDefaultLooking'];
        }

        $ip = getUserIpAddr();

        if(!empty($sm['plugins']['ipstack']['key'])){
            //$location = json_decode(file_get_contents('http://api.ipstack.com/'.$ip.'?access_key='.$sm['plugins']['ipstack']['key']));
            //$city = $location->city;    
            //$country = $location->country_name;     
            //$lat = $location->latitude;     
            //$lng = $location->longitude;
			$city = 'Los Angeles';
            $country = 'United States';
            $lat = '-56.1250444';
            $lng = '-34.8872424';
        } else {
            $city = 'Los Angeles';
            $country = 'United States';
            $lat = '-56.1250444';
            $lng = '-34.8872424';
        }

        $date = date('m/d/Y', time());
        
        $dID = 0;
        $bio = $sm['lang'][322]['text']." ".$name.", ".$age." ".$sm['lang'][323]['text']." ".$city." ".$country;

        $query = "INSERT INTO users (name,email,pass,age,birthday,gender,city,country,lat,lng,looking,lang,join_date,bio,s_gender,s_age,credits,online_day,password,ip,last_access,username,join_date_time,app_id) VALUES ('".$name."', '".$email."','".$pswd."','".$age."','".$birthday."','".$gender."','".$city."','".$country."','".$lat."','".$lng."','".$looking."','".$lang."','".$date."','".$bio."','".$looking."','18,29,1',0,0,'".$sm['plugins']['autoRegister']['guestDefaultPswd']."','".$ip."','".time()."','".$username."','".time()."','".$dID."')";    
        if ($mysqli->query($query) === TRUE) {
            $last_id = $mysqli->insert_id;
            $mysqli->query("INSERT INTO users_videocall (u_id) VALUES ('".$last_id."')");   

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

            $mysqli->query("INSERT INTO users_notifications (uid) VALUES ('".$last_id."')");
            $mysqli->query("INSERT INTO users_extended (uid,field1) VALUES ('".$last_id."','".$sm['lang'][224]['text']."')");


            $_SESSION['user'] = $last_id;
            getUserInfo($last_id);
            checkUserPremium($_SESSION['user']);
            $sm['user_notifications'] = userNotifications($_SESSION['user']);
            
            $sm['lang'] = siteLang($sm['user']['lang']);
            $sm['alang'] = appLang($sm['user']['lang']);
            $sm['elang'] = emailLang($sm['user']['lang']);
            $sm['seoLang'] = seoLang($sm['user']['lang']);
            $sm['landingLang'] = landingLang($sm['user']['lang'],$landingTheme,$_SESSION['landingPreset']);
            $sm['genders'] = siteGenders($sm['user']['lang']);      

            $modPermission = array();
            if($sm['user']['admin'] >= 1){
                $moderationList = getArray('moderation_list','','moderation ASC');
                foreach ($moderationList as $mod) {  
                    if($sm['user']['admin'] == 1){
                        $modPermission[$mod['moderation']] = 'Yes';
                    } else {
                        $modVal = getData('moderators_permission','setting_val','WHERE setting = "'.$mod['moderation'].'" AND id = "'.$sm['user']['moderator'].'"');
                        $modPermission[$mod['moderation']] = $modVal;
                    }
                }      
            }
            $sm['moderator'] = $modPermission;

            $time = time();
            $logged = true;     
            
            if($sm['user']['ip'] != $ip){
                $mysqli->query("UPDATE users set ip = '".$ip."' where id = '".$_SESSION['user']."'");
            }
            if($sm['user']['last_access'] < $time || $sm['user']['last_access'] == 0){  
                $mysqli->query("UPDATE users set last_access = '".$time."' where id = '".$_SESSION['user']."'");    
            }
        }           
    }   
}

// Prepare user data
$user = array();
$randomFakeOnline = [];
$oneSignalID = -1;

if (isset($_SESSION['user']) && is_numeric($_SESSION['user']) && $_SESSION['user'] > 0) {
    if(isset($_GET['logout'])){
        unset($_SESSION['user']);
        setcookie("user", 0, time() - 3600); 
        $oneSignalID = 0;
    } else {
        $user = $sm['user'];      
        $randomFakeOnline = getRandomFakeOnline('id',$sm['user']['looking']);
        $oneSignalID = $_SESSION['user'];
    }
}

// Get theme settings
$themeFilter = 'WHERE theme ="'.$sm['settings']['desktopTheme'].'" AND preset = "'.$sm['settings']['desktopThemePreset'].'"';
$sm['theme'] = json_decode(getData('theme_preset','theme_settings',$themeFilter),true);

// Prepare age range
$ag1 = isset($sm['user']['id']) ? explode(',', $sm['user']['s_age'])[0] : $sm['plugins']['settings']['minRegisterAge'];
$ag2 = isset($sm['user']['id']) ? explode(',', $sm['user']['s_age'])[1] : 60;

// Get user IP
$ip = $_SERVER['REMOTE_ADDR'];
$ipstack = array('127.0.0.1', "::1");
if(in_array($_SERVER['REMOTE_ADDR'], $ipstack)){
    $ip = '192.196.0.1';
}

// Prepare mobile theme
$mobileThemeDesign = getData('theme_preset','theme_settings','where theme = "'.$sm['settings']['mobileThemePreset'].'" and preset = "'.$sm['settings']['mobileTheme'].'"');
$mainFont = ($sm['settings']['mobileTheme'] == 'twigo') ? 'Inter' : 'Rubik';

if($mobileThemeDesign == 'noData'){
    $mobile_theme = new stdClass();
} else {
    $mobile_theme = json_decode($mobileThemeDesign);
}
if(!isset($mobile_theme->logo)) $mobile_theme->logo = new stdClass();
$mobile_theme->logo->val = ($_SERVER['SERVER_NAME']=='special-dating.com')?$sm['metasettings']['website_mobile_logo']:getvaluemetax('_domain_mobile_logo',$_SERVER['SERVER_NAME']);

// Check plugins
$checkFeedPlugin = getData('plugins','enabled','where name = "fgfeed"');
$checkLivePlugin = getData('plugins','enabled','where name = "live"');
$checkReelsPlugin = getData('plugins','enabled','where name = "reels"');
$checkStoryPlugin = getData('plugins','enabled','where name = "story"');

// Prepare dating data
$budget = explode(',', $sm['plugins']['date']['budgetValues']);
$dating_types = explode(',', $sm['plugins']['date']['type']);
$dating_cities = getArrayDSelected('city','users');

// Prepare Pusher data
$isIframe = false;
if(isset($_GET['iframe']) || (isset($_SERVER['HTTP_REFERER']) && strpos($_SERVER['HTTP_REFERER'], 'iframe') !== false)){
    $isIframe = true;
}

// Build response data
$response = array(
    // Basic config
    'site_url' => $sm['config']['site_url'],
    'siteUrl' => $sm['config']['site_url'],
    'ajax_path' => $sm['config']['ajax_path'],
    'theme_url' => $sm['config']['theme_url'],
    
    // Mobile settings
    'nextStory' => 0,
    'storyPage' => '',
    'mobileSite' => true,
    'mobileTheme' => $sm['settings']['mobileTheme'],
    'siteLang' => $_SESSION['lang'],
    'alang' => isset($sm['alang']) ? $sm['alang'] : appLang($_SESSION['lang']),
    'userId' => isset($user['id']) ? $user['id'] : 0,
    
    // Age range
    'ag1' => (int)$ag1,
    'ag2' => (int)$ag2,
    
    // User data
    'user' => $user,
    'oneSignalID' => $oneSignalID,
    'randomFakeOnline' => $randomFakeOnline,
    
    // Plugins and theme
    'plugin' => $sm['plugin'],
    'plugins' => $sm['plugins'],
    'site_theme' => $sm['theme'],
    'mobile_theme' => $mobile_theme,
    'account_basic' => $sm['basic'],
    
    // Counts
    'allG' => count($sm['genders']) + 1,
    
    // File filters
    'extFilter' => array("jpg", "jpeg", "png", "mp4", "ogg", "webm"),
    'storyAlbumFilter' => array("video/3gpp", "video/mpeg", "video/mp4","video/webm","video/ogg"),
    
    // Dating data
    'dating_plans' => array(),
    'dating_plans_length' => 0,
    'dating_budgets' => $budget,
    'dating_types' => $dating_types,
    'dating_cities' => $dating_cities,
    
    // API URLs
    'gUrl' => $sm['config']['ajax_path'].'/rt.php',
    'aUrl' => $sm['config']['ajax_path'].'/api.php',
    
    // Pusher config
    'pusher' => array(
        'key' => $sm['plugins']['pusher']['key'],
        'cluster' => $sm['plugins']['pusher']['cluster'],
        'isIframe' => $isIframe
    ),
    
    // IP and location
    'userIp' => $ip,
    
    // Initialization flags
    'uploadStory' => false,
    'current_user_id' => 0,
    'ph' => 0,
    'upphotos' => array(),
    'rnd_f_c' => 0,
    
    // UI settings
    'mainFont' => $mainFont,
    
    // Plugin enabled flags
    'liveEnabled' => ($checkLivePlugin == 1),
    'feedEnabled' => ($checkFeedPlugin == 1),
    'reelsEnabled' => ($checkReelsPlugin == 1),
    'storyEnabled' => ($checkStoryPlugin == 1),
    
    // Meta settings
    'google_analytics_id' => $sm['metasettings']['google_analytics_id'],
    
    // Custom HTML
    'customHtml' => array(
        'mobile_header' => $sm['plugins']['customHtml']['mobile_header'],
        'mobile_footer' => $sm['plugins']['customHtml']['mobile_footer']
    ),
    
    // Upload endpoint
    'upload_endpoint' => $sm['config']['site_url'].'assets/sources/upload.php',
    
    // Adult plugin (if applicable)
    'adultPlugin' => null
);

// Handle adult plugin if enabled
if($sm['plugins']['adultplugin']['enabled'] == 'Yes') {
    $age_result = explode("-", $sm['plugins']['adultplugin']['age_range']);
    $min_age = isset($age_result[0]) ? $age_result[0] : 0;
    $max_age = isset($age_result[1]) ? $age_result[1] : 0;
    $adult_countries = explode(",", $sm['plugins']['adultplugin']['countries']);
    $adult_gender = ($sm['plugins']['adultplugin']['gender']=="Male") ? 1 : (($sm['plugins']['adultplugin']['gender']=="Female") ? 2 : '');
    
    $showAdultPlugin = false;
    if(isset($sm['user']['age']) && $sm['user']['age'] >= $min_age && $sm['user']['age'] <= $max_age) {
        if(in_array($sm['user']['country'], $adult_countries)) {
            if($sm['plugins']['adultplugin']['gender'] == "Both" || $sm['user']['gender'] == $adult_gender) {
                $showAdultPlugin = true;
            }
        }
    }
    
    if($showAdultPlugin) {
        $response['adultPlugin'] = array(
            'enabled' => true,
            'url' => $sm['plugins']['adultplugin']['url'],
            'icon' => $sm['config']['site_url'].'mobile/twigo/img/flammable_298291.png'
        );
    }
}

// Output JSON
header('Content-Type: application/json');
echo json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
exit;
?>
