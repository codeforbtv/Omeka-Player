<?php $view = get_view();
// Prefill fields with values stored previously
// TODO Error handling
$defaultLicenses = get_option(OPTION_LICENSES);
$defaultFolder   = get_option(OPTION_FOLDER);
$timeout         = get_option(OPTION_TIMEOUT);
$customMessage   = file_get_contents(FILES_DIR . DIRECTORY_SEPARATOR
                                    . SO_PLAYLIST. DIRECTORY_SEPARATOR
                                    . SO_CUSTOM_MSG_FILE);
?>
<div id="stream-only-plugin-theme-warning">
    <p><?php echo __(SO_THEME_WARNING);?></p>
</div>

<div id="stream-only-plugin-settings">

    <h2><?php echo __(ELEMENT_LICENSE_COUNT_TITLE); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_LICENSES, __(ELEMENT_LICENSE_COUNT)); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formText(OPTION_LICENSES, $defaultLicenses); ?>
        </div>
        <div><?php echo __(ELEMENT_LICENSE_COUNT_DESCRIPTION);?></div>
    </div>

    <h2><?php echo __(ELEMENT_FOLDER_TITLE ); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_FOLDER, __(ELEMENT_FOLDER)); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formText(OPTION_FOLDER, $defaultFolder); ?>
        </div>
        <div><?php echo __(ELEMENT_FOLDER_DESCRIPTION_DEFAULT);?></div>
    </div>

    <h2><?php echo __(ELEMENT_TIMEOUT_TITLE); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_TIMEOUT, __(ELEMENT_TIMEOUT)); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formText(OPTION_TIMEOUT, $timeout); ?>
        </div>
        <div><?php echo __(ELEMENT_TIMEOUT_DESCRIPTION);?></div>
    </div>

    <h2><?php echo __(ELEMENT_CUSTOM_MSG_TITLE); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_CUSTOM_MSG, __(ELEMENT_CUSTOM_MSG)); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formTextarea(OPTION_CUSTOM_MSG, $customMessage); ?>
        </div>
        <div><?php echo __(ELEMENT_CUSTOM_MSG_DESCRIPTION);?></div>
    </div>

</div>
