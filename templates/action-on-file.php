<fieldset>
	<legend>Results of "<?= entities($actionName) ?>" on "<?= entities($fileName) ?>"</legend>

	<?php
	zkdump($results);
	?>
</fieldset>