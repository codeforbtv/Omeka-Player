<?php
/**
 * Stream Only Omeka Player Plugin
 * .htaccess file routines
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

function so_update_access ($action) {

    $pathname = _remove_nodes(dirname(__FILE__), 3);

    $old_filename = $pathname . "/" . HTACCESS;
    $old_handle = fopen($old_filename, "r");
    if (!$old_handle) return;

    $tmp_filename = $pathname . "/" . HTACCESS_TMP;
    $tmp_handle = fopen($tmp_filename, "w");

    switch ($action) {

        case ('add'):
            while (!feof($old_handle)) {
                $command = fgets($old_handle);
                fwrite($tmp_handle, $command);
                if ($command == REWRITE_CONDITION) {
                    fwrite($tmp_handle, REWRITE_RULE); // insert the new rule
                }
            }
            break;

        case ('remove'):
            while (!feof($old_handle)) {
                $command = fgets($old_handle);
                if ($command != REWRITE_RULE) {
                    fwrite($tmp_handle, $command); // output all but the new rule
                }
            }
            break;
    }

    fclose($old_handle);
    fclose($tmp_handle);
    rename($tmp_filename, $old_filename);

}
