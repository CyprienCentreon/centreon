<?php

/*
 * Copyright 2005-2015 Centreon
 * Centreon is developped by : Julien Mathis and Romain Le Merlus under
 * GPL Licence 2.0.
 *
 * This program is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License as published by the Free Software
 * Foundation ; either version 2 of the License.
 *
 * This program is distributed in the hope that it will be useful, but WITHOUT ANY
 * WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A
 * PARTICULAR PURPOSE. See the GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License along with
 * this program; if not, see <http://www.gnu.org/licenses>.
 *
 * Linking this program statically or dynamically with other modules is making a
 * combined work based on this program. Thus, the terms and conditions of the GNU
 * General Public License cover the whole combination.
 *
 * As a special exception, the copyright holders of this program give Centreon
 * permission to link this program with independent modules to produce an executable,
 * regardless of the license terms of these independent modules, and to copy and
 * distribute the resulting executable under terms of Centreon choice, provided that
 * Centreon also meet, for each linked independent module, the terms  and conditions
 * of the license of that module. An independent module is a module which is not
 * derived from this program. If you modify this program, you may extend this
 * exception to your version of the program, but you are not obliged to do so. If you
 * do not wish to do so, delete this exception statement from your version.
 *
 * For more information : contact@centreon.com
 *
 * SVN : $URL$
 * SVN : $Id$
 *
 */
require_once $centreon_path . 'www/class/centreonInstance.class.php';

/**
 *  Class that contains various methods for managing services
 */
class CentreonService
{
    /**
     *
     * @var type 
     */
    protected $db;
    
    /**
     *
     * @var type 
     */
    protected $instanceObj;

    /**
     *  Constructor
     *
     *  @param CentreonDB $db
     */
    public function __construct($db)
    {
        $this->db = $db;
        $this->instanceObj = new CentreonInstance($db);
    }

    /**
     *  Method that returns service description from service_id
     *
     *  @param int $svc_id
     *  @return string
     */
    public function getServiceDesc($svc_id)
    {
        static $svcTab = null;

        if (is_null($svcTab)) {
            $svcTab = array();

            $rq = "SELECT service_id, service_description
     			   FROM service";
            $res = $this->db->query($rq);
            while ($row = $res->fetchRow()) {
                $svcTab[$row['service_id']] = $row['service_description'];
            }
        }
        if (isset($svcTab[$svc_id])) {
            return $svcTab[$svc_id];
        }
        return null;
    }

    /**
     * Get Service Template ID
     * 
     * @param string $templateName
     * @return int
     */
    public function getServiceTemplateId($templateName = null)
    {
        if (is_null($templateName)) {
            return null;
        }
        $res = $this->db->query(
                "SELECT service_id 
                 FROM service
                 WHERE service_description = '" . $this->db->escape($templateName) . "' 
                    AND service_register = '0'"
        );
        if (!$res->numRows()) {
            return null;
        }
        $row = $res->fetchRow();
        return $row['service_id'];
    }

    /**
     *  Method that returns the id of a service
     *
     *  @param string $svc_desc
     *  @param string $host_name
     *  @return int
     */
    public function getServiceId($svc_desc = null, $host_name)
    {
        static $hostSvcTab = array();

        if (!isset($hostSvcTab[$host_name])) {
            $rq = "SELECT s.service_id, s.service_description " .
                    " FROM service s" .
                    " JOIN (SELECT hsr.service_service_id FROM host_service_relation hsr" .
                    " JOIN host h" .
                    "     ON hsr.host_host_id = h.host_id" .
                    "     	WHERE h.host_name = '" . $this->db->escape($host_name) . "'" .
                    "     UNION" .
                    "    	 SELECT hsr.service_service_id FROM hostgroup_relation hgr" .
                    " JOIN host h" .
                    "     ON hgr.host_host_id = h.host_id" .
                    " JOIN host_service_relation hsr" .
                    "     ON hgr.hostgroup_hg_id = hsr.hostgroup_hg_id" .
                    "     	WHERE h.host_name = '" . $this->db->escape($host_name) . "' ) ghsrv" .
                    " ON s.service_id = ghsrv.service_service_id";
            $DBRES = $this->db->query($rq);
            $hostSvcTab[$host_name] = array();
            while ($row = $DBRES->fetchRow()) {
                $hostSvcTab[$host_name][$row['service_description']] = $row['service_id'];
            }
        }
        if (!isset($svc_desc) && isset($hostSvcTab[$host_name])) {
            return $hostSvcTab[$host_name];
        }
        if (isset($hostSvcTab[$host_name]) && isset($hostSvcTab[$host_name][$svc_desc])) {
            return $hostSvcTab[$host_name][$svc_desc];
        }
        return null;
    }

    /**
     * Get Service Id From Hostgroup Name
     *
     * @param string $service_desc
     * @param string $hgName
     * @return int
     */
    public function getServiceIdFromHgName($service_desc, $hgName)
    {
        static $hgSvcTab = array();

        if (!isset($hgSvcTab[$hgName])) {
            $rq = "SELECT hsr.service_service_id, s.service_description
            		FROM host_service_relation hsr, hostgroup hg, service s
            		WHERE hsr.hostgroup_hg_id = hg.hg_id
        			AND hsr.service_service_id = s.service_id
            		AND hg.hg_name LIKE '" . $this->db->escape($hgName) . "' ";
            $res = $this->db->query($rq);
            while ($row = $res->fetchRow()) {
                $hgSvcTab[$hgName][$row['service_description']] = $row['service_service_id'];
            }
        }
        if (isset($hgSvcTab[$hgName]) && isset($hgSvcTab[$hgName][$service_desc])) {
            return $hgSvcTab[$hgName][$service_desc];
        }
        return null;
    }

    /**
     * Get Service alias
     *
     * @param int $sid
     * @return string
     */
    public function getServiceName($sid)
    {
        static $svcTab = array();

        if (!isset($svcTab[$sid])) {
            $query = "SELECT service_alias
     				  FROM service
     				  WHERE service_id = " . $this->db->escape($sid);
            $res = $this->db->query($query);
            if ($res->numRows()) {
                $row = $res->fetchRow();
                $svcTab[$sid] = $row['service_alias'];
            }
        }
        if (isset($svcTab[$sid])) {
            return $svcTab[$sid];
        }
        return null;
    }

    /**
     * Check illegal char defined into nagios.cfg file
     *
     * @param string $name
     * @return string
     */
    public function checkIllegalChar($name)
    {
        $DBRESULT = $this->db->query("SELECT illegal_object_name_chars FROM cfg_nagios");
        while ($data = $DBRESULT->fetchRow()) {
            $tab = str_split(html_entity_decode($data['illegal_object_name_chars'], ENT_QUOTES, "UTF-8"));
            foreach ($tab as $char) {
                $name = str_replace($char, "", $name);
            }
        }
        $DBRESULT->free();
        return $name;
    }

    /**
     *  Returns a string that replaces on demand macros by their values
     *
     *  @param int $svc_id
     *  @param string $string
     *  @param int $antiLoop
     *  @param int $instanceId
     *  @return string
     */
    public function replaceMacroInString($svc_id, $string, $antiLoop = null, $instanceId = null)
    {
        $rq = "SELECT service_register FROM service WHERE service_id = '" . $svc_id . "' LIMIT 1";
        $DBRES = $this->db->query($rq);
        if (!$DBRES->numRows())
            return $string;
        $row = $DBRES->fetchRow();

        /*
         * replace if not template
         */
        if ($row['service_register'] == 1) {
            if (preg_match('/\$SERVICEDESC\$/', $string)) {
                $string = str_replace("\$SERVICEDESC\$", $this->getServiceDesc($svc_id), $string);
            }
            if (!is_null($instanceId) && preg_match("\$INSTANCENAME\$", $string)) {
                $string = str_replace("\$INSTANCENAME\$", $this->instanceObj->getParam($instanceId, 'name'), $string);
            }
            if (!is_null($instanceId) && preg_match("\$INSTANCEADDRESS\$", $string)) {
                $string = str_replace("\$INSTANCEADDRESS\$", $this->instanceObj->getParam($instanceId, 'ns_ip_address'), $string);
            }
        }
        $matches = array();
        $pattern = '|(\$_SERVICE[0-9a-zA-Z\_\-]+\$)|';
        preg_match_all($pattern, $string, $matches);
        $i = 0;
        while (isset($matches[1][$i])) {
            $rq = "SELECT svc_macro_value FROM on_demand_macro_service WHERE svc_svc_id = '" . $svc_id . "' AND svc_macro_name LIKE '" . $matches[1][$i] . "'";
            $DBRES = $this->db->query($rq);
            while ($row = $DBRES->fetchRow()) {
                $string = str_replace($matches[1][$i], $row['svc_macro_value'], $string);
            }
            $i++;
        }
        if ($i) {
            $rq2 = "SELECT service_template_model_stm_id FROM service WHERE service_id = '" . $svc_id . "'";
            $DBRES2 = $this->db->query($rq2);
            while ($row2 = $DBRES2->fetchRow()) {
                if (!isset($antiLoop) || !$antiLoop) {
                    $string = $this->replaceMacroInString($row2['service_template_model_stm_id'], $string, $row2['service_template_model_stm_id']);
                } elseif ($row2['service_template_model_stm_id'] != $antiLoop) {
                    $string = $this->replaceMacroInString($row2['service_template_model_stm_id'], $string, $antiLoop);
                }
            }
        }
        return $string;
    }

    /**
     * Get list of service templates
     * 
     * @return array 
     */
    public function getServiceTemplateList()
    {
        $res = $this->db->query("SELECT service_id, service_description 
                            FROM service
                            WHERE service_register = '0'
                            ORDER BY service_description");
        $list = array();
        while ($row = $res->fetchRow()) {
            $list[$row['service_id']] = $row['service_description'];
        }
        return $list;
    }

    /* public function getServiceTemplateTree($serviceId,$macros = null){
      $res = $this->db->query("SELECT s.service_id, s.service_template_model_stm_id
      FROM service s
      WHERE s.service_id = ".$this->db->escape($serviceId)."
      ");
      $service = array();
      while ($row = $res->fetchRow()) {
      $service['service_id'] = $row['service_id'];
      $service['macros'] = $this->getCustomMacro($row['service_id']);
      if(!is_null($row['service_template_model_stm_id'])){
      $service['parentTpl'] = $this->getServiceTemplateTree($row['service_template_model_stm_id']);
      }
      }
      return $service;
      }

      public function getMacrosFromService($serviceId){
      $res = $this->db->query("SELECT svc_macro_name, svc_macro_value, is_password
      FROM on_demand_macro_service
      WHERE svc_svc_id = " .
      $this->db->escape($serviceId));
      $macroArray = array();
      while ($row = $res->fetchRow()) {
      $arr = array();
      if (preg_match('/\$_SERVICE(.*)\$$/', $row['svc_macro_name'], $matches)) {
      $arr['name'] = $matches[1];
      $arr['value'] = $row['svc_macro_value'];
      $arr['password'] = $row['is_password'] ? 1 : NULL;
      $macroArray[] = $arr;
      }
      }
      } */

    /**
     * Insert macro
     * 
     * @param int $serviceId
     * @param array $macroInput
     * @param array $macroValue
     * @param array $macroPassword
     * @param array $macroDescription
     * @param bool $isMassiveChange
     * @return void
     */
    public function insertMacro(
        $serviceId,
        $macroInput = array(),
        $macroValue = array(),
        $macroPassword = array(),
        $macroDescription = array(),
        $isMassiveChange = false,
        $cmdId = false
    ) {
        if (false === $isMassiveChange) {
            $this->db->query("DELETE FROM on_demand_macro_service
                    WHERE svc_svc_id = " . $this->db->escape($serviceId)
            );
        } else {
            $macroList = "";
            foreach ($macroInput as $v) {
                $macroList .= "'\$_SERVICE" . strtoupper($this->db->escape($v)) . "\$',";
            }
            if ($macroList) {
                $macroList = rtrim($macroList, ",");
                $this->db->query("DELETE FROM on_demand_macro_service
                         WHERE svc_svc_id = " . $this->db->escape($serviceId) . "
                         AND svc_macro_name IN ({$macroList})"
                );
            }
        }

        $macros = $macroInput;
        $macrovalues = $macroValue;
        
        $this->hasMacroFromServiceChanged($this->db,$serviceId,$macros,$macrovalues,$cmdId);
        
        $stored = array();
        $cnt = 0;
        foreach ($macros as $key => $value) {
            if ($value != "" &&
                    !isset($stored[strtolower($value)])) {
                $this->db->query("INSERT INTO on_demand_macro_service (`svc_macro_name`, `svc_macro_value`, `is_password`, `description`, `svc_svc_id`, `macro_order`) 
                                VALUES ('\$_SERVICE" . strtoupper($this->db->escape($value)) . "\$', '" . $this->db->escape($macrovalues[$key]) . "', " . (isset($macroPassword[$key]) ? 1 : 'NULL') . ", '" . $this->db->escape($macroDescription[$key]) . "', " . $this->db->escape($serviceId) . ", " . $cnt . " )");
                $stored[strtolower($value)] = true;
                $cnt ++;
            }
        }
    }

    /**
     * 
     * @param integer $serviceId
     * @param array $template
     * @return array
     */
    public function getCustomMacroInDb($serviceId = null, $template = null)
    {
        $arr = array();
        $i = 0;
        if ($serviceId) {
            $res = $this->db->query("SELECT svc_macro_name, svc_macro_value, is_password, description
                                FROM on_demand_macro_service
                                WHERE svc_svc_id = " .
                    $this->db->escape($serviceId) . "
                                ORDER BY macro_order ASC");
            while ($row = $res->fetchRow()) {
                if (preg_match('/\$_SERVICE(.*)\$$/', $row['svc_macro_name'], $matches)) {
                    $arr[$i]['macroInput_#index#'] = $matches[1];
                    $arr[$i]['macroValue_#index#'] = $row['svc_macro_value'];
                    $arr[$i]['macroPassword_#index#'] = $row['is_password'] ? 1 : NULL;
                    $arr[$i]['macroDescription_#index#'] = $row['description'];
                    $arr[$i]['macroDescription'] = $row['description'];
                    if(!is_null($template)){
                        $arr[$i]['macroTpl_#index#'] = $template['service_description'];
                    }
                    $i++;
                }
            }
        }
        return $arr;
    }
    
    
    /**
     * Get service custom macro
     * 
     * @param int $serviceId
     * @return array
     */
    public function getCustomMacro($serviceId = null, $realKeys = false)
    {
        $arr = array();
        $i = 0;
        if (!isset($_REQUEST['macroInput']) && $serviceId) {
            $res = $this->db->query("SELECT svc_macro_name, svc_macro_value, is_password, description
                                FROM on_demand_macro_service
                                WHERE svc_svc_id = " .
                    $this->db->escape($serviceId) . "
                                ORDER BY macro_order ASC");
            while ($row = $res->fetchRow()) {
                if (preg_match('/\$_SERVICE(.*)\$$/', $row['svc_macro_name'], $matches)) {
                    $arr[$i]['macroInput_#index#'] = $matches[1];
                    $arr[$i]['macroValue_#index#'] = $row['svc_macro_value'];
                    $arr[$i]['macroPassword_#index#'] = $row['is_password'] ? 1 : NULL;
                    $arr[$i]['macroDescription_#index#'] = $row['description'];
                    $arr[$i]['macroDescription'] = $row['description'];
                    $i++;
                }
            }
        } elseif (isset($_REQUEST['macroInput'])) {
            foreach ($_REQUEST['macroInput'] as $key => $val) {
                $index = $i;
                if($realKeys){
                    $index = $key;
                }
                $arr[$index]['macroInput_#index#'] = $val;
                $arr[$index]['macroValue_#index#'] = $_REQUEST['macroValue'][$key];
                $arr[$index]['macroPassword_#index#'] = isset($_REQUEST['is_password'][$key]) ? 1 : NULL;                
                $arr[$index]['macroDescription_#index#'] = isset($_REQUEST['description'][$key]) ? $_REQUEST['description'][$key] : NULL;
                $arr[$index]['macroDescription'] = isset($_REQUEST['description'][$key]) ? $_REQUEST['description'][$key] : NULL;
                $i++;
            }
        }
        return $arr;
    }

    /**
     * Returns array of locked templates
     * 
     * @return array
     */
    public function getLockedServiceTemplates()
    {
        static $arr = null;

        if (is_null($arr)) {
            $arr = array();
            $res = $this->db->query("SELECT service_id 
                    FROM service 
                    WHERE service_locked = 1");
            while ($row = $res->fetchRow()) {
                $arr[$row['service_id']] = true;
            }
        }
        return $arr;
    }

    /**
     * Clean up service relations (services by hostgroup)
     * 
     * @param string $table
     * @param string $host_id_field
     * @param string $service_id_field
     * @return void
     */
    public function cleanServiceRelations($table = "", $host_id_field = "", $service_id_field = "")
    {
        $sql = "DELETE FROM {$table}
                    WHERE NOT EXISTS ( 
                        SELECT hsr1.host_host_id 
                        FROM host_service_relation hsr1
                        WHERE hsr1.host_host_id = {$table}.{$host_id_field}
                        AND hsr1.service_service_id = {$table}.{$service_id_field}
                    )
                    AND NOT EXISTS (
                        SELECT hsr2.host_host_id 
                        FROM host_service_relation hsr2, hostgroup_relation hgr
                        WHERE hsr2.host_host_id = hgr.host_host_id
                        AND hgr.host_host_id = {$table}.{$host_id_field}
                        AND hsr2.service_service_id = {$table}.{$service_id_field}
                    )";
        $this->db->query($sql);
    }

    /**
     * @param array $service
     * @param int $type | 0 = contact, 1 = contactgroup
     * @param array $cgSCache
     * @param array $cctSCache
     * @return bool
     */
    public function serviceHasContact($service, $type = 0, $cgSCache, $cctSCache)
    {
        static $serviceTemplateHasContactGroup = array();
        static $serviceTemplateHasContact = array();

        if ($type == 0) {
            $staticArr = & $serviceTemplateHasContact;
            $cache = $cctSCache;
        } else {
            $staticArr = & $serviceTemplateHasContactGroup;
            $cache = $cgSCache;
        }

        if (isset($cache[$service['service_id']])) {
            return true;
        }
        while (isset($service['service_template_model_stm_id']) && $service['service_template_model_stm_id']) {
            $serviceId = $service['service_template_model_stm_id'];
            if (isset($cache[$serviceId]) || isset($staticArr[$serviceId])) {
                $staticArr[$serviceId] = true;
                return true;
            }
            $res = $this->db->query("SELECT service_template_model_stm_id 
			   	    FROM service 
				    WHERE service_id = {$serviceId}"
            );
            $service = $res->fetchRow();
        }
        return false;
    }
    
    /**
     * 
     * @param type $pearDB
     * @param integer $service_id
     * @param string $macroInput
     * @param string $macroValue
     * @param boolean $cmdId
     */
    public function hasMacroFromServiceChanged($pearDB, $service_id, &$macroInput, &$macroValue, $cmdId = false)
    {
        $aListTemplate = getListTemplates($pearDB, $service_id);
        
        if (!isset($cmdId)) {
            $cmdId = "";
        }
        $aMacros = $this->getMacros($service_id, $aListTemplate, $cmdId);
        foreach($aMacros as $macro){
            foreach($macroInput as $ind=>$input){
                if($input == $macro['macroInput_#index#'] && $macroValue[$ind] == $macro["macroValue_#index#"]){
                    unset($macroInput[$ind]);
                    unset($macroValue[$ind]);
                }
            }
        }
    }
    
    
    /**
     * This method get the macro attached to the service
     * 
     * @param int $iServiceId
     * @param array $aListTemplate
     * @param int $iIdCommande
     * 
     * @return array
     */
    public function getMacros($iServiceId, $aListTemplate, $iIdCommande)
    {
        
        $aMacro = array();
        $macroArray = array();
        $aMacroInService = array();
        
        //Get macro attached to the service
        $macroArray = $this->getCustomMacroInDb($iServiceId);
        $iNb = count($macroArray);

        //Get macro attached to the template
        $aMacroTemplate = array();
        
        foreach ($aListTemplate as $template) {
            if (!empty($template)) {
                $aMacroTemplate[] = $this->getCustomMacroInDb($template['service_template_model_stm_id'],$template);
            }
        }
        //Get macro attached to the command        
        if (!empty($iIdCommande)) {
            $oCommand = new CentreonCommand($this->db);
            $aMacroInService[] = $oCommand->getMacroByIdAndType($iIdCommande, 'service');
        }
        
        

        //filter a macro
        $aTempMacro = array();
        $serv = current($aMacroInService);
        if (count($aMacroInService) > 0) {
            for ($i = 0; $i < count($serv); $i++) {
                $serv[$i]['macroOldValue_#index#'] = $serv[$i]["macroValue_#index#"];
                $serv[$i]['macroFrom_#index#'] = 'fromService';
                $serv[$i]['source'] = 'fromService';
                $aTempMacro[] = $serv[$i];
            }
        }
        
        if (count($aMacroTemplate) > 0) {  
            foreach ($aMacroTemplate as $key => $macr) {
                foreach ($macr as $mm) {
                    $mm['macroOldValue_#index#'] = $mm["macroValue_#index#"];
                    $mm['macroFrom_#index#'] = 'fromTpl';
                    $mm['source'] = 'fromTpl';
                    $aTempMacro[] = $mm;
                }
            }
        }
        
        if (count($macroArray) > 0) {
            foreach($macroArray as $directMacro){
                $directMacro['macroOldValue_#index#'] = $directMacro["macroValue_#index#"];
                $directMacro['macroFrom_#index#'] = 'direct';
                $directMacro['source'] = 'direct';
                $aTempMacro[] = $directMacro;
            }
        }
        
        


        $aFinalMacro = $this->macro_unique($aTempMacro);
        
        return $aFinalMacro;
    }

    public function purgeOldMacroToForm(&$macroArray,&$form,$fromKey,$macrosArrayToCompare = null){
        
        
        if(isset($form["macroInput"]["#index#"])){
            unset($form["macroInput"]["#index#"]); 
        }
        if(isset($form["macroValue"]["#index#"])){
            unset($form["macroValue"]["#index#"]); 
        }

        
        
        
        foreach($macroArray as $key=>$macro){
            if($macro["macroInput_#index#"] == ""){
                unset($macroArray[$key]);
            }
        }
        
        if(is_null($macrosArrayToCompare)){
            foreach($macroArray as $key=>$macro){
                if($form['macroFrom'][$key] == $fromKey){
                    unset($macroArray[$key]);
                }
            }
        }else{
            $inputIndexArray = array();
            foreach($macrosArrayToCompare as $tocompare){
                if (isset($tocompare['macroInput_#index#'])) {
                    $inputIndexArray[] = $tocompare['macroInput_#index#'];
                }
            }
            foreach($macroArray as $key=>$macro){
                if($form['macroFrom'][$key] == $fromKey){
                    if(!in_array($macro['macroInput_#index#'],$inputIndexArray)){
                        unset($macroArray[$key]);
                    }
                }
            }
        }
    }
    
    
    
    /**
     * 
     * @param integer $field
     * @return array
     */
    public static function getDefaultValuesParameters($field)
    {
        $parameters = array();
        $parameters['currentObject']['table'] = 'service';
        $parameters['currentObject']['id'] = 'service_id';
        $parameters['currentObject']['name'] = 'service_description';
        $parameters['currentObject']['comparator'] = 'service_id';

        switch ($field) {
            case 'timeperiod_tp_id':
            case 'timeperiod_tp_id2':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'timeperiod';
                $parameters['externalObject']['id'] = 'tp_id';
                $parameters['externalObject']['name'] = 'tp_name';
                $parameters['externalObject']['comparator'] = 'tp_id';
                break;
            case 'command_command_id':
            case 'command_command_id2':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'command';
                $parameters['externalObject']['id'] = 'command_id';
                $parameters['externalObject']['name'] = 'command_name';
                $parameters['externalObject']['comparator'] = 'command_id';
                break;
            case 'service_template_model_stm_id':
                $parameters['type'] = 'simple';
                $parameters['externalObject']['table'] = 'service';
                $parameters['externalObject']['id'] = 'service_id';
                $parameters['externalObject']['name'] = 'service_description';
                $parameters['externalObject']['comparator'] = 'service_id';
                break;
            case 'service_cs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'contact';
                $parameters['externalObject']['id'] = 'contact_id';
                $parameters['externalObject']['name'] = 'contact_name';
                $parameters['externalObject']['comparator'] = 'contact_id';
                $parameters['relationObject']['table'] = 'contact_service_relation';
                $parameters['relationObject']['field'] = 'contact_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_cgs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'contactgroup';
                $parameters['externalObject']['id'] = 'cg_id';
                $parameters['externalObject']['name'] = 'cg_name';
                $parameters['externalObject']['comparator'] = 'cg_id';
                $parameters['relationObject']['table'] = 'contactgroup_service_relation';
                $parameters['relationObject']['field'] = 'contactgroup_cg_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_hPars':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'host';
                $parameters['externalObject']['id'] = 'host_id';
                $parameters['externalObject']['name'] = 'host_name';
                $parameters['externalObject']['comparator'] = 'host_id';
                $parameters['relationObject']['table'] = 'host_service_relation';
                $parameters['relationObject']['field'] = 'host_host_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_hgPars':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'hostgroup';
                $parameters['externalObject']['id'] = 'hg_id';
                $parameters['externalObject']['name'] = 'hg_name';
                $parameters['externalObject']['comparator'] = 'hg_id';
                $parameters['relationObject']['table'] = 'host_service_relation';
                $parameters['relationObject']['field'] = 'hostgroup_hg_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_sgs':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'servicegroup';
                $parameters['externalObject']['id'] = 'sg_id';
                $parameters['externalObject']['name'] = 'sg_name';
                $parameters['externalObject']['comparator'] = 'sg_id';
                $parameters['relationObject']['table'] = 'servicegroup_relation';
                $parameters['relationObject']['field'] = 'servicegroup_sg_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_traps':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'traps';
                $parameters['externalObject']['id'] = 'traps_id';
                $parameters['externalObject']['name'] = 'traps_name';
                $parameters['externalObject']['comparator'] = 'traps_id';
                $parameters['relationObject']['table'] = 'traps_service_relation';
                $parameters['relationObject']['field'] = 'traps_id';
                $parameters['relationObject']['comparator'] = 'service_id';
                break;
            case 'graph_id':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'giv_graphs_template';
                $parameters['externalObject']['id'] = 'graph_id';
                $parameters['externalObject']['name'] = 'name';
                $parameters['externalObject']['comparator'] = 'graph_id';
                $parameters['relationObject']['table'] = 'extended_service_information';
                $parameters['relationObject']['field'] = 'graph_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
            case 'service_categories':
                $parameters['type'] = 'relation';
                $parameters['externalObject']['table'] = 'service_categories';
                $parameters['externalObject']['id'] = 'sc_id';
                $parameters['externalObject']['name'] = 'sc_name';
                $parameters['externalObject']['comparator'] = 'sc_id';
                $parameters['relationObject']['table'] = 'service_categories_relation';
                $parameters['relationObject']['field'] = 'sc_id';
                $parameters['relationObject']['comparator'] = 'service_service_id';
                break;
        }
        
        return $parameters;
    }
    
    /**
     * 
     * @param type $values
     * @return type
     */
    public function getObjectForSelect2($values = array())
    {
        $selectedServices = '';
        $explodedValues = implode(',', $values);
        if (empty($explodedValues)) {
            $explodedValues = "''";
        } else {
            $selectedServices .= "AND hsr.service_service_id IN ($explodedValues) ";
        }
        
        $queryService = "SELECT DISTINCT s.service_description, s.service_id, h.host_name, h.host_id "
            . "FROM host h, service s, host_service_relation hsr "
            . 'WHERE hsr.host_host_id = h.host_id '
            . "AND hsr.service_service_id = s.service_id "
            . "AND h.host_register = '1' AND s.service_register = '1' "
            . $selectedServices
            . "ORDER BY h.host_name";
        
        $DBRESULT = $this->db->query($queryService);
        
        $serviceList = array();
        while ($data = $DBRESULT->fetchRow()) {
            $serviceCompleteName = $data['host_name'] . ' - ' . $data['service_description'];
            $serviceCompleteId = $data['host_id'] . '-' . $data['service_id'];
            
            $serviceList[] = array('id' => htmlentities($serviceCompleteId), 'text' => htmlentities($serviceCompleteName));
        }
        
        return $serviceList;
    }
    
    public function ajaxMacroControl($form){

        $macroArray = $this->getCustomMacro(null,true);
        $this->purgeOldMacroToForm(&$macroArray,&$form,'fromTpl');
        $aListTemplate = array_merge(
                        getListTemplates($this->db, $form['service_template_model_stm_id']),array(array('service_template_model_stm_id' => $form['service_template_model_stm_id'])));
        
        //Get macro attached to the template
        $aMacroTemplate = array();
        
        foreach ($aListTemplate as $template) {
            if (!empty($template)) {
                $aMacroTemplate[] = $this->getCustomMacroInDb($template['service_template_model_stm_id'],$template);
            }
        }
        
        $iIdCommande = $form['command_command_id'];
        //Get macro attached to the command        
        if (!empty($iIdCommande)) {
            $oCommand = new CentreonCommand($this->db);
            $aMacroInService[] = $oCommand->getMacroByIdAndType($iIdCommande, 'service');
        }

        $this->purgeOldMacroToForm(&$macroArray,&$form,'fromService',$aMacroInService);
        
        
        //filter a macro
        $aTempMacro = array();
        
        $serv = current($aMacroInService);
        if (count($aMacroInService) > 0) {
            for ($i = 0; $i < count($serv); $i++) {
                $serv[$i]['macroOldValue_#index#'] = $serv[$i]["macroValue_#index#"];
                $serv[$i]['macroFrom_#index#'] = 'fromService';
                $serv[$i]['source'] = 'fromService';
                $aTempMacro[] = $serv[$i];
            }
        }
        
        if (count($aMacroTemplate) > 0) {  
            foreach ($aMacroTemplate as $key => $macr) {
                foreach ($macr as $mm) {
                    $mm['macroOldValue_#index#'] = $mm["macroValue_#index#"];
                    $mm['macroFrom_#index#'] = 'fromTpl';
                    $mm['source'] = 'fromTpl';
                    $aTempMacro[] = $mm;
                }
            }
        }
        
        if (count($macroArray) > 0) {
            foreach($macroArray as $key => $directMacro){
                $directMacro['macroOldValue_#index#'] = $directMacro["macroValue_#index#"];
                $directMacro['macroFrom_#index#'] = $form['macroFrom'][$key];
                $directMacro['source'] = 'direct';
                $aTempMacro[] = $directMacro;
            }
        }
        
        $aFinalMacro = $this->macro_unique($aTempMacro);
        
        return $aFinalMacro;
    }
    
        /**
     * This method remove duplicate macro by her name
     * 
     * @param array $aTempMacro
     * @return array
     */
    function macro_unique($aTempMacro)
    {
        $aFinalMacro = array();
        
        
        $x = 0;
        foreach($aTempMacro as $keyTmp=>$TempMacro){
            $sInput = $TempMacro['macroInput_#index#'];
            $existe = null;
            if (count($aFinalMacro) > 0) {
                foreach($aFinalMacro as $keyFinal=>$FinalMacro){
                //for ($j = 0; $j < count($aFinalMacro); $j++ ) 
                    if ($FinalMacro['macroInput_#index#'] == $sInput) {
                        
                        //store the template value when it is overloaded with direct macro
                        if(isset($FinalMacro['source']) 
                        && $FinalMacro['source'] == 'fromTpl' 
                        && $TempMacro['source'] == "direct"){    
                            $TempMacro['macroTplValue_#index#'] = $FinalMacro['macroValue_#index#'];
                            $TempMacro['macroTplValToDisplay_#index#'] = 1;
                        }else{
                            $TempMacro['macroTplValue_#index#'] = "";
                            $TempMacro['macroTplValToDisplay_#index#'] = 0;
                        }
                        //
                        
                        $existe = $keyFinal;
                    }
                }
                if (is_null($existe)) {
                    $aFinalMacro[] = $TempMacro;
                } else {
                    $aFinalMacro[$existe] = $TempMacro;
                }
            } else {
                $aFinalMacro[] = $TempMacro;
            }
        }
        
        return $aFinalMacro;
    }
    
    
    
    
    /**
     * 
     * @param type $ret
     * @return type
     */
    public function insert($ret)
    {
        $ret["service_description"] = $this->checkIllegalChar($ret["service_description"]);

        if (isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != null)		{
            $ret["command_command_id_arg2"] = str_replace("\n", "//BR//", $ret["command_command_id_arg2"]);
            $ret["command_command_id_arg2"] = str_replace("\t", "//T//", $ret["command_command_id_arg2"]);
            $ret["command_command_id_arg2"] = str_replace("\r", "//R//", $ret["command_command_id_arg2"]);
        }
        $rq = "INSERT INTO service " .
            "(service_template_model_stm_id, command_command_id, timeperiod_tp_id, command_command_id2, timeperiod_tp_id2, " .
            "service_description, service_alias, service_is_volatile, service_max_check_attempts, service_normal_check_interval, " .
            "service_retry_check_interval, service_active_checks_enabled, " .
            "service_passive_checks_enabled, service_obsess_over_service, service_check_freshness, service_freshness_threshold, " .
            "service_event_handler_enabled, service_low_flap_threshold, service_high_flap_threshold, service_flap_detection_enabled, " .
            "service_process_perf_data, service_retain_status_information, service_retain_nonstatus_information, service_notification_interval, " .
            "service_notification_options, service_notifications_enabled, contact_additive_inheritance, cg_additive_inheritance, service_inherit_contacts_from_host, service_stalking_options, service_first_notification_delay ,service_comment, command_command_id_arg, command_command_id_arg2, " .
            "service_register, service_activate) " .
            "VALUES ( ";
        isset($ret["service_template_model_stm_id"]) && $ret["service_template_model_stm_id"] != NULL ? $rq .= "'".$ret["service_template_model_stm_id"]."', ": $rq .= "NULL, ";
        isset($ret["command_command_id"]) && $ret["command_command_id"] != NULL ? $rq .= "'".$ret["command_command_id"]."', ": $rq .= "NULL, ";
        isset($ret["timeperiod_tp_id"]) && $ret["timeperiod_tp_id"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id"]."', ": $rq .= "NULL, ";
        isset($ret["command_command_id2"]) && $ret["command_command_id2"] != NULL ? $rq .= "'".$ret["command_command_id2"]."', ": $rq .= "NULL, ";
        isset($ret["timeperiod_tp_id2"]) && $ret["timeperiod_tp_id2"] != NULL ? $rq .= "'".$ret["timeperiod_tp_id2"]."', ": $rq .= "NULL, ";
        isset($ret["service_description"]) && $ret["service_description"] != NULL ? $rq .= "'".CentreonDB::escape($ret["service_description"])."', ": $rq .= "NULL, ";
        isset($ret["service_alias"]) && $ret["service_alias"] != NULL ? $rq .= "'".CentreonDB::escape($ret["service_alias"])."', ": $rq .= "NULL, ";
        isset($ret["service_is_volatile"]) && $ret["service_is_volatile"]["service_is_volatile"] != 2 ? $rq .= "'".$ret["service_is_volatile"]["service_is_volatile"]."', ": $rq .= "'2', ";
        isset($ret["service_max_check_attempts"]) && $ret["service_max_check_attempts"] != NULL ? $rq .= "'".$ret["service_max_check_attempts"]."', " : $rq .= "NULL, ";
        isset($ret["service_normal_check_interval"]) && $ret["service_normal_check_interval"] != NULL ? $rq .= "'".$ret["service_normal_check_interval"]."', ": $rq .= "NULL, ";
        isset($ret["service_retry_check_interval"]) && $ret["service_retry_check_interval"] != NULL ? $rq .= "'".$ret["service_retry_check_interval"]."', ": $rq .= "NULL, ";
        isset($ret["service_active_checks_enabled"]["service_active_checks_enabled"]) && $ret["service_active_checks_enabled"]["service_active_checks_enabled"] != 2 ? $rq .= "'".$ret["service_active_checks_enabled"]["service_active_checks_enabled"]."', ": $rq .= "'2', ";
        isset($ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]) && $ret["service_passive_checks_enabled"]["service_passive_checks_enabled"] != 2 ? $rq .= "'".$ret["service_passive_checks_enabled"]["service_passive_checks_enabled"]."', ": $rq .= "'2', ";
        isset($ret["service_obsess_over_service"]["service_obsess_over_service"]) && $ret["service_obsess_over_service"]["service_obsess_over_service"] != 2 ? $rq .= "'".$ret["service_obsess_over_service"]["service_obsess_over_service"]."', ": $rq .= "'2', ";
        isset($ret["service_check_freshness"]["service_check_freshness"]) && $ret["service_check_freshness"]["service_check_freshness"] != 2 ? $rq .= "'".$ret["service_check_freshness"]["service_check_freshness"]."', ": $rq .= "'2', ";
        isset($ret["service_freshness_threshold"]) && $ret["service_freshness_threshold"] != NULL ? $rq .= "'".$ret["service_freshness_threshold"]."', ": $rq .= "NULL, ";
        isset($ret["service_event_handler_enabled"]["service_event_handler_enabled"]) && $ret["service_event_handler_enabled"]["service_event_handler_enabled"] != 2 ? $rq .= "'".$ret["service_event_handler_enabled"]["service_event_handler_enabled"]."', ": $rq .= "'2', ";
        isset($ret["service_low_flap_threshold"]) && $ret["service_low_flap_threshold"] != NULL ? $rq .= "'".$ret["service_low_flap_threshold"]."', " : $rq .= "NULL, ";
        isset($ret["service_high_flap_threshold"]) && $ret["service_high_flap_threshold"] != NULL ? $rq .= "'".$ret["service_high_flap_threshold"]."', " : $rq .= "NULL, ";
        isset($ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]) && $ret["service_flap_detection_enabled"]["service_flap_detection_enabled"] != 2 ? $rq .= "'".$ret["service_flap_detection_enabled"]["service_flap_detection_enabled"]."', " : $rq .= "'2', ";
        isset($ret["service_process_perf_data"]["service_process_perf_data"]) && $ret["service_process_perf_data"]["service_process_perf_data"] != 2 ? $rq .= "'".$ret["service_process_perf_data"]["service_process_perf_data"]."', " : $rq .= "'2', ";
        isset($ret["service_retain_status_information"]["service_retain_status_information"]) && $ret["service_retain_status_information"]["service_retain_status_information"] != 2 ? $rq .= "'".$ret["service_retain_status_information"]["service_retain_status_information"]."', " : $rq .= "'2', ";
        isset($ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]) && $ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"] != 2 ? $rq .= "'".$ret["service_retain_nonstatus_information"]["service_retain_nonstatus_information"]."', " : $rq .= "'2', ";
        isset($ret["service_notification_interval"]) && $ret["service_notification_interval"] != NULL ? $rq .= "'".$ret["service_notification_interval"]."', " : $rq .= "NULL, ";
        isset($ret["service_notifOpts"]) && $ret["service_notifOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["service_notifOpts"]))."', " : $rq .= "NULL, ";
        isset($ret["service_notifications_enabled"]["service_notifications_enabled"]) && $ret["service_notifications_enabled"]["service_notifications_enabled"] != 2 ? $rq .= "'".$ret["service_notifications_enabled"]["service_notifications_enabled"]."', " : $rq .= "'2', ";
        $rq .= (isset($ret["contact_additive_inheritance"]) ? 1 : 0) . ', ';
        $rq .= (isset($ret["cg_additive_inheritance"]) ? 1 : 0) . ', ';
        isset($ret["service_inherit_contacts_from_host"]["service_inherit_contacts_from_host"]) && $ret["service_inherit_contacts_from_host"]["service_inherit_contacts_from_host"] != NULL ? $rq .= "'".$ret["service_inherit_contacts_from_host"]["service_inherit_contacts_from_host"]."', " : $rq .= "'NULL', ";
        isset($ret["service_stalOpts"]) && $ret["service_stalOpts"] != NULL ? $rq .= "'".implode(",", array_keys($ret["service_stalOpts"]))."', " : $rq .= "NULL, ";
        isset($ret["service_first_notification_delay"]) && $ret["service_first_notification_delay"] != NULL ? $rq .= "'".$ret["service_first_notification_delay"]."', " : $rq .= "NULL, ";

        isset($ret["service_comment"]) && $ret["service_comment"] != NULL ? $rq .= "'".CentreonDB::escape($ret["service_comment"])."', " : $rq .= "NULL, ";
        $ret['command_command_id_arg'] = $this->getCommandArgs($ret, $ret);
        isset($ret["command_command_id_arg"]) && $ret["command_command_id_arg"] != NULL ? $rq .= "'".CentreonDB::escape($ret["command_command_id_arg"])."', " : $rq .= "NULL, ";


        isset($ret["command_command_id_arg2"]) && $ret["command_command_id_arg2"] != NULL ? $rq .= "'".CentreonDB::escape($ret["command_command_id_arg2"])."', " : $rq .= "NULL, ";
        isset($ret["service_register"]) && $ret["service_register"] != NULL ? $rq .= "'".$ret["service_register"]."', " : $rq .= "NULL, ";
        isset($ret["service_activate"]["service_activate"]) && $ret["service_activate"]["service_activate"] != NULL ? $rq .= "'".$ret["service_activate"]["service_activate"]."'" : $rq .= "NULL";
        $rq .= ")";
        $DBRESULT = $this->db->query($rq);
        
        $DBRESULT   = $this->db->query("SELECT MAX(service_id) as id FROM service");
        $service_id = $DBRESULT->fetchRow();
        
        return $service_id['id'];
    
    }
    
    /**
     * 
     * @param type $aDatas
     * @return type
     */
    public function insertExtendInfo($aDatas)
    {
        
        if (empty($aDatas['service_service_id'])) {
            return;
        }
        $rq = "INSERT INTO extended_service_information ";
        $rq .= "(service_service_id, esi_notes, esi_notes_url, esi_action_url, esi_icon_image, esi_icon_image_alt, graph_id) ";
        $rq .= "VALUES ";
        $rq .= "('".$aDatas['service_service_id']."', ";
        $rq .= (isset($aDatas["esi_notes"]) ? "'" .CentreonDB::escape($aDatas["esi_notes"])."'" : NULL) . ", ";
        $rq .= (isset($aDatas["esi_notes_url"]) ? "'" .CentreonDB::escape($aDatas["esi_notes_url"])."'" : NULL) . ", ";
        $rq .= (isset($aDatas["esi_action_url"]) ? "'" .CentreonDB::escape($aDatas["esi_action_url"])."'" : NULL) . ", ";
        $rq .= (isset($aDatas["esi_icon_image"]) ? "'" .CentreonDB::escape($aDatas["esi_icon_image"])."'" : NULL) . ", ";
        $rq .= (isset($aDatas["esi_icon_image_alt"]) ? "'" .CentreonDB::escape($aDatas["esi_icon_image_alt"])."'" : NULL) . ", ";
        $rq .= (isset($aDatas["graph_id"]) ? CentreonDB::escape($aDatas["graph_id"]) : NULL) . " ";
        $rq .= ")";
        $DBRESULT = $this->db->query($rq);
    }
    
    /**
    * Returns the formatted string for command arguments
    *
    * @param $argArray
    * @return string
    */
   function getCommandArgs($argArray = array(), $conf = array())
   {
       if (isset($conf['command_command_id_arg'])) {
           return $conf['command_command_id_arg'];
       }
       $argTab = array();
       foreach ($argArray as $key => $value) {
           if (preg_match('/^ARG(\d+)/', $key, $matches)) {
               $argTab[$matches[1]] = $value;
               $argTab[$matches[1]] = str_replace("\n", "#BR#", $argTab[$matches[1]]);
               $argTab[$matches[1]] = str_replace("\t", "#T#", $argTab[$matches[1]]);
               $argTab[$matches[1]] = str_replace("\r", "#R#", $argTab[$matches[1]]);
           }
       }
       ksort($argTab);
       $str = "";
       foreach ($argTab as $val) {
           if ($val != "") {
               $str .= "!" . $val;
           }
       }
       if (!strlen($str)) {
           return null;
       }
       return $str;
   }
}

?>