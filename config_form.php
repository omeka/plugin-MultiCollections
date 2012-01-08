<div class="field">
	<div class="inputs">
		<p>Override regular "Collections" tab?</p>
		<label for="multicollections_override">Yes</label>
		<?php
		    $checked = ( get_option('multicollections_override') == 'on') ? true : false;
		?>
		<input name="multicollections_override" type="checkbox" <?php if($checked) {echo "checked='checked'"; } ?>  />
	</div>
</div>