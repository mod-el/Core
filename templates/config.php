<style>
	td {
		padding: 5px;
	}

	input[type="text"] {
		width: 100%;
		-webkit-box-sizing: border-box;
		-moz-box-sizing: border-box;
		box-sizing: border-box;
	}
</style>

<form action="?" method="post">
	<table style="width: 100%">
		<tr>
			<td style="width: 34%">
				Repository<br/> <input type="text" name="repository" value="<?= entities($config['repository']) ?>"/>
			</td>
			<td style="width: 33%">
				License Key<br/> <input type="text" name="license" value="<?= entities($config['license']) ?>"/>
			</td>
			<td style="width: 33%">
				404 controller<br/> <input type="text" name="404-controller" value="<?= entities($config['404-controller'] ?? 'Err404') ?>"/>
			</td>
		</tr>
	</table>

	<p>
		<input type="submit" value="Save"/>
	</p>
</form>