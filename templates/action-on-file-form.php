<fieldset>
	<legend>Perform "<?= entities($actionName) ?>" on "<?= entities($fileName) ?>"</legend>

	<form action="?" method="post" onsubmit='performActionOnFile(<?= json_encode($this->model->getRequest(2)) ?>, <?= json_encode($type) ?>, <?= json_encode($fileName) ?>, <?= json_encode($actionName) ?>, this, <?= json_encode(array_keys($action['params'])) ?>); return false'>
		<table>
			<?php
			foreach ($action['params'] as $paramName => $paramOptions) {
				?>
				<tr>
					<td style="padding-right: 10px"><?= entities($paramOptions['label']) ?></td>
					<td><input type="text" name="<?= $paramName ?>"/></td>
					<td><i><?= entities($paramOptions['notes'] ?? '') ?></i></td>
				</tr>
				<?php
			}
			?>
			<tr>
				<td colspan="2" style="text-align: center"><input type="submit" value="Perform"/></td>
			</tr>
		</table>
	</form>
</fieldset>