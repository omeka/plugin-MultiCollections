<div class="field">
    <div class="two columns alpha">
        <?php echo $view->formLabel('multicollections_override', __('Override regular "Collections" tab?')); ?>
    </div>
    <div class="inputs five columns omega">
        <div class="input-block">
            <p class="explanation"></p>
            <?php echo $view->formCheckbox(
                'multicollections_override', true,
                array('checked' => (boolean) get_option('multicollections_override'))); ?>
        </div>
    </div>
</div>
