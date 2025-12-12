<?php
/* Belloo Software by https://premiumdatingscript.com */
header('Content-Type: application/json');
require_once('../assets/includes/core.php');
require_once('./auth_middleware.php');

// Require authentication for reels operations
requireAuth();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($_GET['action']) {
        case 'loadDynamicReels':
            $arr = array();
            $arr['html']= '';
            $feed = array();
            $order = 'id ASC';
            
            // Always use session user ID, never accept from GET/POST parameters
            $uid = getUserIdFromSession();
            
            $limit = secureEncode($_GET['limit']);
            //$looking = getData('users','s_gender','where id ="'.$uid.'"');
            
            // Set default value if s_gender is empty or 0 (opposite of user's gender)
            //if(empty($looking) || $looking == 0 || $looking == '0'){
                // Get user's gender
                $userGender = getData('users','gender','where id ="'.$uid.'"');
                // Set looking to opposite gender: if male (1) look for female (2), if female (2) look for male (1)
                if($userGender == 1){
                    $looking = 2; // Male looks for female
                } else if($userGender == 2){
                    $looking = 1; // Female looks for male
                } else {
                    $looking = 2; // Fallback default to female
                }
            //}
            
            $customFilter = secureEncode($_GET['customFilter']);
            $trending = secureEncode($_GET['trending']);

            if($customFilter == '' || $customFilter == 'all'){
                // Base filter - gender filter only applies to trending
                if($trending == 1){
                    // For trending: apply gender filter, exclude already played reels, and use random ordering
                    $filter = 'WHERE visible = 1 AND gender = '.$looking.' AND uid <> '.$uid;
                    
                    // Get list of already played reel IDs for this user
                    
                    $playedReels = getArray('users_reels_played','WHERE uid = '.$uid,'uid','');
                    
                    $excludedIds = array();
                    if(!empty($playedReels)){
                        foreach($playedReels as $played){
                            $excludedIds[] = $played['rid'];
                        }
                    }
                    
                    // Exclude already played reels from filter
                    if(!empty($excludedIds)){
                        $excludedIdsStr = implode(',', array_map('intval', $excludedIds));
                        $filter = $filter.' AND id NOT IN ('.$excludedIdsStr.')';
                    }                    
                    // Use random ordering for trending
                    $order = 'RAND()';
                } else {
                    // For latest: no gender filter, just visible and not own reels
                    $filter = 'WHERE visible = 1 AND uid <> '.$uid;
                    $lastViewedReel = getData('users_reels_played','rid','where uid ='.$uid);
                    if($lastViewedReel != 'noData' && $trending == 0){
                        // die("SDf");
                        // $filter = $filter.' AND id > '.$lastViewedReel;
                    }
                    $order = 'id ASC';
                }

                $reels = getArray('reels',$filter,$order,'LIMIT '.$limit.',10');
            } else {
                if($customFilter == 'liked'){
                    $data = getArray('reels_likes','WHERE uid = '.$sm['user']['id'],'time DESC','LIMIT 0,300');
                    if(!empty($data)){
                        foreach ($data as $d) {
                            $reels[] = getDataArray('reels','visible = 1 AND id = '.$d['rid']);
                        }
                    }    
                }   

                if($customFilter == 'purchased'){
                    $data = getArray('users_reels_purchases','WHERE uid = '.$sm['user']['id'],'time DESC','LIMIT 0,300');
                    if(!empty($data)){
                        foreach ($data as $d) {
                            $reels[] = getDataArray('reels','visible = 1 AND id = '.$d['rid']);
                        }
                    } else {
                        $arr['html'] = 'NORESULTS';
                    }                    
                }

                if($customFilter == 'me'){
                    $filter = 'WHERE visible = 1 AND uid = '.$uid; 
                    $order = 'id DESC';
                    $reels = getArray('reels',$filter,$order,'');
                }

            }

            if(!empty($reels)){
                $index = $limit;
                foreach ($reels as $reel) {
                    $username = getData('users','username','where id ="'.$reel['uid'].'"');
                    if(is_numeric($username)){
                        $username = getData('users','name','where id ="'.$reel['uid'].'"');
                    }
                    $city = getData('users','city','where id ="'.$reel['uid'].'"');
                    $country = getData('users','country','where id ="'.$reel['uid'].'"');
                    $age = getData('users','age','where id ="'.$reel['uid'].'"'); 
                    $profile_photo = profilePhoto($reel['uid']);

                    $checkLiked = getData('reels_likes','rid','where rid ='.$reel['id'].' AND uid = '.$sm['user']['id']);
                    $purchased = 'No';

                    $liked = 'style="display:none"';
                    $noliked = 'style="display:block"';
                    $reelLiked = 0;
                    if($checkLiked != 'noData'){
                        $liked = 'style="display:block"';
                        $noliked = 'style="display:none"';
                        $reelLiked = 1;
                    }

                    if($reel['uid'] == $uid){
                        $purchased = 'Yes';
                    }

                    $privateMask = '';
                    $pruchased = 'No';
                    if($reel['reel_price'] > 0){
                        $checkPurchased = getData('users_reels_purchases','uid','where rid ='.$reel['id'].' AND uid = '.$sm['user']['id']);  
                        if($checkPurchased != 'noData'){
                            $purchased = 'Yes';
                        } else {
                            $pruchased = 'No';
                        }
                        $privateMask = 'isPrivate';                         
                    }


                    $storyFrom = $sm['plugins']['story']['days'];
                    $time = time(); 
                    $extra = 86400 * $storyFrom;
                    $storyFrom = $time - $extra;            
                    $storiesFilter = 'where uid = '.$reel['uid'].' and storyTime >'.$storyFrom.' and deleted = 0';
                    $openStory = selectC('users_story',$storiesFilter);
                    $profilePhotoBorder = 'border: 2px solid #fff';
                    if($openStory > 0){
                        $profilePhotoBorder = 'border: 2px solid #e22d48';
                    }

                    $arr['reels'][] = array(
                      "id" => $reel['id'],
                      "price" => $reel['reel_price'],                     
                      "play" => $reel['reel_src'],
                      "playHls" => !empty($reel['reel_src_hls']) ? $reel['reel_src_hls'] : '',
                      "cover" => $reel['reel_cover'],
                      "caption" => $reel['reel_meta'],                    
                      "photo" => $profile_photo,
                      "photoBig" => profilePhoto($reel['uid'],1),                      
                      "purchased" => $purchased,
                      "user_id" => $reel['uid'],
                      "name" => $username,                     
                      "age" => $age,
                      "city" => $city,
                      "liked" =>$reelLiked                                   
                    );

                    $privateClass = '';
                    $privateHtml = '';
                    $sideBar = '';

                    // if($reel['reel_price'] > 0 && $purchased == 'No'){
                    //     $privateClass = 'isPrivate';
                    //     $privateHtml = '

                    //     <div class="mask" id="reelMask'.$reel['id'].'">  
                    //         <svg style="height:100%" xmlns="http://www.w3.org/2000/svg" version="1.1" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:svgjs="http://svgjs.dev/svgjs" viewBox="0 0 640 800" opacity="1"><defs><filter id="bbblurry-filter" x="-100%" y="-100%" width="400%" height="400%" filterUnits="objectBoundingBox" primitiveUnits="userSpaceOnUse" color-interpolation-filters="sRGB">
                    //             <feGaussianBlur stdDeviation="103" x="0%" y="0%" width="100%" height="100%" in="SourceGraphic" edgeMode="none" result="blur"></feGaussianBlur></filter></defs><g filter="url(#bbblurry-filter)"><ellipse rx="177.5" ry="165.5" cx="185.3175332377275" cy="154.67259036086676" fill="hsla(324, 92%, 54%, 1.00)"></ellipse><ellipse rx="177.5" ry="165.5" cx="466.94405238834906" cy="702.7701045959953" fill="hsla(284, 95%, 52%, 1.00)"></ellipse><ellipse rx="177.5" ry="165.5" cx="55.77614977979283" cy="582.1078918937624" fill="hsla(0, 63%, 51%, 1.00)"></ellipse><ellipse rx="177.5" ry="165.5" cx="310.10553987758385" cy="392.4187499519408" fill="hsla(340, 100%, 39%, 1.00)"></ellipse><ellipse rx="177.5" ry="165.5" cx="535.126172403651" cy="123.65015129029274" fill="hsla(0, 100%, 50%, 1.00)"></ellipse></g>
                    //         </svg>                                                                             
                    //         <div class="guide-info" onclick="purchaseReel('.$reel['id'].','.$reel['reel_price'].')">
                    //             <div class="ani-guide">
                    //                 <img src="'.$profile_photo.'" style="width: 100%;border-radius: 50%;">
                    //             </div>
                    //             <p class="title">Private content</p>
                    //             <p class="content" style="padding:0">'.$sm['lang'][941]['text'].' <strong>'.$reel['reel_price'].' '.$sm['lang'][73]['text'].'</strong></p>
                    //         </div>
                    //     </div>';
                    // }

                    // if($reel['uid'] == $uid){
                    //     $sideBar = '<div class="side-bar">
                    //         <svg id="editCurrentReelBtn'.$reel['id'].'" onclick="editCurrentReel('.$reel['id'].',`edit`)" width="32px" height="32px" viewBox="0 0 24 24" stroke-width="1.5" fill="none" xmlns="http://www.w3.org/2000/svg" color="#fff"><path d="M14.363 5.652l1.48-1.48a2 2 0 012.829 0l1.414 1.414a2 2 0 010 2.828l-1.48 1.48m-4.243-4.242l-9.616 9.615a2 2 0 00-.578 1.238l-.242 2.74a1 1 0 001.084 1.085l2.74-.242a2 2 0 001.24-.578l9.615-9.616m-4.243-4.242l4.243 4.242" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>

                    //         <svg id="editCurrentReelBtnCheck'.$reel['id'].'" style="display: none" onclick="editCurrentReel('.$reel['id'].',`complete`)" width="32px" height="32px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#fff"><path d="M7 12.5l3 3 7-7" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    //     ';
                    //     if($reel['reel_price'] > 0){
                    //         $sideBar.='
                    //         <svg  width="32px" height="32px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#fff"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 8.5c-.685-.685-1.891-1.161-3-1.191M9 15c.644.86 1.843 1.35 3 1.391m0-9.082c-1.32-.036-2.5.561-2.5 2.191 0 3 5.5 1.5 5.5 4.5 0 1.711-1.464 2.446-3 2.391m0-9.082V5.5m0 10.891V18.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
                    //     }
                    //     $sideBar.='
                    //     <svg onclick="deleteReel('.$reel['id'].')" width="32px" height="32px" viewBox="0 0 24 24" stroke-width="1.5" fill="none" xmlns="http://www.w3.org/2000/svg" color="#fff"><path d="M20 9l-1.995 11.346A2 2 0 0116.035 22h-8.07a2 2 0 01-1.97-1.654L4 9M21 6h-5.625M3 6h5.625m0 0V4a2 2 0 012-2h2.75a2 2 0 012 2v2m-6.75 0h6.75" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>
                    //     </div>';
                    // } else {
                    //     $sideBar = '<div class="side-bar">';
                    //     if($reel['reel_price'] > 0 && $purchased == 'Yes' && $reel['uid'] != $uid){
                    //         $sideBar.='
                    //         <svg  width="32px" height="32px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#fff"><path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path><path d="M15 8.5c-.685-.685-1.891-1.161-3-1.191M9 15c.644.86 1.843 1.35 3 1.391m0-9.082c-1.32-.036-2.5.561-2.5 2.191 0 3 5.5 1.5 5.5 4.5 0 1.711-1.464 2.446-3 2.391m0-9.082V5.5m0 10.891V18.5" stroke="#fff" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"></path></svg>';
                    //     }  
                    //     $sideBar.='
                    //     <svg '.$noliked.' id="reelNoLiked'.$reel['id'].'" onclick="likeReel('.$reel['id'].',1)" width="32px" height="32px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" color="#fff"><path d="M22 8.862a5.95 5.95 0 01-1.654 4.13c-2.441 2.531-4.809 5.17-7.34 7.608-.581.55-1.502.53-2.057-.045l-7.295-7.562c-2.205-2.286-2.205-5.976 0-8.261a5.58 5.58 0 018.08 0l.266.274.265-.274A5.612 5.612 0 0116.305 3c1.52 0 2.973.624 4.04 1.732A5.95 5.95 0 0122 8.862z" stroke="#fff" stroke-width="1.5" stroke-linejoin="round"></path></svg>

                    //     <svg '.$liked.' id="reelLiked'.$reel['id'].'" onclick="likeReel('.$reel['id'].',0)" width="32px" height="32px" stroke-width="1.5" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" fill="rgb(255, 48, 64)" color="rgb(255, 48, 64)"><path fill="rgb(255, 48, 64)" d="M22 8.862a5.95 5.95 0 01-1.654 4.13c-2.441 2.531-4.809 5.17-7.34 7.608-.581.55-1.502.53-2.057-.045l-7.295-7.562c-2.205-2.286-2.205-5.976 0-8.261a5.58 5.58 0 018.08 0l.266.274.265-.274A5.612 5.612 0 0116.305 3c1.52 0 2.973.624 4.04 1.732A5.95 5.95 0 0122 8.862z" stroke="rgb(255, 48, 64)" stroke-width="1.5" stroke-linejoin="round"></path></svg>                                    
                    // </div>';
                    // }

                    // $poster = 'poster="'.profilePhoto($reel['uid'],1).'"';

                    // $arr['html'].= '
                    //     <div class="swiper-slide">
                    //         <div class="video-item">
                    //             <div class="video-wraper black">
                    //                 <div class="poster isTop '.$privateClass.'" id="reel'.$reel['id'].'" data-reel-video-index="'.$index.'">
                    //                     <video webkit-playsinline="true" playsinline="true" data-reel-id="'.$reel['id'].'" data-reel-user-id="'.$reel['uid'].'" id="video'.$index.'"  src="'.$reel['reel_src'].'" muted loop preload>
                    //                     </video>
                    //                 </div>

                    //                 <div class="refresh-wrapper" 
                    //                     style="background-image: url('.profilePhoto($reel['uid'],1).');display:none";>
                    //                 </div>
                    //                 '.$sideBar.'
                    //                 <div class="info-wraper" id="reelInfo'.$reel['id'].'">
                    //                     <div class="user-info" onclick="goToProfile('.$reel['uid'].')">
                    //                         <a href="javascript:;"><img src="'.$profile_photo.'" class="avator" style="object-fit:cover" id="uploadReelPhoto'.$index.'"></a>
                    //                         <p class="name" id="uploadReelName'.$index.'">'.$username.'</p>
                    //                     </div>
                    //                     <p class="video-info" id="currentReelCaption'.$reel['id'].'">
                    //                         '.$reel['reel_meta'].'
                    //                     </p>
                    //                 </div>

                    //                 <div class="info-wraper" id="editReel'.$reel['id'].'" style="bottom: 5.35rem;display: none;">
                    //                     <div class="user-info"  style="margin-left:12px">
                    //                         <a href="javascript:;"><img src="'.$profile_photo.'" class="avator" style="object-fit:cover"></a>
                    //                         <p class="name">'.$username.'</p>

                    //                     </div>

                    //                     <div class="reel-form" style="margin:15px">
                    //                       <textarea style="width:100%;font-size:16px;line-height: 18px;height:60px;background: none;color:#eee;border:none;outline: none;" class="montserrat" id="editReelTextarea'.$reel['id'].'" onkeyup="editMyReel(`caption`,this.value)">'.$reel['reel_meta'].'</textarea>
                    //                     </div>    

                    //                     <div class="montserrat reel-form" style="margin:15px" id="reelPrice">
                    //                         <span>Reel price in credits</span>
                    //                         <label class="" style="margin-left:25px">
                    //                           <input type="number" value="'.$reel['reel_price'].'" onkeyup="editMyReel(`price`,this.value)" id="editReelPrice'.$reel['id'].'"  style="width: 50px;height:30px;display: inline-block;font-size: 1.05rem;margin-top: -3px;text-align: center;background:none;border:1px solid #777;border-radius: 10px;color: #ffbf00;outline: none;">
                    //                         </label>
                    //                     </div>  

                    //                 </div>

                    //                 '.$privateHtml.'                 
                    //             </div>
                    //         </div>
                    //     </div>';
                    $index++;
                }   
            } else {
                $arr['html'] = 'NORESULTS';
            } 


            echo json_encode($arr);
        break;

        case 'viewed':
            $rid = secureEncode($_GET['rid']);
            $uid = secureEncode($_GET['uid']);
            $fromTrending = secureEncode($_GET['from_trending']);
            $time = time();
            $arr = array();
            $arr['result'] = 'OK';
            
            // Check if record already exists
            $checkQuery = "SELECT uid FROM users_reels_played WHERE uid = '".$uid."' AND rid = '".$rid."' LIMIT 1";
            $checkResult = $mysqli->query($checkQuery);
            if($checkResult && $checkResult->num_rows > 0){
                // Update existing record
                $updateQuery = "UPDATE users_reels_played SET time = '".$time."' WHERE uid = '".$uid."' AND rid = '".$rid."'";
                $mysqli->query($updateQuery);
            } else {
                // Insert new record
                $insertQuery = "INSERT INTO users_reels_played (uid, rid, time) VALUES ('".$uid."', '".$rid."', '".$time."')";
                $mysqli->query($insertQuery);
            }
            
            updateData('reels','viewed','viewed + 1','WHERE id ='.$rid);

            echo json_encode($arr);
        break;

        case 'removeReel':
            $id = secureEncode($_GET['rid']);
            $uid = secureEncode($_GET['uid']);
            $arr = array();
            $time = time();
            deleteData('reels','WHERE uid = '.$uid.' AND id = '.$id);
            deleteData('reels_likes','WHERE rid = '.$id);            
            deleteData('users_reels_played','WHERE rid = '.$id);
            deleteData('users_reels_purchases','WHERE rid = '.$id);
            $arr['OK'] = 'OK';
            echo json_encode($arr);
        break;        
    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {       
        case 'uploadReel':
            $time = time();
            $arr = array();
            $uid = secureEncode($_POST['uid']);
            $gender = secureEncode($_POST['gender']);
            $price = secureEncode($_POST['price']);
            $caption = secureEncode($_POST['caption']);
            $path = secureEncode($_POST['path']);

            // Extract file name from S3 path to check rekognition data
            $file_name = '';
            if (strpos($path, 's3.') !== false || strpos($path, 'amazonaws.com') !== false) {
                $parsedUrl = parse_url($path);
                $pathParts = explode('/', trim($parsedUrl['path'], '/'));
                $file_name = end($pathParts);
                if (empty($file_name)) {
                    $file_name = basename($path);
                }
            }

            // Check rekognition data from reels_aws_upload table
            $visible = 1; // Default: approved
            $rekognitionData = null;
            $rekognitionJson = '';
            $hls_url = '';
            
            if (!empty($file_name)) {
                $escapedFileName = $mysqli->real_escape_string($file_name);
                $maxWaitTime = 30; // Maximum wait time in seconds
                $startTime = time();
                $pollInterval = 1; // Check every 1 second
                
                // Poll for rekognition data if record exists but data is not ready
                do {
                    $rekognitionQuery = "SELECT rekognition, hls_url FROM reels_aws_upload WHERE file_name = '$escapedFileName'";
                    $rekognitionResult = $mysqli->query($rekognitionQuery);
                    
                    if ($rekognitionResult && $rekognitionResult->num_rows > 0) {
                        $rekognitionRow = $rekognitionResult->fetch_assoc();
                        $rekognitionJson = $rekognitionRow['rekognition'];
                        $hls_url = $rekognitionRow['hls_url'] ?? '';
                        
                        // If rekognition data is available, break the loop
                        if (!empty($rekognitionJson)) {
                            break;
                        }
                    } else {
                        // No record found, break immediately
                        break;
                    }
                    
                    // Wait before next poll (if still within time limit)
                    if ((time() - $startTime) < $maxWaitTime) {
                        sleep($pollInterval);
                    } else {
                        // Timeout reached
                        break;
                    }
                } while (empty($rekognitionJson) && (time() - $startTime) < $maxWaitTime);
                
                // Parse rekognition data and determine visibility
                if (!empty($rekognitionJson)) {
                    $rekognitionData = json_decode($rekognitionJson, true);
                    
                    if ($rekognitionData && is_array($rekognitionData)) {
                        // Use new moderation_scores format (nudity, sexual, violence)
                        $nudity = isset($rekognitionData['nudity']) ? (float)$rekognitionData['nudity'] : 0;
                        $sexual = isset($rekognitionData['sexual']) ? (float)$rekognitionData['sexual'] : 0;
                        $violence = isset($rekognitionData['violence']) ? (float)$rekognitionData['violence'] : 0;
                        $other = isset($rekognitionData['other']) ? (float)$rekognitionData['other'] : 0;
                        
                        
                        $maxScore = max($nudity, $sexual, $violence, $other);
                        
                        if ($maxScore >= 80) {
                            $visible = 2; // Rejected
                        } else if ($maxScore >= 60) {
                            $visible = 0; // Pending review
                        } else {
                            $visible = 1; // Approved
                        }
                    }
                } else {
                    // No rekognition data yet - set as pending
                    $visible = 0;
                }
            } else {
                // Not an S3 URL - set as pending
                $visible = 0;
            }

            // Prepare rekognition data for insertion (always include, even if empty)
            $rekognitionJsonEscaped = '';
            if (!empty($rekognitionJson)) {
                $rekognitionJsonEscaped = $mysqli->real_escape_string($rekognitionJson);
            }

            // Prepare hls_url for insertion (always include, even if empty)
            $hls_urlEscaped = '';
            if (!empty($hls_url)) {
                $hls_urlEscaped = $mysqli->real_escape_string($hls_url);
            }

            // Save reel with appropriate visibility status, rekognition data, and hls_url
            // Always include hls_url and rekognition columns, even if empty
            // Skip saving if rejected (visible == 2)
            //if ($visible != 2) {
                $cols = 'uid,reel_price,reel_src,reel_meta,time,visible,gender,rekognition,reel_src_hls';
                $vals = $uid.','.$price.',"'.$path.'","'.$caption.'","'.$time.'",'.$visible.','.$gender.',"'.$rekognitionJsonEscaped.'","'.$hls_urlEscaped.'"';                
                insertData('reels',$cols,$vals);
            //}

            $arr['uploaded'] = 'OK';
            $arr['status'] = $visible == 1 ? 'approved' : ($visible == 2 ? 'rejected' : 'pending');

            if ($visible == 0) {
                $arr['message'] = 'Your reel is pending review and will be visible after moderation.';
            } else if ($visible == 2) {
                $arr['message'] = 'Your reel was rejected due to content policy violations.';
            }

            echo json_encode($arr);          
        break;

        case 'editReel':
            $time = time();
            $arr = array();
            $arr['edited'] = 'OK';
            $reel = secureEncode($_POST['reel']);
            $price = secureEncode($_POST['price']);
            $caption = secureEncode($_POST['caption']);
            
            $query = "UPDATE reels SET reel_meta = '".$caption."',reel_price = ".$price." WHERE id = ".$reel;
            $mysqli->query($query);   

            echo json_encode($arr);          
        break;        

        case 'reelLike':
            $arr = array();
            $user = secureEncode($_POST['user']);
            $reel = secureEncode($_POST['rid']);
            $motive = secureEncode($_POST['motive']);         
            if($motive == 'like'){
                $cols = 'rid,uid,time';
                $vals = $reel.','.$user.','.time();
                insertData('reels_likes',$cols,$vals);
            } else {
                $delete = 'WHERE rid = '.$reel.' AND uid = '.$user;
                deleteData('reels_likes',$delete);
            }

            $count = getData('reels','likes','where id ='.$reel);
        
            if($motive == 'remove'){
                $count = $count - 1;
            } else {
                $count = $count + 1;
            }
            updateData('reels','likes',$count,'WHERE id ='.$reel);

            $arr['OK'] = 'Yes';
            echo json_encode($arr);     
        break;

        case 'purchase_reel':
            $arr = array();
            $time = time();
            $user = secureEncode($_POST['user']);
            $reel = secureEncode($_POST['rid']);
            $action = secureEncode($_POST['purchase_action']);

            if($action == 'purchase'){
                $cols = 'uid,rid,time';
                $vals = $uid.','.$reel.',"'.$time.'"';
                insertData('users_reels_purchases',$cols,$vals); 
                updateData('reels','purchased','purchased +1','WHERE id ='.$reel);    
            } else {
                deleteData('users_reels_purchases','WHERE uid = '.$uid.' AND rid = '.$reel);
            }
            $arr['OK'] = 'OK';
            echo json_encode($arr);             
        break; 

        default:
        break;
    }
}