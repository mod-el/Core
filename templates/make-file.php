<?php
$fileType = $this->model->getRequest(4);
$maker = new \Model\Core\Maker($this->model);
?>
<fieldset>
	<legend>Make new "<?= entities($fileType) ?>" file</legend>
	<?php
	try {
		$params = $maker->getParamsList($fileType);
		?>
		<form action="?" method="post">
			<input type="hidden" name="makeNewFile" value="<?= entities($fileType) ?>"/>
			<table>
				<?php
				foreach ($params as $p => $pOptions) {
					if ($p === 'namespace')
						continue;
					?>
					<tr>
						<td style="padding-right: 10px"><?= entities($pOptions['label']) ?></td>
						<td><input type="text" name="<?= $p ?>"/></td>
						<td><i><?= entities($pOptions['notes'] ?? '') ?></i></td>
					</tr>
					<?php
				}
				?>
				<tr>
					<td colspan="2" style="text-align: center"><input type="submit" value="Save"/></td>
				</tr>
			</table>
		</form>
		<?php
	} catch (Exception $e) {
		echo getErr($e);
	}
	?>
</fieldset>