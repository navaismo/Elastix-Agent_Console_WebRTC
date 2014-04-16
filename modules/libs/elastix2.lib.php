<?php
/* vim: set expandtab tabstop=4 softtabstop=4 shiftwidth=4:
  Codificación: UTF-8
  +----------------------------------------------------------------------+
  | Elastix version 0.5                                                  |f
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

/**
 * Esta biblioteca contiene funciones que existen en Elastix 2 pero no en 
 * Elastix 1.6. De esta manera se puede programar asumiendo un entorno 
 * equivalente a Elastix 2. Por medio de las verificaciones de function_exists()
 * se evita declarar la función cuando se ejecuta realmente en Elastix 2.
 */

// Función de conveniencia para pedir traducción de texto, si existe
if (!function_exists('_tr')) {
function _tr($s)
{
    global $arrLang;
    return isset($arrLang[$s]) ? $arrLang[$s] : $s;
}
}

/**
* Funcion que sirve para obtener los valores de los parametros de los campos en los
* formularios, Esta funcion verifiva si el parametro viene por POST y si no lo encuentra
* trata de buscar por GET para poder retornar algun valor, si el parametro ha consultar no
* no esta en request retorna null.
*
* Ejemplo: $nombre = getParameter('nombre');
*/
if (!function_exists('getParameter')) {
function getParameter($parameter)
{
    if(isset($_POST[$parameter]))
        return $_POST[$parameter];
    else if(isset($_GET[$parameter]))
        return $_GET[$parameter];
    else
        return null;
}
}

/**
 * Función para obtener la clave MySQL de usuarios bien conocidos de Elastix.
 * Los usuarios conocidos hasta ahora son 'root' (sacada de /etc/elastix.conf)
 * y 'asteriskuser' (sacada de /etc/amportal.conf)
 *
 * @param   string  $sNombreUsuario     Nombre de usuario para interrogar
 * @param   string  $ruta_base          Ruta base para inclusión de librerías
 *
 * @return  mixed   NULL si no se reconoce usuario, o la clave en plaintext
 */
if (!function_exists('obtenerClaveConocidaMySQL')) {
function obtenerClaveConocidaMySQL($sNombreUsuario, $ruta_base='')
{
    require_once $ruta_base.'libs/paloSantoConfig.class.php';
    switch ($sNombreUsuario) {
    case 'root':
        $pConfig = new paloConfig("/etc", "elastix.conf", "=", "[[:space:]]*=[[:space:]]*");
        $listaParam = $pConfig->leer_configuracion(FALSE);
        if (isset($listaParam['mysqlrootpwd'])) 
            return $listaParam['mysqlrootpwd']['valor'];
        else return 'eLaStIx.2oo7'; // Compatibility for updates where /etc/elastix.conf is not available
        break;
    case 'asteriskuser':
        $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
        $listaParam = $pConfig->leer_configuracion(FALSE);
        if (isset($listaParam['AMPDBPASS']))
            return $listaParam['AMPDBPASS']['valor'];
        break;
    }
    return NULL;
}
}

/**
 * Función para construir un DSN para conectarse a varias bases de datos 
 * frecuentemente utilizadas en Elastix. Para cada base de datos reconocida, se
 * busca la clave en /etc/elastix.conf o en /etc/amportal.conf según corresponda.
 *
 * @param   string  $sNombreUsuario     Nombre de usuario para interrogar
 * @param   string  $sNombreDB          Nombre de base de datos para DNS
 * @param   string  $ruta_base          Ruta base para inclusión de librerías
 *
 * @return  mixed   NULL si no se reconoce usuario, o el DNS con clave resuelta
 */
if (!function_exists('generarDSNSistema')) {
function generarDSNSistema($sNombreUsuario, $sNombreDB, $ruta_base='')
{
    require_once $ruta_base.'libs/paloSantoConfig.class.php';
    switch ($sNombreUsuario) {
    case 'root':
        $sClave = obtenerClaveConocidaMySQL($sNombreUsuario, $ruta_base);
        if (is_null($sClave)) return NULL;
        return 'mysql://root:'.$sClave.'@localhost/'.$sNombreDB;
    case 'asteriskuser':
        $pConfig = new paloConfig("/etc", "amportal.conf", "=", "[[:space:]]*=[[:space:]]*");
        $listaParam = $pConfig->leer_configuracion(FALSE);
        return $listaParam['AMPDBENGINE']['valor']."://".
               $listaParam['AMPDBUSER']['valor']. ":".
               $listaParam['AMPDBPASS']['valor']. "@".
               $listaParam['AMPDBHOST']['valor']. "/".$sNombreDB;
    }
    return NULL;
}
}

if (!function_exists('load_language_module')) {
function load_language_module($module_id, $ruta_base='')
{
    $lang = get_language($ruta_base);
    include_once $ruta_base."modules/$module_id/lang/en.lang";
    $lang_file_module = $ruta_base."modules/$module_id/lang/$lang.lang";
    if ($lang != 'en' && file_exists("$lang_file_module")) {
        $arrLangEN = $arrLangModule;
        include_once "$lang_file_module";
        $arrLangModule = array_merge($arrLangEN, $arrLangModule);
    }

    global $arrLang;
    global $arrLangModule;
    $arrLang = array_merge($arrLang,$arrLangModule);
}
}

/**
 * Las siguientes son funciones de compatibilidad para que el módulo haga uso de
 * funcionalidad de Elastix 2, mientras que siga funcionando con Elastix 1.6.
 */

/**
 * Procedimiento para listar la ruta, relativa a la raíz del framework, donde
 * residen las bibliotecas JQuery, los CSS de JQuery, y las bibliotecas 
 * Javascript del módulo. Si se ejecuta en Elastix 2, la lista es vacía, porque
 * todos los archivos fueron incluidos por el framework de Elastix 2. En Elastix
 * 1.6 se devuelve una lista de tuplas de la forma ('js|css', 'ruta_a_archivo')
 *
 * @param   object  $smarty         Objeto Smarty a modificar
 * @param   string  $module_name    Nombre del módulo que invoca
 * 
 * @return  mixed   Arreglo de tipos y rutas a incluir para el módulo
 */
function generarRutaJQueryModulo(&$smarty, $module_name)
{
    $bHayHeaderJQuery = !is_null($smarty->get_template_vars('HEADER_LIBS_JQUERY'));
    $bHayHeaderModulo = !is_null($smarty->get_template_vars('HEADER_MODULES'));

    // Verificar si se está ejecutando en Elastix 2.0
    if (in_array('putHEAD_JQUERY_HTML', get_class_methods('PaloSantoNavigation'))) {
        
        // Verificar versión de jQuery en el sistema...
        $listaArchivos = glob($_SERVER['DOCUMENT_ROOT'].'/libs/js/jquery/*.js');
        if (is_array($listaArchivos) && count($listaArchivos) > 0) {
            foreach ($listaArchivos as $sRutaArchivo) {
                $sNombreBase = basename($sRutaArchivo);
                $regs = NULL;
                if (preg_match('/^jquery-([\d\.]+\d)/', $sNombreBase, $regs)) {
                	$versionMin = array(1, 5, 0);
                    $versionActual = explode('.', $regs[1]);
                    while (count($versionMin) < count($versionActual))
                        $versionMin[] = 0;
                    while (count($versionMin) > count($versionActual))
                        $versionActual[] = 0;
                    if ($versionActual >= $versionMin) {
                        // La versión es suficientemente reciente - se delega a Elastix
                        return array();
                    }
                }
            }
        }
    }
    $listaRutas = array();
    $sIncluir = ''; $sIncluirModulo = '';
    
    // Rutas de JavaScript hacia JQuery
    $listaRutasPosibles = array(
        "modules/$module_name/libs/jquery/",
        'libs/js/jquery/',
        'libs/js/jquery/js/',
    );
    foreach ($listaRutasPosibles as $sPosibleRuta) {
        $listaArchivos = glob($_SERVER['DOCUMENT_ROOT'].'/'.$sPosibleRuta.'*.js');
        if (is_array($listaArchivos) && count($listaArchivos) > 0) {
            foreach ($listaArchivos as $sRutaArchivo) {
                $sRutaURL = substr($sRutaArchivo, strlen($_SERVER['DOCUMENT_ROOT']) + 1);
                if ($bHayHeaderJQuery) {
                	$sIncluir .= "<script type=\"text/javascript\" src='$sRutaURL'></script>\n";
                } else {
                    $listaRutas[] = array('js', $sRutaURL);
                }
            }
            break;
        }
    }

    // Rutas de CSS hacia JQuery
    $listaRutasPosibles = array(
        "modules/$module_name/libs/jquery/css/ui-lightness/",
        'libs/js/jquery/css/ui-lightness/',
    );
    foreach ($listaRutasPosibles as $sPosibleRuta) {
        $listaArchivos = glob($_SERVER['DOCUMENT_ROOT'].'/'.$sPosibleRuta.'*.css');
        if (is_array($listaArchivos) && count($listaArchivos) > 0) {
            foreach ($listaArchivos as $sRutaArchivo) {
                $sRutaURL = substr($sRutaArchivo, strlen($_SERVER['DOCUMENT_ROOT']) + 1);
                if ($bHayHeaderJQuery) {
                    $sIncluir .= "<link rel=\"stylesheet\" href='$sRutaURL' />\n";
                } else {
                    $listaRutas[] = array('css', $sRutaURL);
                }
            }
            break;
        }
    }

    // Rutas de JavaScript hacia módulo
    $listaRutasPosibles = array(
        "modules/$module_name/themes/default/js/",
    );
    foreach ($listaRutasPosibles as $sPosibleRuta) {
        $listaArchivos = glob($_SERVER['DOCUMENT_ROOT'].'/'.$sPosibleRuta.'*.js');
        if (is_array($listaArchivos) && count($listaArchivos) > 0) {
            foreach ($listaArchivos as $sRutaArchivo) {
                $sRutaURL = substr($sRutaArchivo, strlen($_SERVER['DOCUMENT_ROOT']) + 1);
                if ($bHayHeaderModulo) {
                    $sIncluirModulo .= "<script type=\"text/javascript\" src='$sRutaURL'></script>\n";
                } else {
                    $listaRutas[] = array('js', $sRutaURL);
                }
            }
            break;
        }
    }

    // Rutas de CSS hacia módulo
    $listaRutasPosibles = array(
        "modules/$module_name/themes/default/css/",
    );
    foreach ($listaRutasPosibles as $sPosibleRuta) {
        $listaArchivos = glob($_SERVER['DOCUMENT_ROOT'].'/'.$sPosibleRuta.'*.css');
        if (is_array($listaArchivos) && count($listaArchivos) > 0) {
            foreach ($listaArchivos as $sRutaArchivo) {
                $sRutaURL = substr($sRutaArchivo, strlen($_SERVER['DOCUMENT_ROOT']) + 1);
                if ($bHayHeaderModulo) {
                    $sIncluirModulo .= "<link rel=\"stylesheet\" href='$sRutaURL' />\n";
                } else {
                    $listaRutas[] = array('css', $sRutaURL);
                }
            }
            break;
        }
    }
    if ($sIncluir != '') $smarty->assign('HEADER_LIBS_JQUERY', $sIncluir);
    if ($sIncluirModulo != '') $smarty->assign('HEADER_MODULES', $sIncluirModulo);
    $smarty->assign('LISTA_JQUERY_CSS', $listaRutas);
}

/**
 * Procedimiento para interrogar si el framework contiene soporte de mostrar el
 * título del formulario como parte de la plantilla del framework. Esta 
 * verificación es necesaria para evitar mostrar títulos duplicados en los 
 * formularios
 * 
 * @return bool VERDADERO si el soporte existe, FALSO si no.
 */
function existeSoporteTituloFramework()
{
	global $arrConf;
    
    if (!isset($arrConf['mainTheme'])) return FALSE;
    $bExisteSoporteTitulo = FALSE;
    foreach (array(
        "themes/{$arrConf['mainTheme']}/_common/index.tpl",
        "themes/{$arrConf['mainTheme']}/_common/_menu.tpl",
    ) as $sArchivo) {
    	$h = fopen($sArchivo, 'r');
        if ($h) {
            while (!feof($h)) {
            	if (strpos(fgets($h), '$title') !== FALSE) {
            		$bExisteSoporteTitulo = TRUE;
                    break;
            	}
            }
        	fclose($h);
        }
        if ($bExisteSoporteTitulo) return $bExisteSoporteTitulo;
    }
    return $bExisteSoporteTitulo;
}
?>