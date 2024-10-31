=== NTRSCTN Content Aggregator ===
Author: complex_ntrsctn
Contributors: joshs633
Original Author: dphiffer
Tags: ntrsctn, api, aggregation
Requires at least: 2.9
Tested up to: 3.3.1
Stable tag: 1.1

== Install Instructions ==
* Either enter your own aggregation notification server or a server provided by ntrsctn
* If you are implemeting your own aggregation server, see server documentation at http://treemolabs.com/aggregator/
* After instaling the plugin, go to Settings > NTRSCTN Content Aggregator Settings and set the Notification API to your aggregation notification server

== Original Documentation ==
http://wordpress.org/extend/plugins/json-api/other_notes/

== Features ==
* A `secret` is required for all communication
* Registers with a central notification api on activation. You can modify or remove the notification api in the plugin settings. When you modify the api url in the plugin settings, the plugin automatically registers with the new server.
* Request is sent to the notification api when a post is published, published post is modified or a post is unpublished
* Bulk requests to fetch posts between two dates
* Bulk requests to fetch posts modified between two dates
* Bulk requests to fetch posts deleted between two dates
