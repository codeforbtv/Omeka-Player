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
function soDisplayFile($record, $props) {

    $db = get_db();

    $item = $record->getItem();
    if (!isSOItem($item)) {
        $msg = __("Your browser does not support the audio tag");
        $filename = $record['filename'];
        $audioProps = _build_audio_props($props);
        $html = "<audio $audioProps src='files/original/$filename'>$msg</audio>";
        return ($html); // TODO use $props to modify $html???
    }

    $soFileID        = $record['id'];
    $mp3Dir          = get_record('StreamOnlyModel', array("file_id" => $soFileID))['so_directory'];
    $m3uDir          = _remove_nodes(dirname(__FILE__), 3) . '/files/m3u/';

    $timeout         = get_option('so_timeout');  // TODO use info in StreamOnly Table
    $licenses        = get_option('so_licenses'); // TODO use info in StreamOnly Table

    $reservedFiles   = [];
    $reservedExpires = [];

    # Scan the playlist directory for old files and delete them
    # If we don't do this, the playlist directory will become cluttered.
    $dir = opendir($m3uDir);
    if (!$dir) {die("Can't find playlist directory");} // TODO Report Omeka Error
    while ($file = readdir($dir)) {

        # Ignore everything that isn't an .m3u file
        if (!preg_match("/.m3u$/i", $file)) continue;

        # If the file is too old, delete it
        $fileinfo = stat($m3uDir . $file);
        $now      = time();
        $expires  = $fileinfo['mtime'] + ($timeout * 1000);
        if ($expires <= $now) {
            unlink($m3uDir . $file);
            continue;
        }

        # Record that someone has this file reserved
        # The key of the record in the Files Table is stored on the second line of the playlist
        $content = file($m3uDir . $file);
        $mp3ID   = (int) $content[1];

        // Determine how many other users have this file reserved
        $reservedFiles[$mp3ID] =
            (isset($reservedFiles[$mp3ID])) ? $reservedFiles[$mp3ID]++ : 1;

        // Determine the soonest a reservation will expire
        $reservedExpires[$mp3ID] =
            (isset($reservedExpires[$mp3ID])) ? min($reservedExpires[$mp3ID], $expires) : $expires;

    }

    $id  = $record['id'];
    $now = time();

    // Check to see if others have the file reserved
    // If enough of them do, ask the user to wait his/her turn
    if (isset($reservedFiles[$id]) && $reservedFiles[$id] >= $licenses) {
        $expires = $reservedExpires[$id];
        $seconds = ($expires - $now) / 1000;
        $msg     = __("Recording reserved, available in <span class='so-seconds'>%s</span> seconds", $seconds);
        $html    = "<span class='so-msg' data-sofile='$id' data-soexpires='$expires'>$msg</span>";
        return ($html);
    }

    # Needed for PHP versions OLDER than 4.2.0 only.
    # If your host still has PHP older than 4.2.0, shame on them.
    # Find a better web host.
    // list($usec, $sec) = explode(' ', microtime());
    // $seed = (float) $sec + ((float) $usec * 100000);
    // srand($seed);

    # Generate a unique ID for this song download.
    $m3uFile = rand(0, 1 << 30);
    $handle = fopen($m3uDir . $m3uFile . ".m3u", "w");
    fwrite($handle, DIRECTORY_SEPARATOR . $mp3Dir .DIRECTORY_SEPARATOR. $record['filename'] . "\n");
    fwrite($handle, $id . "\n");
    fclose($handle);

    // Build the HTML for this file
    $msg = __("Your browser does not support the audio element");
    $expires = (($now + ($timeout * 1000))/1000) - 2;
    $audioProps = " class='so-audio' data-sofile='$id' data-soexpires='$expires'";
    $audioProps .= _build_audio_props($props);
    $html = "<audio $audioProps src='plugins/StreamOnly/scripts/play.php/$m3uFile.m3u'>$msg</audio>";
    return ($html);
}