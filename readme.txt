=== Metaverse ID ===
Contributors: signpostmarv
Tags: mv-id, MV-ID, Metaverse, ID, hCard, vCard, hResume, hCalendar, vEvent, iCal, identity, profile, SL, Second Life, WoW, World of Warcraft, LotRO, Metaplace, EVE, EVE Online, Progress Quest
Requires at least: 2.8
Tested up to: 2.8
Stable tag: 0.10.0

Display your identity from around the metaverse!

== Description ==

"Metaverse ID" for WordPress is based on the work of the currently retired swslr project. The plugin aims to allow its users to place widgets into the sidebars of their WordPress blogs that let them show of their profiles around the Metaverse.

Supported Metaverses
--------------------
* EVE Online
* Free Realms
* Lord of the Rings Online
* Metaplace
* Progress Quest
* Second Life
 * Agni/Main Grid
 * Teen Second Life
* World of Warcraft
 * European servers
 * US Servers

Metaverse Configuration
--------------------
Some Metaverses (such as EVE) require some extra info in order for Metaverse ID to access the data. A menu for Metaverse ID will be added to the *Settings* menu if one of the supported Metaverses requires configuration.

If you try to update a Metaverse ID and you repeatedly get a message to the effect that the update failed, check to make sure that the Metaverse has been correctly configured!

== Installation ==

1. Upload the `metaverse-id` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. The `Metaverse ID` plugin page before any profiles have been added.
2. Adding multiple profiles in one go.
3. Profiles haven't been cached yet! Better force an update to get the profiles cached.
4. Freshly cached profiles. Ticking the box in the `Update` column can be used to force an update of the profile cache.
5. Individual widgets for each ID!

== Requirements ==

* PHP5 (I'm using features not present in PHP4, WordPress runs fine on PHP5, so upgrade already!)
* DOMDocument (required for Second Life, LOTRO)
* SimpleXML (required for WoW, Metaplace, EVE)
* JSON decode support (required for Free Realms)

== Changes ==

To-do
--------------------
* Give admin-level users ability to delete/update all IDs.
* Add option to auto-detect links in profile text and apply appropriate XFN values.
* Add generic hCard/hResume parser

0.10.0
--------------------
* Added select boxes to widget options, allowing one widget per ID.
* Fixed some minor bugs
* Converted Denis de Bernardy & Semilogic's "Autolink URI" plugin to a filter in order to implement a feature suggested by Will Norris
* Converted some logic to WP's Actions & Filters, added a filter for plugins to modify widget output (post_output_mv_id_vcard)
* Converted widgets to use WP 2.8's Widget facilities

0.9.5
--------------------
* Cleaned up class name left over from using hListing
* Changed semantics of character creation dates after discussion with Tantek Çelik
 * We agreed that using the bday property wasn't quite right, so I suggested using an hCalendar event block

0.9.4
--------------------
* Added support for EVE Online
* Added support for Metaverses that require API configuration in order to use.

0.9.2/3
--------------------
* Fixed a bug with PHP safe mode/open\_basedir interfering with CURLOPT\_FOLLOWLOCATION

0.9.1
--------------------
* Delete IDs when user is demoted to subscriber or deleted
 * partially implemented, demoting a user from the batch-edit screen doesn't delete the IDs.

0.9
--------------------
* Moved Metaverse ID page from *Settings* to *Users* section
* Users above subscriber-level get seperate IDs
* Widget output strips duplicate IDs

0.8
--------------------
* Added [Skills](http://microformats.org/wiki/hresume#Skills) & Stats support.
* Stats are currently only used to supply account creation dates via the [hCard bday property](http://microformats.org/wiki/hcard).

0.7
--------------------
* Switched from hListing with self-review to [hResume](http://microformats.org/wiki/hresume)
 * adding guilds/groups as "[affiliations](http://microformats.org/wiki/hresume#Affiliations)".

0.6
--------------------
* Optimised the UI by using javascript to dynamically add more fields instead of using a fixed list of fields (which would take up more and more space with every metaverse that was added).