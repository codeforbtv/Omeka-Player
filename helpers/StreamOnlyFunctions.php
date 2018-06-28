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