<?php


	wp_enqueue_script('jquery');

?><div class="wrap">

	<h1>Autoconnect to UWAP</h1>

	<p>UWAP is a middleware collaboration platform that you can connect to in order to delgate authentication, 
		authorization, group support, and activity streams.</p>


	
	


	<p><?php echo $uwap['autoconnect'] ?></p>
	<iframe id="uwap_autoconnect_widget" src="<?php echo $uwap['autoconnect'] ?>" 
		style="border-radius: 10px; border: 1px solid #aaa; width: 100%; height: 680px"></iframe>




	<script type="text/javascript">
 		

 		var $ = jQuery;
		$(document).ready(function() {




			window.addEventListener("message", function(event) {

				if (event.data.msg === 'ready') {
					console.log("Received a message to container", event.data);
					// console.log("Received a message to container", event);

					var widget = document.getElementById("uwap_autoconnect_widget").contentWindow;
					// console.log($("#uwap_autoconnect_widget"));
					widget.postMessage(
						{
							"msg": "metdata", 
							"metadata": <?php echo json_encode($metadata); ?>
						}, "<?php echo $uwap['dev'] ?>");

				} else if (event.data.msg === 'appconfig') {

					console.log("APP CONFIG RECEIVED FROM Autconfigure...", event.data.data);

					$("#uwap_autoconnect_widget").hide();
					// $("#currentconfig").html(JSON.stringify(event.data.data, undefined, 4));



					var meta = {
						'authorization': '<?php echo $uwap["oauth"]["authorization"]; ?>',
						'token':  '<?php echo $uwap["oauth"]["token"]; ?>',
						'userinfo':  '<?php echo $uwap["oauth"]["userinfo"]; ?>',
						'client_id':  event.data.data['client_id'],
						'client_secret':  event.data.data['client_secret'],
						'redirect_uri':  event.data.data['redirect_uri'][0],
					};


					$("#updmetadata").attr('value', JSON.stringify(meta));

					$.ajax({
						type: "POST",
						processData: false,
						dataType: "json",
						mimeType: "text/json",
						url: "<?php echo $uwap['url_api']; ?>",
						data: JSON.stringify({"metadata": meta}),
						success: function(msg) {

							location.reload(true);	
						}
					});

			  
					

				}


			}, false);



		});

	</script>

</div>