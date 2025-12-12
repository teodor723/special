var reelsUrl = request_source()+'/reels.php';
var reels = [];
var swiper;
var currentReel = 0;
var uploadReel = {};
var editReel = {};
var forceNewReelLoad = false;
var updatingAfterSlide = false;
var fromTrending = 0;
var moreReelsToLoad = false;
var initLoadLimit = 0;
var loadedReelsLenght = 0;
var initLoadType = 0;

uploadReel['action'] = 'uploadReel';
uploadReel['caption'] = '';
uploadReel['price'] = 0;
uploadReel['path'] = '';

editReel['action'] = 'editReel';
editReel['caption'] = '';
editReel['price'] = 0;

if(mobileSite === true){
    var user_info = user;
}

uploadReel['uid'] = user_info.id;
uploadReel['gender'] = user_info.gender;

var reelExtFilter = ["mp4", "ogg", "webm"];
$("#upload-reel").dmUploader({
    url: siteUrl+'/assets/sources/upload.php',
    extFilter: reelExtFilter,
    multiple: false,
    onFileExtError: function(file){
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[942]['text'];
        if(mobileSite){
            pushNotifMobileReel(data,1,3000);
        } else {
            pushNotif(data,1);  
        }
    },
    onNewFile: function(id, file){
        var fileUrl = URL.createObjectURL(file);
        var fileType = file.type;
        if(file.size > site_config.max_upload){
            var maxAllowed = site_config.max_upload / 1024 / 1024;
            var data = [];
            data.name = '';
            data.icon = user_info.profile_photo;
            data.message = lang[809]['text']+' '+maxAllowed+' MB';
            if(mobileSite){
                pushNotifMobile(data,1,5000);
            } else {
                pushNotif(data,1);  
            }            
            return false;
        }

        $('.upload-reel-container').show();
        $('.active-reels').hide();
        if(swiper !== undefined){
            swiper.slideTo(0);
            swiper.destroy();            
        }
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
        createReelPreview(file, fileUrl,id);  
        $('.uploadingReelTitleStart').hide();
        $('.uploadingReelTitle').show();
        $('#uploadReelSidebar').show();
        $('#uploadReelInfo').show();
    },    
    onUploadProgress: function(id,percent){
        $('.uploadingReelProgress').text(percent+'%');
    },
    onComplete: function(){
    },
    onUploadSuccess: function(id, file){
    
    $('.uploadingReel').hide();
    $('#uploadingReelVideo').removeClass('uploadingReelVideo');
    $('#uploadingReelVideo').removeClass('isPrivate');
    $('#uploadReelBtnComplete').show();

    var reelPath = file.path;
    upphotos[0] = file;
    uploadReel['path'] = file.path;
    
  }
  
});

function createReelPreview(file,fileContents,id) {
  var $previewElement = '';
  switch (file.type) {
    case 'video/mp4':
    case 'video/webm':
    case 'video/ogg':
      $('#uploadingReelVideo').html('<video webkit-playsinline="true" playsinline="true" autoplay muted loop><source src="' + fileContents + '" type="' + file.type + '"></video>');
      break;
    default:
    break;
  }
}

function pushNotifMobileReel(data,type=0,time=1000){
    if(!$('.chatNotification').hasClass('is-visible')){     
        $('.chatNotification').attr('ng-click',"hideNotification()");                           
        $('.chatNotification').removeClass('is-visible');
        $('.chatNotificationPhoto').removeClass('sblur');   
        $('.chatNotificationPhoto').css('background-image', 'url('+ data.icon +')');
        $('.chatNotificationContent').html(data.message);
        setTimeout(function(){
            if(!$('.chatNotification').hasClass('is-visible')){
                $('.chatNotification').addClass('is-visible');
            }
        },100);             
        setTimeout(function(){
            if($('.chatNotification').hasClass('is-visible')){
                $('.chatNotification').removeClass('is-visible');
            }
        },time);                    
    }
}

function reelUploadPrivate(){
    var checked = $('#privateReelCheckbox:checked').val();
    if(checked != 'on'){
        $('#reelPrice').hide();
        $('#privateReelPrice').val(0).change();
    } else {
        $('#reelPrice').show();
    }   
}

function updateMyReel(action){
    if(action == 'price'){
        var price = $('#privateReelPrice').val();
        price = parseInt(price);
        uploadReel['price'] = price;
    }

    if(action == 'caption'){
        var caption = $('#uploadReelCaption').val();
        uploadReel['caption'] = caption;
    }
}

function editCurrentReel(reel,action){
    if(action == 'edit'){
        editReelBtn(reel);  
    } else {
        upEditedReel(reel);
    }
}

function editMyReel(action,val){
    if(action == 'price'){
        val = parseInt(val);
    }
    editReel[action] = val;
}

function upEditedReel(reelId){
    editReel['reel'] = reelId;
    editReel['caption'] = $('#editReelTextarea'+reelId).val();
    editReel['price'] = $('#editReelPrice'+reelId).val();
    if(editReel['caption'] == ''){
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[943]['text'];
        if(mobileSite){
            pushNotifMobileReel(data,1,5000);
        } else {
            pushNotif(data,1);  
        }  
        return false; 
    }

    $('#editCurrentReelBtn'+reelId).show();
    $('#editCurrentReelBtnCheck'+reelId).hide();
    
    $.ajax({
      type: "POST",
      url: request_source()+'/reels.php',
      data: editReel,
      dataType: 'JSON',
      success: function(response) {
        if(mobileSite){
            angular.element(document.querySelector('#goToMyReels')).triggerHandler('click');
        } else {
            reelsTab(4); 
        }

        $('#currentReelCaption'+reelId).val(editReel['caption']);
        $('#editReel'+reelId).hide();
        $('#reelInfo'+reelId).show();
        
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[944]['text'];
        if(mobileSite){
            pushNotifMobileReel(data,1,5000);
        } else {
            pushNotif(data,1);  
        }          
      }
    });
}

function upReel(){

    if(uploadReel['caption'] == ''){
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[943]['text'];
        if(mobileSite){
            pushNotifMobileReel(data,1,5000);
        } else {
            pushNotif(data,1);  
        }  
        return false; 
    }

    if(uploadReel['path'] == ''){
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[945]['text'];
        if(mobileSite){
            pushNotifMobileReel(data,1,5000);
        } else {
            pushNotif(data,1);  
        }  
        return false; 
    }    


    $('#uploadReelBtnComplete').hide();
    $.ajax({
      type: "POST",
      url: request_source()+'/reels.php',
      data: uploadReel,
      dataType: 'JSON',
      success: function(response) {
        if(mobileSite){
            angular.element(document.querySelector('#goToMyReels')).triggerHandler('click');
        } else {
            reelsTab(4); 
        }
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[946]['text'];
        if(mobileSite){
            pushNotifMobileReel(data,1,5000);
        } else {
            pushNotif(data,1);  
        }          
      }
    });
}

function editReelBtn(reel){
    $('#reelInfo'+reel).hide();
    $('#editReel'+reel).show();
    $('#editCurrentReelBtn'+reel).hide();
    $('#editCurrentReelBtnCheck'+reel).show();    
}


function likeReel(rid,liked){
    if(liked == 0){ //REMOVE LIKE

        $('#reelLiked'+rid).hide();
        $('#reelNoLiked'+rid).show();
        $.ajax({
            url: request_source()+'/reels.php',
            data: {
                action: 'reelLike',
                rid: rid,
                user: user_info.id,
                motive: 'remove'
            },
            type: "post",
            dataType: 'JSON',
            success: function(response) {},
        });
    } else { //ADD LIKE

        $('#reelLiked'+rid).show();
        $('#reelNoLiked'+rid).hide();
        $.ajax({
            url: request_source()+'/reels.php',
            data: {
                action: 'reelLike',
                rid: rid,
                user: user_info.id,
                motive: 'like'
            },
            type: "post",
            dataType: 'JSON',
            success: function(response) {},
        });            
    }
}

function hideReelNothingToShow(){
    $('#reelNothingToShow').hide();
}

function loadWebReels(customFilter='',forceNewLoad=false,trending=0){
    loadingReels = true;

    $('.active-reels').show();
    $('.upload-reel-container').hide();
    

    if(reels.length > 0 && !forceNewLoad){
        var i = 0;
        setTimeout(function(){
            initSwiper(currentReel);         
        },1000);
    } else {    
        initLoadLimit = 0;      
        currentReel = 0;

      $.getJSON( reelsUrl, { action: 'loadDynamicReels', uid: user_info.id, looking: user_info.s_gender, customFilter: customFilter, trending:trending, limit: initLoadLimit},function(data){

        reels = [];
        $('.swiper-wrapper').removeAttr('style');
        $('.swiper-slide').removeAttr('style');     
           
        if(data.html == 'NORESULTS'){
            $('#reels-content').html('');
            if(customFilter == 'me'){
                $('.upload-reel-container').show();
                $('.swiper-wrapper').show();
                if(swiper !== undefined){
                    swiper.slideTo(0);
                    swiper.destroy();
                }
                reels = [];
                $('.swiper-wrapper').removeAttr('style');
                $('.swiper-slide').removeAttr('style');  
                $('.active-reels').hide();
            } else {
                $('.upload-reel-container').hide();                 
                $('#reelNothingFound').show();
            }

            if(customFilter != ''){
                $('[data-reel-tab]').removeClass('reel-tab-selected');
            }

        } else {    
            $('#reelNothingToShow').hide();
            $('#reelNothingFound').hide();            
            $('#reels-content').html(data.html);
            if(data.reels.length >= 10){
                moreReelsToLoad = true;
            } else {
                moreReelsToLoad = false;
            }

            forceNewReelLoad = false;   
            if(customFilter != ''){
                if(swiper !== undefined){
                    swiper.slideTo(0);
                    swiper.destroy();
                }
                $('.swiper-wrapper').removeAttr('style');
                $('.swiper-slide').removeAttr('style');                         
                currentReel = 0;
                if(customFilter != 'me'){
                    $('.upload-reel-container').hide();                             
                }
                forceNewReelLoad = true;
                $('[data-reel-tab]').removeClass('reel-tab-selected');
            }

            setTimeout(function(){
                $('.swiper-wrapper').removeAttr('style');
                $('.swiper-slide').removeAttr('style');                         
                initSwiper();    
            },50);
        }                   
      });
    }
}

var prev_vid = 0;
var next_vid = 0;
var firstLoad = true;
var loadingMoreReels = false;

function initSwiper(slideTo=0){
    var intentViewMore = 0;
    swiper = new Swiper(".swiper-container", {
        direction: "vertical",
        lazyload: {
            loadPrevNext: true,
            loadPrevNextAmount: 1,
            loadOnTransitionStart: true,
            elementClass: 'swiper-lazy',
            loadingClass: 'swiper-lazy-loading',
            loadedClass: 'swiper-lazy-loaded',
            preloaderClass: 'swiper-lazy-preloader'
        },
        preventClicks :true,
        keyboard: {
            enabled: true
        },    
        initialSlide: currentReel           
    });

    swiper.on('after-slide',function(video,state){
        if(video !== undefined){
            if(video < 0){
                video = 0;
            }

            var lastViewedReel = $('#video'+video).attr('data-reel-id');
            var lastViewedReelUser = $('#video'+video).attr('data-reel-user-id');

            if(lastViewedReelUser != user_info.id){
                if(!updatingAfterSlide){
                    updatingAfterSlide = true;
                    $.ajax({
                        url: request_source()+'/reels.php', 
                        data: {
                            action: 'viewed',
                            rid: lastViewedReel,
                            uid: user_info.id,
                            from_trending: fromTrending
                        },
                        type: "GET",
                        dataType: 'JSON',
                        complete: function() {},
                    }); 
                    setTimeout(function(){
                        updatingAfterSlide = false;
                    },1500);                
                }
            }

            playVid(video);
                
            currentReel = video;
            prev_vid = currentReel-1;
            next_vid = currentReel+1;
            if(video >= 1){
               pauseVid(prev_vid);
               if(reels.length > video){
                pauseVid(next_vid);     
               }
            }

            if(state.progress > 0.85){
                //preload more reels
                if(moreReelsToLoad){
                    if(!loadingMoreReels){
                        loadingMoreReels = true;    
                        console.log('loading more reels');
                        $.ajax({
                            url: request_source()+'/reels.php', 
                            data: {
                                action: 'viewed',
                                rid: lastViewedReel,
                                uid: user_info.id,
                                from_trending: fromTrending
                            },
                            type: "GET",
                            dataType: 'JSON',
                            complete: function() {
                                loadMoreReels();
                            },
                        });                             
                        
                    }
                }
                setTimeout(function(){
                    loadingMoreReels = false;
                },3500)

                if(state.progress == 1 && !loadingMoreReels){
                    intentViewMore++;
                    if(intentViewMore > 2){
                        $('#reelNothingToShow').show();
                    }
                }
            }
        }
    });
}


function loadMoreReels(customFilter=''){
    loadingReels = true;

    if(initLoadType == 1){
        initLoadLimit = parseInt(initLoadLimit+10); 
    } else {
        initLoadLimit = 0;
    }
    
    $.getJSON( reelsUrl, { action: 'loadDynamicReels', uid: user_info.id, looking: user_info.s_gender, customFilter: customFilter, trending:initLoadType, limit: initLoadLimit},function(data){         
    
        if(data.html == 'NORESULTS'){
            console.log('no more reels to load');
            moreReelsToLoad = false;
        } else {
            var loaded = reels.concat(data.reels);
            reels = loaded;
            console.log('loaded '+data.reels.length+' new reels');
            loadedReelsLenght = data.reels.length;
            if(loadedReelsLenght == 10){
                moreReelsToLoad = true;
            } else {
                console.log('no more reels to load');
                moreReelsToLoad = false;
            }
            $('#reels-content').append(data.html);
            swiper.update();
        }               
    });
}

function playVid(video) {
    if($('#video'+video).length){
        $('#video'+video).trigger('play');
        $('#video'+video).prop('muted', false);
        $('#video'+video).get(0).setVolume = 100;
    }
}

function pauseVid(video) {
    if($('#video'+video).length){
        $('#video'+video).get(0).currentTime = 0;
        $('#video'+video).trigger('pause');
        $('#video'+video).prop('muted', true);
    }
}

function reelsTab(action){
    if(swiper !== undefined){
        swiper.slideTo(0);
        swiper.destroy();
    }

    reels = [];

    $('.swiper-wrapper').removeAttr('style');
    $('.swiper-slide').removeAttr('style');  
    $('.active-reels').hide();  

    fromTrending = 1;
    if(action == 0){
        fromTrending = 0;
        loadWebReels('',true,action); 
    }
    if(action == 1){
        loadWebReels('',true,action); 
    }        
    if(action == 2){
        loadWebReels('liked',true);
    }
    if(action == 3){
        loadWebReels('purchased',true);
    }
    if(action == 4){
        loadWebReels('me',true);
    }
    if(action == 5){
        $('#uploadReelContent').click();
    }      
    
    $('[data-reel-tab]').removeClass('active');

    if(action == 1){
        initLoadType = 1;
        fromTrending = 1;
        $('[data-reel-tab="trending"]').addClass('active');
    } 

    if(action == 0){
        fromTrending = 0;
        initLoadType = 0;
        $('[data-reel-tab="latest"]').addClass('active');
    }
    if(action > 1){
        $('[data-reel-tab='+action+']').addClass('active');
    }
}

function purchaseReel (reelId,reelPrice){

    if(user_info.credits < parseInt(reelPrice)){ 
        openWidget("purchaseCredits");
        return false;
    }

    var data = [];
    data.name = '';
    data.icon = user_info.profile_photo;
    data.message = site_lang[610].text+' '+reelPrice+ ' ' + site_lang[73].text;
    pushNotif(data,1);

    updateCredits(user_info.id,reelPrice,1,'Credits for purchase reel');

    pauseVid(currentReel);

    $('#reelMask'+reelId).remove();
    $('#reel'+reelId).removeClass('isPrivate');
    playVid(currentReel);

    $.ajax({
        url: request_source()+'/reels.php',
        data: {
          action: 'purchase_reel',
          rid: reelId,
          user: user_info.id,
          purchase_action: 'purchase'
        },
        type: "post",
        dataType: 'JSON',
        success: function(response) {},
    });
}

function deleteReel(rid){
  var result = confirm(site_lang[947]['text']);
  if (result) {
      $.ajax({
          url: request_source()+'/reels.php', 
          data: {
              action: 'removeReel',
              rid: rid,
              uid: user_info.id
          },
          type: "GET",
          dataType: 'JSON',
          success: function(response) {
            reelsTab(4);
          },
      });
  }  
}

function uploadReelFromTitle(){
    $('#uploadReelContent').click();
}