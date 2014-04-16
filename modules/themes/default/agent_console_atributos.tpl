<table border="0">
    <theader>
	    <tr>
	        <th colspan="2">{$LBL_INFORMACION_LLAMADA|escape:"html"}</th>
	    </tr>
    </theader>
    <tbody>
    {foreach from=$ATRIBUTOS_LLAMADA item=ATRIBUTO }
	    <tr>
	       <td><label>{$ATRIBUTO.label|escape:"html"}: </label></td>
           <td>{$ATRIBUTO.value}</td>
	    </tr>
	{foreachelse}
	   <tr><td colspan="2">{$MSG_NO_ATTRIBUTES}</td></tr>
    {/foreach}
    </tbody>
</table>