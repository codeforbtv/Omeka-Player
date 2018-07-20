<?php
/**
 * To create test environment:
 * 1. set up .htaccess files (root, files/m3u/)
 * 2. create a new file 123456789.m3u so it has a recent creation date/time
 * 3. copy the contents of 9999999999.m3u into 123456789.m2u
 * 4. copy 123456789.m3u file into the files/m3u/ directory
 * 5. clear the browser cache
 * 6. set needed breakpoints
 * 7. enter the URL of this file with an empty message
 *  path-to-file/index.php?msg=
**/
?>
<html>
<body>
<?php
echo $_GET['msg'] . "\n";
?>

<h1 style = "text-align: center">Test StreamOnly Script</h1>
<div class="element-text">
    <div class="item-file audio-mpeg">
        <audio  class='so-audio' data-sofile='1' data-soexpires='1531540.791' height=20 width=200 controls controlsList="nodownload">
            <source src="plugins/StreamOnly/scripts/play.php/111111111.m3u/">
            Your browser does not support the audio element
        </audio>
    </div>
</div>

</body>
</html>