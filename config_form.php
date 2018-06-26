<?php $view = get_view();
// TODO prefill certain fields with values in Options Table
?>
<div id="stream-only-plugin-warning">
    <p>This plugin only works with themes that use Omeka-provided methods to output the HTML to display the files.
    If the theme does not use these methods, the user will see errors when s/he clicks on links to audio files stored
    as StreamOnly Items.</p>
</div>

<div id="stream-only-plugin-settings">

    <h2><?php echo __('License Settings'); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_LICENSES, __('# Streams (default)')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formText(OPTION_LICENSES, $defaultLicenses); ?>
        </div>
        <div>Number of simultaneous listeners (not currently implemented).</div>
    </div>

    <h2><?php echo __('Folder for Storing Audio Files'); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_FOLDER, __('Folder Name')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formText(OPTION_FOLDER, $defaultFolder); ?>
        </div>
        <div><strong>***Folders should be chosen after consulting the server admin.***</strong></div>
    </div>

    <h2><?php echo __('Timeout'); ?></h2>
    <div class="field">
        <div class="two columns alpha">
            <?php echo $view->formLabel(OPTION_TIMEOUT, __('Timeout')); ?>
        </div>
        <div class="inputs five columns omega">
            <?php echo $view->formText(OPTION_TIMEOUT, $timeout); ?>
        </div>
    </div>
    <div>Number of seconds after which the temporary files permitting access to the audio files
         may be deleted. Setting a lower value may be helpful if your site gets a lot of activity,
         or if you don't want one user to prevent others from listening to the audio file(s)
         associated with the StreamOnly Item (latter not currently implemented).
    </div>

</div>
