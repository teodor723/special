<?php
header('Content-Type: application/json');
require_once('../assets/includes/core.php');
require_once('./auth_middleware.php');

// Require authentication for feed operations (except randomPostGenerator which is internal)
$allowedActions = ['randomPostGenerator']; // This should only be called internally
requireAuth($allowedActions);

// Always use session user ID, never accept from GET/POST parameters
$uid = getUserIdFromSession();

if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    switch ($_GET['action']) {
        case 'randomPostGenerator':
            $feedPosts = getSelectedArray('id','users','where fake = 1 and age BETWEEN 18 and 48','rand()','LIMIT 0,10'); 
            $i=0;
            foreach ($feedPosts as $f) { 
                global $mysqli;
                $photo = $mysqli->query("SELECT photo FROM users_photos where u_id = ".$f['id']." ORDER BY rand() LIMIT 1");
                if ($photo->num_rows > 0) {
                    $p = $photo->fetch_object();
                    $likes = rand(860,12000);
                    $time = time()-$likes;
                    $cols = 'uid,post_type,post_price,post_src,post_meta,post_premium,post_blur,likes,time,visible';
                    $randomCaption = getData('feed_random_captions','caption','order by rand() limit 1');
                    $vals = $f['id'].',"image",0,"'.$p->photo.'","'.$randomCaption.'","No",
                    "'.$p->photo.'",'.$likes.','.$time.',1';
                    insertData('feed',$cols,$vals);  
                    $i++;
                }
            }
            $arr = array();
            $arr['OK'] = 'Yes';
            echo json_encode($arr);
        break;

        case 'loadFeed':
            $arr = array();
            $arr['html']= '';
            $feed = array();
            $order = 'id DESC';
            // Use authenticated user's ID from session, not from GET parameter
            $looking = getData('users','s_gender','where id ="'.$uid.'"');
            $filter = 'WHERE visible = 1 AND gender = '.$looking.' AND uid <> '.$sm['user']['id'];            
            $customFilter = secureEncode($_GET['customFilter']);

            if($customFilter == '' || $customFilter == 'all'){
                $feed = getArray('feed',$filter,$order,'LIMIT 0,25');
            } else {
                if($customFilter == 'liked'){
                    $data = getArray('feed_likes','WHERE uid = '.$sm['user']['id'],'time DESC','LIMIT 0,300');
                    if(!empty($data)){
                        foreach ($data as $d) {
                            $feed[] = getDataArray('feed','id = '.$d['fid']);
                        }
                    }    
                }   
                if($customFilter == 'saved'){
                    $data = getArray('users_feed_favs','WHERE uid = '.$sm['user']['id'],'time DESC','LIMIT 0,300');
                    if(!empty($data)){
                        foreach ($data as $d) {
                            $feed[] = getDataArray('feed','id = '.$d['fid']);
                        }
                    }
                } 
                if($customFilter == 'purchased'){
                    $data = getArray('users_feed_purchases','WHERE uid = '.$sm['user']['id'],'time DESC','LIMIT 0,300');
                    if(!empty($data)){
                        foreach ($data as $d) {
                            $feed[] = getDataArray('feed','id = '.$d['fid']);
                        }
                    } else {
                        $arr['html'] = 'empty';
                    }                    
                }

                if($customFilter == 'me'){
                    $filter = 'WHERE visible = 1 AND uid = '.$uid; 
                    $feed = getArray('feed',$filter,$order,'LIMIT 0,25');
                }

            }

            if(!empty($feed)){
                foreach ($feed as $f) {
                    $name = getData('users','name','where id ="'.$f['uid'].'"');
                    $username = getData('users','username','where id ="'.$f['uid'].'"');
                    $verified = getData('users','verified','where id ="'.$f['uid'].'"');
                    $premiumu = getData('users','premium','where id ="'.$f['uid'].'"');
                    $popular = getData('users','popular','where id ="'.$f['uid'].'"');
                    $city = getData('users','city','where id ="'.$f['uid'].'"');
                    $country = getData('users','country','where id ="'.$f['uid'].'"');
                    $age = getData('users','age','where id ="'.$f['uid'].'"'); 
                    $profile_photo = profilePhoto($f['uid']);
                    $checkLiked = getData('feed_likes','fid','where fid ='.$f['id'].' AND uid = '.$sm['user']['id']);

                    $checkPurchased = getData('users_feed_purchases','uid','where fid ='.$f['id'].' AND uid = '.$sm['user']['id']);                 

                    $purchased = 'No';

                    $liked = 'style="display:none"';
                    $noliked = 'style="display:block"';
                    if($checkLiked != 'noData'){
                        $liked = 'style="display:block"';
                        $noliked = 'style="display:none"';
                    }

                    if($checkPurchased != 'noData'){
                        $purchased = 'Yes';
                    } 

                    if($f['uid'] == $sm['user']['id']){
                        $purchased = 'Yes';
                    }

                    $saved = 'display:block';
                    $removesaved = 'display:none';
                    $favs = getData('users_feed_favs','fid','WHERE uid = '.$sm['user']['id'].' AND fid = '.$f['id']);
                    if($favs != 'noData'){
                        $favs = 'favorited';
                        $saved = 'display:none';
                        $removesaved = 'display:block';
                    }

                    $premium = '';
                    $premiumPost = '';
                    if($f['post_premium'] == 'Yes' && $purchased == 'No'){
                        $premium = 'premium-post';
                        $premiumPost = '<div class="premium-post-label" data-feed-id-premium="'.$f['id'].'" style="cursor:pointer" onClick="purchasePremiumPost('.$f['id'].')">
                          <h6>
                            <img src="'.$sm['config']['theme_url'].'/images/privateFeed.png">
                          </h6>  
                          <p>
                            '.$sm['lang'][924]['text'].'<br>'.$sm['lang'][925]['text'].' '.$sm['plugins']['fgfeed']['creditsForPremium'].' '.$sm['lang'][73]['text'].'
                          </p>
                        </div>';
                    }

                    $privatePostIcon = '';
                    if($f['post_premium'] == 'Yes'){
                        $privatePostIcon = '<button alt="'.$sm['lang'][924]['text'].'" class="moreDropdown btn btn--ico box-shadow-low" type="button" href="javascript:;" style="background: #fff;"><svg xmlns="http://www.w3.org/2000/svg" width="60%" height="60%" viewBox="0 0 24 24" fill="none" stroke="#0d0d0d" stroke-width="1.5" stroke-linecap="butt" stroke-linejoin="bevel"><line x1="12" y1="1" x2="12" y2="23"></line><path d="M17 5H9.5a3.5 3.5 0 0 0 0 7h5a3.5 3.5 0 0 1 0 7H6"></path></svg>          
                                  </button>';
                    }
                   

                    $storyFrom = $sm['plugins']['story']['days'];
                    $time = time(); 
                    $extra = 86400 * $storyFrom;
                    $storyFrom = $time - $extra;            
                    $storiesFilter = 'where uid = '.$f['uid'].' and storyTime >'.$storyFrom.' and deleted = 0';
                    $openStory = selectC('users_story',$storiesFilter);
                    $profilePhotoBorder = 'border: 2px solid #fff';
                    if($openStory > 0){
                        $profilePhotoBorder = 'border: 2px solid #e22d48';
                    }
                    $multipleSRC = 'No';
                    if(strpos($f['post_src'], ',') !== false){
                        $multipleSRC = 'Yes';
                    }

                    $hideMeChat = '';
                    if($f['uid'] == $uid){
                        $hideMeChat = 'display:none;';
                    }

                    $hideComments = '';
                    if($premium != ''){
                        $hideComments = 'style="display:none"';
                    }

                    $arr['feed_user'][$f['uid']] = array(
                      "id" => $f['uid'],
                      "name" => $username,                     
                      "age" => $age,
                      "city" => $city,                    
                      "photo" => $profile_photo,
                    );

                    $arr['html'].= '
                      <div class="instagram-sec '.$premium.'" data-uid="'.$f['uid'].'" data-feed-id="'.$f['id'].'">
                            <div class="insta-sec-heading">
                              <div class="insta-sec-head-content">
                                 <a href="javascript:;" onclick="openStory('.$openStory.','.$f['uid'].')">
                                    <img src="'.$profile_photo.'" class="insta-profile-img" style="'.$profilePhotoBorder.'"/>
                                 </a>
                                 <div class="insta-sec-head-inner goToProfileFeed'.$f['uid'].'"  onclick="goToProfile('.$f['uid'].')" style="cursor: pointer;">
                                    <h6 style="display:flex;">
                                       '.$name.', '.$age.(($premiumu == 1)?'<div style="width: 20px;border-radius: 50%;text-align: center;margin: 0 0 0 5px;" class="premium" data-badge-premium data-tooltip="'.$sm['lang'][438]['text'].'"><i class="mdi-editor-attach-money"></i></div>':'').(($verified == 1)?'<div style="width: 20px;border-radius: 50%;text-align: center;margin: 0 0 0 5px;" class="verified" data-badge-verified  data-tooltip="'.$sm['lang'][439]['text'].'"><i class="mdi-action-verified-user"></i></div>':'').(($popular > 1000)?'<div style="width: 20px;border-radius: 50%;text-align: center;margin: 0 0 0 5px;" class="popular" data-badge-popular data-tooltip="'.$sm['lang'][440]['text'].'"><i class="mdi-social-whatshot"></i></div>':'').'
                                    </h6>
                                    <p>'.$city.','.$country.'</p>
                                 </div>

                              </div>
                              
                              ';
                              if($f['uid'] == $uid){ 
                                  $arr['html'].= '<button class="moreDropdown btn btn--ico box-shadow-low" type="button" href="javascript:;" onclick="deleteFeed('.$f['id'].')" style="background: #fff;"><svg xmlns="http://www.w3.org/2000/svg" width="60%" height="60%" viewBox="0 0 24 24" fill="none" stroke="#0d0d0d" stroke-width="1" stroke-linecap="butt" stroke-linejoin="bevel"><polyline points="3 6 5 6 21 6"></polyline><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"></path><line x1="10" y1="11" x2="10" y2="17"></line><line x1="14" y1="11" x2="14" y2="17"></line></svg>           
                                  </button>';
                              } 
                              $arr['html'].= $privatePostIcon.'
                              <button class="moreDropdown btn btn--ico box-shadow-low goToChatFromFeedPost" type="button" href="javascript:;"  onclick="goToChat('.$f['uid'].')" style="background: #fff;'.$hideMeChat.'"><svg xmlns="http://www.w3.org/2000/svg" width="60%" height="60%" viewBox="0 0 24 24" fill="none" stroke="#0d0d0d" stroke-width="1.5" stroke-linecap="butt" stroke-linejoin="bevel"><path d="M21 11.5a8.38 8.38 0 0 1-.9 3.8 8.5 8.5 0 0 1-7.6 4.7 8.38 8.38 0 0 1-3.8-.9L3 21l1.9-5.7a8.38 8.38 0 0 1-.9-3.8 8.5 8.5 0 0 1 4.7-7.6 8.38 8.38 0 0 1 3.8-.9h.5a8.48 8.48 0 0 1 8 8v.5z"></path></svg>          
                              </button>
                            </div>
                            <div class="insta-imgs-slider" data-multiple="'.$multipleSRC.'">
                            ';

                            if($f['post_type'] == 'image'){
                                $arr['html'].='<div class="insta-imgs-slide" style="background-image:url('.$f['post_src'].');  background-size: cover;background-repeat: no-repeat !important;background-position: top center !important;height:100%;overflow:hidden"></div>';
                            }

                            if($f['post_type'] == 'video'){
                                $arr['html'].='<div class="insta-imgs-slide" style="height:100%;overflow:hidden"><video style="width: 100%;min-height:520px;max-height:520px;object-fit: cover;" class="filter blur-lg"  muted autoplay loop >
                                  <source src="'.$f['post_src'].'"  type="video/mp4">
                                    Your browser does not support the video tag.
                                </video></div>';                                  
                            }
                           $arr['html'].='
                            </div>
                            '.$premiumPost.'             
                            <div class="insta-sec-footer-wrap">
                              <div class="our-collection our-collection-'.$f['id'].'">
                                 <div class="oc-content">';
                                    if($multipleSRC == 'Yes'){
                                        $arr['html'].='<img src="'.$media[0].'" alt="" />';
                                    } else {
                                        $arr['html'].='<img src="'.$f['post_src'].'" alt="" />';
                                    }
                                    $arr['html'].='<span>Story saved</span>
                                 </div>
                              </div>
                              <div class="insta-sec-footer">
                                 <div class="insta-footer-link">
                                    <ul>
                                        <li>
                                            <div type="button" class="likeIcon">
                                                <svg class="unlike dis-hide" id="unlike-'.$f['id'].'" '.$noliked.' onclick="likeFeed('.$f['id'].',1);" color="#262626" fill="#262626" height="26" role="img" viewBox="0 0 24 24"
                                                width="26">
                                                    <path d="M16.792 3.904A4.989 4.989 0 0121.5
                                                       9.122c0
                                                       3.072-2.652 4.959-5.197 7.222-2.512 2.243-3.865
                                                       3.469-4.303
                                                       3.752-.477-.309-2.143-1.823-4.303-3.752C5.141
                                                       14.072 2.5 12.167 2.5 9.122a4.989 4.989 0
                                                       014.708-5.218 4.21 4.21 0 013.675 1.941c.84
                                                       1.175.98 1.763 1.12 1.763s.278-.588
                                                       1.11-1.766a4.17 4.17 0 013.679-1.938m0-2a6.04
                                                       6.04 0 00-4.797 2.127 6.052 6.052 0
                                                       00-4.787-2.127A6.985 6.985 0 00.5 9.122c0 3.61
                                                       2.55 5.827 5.015
                                                       7.97.283.246.569.494.853.747l1.027.918a44.998
                                                       44.998 0 003.518 3.018 2 2 0 002.174 0 45.263
                                                       45.263 0
                                                       003.626-3.115l.922-.824c.293-.26.59-.519.885-.774
                                                       2.334-2.025 4.98-4.32 4.98-7.94a6.985 6.985 0
                                                       00-6.708-7.218z"></path>
                                                </svg>
                                                <svg class="liked dis-hide" id="like-'.$f['id'].'" '.$liked.' onclick="likeFeed('.$f['id'].',0);" color="#ed4956" fill="#ed4956" height="26" role="img"
                                                viewBox="0 0 48 48" width="26">
                                                    <path d="M34.6 3.1c-4.5 0-7.9 1.8-10.6
                                                       5.6-2.7-3.7-6.1-5.5-10.6-5.5C6 3.1 0 9.6 0
                                                       17.6c0 7.3 5.4 12 10.6 16.5.6.5 1.3 1.1 1.9
                                                       1.7l2.3 2c4.4 3.9 6.6 5.9 7.6 6.5.5.3 1.1.5
                                                       1.6.5s1.1-.2 1.6-.5c1-.6 2.8-2.2
                                                       7.8-6.8l2-1.8c.7-.6 1.3-1.2 2-1.7C42.7 29.6 48
                                                       25 48 17.6c0-8-6-14.5-13.4-14.5z"></path>
                                                </svg>
                                            </div>
                                        </li>
                                        <li>
                                            <div type="button" class="commentIcon" onclick="showFeedComments('.$f['id'].',`'.$f['post_meta'].'`,`'.$profile_photo.'`,`'.$f['post_src'].'`,`'.$f['post_type'].'`)" '.$hideComments.'>
                                                <svg width="22" height="23" viewBox="0 0 22 23" fill="none"
                                                xmlns="http://www.w3.org/2000/svg">
                                                    <path fill-rule="evenodd" clip-rule="evenodd" d="M22 11.5C22 5.42487 17.0751 0.5 11
                                                       0.5C4.92487 0.5 0 5.42487 0 11.5C0 17.5751
                                                       4.92487 22.5 11 22.5C12.326 22.5 14.1475 22.0163
                                                       16.4645 21.0489L20.2113 22.5104L20.3223
                                                       22.5465C20.8067 22.6735 21.3199 22.42 21.5063
                                                       21.9422C21.5808 21.7511 21.5948 21.5418 21.5464
                                                       21.3425L20.5016 17.046L20.6727 16.6367C21.5576
                                                       14.4813 22 12.7691 22 11.5ZM19.2714
                                                       16.1004L18.9265 16.9169L19.862 20.7641L16.4454
                                                       19.4313L15.5123 19.8183C13.5445 20.6124 12.0245
                                                       21 11 21C5.75329 21 1.5 16.7467 1.5 11.5C1.5
                                                       6.25329 5.75329 2 11 2C16.2467 2 20.5 6.25329
                                                       20.5 11.5C20.5 12.5455 20.097 14.0952 19.2714
                                                       16.1004Z" fill="#262626" />
                                                </svg>
                                            </div>
                                        </li>
                                    </ul>
                                    <button class="save-collection-btn">
                                       <svg class="save save-'.$f['id'].'" style="'.$saved.'" onclick="postFav('.$f['id'].',`add`)" color="#262626" fill="#262626" height="24" role="img" viewBox="0 0 24 24"
                                          width="24">
                                          <polygon fill="none" points="20 21 12 13.44 4 21 4
                                             3 20 3 20 21" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round"
                                             stroke-width="2"></polygon>
                                       </svg>
                                       <svg class="remove remove-'.$f['id'].'" style="'.$removesaved.'" onclick="postFav('.$f['id'].',`remove`)" color="#262626" fill="#262626" height="24" role="img"
                                          viewBox="0 0 24 24" width="24">
                                          <path d="M20 22a.999.999 0 01-.687-.273L12
                                             14.815l-7.313 6.912A1 1 0 013 21V3a1 1 0
                                             011-1h16a1 1 0 011 1v18a1 1 0 01-1 1z"></path>
                                       </svg>
                                    </button>
                                 </div>
                                 <p>
                                    '.$f['post_meta'].'
                                 </p>
                                 <span>'.date('M d, Y',$f['time']).'</span>
                              </div>
                           </div>
                        </div>
                    ';
                }   
            } else {
                $arr['html'] = 'empty';
            }    
            echo json_encode($arr);
        break;

        case 'loadFeedComments':
            $fid = secureEncode($_GET['fid']);
            $arr = array();
            $cFilter = 'WHERE fid = '.$fid;
            $comments = getArray('feed_comments',$cFilter,'time DESC','LIMIT 0,65'); 
            foreach ($comments as $c) {
                $c['photo'] = profilePhoto($c['uid']);
                $c['username'] = getData('users','username','WHERE id = '.$c['uid']);
                $c['liked'] = getData('feed_comments_likes','cid','WHERE cid = '.$c['id'].' AND uid = '.$uid);
                $arr[] = $c;
            }           
            echo json_encode($arr);
        break;

        case 'removeFeed':
            $fid = secureEncode($_GET['fid']);
            $uid = secureEncode($_GET['uid']);
            $arr = array();
            $time = time();
            deleteData('feed','WHERE uid = '.$uid.' AND id = '.$fid);
            deleteData('feed_comments','WHERE fid = '.$fid);
            deleteData('feed_likes','WHERE fid = '.$fid);            
            deleteData('users_feed_favs','WHERE fid = '.$fid);
            deleteData('users_feed_purchases','WHERE fid = '.$fid);
            $arr['OK'] = 'OK';
            echo json_encode($arr);
        break;        

        case 'like_comment':
            $query = secureEncode($_GET['query']);
            $data = explode(',',$query);
            $time = time();
            $uid = $data[0];
            $cid = $data[1];
            $action = $data[2];
            $time = time();
            if($action == 1){
                $query = "INSERT INTO feed_comments_likes (cid,uid,time) VALUES ('".$cid."', '".$uid."', '".$time."')";
            } else {
                $query = "DELETE FROM feed_comments_likes WHERE cid = '".$cid."' AND uid = '".$uid."'";
            }

            $mysqli->query($query);             
        break;

        case 'post_fav':
            $arr = array();
            $time = time();
            $query = secureEncode($_GET['query']);
            $data = explode(',',$query);
            $uid = $data[0];    
            $post = $data[1];
            $action = $data[2];

            if($action == 'add'){
                $cols = 'uid,fid,time';
                $vals = $uid.','.$post.',"'.$time.'"';
                insertData('users_feed_favs',$cols,$vals);      
            } else {
                deleteData('users_feed_favs','WHERE uid = '.$uid.' AND fid = '.$post);
            }
            $arr['OK'] = 'OK';
            echo json_encode($arr);             
        break;

        case 'post_purchase':
            $arr = array();
            $time = time();
            $query = secureEncode($_GET['query']);
            $data = explode(',',$query);
            $uid = $data[0];    
            $post = $data[1];
            $action = $data[2];

            if($action == 'purchase'){
                $cols = 'uid,fid,time';
                $vals = $uid.','.$post.',"'.$time.'"';
                insertData('users_feed_purchases',$cols,$vals);     
            } else {
                deleteData('users_feed_purchases','WHERE uid = '.$uid.' AND fid = '.$post);
            }
            $arr['OK'] = 'OK';
            echo json_encode($arr);             
        break;   



    }
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    switch ($_POST['action']) {       
        case 'uploadFeedMedia':
            $time = time();
            $arr = array();

            if(isset($_POST['create_post'])){

                $blur = '';
                $media = '';
                $video = '';
                $audio = '';
                $uid = secureEncode($_POST['uid']);
                $private = secureEncode($_POST['private']);
                $message = secureEncode($_POST['message']);
                $comments = secureEncode($_POST['comments']);
                $gender = getData('users','gender','where id ="'.$uid.'"');

                $post_type = 'image';

                if(!empty($_POST['media'])){
                    $blur = secureEncode($_POST['media'][0]['path']);
                    $media = secureEncode($_POST['media'][0]['path']);              
                    $video = secureEncode($_POST['media'][0]['video']);
                    $audio = 0;
                } else {
                    $post_type = 'text';
                }

                if($video == 1){
                    $post_type = 'video';
                }
                if($audio == 1){
                    $post_type = 'audio';
                }                                           

                $cols = 'uid,post_type,post_src,post_meta,post_premium,time,visible,post_blur,post_disable_comments,gender';
                $vals = $uid.',"'.$post_type.'","'.$media.'","'.$message.'","'.$private.'","'.$time.'",1,"'.$blur.'","'.$comments.'","'.$gender.'"';
                insertData('feed',$cols,$vals);         
            }


            echo json_encode($arr);          
        break;

        case 'feedLike':

            $arr = array();
            $user = secureEncode($_POST['user']);
            $feed = secureEncode($_POST['fid']);
            $motive = secureEncode($_POST['motive']);
            $count = secureEncode($_POST['count']);     ;              
            if($motive == 'like'){
                $cols = 'fid,uid,time';
                $vals = $feed.','.$user.','.time();
                insertData('feed_likes',$cols,$vals);
            } else {
                $delete = 'WHERE fid = '.$feed.' AND uid = '.$user;
                deleteData('feed_likes',$delete);
            }
            $count = getData('feed','likes','where id ='.$feed);
            
            if($motive == 'remove'){
                $count = $count - 1;
            } else {
                $count = $count + 1;
            }
            updateData('feed','likes',$count,'WHERE id ='.$feed);

            $arr['OK'] = 'Yes';
            echo json_encode($arr);     
        break;

        case 'feedComment':

            $arr = array();
            $user = secureEncode($_POST['user']);
            $feed = secureEncode($_POST['fid']);
            $motive = secureEncode($_POST['motive']);
            $comment = secureEncode($_POST['comment']);

            if($motive == 'comment'){
                $cols = 'fid,uid,comment,time';
                $vals = $feed.','.$user.',"'.$comment.'",'.time();
                insertData('feed_comments',$cols,$vals);
            }

            $arr['OK'] = 'Yes';
            echo json_encode($arr);      
        break;      

        default:

        break;
    }
}