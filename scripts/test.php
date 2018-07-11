<?php
/**
 * To test plugins/StreamOnly/scripts/play.php:
 * 1. rename .htaccess (to disable it)
 * 2. create a valid .m3u file in the m3u directory
 * 3. update the src property in the audio tag with the new .m3u file name
 * 4. update the data-sofile property with the id of the audio file in the Files Table
 * 4. clear the browser cache
 * 5. enter the URL of this file (test.php) in the browser
 *
 * 6. Don't forget to restore .htaccess when you're done!
**/
?>

<h1 style = "text-align: center">Test StreamOnly Script</h1>
<div class="element-text"><div class="item-file audio-mpeg"><audio  class='so-audio' data-sofile='1' data-soexpires='1531540.791' height=20 width=200 controls src='play.php/774859073.m3u'>Your browser does not support the audio element</audio></div></div>
