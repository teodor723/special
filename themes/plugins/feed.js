//FEED
var createPost = {};
var feedUrl = request_source()+'/feed.php';
if(mobileSite === true){
    var user_info = user;
}

function createFeedPost(){
    $('#previewMediaFeed').hide();

    createPost['create_post'] = 'Yes';
    createPost['message'] = $('#postMessage').val();
    if(createPost['message'] == ''){
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[928]['text'];    
        if(mobileSite){
          pushNotifMobile(data,1);  
        } else {
          alert(site_lang[928]['text']);
        }
        
        return false;
    }

    if(createPost['media'] === undefined){
        var data = [];
        data.name = '';
        data.icon = user_info.profile_photo;
        data.message = site_lang[929]['text'];    
        if(mobileSite){
          pushNotifMobile(data,1);  
        } else {
          alert(site_lang[929]['text']);
        }
      return false;      
    }

    if(user_info.credits < plugins['fgfeed']['creditsForUpload']){
        openWidget("purchaseCredits");
        return false;
    }

    if(plugins['fgfeed']['creditsForUpload'] > 0){
      var data = [];
      data.name = '';
      data.icon = '';
      data.message = site_lang[610]['text']+' '+plugins['fgfeed']['creditsForUpload']+' ' + site_lang[73]['text'];

      updateCredits(user_info.id,plugins['fgfeed']['creditsForUpload'],1,'Credits for purchase premium post');
      if(mobileSite){
          pushNotifMobile(data,1);
      } else {
          pushNotif(data,1);  
      }
    }

    var private = $('#postType').val();
    createPost['private'] = 'No';
    if(private == 'Private'){
        createPost['private'] = 'Yes';
    }
    var comments = $('#disableComments:checked').val();
    createPost['comments'] = 'No';
    if(comments == 'on'){
        createPost['comments'] = 'Yes';
    }    
    createPost['action'] = 'uploadFeedMedia';
    createPost['uid'] = user_info.id;

    $.ajax({
      type: "POST",
      url: request_source()+'/feed.php',
      data: createPost,
      dataType: 'JSON',
      success: function(response) { 
        setTimeout(function(){
          closeUploadFGFeed();
          loadUserFeed('me');
        },200);
      }
    });    
}

function likeFeed(fid,liked){

    if(liked == 0){ //REMOVE LIKE
        var count = parseInt($('[data-feed-likes='+fid+']').text()) - 1;
        $('[data-feed-likes='+fid+']').text(count);
        $('#unlike-'+fid).show();
        $('#like-'+fid).hide();
        $.ajax({
            url: request_source()+'/feed.php',
            data: {
                action: 'feedLike',
                fid: fid,
                user: user_info.id,
                count: count,
                motive: 'remove'
            },
            type: "post",
            dataType: 'JSON',
            success: function(response) {
            },
        });
    } else { //ADD LIKE

        if(user_info.credits < plugins['fgfeed']['creditsForLike']){
            openWidget("purchaseCredits");
            return false;
        }

        if(plugins['fgfeed']['creditsForLike'] > 0){
          var data = [];
          data.name = '';
          data.icon = user_info.profile_photo;;
          data.message = site_lang[610]['text']+' '+plugins['fgfeed']['creditsForLike']+' ' + site_lang[73]['text'];

          updateCredits(user_info.id,plugins['fgfeed']['creditsForLike'],1,'Credits for like post');
          if(mobileSite){
              pushNotifMobile(data,1);
          } else {
              pushNotif(data,1);  
          }
        }

        var count = parseInt($('[data-feed-likes='+fid+']').text()) + 1;
        $('[data-feed-likes='+fid+']').text(count);
        $('#unlike-'+fid).hide();
        $('#like-'+fid).show();

        $.ajax({
            url: request_source()+'/feed.php',
            data: {
                action: 'feedLike',
                fid: fid,
                user: user_info.id,
                count: count,
                motive: 'like'
            },
            type: "post",
            dataType: 'JSON',
            success: function(response) {
            },
        });
    }
}

function publishComment(fid){
    var comment = $('#commentInput'+fid).val();
    if(comment == ''){
        return false;
    }

    var count = parseInt($('[data-feed-comments='+fid+']').text()) + 1;
    $('[data-feed-comments='+fid+']').text(count);

    $('#postComments'+fid).append(`
        <div class="flex">
            <div class="w-10 h-10 rounded-full relative flex-shrink-0 user-profile-photo" style="background-image: url('`+user_info.profile_photo+`')">
            </div>
            <div class="text-gray-700 py-2 px-3 rounded-lg bg-gray-100 h-full relative lg:ml-5 lg:mr-20  dark:bg-gray-800 dark:text-gray-100 ">
                <p class="leading-6 blurred-comment dark:bg-gray-800 dark:text-gray-100">
                    `+comment+`
                </p>
            </div>
        </div>
    `);

    $('#commentInput'+fid).val('');
    $('#postComments'+fid).scrollTop(500000);

    $.ajax({
        url: request_source()+'/feed.php',
        data: {
            action: 'feedComment',
            fid: fid,
            user: user_info.id,
            count: count,
            motive: 'comment',
            comment: comment
        },
        type: "post",
        dataType: 'JSON',
        success: function(response) {
        },
    });

}

function loadUserFeed(filter=''){
  if(filter != ''){
    $('#feed-content').html('');
    $('.loading-feed').show();
    //$('.insta-imgs-slider').slick('unslick');
    $('.insta-imgs-slide').remove();
  }
  $.getJSON( feedUrl, { action: 'loadFeed',customFilter: filter,uid: user_info.id},function(data){
    if(data.html != ''){
        setTimeout(function(){
          if(data.html == 'empty'){
            $('.loading-feed').hide();
            $('#feed-content').append(`<center><span style="margin-top:15px;font-weight:bold">`+site_lang[917]['text']+`</center>`);
          } else {
            $('#feed-content').append(data.html);
            if(mobileSite){
              $('.goToChatFromFeedPost').hide();
            }
            $('.loading-feed').hide();
          }
        },300);      
      }
  });
}

if(url == 'feed'){
  loadUserFeed();  
}


$("#comment-write").on('keyup', function (e) {
    if (e.key === 'Enter' || e.keyCode === 13) {
      $('#post-comment').submit();
    }
});

function likeComment(cid,action){
  if(action == 1){
    $('[data-comment-id="comment'+action+cid+'"]').hide();
    $('[data-comment-id="comment0'+cid+'"]').show();
  } else {
    $('[data-comment-id="comment'+action+cid+'"]').hide();
    $('[data-comment-id="comment1'+cid+'"]').show();
  }
  var query = user_info.id+','+cid+','+action;
  $.get( feedUrl, {action: 'like_comment', query: query} ,function( data ) {});
}


function closeFeedComments(){
  $(".insta-comments").hide();
  $(".insta-comments-image").hide();
  $(".insta-comments-overlay").hide();
  $("body").removeClass("fixed");
  if(mobileSite){
    $('#feed-content').show();
    $('ion-content').css('overflow-y','auto');
  }   
}

function showFeedComments(fid,caption,photo,media,type){

  $(".insta-comments").show();
  $(".insta-comments-image").show();
  $(".insta-comments-overlay").show();
  $("body").addClass("fixed");

  $('#feed-comments').html('');
  $('#feed-comment').text(caption);
  $('#feed-comment-photo').attr('src',photo);

  if(type == 'video'){
    $('#feed-comment-media').html('<video autoplay muted loop width="100%" height="100%" style="object-fit:cover"><source src="' + media + '"></video>');
  } else {
    $('#feed-comment-media').html('');
    $('#feed-comment-media').css('background-image',`url(`+media+`)`);
  }

  $('#comment-write').focus();
  $('#feedID').val(fid);
  $('.insta-comments').scrollTop(0);
  if(mobileSite){
    $('ion-content').scrollTop(0);
    $('#feed-content').hide();
    $('ion-content').css('overflow-y','hidden');     
  }
  $.getJSON( feedUrl, { action: 'loadFeedComments', fid: fid },function(data){
    if(data.length > 0){
        data.forEach(function(comment) {
          console.log(comment);
          var hideLikeComment = 'style="display:none"';
          var showLikeComment = 'style="display:none"';
          if(comment.liked == 'noData'){
            hideLikeComment = 'style="display:none"';
            showLikeComment = '';
          } else {
            hideLikeComment = '';
            showLikeComment = 'style="display:none"';
          }
          var likeCommentBtn = `
          <button type="button" data-comment-id="comment1`+comment.id+`"
            onclick="likeComment(`+comment.id+`,1)" class="likeIcon" `+showLikeComment+`>
            <svg class="unlike" color="#262626" fill="#262626" height="24" role="img" viewBox="0 0 24 24" width="24">
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
          </button>
          <button type="button"  data-comment-id="comment0`+comment.id+`"
            onclick="likeComment(`+comment.id+`,0)" class="likeIcon" `+hideLikeComment+`>
            <svg class="liked" color="#ed4956" fill="#ed4956" height="24" role="img" viewBox="0 0 48 48" width="24">
              <path d="M34.6 3.1c-4.5 0-7.9 1.8-10.6
                  5.6-2.7-3.7-6.1-5.5-10.6-5.5C6 3.1 0 9.6 0
                  17.6c0 7.3 5.4 12 10.6 16.5.6.5 1.3 1.1 1.9
                  1.7l2.3 2c4.4 3.9 6.6 5.9 7.6 6.5.5.3 1.1.5
                  1.6.5s1.1-.2 1.6-.5c1-.6 2.8-2.2
                  7.8-6.8l2-1.8c.7-.6 1.3-1.2 2-1.7C42.7 29.6 48
                  25 48 17.6c0-8-6-14.5-13.4-14.5z"></path>
            </svg>
          </button>`;
          // if(user_info.id == comment.uid){
          //   likeCommentBtn = '';
          // }

          $('#feed-comments').append(`
            <li>
              <div class="comment-like-wrap">
                <div class="comment-wrap">
                  <a href="javascript:;" onclick="goToProfile(`+comment.uid+`);" class="comment-img">
                    <img src="`+comment.photo+`">
                  </a>
                  <div class="comment-content">
                    <p>
                      <a href="javascript:;" onclick="goToProfile(`+comment.uid+`);" class="commenter-name">`+comment.username+`</a>
                      `+comment.comment+`
                    </p>
                  </div>                 
                </div>
                `+likeCommentBtn+`                
              </div>
            </li>
          `);
        });
      }
  });

}

$('#post-comment').submit(function(e) {
  e.preventDefault();

  if(user_info.credits < plugins['fgfeed']['creditsForComment']){
      openWidget("purchaseCredits");
      return false;
  }

  if(plugins['fgfeed']['creditsForComment'] > 0){
    var data = [];
    data.name = '';
    data.icon = user_info.profile_photo;
    data.message = site_lang[610]['text']+' '+plugins['fgfeed']['creditsForComment']+' ' + site_lang[73]['text'];

    updateCredits(user_info.id,plugins['fgfeed']['creditsForComment'],1,'Credits for like post');
    if(mobileSite){
        pushNotifMobile(data,1);
    } else {
        pushNotif(data,1);  
    }
  }

  var comment = $('#comment-write').val();
  if(comment == ''){
      return false;
  }
  $('#comment-write').val('');
  $('#comment-btn').addClass('disable-btn');
  $('#feed-comments').prepend(`
    <li>
      <div class="comment-like-wrap first-commit">
        <div class="comment-wrap">
          <a href="javascript:;" onclick="goToProfile(`+user_info.id+`);" class="comment-img">
            <img src="`+user_info.profile_photo+`">
          </a>
          <div class="comment-content">
            <p>
              <a href="javascript:;" onclick="goToProfile(`+user_info.id+`);" class="commenter-name">`+user_info.username+`</a>
              `+comment+`
            </p>
          </div>
        </div>
      </div>
    </li>
  `);
  $('.insta-comments').scrollTop(0);
  var fid = $('#feedID').val();
  $.ajax({
      url: request_source()+'/feed.php',
      data: {
          action: 'feedComment',
          fid: fid,
          user: user_info.id,
          motive: 'comment',
          comment: comment
      },
      type: "post",
      dataType: 'JSON',
      success: function(response) {

      },
      complete: function (response) {
        addLikeBlockComment(user_info.id);
      },
  });

});

function addLikeBlockComment (fid) {
  $.getJSON( feedUrl, { action: 'firstIDComment', fid: fid },function(data){
    if(data.length > 0){
      data.forEach(function(comment) {
        var hideLikeComment = 'style="display:none"';
        var showLikeComment = 'style="display:none"';
        if(comment.liked == 'noData'){
          hideLikeComment = 'style="display:none"';
          showLikeComment = '';
        } else {
          hideLikeComment = '';
          showLikeComment = 'style="display:none"';
        }
        var likeCommentBtn = `
          <button type="button" data-comment-id="comment1`+comment.id+`"
            onclick="likeComment(`+comment.id+`,1)" class="likeIcon" `+showLikeComment+`>
            <svg class="unlike" color="#262626" fill="#262626" height="24" role="img" viewBox="0 0 24 24" width="24">
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
          </button>
          <button type="button"  data-comment-id="comment0`+comment.id+`"
            onclick="likeComment(`+comment.id+`,0)" class="likeIcon" `+hideLikeComment+`>
            <svg class="liked" color="#ed4956" fill="#ed4956" height="24" role="img" viewBox="0 0 48 48" width="24">
              <path d="M34.6 3.1c-4.5 0-7.9 1.8-10.6
                  5.6-2.7-3.7-6.1-5.5-10.6-5.5C6 3.1 0 9.6 0
                  17.6c0 7.3 5.4 12 10.6 16.5.6.5 1.3 1.1 1.9
                  1.7l2.3 2c4.4 3.9 6.6 5.9 7.6 6.5.5.3 1.1.5
                  1.6.5s1.1-.2 1.6-.5c1-.6 2.8-2.2
                  7.8-6.8l2-1.8c.7-.6 1.3-1.2 2-1.7C42.7 29.6 48
                  25 48 17.6c0-8-6-14.5-13.4-14.5z"></path>
            </svg>
          </button>`;

        $('.first-commit').append(likeCommentBtn).removeClass('first-commit');
      })
    }
  })
};

function postFav(post,method){
    var query = user_info.id+','+post+','+method;

    if(method == 'add'){
      $('.our-collection-'+post).addClass('save-collection');
      $('.our-collection-'+post).addClass('moveUp');
      $('.save-'+post).hide();
      $('.remove-'+post).show();
      setTimeout(function () {
        $(".our-collection-"+post).removeClass("moveUp");
      }, 3000);
    } else {
      $('.save-'+post).show();
      $('.remove-'+post).hide();
    }

    $.get( feedUrl, {action: 'post_fav', query: query} ,function( data ) {});
}

function purchasePremiumPost(post){

  if(user_info.credits < plugins['fgfeed']['creditsForPremium']){
      openWidget("purchaseCredits");
      return false;
  }

  var data = [];
  data.name = '';
  data.icon = '';
  data.message = site_lang[610]['text']+' '+plugins['fgfeed']['creditsForPremium']+' ' + site_lang[73]['text'];

  updateCredits(user_info.id,plugins['fgfeed']['creditsForPremium'],1,'Credits for purchase premium post');
  if(mobileSite){
      pushNotifMobile(data,1);
  } else {
      pushNotif(data,1);  
  }

  $('[data-feed-id='+post+']').removeClass('premium-post');
  $('[data-feed-id-premium='+post+']').remove();
  var query = user_info.id+','+post+',purchase';
  $.getJSON( feedUrl, {action: 'post_purchase', query: query} ,function( data ) {

  });
}

function updateFeedMeta(val){
  uploadFeed['meta'] = val;
}


function uploadUserFeed(){
  if(user_info.credits < plugins['fgfeed']['creditsForUpload']){
      openWidget("purchaseCredits");
      return false;
  }

  var data = [];
  data.name = '';
  data.icon = '';
  data.message = site_lang[610]['text']+' '+plugins['fgfeed']['creditsForUpload']+' ' + site_lang[73]['text'];

  updateCredits(user_info.id,plugins['fgfeed']['creditsForUpload'],1,'Credits for purchase premium post');
  if(mobileSite){
      pushNotifMobile(data,1);
  } else {
      pushNotif(data,1);  
  }

  $.ajax({
    url: request_source()+'/feed.php',
    data: uploadFeed,
    type: "post",
    dataType: 'JSON',
    success: function(response) {
      setTimeout(function(){
        window.location.href = site_url()+'feed';
      },1500)

    },
  });
}


function openUploadFGFeed(){
  $("#create-feed-post-overlay").show();
  $("body").addClass("fixed");  
  $('#create-feed-post').show();
  if(mobileSite){
    $('ion-content').css('overflow-y','hidden');
    $('#feed-content').hide();
  }
}
function closeUploadFGFeed(){
  $("#create-feed-post-overlay").hide();
  $("body").removeClass("fixed");  
  $('#create-feed-post').hide();
  if(mobileSite){
    $('ion-content').css('overflow-y','auto');
    $('#feed-content').show();
  }  
}

function uploadFeedMedia(){
  upType = 20;
  document.getElementById("uploadContent").click();
}


function feedSlider($slider,multiple){
  var count = 4;
  var currentSlide;
  var slidesCount;
  var sliderCounter = document.createElement("span");
  sliderCounter.classList.add("slider__counter");
  var maxDots = 6;
  var transformXIntervalNext = -10;
  var transformXIntervalPrev = 10;
  var multipleSlides = false;
  if(multiple == 'Yes'){
    multipleSlides = true;
  }

  $slider.on("init", function (event, slick) {
    $(this)
      .find("ul.slick-dots")
      .wrap("<div class='slick-dots-container'></div>");
    $(this)
      .find("ul.slick-dots li")
      .each(function (index) {
        $(this).addClass("dot-index-" + index);
      });
    $(this).find("ul.slick-dots").css("transform", "translateX(0)");
    setBoundries($(this), "default");
  });

  var transformCount = 0;
  $slider.on("beforeChange", function (event, slick, currentSlide, nextSlide) {
    var totalCount = $(this).find(".slick-dots li").length;
    if (totalCount > maxDots && multiple == 'Yes') {
      if (nextSlide > currentSlide) {
        if (
          $(this)
            .find("ul.slick-dots li.dot-index-" + nextSlide)
            .hasClass("n-small-1")
        ) {
          if (
            !$(this).find("ul.slick-dots li:last-child").hasClass("n-small-1")
          ) {
            transformCount = transformCount + transformXIntervalNext;
            $(this)
              .find("ul.slick-dots li.dot-index-" + nextSlide)
              .removeClass("n-small-1");
            var nextSlidePlusOne = nextSlide + 1;
            $(this)
              .find("ul.slick-dots li.dot-index-" + nextSlidePlusOne)
              .addClass("n-small-1");
            $(this)
              .find("ul.slick-dots")
              .css("transform", "translateX(" + transformCount + "px)");
            var pPointer = nextSlide - 3;
            var pPointerMinusOne = pPointer - 1;
            $(this)
              .find("ul.slick-dots li")
              .eq(pPointerMinusOne)
              .removeClass("p-small-1");
            $(this).find("ul.slick-dots li").eq(pPointer).addClass("p-small-1");
          }
        }
      } else {
        if (
          $(this)
            .find("ul.slick-dots li.dot-index-" + nextSlide)
            .hasClass("p-small-1")
        ) {
          if (
            !$(this).find("ul.slick-dots li:first-child").hasClass("p-small-1")
          ) {
            transformCount = transformCount + transformXIntervalPrev;
            $(this)
              .find("ul.slick-dots li.dot-index-" + nextSlide)
              .removeClass("p-small-1");
            var nextSlidePlusOne = nextSlide - 1;
            $(this)
              .find("ul.slick-dots li.dot-index-" + nextSlidePlusOne)
              .addClass("p-small-1");
            $(this)
              .find("ul.slick-dots")
              .css("transform", "translateX(" + transformCount + "px)");
            var nPointer = currentSlide + 3;
            var nPointerMinusOne = nPointer - 1;
            $(this)
              .find("ul.slick-dots li")
              .eq(nPointer)
              .removeClass("n-small-1");
            $(this)
              .find("ul.slick-dots li")
              .eq(nPointerMinusOne)
              .addClass("n-small-1");
          }
        }
      }
    }
  });

  var updateSliderCounter = function (slick, currentIndex) {
    currentSlide = slick.slickCurrentSlide() + 1;
    slidesCount = slick.slideCount;
    $(sliderCounter).text(currentSlide + "/" + slidesCount);
  };

  $slider.on("init", function (event, slick) {
    if(multiple == 'Yes'){
      $slider.append(sliderCounter);
      updateSliderCounter(slick);
    }
  });

  $slider.on("afterChange", function (event, slick, currentSlide) {
    updateSliderCounter(slick, currentSlide);
  });

  $slider.slick({
    dots: multipleSlides,
    infinite: false,
    arrows: false,
    speed: 800,
    slidesToShow: 1,
    slidesToScroll: 1,
  });
}

function setBoundries(slick, state) {
  if (state === "default") {
    slick.find("ul.slick-dots li").eq(4).addClass("n-small-1");
  }
}

function createFeedPreview(file,fileContents,id) {
  var $previewElement = '';
  switch (file.type) {
    case 'image/png':
    case 'image/jpeg':
    case 'image/gif':
      $('#preview-upload-image-feed').css('background-image',`url(`+fileContents+`)`);
      break;
    case 'video/mp4':
    case 'video/webm':
    case 'video/ogg':
      $('#preview-upload-image-feed').html('<video autoplay muted loop width="100%" height="100%"><source src="' + fileContents + '" type="' + file.type + '"></video>');
      break;
    default:
      break;
  }
  
}

function createRandomPosts(){
  $.ajax({
      url: request_source()+'/feed.php', 
      data: {
          action: 'randomPostGenerator',
      },
      type: "GET",
      dataType: 'JSON',
      success: function(response) {
        loadDataPost()
      },
  });   
}


function deleteFeed(fid){
  var result = confirm("Delete post?");
  if (result) {
      $.ajax({
          url: request_source()+'/feed.php', 
          data: {
              action: 'removeFeed',
              fid: fid,
              uid: user_info.id
          },
          type: "GET",
          dataType: 'JSON',
          success: function(response) {
            $('[data-feed-id='+fid+']').remove();
          },
      });
  }  
}