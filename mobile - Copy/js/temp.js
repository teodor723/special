$scope.openEditProfileModal = function() {
    $scope.editProfileModal.show();
    user = $localstorage.getObject('user');
    lang = $localstorage.getObject('lang');
    $('[data-lid]').each(function(){
      var id = $(this).attr('data-lid');
      $(this).text(lang[id].text);
    });

    alang = $localstorage.getObject('alang');

    $scope.lang = [];
    $scope.alang = [];
    angular.forEach(lang,function(entry) {						  
      $scope.lang.push({
        id: entry,
        text: entry.text
      });
    })	

    angular.forEach(alang,function(entry) {						  
      $scope.alang.push({
        id: entry,
        text: entry.text
      });
    })			

    $scope.noData = '...';
    $scope.lang = lang[134].text;
    $scope.loading = false;
    $scope.bio = user.bio;
    $scope.name = user.name;
    $scope.age = user.age;		
    $rootScope.uphotos = usPhotos;

    $rootScope.questions = user.question;	
        
    angular.forEach(config.interests,function(i,index) {
        if(user.interest[i.id] === undefined){
            config.interests[index].selected = 'grayScale';
            config.interests[index].action = 'add';
        } else {
            config.interests[index].selected = '';
            config.interests[index].action = 'remove';
        }

    });

    $scope.interests = config.interests;

    $scope.selectInterest = function(id){
    var query = user.id+','+id;
    var action = $('#interest'+id).attr('data-interest-action');
    if(action == 'add'){
        $('#interest'+id).removeClass('grayScale');
        $('#interest'+id).attr('data-interest-action','remove');	    	
        A.Query.get({action: 'add_interest', query: query});	
    } else {
        $('#interest'+id).addClass('grayScale');
        $('#interest'+id).attr('data-interest-action','add');		    	
        A.Query.get({action: 'del_interest', query: query});				
    }
        
    }

    var answers = user.question;
    var la = user.lang;
    angular.forEach(config.languages,function(lang) {
        if(lang['id'] == user.lang){
            $scope.userLang = lang['name'];
        }
    });
    
    $rootScope.updateUserLanguage = function() {
      $rootScope.openLanguagesModal();
      $rootScope.languages = site_config.languages;
    }

    $scope.updateNotification = function(e,a) {
        if(a === true){
            a = 1;
        } else {
            a = 0;
        }
        var message = user.id+','+e+','+a;
        $scope.ajaxRequest = A.Query.get({action: 'updateNotification', query: message});
        $scope.ajaxRequest.$promise.then(function(){											
        });			
    };

    if(user.notification.fan.inapp == 1){
        $scope.fans = true;
    } else {
        $scope.fans = false;
    }
    if(user.notification.near_me.inapp == 1){
        $scope.near_me = true;
    } else {
        $scope.near_me = false;
    }

    if(user.notification.match_me.inapp == 1){
        $scope.matches = true;
    } else {
        $scope.matches = false;
    }
    if(user.notification.message.inapp == 1){
        $scope.messages = true;
    } else {
        $scope.messages = false;
    }		

    $scope.pick = function(id=0) {
        photoUploadSlot = id;
        upType = 1;
        $('#uploadContent').click();		
    };

    $rootScope.selectLanguage = function(id){
        if(updateVisitorLanguage){
            window.location.href = siteUrl+'mobile/index.php?lang='+id;
        } else {
            $scope.loading = true;
            var message = user.id+','+id;
            $scope.ajaxRequest34 = A.Query.get({action: 'updateUserLanguage', query: message});
            $scope.ajaxRequest34.$promise.then(function(){											
                $rootScope.closeLanguagesModal();
                $scope.closeEditProfileModal();
                siteLang = id;
                $state.go('loader');
            });	
        }		
    }

    $rootScope.updateUserQuestion = function(q,a) {
      if(q.method == 'select'){
          var hideSheet = $ionicActionSheet.show({
            buttons: a,
            cancelText: alang[2].text,
            cancel: function() {
              },
            buttonClicked: function(index,val) {
                var message = user.id+'[divider]'+q.id+'[divider]'+val.text;
                $scope.loading = true;
                var e = angular.element(document.getElementsByClassName('userAnswer'+q.id));
                e.text(val.text);
                e.css('color', '#111');
                $scope.ajaxRequest34 = A.Query.get({action: 'updateUserExtended', query: message});
                $scope.ajaxRequest34.$promise.then(function(){											
                    $localstorage.setObject('user', $scope.ajaxRequest34.user);
                    $scope.loading = false;
                });				
              return true;
            }
          });
      } else {

      }

    }

  $rootScope.showPhotoOptions = function(val,pid,blocked,profile,approved) {
           if(approved == 0){
               awlert.neutral(lang[697].text,2000);
               return false;
           }
      var hideSheet = $ionicActionSheet.show({
        buttons: [
          { text:  lang[289].text },
          { text:  lang[292].text },
        ],
        cancelText: lang[617].text,
        cancel: function() {
          },
        buttonClicked: function(index) {
          if(index ==0){
              var p = $rootScope.uphotos[0];
              if(val == 2){
                var n = $rootScope.uphotos[1];
                $rootScope.uphotos[0] = n;
                $rootScope.uphotos[1] = p;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'updateUserProfilePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    $localstorage.setObject('user', $scope.ajaxRequest.user);
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                }); 
              }
              if(val == 3){
                var n = $rootScope.uphotos[2]
                $rootScope.uphotos[0] = n;
                $rootScope.uphotos[2] = p;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'updateUserProfilePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    $localstorage.setObject('user', $scope.ajaxRequest.user);
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                }); 
              }
              if(val == 4){
                var n = $rootScope.uphotos[3]
                $rootScope.uphotos[0] = n;
                $rootScope.uphotos[3] = p;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'updateUserProfilePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    $localstorage.setObject('user', $scope.ajaxRequest.user);
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                }); 
              }
              if(val == 5){
                var n = $rootScope.uphotos[4]
                $rootScope.uphotos[0] = n;
                $rootScope.uphotos[4] = p;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'updateUserProfilePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    $localstorage.setObject('user', $scope.ajaxRequest.user);
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                }); 
              }
              if(val == 6){
                var n = $rootScope.uphotos[5]
                $rootScope.uphotos[0] = n;
                $rootScope.uphotos[5] = p;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'updateUserProfilePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    $localstorage.setObject('user', $scope.ajaxRequest.user);
                    usPhotos = $scope.ajaxRequest.user.photos;
                    $rootScope.me = $scope.ajaxRequest.user;
                }); 
              }
          }
          if(index == 1){
              if(val == 2){
                $rootScope.uphotos[1] = null;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'deletePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                }); 				
              }
              if(val == 3){
                $rootScope.uphotos[2] = null;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'deletePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                });
              }
              if(val == 4){
                $rootScope.uphotos[3] = null;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'deletePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    usPhotos = $scope.ajaxRequest.user.photos;
                    $rootScope.me = $scope.ajaxRequest.user;	
                });
              }
              if(val == 5){
                $rootScope.uphotos[4] = null;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'deletePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                });
              }
              if(val == 6){
                $rootScope.uphotos[5] = null;
                var m = user.id +','+pid;
                $scope.ajaxRequest = A.Query.get({action: 'deletePhoto', query: m});
                $scope.ajaxRequest.$promise.then(function(){							
                    usPhotos = $scope.ajaxRequest.user.photos;	
                    $rootScope.me = $scope.ajaxRequest.user;
                });
              }
              
          }
          return true;
        }
      });
  }	
  

    function validateEmail(email) {
        var re = /^(([^<>()\[\]\\.,;:\s@"]+(\.[^<>()\[\]\\.,;:\s@"]+)*)|(".+"))@((\[[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}\.[0-9]{1,3}])|(([a-zA-Z\-0-9]+\.)+[a-zA-Z]{2,}))$/;
        return re.test(email);
    }
    

    function checkUsernameEmail(col,username,email){
        try {	
            var query = username+','+email;
            $scope.checkUsernameAjax = A.Query.get({action: 'checkUsername', query: query});
            $scope.checkUsernameAjax.$promise.then(function(){	
                $scope.validUsername = $scope.checkUsernameAjax['validUsername'];
                $scope.validEmail = $scope.checkUsernameAjax['validEmail'];

                var val = '';
                var ajax = 1;
                if ($scope.validUsername == 'No' && col == 'username') {	
                    val = username;	
                    awlert.neutral($scope.checkUsernameAjax['validUsernameMsg'],2000);
                    $('[data-update-profile="username"]').val($rootScope.me.username);
                    ajax = 0;
                }

                if ($scope.validEmail == 'No'  && col == 'email') {		
                    val = email;
                    awlert.neutral($scope.checkUsernameAjax['validEmailMsg'],2000);
                    $('[data-update-profile="email"]').val($rootScope.me.email);
                    ajax = 0;		
                }

                if(ajax == 1){
                    if(col == 'username'){
                        val = username;
                    } else {
                        val = email;
                    }
                    $scope.loading = true;
                    var message = user.id+','+val+','+col;
                    $scope.ajaxRequest14 = A.Query.get({action: 'updateUser', query: message});
                    $scope.ajaxRequest14.$promise.then(function(){											
                        $localstorage.setObject('user', $scope.ajaxRequest14.user);
                        $scope.loading = false;				
                    });	
                }

            },
            function(){}
            )		 
        }
        catch (err) {
            console.log("Error " + err);
        }		
    }

    $scope.validUsername = 'Yes';
    $scope.validEmail = 'Yes';

    $('[data-update-profile]').change(function(){
        var val = $(this).val();
        var col = $(this).attr('data-update-profile');

        if(col == 'username' || col == 'email'){
            if(col == 'username'){
                checkUsernameEmail(col,val,$rootScope.me.email);
            } else {
                checkUsernameEmail(col,$rootScope.me.username,val);
            }	
        } else {
            $scope.loading = true;
            var message = user.id+','+val+','+col;
            $scope.ajaxRequest14 = A.Query.get({action: 'updateUser', query: message});
            $scope.ajaxRequest14.$promise.then(function(){											
                $localstorage.setObject('user', $scope.ajaxRequest14.user);
                $scope.loading = false;				
            });
        }				
    });	 

    $('#userName').change(function(){
        var val = $(this).val();
        var col = 'name';
        $scope.loading = true;
        var message = user.id+','+val+','+col;
        $scope.ajaxRequest14 = A.Query.get({action: 'updateUser', query: message});
        $scope.ajaxRequest14.$promise.then(function(){											
            $localstorage.setObject('user', $scope.ajaxRequest14.user);
            $scope.loading = false;				
        });				
    });

    $('#userAge').change(function(){
        var val = $(this).val();
        var col = 'age';
        $scope.loading = true;
        var message = user.id+','+val+','+col;
        $scope.ajaxRequest14 = A.Query.get({action: 'updateUser', query: message});
        $scope.ajaxRequest14.$promise.then(function(){											
            $localstorage.setObject('user', $scope.ajaxRequest14.user);
            $scope.loading = false;				
        });				
    });	

    $('#userBio').change(function(){
        var val = $(this).val();
        var col = 'bio';
        var message = user.id+'[divider]'+val+'[divider]'+col;
        $scope.ajaxRequest14 = A.Query.get({action: 'updateUser', query: message, divider: '[divider]'});
        $scope.ajaxRequest14.$promise.then(function(){											
            $localstorage.setObject('user', $scope.ajaxRequest14.user);
        });				
    });

    var l = user.gender - 1;
    $scope.gender = config.genders[l].text;		

    $scope.updateUserGender = function() {
      var hideSheet = $ionicActionSheet.show({
        buttons: config.genders,
        cancelText: alang[2].text,
        cancel: function() {
          },
        buttonClicked: function(index,val) {
            var gender;
            $scope.gender = val.text;		
            gender = val.id;	
            var message = user.id+','+gender;
            $scope.ajaxRequest34 = A.Query.get({action: 'updateUserGender', query: message});
            $scope.ajaxRequest34.$promise.then(function(){											
                $localstorage.setObject('user', $scope.ajaxRequest34.user);
            });				
          return true;
        }
      });
    }		
}

$scope.closeEditProfileModal = function() {
$scope.editProfileModal.hide();
$state.go('home.profile');
};


$rootScope.deleteProfile = function(){
    var hideSheet = $ionicActionSheet.show({
    buttons: [
    { text: lang[150].text }
    ],
    cancelText: alang[2].text,
    cancel: function() {
    },
    buttonClicked: function(index) {	
    var message = user.id;
    oneSignalID = null;
    A.Query.get({action: 'delete_profile', query: message});
    $localstorage.setObject('user','');
    $localstorage.set('userHistory','');
    chats = [];
    matche = [];
    mylikes = [];
    myfans = [];
    cards = [];
    visitors = [];
    $ionicSideMenuDelegate.toggleLeft();
    $scope.closeEditProfileModal();
    $state.go('loader');	
    }
    });
}