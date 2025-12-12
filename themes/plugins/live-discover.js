"use strict";

var live_mingle_user = 0;
var random_image_bg_interval;
function startLiveDiscover(uid,action='start'){
  $.get( aUrl, {action: 'liveMingle', uid: uid} );
  console.log('live discover activated');
  var video = document.querySelector("#localVideo");
  var videoRemote = document.querySelector("#remoteVideo");

  if(!mobileSite){
    $('#mingleChatInput').hide();  
  }
  
  $('#liveMingleName').text('');
  $('#mingleChatContainer').html('');
  $('#liveMinglePhoto').hide();
  $('#mingleChatBody').hide();
  $('#liveMNC').show();
  in_live_mingle = true;

  random_image_bg_interval = setInterval(function(){
    changeRandomImage();
  },2000);

  if(action == 'start'){
    if (navigator.mediaDevices.getUserMedia) {       
        navigator.mediaDevices.getUserMedia({audio: true, video: true})
      .then(function(stream) {
      video.srcObject = stream;
      window.stream = stream;
      window.localStream = stream;
      $('#localVideoPoster').hide();

      $("#video_button").click(function(){
        window.localStream.getVideoTracks()[0].enabled = !(window.localStream.getVideoTracks()[0].enabled);
        var check = $(this).hasClass( "on" );
        if(check === true){
          $(this).removeClass("on");            
          $(this).addClass("off"); 
          $('#localVideoPoster').hide();
          $('#localVideo').show();           
        } else {
          $(this).addClass("on");            
          $(this).removeClass("off");  
          $('#localVideo').hide(); 
          $('#localVideoPoster').show();           
        }
      });

      $("#audio_button").click(function(){
          var check = $(this).hasClass('on');            
          window.localStream.getAudioTracks()[0].enabled = !(window.localStream.getAudioTracks()[0].enabled);
          if(check === true){
            $(this).removeClass("on");            
            $(this).addClass("off");         
          }else {
            $(this).addClass("on");            
            $(this).removeClass("off");          
          }        
      });

      $('#remoteVideo').hide();
      $('#videoLoader').show();
      

      searchMingle(user_info.id);

      })
      .catch(function(err) {
        console.log(err); 
        if (err.name == "NotFoundError" || err.name == "DevicesNotFoundError") {
        } else if (err.name == "NotReadableError" || err.name == "TrackStartError") {
        } else if (err.name == "OverconstrainedError" || err.name == "ConstraintNotSatisfiedError") {
        } else if (err.name == "NotAllowedError" || err.name == "PermissionDeniedError") {
        } else if (err.name == "TypeError" || err.name == "TypeError") {
        } else {
           console.log('unknown error');
        }
      });
    }  
  }

  if(action == 'continue'){
    console.log('Searching next mingle');
    $('#remoteVideo').hide();
    $('#videoLoader').show();
    searchMingle(user_info.id);    
  }

}

function toggleMingleFilter(el){
    if(plugins['liveDiscover']['premium_filter_gender'] == 'Yes' && user_info.premium == 0){
      var data = [];
      data.name = site_config.name;
      data.icon = user_info.profile_photo;
      data.message = site_lang[144].text;
      pushNotif(data,1);   
      return false;
    }  
  $(el).toggleClass('mcnb-open');
}

function filterSearchMingle(gender,genderText){
  updateMingleGender(user_info.id,gender);
  live_mingle_filter = gender;
  $('#mingleFilterText').text(genderText);
  finishMingle('filter');
  $('#remoteVideo').hide();
  startLiveDiscover(user_info.id);
}



function changeRandomImage(){
  $.getJSON( aUrl, {action: 'randomImageLoader',gender: user_info.looking} ,function( data ) {
    $('#randomImageLoader').attr('src',data.image);
  });
}

var searchAgainInterval;
function searchMingle(me){
  $('.live-mingle-header').hide();
  $.getJSON( aUrl, {action: 'searchMingle',user: me,gender: live_mingle_filter} ,function( data ) {
    if(data.length > 0){
      if(data[0].result == 'ok'){
        var call = peer.call(data[0].peer_id, window.localStream);
        mingleCall(call);
      } else {
        searchAgainInterval = setTimeout(function(){
          searchMingle(user_info.id);
        },1000)
      }
    }
  });
}

function mingleCall(call) {
  var video = document.querySelector("#remoteVideo");
  call.on('stream', function(stream){   
    video.srcObject = stream;
  });

  window.existingCall = call;
  call.on('close', function(){
    console.log('call ended');
  }); 
  clearTimeout(searchAgainInterval);
  $.getJSON( aUrl, {action: 'income',query: call.peer} ,function( data ) {
      $('.live-mingle-header').show();
      if(data.gender == 2){
        $('.chat-head').addClass('gradient34');
        $('.chat-head').removeClass('gradient23');
      } else {
        $('.chat-head').removeClass('gradient34');
        $('.chat-head').addClass('gradient23');
      }

      $('#liveMingleName').text(data.name+','+data.age);
      $('#liveMinglePhoto').show();
      $('#mingleChatInput').show();
      $('#liveMNC').hide();
      $('#videoLoader').hide();
      $('#remoteVideo').show();

      $('#liveMinglePhoto').css("background-image",'url(' + data.photo + ')')
      live_mingle_user = data.id;
      $('#mingleChatBody').show();

      channel.unbind('livemingle'+user_info.id);
      var liveMsgBind = 'livemingle'+user_info.id;
      channel.bind(liveMsgBind, function(data) {
        if(data.type == 'message'){
          liveMingleNewMsg(``+data.name+``,``+data.msg+``);  
        }
        if(data.type == 'finish'){
          console.log('finish current mingle');
          live_mingle_user = 0;
          startLiveDiscover(user_info.id,'continue');
        }
      });

  });
}

function finishMingle(action=""){
    $.get( aUrl, {action: 'finishMingle',uid: user_info.id} ,function( data ) {});  
    if(in_live_mingle === true){
      var send = user_info.id+'[rt]'+live_mingle_user;      
      $.get( gUrl, {action: 'liveMingleFinish', query: send} );          
    }

    clearInterval(random_image_bg_interval);

    in_live_mingle = false;
    live_mingle_user = 0;

    if(action == ''){
      if (typeof window.localStream !== 'undefined'){
          window.localStream.getAudioTracks().forEach(function(track) {
              track.stop();
          });

          window.localStream.getVideoTracks().forEach(function(track) {
              track.stop();
          });
      }
      
      if (typeof window.stream !== 'undefined'){
          window.stream.getAudioTracks().forEach(function(track) {
              track.stop();
          });
          window.stream.getVideoTracks().forEach(function(track) {
              track.stop();
          }); 
      }
    }
}

function liveMingleChat(){
  var msg = $('#chatLiveMingle').val();
  $('#chatLiveMingle').val('');
  $('#mingleChatContainer').append(`
    <div class="m-b">
        <div>
            <div class="pos-rlt r r-2x"><span class="arrow right arrow-primary pull-up"></span>
                <div class="m-b-none chatmsg  " data-msg="`+user_info.first_name+`: ">`+msg+`</div>
            </div>
        </div>
    </div>
  `);


  var send = user_info.id+'[rt]'+live_mingle_user+'[rt]'+user_info.first_name+'[rt]'+msg;      
  $.get( gUrl, {action: 'liveMingleMsg', query: send} );  
}


function liveMingleNewMsg(name,msg){
  $('#mingleChatContainer').append(`
    <div class="m-b">
        <div>
            <div class="pos-rlt r r-2x"><span class="arrow right arrow-primary pull-up"></span>
                <div class="m-b-none chatmsg  " data-msg="`+name+`: ">`+msg+`</div>
            </div>
        </div>
    </div>
  `);
  if(!mobileSite){
    $('#mingleChatContainer').mCustomScrollbar("destroy");
    scroller();
  }
}

function likeLiveMingleUser(like){
  if(like == 1){
  }
  $.get( aUrl, { action: 'mingle_like', uid1: user_info.id, uid2: live_mingle_user, uid3: like } );
  finishMingle('likeAction');
  $('#remoteVideo').hide();
  startLiveDiscover(user_info.id);  
}

function updateMingleGender(user,gender){
  $.get( aUrl, {action: 'updateMingleGender',user:user, gender:gender} );  
}

window.onbeforeunload = function(e) {
  if(in_live_mingle){
    var send = user_info.id+'[rt]'+live_mingle_user;      
    $.get( gUrl, {action: 'liveMingleFinish', query: send} );    
    $.get( aUrl, {action: 'finishMingle',uid: user_info.id} ,function( data ) {});          
  }
};

