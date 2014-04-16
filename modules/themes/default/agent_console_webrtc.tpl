{* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.8                                                  |
  | http://www.elastix.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-433, 2284-336                                  |
  | Guayaquil - Ecuador                                                  |
  | http://www.palosanto.com                                             |
  +----------------------------------------------------------------------+
  | The contents of this file are subject to the General Public License  |
  | (GPL) Version 2 (the "License"); you may not use this file except in |
  | compliance with the License. You may obtain a copy of the License at |
  | http://www.opensource.org/licenses/gpl-license.php                   |
  |                                                                      |
  | Software distributed under the License is distributed on an "AS IS"  |
  | basis, WITHOUT WARRANTY OF ANY KIND, either express or implied. See  |
  | the License for the specific language governing rights and           |
  | limitations under the License.                                       |
  +----------------------------------------------------------------------+
  | The Original Code is: Elastix Open Source.                           |
  | The Initial Developer of the Original Code is PaloSanto Solutions    |
  +----------------------------------------------------------------------+
  $Id: default.conf.php,v 1.1.1.1 2007/03/23 00:13:58 elandivar Exp $
*}

{* --mein Incluir SipML5 Y JS *}
<link href="modules/agent_console_webrtc/themes/default/css/bootstrap.css" rel="stylesheet" />
<link href="modules/agent_console_webrtc/themes/default/css/textarea.css" rel="stylesheet" />
<script src="modules/agent_console_webrtc/themes/default/js/SIPml-api.js?svn=220"></script>
<script src="modules/agent_console_webrtc/themes/default/js/ml5.js"></script>
{* --mein Fin SipML5 *}

{* Incluir todas las bibliotecas y CSS necesarios *}
{foreach from=$LISTA_JQUERY_CSS item=CURR_ITEM}
    {if $CURR_ITEM[0] == 'css'}
<link rel="stylesheet" href='{$CURR_ITEM[1]}' />


    {/if}
    {if $CURR_ITEM[0] == 'js'}
<script type="text/javascript" src='{$CURR_ITEM[1]}'></script>


    {/if}
{/foreach}

{* Este DIV se usa para mostrar los mensajes de éxito *}
<div
    id="elastix-callcenter-info-message"
    class="ui-state-highlight ui-corner-all">
    <p>
        <span class="ui-icon ui-icon-info" style="float: left; margin-right: .3em;"></span>
        <span id="elastix-callcenter-info-message-text"></span>
    </p>
</div>
{* Este DIV se usa para mostrar los mensajes de error *}
<div
    id="elastix-callcenter-error-message"
    class="ui-state-error ui-corner-all">
    <p>
        <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
        <span id="elastix-callcenter-error-message-text"></span>
    </p>
</div>
{* Marco principal de la consola de agente *}
<div id="elastix-callcenter-area-principal">
    {* Título con nombre del módulo *}
{if !$FRAMEWORK_TIENE_TITULO_MODULO}
    <div id="elastix-callcenter-titulo-consola" class="moduleTitle">&nbsp;<img src="{$icon}" border="0" align="absmiddle" alt="" />&nbsp;{$title}</div>
{/if}    
	{* Estado del agente con número y nombre del agente *}
	<div id="elastix-callcenter-estado-agente" class="{$CLASS_ESTADO_AGENTE_INICIAL}">
	    <span style="margin-left: 8pt;" id="elastix-callcenter-estado-agente-texto">{$TEXTO_ESTADO_AGENTE_INICIAL}</span>
        <div id="elastix-callcenter-cronometro">{$CRONOMETRO}</div>{* elastix-callcenter-cronometro *}
    </div>{* elastix-callcenter-estado-agente *}
    <div id="elastix-callcenter-wrap">
	    {* Los controles que aparecen del lado izquierdo de la interfaz *}
	    <div id="elastix-callcenter-controles">
	        <button id="btn_hangup" class="elastix-callcenter-boton-activo">{$BTN_COLGAR_LLAMADA}</button>
	        <button id="btn_togglebreak" class="{$CLASS_BOTON_BREAK}" >{$BTN_BREAK}</button>
	        <button id="btn_transfer" class="elastix-callcenter-boton-activo" >{$BTN_TRANSFER}</button>
{if $BTN_VTIGERCRM}
	        <button id="btn_vtigercrm" class="elastix-callcenter-boton-activo">{$BTN_VTIGERCRM}</button>
{/if}
	        <button id="btn_logout" class="elastix-callcenter-boton-activo">{$BTN_FINALIZAR_LOGIN}</button>


{* --mein Comienza la construcción del teléfono ml5  *}

        <br><br><audio id='audio_remote'></audio>
	<div align=center>
	<input type="text" style="width: 80%" id="callnumber" />
        <span class="label label-inverse" id="mysipstatus" style="font-size: 8px">Browser Not Supported</span><br>
	</div>
                               	<table style="width: 75%; height: 75%" align='center'>
                                    <tr><td><input type="button" style="width: 33%" class="btn-primary" value="1"
onclick="sipSendDTMF('1');"/><input type="button" style="width: 33%" class="btn-primary" value="2" onclick="sipSendDTMF('2');"/><input
type="button" style="width: 33%" class="btn-primary" value="3" onclick="sipSendDTMF('3');"/></td></tr>
                                    <tr><td><input type="button" style="width: 33%" class="btn-primary" value="4"
onclick="sipSendDTMF('4');"/><input type="button" style="width: 33%" class="btn-primary" value="5" onclick="sipSendDTMF('5');"/><input
type="button" style="width: 33%" class="btn-primary" value="6" onclick="sipSendDTMF('6');"/></td></tr>
                                    <tr><td><input type="button" style="width: 33%" class="btn-primary" value="7"
onclick="sipSendDTMF('7');"/><input type="button" style="width: 33%" class="btn-primary" value="8" onclick="sipSendDTMF('8');"/><input
type="button" style="width: 33%" class="btn-primary" value="9" onclick="sipSendDTMF('9');"/></td></tr>
                                    <tr><td><input type="button" style="width: 33%" class="btn-primary" value="*"
onclick="sipSendDTMF('*');"/><input type="button" style="width: 33%" class="btn-primary" value="0" onclick="sipSendDTMF('0');"/><input
type="button" style="width: 33%" class="btn-primary" value="#" onclick="sipSendDTMF('#');"/></td></tr>
                                </table>

	<div align="center">
	        <button class="btn btn-success" id="btnCall" onclick="call()" >Call</button>
	        <button  class="btn btn-danger " id="btnHangUp" onclick="hangup()" >Hangup</button><br>
	        <span class="label" id="mycallstatus"  style="font-size: 8px"></span>
	</div><br>


        {* Audios *}
        <audio id="audio_remote" autoplay="autoplay" />
        <audio id="ringtone" loop src="modules/agent_console_webrtc/themes/default/sounds/ringtone.wav" />
        <audio id="ringbacktone" loop src="modules/agent_console_webrtc/themes/default/sounds/ringbacktone.wav" />
        <audio id="dtmfTone" src="modules/agent_console_webrtc/themes/default/sounds/dtmf.wav" />
        <audio id="newmsg" src="modules/agent_console_webrtc/themes/default/sounds/newmsg.wav" />

{* --mein Fin de la construcción del teléfono ml5  *}

	    </div> {* elastix-callcenter-controles *}
	    {* El panel que aparece a la derecha como área principal del módulo *}
	    <div id="elastix-callcenter-contenido">
			{* Definición de las cejillas de información/script/formulario *}
			<div 
			  id="elastix-callcenter-cejillas-contenido"
			  class="{if $CALLINFO_CALLTYPE == ''}elastix-callcenter-cejillas-contenido-barra-oculta{else}elastix-callcenter-cejillas-contenido-barra-visible{/if}">
			   <ul>
				<li><a href="#elastix-callcenter-chat">Chat</a></li>
				<li><a href="#elastix-callcenter-llamada-info">{$TAB_LLAMADA_INFO}</a></li>
		        	<li><a href="#elastix-callcenter-llamada-script">{$TAB_LLAMADA_SCRIPT}</a></li>
			        <li><a href="#elastix-callcenter-llamada-form">{$TAB_LLAMADA_FORM}</a></li>
			   </ul>
			<div id="elastix-callcenter-chat">

				<br><b>

				<div>
					<div id="peers"></div>
					<!--<textarea name="recchat" class="styled" id="recchat" rows="15" cols="100" readonly></textarea><br>-->
					
					<table>
						<tr>
							<td>
								<label class='label label-succes'>Select the Contact</label>
							</td>
							<td>
								<select align="center" id="chatpeers" name="chatpeers">
						                    {html_options options=$LISTA_SIP}
                						</select>
							</td>
						</tr>
					</table>


					<div id="chatarea">
					</div>

					<input type="text" name="sendchat" class="styletext"id="sendchat" onkeypress="handleKeyPress(event)" size="120"/>
			                <input type="button" class="btn-success" style="" id="btnIM" value="Send" onclick='sendIM();' />&nbsp;	
				</div>
	
			</div>
		       <div id="elastix-callcenter-llamada-info">
	{$CONTENIDO_LLAMADA_INFORMACION}           
		       </div>
		       <div id="elastix-callcenter-llamada-script">
	{$CONTENIDO_LLAMADA_SCRIPT}           
		       </div>
		       <div id="elastix-callcenter-llamada-form">
	{$CONTENIDO_LLAMADA_FORMULARIO}           
		       </div>
			</div>{* elastix-callcenter-cejillas-contenido *}
	        {* Barra inferior que muestra la información de la llamada entrante *}
			<div 
			  id="elastix-callcenter-barra-llamada-entrante" 
			  class="elastix-callcenter-barra-llamada ui-widget-header ui-rounded-all"
			  {if $CALLINFO_CALLTYPE != 'incoming'}style="display: none;"{/if}>
		      <label for="llamada_entrante_contacto_telefono">{$LBL_CONTACTO_TELEFONO}: </label>
		      <span id="llamada_entrante_contacto_telefono">{$TEXTO_CONTACTO_TELEFONO}</span>
		      <label for="llamada_entrante_contacto_id">{$LBL_CONTACTO_SELECT}: </label>
		      <select
		          name="llamada_entrante_contacto_id"
		          id="llamada_entrante_contacto_id"
		          class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">
		          {html_options options=$LISTA_CONTACTOS}
		      </select>
		      <button id="btn_confirmar_contacto">{$BTN_CONFIRMAR_CONTACTO}</button>
			</div>{* elastix-callcenter-barra-llamada-entrante *}
	        {* Barra inferior que muestra la información de la llamada saliente *}
	        <div 
	          id="elastix-callcenter-barra-llamada-saliente" 
	          class="elastix-callcenter-barra-llamada ui-widget-header ui-rounded-all"
	          {if $CALLINFO_CALLTYPE != 'outgoing'}style="display: none;"{/if}>
	          <label for="llamada_saliente_contacto_telefono">{$LBL_CONTACTO_TELEFONO}: </label>
	          <span id="llamada_saliente_contacto_telefono">{$TEXTO_CONTACTO_TELEFONO}</span>
	          <label for="llamada_saliente_nombres">{$LBL_CONTACTO_NOMBRES}: </label>
	          <span id="llamada_saliente_nombres">{$TEXTO_CONTACTO_NOMBRES|escape:"html"}</span>
	          <button id="btn_agendar_llamada">{$BTN_AGENDAR_LLAMADA}</button>
	        </div>{* elastix-callcenter-barra-llamada-saliente *}
		</div>{* elastix-callcenter-contenido *}
	</div>
</div>{* elastix-callcenter-area-principal *}
<div id="elastix-callcenter-seleccion-break" title="{$TITLE_BREAK_DIALOG}">
    <form>
        <select
            name="break_select"
            id="break_select"
            class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only"
            style="width: 100%">{html_options options=$LISTA_BREAKS}
        </select>
    </form>
</div>{* elastix-callcenter-seleccion-break *}
<div id="elastix-callcenter-seleccion-transfer" title="{$TITLE_TRANSFER_DIALOG}">
    <form>
        <div style="display: table; width: 100%">
            <div style="display: table-row;">
	        <input 
	            name="transfer_extension"
	            id="transfer_extension"
	            class="ui-widget-content ui-corner-all" 
	            style="width: 100%" />
	        </div>
	        <div align="center" id="transfer_type_radio" style="display: table-row; width: 100%">
	            <input type="radio" id="transfer_type_blind" name="transfer_type" value="blind" checked="checked"/><label for="transfer_type_blind">{$LBL_TRANSFER_BLIND}</label>
	            <input type="radio" id="transfer_type_attended" name="transfer_type" value="attended" /><label for="transfer_type_attended">{$LBL_TRANSFER_ATTENDED}</label>
	        </div>
        </div>
    </form>
</div>{* elastix-callcenter-seleccion-transfer *}

<div id="elastix-callcenter-agendar-llamada" title="{$TITLE_SCHEDULE_CALL}">
	<div
	    id="elastix-callcenter-agendar-llamada-error-message"
	    class="ui-state-error ui-corner-all">
	    <p>
	        <span class="ui-icon ui-icon-alert" style="float: left; margin-right: .3em;"></span>
	        <span id="elastix-callcenter-agendar-llamada-error-message-text"></span>
	    </p>
	</div>
    <form>
        <div style="display: table; width: 100%">
	        <div style="display: table-row;">
	            <label style="display: table-cell;" for="schedule_new_phone">{$LBL_CONTACTO_TELEFONO}:&nbsp;</label>
	            <input 
		            name="schedule_new_phone"
		            id="schedule_new_phone"
		            class="ui-widget-content ui-corner-all"
		            maxlength="64"
		            style="display: table-cell; width: 100%;"
		            value="{$TEXTO_CONTACTO_TELEFONO|escape:"html"}" />
	        </div>
	        <div style="display: table-row;">
	            <label style="display: table-cell;" for="schedule_new_name">{$LBL_CONTACTO_NOMBRES}:&nbsp;</label>
	            <input 
		            name="schedule_new_name"
		            id="schedule_new_name"
		            class="ui-widget-content ui-corner-all"
		            maxlength="250"
		            style="display: table-cell; width: 100%;"
		            value="{$TEXTO_CONTACTO_NOMBRES|escape:"html"}" />
	        </div>
        </div>
        <hr />
        <div align="center" id="schedule_radio" style="width: 100%">
            <input type="radio" id="schedule_type_campaign_end" name="schedule_type" value="campaign_end" checked="checked"/><label for="schedule_type_campaign_end">{$LBL_SCHEDULE_CAMPAIGN_END}</label>
            <input type="radio" id="schedule_type_bydate" name="schedule_type" value="bydate" /><label for="schedule_type_bydate">{$LBL_SCHEDULE_BYDATE}</label>
        </div>
        <br/>
        <div id="schedule_date" style="display: table; width: 100%">
            <div style="display: table-row">
	            <div style="position: relative; display: table-cell;">
		            <label for="schedule_date_start">{$LBL_SCHEDULE_DATE_START}:&nbsp;</label>
		            <input type="text" class="ui-widget-content ui-corner-all" name="schedule_date_start" id="schedule_date_start" />
	            </div>
	            <div style="position: relative; display: table-cell;">
		            <label for="schedule_date_end">{$LBL_SCHEDULE_DATE_END}:&nbsp;</label>
		            <input type="text" class="ui-widget-content ui-corner-all" name="schedule_date_end" id="schedule_date_end" />
	            </div>
            </div>
            <div style="display: table-row">
	            <div style="position: relative; display: table-cell;">
	                <label>{$LBL_SCHEDULE_TIME_START}:&nbsp;</label><select
			            name="schedule_time_start_hh"
			            id="schedule_time_start_hh"
			            class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_HH}
	                </select>:<select
			            name="schedule_time_start_mm"
			            id="schedule_time_start_mm"
			            class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_MM}
	                </select>
	            </div>
	            <div style="position: relative; display: table-cell;">
	                <label>{$LBL_SCHEDULE_TIME_END}:&nbsp;</label><select
	                    name="schedule_time_end_hh"
	                    id="schedule_time_end_hh"
	                    class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_HH}
	                </select>:<select
	                    name="schedule_time_end_mm"
	                    id="schedule_time_end_mm"
	                    class="ui-button ui-widget ui-state-default ui-corner-all ui-button-text-only">{html_options options=$SCHEDULE_TIME_MM}
	                </select>
	            </div>
            </div>
            <input type="checkbox" id="schedule_same_agent" name="schedule_same_agent" /><label for="schedule_same_agent">{$LBL_SCHEDULE_SAME_AGENT}</label>
        </div>
    </form>
</div>
{literal}
<script type="text/javascript">
// Aplicar temas de jQueryUI a diversos elementos
$(function() {
{/literal}
    apply_ui_styles({$APPLY_UI_STYLES});
    initialize_client_state({$INITIAL_CLIENT_STATE});
{literal}
});
</script>
{/literal}


