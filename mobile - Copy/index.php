<?php 
/* Belloo Software by https://premiumdatingscript.com */
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
            $location = json_decode(file_get_contents('http://api.ipstack.com/'.$ip.'?access_key='.$sm['plugins']['ipstack']['key']));
            $city = $location->city;    
            $country = $location->country_name;     
            $lat = $location->latitude;     
            $lng = $location->longitude;
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
?>
<!DOCTYPE html>
<html>
  <head>
	<title><?php echo $sm['metasettings']['website_title']; ?></title>
    <meta http-equiv="Content-Type" content="text/html; charset=UTF-8"/>
    <meta http-equiv="X-UA-Compatible" content="IE=Edge">
    <meta name="theme-color" content="#fff">
    <meta name="format-detection" content="telephone=no">
    <meta name="description" content="<?php echo $sm['metasettings']['description']; ?>">
    <meta name="keywords" content="<?php echo $sm['metasettings']['keywords']; ?>">
    <meta name="author" content="<?php echo $sm['metasettings']['author']; ?>"/>
    <meta name="robots" content="<?php echo $sm['metasettings']['robots']; ?>">
    <link rel="canonical" href="<?php echo $sm['metasettings']['canonical']; ?>">
    <meta property="fb:app_id" content="<?= siteConfig('fb_app_id'); ?>" />
    
    <meta property="og:title" content="<?php echo $sm['metasettings']['og_title']; ?>" />
    <meta property="og:url" content="<?php echo $sm['metasettings']['og_url']; ?>" />
    <meta property="og:image" content="<?= $sm['metasettings']['og_image']; ?>" />
    <meta property="og:description" content="<?php echo $sm['metasettings']['og_description']; ?>" />
    <meta property="og:type" content="<?php echo $sm['metasettings']['og_type']; ?>" />
    <meta property="og:site_name" content="<?php echo $sm['metasettings']['og_site_name']; ?>" />
    
    <meta name="twitter:card" content="<?php echo $sm['metasettings']['twitter_card']; ?>">
    <meta name="twitter:title" content="<?php echo $sm['metasettings']['twitter_title']; ?>">
    <meta name="twitter:description" content="<?php echo $sm['metasettings']['twitter_description']; ?>">
    <meta name="twitter:image" content="<?php echo $sm['metasettings']['twitter_image']; ?>">
    <meta name="twitter:site" content="<?php echo $sm['metasettings']['twitter_site']; ?>">
    <meta name="twitter:creator" content="<?php echo $sm['metasettings']['twitter_creator']; ?>">
	<meta name="viewport" content="width=device-width, height=device-height, initial-scale=1.0, maximum-scale=1.0">
    <link rel="manifest" href="manifest.webmanifest">
    <meta name="application-name" content="<?= $sm['config']['name']; ?>">
    <link rel="icon" type="image/png" href="<?php echo ($_SERVER['SERVER_NAME']=='special-dating.com')?$sm['metasettings']['website_favicon']:getvaluemetax('_domain_favicon',$_SERVER['SERVER_NAME']); ?>" sizes="32x32">
    <?php echo $sm['metasettings']['schema_markup']; ?>
    <base href="<?= $sm['config']['site_url']; ?>mobile/">    
    <link href="css/magic.min.css" rel="stylesheet">
    <link href="css/style.css" rel="stylesheet">
    <link href="css/belloo20.css" rel="stylesheet">       
    <link href="css/belloo.css" rel="stylesheet">
    <link href="css/vivify.min.css" rel="stylesheet"> 
    <link href="css/i.css" rel="stylesheet">
    <style>
        input[type="date"] {
        font-size: 1.1rem;
        padding: 0.5rem 0.75rem;
        border-radius: 10px;
        border: none;
        outline: none;
        width: 250px;
        box-sizing: border-box;
        -webkit-appearance: textfield; /* This fixes some issues on Chrome iOS */
      }
      /* For Firefox, to disable the default calendar arrow turning blocky, remove this */
      input[type="date"]::-moz-inner-spin-button,
      input[type="date"]::-moz-calendar-picker-indicator {
        display: block;
      }
    </style>
   
    <link href="https://fonts.googleapis.com/css?family=Montserrat" rel="stylesheet">

    <script>
	var site_url = '<?= $sm['config']['site_url']; ?>';
    var siteUrl = '<?= $sm['config']['site_url']; ?>';
	</script>
    <script src="lib/ionic/js/ionic.bundle.js"></script>
    <script src="lib/collide/collide.js"></script>
    <script src="lib/angular-elastic/elastic.js"></script>
    <script src="js/giphy.js"></script>
    <script src="lib/ngCordova/dist/ng-cordova.js"></script>
	<script src="lib/gsap/src/uncompressed/TweenMax.js"></script>
	<script src="lib/ngFx/dist/ngFx.js"></script>
    <script src="lib/hammer/hammer.js"></script>
    <link rel="icon" type="image/png" href="<?= $sm['theme']['favicon']['val']; ?>" sizes="32x32">
    <script src="lib/angular-animate/angular-animate.js"/></script>
    <script src="https://angular-ui.github.io/ui-router/release/angular-ui-router.min.js"></script>    
    <link rel="stylesheet" href="lib/awlert/dist/css/awlert.css">
    <script src="lib/awlert/dist/js/awlert.min.js"></script>
	<script src="lib/ng-cordova-oauth/dist/ng-cordova-oauth.min.js"></script>
    <script src="https://js.pusher.com/8.0.1/pusher.min.js"></script>
    <link href="css/autocomplete.css" rel="stylesheet" type="text/css" />       
    <link rel="stylesheet" href="<?php echo $sm['config']['theme_url']; ?>/css/vendor/slim-lightbox.min.css">
    <link rel="stylesheet" type="text/css" href="<?php echo $sm['config']['theme_url']; ?>/css/vendor/little-widgets.css"/>  
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.8/css/intlTelInput.css" />  

    <link rel="stylesheet" href="../themes/default/css/crossplatform.css"/>

    <?php 
        foreach($sm['plugin'] as $plugin){ ?>
            <?php if(!empty($plugin['css_file'])){
                $show = true;
                if($show){
                    echo '<link rel="stylesheet" id="style'.$plugin['css_file'].'" type="text/css" href="'.$sm['config']['theme_url'].'/css/vendor/'.$plugin['css_file'].'.css"/>';
                }
            } 
        } 
    ?>

    <link rel="stylesheet" type="text/css" href="css/live.css"/>    
    <script>
        var nextStory = 0;
        var storyPage = '';
        var mobileSite = true;  
        var mobileTheme = '<?= $sm['settings']['mobileTheme'];?>'; 
        var siteLang = <?= $_SESSION['lang']; ?>;
        var userId=0;
        <?php 
            if(!isset($sm['user']['id'])){
                echo '
                    var ag1 = '.$sm['plugins']['settings']['minRegisterAge'].';
                    var ag2 = 60;
                ';
            } else {
                $e_age = explode( ',', $sm['user']['s_age'] );
                $age1 = $e_age[0];
                $age2 = $e_age[1];
                echo '
                    var ag1 = '.$age1.';
                    var ag2 = '.$age2.';
                ';
            }
        ?>
    </script>
	<script>
    var globalStreams = [];
    var streamName = '';
    var in_live_mingle = false;
    var live_mingle_filter = 0;
    var streamGiftCredits,streamGiftIcon;
    var oneSignalID=-1,notificationOpenedCallback=function(e){},reg=0,c_quantity=0,lid=43,loader=0,p_quantity=0,ticky=!1,app,interval,c_price,reg_photo="",reg_username="",reg_lat="",reg_lng="",reg_city="",reg_country="",reg_thumb="",p_price,url,mobile=!1,chatLimit=!1,config="",peer,in_videocall=!1,site_prices,account_basic,account_premium,lang,tlang,alang,logo,online=[],unread=[],usPhotos="",cu,regName,localStream,tlang,current_user,user="",show_chat_premium=1,game_array,user_info,current_user_id=0,user_name,meet_limit=0,meet_pages=0,spotlight=[],da=site_url,chats=[],f=da,matche=[],visitors=[],mylikes=[],superlikes=[],myfans=[],cards=[],gresult=[],chatUser,s_age,bottom=!1,s_radius,s_gender,onlineMeet=0,chatInterval,y=f,user_country,u=y,a=u,user_city,site_lang,site_config,totalDiscoverStories=0,storiesGlobal=[],goBackGlobal='',secu=0,minu=0,sec=0,live_mingle_filter_buttons=[];
    	<?php 
        $user = array();
        $randomFakeOnline = [];

        if (isset($_SESSION['user']) && is_numeric($_SESSION['user']) && $_SESSION['user'] > 0) {
            if(isset($_GET['logout'])){
                unset($_SESSION['user']);
                setcookie("user", 0, time() - 3600); 
                echo 'oneSignalID = 0;';             
            } else {
                $user = $sm['user'];      
                $randomFakeOnline = getRandomFakeOnline('id',$sm['user']['looking']);
                echo 'oneSignalID = '.$_SESSION['user'].';';
            }
        }
        $themeFilter = 'WHERE theme ="'.$sm['settings']['desktopTheme'].'" AND preset = "'.$sm['settings']['desktopThemePreset'].'"';
        $sm['theme'] = json_decode(getData('theme_preset','theme_settings',$themeFilter),true);        
        $site_plugins = json_encode($sm['plugins']); 
        $site_theme = json_encode($sm['theme']);
        $allG = count($sm['genders']);
        $account_basic = json_encode($sm['basic']);
        $user = json_encode($user);  
        $allG = $allG + 1;
        $pk = $sm['plugins']['pusher']['key'];
        $budget = json_encode(explode( ',', $sm['plugins']['date']['budgetValues']));
        $dating_types = json_encode(explode( ',', $sm['plugins']['date']['type']));
        $dating_cities = json_encode(getArrayDSelected('city','users'));
        echo '
            var allG = '.$allG.';
            user = '.$user.';  
            var plugins = '. $site_plugins  . ';
            var site_theme = '. $site_theme  . ';  
            var uploadStory = false;
            var user_name;  
            var current_user_id = 0;
            var account_basic = '. $account_basic  . ';
            var ph = 0;
            var upphotos = [];
            var extFilter = ["jpg", "jpeg", "png", "mp4", "ogg", "webm"];
            var storyAlbumFilter = ["video/3gpp", "video/mpeg", "video/mp4","video/webm","video/ogg"];   
            var rnd_f_c = 0; 

            var dating_plans = [];
            var dating_plans_length = 0;
            var dating_budgets = '.$budget.';
            var dating_types = '.$dating_types.';
            var dating_cities = '.$dating_cities.';                   
        '; 
        echo '
        function request_source(){
            return \'' . $sm['config']['ajax_path'] . '\';
        }';
        ?>

        var gUrl = request_source()+'/rt.php';
        var aUrl = request_source()+'/api.php';  
        function inIframe () {
        try {
          return window.self !== window.top;
        } catch (e) {
          return true;
        }
        }

        var rt = '';
        var channel = '';
        if(inIframe() !== true){
            rt = new Pusher("<?= $pk; ?>", {
              cluster: "<?=$sm['plugins']['pusher']['cluster']; ?>"
            });
            console.log("<?= $pk; ?>");
            channel = rt.subscribe("<?= $pk; ?>");
        } else {
            rt = new Pusher("<?= $pk; ?>", {
              cluster: "<?=$sm['plugins']['pusher']['cluster']; ?>"
            });    
            channel = rt.subscribe("<?= $pk; ?>iframe");    
            console.log('iframe loaded no real time');
        }
    </script>
    <?php 
        $ip = $_SERVER['REMOTE_ADDR'];
        $ipstack = array('127.0.0.1', "::1");
        if(in_array($_SERVER['REMOTE_ADDR'], $ipstack)){
            $ip = '192.196.0.1';
        }
    ?>
    <script>
        var userIp = '<?= $ip; ?>';
        var upType = 0;
        var updateVisitorLanguage = false;
        var updateLocationRegister;
        var loadedMessages = [];
        var wmethod = '';
        <?php if(empty($randomFakeOnline)){ ?>
        var rnd_f = [];
        <?php } else { ?>
        var rnd_f = <?= json_encode($randomFakeOnline); ?>;
        <?php } ?>            
        
    </script>
 
    <script src="js/app.js"></script>
    <script src="js/controllers.js"></script>
    <script src="js/services.js"></script>
    <script src="js/directives.js"></script>
    <?php if($sm['plugins']['videocall']['enabled'] == 'Yes' || $sm['plugins']['liveDiscover']['enabled'] == "Yes"){ ?>
        <script src="https://unpkg.com/peerjs@latest/dist/peerjs.min.js"></script>
    <?php } ?>
    <script src="js/resource.min.js"></script>
    <script src="lib/jquery/jquery.js"></script>
    <?php if($sm['plugins']['adultplugin']['enabled'] == 'Yes') {
        $age_result=explode("-",$sm['plugins']['adultplugin']['age_range']);
        $min_age=isset($age_result[0])?$age_result[0]:0;
        $max_age=isset($age_result[1])?$age_result[1]:0;
        $adult_countries=explode(",",$sm['plugins']['adultplugin']['countries']);
        $adult_gender=($sm['plugins']['adultplugin']['gender']=="Male")?1:(($sm['plugins']['adultplugin']['gender']=="Female")?2:'');
        if($sm['user']['age'] >= $min_age && $sm['user']['age'] <= $max_age) {
            if(in_array($sm['user']['country'],$adult_countries)) {
                if($sm['plugins']['adultplugin']['gender'] == "Both" || $sm['user']['gender'] == $adult_gender) {
    ?>
    <script>
        var item='<div class="tabbar__item adultoweb"><div class="tabbar-item is-active" onclick="adultoweb()"><div class="tabbar-item__icon "><div class="icon icon--stretch mt-5px"><img src="<?php echo  $sm['config']['site_url']; ?>mobile/twigo/img/flammable_298291.png"></div></div></div></div>';
        shioc();
        function shioc(){
            var el=(typeof(document.querySelector('.adultoweb')) != 'undefined' && document.querySelector('.adultoweb') != null)?'exist':'not exist';
            if($(".tabbar .tabbar__items").css('display')=="flex" && el == "not exist"){
                $(".tabbar .tabbar__items").prepend(item);
            } else {
                setTimeout(shioc,500);
            }
        }
        function adultoweb(){window.location.href = "<?php echo $sm['plugins']['adultplugin']['url']; ?>";}
    </script>
    <?php }
            }
        }
    }
    ?>
    <link rel="stylesheet" href="<?php echo $sm['config']['theme_url']; ?>/css/vendor/scroller.css"/>
    <script src="<?php echo $sm['config']['theme_url']; ?>/js/vendor/scroller.js"></script> 
    <script src="https://cdnjs.cloudflare.com/ajax/libs/ng-flow/2.5.1/ng-flow-standalone.min.js"></script>
    <script src="https://connect.facebook.net/en_US/sdk.js"></script>  
    <link href="https://releases.transloadit.com/uppy/v3.27.1/uppy.min.css" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css?family=Rubik" rel="stylesheet" type="text/css"/>
    <style>
    .hide_el{display:none !important;}
    span.tphotos {
        z-index: 99999;
        position: absolute;
        display: flex;
        color: #fff;
        left: 5px;
        top: 5px;
        background: rgba(0,0,0,0.1);
        padding: 5px;
        border-radius: 5px;
        }
    .uppy-Dashboard-AddFiles-info{display:none !important;}</style>
    <script type="module">
      import {Uppy,Dashboard,Webcam,XHRUpload} from 'https://releases.transloadit.com/uppy/v3.27.1/uppy.min.mjs';
        const COMPANION_URL = "http://companion.uppy.io";
        const companionAllowedHosts = [];
      const uppy = new Uppy({ debug: true, autoProceed: false,restrictions: {limit:1,closeAfterFinish:true,allowMultipleUploadBatches:false,allowedFileTypes:['video/mp4','video/webm','video/ogg'],maxNumberOfFiles:1,minNumberOfFiles:1,maxFileSize:52428800,maxTotalFileSize:52428800},
          onBeforeUpload (files) {
            for (const [key, file] of Object.entries(files)) {
                var fileUrl = URL.createObjectURL(file.data);
                $('.upload-reel-container').show();
                $('.active-reels').hide();
                reels = [];
                $('.swiper-wrapper').removeAttr('style');
                $('.swiper-slide').removeAttr('style');          
                $('#uploadingReelVideo').addClass('uploadingReelVideo');
                $('#uploadingReelVideo').addClass('isPrivate');
                $('#uploadReelName').text(user_info.name);
                $('#uploadReelCaption').text('');
                $('#uploadReelPhoto').attr('src',user_info.profile_photo);
                $('.uploadingReel').show();
                $('#uploadingReelVideo').show();
                createReelPreview(file.data, fileUrl,key);
                $('.uploadingReelTitleStart').hide();
                $('.uploadingReelTitle').show();
                $('#uploadReelSidebar').show();
                $('#uploadReelInfo').show();
            }
          }
      })
        .use(Dashboard, { trigger: '#uppyModalOpener',theme:'dark',showProgressDetails:true,proudlyDisplayPoweredByUppy: true,closeAfterFinish:false,note: 'Upload only short video upto 60s, 50MB or less',})
        .use(Webcam, { target: Dashboard,showVideoSourceDropdown: true, showRecordingLength: true})
        .use(XHRUpload, { endpoint: "<?php echo $sm['config']['site_url']; ?>assets/sources/upload.php",fieldName: 'file',getResponseData(responseText, response) {
            var file=jQuery.parseJSON(responseText);
            if(file.contetnerror) {
                var data = [];
                data.name = '';
                data.icon = user.profile_photo;
                data.message = file.contetnerror;
                if(mobileSite){
                    pushNotifMobileReel(data,1,9000);
                } else {
                    pushNotif(data,1);  
                }
                return;
            }
            uploadReel['path'] = file.path;
            $('.uploadingReel').hide();
            $('#uploadingReelVideo').removeClass('uploadingReelVideo');
            $('#uploadingReelVideo').removeClass('isPrivate');
            $('#uploadReelBtnComplete').show();
            var reelPath = file.path;
            upphotos[0] = file;
        },getResponseError(responseText, response) {
	        console.log("response error");
            console.log(responseText);
        }});

      uppy.on('success', (fileCount) => {
        console.log(`${fileCount} files uploaded`)
      });
      uppy.on('upload-error', (file, error, response) => {
        console.log(error);
      });
    </script>
    <?php 
        $mobileThemeDesign = getData('theme_preset','theme_settings','where theme = "'.$sm['settings']['mobileThemePreset'].'" and preset = "'.$sm['settings']['mobileTheme'].'"');
    ?>

    <?php if($sm['settings']['mobileTheme'] == 'twigo'){ 
        $mainFont = 'Inter'; ?>
        <link href="https://fonts.googleapis.com/css?family=Inter" rel="stylesheet">        
        <link href="twigo/css/twigo.css" rel="stylesheet"> 
    <?php } else {
        $mainFont = 'Rubik';
    } ?>

    <?php if($mobileThemeDesign == 'noData'){
        $mobile_theme = '""';
    } else {
        $mobile_theme = $mobileThemeDesign;
    }
    $mobile_theme=json_decode($mobile_theme);
    $mobile_theme->logo->val=($_SERVER['SERVER_NAME']=='special-dating.com')?$sm['metasettings']['website_mobile_logo']:getvaluemetax('_domain_mobile_logo',$_SERVER['SERVER_NAME']);
    $mobile_theme=json_encode($mobile_theme);
    echo '
        <script>
        var mobile_theme = '. $mobile_theme  . ';
        </script>
    ';
    ?>
    <style>
        #load_more_stories{display:none;}
        .backdrop{
            pointer-events: none!important;
        }
        .slider-pager .slider-pager-page.active {
            color: #333;
        }        
        .comforta{
            font-family: '<?=$mainFont;?>' !important;
        }
        .b-none{
            font-family: '<?=$mainFont;?>' !important;   
        }
        .csms-profile-info__name-inner{
            font-family: '<?=$mainFont;?>' !important;
        }
        .csms-p-1{
            font-family: '<?=$mainFont;?>' !important;
        }
        .chat-name{
            font-family: '<?=$mainFont;?>' !important;
        }        
        .text-muted{
            font-family: '<?=$mainFont;?>' !important;
        }  
        .item span{
            font-family: '<?=$mainFont;?>' !important;   
        }
        #regf_landing .cta-box__buttons .list-view li input::placeholder {
            color: #fff !important;
            opacity: 1;
        }
        .positive-bg{
            background: #000 !important;
        }
        .button.button--primary{
            background: #000!important;
            color: #fff!important;
        }
        .profile-info__title{
            font-family: '<?=$mainFont;?>' !important; 
        }
        .profile-info__content{
            font-family: '<?=$mainFont;?>' !important; 
        }
        .button.button--secundary.active{
            opacity:1;
        }
        .button.button--secundary.a {
            background:#BE1622 !important;
            color:#fff !important;
            opacity: 0.6;
        }
        .black-text{
            color: #000!important;
        }         
        .selector__label{
            font-size: 16px;
        }
        .storyOn{
            border:3px solid <?= $sm['theme']['story_on']['val'];?>;
        }  
        .stream-message__content{
            color: #fbfbfb;
            z-index: 10;
        }           
    </style>
    <?php 
    $checkFeedPlugin = getData('plugins','enabled','where name = "fgfeed"');
    $checkLivePlugin = getData('plugins','enabled','where name = "live"');
    $checkReelsPlugin = getData('plugins','enabled','where name = "reels"');
    $checkStoryPlugin = getData('plugins','enabled','where name = "story"');
    if($checkLivePlugin == 1){ ?>
        <script>var liveEnabled = true;</script>
    <?php } else { ?>
        <script>var liveEnabled = false;</script>
    <?php } ?>
    <?php if($checkFeedPlugin == 1){ ?>
        <script>var feedEnabled = true;</script>
    <?php } else { ?>
        <script>var feedEnabled = false;</script>
    <?php } ?> 
    <?php if($checkReelsPlugin == 1){ ?>
        <script>var reelsEnabled = true;</script>
    <?php } else { ?>
        <script>var reelsEnabled = false;</script>
    <?php } ?>        
    <?php if($checkStoryPlugin == 1){ ?>
        <script>var storyEnabled = true;</script>
    <?php } else { ?>
        <script>var storyEnabled = false;</script>
    <?php } ?>  
    <?php if(!empty($sm['metasettings']['google_analytics_id'])){ ?>
        <script async src="https://www.googletagmanager.com/gtag/js?id=<?= $sm['metasettings']['google_analytics_id']; ?>"></script>
        <script>
          window.dataLayer = window.dataLayer || [];
          function gtag(){dataLayer.push(arguments);}
          gtag('js', new Date());

          gtag('config', '<?= $sm['metasettings']['google_analytics_id']; ?>');
        </script>    
    <?php } ?>

    <?= $sm['plugins']['customHtml']['mobile_header']; ?>
    <style>
    .gem-icon{
        background: url(<?= $sm['theme']['left_menu_icon_credits']['val']; ?>) center/contain no-repeat !important;
    }
    </style>    
  </head>
  <body ng-app="starter" ng-controller="AppCtrl"  style="background: #fff;">
    <ion-nav-bar></ion-nav-bar>
    <ion-pane ui-view="">   
    </ion-pane>

    <div class="inapp-notification-wrapper chatNotification" style="z-index: 99999" ng-click="goTo('home.matches');hideNotification()">
        <div class="inapp-notification js-inapp-notification-touchable">
            <div class="inapp-notification__promo">
                <div class="inapp-notification__images">
                    <div class="inapp-notification__user chatNotificationPhoto" style="border-radius: 50%"></div>
                </div>
            </div>
            <div class="inapp-notification__content chatNotificationContent comforta"></div> 
        </div>
    </div>
    
    <div id="storytime" data-story="0"></div>
    <div class="preload-photos" style="display:none"></div>     
    <div id="upload-area" style="display: none">
        <input type='file' id="uploadContent" style="display: none" />    
    </div>
    <div id="upload-reel" style="display: none">
        <input type='file' id="uploadReelContent" style="display: none" />    
        <button id="uppyModalOpener" style="display:none;">UpReal</button>
    </div>    
    <div id="upload-story" style="display: none" >
        <input type='file' id="uploadStoryContent">
    </div>    
    

    <script src="https://cdnjs.cloudflare.com/ajax/libs/fingerprintjs2/2.1.0/fingerprint2.min.js"></script>
    <script src="https://unpkg.com/tiny-swiper@latest/lib/index.min.js"></script>
    
      
    <script src="<?php echo $sm['config']['theme_url']; ?>/js/vendor/jquery.dm-uploader.min.js"></script>
    <?php foreach($sm['plugin'] as $plugin){ ?>
        <?php if(!empty($plugin['js_file'])){
    echo '<script src="'.$sm['config']['site_url'].'themes/plugins/'.$plugin['js_file'].'.js"></script>';
                } 
        } ?>
    <script>
    function locationUpdated(value){ 
        var lat = value.latitude;
        var lng = value.longitude;
        var city = value.name;
        var country = value.country;
        var message = userId+','+lat+','+lng+','+city+','+country;
        if(updateLocationRegister == 'Yes'){
            reg_city = city;
            reg_lng = lng;
            reg_country = country;
            reg_lat = lat;
            return false;
        }
        $.ajax({
            url: request_source()+'/api.php', 
            data: {
                action:"updateLocation",
                query: message                   
            },  
            type: "get",  
            dataType: "JSON",         
            success: function(response) {
                window.location.reload();                        
            },
        });             
    }
    </script>
    <script src="lib/hammer/jquery.hammer.js"></script>
    <script src="<?php echo $sm['config']['theme_url']; ?>/js/vendor/action-sheet.js"></script>    
    <script src="lib/autocomplete/autocomplete.js"></script>    
    <script src="<?php echo $sm['config']['theme_url']; ?>/js/vendor/slim-lightbox.min.js"></script>
    <script src="<?php echo $sm['config']['theme_url']; ?>/js/vendor/little-widgets.js"></script> 
    <script src="https://webrtc.github.io/adapter/adapter-latest.js"></script>
    <script src="https://unpkg.com/@geoapify/geocoder-autocomplete@^1/dist/index.min.js"></script>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/intl-tel-input/17.0.16/js/intlTelInput.min.js"></script>

    <script type="text/javascript">
        var currentStory;
        var socialStory = new Story({
            playlist: currentStory
        });          
    </script>

    <?= $sm['plugins']['customHtml']['mobile_footer']; ?>
  </body>
</html>