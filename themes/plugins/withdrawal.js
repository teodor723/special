function showWithdrawModal(method,email){
    $('.selectedPayout').text(method);
    wmethod = method;
    $('#showWithdraw').show();
    $('#payoutEmail').hide();
    $('#payoutDetails').hide();
    if(email == 1){
        $('#payoutEmail').show();
        $("#withdrawBtn").attr("onclick","withdrawNow('email')");
    } else {
        $('#payoutDetails').show();
        $("#withdrawBtn").attr("onclick","withdrawNow('details')");
    }
}

function withdrawMoneyNow(details){
    var t = user_info.payout,
    e = user_info.credits;
    if(details == 'email'){
        var p = $('#payoutEmail').val();
    } else {
        var p = $('#payoutDetails').val();
    }
    
    if(p == ''){
        swal({   title: "Error",   text: site_lang[182]['text'], type: "warning" }, function(){ });
        return false;       
    }
    if (user_info.payout < plugins['withdrawal']['minRequired']) {
        swal({   title: "Error",   text: site_lang[585]['text'], type: "warning" }, function(){ });
        return false;       
    }  else {
     swal({
        title: site_lang[590]['text'],
        text: site_lang[591]['text'] + ' ' + t + " " + plugins['settings']['currency'],
        imageUrl: user_info.profile_photo,
        showCancelButton: !0,
        confirmButtonText: site_lang[569]['text'],
        closeOnConfirm: !1
    }, function() {
         $.ajax({
            type: "POST",
            url: request_source() + "/api.php",
            data: {
                action: "withdraw",
                wmethod: wmethod,
                wdetails: p,
                uid: user_info.id,
                credits: e,
                money: t
            },
            success: function(t) {
                window.location.reload();
            }
        })
    })
    }
}