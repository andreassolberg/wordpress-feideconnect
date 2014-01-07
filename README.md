Wordpress Plugin for Feide Connect authentication and authorization
======================

wordpress-feideconnect



## Description

...


## Installation

...

## Inspect database


	select * from wp_usermeta where meta_key = 'uwap_accesstoken';
	select * from wp_options where option_name like 'uwap-%';

## Reset

In order to reset all changes applied to the database:


	delete from wp_usermeta where meta_key = 'uwap_accesstoken';
	delete from wp_options where option_name like 'uwap-%';
	drop table wp_uwapstore_states;
	drop table wp_uwapstore_tokens;

	delete FROM wp_options WHERE option_name = 'active_plugins';

Disable plugin manually




## UWAP MongoDB Debug



	db['oauth2-server-clients'].remove({client_name: 'Andreas sin testblogg'});


## Changelog

* 0.1.0 Initial version

## Who made this?

Andreas Åkre Solberg, UNINETT AS.
