<?php
class WorkspaceEPML {
	
	public $sessionID;
	public $filepath;	// e.g. workspace
	public $file;		// complete filepath e.g. workspace/workspace.epml
	public $filename = "workspace.epml";
        private $matchingID = 0;
	
	public $modelList = array();
        public $mappingFileList = array();
	
	public $sources = array();
	public $sourceAssignments = array();
	public $numSources = 0;
		
	public $epcs = array();
	public $numModels = 0;
	
	private $currentNodeID = 1;
	private $currentEpcID = 1;
	
	/**
	 * Constructor
	 */
	public function __construct($doLoadEPCs = true) {	
		$this->init($doLoadEPCs);
	}
	
	public function clear() {
		$_SESSION['numWorkspaceModels'] = 0;
		$_SESSION["modelsInWorkspace"] = array();
		unlink($this->file);
		//$this->init();
	}
		
	private function init($doLoadEPCs = true) {
		$this->sessionID = session_id();
		$this->filepath = Config::WORKSPACE_PATH."/".$this->sessionID;
		$this->file = $this->filepath."/".$this->filename;
		
		// create session directory
		if ( !is_dir($this->filepath) ) {
			mkdir($this->filepath, 0777, true);
			chmod($this->filepath, 0777);
		} 
		
		// create/load workspace epml
		if ( file_exists($this->file) ) {
			$content_file = file_get_contents($this->file);
			$xml = new SimpleXMLElement($content_file);
			$this->numModels = count($xml->xpath("//epc"));
			
			// load meta data
			$metaData = $xml->xpath("//rmm-workspace");
			$this->currentNodeID = intval($metaData[0]["maxNodeID"]);
			$this->currentEpcID = intval($metaData[0]["maxEpcID"]);
			
			// load source assignments
			foreach ( $xml->xpath("//source-assignment") as $assignment ) {
				$this->sourceAssignments[intval($assignment["modelID"])] = (string) $assignment["source"];
				if ( !in_array((string) $assignment["source"], $this->sources) ) array_push($this->sources, (string) $assignment["source"]);
			}
			
			$this->numSources = count($this->sources);
			$this->numModels = count($this->sourceAssignments);
			$_SESSION['numWorkspaceModels'] = $this->numModels;
			foreach ($xml->xpath("//epc") as $xml_epc) {
				$this->modelList[(string) $xml_epc["epcId"]] = htmlspecialchars($xml_epc["name"]);
			}
			
			if ( $doLoadEPCs ) $this->loadEPCs($xml);
		} else {
			// create workspace epml file
			$this->updateWorkspaceEPMLFile();
		}
	}
        
        public function createNewMatchingFile($fileName, $fileType){
            unset($this->mappingFileList);
            $this->mappingFileList = array();
            $matchingFile = new MappingFile();
            $matchingFile->setFileName($this->filepath."/".$fileName);
            $matchingFile->setFileType($fileType."matching");
            $matchingFile->filenameInWorkspace = $fileName;
            $mapping = new GenericMapping();
            $mapping->filename = $this->filepath."/".$fileName;
            $mapping->id = $this->matchingID++;
            $matchingFile->addMatching($mapping);
            array_push($this->mappingFileList, $matchingFile);
        }
        
        
                        public function getMatchingFileInWorkspace($fileName){

            
                    foreach ( $this->mappingFileList as $mappingFile ) {
                        
                            //$mappingFile = substr($mappingFile, strrpos($mappingFile, "/")+1);
			if ($mappingFile->filenameInWorkspace == $fileName){
                            return $mappingFile;
                        }
                        
                        
                    }
                    
                    return null;
        }
        
                public function getMatchingFile($fileName){

            
                    foreach ( $this->mappingFileList as $mappingFile ) {
                        
                            //$mappingFile = substr($mappingFile, strrpos($mappingFile, "/")+1);
			if ($mappingFile->filename == $fileName){
                            return $mappingFile;
                        }
                        
                        
                    }
                    
                    return null;
        }
        
        public function getMatching($id){
            
                    foreach ( $this->mappingFileList as $mappingFile ) {
                        foreach ( $mappingFile->matchings as $mapping) {
                            $mappingID = $mapping->id;
			if ($mappingID == $id){
                            return $mapping;
                        }
                        }
                        
                    }   
                    return null;
        }
        
        public function loadMatchingFile($filename, $fileType){
            $matchingFile = new MappingFile();
            $matchingFile->setFileName($filename);
            $matchingFile->setFileType($fileType);
            $matchingFile->loadMatching($filename, $this);
            array_push($this->mappingFileList, $matchingFile);
        }
        
        public function loadAndGetMatchingFile($filename, $fileType){
            $matchingFile = new MappingFile();
            $matchingFile->setFileName($filename);
            $matchingFile->setFileType($fileType);
            $matchingFile->loadMatching($filename, $this);
            return $matchingFile;
        }
        
        public function loadMatching($filename){
            $matchingFile = new MappingFile();
            $matchingFile->setFileName($filename);
            $mapping = new GenericMapping();
            $mapping->loadRDF_BPMContest2015($filename, $this->matchingID++);
            $matchingFile->addMatching($mapping);
            array_push($this->mappingFileList, $matchingFile);
        }
	
	private function loadEPCs($xml) {
		foreach ($xml->xpath("//epc") as $xml_epc) {
			$epc = new EPC($xml, $xml_epc["epcId"], htmlspecialchars($xml_epc["name"]));
			$this->epcs[(string) $xml_epc["epcId"]] = $epc;
		}
		ksort($this->epcs);
	}
	
	private function updateIDsInAllEPCs() {
		foreach ( $this->epcs as $index => $epc ) {
			$this->updateIDsInEPC($index);
		}
	}
	
	private function updateIDsInEPC($epcIndex) {
			$idConversion = array();
			
			$functionRebuild = array();
			foreach ( $this->epcs[$epcIndex]->functions as $id => $label ) {
				$functionRebuild[$this->currentNodeID] = $label;
				$idConversion[$id] = $this->currentNodeID;
				$this->currentNodeID++;
			}
			$this->epcs[$epcIndex]->functions = $functionRebuild;
			
			$eventRebuild = array();
			foreach ( $this->epcs[$epcIndex]->events as $id => $label ) {
				$eventRebuild[$this->currentNodeID] = $label;
				$idConversion[$id] = $this->currentNodeID;
				$this->currentNodeID++;
			}
			$this->epcs[$epcIndex]->events = $eventRebuild;
			
			$xorRebuild = array();
			foreach ( $this->epcs[$epcIndex]->xor as $id => $label ) {
				$xorRebuild[$this->currentNodeID] = $label;
				$idConversion[$id] = $this->currentNodeID;
				$this->currentNodeID++;
			}
			$this->epcs[$epcIndex]->xor = $xorRebuild;
						
			$orRebuild = array();
			foreach ( $this->epcs[$epcIndex]->or as $id => $label ) {
				$orRebuild[$this->currentNodeID] = $label;
				$idConversion[$id] = $this->currentNodeID;
				$this->currentNodeID++;
			}
			$this->epcs[$epcIndex]->or = $orRebuild;
			
			$andRebuild = array();
			foreach ( $this->epcs[$epcIndex]->and as $id => $label ) {
				$andRebuild[$this->currentNodeID] = $label;
				$idConversion[$id] = $this->currentNodeID;
				$this->currentNodeID++;
			}
			$this->epcs[$epcIndex]->and = $andRebuild;

			$edgeRebuild = array();
			foreach ( $this->epcs[$epcIndex]->edges as $edge ) {
				$keys = array_keys($edge);
				$source = $keys[0];
				$target = $edge[$source];
				$newEdge = array($idConversion[$source] => $idConversion[$target]);
				array_push($edgeRebuild, $newEdge);
			}
			$this->epcs[$epcIndex]->edges = $edgeRebuild;
	}
	
	public function getEPC($epcID) {
		if ( empty($epcID) || is_null($epcID) ) return null;
		return isset($this->epcs[$epcID]) ? $this->epcs[$epcID] : null;
	}
        
	public function getEPCByName($epcName) {
		if ( empty($epcName) || is_null($epcName) ) return null;
                foreach ( $this->epcs as $epc ) {
                    if ($epc->name == $epcName){
                        return $epc;
                    }
		}
	}
	
	public function addEPC(EPC $epc, $sourceFilename) {
		if ( $this->includes($epc) ) return false;
		if ( !in_array($sourceFilename, $this->sources) ) {
			array_push($this->sources, $sourceFilename);
			$this->numSources++;
		}
		$this->sourceAssignments[$this->currentEpcID] = $sourceFilename;

		// check EPC-Name for duplicate
		$name = $epc->name;
		$counter = "";
		$epcNames = $this->getEPCNames();
		while ( in_array($name.$counter, $epcNames) ) {
			$counter = empty($counter) ? 1 : $counter+1;
		}
		$epc->name .= $counter;
		
		$this->epcs[$this->currentEpcID] = $epc;
		$this->epcs[$this->currentEpcID]->id = $this->currentEpcID;
		//$this->updateIDsInEPC($this->currentEpcID);
		$this->currentEpcID++;
		$this->numModels++;
		$this->updateSessionAdd($sourceFilename, $epc->name);
		return true;
	}
	
	private function getEPCNames() {
		$names = array();
		foreach ( $this->epcs as $epc ) {
			array_push($names, $epc->name);
		}
		return $names;
	}
	
	public function removeEPC($id) {
		if ( is_null($id) ) return;
		$this->numSources++;
		$this->numModels--;
		$sourceFilename = $this->sourceAssignments[$id];
		$modelName = $this->epcs[$id]->name;
		
		$numModelsOfSource = 0;
		foreach ( $this->sourceAssignments as $epcID => $filename ) {
			if ( $filename == $sourceFilename ) $numModelsOfSource++;
		}
		if ( $numModelsOfSource == 1 ) unset($this->sources[$sourceFilename]);
		unset($this->sourceAssignments[$id]);
		
		$this->updateSessionRemove($sourceFilename, $modelName);
		unset($this->epcs[$id]);
	}
	
	public function removeEPC2(EPC $epc, $sourceFilename) {
		$workspaceEPCID = null;
		foreach ( $this->sourceAssignments as $epcID => $filename ) {
			if ( $filename == $sourceFilename ) {
				if ( $this->epcs[$epcID]->name == $epc->name ) $workspaceEPCID = $epcID;
			}
		}
		$this->removeEPC($workspaceEPCID);
	}
	
	public function removeAllEPCsOfSource($sourceFilename) {
		foreach ( $this->sourceAssignments as $epcID => $filename ) {
			if ( $filename == $sourceFilename ) $this->removeEPC($epcID);
		}
	}
	
	private function updateSessionAdd($sourceFilename, $modelName) {
		$_SESSION['numWorkspaceModels']++;
		$modelsInWorkspace = $_SESSION["modelsInWorkspace"];
		if ( !in_array($sourceFilename."/".$modelName, $modelsInWorkspace) ) array_push($modelsInWorkspace, $sourceFilename."/".$modelName);
		$_SESSION["modelsInWorkspace"] = $modelsInWorkspace;
	}
	
	private function updateSessionRemove($sourceFilename, $modelName) {
		$_SESSION['numWorkspaceModels']--;
		$modelsInWorkspace = $_SESSION["modelsInWorkspace"];
		$flipped = array_flip($modelsInWorkspace);
		$index = (int) $flipped[$sourceFilename."/".$modelName];
		unset($modelsInWorkspace[$index]);
		$_SESSION["modelsInWorkspace"] = $modelsInWorkspace;
	}
	
	public static function inWorkspace($sourceFilename, $modelName) {
		if ( in_array($sourceFilename."/".$modelName, $_SESSION["modelsInWorkspace"]) ) return true;
		return false;
	}
	
	public function includes(EPC $epc) {
		$checkHash = $epc->getHash();
		foreach ( $this->epcs as $currEPC ) {
			$hash = $currEPC->getHash();
			if ( $checkHash == $hash ) return true;
		}
		return false;
	}
	
	/**
	 * Return to a source given in $this->sources all assigned EPC IDs
	 * 
	 * @param string $source
	 * @return array of EpcIDs
	 */
	public function getModelsFromSource($source) {
		$assignedEpcIDs = array();
		foreach ( $this->sourceAssignments as $epcID => $assignedSource ) {
			if ( $source == $assignedSource ) array_push($assignedEpcIDs, $epcID);
		}
		return $assignedEpcIDs;
	}
	
	public function updateWorkspaceEPMLFile() {
		$content =  "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n";
		$content .= "<epml:epml xmlns:epml=\"http://www.epml.de\"\n";
		$content .= "  xmlns:xsi=\"http://www.w3.org/2001/XMLSchema-instance\" xsi:schemaLocation=\"epml_1_draft.xsd\">\n";
		$content .= "  <rmm-workspace maxNodeID=\"".$this->currentNodeID."\" maxEpcID=\"".$this->currentEpcID."\">\n";
		foreach ( $this->sourceAssignments as $modelID => $source ) {
			$content .= "     <source-assignment modelID=\"".$modelID."\" source=\"".$source."\" />\n";
		}
		$content .= "  </rmm-workspace>\n";
		$content .= "  <directory name=\"root\">\n";
		
		foreach ( $this->sources as $source ) {			
			$content .= "  <directory name=\"".$source."\">\n";
			
			$assignedEpcIDs = $this->getModelsFromSource($source);
			foreach ( $assignedEpcIDs as $epcID ) {
				
				$content .= "    <epc epcId=\"".$epcID."\" name=\"".htmlspecialchars(EPC::convertIllegalChars($this->epcs[$epcID]->getEPCName()))."\">\n";
				
				foreach ( $this->epcs[$epcID]->functions as $id => $label ) {
					$content .= "      <function id=\"".$id."\">\n";
					$content .= "        <name>".htmlspecialchars($this->epcs[$epcID]->convertIllegalChars($label))."</name>\n";
					$content .= "      </function>\n";
				}
				
				foreach ( $this->epcs[$epcID]->events as $id => $label ) {
					$content .= "      <event id=\"".$id."\">\n";
					$content .= "        <name>".htmlspecialchars($this->epcs[$epcID]->convertIllegalChars($label))."</name>\n";
					$content .= "      </event>\n";
				}
				
				foreach ( $this->epcs[$epcID]->getAllConnectors() as $id => $label ) {
					$content .= "      <".$label." id=\"".$id."\" />\n";
				}
				
				foreach ( $this->epcs[$epcID]->edges as $index => $edge ) {
					$keys = array_keys($edge);
					$source = $keys[0];
					$target = $edge[$source];
				
					$content .= "      <arc id=\"".$this->currentNodeID."\">\n";
					$content .= "        <flow source=\"".$source."\" target=\"".$target."\" />\n";
					$content .= "      </arc>\n";
				
					$this->currentNodeID++;
					//if ( $this->currentNodeID == 21 ) echo "NOW - generate";
				}
				
				foreach ( $this->epcs[$epcID]->orgUnits as $id => $label ) {
					$content .= "    <role id=\"".$id."\" optional=\"false\">\n";
					$content .= "      <name>".htmlspecialchars($this->epcs[$epcID]->convertIllegalChars($label));
					$content .= "</name>\n";
					$content .= "    </role>\n";
				}
				
				foreach ( $this->epcs[$epcID]->functionOrgUnitAssignments as $funcID => $roleID ) {
					$content .= "    <arc id=\"".$this->currentNodeID."\">\n";
					$content .= "      <relation source=\"".$roleID."\" target=\"".$funcID."\" type=\"role\"/>\n";
					$content .= "    </arc>\n";
				
					$this->currentNodeID++;
				}
				
				$content .= "    </epc>\n";				
			}
			$content .= "  </directory>\n";
		}
		$content .= "  </directory>\n";

		$content .= "</epml:epml>";
		
		$handler = fopen($this->file, "w");
		fwrite($handler, $content);
		fclose($handler);
		//chmod($this->file, 0777);
	}
	
	public function getAvailableData() {
		return new WorkspaceData($this->filepath);
	}
	
	public function getEditEPCNameModalCode($epcID) {
		
		$epcName = $this->epcs[$epcID]->getEPCName();
		$epcHash = $this->epcs[$epcID]->getHash();
	
		return "
		<div class=\"modal fade\" id=\"modal_editEPCName_".$epcHash."\" tabindex=\"-1\" role=\"dialog\" aria-labelledby=\"myModalLabel\" aria-hidden=\"true\">
			<div class=\"modal-dialog\">
				<div class=\"modal-content\">
					<form  class=\"form-horizontal\" method=\"post\">
						<input type=\"hidden\" name=\"action\" value=\"doEditEPCName\" />
						<input type=\"hidden\" name=\"epcID\" value=\"".$epcID."\" />
						<div class=\"modal-header\">
							<button type=\"button\" class=\"close\" data-dismiss=\"modal\" aria-label=\"Close\"><span aria-hidden=\"true\">&times;</span></button>
							<h4 class=\"modal-title\" id=\"myModalLabel\">Edit name of EPC \"".$epcName."\"</h4>
						</div>
						<div class=\"modal-body\">
							<input type=\"text\" class=\"form-control\" name=\"epcName\" value=\"".$epcName."\">
						</div>
						<div class=\"modal-footer\">
							<button type=\"button\" class=\"btn btn-default\" data-dismiss=\"modal\">Cancel</button>
							 <button type=\"submit\" class=\"btn btn-primary\">Save</button>
						</div>
					</form>
				</div>
			</div>
		</div>
		";
	}
	
}
?>