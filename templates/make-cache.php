<h2>Updating cache...</h2>

<table>
	<?php
	foreach ($modules as $module) {
		?>
		<tr>
			<td><?= entities($module) ?></td>
			<td data-cachemodule="<?= $module ?>" data-executed="0"></td>
		</tr>
		<?php
	}
	?>
</table>
