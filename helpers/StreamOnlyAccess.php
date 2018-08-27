<?php
/**
 * Stream Only Omeka Player Plugin
 * .htaccess file routines
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */


/**
 * Adds or removes a RewriteCond in two places in the .htaccess file
 * The added statements allow the script that downloads
 *   protected audio files to execute.
 *
 * @param $action - 'add' or 'remove' statements
 * @return bool
 */
function so_update_access ($action) {

    $old_filename = BASE_DIR . DIRECTORY_SEPARATOR . HTACCESS;
    $old_handle = fopen($old_filename, "r");
    if (!$old_handle) return false;

    $tmp_filename = BASE_DIR . DIRECTORY_SEPARATOR . HTACCESS_TMP;
    $tmp_handle = fopen($tmp_filename, "w");
    if (!$tmp_handle) return false;

    switch ($action) {

        case ('add'):
            // Add the first new statement
            while (!feof($old_handle)) {
                $command = fgets($old_handle);
                if ($command == REWRITE_OMEKA_CONDITION) {
                    fwrite($tmp_handle, REWRITE_SO_CONDITION); // insert the new condition
                    fwrite($tmp_handle, $command);
                    break;
                }
                fwrite($tmp_handle, $command);
            }
            // Add the second new statement
            while (!feof($old_handle)) {
                $command = fgets($old_handle);
                if ($command == REWRITE_OMEKA_RULE) {
                    fwrite($tmp_handle, REWRITE_SO_CONDITION); // insert the new condition
                    fwrite($tmp_handle, $command);
                    break;
                }
                fwrite($tmp_handle, $command);
            }
            // Write out the rest of the file
            while (!feof($old_handle)) {
                fwrite($tmp_handle, fgets($old_handle));
            }
            break;

        case ('remove'):
            // Remove the two instances of the new statement
            while (!feof($old_handle)) {
                $command = fgets($old_handle);
                if ($command != REWRITE_SO_CONDITION) {
                    fwrite($tmp_handle, $command); // output all but the new condition
                }
            }
            break;
    }

    fclose($old_handle);
    fclose($tmp_handle);
    if (!$tmp = rename($tmp_filename, $old_filename)) {
        return false;
    }

    return true;

}


