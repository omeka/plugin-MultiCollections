
<div class="field">
    <div class="two columns alpha">
        <label>Override regular "Collections" tab?</label>    
    </div>    
    <div class="inputs five columns omega">
        <p class="explanation"></p>
        <div class="input-block">        
		<?php
		    $checked = ( get_option('multicollections_override') == 'on') ? true : false;
		?>
		<input name="multicollections_override" type="checkbox" <?php if($checked) {echo "checked='checked'"; } ?>  />        
        </div>
    </div>
</div>