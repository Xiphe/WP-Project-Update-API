Project Update Api for Wordpress
================================

Handles Wordpress plugin and theme update requests by comparing the 
request data with files hosted on **[github](https://github.com/)**, 
**[bitbucket](https://bitbucket.org/)** or your own **[gitlab](http://gitlabhq.com/)** server.  
See [Project Wiki](https://github.com/Xiphe/WP-Project-Update-API/wiki)
for detailed information and documentation.

This is targeted to Wordpress plugin and theme developers who do not
want to use the official Wordpress Plugin Directory for whatever
reasons.  
For example some might want to use GIT instead of SVN or want to
keep the project private and only distrubute updates it to specific
ip adresses or to requests containing a valid api key.

It it possible to specify the branch on which the updates will be
distributed so you can have a development branch and a live branch.


Version
-------

1.0.0

_The projects class files are versioned itself and have their independant version._


Support
-------

I've written this project for my own needs so i am not willing to give
full support. Anyway, im verry interested in any bugs, hints, requests
or whatever. Please use the [github issue system](https://github.com/Xiphe/WP-Project-Update-API/issues)
and i'll try to answer.


Dependencys
-----------

**Required:**
* PHP 5.3
* Support for PHPs [cURL functions](http://www.php.net/manual/ref.curl.php)

**Recommended:**
* PEAR module [File_Archive](http://pear.php.net/package/File_Archive/redirected)

File_Archive is used to rename the project Folders for github and bitbucket
and convert the archives from gitlab from tar.gz to zip.
If you choose not to use it you have to set the global settings "useCache"
and "renameFolders" to false.


Installation
------------

1. Make shure your webserver supports all the dependencys 
2. Download or Clone the Project Files into a folder on your webserver.
3. Rename the globalSettings.json-sample to globalSettings.json
4. Make your settings in globalSettings.json.
   See [Project Wiki - Global Settings](https://github.com/Xiphe/WP-Project-Update-API/wiki/Global-Settings)
   for details.
5. Setup your first project
	1. Choose the appropriate file from /projectSettingsSamples folder.
	2. Copy it into /projectSettings folder.
	3. Rename it in [your Project Slug].json.
	4. Insert the project data.
       See [Project Wiki - Project Settings](https://github.com/Xiphe/WP-Project-Update-API/wiki/Project-Settings)
       for details.
6. Check if everything works by testing one of the api requests.
   See [Project Wiki - API Requests](https://github.com/Xiphe/WP-Project-Update-API/wiki/API-Requests)
   for documentation.


Preparing your Plugin/Theme
---------------------------

It is required that you change your plugin code so the update requests
will not be send to wordpress.org but to your server.

I am currently developing a wordpress subframework for handling this.
Keep watching [THEUPDATES standalone](https://github.com/Xiphe/-THE-UPDATES-standalone)
for more to come.

I have not tested it but it should work as well with the plugin and 
theme files of the [Automatic Theme & Plugin Updater](https://github.com/jeremyclark13/automatic-theme-plugin-update) from jeremyclark13.
Which, btw can also be a grat alternative to this project if you 
want to keep your plugin code completly on your server.


Props
-----

**Inspired by:**
* **[automatic-theme-plugin-update](https://github.com/jeremyclark13/automatic-theme-plugin-update)**  
  by Kaspars Dambis (kaspars@konstruktors.com)  
  Modified by [Jeremy Clark](http://clark-technet.com)  
  [Donate](https://www.paypal.com/cgi-bin/webscr?cmd=_s-xclick&hosted_button_id=73N8G8UPGDG2Q)
* **[BitBucket API Library](https://bitbucket.org/steinerd/bitbucket-api-library/overview)**  
  by [Anthony Steiner](http://steinerd.com)

Inspired means that i've seen these **brilliant projects**, started to use them
and than started to write my own version with the intention to just have the
functionality i realy use.  
I got the idear of how things are working by analyzing and using the code of
them and i can't say where i used snippets from these projects but they've
influenced my code strongly.

**Uses:**
* **[Markdown Extra](http://michelf.ca/projects/php-markdown/extra/)**  
  A text-to-HTML conversion tool for web writers  
  PHP Markdown & Extra  
  Copyright (c) 2004-2009 [Michel Fortin](http://michelf.com/projects/php-markdown/)  
  Original Markdown  
  Copyright (c) 2004-2006 [John Gruber](http://daringfireball.net/projects/markdown/)
* **Random String generation** from [noobis.de](http://www.noobis.de/developer/141-php-random-string-erzeugen.html)
* **Recursive dir deletion** from [php.net comments](http://www.php.net/manual/de/function.rmdir.php#108113)
* **json_readable_encode** from [php.net comments](http://www.php.net/manual/de/function.json-encode.php#102091)  
  by bohwaz licensed under GNU/AGPLv3 or GPLv3
* PEAR module **[File_Archive](http://pear.php.net/package/File_Archive/redirected)**

Changelog
---------

This is the first release.


Todo
----
 * Test with larger repositorys
 * Get gitlab https to work
 * Check protected Github + public Bitbucket
 * Add forceCacheLive option for relying on local cache for a specific timeframe
 * Add cronjob requests for checking cache and gitlab login.
 * Write tests
 * Spice up the theme detail page.
 * Check documentation for logical and spelling mistakes
 * Backend ;)

Im not an native english speaker so please pardon any spelling or grammar mistakes.
I'd realy love to get some feedback for eny errors in the documentation.


License
-------

Copyright (C) 2012 Hannes Diercks

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License along
with this program; if not, write to the Free Software Foundation, Inc.,
51 Franklin Street, Fifth Floor, Boston, MA 02110-1301 USA.