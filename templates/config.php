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
    <table style="width: 50%">
        <tr>
            <td style="width: 50%">
                Repository<br/>
                <input type="text" name="repository" value="<?= entities($this->options['config']['repository']) ?>"/>
            </td>
            <td style="width: 50%">
                License Key<br/>
                <input type="text" name="license" value="<?= entities($this->options['config']['license']) ?>"/>
            </td>
        </tr>
    </table>

    <p>
        <input type="submit" value="Save"/>
    </p>
</form>