=== Metaverse ID ===
Contributors: signpostmarv
Tags: mv-id, metaverse, id, hcard, vcard, hresume, identity, profile, sl, second life, wow, world of warcraft, lotro, metaplace
Requires at least: 2.3
Tested up to: 2.7.1
Stable tag: 0.9.3

Display your identity from around the metaverse!

== Description ==

"Metaverse ID" for WordPress is based on the work of the currently retired swslr project. The plugin aims to allow its users to place widgets into the sidebars of their WordPress blogs that let them show of their profiles around the Metaverse.

Supported Metaverses
--------------------
* Free Realms
* Lord of the Rings Online
* Metaplace
* Second Life
 * Agni/Main Grid
 * Teen Second Life
* World of Warcraft
 * European servers
 * US Servers

== Installation ==

1. Upload the `metaverse-id` directory to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. The `Metaverse ID` plugin page before any profiles have been added.
2. Adding multiple profiles in one go.
3. Profiles haven't been cached yet! Better force an update to get the profiles cached.
4. Freshly cached profiles. Ticking the box in the `Update` column can be used to force an update of the profile cache.
5. Individual widgets for each Metaverse!

== Requirements ==

* PHP5 (I'm using features not present in PHP4, WordPress runs fine on PHP5, so upgrade already!)
* DOMDocument (required for Second Life, LOTRO)
* SimpleXML (required for WoW, Metaplace)
* JSON decode support (required for Free Realms)

== Changes ==

To-do
--------------------
* Give admin-level users ability to delete/update all IDs
* Add multi-select box to widget options to select which IDs get displayed in the widget.

0.9.2/3
--------------------
* Fixed a bug with PHP safe mode/open_basedir interfering with CURLOPT_FOLLOWLOCATION

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