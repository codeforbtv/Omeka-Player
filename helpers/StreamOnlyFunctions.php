<?php
/**
 * Stream Only
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

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
    $m3uDir = FILES_DIR . '/m3u/';
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

        // If this entry has expired
        //   don't add it to the list of reserved files
        if ($fileinfo[M3U_EXPIRES] <= $now) continue;

        # Record that someone has this file reserved
        $mp3ID = (int)$fileinfo[M3U_FILEID];
        $newFilelist .= $file;

        // Determine how many other users have this file reserved
        $reservedFiles[$mp3ID] =
            (isset($reservedFiles[$mp3ID])) ? $reservedFiles[$mp3ID] + 1 : 1;

        // Determine the soonest a reservation will expire
        $reservedExpires[$mp3ID] =
            (isset($reservedExpires[$mp3ID])) ? min($reservedExpires[$mp3ID], $fileinfo[M3U_EXPIRES]) : $fileinfo[M3U_EXPIRES];
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

        $fileinfo[M3U_FILENAME] = $mp3Dir . $record['filename'];
        $fileinfo[M3U_FILEID] = $id;
        $fileinfo[M3U_EXPIRES] = $now + (int)$timeout;
        $fileinfo[M3U_LICENSES] = $licenses;
        fwrite($handle, implode("\n", $fileinfo) . "\n");
        fclose($handle);

        // Add to the list of reserved files
        $newFilelist .= implode(",", $fileinfo) . "\n";

        // Build the HTML for this file
        $expires    = $now + (int)$timeout - 2;
        $audioProps  = " class='so-audio' data-sofile='$id' data-soexpires='$expires'";
        $audioProps .= ' controlsList="nodownload"' . _build_audio_props($props);
        $url = WEB_PLUGIN . "/" . SO_PLUGIN_NAME . "/scripts/play.php/$m3uFile/";

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

/**
 * @return mixed
 *   array of the 3 IDs of the Item Type Elements that must not be deleted
 */
function soGetItemTypeElements() {

    $itemTypeID = get_record('ItemType', array("name"=>SO_ITEM_TYPE_NAME))->id;

    $db = get_db();
    $iteTable = $db->getTable('ItemTypesElements');

    // get_record() not implemented for ItemTypesElements
    $folderElem   =
        get_record('Element', array("name"=>ELEMENT_FOLDER));
    $iteIDs[0]    =
        $iteTable->findBy(array("item_type_id"=>$itemTypeID, "element_id"=>$folderElem->id))[0]->id;
    $licensesElem =
        get_record('Element', array("name"=>ELEMENT_LICENSE_COUNT));
    $iteIDs[1]    =
        $iteTable->findBy(array("item_type_id"=>$itemTypeID, "element_id"=>$licensesElem->id))[0]->id;
    $timeoutElem  =
        get_record('Element', array("name"=>ELEMENT_TIMEOUT));
    $iteIDs[2]   =
        $iteTable->findBy(array("item_type_id"=>$itemTypeID, "element_id"=>$timeoutElem->id))[0]->id;

    return $iteIDs;
}

/**
 * Called from hookAdminFooter()
 * On the Show Item Type or Edit Item Type menus,
 *   outputs a small JS script that removes
 *   the buttons/icons that would allow the site
 *   administrator to delete database elements
 *   necessary for the StreamOnly plugin to work.
 *
 * @return bool
 *   true if this was the Show Item Type or Edit Item Type menu; actions were taken
 *   false if this was not either of the above; no actions were taken
 */
function soItemTypeNoDelete() {

    // Menu for Item Types?
    if (preg_match (SO_ITEM_TYPE_URL_PATTERN, $_SERVER['REQUEST_URI'], $matches ) != 1) return false;

    // Item Type StreamOnly?
    $itemTypeID = $matches[2];
    if (get_record_by_id('Item Type', $itemTypeID)->name != SO_ITEM_TYPE_NAME) return false;

    // Get the protected Item Type Elements
    if ($matches[1] == "edit") {
        $iteIDs = soGetItemTypeElements();
    }

    echo "\n<!-- Prevent user from deleting necessary db entries -->\n";
    echo "<script type='text/javascript'>\n";
    echo "jQuery(document).ready(function() {\n";

    echo "  jQuery('.delete-confirm')[0].remove();\n";

    if ($matches[1] == "edit") {
        echo "  jQuery('#remove-element-link-$iteIDs[0]').hide();\n";
        echo "  jQuery('#remove-element-link-$iteIDs[1]').hide();\n";
        echo "  jQuery('#remove-element-link-$iteIDs[2]').hide();\n";
    }

    echo "});";
    echo "</script>\n\n";

    return true;

}

/**
 * Called from hookAdminFooter()
 * On the Settings: Item Type Elements menu,
 *   outputs a small JS script that removes
 *   the buttons/icons that would allow the site
 *   administrator to delete database elements
 *   necessary for the StreamOnly plugin to work.
 *
 * @return bool
 *   true if this was the Item Type Elements menu; actions were taken
 *   false if this was not either of the above; no actions were taken
 */
function soSettingsITE() {

    // Menu for Settings: Item Type Elements?
    if (preg_match (SO_SETTINGS_URL_PATTERN, $_SERVER['REQUEST_URI'], $matches ) != 1) return false;

    $iteIDs = soGetItemTypeElements();

    echo "\n<!-- Prevent user from deleting necessary db entries -->";
    echo "\n<script type='text/javascript'>\n";
    echo "jQuery(document).ready(function() {\n";


    echo "  jQuery('#elements-$iteIDs[0]-delete').parent().find('.delete-element').hide();\n";
    echo "  jQuery('#elements-$iteIDs[1]-delete').parent().find('.delete-element').hide();\n";
    echo "  jQuery('#elements-$iteIDs[2]-delete').parent().find('.delete-element').hide();\n";


    echo "});";
    echo "</script>\n\n";

    return true;
}