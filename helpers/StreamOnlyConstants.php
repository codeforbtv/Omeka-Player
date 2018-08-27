<?php
/**
 * Stream Only Omeka Player Plugin
 * Constants
 *
 * @copyright Copyright 2018 Code for Burlington
 * @license http://www.gnu.org/licenses/gpl-3.0.txt GNU GPLv3
 */

//  Item Type
const SO_ITEM_TYPE_NAME = 'StreamOnly';
const SO_ITEM_TYPE_DESCRIPTION = 'Audio files stored in this Item Type cannot be downloaded';
const SO_PLUGIN_NAME = 'StreamOnly';
const SO_PLUGIN_MODEL = 'StreamOnlyModel';

// Displayed at the top of config_form.php
const SO_THEME_WARNING =
    "This plugin only works with themes that use Omeka-provided methods " .
    "to output the HTML to display the files. " .
    "If the theme does not use these methods, the user will see errors " .
    "when s/he clicks on links to audio files stored as " .
    "Items of Item Type StreamOnly.";

// Links to the User Documentation: Configuration page
// Displayed at the top of config_form.php
// Appended to each description of an Item Element unique to StreamOnly Item Type
const SO_SEE_USER_DOCUMENTATION =
"Please see " .
"<a href='https://github.com/codeforbtv/StreamOnly/wiki/User-documentation:-Configuration' target='_blank'>" .
"User Documentation: Configuration" .
"</a>";
const SO_SEE_CONFIGURATION_PAGE =
"Please see the Plugin Configuration page for more information.";

// Elements made available for Items of ItemType StreamOnly
// Not critical to the plugin
const ELEMENT_TRANSCRIPTION = 'Transcription';
const ELEMENT_TRANSCRIPTION_DESCRIPTION = 'Any written text transcribed from a sound';
const ELEMENT_TRANSCRIPTION_FIELD = 'transcription';
const ELEMENT_ORIGINAL_FORMAT = 'Original Format';
const ELEMENT_ORIGINAL_FORMAT_DESCRIPTION  = 'The type of object, such as painting, sculpture, paper, photo, and additional data';
const ELEMENT_ORIGINAL_FORMAT_FIELD = 'original_format';
const ELEMENT_DURATION = 'Duration';
const ELEMENT_DURATION_DESCRIPTION  = 'Length of time involved (seconds, minutes, hours, days, class periods, etc.';
const ELEMENT_DURATION_FIELD = 'duration';
const ELEMENT_BIT_RATE = 'Bit Rate/Frequency';
const ELEMENT_BIT_RATE_DESCRIPTION  = 'Rate at which bits are transferred (i.e. 96 kbit/s would be FM quality audio';
const ELEMENT_BIT_RATE_FIELD = 'bit_rate/frequency';

// Elements made available for Items of ItemType StreamOnly
// CRITICAL to the plugin
const ELEMENT_LICENSE_COUNT_TITLE = 'License Settings';
const ELEMENT_LICENSE_COUNT = '# Licenses (default)';
const ELEMENT_LICENSE_COUNT_DESCRIPTION =
    "Number of simultaneous listeners permitted by licensing. " .
    'The default number is set during configuration of the plugin, ' .
    "but can be overridden on an Item-by-Item basis. ";
const ELEMENT_LICENSE_COUNT_FIELD = 'stream-only-license-count';

const ELEMENT_FOLDER_TITLE = 'Folder for Storing Audio Files';
const ELEMENT_FOLDER = 'Folder Name';
const ELEMENT_FOLDER_DESCRIPTION =
    'The folder (relative to the root folder of the file system) '.
    'where protected audio files will be stored. ' .
    'The default folder is set during configuration of the plugin, ' .
    "but can be overridden on an Item-by-Item basis. ";
const ELEMENT_FOLDER_DESCRIPTION_DEFAULT =
    'The folder (relative to the account root) '.
    'where protected audio files will be stored. ' .
    'This folder will be used when no folder is specified ' .
    "for the individual Item.";
const ELEMENT_FOLDER_FIELD = 'stream-only-directory';

const ELEMENT_TIMEOUT_TITLE = 'Timeout';
const ELEMENT_TIMEOUT = "Timeout";
const ELEMENT_TIMEOUT_DESCRIPTION =
    "Number of seconds the protected file is made available to a site visitor. " .
    "Setting a lower value may be helpful if your site gets a lot of activity. ";
const ELEMENT_TIMEOUT_FIELD = 'timeout';

// The custom message is not stored as an element
// It is stored in a file in the files/m3u folder
const ELEMENT_CUSTOM_MSG_TITLE = 'Custom Message';
const ELEMENT_CUSTOM_MSG = "Custom message";
const ELEMENT_CUSTOM_MSG_DESCRIPTION =
    "Custom text to be downloaded when user tries to download a protected file. ";
const ELEMENT_CUSTOM_MSG_FIELD = 'custom-msg';

// Names of options
const OPTION_LICENSES = 'so_default_license_count';
const OPTION_FOLDER = 'so_default_folder';
const OPTION_TIMEOUT = 'so_timeout';
const OPTION_CUSTOM_MSG = 'so_custom_msg';

// Defaults for options
const DEFAULT_LICENSES = 1;
const DEFAULT_FOLDER = 'so_files';
CONST DEFAULT_TIMEOUT = 300;

// *** Must match definitions found in scripts/play.php ***
// Info stored in .m3u file
const M3U_FILENAME = 0;
const M3U_FILEID   = 1;
const M3U_EXPIRES  = 2;
const M3U_LICENSES = 3;

// Directory for .m3u files
const SO_PLAYLIST = 'm3u';

// File extension for .m3u files
const SO_PLAYLIST_EXT = '.m3u';

// File for storing list of reserved files
const SO_RESERVED_FILES = 'reserved.txt';

// File for storing the custom msg
const SO_CUSTOM_MSG_FILE = 'custom_msg.txt';

const SO_CUSTOM_MSG_TEXT =
'The license under which this file is made available for streaming ' .
'does not permit it to be downloaded.';

// .htaccess rule
const HTACCESS                = '.htaccess';
const HTACCESS_TMP            = '.htaccess-tmp';
const REWRITE_OMEKA_CONDITION = "RewriteCond %{REQUEST_FILENAME} -f\n";
const REWRITE_OMEKA_RULE      = "RewriteRule .* index.php\n";
const REWRITE_SO_CONDITION    = "RewriteCond %{REQUEST_URI} !plugins/StreamOnly/scripts/play.php\n";


// .htaccess file for files/m3u/ directory
const SO_DENY_ACCESS =
"Options -Indexes

AddType audio/mpeg mp3

# New-fangled directions
<IfModule mod_authz_core.c>
    Require all denied
</IfModule>

# Old-timey directives
<IfModule !mod_authz_core.c>
    Order Deny,Allow
    Deny from all
</IfModule>";

const SO_XSENDFILE_ON = "XSendFile on\n";