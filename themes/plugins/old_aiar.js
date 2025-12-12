var aiarUrl = request_source()+'/aiautoresponder.php';
var ai_r_i = plugins['aiautoresponder']['responder_interval'].split("-");
var autores_i1 = ai_r_i[0]*1000;
var autores_i2 = ai_r_i[1]*1000;

function sendAutoResp(s,r){
	var rnd = Math.floor(Math.random() * (autores_i2 - autores_i1) + autores_i1);
	setTimeout(function(){
		$.getJSON( aiarUrl, {action: 'respond',uid1: s, uid2: r} ,function( data ) {
			if(data.send == 'YES'){
				var message = s+'[message]'+r+'[message]'+data.msg+'[message]text';
				var send = s+'[rt]'+r+'[rt]'+data.photo+'[rt]'+data.name+'[rt]'+data.msg+'[rt]text';
				$.get( gUrl, {action: 'message', query: send} );		
				$.get( aUrl, {action: 'sendMessage', query: message} );
			}
		});
	},rnd);
}
