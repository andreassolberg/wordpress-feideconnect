<?php


	wp_enqueue_script('jquery');

?><div class="wrap">


	<style type="text/css">

		table.roles td {
			padding: 4px 30px 4px 4px;
		}
		table.roles tr:nth-child(even) {
		  background-color: #fff;
		}


		table.roles tr:nth-child(odd) {
		  background-color: #eee;
		}

	</style>

	<h1>UNINETT WebApp Park</h1>

	<!-- <p>Your Wordpress installation is already configured to work with UWAP.</p> -->

	<div style="background: #ffe; border-radius: 10px; border: 1px solid #aaa; padding: 1em; margin-bottom: 2em">
		<h2>Successfully connected to UNINETT WebApp Park</h2>
		<p><b>Client identifier:</b> <tt><?php echo htmlspecialchars($storedMetadata['client_id']); ?></tt><br />
		<b>Client secret:</b> <tt><?php echo htmlspecialchars($storedMetadata['client_secret']); ?></tt></p>

		<p><a href="https://dev.uwap.org">Edit the configuration of this service at UWAP Developer Console</a></p>

		<form>
			<button id="uwapreset">Reset and disconnect UWAP</button>
		</form>
	</div>



	<!-- <pre style="border: 1px solid #ccc; background: #eee; padding: 1em"><?php print_r( $storedMetadata); ?></pre> -->

	<div id="rolemngmnt">
		<h2>Role Management</h2>
		<p>Wordpress operates with a set of roles that map to a of capabilities, 
			such as publish a post or post a comment. The UWAP connector will automatically assign new users to these Wordpress roles.
			You can here control which UWAP groups corresponds to roles in Wordpress.
		</p>

		<h3>Mapping from UWAP groups to roles</h3>
		<div id="roleruleset"></div>
		<div id="addnewrule"></div>
		<!-- <button id="addnew">Add new rule</button> -->

		<h3>Default role</h3>
		<div id="default">Default role to new users <span id="defaultopt"></span></div>

		<h3>Administrator</h3>

		<div>
			<p>You may specify an userID that will automatically map to the main administrator of this site.</p>
			<p><input type="checkbox" id="adminenable" checked="checked"/> <label for="adminenable">Administrator User ID</label> <input id="adminuserid" type="text" value="" /></p>
			
		</div>


		<div id="ctrl">
		<form>

			<button id="save">Save changes</button>
		</form>
		</div>

	</div>



	<script type="text/javascript">


 		var apiURL = '<?php echo $uwap["url_api"]; ?>';
 		var loginURL = '<?php echo $uwap["url_login"]; ?>';

		/*
			'rules' => array(
				// IoU WebTeknologi
				array('groups' => array('a48a713d-d1bb-4d63-84fd-2825ce518776'), 'role' => 'administrator'),

				// IOU Ledergruppe
				array('groups' => array('d6ac7d23-fb8e-4e00-92ec-11e58ee80d97'), 'role' => 'editor'),

				array('groups' => array('uwap:realm:uninett_no'), 'role' => 'contributor'),
			),
			'default' => 'subscriber',
			'admin' => array(
				'userid' => 'andreas@uninett.no',
				'role' => 'administrator',
			),
		 */


		var RoleMngmntController = function(el) {
			var that = this;
			this.el = el;

			this.el.on('click', '#save', function(e) {
				e.preventDefault(); e.stopPropagation();
				console.log("SAVE()");

				that.pushUpdate();
			});
			this.el.on('click', '#addrule', function(e) {
				e.preventDefault(); e.stopPropagation();

				var role = that.el.find("#addnewrule select.role").val();
				var group = that.el.find("#addnewrule select.group").val();

				if (role === null) {alert('You must select a role'); return;}
				if (group=== null) {alert('You must select a group'); return;}

				console.log("DATA", that.data);

				that.data.roleruleset.rules.push({'groups': [group], 'role': role});


				console.log("addrule()", role, group, that.data.roleruleset);

				that.pushUpdate();

			});
			this.el.on('click', '.removerule', function(e) {
				e.preventDefault(); e.stopPropagation();
				console.log("removerule()");

				var rno = $(e.currentTarget).closest('tr').data('rulesetno');
				console.log("Row ", $(e.currentTarget), rno);

				if (that.data.roleruleset.rules[rno]) {
					that.data.roleruleset.rules.splice(rno, 1);
				}

				that.pushUpdate();
			});
			this.el.on('change', '#defaultopt select', function(e) {
				e.preventDefault(); e.stopPropagation();
				console.log("change default()");



				that.pushUpdate();
			});

			this.data = null;
			this.load();
		}


		RoleMngmntController.prototype.pushUpdate = function() {

			var that = this;
			var adminenable = that.el.find("#adminenable").prop('checked');
			var adminuserid = that.el.find("#adminuserid").val();

			console.log('update()');
			console.log('admin enable, userid [', adminenable, ']', adminuserid);

			if (!adminenable) {
				if (that.data.roleruleset.admin) delete that.data.roleruleset.admin;
			} else {
				that.data.roleruleset.admin = {
					'userid': adminuserid,
					'role': 'administrator'
				};
			}

			var defaultrole = that.el.find('#defaultopt select').val();
			that.data.roleruleset.default = defaultrole;

			console.log("UPDATE() ", that.data.roleruleset);

			$.ajax({
				type: "POST",
				processData: false,
				dataType: "json",
				mimeType: "text/json",
				url: apiURL,
				data: JSON.stringify({"action": "update-rolemap", "rolemap": that.data.roleruleset}),
				success: function(msg) {
					that.draw();
				}
			});



		}

		RoleMngmntController.prototype.draw = function() {


			console.log("API Data received", this.data);

			// var ruleset = {
			// 	'rules': [],
			// 	'default': 'subscriber',
			// 	'admin': {
			// 		"userid": "andreas@uninett.no",
			// 		'role': 'administrator'
			// 	}
			// };

			var ruleset = null;
			if (this.data.roleruleset) {
				ruleset = this.data.roleruleset;
			}

			if (this.data.roleruleset.admin && this.data.roleruleset.admin.userid) {
				this.el.find('#adminenable').prop('checked', true);
			
				this.el.find('#adminuserid').val(this.data.roleruleset.admin.userid);
				console.log("setting admin", this.data.roleruleset.admin)
			} else {
				this.el.find('#adminenable').prop('checked', false);
			}


			console.log("Roles", this.data);
			$("#roleruleset").empty();
			$("#addnewrule").empty();

			var html, groupname, rolename;
			html = '<table class="roles" style=""><thead><tr style="text-align: left"><th>UWAP Role</th><th>Role</th><th>Action</th></tr></thead>';
			for(var i = 0; i < ruleset.rules.length; i++) {
				groupname = ruleset.rules[i].groups[0];
				rolename  = ruleset.rules[i].role;
				if (this.data.groups[ruleset.rules[i].groups[0]]) {
					groupname = this.data.groups[ruleset.rules[i].groups[0]];
				}
				if (this.data.roles[ruleset.rules[i].role]) {
					rolename = this.data.roles[ruleset.rules[i].role];
				}
				

				html += '<tr data-rulesetno="' + i + '">';
				html += '<td><span style="">' + groupname + '</span></td>';
				// html += ' will be assigned the role ';
				html += '<td><span style="">' + rolename + '</span></td>';
				html += ' <td><button class="removerule">Remove</button></td>';
				html += '</tr>';
			}
			html += '</table>';
			$("#roleruleset").append(html);

			if (ruleset.rules.length === 0) {
				$("#roleruleset").append('<p>No group to role mappings are yet added.</div></p>');
			}

			html = '<div>Member of ';
			html += getGroups(this.data.groups, 'a');
			html += ' will be assigned the role ';
			html += getOpt(this.data.roles, 'a');
			html += ' <button id="addrule">Add new rule</button>';
			html += '</div>';
			$("#addnewrule").append(html);


			$("#defaultopt").empty().append(getOpt(this.data.roles));

			if (this.data.roleruleset.default) {
				this.el.find('#defaultopt select').val(this.data.roleruleset.default);
				console.log("set default value: ", this.data.roleruleset.default)
			}

		}

		RoleMngmntController.prototype.load = function() {
			var that = this;
			$.getJSON(apiURL + '?action=getroles', function(data) {

				that.data = data;

				// User is not authenticated. Redirect user to login page to enforce login.
				if (!data.uwaptoken) {
					console.log('User not authorized to UWAP. Redirecting to ensure login.', loginURL);
					window.location = loginURL;
					return;
				}

				that.draw();

			});
		}


		var getOpt = function(roles) {
			var html = '<select class="role" style="display: inline" name="role"><option value="_" disabled="disabled" selected="selected">Select a role</option>';
			for(var key in roles) {
				html += '<option value="' + key + '">' + roles[key] + '</option>';
			}
			html += '</select>';
			return html;
		}

		var getGroups = function(groups) {
			var html = '<select class="group" style="display: inline" name="role"><option value="_" disabled="disabled" selected="selected">Select a group</option>';
			for(var key in groups) {
				html += '<option value="' + key + '">' + groups[key] + '</option>';
			}
			html += '</select>';
			return html;
		}


		var postMeta = function(e) {
			e.preventDefault(); e.stopPropagation();

			$.ajax({
				type: "POST",
				processData: false,
				dataType: "json",
				mimeType: "text/json",
				url: apiURL,
				data: JSON.stringify({"action": "reset"}),
				success: function(msg) {
					location.reload(true);	
				}
			});
		}
		// console.log("JA");

 		var $ = jQuery;
		$(document).ready(function() {


			var rm = new RoleMngmntController($("#rolemngmnt"));

			console.log("Ready");

			$("#uwapreset").click(postMeta);

		});

	</script>















</div>