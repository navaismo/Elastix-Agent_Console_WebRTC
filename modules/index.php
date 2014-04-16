<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |
  | http://www.elastix.org                                               |
  +----------------------------------------------------------------------+
  | Copyright (c) 2006 Palosanto Solutions S. A.                         |
  +----------------------------------------------------------------------+
  | Cdla. Nueva Kennedy Calle E 222 y 9na. Este                          |
  | Telfs. 2283-268, 2294-440, 2284-356                                  |
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
  $Id: new_campaign.php $ */
require_once "libs/paloSantoForm.class.php";
require_once "libs/paloSantoTrunk.class.php";
require_once "libs/paloSantoConfig.class.php";

// --mein conexion a bd asterisk
//require_once "libs/astdb.php";

function _moduleContent(&$smarty, $module_name)
{
    global $arrConf;
    global $arrLang;

    require_once "modules/$module_name/libs/elastix2.lib.php";
    require_once "modules/$module_name/libs/paloSantoConsola.class.php";
    require_once "modules/$module_name/configs/default.conf.php";
    require_once "modules/$module_name/libs/JSON.php";
    
    // Directorio de este módulo
    $sDirScript = dirname($_SERVER['SCRIPT_FILENAME']);

    // Se fusiona la configuración del módulo con la configuración global
    $arrConf = array_merge($arrConf, $arrConfModule);

    /* Se pide el archivo de inglés, que se elige a menos que el sistema indique
       otro idioma a usar. Así se dispone al menos de la traducción al inglés
       si el idioma elegido carece de la cadena.
     */
    load_language_module($module_name);

    // Asignación de variables comunes y directorios de plantillas
    $sDirPlantillas = (isset($arrConf['templates_dir'])) 
        ? $arrConf['templates_dir'] : 'themes';
    $sDirLocalPlantillas = "$sDirScript/modules/$module_name/".$sDirPlantillas.'/'.$arrConf['theme'];
    $smarty->assign("MODULE_NAME", $module_name);

    // Incluir todas las bibliotecas y CSS necesarios
    generarRutaJQueryModulo($smarty, $module_name);

    // Estado inicial de la consola del Call Center
    if (!isset($_SESSION['callcenter']) || 
        !is_array($_SESSION['callcenter']) || 
        !isset($_SESSION['callcenter']['estado_consola']))
        $_SESSION['callcenter'] = generarEstadoInicial();

    /* Al iniciar la sesión del agente, se asignan las variables elastix_agent_user y elastix_extension  */
    if ($_SESSION['callcenter']['estado_consola'] == 'logged-in') {
        // Manejo de la sesión activa del agente logoneado
        return manejarSesionActiva($module_name, $smarty, $sDirLocalPlantillas);
    } else {
        // Manejo del inicio de la sesión del agente
        return manejarLogin($module_name, $smarty, $sDirLocalPlantillas);
    }
}

/* Procedimiento para generar el estado inicial de la información del agente en
 * la sesión PHP.  */
function generarEstadoInicial()
{

    return array(
        /*  Estado de la consola. Los valores posibles son 
            logged-out  No hay agente logoneado
            logging     Agente intenta autenticarse con la llamada
            logged-in   Agente fue autenticado y está logoneado en consola
         */
        'estado_consola'    =>  'logged-out',

        /* El número del agente que se logonea. P.ej. 8000 para el agente 8000.
         * En estado logout el agente es NULL.
         */
        'agente'            =>  NULL,

        /* El nombre del agente */
        'agente_nombre'     =>  NULL,

        /* El número de la extensión interna que se logonea al agente. En estado
           logout la extensión es NULL 
         */
        'extension'         =>  NULL,
        
        /* El último tipo de llamada y el último ID de llamada atendida. Esto 
         * permite que se pueda guardar un formulario de una llamada que ya 
         * ha terminado */
        'ultimo_calltype'       =>  NULL,
        'ultimo_callid'         =>  NULL,
        'ultimo_callsurvey'     =>  NULL,
        'ultimo_campaignform'   =>  NULL,
        
        /* Se lleva la cuenta de la duración, en segundos, de los breaks que se
         * han iniciado y terminado durante la sesión. El posible break en curso
         * no se cuenta en break_acumulado. Pero el hecho de que hay un break
         * en curso se registra en break_iniciado por si se refresca la interfaz
         * y se encuentra que el break ha terminado. */
        'break_acumulado'       =>  0,
        'break_iniciado'        =>  NULL,
    );
}

// Procedimiento para decidir qué acción tomar en el estado de login de agente
function manejarLogin($module_name, &$smarty, $sDirLocalPlantillas)
{
    $sAction = '';
    $sContenido = '';

    $sAction = getParameter('action');
    
    /* Si el método está entre estos, pero el estado es de login, entonces se
     * ha perdido un estado de callcenter anterior. */
    if (in_array($sAction, array('checkStatus', 'agentLogout', 'hangup', 
        'break', 'unbreak', 'transfer', 'confirm_contact', 'schedule', 
        'saveforms'))) {
        $json = new Services_JSON();
        Header('Content-Type: application/json');
        return $json->encode(array(
            'action'    =>  'error',
            'message'   =>  _tr('(internal) Action valid only while logged-in, agent session lost or not started')));
    }
    
    if (!in_array($sAction, array('', 'doLogin', 'checkLogin')))
        $sAction = '';

    switch ($sAction) {
    case 'doLogin':
        $sContenido = manejarLogin_doLogin();
        break;
    case 'checkLogin':
        $sContenido = manejarLogin_checkLogin();
        break;
    default:
        $sContenido = manejarLogin_HTML($module_name, $smarty, $sDirLocalPlantillas);
        break;
    }
    
    return $sContenido;
}

// Mostrar el formulario donde el agente ingresa su login
function manejarLogin_HTML($module_name, &$smarty, $sDirLocalPlantillas)
{
    global $arrConf;

    // Acciones para mostrar el formulario, fuera de cualquier acción AJAX
    $smarty->assign(array(
        'FRAMEWORK_TIENE_TITULO_MODULO' => existeSoporteTituloFramework(),
        'icon'                          => 'modules/'.$module_name.'/images/call_center.png',
        'title'                         =>  _tr('WebRTC Agent Console'),
        'WELCOME_AGENT'         =>  _tr('Welcome to the WebRTC Agent Console '),
        'ENTER_USER_PASSWORD'   =>  _tr('Please select your agent number and your extension'),
        'USERNAME'              =>  _tr('Agent Number'),
        'EXTENSION'             =>  _tr('Extension'),
        'CALLBACK_LOGIN'        =>  _tr('Callback Login'),
        'PASSWORD'              =>  _tr('Password'),        
        'CALLBACK_EXTENSION'    =>  _tr('Callback Extension'),
        'LABEL_SUBMIT'          =>  _tr('Enter'),
        'LABEL_NOEXTENSIONS'    =>  _tr('There are no extensions available. At least one extension is required for agent login.'),
        'LABEL_NOAGENTS'        =>  _tr('There are no agents available. At least one agent is required for agent login.'),
        'ESTILO_FILA_ESTADO_LOGIN'  =>  'style="visibility: hidden; position: absolute;"',
        'REANUDAR_VERIFICACION' =>  0,
    ));
    
    $oPaloConsola = new PaloSantoConsola();
    $listaExtensiones = $oPaloConsola->listarExtensiones();
    $listaAgentes = $oPaloConsola->listarAgentes('static');
    $listaExtensionesCallback = $oPaloConsola->listarAgentes('dynamic');
    $oPaloConsola->desconectarTodo();
    $oPaloConsola = NULL;
    
    $bNoHayAgentes = (count($listaAgentes) == 0 && count($listaExtensionesCallback) == 0);
    if (count($listaAgentes) == 0) $listaAgentes[] = _tr('(no agents)');
    if (count($listaExtensionesCallback) == 0) $listaExtensionesCallback[] = _tr('(no agents)');
    $smarty->assign(array(
        'LISTA_EXTENSIONES' =>  $listaExtensiones,
        'LISTA_AGENTES'     =>  $listaAgentes,
        'LISTA_EXTENSIONES_CALLBACK'     =>  $listaExtensionesCallback,
        'NO_EXTENSIONS'     =>  (count($listaExtensiones) == 0), 
        'NO_AGENTS'         =>  $bNoHayAgentes, 
    ));
    
    // Restaurar el estado de espera en caso de que se refresque la página
    if (!is_null($_SESSION['callcenter']['agente']) &&
        !is_null($_SESSION['callcenter']['extension'])) {
        $smarty->assign(array(
            'ID_AGENT'                  =>  $_SESSION['callcenter']['agente'],
            'ID_EXTENSION'              =>  $_SESSION['callcenter']['extension'],
            'ID_EXTENSION_CALLBACK'     =>  $_SESSION['callcenter']['agente'],
            'ESTILO_FILA_ESTADO_LOGIN'  =>  'style="visibility: visible; position: none;"',
            'MSG_ESPERA'                =>  _tr('Logging agent in. Please wait...'),
            'REANUDAR_VERIFICACION'     =>  1,
        ));
        
    } else {
    	/* Si el usuario Elastix logoneado coincide con el número de agente de
         * la lista, se coloca este agente como opción por omisión para login.
         */
        if (isset($listaAgentes['Agent/'.$_SESSION['elastix_user']]))
            $smarty->assign('ID_AGENT', 'Agent/'.$_SESSION['elastix_user']);
        
        /* Si el usuario Elastix logoneado tiene una extensión y aparece en la
         * lista, se sugiere esta extension como la extensión a usar para 
         * marcar. */
        $pACL = new paloACL($arrConf['elastix_dsn']['acl']);
        $idUser = $pACL->getIdUser($_SESSION['elastix_user']);
        if ($idUser !== FALSE) {
        	$tupla = $pACL->getUsers($idUser);
            if (is_array($tupla) && count($tupla) > 0) {
                $sExtension = $tupla[0][3];
                if (isset($listaExtensiones[$sExtension]))
                    $smarty->assign('ID_EXTENSION', $sExtension);
                
                foreach (array_keys($listaExtensionesCallback) as $k) {
                	$regs = NULL;
                    if (preg_match('|^(\w+)/(\d+)$|', $k, $regs) && $regs[2] == $sExtension)
                        $smarty->assign('ID_EXTENSION_CALLBACK', $k);
                }
            }
        }
    }
    $sContenido ="
	<input type='hidden' id='ml5realm' value='' />
	<input type='hidden' id='ml5usn' value='' />
	<input type='hidden' id='ml5sipuri'value='' />
	<input type='hidden' id='ml5cid' value='' />
	<input type='hidden' id='ml5websocket' value='' />
	<input type='hidden' id='ml5breaker' value='true' />
	<input type='hidden' id='ml5pwd' value='' />";

    $sContenido .= $smarty->fetch("$sDirLocalPlantillas/login_agent.tpl");
    return $sContenido;	
}

// Procesar requerimiento AJAX para iniciar el login del agente
function manejarLogin_doLogin()
{
    $oPaloConsola = new PaloSantoConsola();

    // Acción AJAX para iniciar el login de agente
    $bCallback = in_array(getParameter('callback'), array('true', 'checked'));
    if ($bCallback) {
        $sAgente = getParameter('ext_callback');
        $sPasswordCallback = getParameter('pass_callback');
        $regs = NULL;
        $sExtension = (preg_match('|^(\w+)/(\d+)$|', $sAgente, $regs)) ? $regs[2]: NULL;
    } else {
        $sAgente = getParameter('agent');
        $sExtension = getParameter('ext');
        $sPasswordCallback = NULL;
    }

    $respuesta = array(
        'status'    =>  FALSE,  // VERDADERO para éxito en iniciar timbrado
        'message'   =>  '(no message)', // Posible mensaje de error
    );
    $bContinuar = TRUE;

    // Verificar que la extensión y el agente son válidos en el sistema
    if ($bContinuar) {
        $listaExtensiones = $oPaloConsola->listarExtensiones();
        $listaAgentes = $oPaloConsola->listarAgentes();
        if (!in_array($sAgente, array_keys($listaAgentes))) {
            $bContinuar = FALSE;
            $respuesta['status'] = FALSE;
            $respuesta['message'] = _tr('Invalid agent number');
        } elseif (!in_array($sExtension, array_keys($listaExtensiones))) {
            $bContinuar = FALSE;
            $respuesta['status'] = FALSE;
            $respuesta['message'] = _tr('Invalid extension number');
        }
    }
    
    // Verificar si el número de agente no está ya ocupado por otra extensión
    if ($bContinuar) {
        $oPaloConsola->desconectarTodo();
        $oPaloConsola = new PaloSantoConsola($sAgente);

        $estado = (!$bCallback || $oPaloConsola->autenticar($sAgente, $sPasswordCallback))
            ? $oPaloConsola->estadoAgenteLogoneado($sExtension)
            : array('estadofinal' => 'error');
        switch ($estado['estadofinal']) {
        case 'error':
        case 'mismatch':
            $respuesta['status'] = FALSE;
            $respuesta['message'] = _tr('Cannot start agent login').' - '.$oPaloConsola->errMsg;
            break;
        case 'logged-out':
            // No hay canal de login. Se inicia login a través de Originate para el caso de Agent/xxx
            $bExito = $oPaloConsola->loginAgente($sExtension);
            if (!$bExito) {
                $respuesta['status'] = FALSE;
                $respuesta['message'] = _tr('Cannot start agent login').' - '.$oPaloConsola->errMsg;
                break;
            }
            // En caso de éxito, se cuela al siguiente caso
        case 'logging':
        case 'logged-in':
            // Ya está logoneado este agente. Se procede directamente a espera
            $_SESSION['callcenter']['estado_consola'] = 'logging';
            $_SESSION['callcenter']['agente'] = $sAgente;
            $_SESSION['callcenter']['agente_nombre'] = $listaAgentes[$sAgente];
            $_SESSION['callcenter']['extension'] = $sExtension;
            $respuesta['status'] = TRUE;
            $respuesta['message'] = _tr('Logging agent in. Please wait...');
            
            if ($estado['estadofinal'] != 'logged-in') {
                // Esperar hasta 1 segundo para evento de fallo de login.
                $sEstado = $oPaloConsola->esperarResultadoLogin();
                if ($sEstado == 'logged-in') {
                    /* El agente ha podido logonearse. Se delega el cambio de
                     * estado_consola a logged-in a la verificación de 
                     * manejarLogin_checkLogin() */ 
                } elseif ($sEstado == 'logged-out') {
                    // El procedimiento de login ha fallado, sin causa conocida
                    $_SESSION['callcenter'] = generarEstadoInicial();
                    $respuesta['status'] = FALSE;
                    $respuesta['message'] = _tr('Agent log-in failed!');
                } elseif ($sEstado == 'error') {
                    // Ocurre un error al consultar el estado del agente
                    $_SESSION['callcenter'] = generarEstadoInicial();
                    $respuesta['status'] = FALSE;
                    $respuesta['message'] = _tr('Agent log-in failed!').' - '.$oPaloConsola->errMsg;
                }
            }
            break;
        }
    }
    
    $json = new Services_JSON();
    $sContenido = $json->encode($respuesta);
    Header('Content-Type: application/json');
    $oPaloConsola->desconectarTodo();

    return $sContenido;
}

// Procesar requerimiento AJAX para revisar el estado del proceso de login
function manejarLogin_checkLogin()
{
    $respuesta = array(
        'action'    =>  'wait', // Opciones: wait login error
        'message'   =>  '(no message)', // Posible mensaje de error
    );
    $bContinuar = TRUE;

    // Verificación rápida para saber si el canal es correcto
    $sAgente = $_SESSION['callcenter']['agente'];
    $sExtension = $_SESSION['callcenter']['extension'];
    $oPaloConsola = new PaloSantoConsola($sAgente);

    if ($bContinuar) {
        $estado = $oPaloConsola->estadoAgenteLogoneado($sExtension);
        switch ($estado['estadofinal']) {
        case 'error':
        case 'mismatch':
            // Otra extensión ya ocupa el login del agente indicado, o error
            $_SESSION['callcenter'] = generarEstadoInicial();
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Cannot start agent login').' - '.$oPaloConsola->errMsg.
                "ext=$sExtension agente=$sAgente";
            $bContinuar = FALSE;
            break;
        case 'logged-out':
            // No se encuentra evidencia de que se empezara el login
            $_SESSION['callcenter'] = generarEstadoInicial();
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Agent login process not started');
            $bContinuar = FALSE;
            break;
        case 'logging':
            $_SESSION['callcenter']['estado_consola'] = 'logging';
            $respuesta['action'] = 'wait';
            $respuesta['message'] = _tr('Logging agent in. Please wait...');
            break;
        case 'logged-in':
            // El agente ha podido logonearse. Se procede a mostrar el formulario
            $_SESSION['callcenter']['estado_consola'] = 'logged-in';
            $respuesta['action'] = 'login';
            $bContinuar = FALSE;
            break;
        }
    	
    }
    
    if ($bContinuar && $respuesta['action'] == 'wait') {
        $oPaloConsola->desconectarEspera();
        
        // Se inicia espera larga con el navegador...
        $iTimeoutPoll = PaloSantoConsola::recomendarIntervaloEsperaAjax();
        session_commit();
        set_time_limit(0);
        $iTimestampInicio = time();
        
        while ($bContinuar && time() - $iTimestampInicio <  $iTimeoutPoll) {

            // Verificar si el agente ya está en línea
            $sEstado = $oPaloConsola->esperarResultadoLogin();
            if ($sEstado == 'logged-in') {
                // Reiniciar la sesión para poder modificar las variables
                session_start();

                // El agente ha podido logonearse. Se procede a mostrar el formulario
                $_SESSION['callcenter']['estado_consola'] = 'logged-in';
                $respuesta['action'] = 'login';
                $bContinuar = FALSE;

            } elseif ($sEstado == 'logged-out') {
                // Reiniciar la sesión para poder modificar las variables
                session_start();

                // El procedimiento de login ha fallado, sin causa conocida
                $_SESSION['callcenter'] = generarEstadoInicial();
                $respuesta['action'] = 'error';
                $respuesta['message'] = _tr('Agent log-in terminated.');
                $bContinuar = FALSE;
            } elseif ($sEstado == 'error') {
                // Reiniciar la sesión para poder modificar las variables
                session_start();

                // Ocurre un error al consultar el estado del agente
                $_SESSION['callcenter'] = generarEstadoInicial();
                $respuesta['action'] = 'error';
                $respuesta['message'] = _tr('Agent log-in failed!').' - '.$oPaloConsola->errMsg;
                $bContinuar = FALSE;
            }
        }
    }
    
    $json = new Services_JSON();
    $sContenido = $json->encode($respuesta);
    Header('Content-Type: application/json');
    $oPaloConsola->desconectarTodo();

    return $sContenido;
}

// Procedimiento para decidir qué acción tomar en el estado de sesión activa
function manejarSesionActiva($module_name, &$smarty, $sDirLocalPlantillas)
{
    $sAction = '';
    $sContenido = '';

    $sAction = getParameter('action');
    if (!in_array($sAction, array('', 'checkStatus', 'agentLogout', 'hangup', 
        'break', 'unbreak', 'transfer', 'confirm_contact', 'schedule', 
        'saveforms')))
        $sAction = '';

    // Se verifica si el agente sigue logoneado en la cola de Asterisk
    $sAgente = $_SESSION['callcenter']['agente'];
    $sExtension = $_SESSION['callcenter']['extension'];
    $oPaloConsola = new PaloSantoConsola($sAgente);
    $estado = $oPaloConsola->estadoAgenteLogoneado($sExtension);
    if ($estado['estadofinal'] != 'logged-in') {
        // Se marca el final de la sesión del agente en las tablas de auditoría
        $oPaloConsola->logoutAgente();
        $_SESSION['callcenter'] = generarEstadoInicial();
    }

    switch ($sAction) {
    case 'checkStatus':
        $sContenido = manejarSesionActiva_checkStatus($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola, $estado);
        break;
    case 'hangup':
        $sContenido = manejarSesionActiva_hangup($oPaloConsola);
        break;
    case 'agentLogout':
        $sContenido = manejarSesionActiva_agentLogout($oPaloConsola);
        break;
    case 'break':
        $sContenido = manejarSesionActiva_agentBreak($oPaloConsola);
        break;
    case 'unbreak':
        $sContenido = manejarSesionActiva_agentUnBreak($oPaloConsola);
        break;
    case 'transfer':
        $sContenido = manejarSesionActiva_agentTransfer($oPaloConsola);
        break;
    case 'confirm_contact':
        $sContenido = manejarSesionActiva_confirmContact($oPaloConsola, $estado);
        break;
    case 'schedule':
        $sContenido = manejarSesionActiva_scheduleCall($oPaloConsola);
        break;
    case 'saveforms':
        $sContenido = manejarSesionActiva_saveForms($oPaloConsola, $estado);
        break;
    default:
        if ($estado['estadofinal'] != 'logged-in') {
            // Para agente no logoneado, se redirecciona a la página de login
            Header('Location: ?menu='.$module_name);
            $sContenido = '';
        } else {
            $sContenido = manejarSesionActiva_HTML($module_name, $smarty, $sDirLocalPlantillas, $oPaloConsola, $estado);
        }
        break;
    }
    $oPaloConsola->desconectarTodo();

    return $sContenido;
}



function manejarSesionActiva_HTML($module_name, &$smarty, $sDirLocalPlantillas, $oPaloConsola, $estado)
{
    $bInactivarBotonColgar = FALSE;
    $bPuedeConfirmarContacto = FALSE;

	// --mein obtener el pass de la extension
	/*global $pDB;
	$tempext = $_SESSION['callcenter']['extension'];

	$sql="select data from sip where id='$tempext' and keyword='secret'";
	$res=mysql_query($sql);
	$row = mysql_fetch_array($res);
	$ml5tpwd=$row['data'];*/

    // Acciones para mostrar la pantalla principal, fuera de cualquier acción AJAX
    for ($i = 0; $i < 24; $i++) { $ii = sprintf('%02d', $i); $comboHora[$ii] = $ii; }
    for ($i = 0; $i < 60; $i++) { $ii = sprintf('%02d', $i); $comboMinuto[$ii] = $ii; }
    $smarty->assign(array(
        'FRAMEWORK_TIENE_TITULO_MODULO' => existeSoporteTituloFramework(),
        'icon'                          => 'modules/'.$module_name.'/images/call_center.png',
        'title'                         =>  _tr('WebRTC Agent Console').': '.
            $_SESSION['callcenter']['agente_nombre'],
        'BTN_COLGAR_LLAMADA'            =>  _tr('Hangup'),
        'BTN_TRANSFER'                  =>  _tr('Transfer'),
        'BTN_VTIGERCRM'                 =>  file_exists('/var/www/html/vtigercrm') ? _tr('VTiger CRM') : NULL,
        'BTN_FINALIZAR_LOGIN'           =>  _tr('End session'),
        'TITLE_BREAK_DIALOG'            =>  _tr('Select break type'),
        'BTN_CONFIRMAR_CONTACTO'        =>  _tr('Confirm contact'),
        'LBL_CONTACTO_TELEFONO'         =>  _tr('Phone number'),
        'LBL_CONTACTO_SELECT'           =>  _tr('Contact'),
        'LBL_CONTACTO_NOMBRES'          =>  _tr('Names'),
        'TEXTO_CONTACTO_NOMBRES'        =>  '',
        'TEXTO_CONTACTO_TELEFONO'       =>  '',
        'BTN_AGENDAR_LLAMADA'           =>  _tr('Schedule call'),
        'TITLE_TRANSFER_DIALOG'         =>  _tr('Select extension to transfer to'),
        'LBL_TRANSFER_BLIND'            =>  _tr('Blind transfer'),
        'LBL_TRANSFER_ATTENDED'         =>  _tr('Attended transfer'),
        'TITLE_SCHEDULE_CALL'           =>  _tr('Schedule call'),
        'LBL_SCHEDULE_CAMPAIGN_END'     =>  _tr('Call at end of campaign'),
        'LBL_SCHEDULE_BYDATE'           =>  _tr('Schedule at date'),
        'LBL_SCHEDULE_DATE_START'       =>  _tr('Start date'),
        'LBL_SCHEDULE_DATE_END'         =>  _tr('End date'),
        'LBL_SCHEDULE_TIME_START'       =>  _tr('Start time'),
        'LBL_SCHEDULE_TIME_END'         =>  _tr('End time'),
        'LBL_SCHEDULE_SAME_AGENT'       =>  _tr('Schedule to same agent'),
        'SCHEDULE_TIME_HH'              =>  $comboHora,
        'SCHEDULE_TIME_MM'              =>  $comboMinuto,
        'TAB_LLAMADA_INFO'              =>  _tr('Call Information'),
        'TAB_LLAMADA_SCRIPT'            =>  _tr('Call Script'),
        'TAB_LLAMADA_FORM'              =>  _tr('Call Form'),
        'CRONOMETRO'                    =>  '00:00:00',
        'LISTA_BREAKS'                  =>  $oPaloConsola->listarBreaks(),
        'CONTENIDO_LLAMADA_INFORMACION' =>  '',
        'CONTENIDO_LLAMADA_SCRIPT'      =>  '',
        'CONTENIDO_LLAMADA_FORMULARIO'  =>  '',
        'CALLINFO_CALLTYPE'             =>  '',
        'BTN_HOLD'                      =>  $estado['onhold'] ? _tr('End Hold') : _tr('Hold'),

	// --mein  Valores para registrar telefono SIPML5
	'ML5EXTEN'			=>  $_SESSION['callcenter']['extension'],
	'ML5CID'			=>  $_SESSION['callcenter']['agente'],
	'ML5WS'				=>  'ws://'.$_SERVER['SERVER_ADDR'].':10060',
	'ML5IP'				=>  $_SERVER['SERVER_ADDR'],
	'ML5SIPURI'			=>  'sip:'.$_SESSION['callcenter']['extension'].'@'.$_SERVER['SERVER_ADDR'],
	'ML5REALM'			=>  'asterisk',
        //'ML5PWD'              		=>  $ml5tpwd,        
	
    ));
    $estadoInicial = array(
        'onhold'        =>  $estado['onhold'],
        'break_id'      =>  is_null($estado['pauseinfo']) ? NULL : $estado['pauseinfo']['pauseid'],
        'calltype'      =>  NULL,
        'campaign_id'   =>  NULL,
        'callid'        =>  NULL,
        'timer_seconds' =>  '',
        'url'           =>  NULL,
        'urlopentype'   =>  NULL,
    );

    // Decidir estado del break a mostrar
    if (!is_null($estado['pauseinfo'])) {
        $_SESSION['callcenter']['break_iniciado'] = $estado['pauseinfo']['pausestart'];
        $iDuracionPausaActual = time() - strtotime($estado['pauseinfo']['pausestart']);
        $iDuracionPausa = $iDuracionPausaActual + $_SESSION['callcenter']['break_acumulado'];
        $smarty->assign(array(
            'CLASS_BOTON_BREAK'             =>  'elastix-callcenter-boton-unbreak',
            'CLASS_ESTADO_AGENTE_INICIAL'   =>  'elastix-callcenter-class-estado-break',
            'BTN_BREAK'                     =>  _tr('End Break'),
            'TEXTO_ESTADO_AGENTE_INICIAL'   =>  _tr('On break').': '.$estado['pauseinfo']['pausename'],

            // TODO: debe contener tiempo acumulado de break desde inicio sesión
            // TODO: idea: sumar inicios y finales de breaks en variable sesión
            'CRONOMETRO'                    =>  sprintf('%02d:%02d:%02d', 
                ($iDuracionPausa - ($iDuracionPausa % 3600)) / 3600, 
                (($iDuracionPausa - ($iDuracionPausa % 60)) / 60) % 60, 
                $iDuracionPausa % 60),
        ));
        $estadoInicial['timer_seconds'] = $iDuracionPausa;
    } else {
        if (!is_null($_SESSION['callcenter']['break_iniciado'])) {
        	/* Si esta condición se cumple, entonces se ha perdido el evento 
             * pauseexit durante la espera en manejarSesionActiva_checkStatus().
             * Se hace la suposición de que el refresco ocurre poco después de
             * que termina el break, y que por lo tanto el error al usar time()
             * como fin del break es pequeño. 
             */
            $_SESSION['callcenter']['break_acumulado'] += time() - strtotime($_SESSION['callcenter']['break_iniciado']);
        }
        
        $smarty->assign(array(
            'CLASS_BOTON_BREAK'             =>  'elastix-callcenter-boton-break',
            'BTN_BREAK'                     =>  _tr('Take Break'),
            'CLASS_ESTADO_AGENTE_INICIAL'   =>  'elastix-callcenter-class-estado-ocioso',
            'TEXTO_ESTADO_AGENTE_INICIAL'   =>  _tr('No active call'),
        ));
        $_SESSION['callcenter']['break_iniciado'] = NULL;
    }
    
    // Cambios según agente conectado a una llamada versus ocioso
    if (!is_null($estado['callinfo'])) {
        // Información sobre la llamada conectada
        $infoLlamada = $oPaloConsola->leerInfoLlamada(
            $estado['callinfo']['calltype'],
            $estado['callinfo']['campaign_id'],
            $estado['callinfo']['callid']);
        if ($estado['callinfo']['calltype'] == 'incoming' && is_null($estado['callinfo']['campaign_id'])) {
            $infoCampania['queue'] = $infoLlamada['queue'];
        	$infoCampania['script'] = $oPaloConsola->leerScriptCola($infoCampania['queue']);
            $infoCampania['forms'] = NULL;
        } else {
            $infoCampania = $oPaloConsola->leerInfoCampania(
                $estado['callinfo']['calltype'],
                $estado['callinfo']['campaign_id']);
        }
        if (is_null($infoCampania['script']) || $infoCampania['script'] == '')
            $infoCampania['script'] = _tr('(No script available)');

        // Variables de canal de la llamada activa
        $chanvars = $oPaloConsola->leerVariablesCanalLlamadaActiva();

        // Almacenar para regenerar formulario
        $_SESSION['callcenter']['ultimo_calltype'] = $estado['callinfo']['calltype'];
        $_SESSION['callcenter']['ultimo_callid'] = $estado['callinfo']['callid'];
        $_SESSION['callcenter']['ultimo_callsurvey']['call_survey'] = $infoLlamada['call_survey'];
        $_SESSION['callcenter']['ultimo_campaignform']['forms'] = $infoCampania['forms'];

        // Fecha completa de la llamada
        $iDuracionLlamada = time() - strtotime($estado['callinfo']['linkstart']);
  
        // Asignaciones independientes del tipo de llamada
        $bInactivarBotonColgar = false; // Se usa para botón hangup y botón transfer
        $smarty->assign(array(
            'CLASS_ESTADO_AGENTE_INICIAL'   =>  'elastix-callcenter-class-estado-activo',
            'TEXTO_ESTADO_AGENTE_INICIAL'   =>  _tr('Connected to call'),
            'TEXTO_CONTACTO_TELEFONO'       =>  $estado['callinfo']['callnumber'],
            'CALLINFO_CALLTYPE'             =>  $estado['callinfo']['calltype'],

            // TODO: debe contener tiempo transcurrido en llamada
            'CRONOMETRO'                    =>  sprintf('%02d:%02d:%02d', 
                ($iDuracionLlamada - ($iDuracionLlamada % 3600)) / 3600, 
                (($iDuracionLlamada - ($iDuracionLlamada % 60)) / 60) % 60, 
                $iDuracionLlamada % 60),
            
            'CONTENIDO_LLAMADA_INFORMACION' =>  manejarSesionActiva_HTML_generarInformacion($smarty, $sDirLocalPlantillas, $infoLlamada, $infoCampania),
            'CONTENIDO_LLAMADA_FORMULARIO'  =>  manejarSesionActiva_HTML_generarFormulario($smarty, $sDirLocalPlantillas, $infoLlamada, $infoCampania),
            'CONTENIDO_LLAMADA_SCRIPT'      =>  $infoCampania['script'],
        ));
        $estadoInicial['timer_seconds'] = $iDuracionLlamada;
        $estadoInicial['calltype'] = $estado['callinfo']['calltype'];
        $estadoInicial['campaign_id'] = $estado['callinfo']['campaign_id'];
        $estadoInicial['callid'] = $estado['callinfo']['callid'];
        $estadoInicial['urlopentype'] = isset($infoCampania['urlopentype']) ? $infoCampania['urlopentype'] : NULL;
        $estadoInicial['url'] = is_null($estadoInicial['urlopentype']) 
            ? NULL : construirUrlExterno($infoCampania['urltemplate'], $infoLlamada + array(
            'callnumber'        =>  $estado['callinfo']['callnumber'],
            'callid'            =>  $infoLlamada['call_id'],
            'agent_number'      =>  $estado['callinfo']['agent_number'],
            'remote_channel'    =>  $estado['callinfo']['remote_channel']),
            $chanvars);
        
        // Asignaciones específicas para llamadas entrantes
        if ($estado['callinfo']['calltype'] == 'incoming') {
        	$comboContactos = array();
            foreach ($infoLlamada['matching_contacts'] as $idContacto => $tuplaContacto) {
                $infoContactoViejo = array();
                $sDescripcionContacto = '';
                foreach ($tuplaContacto as $attrContacto) {
                	$sDescripcionContacto .= $attrContacto['value'].' ';
                    if (in_array($attrContacto['label'], array('first_name', 'last_name', 'cedula_ruc')))
                        $infoContactoViejo[$attrContacto['label']] = $attrContacto['value'];
                }
                if (count($infoContactoViejo) == 3) {
                	$comboContactos[$idContacto] = $infoContactoViejo['cedula_ruc'].
                        ' - '.$infoContactoViejo['first_name'].' '.$infoContactoViejo['last_name'];
                } else {
                    /* TODO: dar formato adecuado para cuando contactos de llamadas 
                     * entrantes puedan tener atributos arbitrarios */
                    $comboContactos[$idContacto] = $sDescripcionContacto;
                }
        	}
            if (count($comboContactos) == 0) {
            	$comboContactos[''] = _tr('(no matching contacts)');
            }
            $smarty->assign(array(
                'LISTA_CONTACTOS'           =>  $comboContactos,
            ));
            $bPuedeConfirmarContacto = (count($comboContactos) > 1);
        }
        
        // Asignaciones específicas para llamadas salientes
        if ($estado['callinfo']['calltype'] == 'outgoing') {

            /* TODO: el siguiente código asume que el atributo 1 es el nombre
             * del cliente. Esta suposición se hereda del callcenter anterior.
             * Se debe de idear un método para dar formato al nombre del cliente
             * a partir de cualquier combinación de columnas */
            $sNombreCliente = isset($infoLlamada['call_attributes'][1]) 
                ? $infoLlamada['call_attributes'][1]['value'] 
                : _tr('(unavailable)');

        	$smarty->assign(array(
                'TEXTO_CONTACTO_NOMBRES'        =>  $sNombreCliente,
            ));
        }
    } else {
    	$bInactivarBotonColgar = true; // Se usa para botón hangup y botón transfer
        $smarty->assign(array(
            'CONTENIDO_LLAMADA_FORMULARIO'  =>  is_null($_SESSION['callcenter']['ultimo_calltype']) 
                ? '' 
                : manejarSesionActiva_HTML_generarFormulario($smarty, $sDirLocalPlantillas, 
                        $_SESSION['callcenter']['ultimo_callsurvey'], 
                        $_SESSION['callcenter']['ultimo_campaignform']),
        ));
    }
    $oPaloConsola = new PaloSantoConsola();
    $json = new Services_JSON();
    $listaExtensionesCallback = $oPaloConsola->listarAgentes('dynamic');
    $smarty->assign(array(
        'APPLY_UI_STYLES'   =>  $json->encode(array(
            'break_commit'              =>  _tr('Take Break'),
            'break_dismiss'             =>  _tr('Dismiss'),
            'transfer_commit'           =>  _tr('Transfer'),
            'transfer_dismiss'          =>  _tr('Dismiss'),
            'schedule_commit'           =>  _tr('Schedule'),
            'schedule_dismiss'          =>  _tr('Dismiss'),
            'external_url_tab'          =>  _tr('External site'),
            'schedule_call_error_msg_missing_date' => _tr('Start and end date are required for date scheduling.'),
            'no_call'                   =>  $bInactivarBotonColgar,
            'can_confirm_contact'       =>  $bPuedeConfirmarContacto,
            )),
        'INITIAL_CLIENT_STATE'  =>  $json->encode($estadoInicial),
    ));
	

    $smarty->assign(array(
	    'LISTA_SIP'			=>  $listaExtensionesCallback,
            ));


    // --mein obtener el pass de la extension
	//MYSQL!!
	$dsn = generarDSNSistema("root","asterisk");
	$pDB = new paloDB($dsn);
	$tempext = $_SESSION['callcenter']['extension'];

	$query = "select data from sip where id=? and keyword='secret'";;
	$arrParam = array($tempext);
	$result = $pDB->fetchTable($query,TRUE,$arrParam);
	
	if(count($result) > 0){
		foreach($result as $value){
			$ml5tpwd = $value['data'];
		}
	}


    $sContenido =  "\n<!-----ml5----->\n<input type='hidden' id='ml5realm' value='asterisk' />\n";
    $sContenido .= "<input type='hidden' id='ml5usn' value='".$_SESSION['callcenter']['extension']."' />\n";
    $sContenido .= "<input type='hidden' id='ml5sipuri'value='sip:".$_SESSION['callcenter']['extension'].'@'.$_SERVER['SERVER_ADDR']."' />\n";
    $sContenido .= "<input type='hidden' id='ml5cid' value='".$_SESSION['callcenter']['agente']."' />\n";
    $sContenido .= "<input type='hidden' id='ml5websocket' value='ws://".$_SERVER['SERVER_ADDR'].":10060' />\n";
    $sContenido .= "<input type='hidden' id='ml5breaker' value='true' />\n";
    $sContenido .= "<input type='hidden' id='ml5pwd' value='".$ml5tpwd."' />\n\n";

    $sContenido .= $smarty->fetch("$sDirLocalPlantillas/agent_console_webrtc.tpl");

	return $sContenido;
}

function manejarSesionActiva_HTML_generarInformacion($smarty, $sDirLocalPlantillas, $infoLlamada, $infoCampania)
{
    $atributos = array();
    foreach ($infoLlamada['call_attributes'] as $iOrden => $atributo) {
        if (preg_match('|^http(s)?://|', $atributo['value'])) {
        	$atributo['value'] = '<a target="_blank" href="'.$atributo['value'].'">'.$atributo['value'].'</a>';
        } else {
            $atributo['value'] = htmlentities($atributo['value'], ENT_COMPAT, 'UTF-8');
        }
        $atributos[] = $atributo;        
    }
    
    // Caso especial: verificación de etiquetas de contact llamada entrante
    if ($infoLlamada['calltype'] == 'incoming' && count($atributos) == 5) {
    	$n = 5;
        foreach ($atributos as $atributo) {
    		if (in_array($atributo['label'], array('first_name', 'last_name', 'phone', 'cedula_ruc', 'contact_source')))
                $n--;
    	}
        if ($n == 0) {
            $traduccion = array(
                'first_name'    =>  _tr('First name'),
                'last_name'     =>  _tr('Last name'),
                'phone'         =>  _tr('Phone number'),
                'cedula_ruc'    =>  _tr('National ID'),                
            );

        	// Se deben copiar los atributos, excepto el contact_source
            $t = array();
            foreach ($atributos as $atributo) {
            	if ($atributo['label'] != 'contact_source') {
            		$atributo['label'] = $traduccion[$atributo['label']];
                    $t[] = $atributo;
            	}
            }
            $atributos = $t;
        }
    }
    
    $smarty->assign(array(
        'LBL_INFORMACION_LLAMADA'   =>  _tr('Call Information'),
        'MSG_NO_ATTRIBUTES'         =>  _tr('No information available for this call'),
        'ATRIBUTOS_LLAMADA'         =>  $atributos,
    ));
	return $smarty->fetch("$sDirLocalPlantillas/agent_console_atributos.tpl");
}

// Se usa $infoLlamada['call_survey'] , $infoCampania['forms']
function manejarSesionActiva_HTML_generarFormulario($smarty, $sDirLocalPlantillas, $infoLlamada, $infoCampania)
{
    // Se puebla current_value con los valores recogidos previamente, si existen
    if (isset($infoCampania['forms']) && is_array($infoCampania['forms'])) {
        foreach ($infoCampania['forms'] as $idForm => $tuplaForm) {
        	foreach ($tuplaForm['fields'] as $idxCampo => $tuplaCampo) {
        		if (isset($infoLlamada['call_survey'][$idForm]) && 
                    isset($infoLlamada['call_survey'][$idForm][$tuplaCampo['id']]))
                    $infoCampania['forms'][$idForm]['fields'][$idxCampo]['current_value'] = 
                        $infoLlamada['call_survey'][$idForm][$tuplaCampo['id']]['value']; 
        	}
        }
        $smarty->assign(array(
            'FORMS'                     => $infoCampania['forms'],
            'BTN_GUARDAR_FORMULARIOS'   =>  _tr('Save data'),
            ));
        return $smarty->fetch("$sDirLocalPlantillas/agent_console_formulario.tpl");
    } else {
    	return _tr('No forms available for this call');
    }
}

function manejarSesionActiva_agentLogout($oPaloConsola)
{
    $respuesta = array(
        'action'    =>  'logged-out',   // logged-out error
        'message'   =>  '(no message)',
    );
    $bExito = $oPaloConsola->logoutAgente();
    if (!$bExito) {
        $respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Error while logging out agent').' - '.$oPaloConsola->errMsg;
    }
    
    // Se asume que el único error posible en logout es que el agente ya
    // esté deslogoneado.
    $_SESSION['callcenter']['estado_consola'] = 'logged-out';
    $_SESSION['callcenter']['agente'] = NULL;
    $_SESSION['callcenter']['agente_nombre'] = NULL;
    $_SESSION['callcenter']['extension'] = NULL;
    
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_hangup($oPaloConsola)
{
    $respuesta = array(
        'action'    =>  'hangup',
        'message'   =>  '(no message)',
    );
    $bExito = $oPaloConsola->colgarLlamada();
    if (!$bExito) {
        $respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Error while hanging up call').' - '.$oPaloConsola->errMsg;
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_agentBreak($oPaloConsola)
{
    $respuesta = array(
        'action'    =>  'break',
        'message'   =>  '(no message)',
    );
    $idBreak = getParameter('breakid');
    if (is_null($idBreak) || !ctype_digit($idBreak)) {
    	$respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing break ID');
    } else {
        $bExito = $oPaloConsola->iniciarBreak($idBreak);
        if (!$bExito) {
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Error while starting break').' - '.$oPaloConsola->errMsg;
        }
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_agentUnBreak($oPaloConsola)
{
    $respuesta = array(
        'action'    =>  'unbreak',
        'message'   =>  '(no message)',
    );
    $bExito = $oPaloConsola->terminarBreak();
    if (!$bExito) {
        $respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Error while stopping break').' - '.$oPaloConsola->errMsg;
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_agentTransfer($oPaloConsola)
{
    $respuesta = array(
        'action'    =>  'transfer',
        'message'   =>  '(no message)',
    );
    $sTransferExt = getParameter('extension');
    if (is_null($sTransferExt) || !ctype_digit($sTransferExt)) {
        $respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing extension to transfer');
    } else {
        $bExito = $oPaloConsola->transferirLlamada($sTransferExt, in_array(getParameter('atxfer'), array('true', 'checked')));
        if (!$bExito) {
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Error while transferring call').' - '.$oPaloConsola->errMsg;
        }
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_confirmContact($oPaloConsola, $estado)
{
    $respuesta = array(
        'action'    =>  'confirmed',
        'message'   =>  _tr('Contact successfully confirmed'),
    );
    $idContact = getParameter('id_contact');
    if (is_null($idContact) || !ctype_digit($idContact)) {
        $respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Invalid or missing contact ID');
    } elseif (!isset($estado['callinfo']) || $estado['callinfo']['calltype'] != 'incoming') {
        $respuesta['action'] = 'error';
        $respuesta['message'] = _tr('Agent not handling an incoming call');
    } else {
        $bExito = $oPaloConsola->confirmarContacto($estado['callinfo']['callid'], $idContact);
        if (!$bExito) {
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Error while confirming contact').' - '.$oPaloConsola->errMsg;
        }
    }

    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_scheduleCall($oPaloConsola)
{
    $respuesta = array(
        'action'    =>  'scheduled',
        'message'   =>  _tr('Call successfully scheduled'),
    );

    $infoAgendar = getParameter('data');
    foreach (array('schedule_new_phone', 'schedule_new_name', 
        'schedule_use_daterange', 'schedule_use_sameagent', 
        'schedule_date_start', 'schedule_date_end', 'schedule_time_start',
        'schedule_time_end') as $k) 
        if (!isset($infoAgendar[$k])) $infoAgendar[$k] = NULL;
    
    $schedule = in_array($infoAgendar['schedule_use_daterange'], array('true', 'checked')) ? array(
        'date_init' =>  $infoAgendar['schedule_date_start'],
        'date_end'  =>  $infoAgendar['schedule_date_end'], 
        'time_init' =>  $infoAgendar['schedule_time_start'],
        'time_end'  =>  $infoAgendar['schedule_time_end'],
    ) : NULL;
    $sameagent = in_array($infoAgendar['schedule_use_sameagent'], array('true', 'checked'));
    $newphone = $infoAgendar['schedule_new_phone'];
    $newname = $infoAgendar['schedule_new_name'];
    
    if (is_array($schedule) && ($schedule['date_init'] == '' || $schedule['date_end'] == '' ||
        $schedule['time_init'] == '' || $schedule['time_end'] == '')) {
        $respuesta = array(
            'action'    =>  'error',
            'message'   =>  _tr('Invalid or incomplete schedule'),
        );
    } else {
        $bExito = $oPaloConsola->agendarLlamada($schedule, $sameagent, $newphone, $newname);
        if (!$bExito) {
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Error while scheduling call').' - '.$oPaloConsola->errMsg;
        }
    }
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_saveForms($oPaloConsola, $estado)
{
    $respuesta = array(
        'action'    =>  'saved',
        'message'   =>  _tr('Form data successfully saved'),
    );

    $formdata = getParameter('data');
    if (!is_array($formdata)) {
        $respuesta = array(
            'action'    =>  'error',
            'message'   =>  _tr('Invalid or incomplete form data'),
        );
    } else {
        $formInfo = array();
        foreach ($formdata as $tupladata) {
        	$regs = NULL;
            if (preg_match('/^field-(\d+)-(\d+)$/', $tupladata[0], $regs)) {
            	$formInfo[$regs[1]][$regs[2]] = $tupladata[1];
                $_SESSION['callcenter']['ultimo_callsurvey']['call_survey'][$regs[1]][$regs[2]] = array(
                    'label' =>  '', // TODO: asignar desde formulario de campaña
                    'value' =>  $tupladata[1],
                );
            }
        }

        $bExito = $oPaloConsola->guardarDatosFormularios(
            $_SESSION['callcenter']['ultimo_calltype'], //$estado['callinfo']['calltype'],
            $_SESSION['callcenter']['ultimo_callid'], //$estado['callinfo']['callid'],
            $formInfo);
        if (!$bExito) {
            $respuesta['action'] = 'error';
            $respuesta['message'] = _tr('Error while saving form data').' - '.$oPaloConsola->errMsg;
        }
    }
    $json = new Services_JSON();
    Header('Content-Type: application/json');
    return $json->encode($respuesta);
}

function manejarSesionActiva_checkStatus($module_name, $smarty, 
    $sDirLocalPlantillas, $oPaloConsola, $estado)
{
    $respuesta = array();

    ignore_user_abort(true);
    set_time_limit(0);

    $sNombrePausa = NULL;
    $iDuracionLlamada = NULL;
    $iDuracionPausa = $iDuracionPausaActual = NULL;
    
    $estadoCliente = getParameter('clientstate');

    // Validación del estado del cliente:
    // onhold break_id calltype campaign_id callid
    $estadoCliente['onhold'] = isset($estadoCliente['onhold']) 
        ? ($estadoCliente['onhold'] == 'true') 
        : false;
    foreach (array('break_id', 'calltype', 'campaign_id', 'callid') as $k) {
        if (!isset($estadoCliente[$k]) || $estadoCliente[$k] == 'null' || $estadoCliente[$k] == '') 
            $estadoCliente[$k] = NULL;
    }
    if (is_null($estadoCliente['calltype'])) {
        $estadoCliente['campaign_id'] = $estadoCliente['callid'] = NULL;
    } elseif (is_null($estadoCliente['callid'])) {
        $estadoCliente['campaign_id'] = $estadoCliente['calltype'] = NULL;
    } elseif (is_null($estadoCliente['campaign_id']) && $estadoCliente['calltype'] != 'incoming') {
        $estadoCliente['calltype'] = $estadoCliente['callid'] = NULL;
    }

    // Modo a funcionar: Long-Polling, o Server-sent Events
    $sModoEventos = getParameter('serverevents');
    $bSSE = (!is_null($sModoEventos) && $sModoEventos); 
    if ($bSSE) {
        Header('Content-Type: text/event-stream');
        printflush("retry: 5000\n");
    } else {
    	Header('Content-Type: application/json');
    }

    // Respuesta inmediata si el agente ya no está logoneado
    if ($estado['estadofinal'] != 'logged-in') {
        // Respuesta inmediata si el agente ya no está logoneado
        jsonflush($bSSE, array(
            'event' =>  'logged-out',
        ));
        return;
    }
	
    // Verificación de la consistencia del estado de break
    if (!is_null($estado['pauseinfo'])) {
        $sNombrePausa = $estado['pauseinfo']['pausename'];
        $iDuracionPausaActual = time() - strtotime($estado['pauseinfo']['pausestart']);
        $iDuracionPausa = $iDuracionPausaActual + $_SESSION['callcenter']['break_acumulado'];
    } else {
        /* Si esta condición se cumple, entonces se ha perdido el evento 
         * pauseexit durante la espera en manejarSesionActiva_checkStatus().
         * Se hace la suposición de que el refresco ocurre poco después de
         * que termina el break, y que por lo tanto el error al usar time()
         * como fin del break es pequeño. 
         */
        if (!is_null($_SESSION['callcenter']['break_iniciado'])) {
           $_SESSION['callcenter']['break_acumulado'] += time() - strtotime($_SESSION['callcenter']['break_iniciado']);
        }
        $_SESSION['callcenter']['break_iniciado'] = NULL;
    }
    if (!is_null($estado['pauseinfo']) && 
        (is_null($estadoCliente['break_id']) || $estadoCliente['break_id'] != $estado['pauseinfo']['pauseid'])) {
        // La consola debe de entrar en break
        $respuesta[] = construirRespuesta_breakenter($estado['pauseinfo']['pauseid']);
    } elseif (!is_null($estadoCliente['break_id']) && is_null($estado['pauseinfo'])) {
        // La consola debe de salir del break
        $respuesta[] = construirRespuesta_breakexit();
    }

    // Verificación de la consistencia del estado de hold
    if (!$estadoCliente['onhold'] && $estado['onhold']) {
        // La consola debe de entrar en hold
        $respuesta[] = construirRespuesta_holdenter();
    } elseif ($estadoCliente['onhold'] && !$estado['onhold']) {
        // La consola debe de salir de hold
        $respuesta[] = construirRespuesta_holdexit();
    }
    
    if (!is_null($estado['callinfo'])) {
        $iDuracionLlamada = time() - strtotime($estado['callinfo']['linkstart']);           
    }
    
    // Verificación de atención a llamada
    if (!is_null($estado['callinfo']) && 
        (is_null($estadoCliente['calltype']) || 
            $estadoCliente['calltype'] != $estado['callinfo']['calltype'] ||
            $estadoCliente['campaign_id'] != $estado['callinfo']['campaign_id'] ||
            $estadoCliente['callid'] != $estado['callinfo']['callid'])) {

        // Información sobre la llamada conectada
        $infoLlamada = $oPaloConsola->leerInfoLlamada(
            $estado['callinfo']['calltype'],
            $estado['callinfo']['campaign_id'],
            $estado['callinfo']['callid']);

        // Leer información del formulario de la campaña
        if ($estado['callinfo']['calltype'] == 'incoming' && is_null($estado['callinfo']['campaign_id'])) {
            $infoCampania['forms'] = NULL;
        } else {
            $infoCampania = $oPaloConsola->leerInfoCampania(
                $estado['callinfo']['calltype'],
                $estado['callinfo']['campaign_id']);
        }

        // Almacenar para regenerar formulario
        $_SESSION['callcenter']['ultimo_calltype'] = $estado['callinfo']['calltype'];
        $_SESSION['callcenter']['ultimo_callid'] = $estado['callinfo']['callid'];
        $_SESSION['callcenter']['ultimo_callsurvey']['call_survey'] = $infoLlamada['call_survey'];
        $_SESSION['callcenter']['ultimo_campaignform']['forms'] = $infoCampania['forms'];

        $respuesta[] = construirRespuesta_agentlinked($smarty, $sDirLocalPlantillas, 
            $oPaloConsola, $estado['callinfo'], $infoLlamada, $infoCampania);
    } elseif (!is_null($estadoCliente['calltype']) && is_null($estado['callinfo'])) {
        // La consola dejó de atender una llamada
        $respuesta[] = construirRespuesta_agentunlinked();
    }

    // Ciclo de verificación para Server-sent Events
    $sAgente = $_SESSION['callcenter']['agente'];
    $iTimeoutPoll = PaloSantoConsola::recomendarIntervaloEsperaAjax();
    $bReinicioSesion = FALSE;
    do {
        $oPaloConsola->desconectarEspera();
        
        // Se inicia espera larga con el navegador...
        session_commit();
        $iTimestampInicio = time();
        
        $respuestaEventos = array();
    	
        $oPaloConsola->pingAgente();
        while (connection_status() == CONNECTION_NORMAL && 
            count($respuestaEventos) <= 0 && count($respuesta) <= 0 
            && time() - $iTimestampInicio <  $iTimeoutPoll) {
            
            $listaEventos = $oPaloConsola->esperarEventoSesionActiva();
            if (is_null($listaEventos)) {
                // Ocurrió una excepción al esperar eventos
                @session_start();

                $respuesta[] = array(
                    'event' =>  'logged-out',
                );

                // Eliminar la información de login
                $_SESSION['callcenter'] = generarEstadoInicial();
                $bReinicioSesion = TRUE;
                break;
            }
            
            foreach ($listaEventos as $evento) 
            if (isset($evento['agent_number']) && $evento['agent_number'] == $sAgente) 
            switch ($evento['event']) {
            case 'agentloggedout':
                // Reiniciar la sesión para poder modificar las variables
                @session_start();

                $respuesta[] = array(
                    'event' =>  'logged-out',
                );

                // Eliminar la información de login
                $_SESSION['callcenter'] = generarEstadoInicial();
                $bReinicioSesion = TRUE;
                break;
            case 'pausestart':
                unset($respuestaEventos[$evento['pause_class']]);
                switch ($evento['pause_class']) {
                case 'break':
                    if (is_null($estadoCliente['break_id']) ||
                        $estadoCliente['break_id'] != $evento['pause_type']) {
                        $sNombrePausa = $evento['pause_name'];
                        $respuestaEventos['break'] = construirRespuesta_breakenter($evento['pause_type']);
                    }
                    @session_start();
                    $iDuracionPausaActual = time() - strtotime($evento['pause_start']);
                    $iDuracionPausa = $iDuracionPausaActual + $_SESSION['callcenter']['break_acumulado'];
                    $_SESSION['callcenter']['break_iniciado'] = $evento['pause_start'];
                    break;
                case 'hold':
                    if (!$estadoCliente['onhold']) {
                        $respuestaEventos['hold'] = construirRespuesta_holdenter();
                    }
                    break;
                }
                break;
            case 'pauseend':
                unset($respuestaEventos[$evento['pause_class']]);
                switch ($evento['pause_class']) {
                case 'break':
                    if (!is_null($estadoCliente['break_id'])) {
                        $respuestaEventos['break'] = construirRespuesta_breakexit();
                    }
                    @session_start();
                    if (!is_null($_SESSION['callcenter']['break_iniciado'])) {
                        $_SESSION['callcenter']['break_acumulado'] += $evento['pause_duration'];
                        $_SESSION['callcenter']['break_iniciado'] = NULL;
                    }
                    break;
                case 'hold':
                    if ($estadoCliente['onhold']) {
                        $respuestaEventos['hold'] = construirRespuesta_holdexit();
                    }
                    break;
                }
                break;
            case 'agentlinked':
                unset($respuestaEventos['llamada']);
                /* Actualizar la interfaz si entra una nueva llamada, o si 
                 * la llamada activa anterior es reemplazada. */
                if (is_null($estadoCliente['calltype']) || 
                    $estadoCliente['calltype'] != $evento['call_type'] ||
                    $estadoCliente['campaign_id'] != $evento['campaign_id'] ||
                    $estadoCliente['callid'] != $evento['call_id']) {
                    $nuevoEstado = array(
                        'calltype'      =>  $evento['call_type'],
                        'campaign_id'   =>  $evento['campaign_id'],
                        'linkstart'     =>  $evento['datetime_linkstart'],
                        'callid'        =>  $evento['call_id'],
                        'callnumber'    =>  $evento['phone'],
                    );
                    $iDuracionLlamada = time() - strtotime($nuevoEstado['linkstart']);

                    // Leer información del formulario de la campaña
                    if ($nuevoEstado['calltype'] == 'incoming' && is_null($nuevoEstado['campaign_id'])) {
                        $infoCampania['forms'] = NULL;
                    } else {
                        $infoCampania = $oPaloConsola->leerInfoCampania(
                            $nuevoEstado['calltype'],
                            $nuevoEstado['campaign_id']);
                    }

                    // Almacenar para regenerar formulario
                    @session_start();
                    $_SESSION['callcenter']['ultimo_calltype'] = $nuevoEstado['calltype'];
                    $_SESSION['callcenter']['ultimo_callid'] = $nuevoEstado['callid'];
                    $_SESSION['callcenter']['ultimo_callsurvey']['call_survey'] = $evento['call_survey'];
                    $_SESSION['callcenter']['ultimo_campaignform']['forms'] = $infoCampania['forms'];

                    $respuestaEventos['llamada'] = construirRespuesta_agentlinked(
                        $smarty, $sDirLocalPlantillas, $oPaloConsola, $nuevoEstado, 
                        $evento, $infoCampania);
                }
                break;
            case 'agentunlinked':
                unset($respuestaEventos['llamada']);
                if (!is_null($estadoCliente['calltype'])) {
                    $respuestaEventos['llamada'] = construirRespuesta_agentunlinked();
                }
                break;
            }
        } // while(...)

        // Sólo debe haber hasta un evento de llamada, de break, de hold 
        if (isset($respuestaEventos['break'])) $respuesta[] = $respuestaEventos['break']; 
        if (isset($respuestaEventos['hold'])) $respuesta[] = $respuestaEventos['hold']; 
        if (isset($respuestaEventos['llamada'])) $respuesta[] = $respuestaEventos['llamada']; 

        // Agregar los textos a cambiar en la interfaz
        $sDescInicial = describirEstadoBarra($estadoCliente);
        $estadoFinal = $estadoCliente;
        foreach ($respuesta as $evento) switch ($evento['event']) {
        case 'holdenter':
            $estadoCliente['onhold'] = TRUE;
            break;
        case 'holdexit':
            $estadoCliente['onhold'] = FALSE;
            break;
        case 'breakenter': 
            $estadoFinal['break_id'] = $evento['break_id'];
            $estadoCliente['break_id'] = $evento['break_id'];
            break;
        case 'breakexit':
            $estadoFinal['break_id'] = NULL;
            $estadoCliente['break_id'] = NULL;
            break;
        case 'agentlinked':
            $estadoFinal['calltype'] = $evento['calltype'];
            $estadoCliente['calltype'] = $evento['calltype'];
            $estadoCliente['campaign_id'] = $evento['campaign_id'];
            $estadoCliente['callid'] = $evento['callid'];
            break;
        case 'agentunlinked':
            $estadoFinal['calltype'] = NULL;
            $estadoCliente['calltype'] = NULL;
            $estadoCliente['campaign_id'] = NULL;
            $estadoCliente['callid'] = NULL;
            break;
        }
        $sDescFinal = describirEstadoBarra($estadoFinal);
        $iPosEvento = count($respuesta) - 1;
        if ($iPosEvento >= 0 && $sDescInicial != $sDescFinal) switch ($sDescFinal) {
        case 'llamada':
            $respuesta[$iPosEvento]['txt_estado_agente_inicial'] = _tr('Connected to call');
            $respuesta[$iPosEvento]['class_estado_agente_inicial'] = 'elastix-callcenter-class-estado-activo';            
            $respuesta[$iPosEvento]['timer_seconds'] = $iDuracionLlamada;            
            break;
        case 'break':
            $respuesta[$iPosEvento]['txt_estado_agente_inicial'] = _tr('On break').': '.$sNombrePausa;
            $respuesta[$iPosEvento]['class_estado_agente_inicial'] = 'elastix-callcenter-class-estado-break';            
            $respuesta[$iPosEvento]['timer_seconds'] = $iDuracionPausa;            
            break;
        case 'ocioso':
            $respuesta[$iPosEvento]['txt_estado_agente_inicial'] = _tr('No active call');
            $respuesta[$iPosEvento]['class_estado_agente_inicial'] = 'elastix-callcenter-class-estado-ocioso';
            $respuesta[$iPosEvento]['timer_seconds'] = '';            
            break;
        }
        
        jsonflush($bSSE, $respuesta);
        
        $respuesta = array();

    } while($bSSE && !$bReinicioSesion && connection_status() == CONNECTION_NORMAL);
    $oPaloConsola->desconectarTodo();
}

function jsonflush($bSSE, $respuesta)
{
    $json = new Services_JSON();
    $r = $json->encode($respuesta);
    if ($bSSE)
        printflush("data: $r\n\n");
    else printflush($r);
}

function printflush($s)
{
    print $s;
    ob_flush();
    flush();
}

/*  
 * La barra de color de la interfaz debe terminar en uno de tres estados:
 * llamada, break, ocioso.
 */
function describirEstadoBarra($estado)
{
    if (!is_null($estado['calltype']))
        return 'llamada';
    if (!is_null($estado['break_id']))
        return 'break';
    return 'ocioso';
}

function construirRespuesta_breakenter($pause_id)
{
    return array(
        'event'                     =>  'breakenter',
        'break_id'                  =>  $pause_id,
        
        // Etiquetas a modificar en la interfaz
        'txt_btn_break' =>              _tr('End Break'),
    );
}

function construirRespuesta_breakexit()
{
    return array(
        'event'                     =>  'breakexit',
        
        // Etiquetas a modificar en la interfaz
        'txt_btn_break'             =>  _tr('Take Break'),
    );
}

function construirRespuesta_holdenter()
{
    return array(
        'event'         =>  'holdenter',
        
        // Etiquetas a modificar en la interfaz
        'txt_btn_hold' =>  _tr('End Hold'),
    );
}

function construirRespuesta_holdexit()
{
    return array(
        'event'         =>  'holdexit',
        
        // Etiquetas a modificar en la interfaz
        'txt_btn_hold' =>  _tr('Hold'),
    );
}

function construirRespuesta_agentlinked($smarty, $sDirLocalPlantillas, 
    $oPaloConsola, $callinfo, $infoLlamada, &$infoCampania)
{
    foreach (array('calltype', 'campaign_id', 'callid', 'callnumber',
        'agent_number', 'remote_channel') as $k) {
        if (!isset($infoLlamada[$k]) && isset($callinfo[$k]))
            $infoLlamada[$k] = $callinfo[$k];
    }
    if ($callinfo['calltype'] == 'incoming' && is_null($callinfo['campaign_id'])) {
        $infoCampania['queue'] = $infoLlamada['queue'];
        $infoCampania['script'] = $oPaloConsola->leerScriptCola($infoCampania['queue']);
        $infoCampania['forms'] = NULL;
    }
    if (is_null($infoCampania['script']) || $infoCampania['script'] == '')
        $infoCampania['script'] = _tr('(No script available)');

    // Variables de canal de la llamada activa
    $chanvars = $oPaloConsola->leerVariablesCanalLlamadaActiva();

    // Fecha completa de la llamada
    $iDuracionLlamada = time() - strtotime($callinfo['linkstart']);

    // La consola empezó a atender a una llamada
    $registroCambio = array(
        'event'                 =>  'agentlinked',
        'calltype'              =>  $callinfo['calltype'],
        'campaign_id'           =>  $callinfo['campaign_id'],
        'callid'                =>  $callinfo['callid'],

        'txt_contacto_telefono' =>  $callinfo['callnumber'],
        'cronometro'            =>  sprintf('%02d:%02d:%02d', ($iDuracionLlamada - ($iDuracionLlamada % 3600)) / 3600, (($iDuracionLlamada - ($iDuracionLlamada % 60)) / 60) % 60, $iDuracionLlamada % 60),
        'llamada_informacion'   =>  manejarSesionActiva_HTML_generarInformacion($smarty, $sDirLocalPlantillas, $infoLlamada, $infoCampania),
        'llamada_formulario'    =>  manejarSesionActiva_HTML_generarFormulario($smarty, $sDirLocalPlantillas, $infoLlamada, $infoCampania),
        'llamada_script'        =>  $infoCampania['script'],
        'urlopentype'           =>  isset($infoCampania['urlopentype']) ? $infoCampania['urlopentype'] : NULL,
        'url'                   =>  NULL,
    );
    
    if (isset($infoCampania['urltemplate']) && !is_null($infoCampania['urltemplate'])) {
        $registroCambio['url'] = construirUrlExterno($infoCampania['urltemplate'], $infoLlamada, $chanvars);
    }
    
    // Asignaciones específicas para llamadas entrantes
    if ($callinfo['calltype'] == 'incoming') {
        $comboContactos = array();
        foreach ($infoLlamada['matching_contacts'] as $idContacto => $tuplaContacto) {
            $infoContactoViejo = array();
            $sDescripcionContacto = '';
            foreach ($tuplaContacto as $attrContacto) {
                $sDescripcionContacto .= $attrContacto['value'].' ';
                if (in_array($attrContacto['label'], array('first_name', 'last_name', 'cedula_ruc')))
                    $infoContactoViejo[$attrContacto['label']] = $attrContacto['value'];
            }
            if (count($infoContactoViejo) == 3) {
                $sDescripcionContacto = $infoContactoViejo['cedula_ruc'].
                    ' - '.$infoContactoViejo['first_name'].' '.$infoContactoViejo['last_name'];
            } else {
                /* TODO: dar formato adecuado para cuando contactos de llamadas 
                 * entrantes puedan tener atributos arbitrarios */
                
            }
            
            /* El htmlentities de clave y valor es necesario porque del lado 
             * Javascript, se usa concatenación directa de cadenas, porque el
             * objeto option devuelto por createElement no muestra la etiqueta
             * en IE6. Si se descubre la manera de hacerlo, hay que deshacer
             * el htmlentities aquí. */
            $comboContactos[htmlentities($idContacto, ENT_COMPAT, 'UTF-8')] = 
                htmlentities($sDescripcionContacto, ENT_COMPAT, 'UTF-8');
        }
        if (count($comboContactos) == 0) {
            $comboContactos['x'] = htmlentities(_tr('(no matching contacts)'), ENT_COMPAT, 'UTF-8');
        }

        $registroCambio['lista_contactos'] = $comboContactos;
        $registroCambio['puede_confirmar_contacto'] = (count($comboContactos) > 1);
    }
    
    // Asignaciones específicas para llamadas salientes
    if ($callinfo['calltype'] == 'outgoing') {

        /* TODO: el siguiente código asume que el atributo 1 es el nombre
         * del cliente. Esta suposición se hereda del callcenter anterior.
         * Se debe de idear un método para dar formato al nombre del cliente
         * a partir de cualquier combinación de columnas */
        $sNombreCliente = isset($infoLlamada['call_attributes'][1]) 
            ? $infoLlamada['call_attributes'][1]['value'] 
            : _tr('(unavailable)');

        $registroCambio['txt_contacto_nombres'] = $sNombreCliente;
    }

    return $registroCambio;
}

function construirRespuesta_agentunlinked()
{
    return array(
        'event'     =>  'agentunlinked',
    );	
}

function construirUrlExterno($s, $infoLlamada, $chanvars)
{
    $reemplazos = array(
        '{__AGENT_NUMBER__}'    =>  (isset($infoLlamada['agent_number']) 
                ? $infoLlamada['agent_number'] : ''),
        '{__REMOTE_CHANNEL__}'  =>  (isset($infoLlamada['remote_channel']) 
                ? $infoLlamada['remote_channel'] : ''),
        '{__CALL_TYPE__}'       =>  $infoLlamada['calltype'],
        '{__CAMPAIGN_ID__}'     =>  $infoLlamada['campaign_id'],
        '{__CALL_ID__}'         =>  $infoLlamada['callid'],
        '{__PHONE__}'           =>  $infoLlamada['callnumber'],
        '{__UNIQUEID__}'        =>  $infoLlamada['uniqueid'],
    );
    if (is_array($chanvars)) foreach ($chanvars as $k => $v) {
    	$reemplazos['{'.$k.'}'] = $v;
    }
    if (isset($infoLlamada['call_attributes'])) foreach ($infoLlamada['call_attributes'] as $tupla) {
        $reemplazos['{'.$tupla['label'].'}'] = $tupla['value'];
    }
    foreach ($reemplazos as $k => $v) {
        $s = str_replace($k, urlencode($v), $s);
    }
    return $s;
}

?>
