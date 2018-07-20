# Setting up a test environment
## for the StreamOnly script
## to play protected audio files

This describes an environment where the script can be tested in isolation
from Omeka.

The goal at the moment is to determine how to modify the .htaccess file
of an Omeka-based website so that:
1. Omeka operates properly
2. The script that delivers the contents of protected audio files to the
browser operates properly

As with most CMS's, the .htaccess file provided when Omeka is installed
is used to ensure that the CMS's php files are only accessed via approved
entry points. For Omeka, those are:
* install/install.php
* admin/index.php
* index.php

An exception is needed for the script in the StreamOnly plugin which
supplies the contents of the audio file to the browser. It must execute
without going through these entry points. Relative to the root directory
of the Omeka website, the script is found here:
* plugins/StreamOnly/scripts/play.php

The ID of the playlist containing the path to the audio file and filename,
found in files/m3u/ directory, is appended to the script path and filename:
* plugins/StreamOnly/scripts/play.php/000000000.m3u/

(NOTE: The forward slash at the end of the string is very important,
for reasons having nothing to do with the .htaccess file.)

In order to isolate the effects of changes to the script and to Omeka's
.htaccess file, a test environment has been created containing the bare
minimum needed to execute the script. All of these files are found in the
GitHub repository, in the branch "front-end", in the directory test/:
* README.MD - this file, documentation of test environment
* index.php - contains HTML that is typical of that produced by Omeka
and the StreamOnly plugin for protected audio files. With the
appropriate .htaccess file commands, it will cause the script to be
executed. As a debugging aid, it will display any message contained
in a query "?msg=message". This query can be provided either when
entering a URL in the browser, or in a RewriteRule in the root
.htaccess file.
* 111111111.m3u - a typical playlist produced by the plugin.
* 222222222.m3u - a second typical playlist, useful for testing purposes
* .htaccess - the latest iteration of the .htaccess file under development
for the root directory - probably doesn't work
* .htaccess-scripts - the latest iteration of the .htaccess file
for the plugins/StreamOnly/scripts/ directory - might not be needed
* .htaccess-m3u - the latest iteration of the .htaccess file
for the files/m3u/ directory - might not be needed
* play.php - a copy of the script; should match the copy found in
 the folder plugins/StreamOnly/scripts/.
* mervent.mp3 - an audio file in the public domain
* sligomaidslament.mp3 - another audio file in the public domain


The test environment consists of the needed files residing in
directories in the same relative positions they would have
were they in an Omeka website.

* (root folder of test environment, call it whatever you want)
  * files
    * m3u (playlists, will be deleted when script executes)
      * .htaccess-m3u (renamed to .htaccess)
    * m3u-sav (convenient place to store copies of playlists)
      * 111111111.m3u (edit to point to mervent.mp3)
      * 222222222.m3u (edit to point to sligomaidslament.mp3)
  * plugins
    * StreamOnly
        * scripts
           * .htaccess-scripts (renamed to .htaccess)
           * play.php (the script)        
  * .htaccess
  * index.php
* (a folder that is outside of the public_html directory)
  * mervent.mp3
  * sligomaidslament.mp3

After putting the files in place, here are the steps needed to execute
the script once:

1. Clear the server's cache (I use OPCacheGui), to remove any cached
versions of .htaccess files
2. Clear the browser's cache (browser-dependent, there are extensions
to make this easy) to remove any cached versions of .php or audio files.
3. Copy 111111111.mp3 from files/m3u-sav/ to files/m3u/
4. Copy 222222222.mp3 from files/m3u-sav/ to files/m3u/, if needed
5. Enter the URL for index.php in your browser

NOTE #1: If you delete/rename the .htaccess file in the root directory,
the script should execute properly, i.e. the Mervent audio file should play
when you click on the controls.

NOTE #2: If you want to check whether a particular path is being taken
through the .htaccess file:
* for index.php, you can insert a RewriteRule to append a message to the URI,
and use the [L] or [END] flag to stop applying rules
* for play.php, you can insert a RewriteRule to change "111111111"
to "222222222" in the URI