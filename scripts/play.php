<?php
/**
 * Created by PhpStorm.
 * User: chamilton
 * Date: 5/24/18
 * Time: 4:45 PM
 */

error_reporting(E_ALL);

$files = array(
    "mervent.mp3",
    "halleluja.mp3",
    "sligomaidslament.mp3");

// TODO figure out how to build path; DOCUMENT_ROOT only takes us to public_html
//$filename = $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR . "sofiles" . DIRECTORY_SEPARATOR .$files[(int)$_GET["id"]];
$filename = $_SERVER["DOCUMENT_ROOT"] . DIRECTORY_SEPARATOR . "code4btv/Omeka-dev/sofiles" . DIRECTORY_SEPARATOR .$files[(int)$_GET["id"]];

// TODO need a URL in the header
if (!file_exists($filename)) {
    header('Location: index.php');
    exit;
}

header('Content-Disposition: inline');
header('Content-Type: audio/mp3');
header('Content-Length: ' . filesize($filename));
header('X-Sendfile: ' . $filename);

exit;

?>