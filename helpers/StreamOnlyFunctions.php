<?php
/**
 * Stream Only
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

/**
 * Removes some number of directories from the end of a file path.
 * Helpful for moving up the tree structure of the file system.
 *
 * @param $path string, filepath
 * @param $count - int, # directories to remove from the end of $path
 * @return null|string|string[]
 */
function _remove_nodes($path, $count) {

    while ($count > 0) {
        $path = preg_replace("(/[\w]+$)", "", $path, 1);
        $count--;
    }

    return $path;
}

/**
 * Builds the HTML string needed to customize the audio element
 *
 * @param $props - Properties to customize display of audio files
 *    possible keys in array: 'height', 'width', 'controller', 'loop', 'autoplay'
 */
function _build_audio_props($props) {

    $audioProps = "";

    $height = 0;
    if (isset($props['height'])) $height = $props['height'];
    $audioProps .= ($height == 0) ? "" : " height=$height";

    $width = 0;
    if (isset($props['width'])) $width = $props['width'];
    $audioProps .= ($width == 0) ? "" : " width=$width";

    if (isset($props['controller']) && ($props['controller'] == true)) {
        $audioProps .= " controls";
    }

    if (isset($props['autoplay']) && ($props['autoplay'] == true)) {
        $audioProps .= " autoplay";
    }

    if (isset($props['loop']) && ($props['loop'] == true)) {
        $audioProps .= " loop";
    }

    return ($audioProps);
}

/****** THEME **********************************************************/

/**
 * This function is a callback provided to the Omeka Global Theming Function
 *   add_file_display_callback. It returns the appropriate HTML for the
 *   theme to output, so that when the user clicks on the audio controls,
 *   the protected audio file will be played, but cannot be downloaded.
 *
 * @param $record - the record from the File Table
 * @param $props - Properties to customize display of audio files
 *    possible keys in array: 'height', 'width', 'controller', 'loop', 'autoplay'
 *
 * @return string - the HTML for the audio file
 */
function soDisplayFile($record, $props)
{

    $item = $record->getItem();
    $alt_msg = __("Your browser does not support the audio tag");

    // TODO See if there's a way to use the Omeka callback
    if (!isSOItem($item)) {
        $url = WEB_FILES . "/original/" . $record['filename'];
        $audioProps = _build_audio_props($props);
        $html  = "\n<audio $audioProps>\n";
        $html .= "<source src='$url'>\n";  // TODO should there be a mimetype attribute?
        $html .= "$alt_msg\n";
        $html .= "</audio>\n";
        return ($html);
    }

    $id = $record['id'];
    $soRecord = get_record('StreamOnlyModel', array("file_id" =>$id));
    $soDirectory = $soRecord['so_directory'];
    $timeout = $soRecord['so_timeout'];
    $licenses = $soRecord['so_licenses'];
    $m3uDir = BASE_DIR . '/files/m3u/';
    $mp3Dir = "/$soDirectory/";
    $now = time();

    # Scan the playlist directory for old files left around
    #   when people navigated away from pages or the site
    # If we don't delete them, the playlist directory could become cluttered.
    $dir = opendir($m3uDir);
    if (!$dir) die(__("Can't find playlist directory")); // TODO Report Omeka Error

    while ($file = readdir($dir)) {

        # Ignore everything that isn't an .m3u file
        if (!preg_match("/.m3u$/i", $file)) continue;

        # If the file is too old, delete it
        $content = file($m3uDir . $file);
        if ($content[M3U_EXPIRES] <= $now) {
            unlink($m3uDir . $file);
            continue;
        }
    }

    $reservedFiles = [];
    $reservedExpires = [];

    // Scan the reserved audio files, to see if this one is reserved
    // Delete expired entries in the file
    $filelist = file($m3uDir . "reserved.txt");
    $newFilelist = "";
    foreach ($filelist as $file) {
        $fileinfo = explode(",", $file);
        $expires = $fileinfo[M3U_EXPIRES];

        // If this entry has expired
        //   don't add it to the list of reserved files
        if ($expires <= $now) continue;

        # Record that someone has this file reserved
        $mp3ID = (int)$fileinfo[M3U_FILEID];
        $newFilelist .= $file;

        // Determine how many other users have this file reserved
        $reservedFiles[$mp3ID] =
            (isset($reservedFiles[$mp3ID])) ? $reservedFiles[$mp3ID]++ : 1;

        // Determine the soonest a reservation will expire
        $reservedExpires[$mp3ID] =
            (isset($reservedExpires[$mp3ID])) ? min($reservedExpires[$mp3ID], $expires) : $expires;
    }

    // Check to see if others have the file reserved
    // If enough of them do, ask the user to wait his/her turn
    if (isset($reservedFiles[$id]) && $reservedFiles[$id] >= $licenses) {
        $expires = $reservedExpires[$id];
        $seconds = ($expires - $now);
        $msg     = __("Recording reserved, available in <span class='so-seconds'>%s</span> seconds", $seconds);
        $html    = "<span class='so-msg' data-sofile='$id' data-soexpires='$expires'>$msg</span>";
    } else {

        # Generate a unique ID for this song download.
        $m3uFile = rand(0, 1 << 30) . ".m3u";
        $handle = fopen($m3uDir . $m3uFile, "w");
        $filename = $mp3Dir . $record['filename'];
        $expires = $now + (int)$timeout;
        $m3uContent =   $filename . "\n" .
                        $id .       "\n" .
                        $expires .  "\n" .
                        $licenses . "\n";
        fwrite($handle, $m3uContent);
        fclose($handle);

        // Add to the list of reserved files
        $newFilelist .= $filename . "," .
                        $id . "," .
                        $expires . "," .
                        $licenses . "\n";

        // Build the HTML for this file
        $expires    -= 2;
        $audioProps  = " class='so-audio' data-sofile='$id' data-soexpires='$expires'";
        $audioProps .= ' controlsList="nodownload"' . _build_audio_props($props);
        $url = WEB_PLUGIN . "/StreamOnly/scripts/play.php/$m3uFile/";

        $html  = "\n" . "<audio $audioProps>" . "\n";
        $html .= "<source src='$url'>\n";  // TODO should there be a mimetype attribute?
        $html .= "<span class='so-noaudio'>$alt_msg</span>\n";
        $html .= "</audio>";
    }

    // Write out the list of reserved files
    $newReservedFile = "reserved." . rand(0, 1 << 30);
    $handle = fopen($m3uDir . $newReservedFile, "w");
    fwrite ($handle, $newFilelist);
    fclose ($handle);
    do {
        $renamed = rename($m3uDir . $newReservedFile, $m3uDir . "reserved.txt");
    } while (!$renamed);

    return ($html);
}