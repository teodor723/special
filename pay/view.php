<?php 
header('Access-Control-Allow-Origin: *');
require_once('../assets/includes/core.php');
if($logged === false){
    header('Location:'.$sm['config']['site_url']);
    exit;    
}

$win = '';
$value = '';
$action = '';

if(isset($_GET['credits'])){
    $value = secureEncode($_GET['credits']);
    if($value > 200){
        header('Location:'.$sm['config']['site_url']);
        exit;          
    }
    $win = $value.' CREDITS';
    $action = 'credits';
}

if(isset($_GET['premium'])){
    $value = secureEncode($_GET['premium']);
    if($value > 3){
        header('Location:'.$sm['config']['site_url']);
        exit;          
    }
    $hs = $value*24;
    $win = $hs.'hs PREMIUM';
    $action = 'premium';
}

if($value == ''){
    header('Location:'.$sm['config']['site_url']);
    exit;      
}

$title = $sm['lang'][994]['text'].' '.$win;



$intervalSeconds = 2; //change here the interval seconds
?>

<!DOCTYPE html>
<html>
<head>
    <title><?= $sm['config']['name']; ?> | <?= $sm['lang'][993]['text']; ?></title>
    <meta name='viewport' content='width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=0' />
    <link rel="icon" type="image/png" href="<?= $sm['theme']['favicon']['val']; ?>" sizes="32x32">    
    <script src="assets/js/jquery-3.3.1.min.js"></script>
    <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css">
    <script src="https://maxcdn.bootstrapcdn.com/bootstrap/4.3.1/js/bootstrap.min.js"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/4.7.0/css/font-awesome.min.css">
    <link rel="stylesheet" href="assets/css/custom.css" type="text/css">
    <link href="https://fonts.googleapis.com/css?family=Rubik" rel="stylesheet" type="text/css"/>
    <link rel="stylesheet" href="../themes/default/css/crossplatform.css"/>
    <style type="text/css">
        :root {
          touch-action: pan-x pan-y;
          height: 100% 
        }        
        body{
            font-family: 'Rubik' !important;
        }
        .method {
          letter-spacing: 0.75px;
          color: #c1c8d0;
          padding: 0 165px;
          position: relative;   
          list-style: none;
          color: #333 !important;
          cursor: pointer;
          margin-bottom: 15px;
          border-radius: 5px;
          border:1px solid #fafafa;
        }
        .method .cards-icons{
          position: absolute;
          width: auto;
          height: 25px;
          right: calc(10px / 2);
          top: 30%;

        }        
        .method a{
            color: #333;
            font-weight: bold;
        }
        .method:last-of-type {
          border-bottom: 1px solid #d6e5ed;
        }
        .method .gateway-icon {
          position: absolute;
          width: auto;
          height: 25px;
          left: calc(115px / 2);
          top: 50%;
          transform: translate(-40%, -50%);
        }
        .method.active {
          border:2px solid #28a745;
        }
        .method.active a{
          color: #333;
        }        
        .method > a {
          line-height: 70px;
        }
        .list-group-item{
            border:none !important;
            margin-bottom: 15px;
        }
        .method{
            padding: 0 30px;
        }        
        .method .gateway-icon {
            display: none;
        }          
        @media only screen and (max-width: 680px) {
            .method{
                padding: 0 20px;
            }
            .method .gateway-icon {
                display: none;
            }    
            .method .cards-icons{
                height: 20px;
                top: 25px;
            }                                
        }   
        .user-icon {
            height: 70px;
            width: 70px;
            display: inline-block;
            background-size:   cover;
            background-repeat: no-repeat;
            background-position: top center;    
            border-radius: 50%;
            vertical-align:middle;
            margin-left: 5px;
        }    
        .card-header{
            background-image: url('bg-a-page.jpg');
            background-size: cover;
        }   

        #applixir_vanishing_div{
            top: 0!important;
            left: 0!important;
            width: 100vw!important;
            height: 100vh!important;
        }     
        #applixir_parent{
            top: 0!important;
            left: 0!important;
            width: 100vw!important;
            height: 100vh!important;
        }              
    </style>
    <script type='text/javascript' src="https://cdn.applixir.com/applixir.sdk3.0m.js"></script>
</head>
<body style="background: #f9f9f9">
    <div id="applixir_vanishing_div" style="z-index:999" hidden>                                    
        <iframe id="applixir_parent" allow="autoplay;fullscreen" style=""></iframe>
    </div>
    <div class="d-flex justify-content-center">
        <div class="lw-page-loader lw-show-till-loading">
            <div class="spinner-border"  role="status"></div>
        </div>
    </div>

    <div class="pt-4 mb-5 container" style="max-width: 520px" id="lwCheckoutForm">
        <div class="row">
            <div class="col-md-12">
                <div style="position: relative;height: 90px;width: 100%">
                    <center><img src="<?= $sm['theme']['logo']['val']; ?>"></center>

                </div>               
                <form method="post" id="lwPaymentForm" class="box-shadow-credits">
                    <div class="card">
                        <div class="card-header box-shadow">
                            <center><div class="user-icon box-shadow" style="border:3px solid #fff;margin-bottom:15px;background-image: url(<?= $sm['user']['profile_photo']; ?>)">
                            </div></center>                           
                            <h3 class="text-center" style="color: #fff"><?= $title; ?></h3>
                        </div>
                        <div class="card-body">
                            <div class="alert alert-info" style="display: none"></div>
                                <a href="javascript:;" class="btn btn-lg btn-block btn-danger box-shadow" id="countdown" style="margin-top: 25px;margin-bottom: 30px;">
                                    <?=$intervalSeconds; ?> <?= $sm['lang'][318]['text']; ?>
                                </a>                                
                            </div>                          
                        </div>

                    </div>
                </form>

            </div>
        </div>
    </div>





<script type="application/javascript" 
data-idzone="4961170" data-ad_first_trigger_clicks="1" data-ad_next_trigger_clicks="1" data-type="mobile" 
data-browser_settings="1" 
data-ad_trigger_method="2" data-ad_trigger_class="banner-big" 

src="https://a.realsrv.com/fp-interstitial.js"></script>
<!--
<script type="application/javascript" src="https://syndication.realsrv.com/splash.php?idzone=4961172&capping=0"></script>
-->


<script>

var interval;
var intervalIV;

var timeLeft = <?=$intervalSeconds; ?>;
var bigBanner = false;
var elemToShowTimeLeft = document.getElementById('countdown');


var options = {  
    zoneId: 5655,
    userId: '52d807ad-7d93-4476-b729-2420e632834b', // required: UUID value for current customer (required for targeted ads - UUID4 recommended)
    fallback: 0,
    gameId: 7828,
    devId: 7282,
    adStatusCb: adStatusCallback // required: callback function to provide information regarding ad status
};

function adStatusCallback(status) {
    // This can contain whatever code you like. The err parameter will return the 
    // following values (see online documentation for other values):
    //  'ad-blocker' = an ad blocker was detected
    //  'network-error' = network connection not available
    //  'cors-error' = cross-origin error (try clearing browser cache)
    //  'no-zoneId' = the required zoneId value is missing
    //  'ad-watched' = an ad was presented and ran for more than 5 seconds
    //  'ad-interrupted' = ad was ended prior to 5 seconds (abnormal end)
    //  'ads-unavailable' = no ads were returned to the player

    if (status){
        console.log('Applixir status: ' + status);
    }

    if(status == 'ad-blocker'){
        alert('<?= $sm['lang'][995]['text']; ?>');
    }

    if(status == 'ad-interrupted'){
        alert('<?= $sm['lang'][996]['text']; ?>');
        $('#applixir_vanishing_div').hide();
        $('#countdown').removeClass("btn-danger");
        $('#countdown').addClass("btn-warning");
        $('#countdown').attr('onClick','openBannerBig()');
        elemToShowTimeLeft.innerHTML = '<?= $sm['lang'][464]['text']; ?> <?= $win; ?> <?= $sm['lang'][998]['text']; ?>';        
    }    

    if(status == 'ads-unavailable'){
        alert('<?= $sm['lang'][997]['text']; ?>');
        $('#applixir_vanishing_div').hide(); 
        $('#countdown').attr('onClick','openBannerBig()');       
    }

    if(status == 'ad-watched'){
        $('#countdown').removeClass("btn-warning");
        $('#countdown').addClass("btn-success");
        $('#countdown').attr('onClick','getReward()');
        elemToShowTimeLeft.innerHTML = '<?= $sm['lang'][464]['text']; ?> <?= $win; ?> <?= $sm['lang'][998]['text']; ?>'; 
        $('#applixir_vanishing_div').hide();
    }

}


function countdown() {
    
    if (timeLeft == -1) {
        clearTimeout(interval);
        if(bigBanner){
            $('#countdown').removeClass("btn-warning");
            $('#countdown').addClass("btn-success");
            $('#countdown').attr('onClick','getReward()');
            elemToShowTimeLeft.innerHTML = '<?= $sm['lang'][464]['text']; ?> <?= $win; ?> <?= $sm['lang'][998]['text']; ?>';
        } else {
            $('#countdown').removeClass("btn-danger");
            $('#countdown').addClass("btn-warning");
            $('#countdown').attr('onClick','openBannerBig()');
            elemToShowTimeLeft.innerHTML = '<?= $sm['lang'][999]['text']; ?>';
        }    
    } else {
        elemToShowTimeLeft.innerHTML = timeLeft + ' <?= $sm['lang'][318]['text']; ?>';
        timeLeft--;
    }
}

function openBannerBig(){
    $('#applixir_vanishing_div').show();
    invokeApplixirVideoUnit(options);
    bigBanner = false;
    timeLeft = <?=$intervalSeconds; ?>;
    clearTimeout(interval);
    $('#countdown').attr('onClick','');
    interval = setInterval(countdown, 1000);  
}

function getReward(){
    alert('<?= $sm['lang'][1014]['text']; ?> <?= $win; ?>');
    $('#countdown').attr('onClick','');
    $.ajax({ 
      data: {
          action: 'addReward',
          user : <?= $sm['user']['id']; ?>,
          type : '<?= $action; ?>',
          amount : <?= $value; ?>
      },
      url:   '../requests/api.php',
      type:  'post',
      dataType: 'JSON',
      beforeSend: function(){ 
      },
      success: function(response){
        var w = window,
            d = document,
            e = d.documentElement,
            g = d.getElementsByTagName('body')[0],
            x = w.innerWidth || e.clientWidth || g.clientWidth;
            if(x <= 768){
                window.location.href = '<?= $sm['config']['site_url']; ?>mobile';
            } else {
                window.location.href = '<?= $sm['config']['site_url'].$action; ?>';
            }
      }
    });  
}

$(window).ready(function() {
   interval = setInterval(countdown, 1000); 
});

document.addEventListener("visibilitychange", () => {
   var s = document.visibilityState;
   if(s == 'hidden'){
    clearTimeout(interval);
   } else {
     interval = setInterval(countdown, 1000);   
   }   
});



</script>    
      
</body>

</html>


