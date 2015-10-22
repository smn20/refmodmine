<?php
/**
 * Ein N-Aeres Mapping, basierend auf der Ontology und WordNet
 * Im Wesentlichen handelt es sich hier um ein Clustering
 *
 * @author Tom Thaler
 */
class NAryWordstemMappingWithAntonyms2015 extends ANAryMapping implements INAryMapping {
	
	// Best Value
	public $threshold_ontology_quote = 60;
	public $removedPossibleEvents = array ();
	public $harmonizationDegree = 0;
	
	// persisted files
	private $persistedLabelElements = null;
	private $persistedAntonymLabels = null;
	private $persistedNonAntonymLabels = null;
	private $persistedCorrespondentLabels = null;
	private $persistedNonCorrespondentLabels = null;
	private $persistedMatchingCorresponencyLabels = null;
	private $persistedMatchingNonCorrespondencyLabels = null;

	public function __construct() {
		$this->loadPersistedInformation();
	}
	
	public function isBinary() {
		return (count($this->epcs) == 2) ? true : false;
	}
	
	private function loadPersistedInformation() {
		$this->persistedLabelElements = NLP::loadLabelElementsFromPersistedFile();
		$this->persistedAntonymLabels = NLP::loadAntonymLabelsFromPersistedFile();
		$this->persistedNonAntonymLabels = NLP::loadNonAntonymLabelsFromPersistedFile();
		$this->persistedCorrespondentLabels = NLP::loadCorrespondentLabelsFromPersistedFile();
		$this->persistedNonCorrespondentLabels = NLP::loadNonCorrespondentLabelsFromPersistedFile();
		$this->persistedMatchingCorresponencyLabels = NLP::loadMatchingCorrespondentLabelsFromPersistedFile();
		$this->persistedMatchingNonCorrespondencyLabels = NLP::loadMatchingNonCorrespondentLabelsFromPersistedFile();
	}
	
	/**
	 * Setzt den Threshold-Parameter
	 *
	 * @param array $params
	 *        	muss dan Threshold-Parameter [0;100] in der Form array('threshold' => 50) enthalten
	 * @return boolean
	 */
	public function setParams(Array $params) {
		$paramSet = false;
		if (isset ( $params ['threshold_ontology_quote'] )) {
			$this->threshold_ontology_quote = $params ['threshold_ontology_quote'];
			$paramSet = true;
		}
		
		return $paramSet;
	}

	public function sortEPCsByName() {
		$names = array ();
		foreach ( $this->epcs as $epc ) {
			array_push ( $names, strtolower ( ( string ) $epc->name ) );
		}
		sort ( $names, SORT_STRING );
		print_r ( $names );
		$epcs = array ();
		foreach ( $names as $index => $name ) {
			foreach ( $this->epcs as $epc ) {
				if (strtolower ( ( string ) $epc->name ) == $name) {
					$epcs [$index] = $epc;
					continue;
				}
			}
		}
		$this->epcs = $epcs;
	}
	public function map($includeEvents = false) {
		
		/**
		 * Funktionsontologien bauen und alles in ein Array schreiben
		 */
		$numOfAllFuncs = 0;
		foreach ( $this->epcs as $epc ) {
			$numOfAllFuncs += count ( $epc->functions );
		}
		print ("\n\nGenerate function ontologies... \n") ;
		$progressBar = new CLIProgressbar ( $numOfAllFuncs, 0.1 );
		$i = 0;
		
		$allFunctions = array ();
		foreach ( $this->epcs as $epc ) {
			foreach ( $epc->functions as $id => $label ) {
				array_push ( $allFunctions, new FunctionOntologyWithSynonyms ( $epc, $id, $label ) );
				$i ++;
				$progressBar->run ( $i );
			}
		}
		print ("\ndone") ;
		
		/**
		 * Clusterbildung durch Vergleich aller Funktionspaare
		 */
		$i = 0;
		$j = 1;
		// $numOfAllFuncs = count($allFunctions);
		while ( $i < $numOfAllFuncs ) {
			$node1 = $allFunctions [$i];
			while ( $j < $numOfAllFuncs ) {
				// print(".".$i."-".$j.".");
				$node2 = $allFunctions [$j];
				// Similarity nur dann berechnen, wenn es sich um Knoten aus verschiedenen EPKs handelt
				if ($node1->epc->name != $node2->epc->name) {
					$nodeSimilarity = $this->compare ( $node1, $node2 );
					// print("\n ".$node1->label." <=> ".$node2->label." | ".$nodeSimilarity);
					if ($nodeSimilarity >= $this->threshold_ontology_quote) {
						$this->cluster ( $node1, $node2 );
					}
				}
				$j ++;
			}
			$i ++;
			$j = $i + 1;
		}
		
		if ($includeEvents) {
			/**
			 * Eventontologien bauen und alles in ein Array schreiben
			 */
			$numOfAllEvents = 0;
			foreach ( $this->epcs as $epc ) {
				$numOfAllEvents += count ( $epc->events );
			}
			print ("\n\nGenerate event ontologies... \n") ;
			$progressBar = new CLIProgressbar ( $numOfAllEvents, 0.1 );
			$i = 0;
			
			$allEvents = array ();
			foreach ( $this->epcs as $epc ) {
				foreach ( $epc->events as $id => $label ) {
					array_push ( $allEvents, new FunctionOntologyWithSynonyms ( $epc, $id, $label ) );
					$i ++;
					$progressBar->run ( $i );
				}
			}
			print ("\ndone") ;
			
			/**
			 * Clusterbildung durch Vergleich aller Eventpaare
			 */
			$i = 0;
			$j = 1;
			// $numOfAllFuncs = count($allFunctions);
			while ( $i < $numOfAllEvents ) {
				$node1 = $allEvents [$i];
				while ( $j < $numOfAllEvents ) {
					// print(".".$i."-".$j.".");
					$node2 = $allEvents [$j];
					// Similarity nur dann berechnen, wenn es sich um Knoten aus verschiedenen EPKs handelt
					if ($node1->epc->name != $node2->epc->name) {
						$nodeSimilarity = $this->compare ( $node1, $node2 );
						// print("\n ".$node1->label." <=> ".$node2->label." | ".$nodeSimilarity);
						if ($nodeSimilarity >= $this->threshold_ontology_quote) {
							if ($node1->label != "start" && $node2->label != "start" && $node1->label != "end" && $node2->label != "end")
								$this->cluster ( $node1, $node2 );
						}
					}
					$j ++;
				}
				$i ++;
				$j = $i + 1;
			}
		}
		
		$file1 = $this->exportDebug ( "", "_complete" );
		$this->cleanClusters ();
		$file2 = $this->exportDebug ( "", "_reduced" );
		return array (
				$file1,
				$file2 
		);
	}
	
	/**
	 * tried to find adequate shortcut solvings within all available nodes of all models
	 */
	public function solveShortcuts() {
		print ("\n\nProceed shortcut solving ...") ;
		$shortCuts = array ();
		$bag = array ();
		foreach ( $this->epcs as $index => $epc ) {
			$labels = $epc->functions;
			foreach ( $labels as $id => $label ) {
				$label = Tools::replaceUnsupportedChars($label);
				$this->epcs[$index]->functions[$id] = $label;
				$tokens = explode ( " ", ltrim ( rtrim ( $label ) ) );
				foreach ( $tokens as $token ) {
					if (Tools::endsWith ( $token, "." )) {
						$shortCuts [substr ( $token, 0, -1 )] = null;
					} else {
						array_push ( $bag, $token );
					}
				}
			}
			
			$labels = $epc->events;
			foreach ( $labels as $id => $label ) {
				$label = Tools::replaceUnsupportedChars($label);
				$this->epcs[$index]->events[$id] = $label;
				$tokens = explode ( " ", ltrim ( rtrim ( $label ) ) );
				foreach ( $tokens as $token ) {
					if (Tools::endsWith ( $token, "." )) {
						$shortCuts [substr ( $token, 0, -1 )] = null;
					} else {
						array_push ( $bag, $token );
					}
				}
			}
		}
		
		foreach ( $shortCuts as $shortCut => $solve ) {
			foreach ( $bag as $word ) {
				if (Tools::startsWith ( $word, $shortCut ))
					$shortCuts [$shortCut] = $word;
			}
		}
		
		
		foreach ( $shortCuts as $shortCut => $solve ) {
			foreach ( $this->epcs as $index => $epc ) {
				foreach ( $epc->functions as $fIndex => $label ) {				
					$tokens = explode ( " ", ltrim ( rtrim ( $label ) ) );
					foreach ( $tokens as $token ) {
						if ( Tools::endsWith($token, ".") ) {
							$search = substr($token, 0, -1);
							if ( !is_null($shortCuts[$search]) ) {
								$this->epcs[$index]->functions[$fIndex] = str_replace($search.".", $shortCuts[$search], $this->epcs[$index]->functions[$fIndex]);
								print ("\n  " . $label . " renamed to " . $this->epcs[$index]->functions[$fIndex]) ;
							}
						}
					}
				}
			}
		}
		print ("\ndone") ;
	}
	
	public function map2015($includeEvents = false) {
		
		$hDegree = $this->getHarmonizationDegree();
		$harmonizationDegreeBasedThreshold = $hDegree >= $this->threshold_ontology_quote/100 ? 50 : 0; // threshold for detailed analysis
		print("\nDetailed analysis threshold: ".$harmonizationDegreeBasedThreshold);
		
		/**
		 * Funktionsontologien bauen und alles in ein Array schreiben
		 */
		$numOfAllFuncs = 0;
		foreach ( $this->epcs as $epc ) {
			$numOfAllFuncs += count ( $epc->functions );
		}
		print ("\n\nGenerate function ontologies... \n") ;
		$progressBar = new CLIProgressbar ( $numOfAllFuncs, 0.1 );
		$i = 0;
		
		$allFunctions = array ();
		foreach ( $this->epcs as $epc ) {
			foreach ( $epc->functions as $id => $label ) {
				array_push ( $allFunctions, new FunctionOntologyWithSynonyms ( $epc, $id, $label ) );
				$i ++;
				$progressBar->run ( $i );
			}
		}
		print ("\ndone") ;
		
		/**
		 * Clusterbildung durch Vergleich aller Funktionspaare
		 */
		
		$cacheForDetailedAnalysis = array ();
		
		print ("\n\nProceed cluster mapping ... \n") ;
		$numNodePairs = (($numOfAllFuncs * $numOfAllFuncs) - $numOfAllFuncs) / 2;
		$progressBar = new CLIProgressbar ( $numNodePairs, 0.1 );
		$compareCounter = 0;
		
		$i = 0;
		$j = 1;
		// $numOfAllFuncs = count($allFunctions);
		while ( $i < $numOfAllFuncs ) {
			$node1 = $allFunctions [$i];
			while ( $j < $numOfAllFuncs ) {
				// print(".".$i."-".$j.".");
				$node2 = $allFunctions [$j];
				// Similarity nur dann berechnen, wenn es sich um Knoten aus verschiedenen EPKs handelt
				if ($node1->epc->name != $node2->epc->name) {
					$nodeSimilarity = $this->compare2015 ( $node1, $node2 );
					
					if (($node1->label == "Check prename" && $node2->label == "Decide first name") || ($node2->label == "Check prename" && $node1->label == "Decide first name")) {
						print ("\n" . $node1->label . " -> " . $node2->label . ": " . $nodeSimilarity . "\n") ;
					}
					
					// print("\n ".$node1->label." <=> ".$node2->label." | ".$nodeSimilarity);
					if ($nodeSimilarity >= $this->threshold_ontology_quote) {
						$this->cluster ( $node1, $node2 );
						// } elseif ( $nodeSimilarity >= 50 ) { // Admission 0,57 F-Measure
					} 					// elseif ( $nodeSimilarity >= 10 ) {
					elseif ( $nodeSimilarity >= $harmonizationDegreeBasedThreshold ) {
						// Detaillierte Analyse bei Unsicherheit
						$cache = array (
								"node1" => $node1,
								"node2" => $node2 
						);
						array_push ( $cacheForDetailedAnalysis, $cache );
						// print("\n".$node1->label." -> ".$node2->label.": ".$nodeSimilarity."\n");
					}
				}
				$j ++;
				
				$compareCounter ++;
				$progressBar->run ( $compareCounter );
			}
			$i ++;
			$j = $i + 1;
		}
		print ("\ndone") ;
		
		// Detaillierte Analyse bei Unsicherheit
		print ("\n\nDetailed analyses at decision of uncertainty ... \n") ;
		$numNodePairs = count ( $cacheForDetailedAnalysis );
		$progressBar = new CLIProgressbar ( $numNodePairs, 0.1 );
		$compareCounter = 0;
		
		foreach ( $cacheForDetailedAnalysis as $nodePair ) {
			$node1 = $nodePair ["node1"];
			$node2 = $nodePair ["node2"];
			$sim = $this->compareInDetail ( $node1, $node2 );
			if ($sim == 1) {
				$this->cluster ( $node1, $node2 );
			}
			$compareCounter ++;
			$progressBar->run ( $compareCounter );
		}
		print ("\ndone") ;
		
		if ($includeEvents) {
			/**
			 * Eventontologien bauen und alles in ein Array schreiben
			 */
			$numOfAllEvents = 0;
			foreach ( $this->epcs as $epc ) {
				$numOfAllEvents += count ( $epc->events );
			}
			print ("\n\nGenerate event ontologies... \n") ;
			$progressBar = new CLIProgressbar ( $numOfAllEvents, 0.1 );
			$i = 0;
			
			$allEvents = array ();
			foreach ( $this->epcs as $epc ) {
				foreach ( $epc->events as $id => $label ) {
					array_push ( $allEvents, new FunctionOntologyWithSynonyms ( $epc, $id, $label ) );
					$i ++;
					$progressBar->run ( $i );
				}
			}
			print ("\ndone") ;
			
			/**
			 * Clusterbildung durch Vergleich aller Eventpaare
			 */
			$i = 0;
			$j = 1;
			// $numOfAllFuncs = count($allFunctions);
			while ( $i < $numOfAllEvents ) {
				$node1 = $allEvents [$i];
				while ( $j < $numOfAllEvents ) {
					// print(".".$i."-".$j.".");
					$node2 = $allEvents [$j];
					// Similarity nur dann berechnen, wenn es sich um Knoten aus verschiedenen EPKs handelt
					if ($node1->epc->name != $node2->epc->name) {
						$nodeSimilarity = $this->compare ( $node1, $node2 );
						// print("\n ".$node1->label." <=> ".$node2->label." | ".$nodeSimilarity);
						if ($nodeSimilarity >= $this->threshold_ontology_quote) {
							if ($node1->label != "start" && $node2->label != "start" && $node1->label != "end" && $node2->label != "end")
								$this->cluster ( $node1, $node2 );
						}
					}
					$j ++;
				}
				$i ++;
				$j = $i + 1;
			}
		}
		
		$file1 = $this->exportDebug ( "", "_complete" );
		$this->cleanClusters ();
		$file2 = $this->exportDebug ( "", "_reduced" );
		return array (
				$file1,
				$file2 
		);
	}
	public function mapMultiCore($reportingFolder = "") {
		
		/**
		 * Funktionsontologien bauen und alles in ein Array schreiben
		 */
		$numOfAllFuncs = 0;
		foreach ( $this->epcs as $epc ) {
			$numOfAllFuncs += count ( $epc->functions );
		}
		
		// print("\nVerteilung der Ontologieberechnung...\n");
		// $progressBar = new CLIProgressbar($numOfAllFuncs, 0.1);
		// Splitten der Aufgaben auf die Anzahl der Kerne
		$splitCount = round ( count ( $this->epcs ) / Config::NUM_CORES_TO_WORK_ON );
		$nextSplit = $splitCount;
		$epcsParts = array ();
		$epcsParts [0] = array ();
		$part = 0;
		$i = 0;
		foreach ( $this->epcs as $epc ) {
			$i ++;
			if ($i == $nextSplit && $part < Config::NUM_CORES_TO_WORK_ON - 1) {
				$part ++;
				$epcsParts [$part] = array ();
				$nextSplit += $splitCount;
			}
			array_push ( $epcsParts [$part], $epc );
			// $progressBar->run($i);
		}
		// print("\ndone");
		
		print ("Berechnung der Funktionsontologien... \n") ;
		// Fuer jede EPK-Menge einen Thread erzeugen und starten
		$thread = array ();
		$maxThreadID = 0;
		foreach ( $epcsParts as $threadID => $epcsPart ) {
			$thread [$threadID + 1] = new MultiThreadFunctionOntologyOperation ( $epcsPart );
			$thread [$threadID + 1]->start ();
			$maxThreadID = $threadID + 1;
		}
		
		// Threads Synchronisieren
		$allFunctions = array ();
		$progressBar = new CLIProgressbar ( $numOfAllFuncs, 0.1 );
		$currentThread = 1;
		while ( $currentThread <= $maxThreadID ) {
			if ($thread [$currentThread]->isRunning ()) {
				sleep ( 1 );
				$finishedOperations = 0;
				for($i = 1; $i <= $maxThreadID; $i ++) {
					$finishedOperations += $thread [$i]->finishedOperations;
				}
				$progressBar->run ( $finishedOperations );
			} else {
				foreach ( $thread [$currentThread]->functions as $functionOntology ) {
					array_push ( $allFunctions, unserialize ( $functionOntology ) );
				}
				$currentThread ++;
			}
		}
		print ("\ndone\n\n") ;
		
		/**
		 * Clusterbildung durch Vergleich aller Funktionspaare
		 */
		print ("Berechnung der Cluster...\n") ;
		
		$progressBar = new CLIProgressbar ( ($numOfAllFuncs * $numOfAllFuncs) / 2, 0.1 );
		$i = 0;
		$j = 1;
		$finishedOperations = 0;
		// $numOfAllFuncs = count($allFunctions);
		while ( $i < $numOfAllFuncs ) {
			$node1 = $allFunctions [$i];
			while ( $j < $numOfAllFuncs ) {
				// print(".".$i."-".$j.".");
				$node2 = $allFunctions [$j];
				// Similarity nur dann berechnen, wenn es sich um Knoten aus verschiedenen EPKs handelt
				if ($node1->epc->internalID != $node2->epc->internalID) {
					$nodeSimilarity = $this->compare ( $node1, $node2 );
					// print("\n ".$node1->label." <=> ".$node2->label." | ".$nodeSimilarity);
					if ($nodeSimilarity >= $this->threshold_ontology_quote) {
						$this->cluster ( $node1, $node2 );
					}
				}
				$j ++;
				$finishedOperations ++;
				$progressBar->run ( $finishedOperations );
			}
			$i ++;
			$j = $i + 1;
		}
		$this->exportDebug ( $reportingFolder, "_complete" );
		$this->cleanClusters ();
		$this->exportDebug ( $reportingFolder, "_reduced" );
		print ("\ndone\n\n") ;
	}
	private function compare(FunctionOntologyWithSynonyms $node1, FunctionOntologyWithSynonyms $node2) {
		if ($node1->label == $node2->label)
			return 100;
			
			// Dummy-Transitionen nicht matchen
		if (preg_match ( "/^t[0-9]*$/", $node1->label ) || preg_match ( "/^t[0-9]*$/", $node2->label ))
			return 0;
			
			// Wenn ein Label die Verneinung des anderen Labels ist, dann nicht matchen
		if ($this->areAntonyms ( $node1, $node2 ))
			return 0;
		
		/**
		 * Aehnlichkeit ueber Porter-Stems bestimmen
		 */
		
		$countWordstemsOfLabel1 = count ( $node1->wordstems );
		$countWordstemsOfLabel2 = count ( $node2->wordstems );
		if ($countWordstemsOfLabel1 > $countWordstemsOfLabel2) {
			// Label1 muss immer dasjenigen mit der geringeren Anzahl an Komponenten (Woertern) sein
			$node_temp = $node1;
			$node1 = $node2;
			$node2 = $node_temp;
		}
		$countWordstemMappings = 0;
		foreach ( $node1->wordstems as $wordstem1 ) {
			foreach ( $node2->wordstems as $wordstem2 ) {
				if ($wordstem1 == $wordstem2) {
					$countWordstemMappings ++;
					break;
				}
			}
		}
		
		$stemSimilarity = round ( (2 * $countWordstemMappings / ($countWordstemsOfLabel1 + $countWordstemsOfLabel2)) * 100, 2 );
		return $stemSimilarity;
	}
	private function compareInDetail($node1, $node2) {
		if (preg_match ( "/^t[0-9]*$/", $node1->label ) || preg_match ( "/^t[0-9]*$/", $node2->label ))
			return 0;
		
		if ($this->areThereAdversingIdentyMatches ( $node1, $node2 ))
			return 0;
		
		$isCorresponding = NLP::checkVerbObjectCorrespondencyForTwoLabelsForMatching($node1->label, $node2->label, $this->persistedLabelElements);
		
		if ( $isCorresponding ) {
			print ("  ".$node1->label." (".$node1->epc->name.") -> ".$node2->label." (".$node2->epc->name.") matched\n") ;
			return 1;
		}
		return 0;
	}
	
	/**
	 * Checks whether there are identity matches within the models, where one of the both nodes is involved
	 *
	 * @param unknown $node1        	
	 * @param unknown $node2        	
	 *
	 * @return bool
	 */
	private function areThereAdversingIdentyMatches($node1, $node2) {
		foreach ( $node1->epc->functions as $label ) {
			if (strtolower ( $label ) == strtolower ( $node2->label ))
				return true;
		}
		
		foreach ( $node2->epc->functions as $label ) {
			if (strtolower ( $label ) == strtolower ( $node1->label ))
				return true;
		}
		
		return false;
	}
	
	private function compare2015(FunctionOntologyWithSynonyms $node1, FunctionOntologyWithSynonyms $node2) {
		if ($node1->label == $node2->label)
			return 100;
			
			// Dummy-Transitionen nicht matchen
		if (preg_match ( "/^t[0-9]*$/", $node1->label ) || preg_match ( "/^t[0-9]*$/", $node2->label ))
			return 0;
			
			// Wenn ein Label die Verneinung des anderen Labels ist, dann nicht matchen
		if ($this->areAntonyms ( $node1, $node2 ))
			return 0;
		
		/**
		 * Aehnlichkeit ueber Porter-Stems bestimmen
		 */
		
		$countWordstemsOfLabel1 = count ( $node1->wordstems );
		$countWordstemsOfLabel2 = count ( $node2->wordstems );
		if ($countWordstemsOfLabel1 > $countWordstemsOfLabel2) {
			// Label1 muss immer dasjenigen mit der geringeren Anzahl an Komponenten (Woertern) sein
			$node_temp = $node1;
			$node1 = $node2;
			$node2 = $node_temp;
		}
		$countWordstemMappings = 0;
		foreach ( $node1->wordstems as $wordstem1 ) {
			foreach ( $node2->wordstems as $wordstem2 ) {
				if ($wordstem1 == $wordstem2) {
					$countWordstemMappings ++;
					break;
				}
			}
		}
		
		$stemSimilarity = round ( (2 * $countWordstemMappings / ($countWordstemsOfLabel1 + $countWordstemsOfLabel2)) * 100, 2 );
		return $stemSimilarity;
	}
	
	private function getHarmonizationDegree() {
		$numFunctions = 0;
		$differentFunctionLabels = array();
		foreach ( $this->epcs as $epc ) {
			foreach ( $epc->functions as $id => $label ) {
				$numFunctions++;
				if ( isset($differentFunctionLabels[$label]) ) {
					$differentFunctionLabels[$label]++;
				} else {
					$differentFunctionLabels[$label] = 1;
				}
			}
		}
		
		// num labels occuring more than once
		$numLabelsMoreThanOnce = 0;
		foreach ( $differentFunctionLabels as $label => $count ) {
			if ( $count > 1 ) $numLabelsMoreThanOnce++;
		}
		
		
		$numDifferentFunctions = count($differentFunctionLabels);
		$maxMoreThanOnce = $numFunctions - $numDifferentFunctions;
		
		$minPossible = 2*$maxMoreThanOnce/count($this->epcs);
		
		$zero = $numLabelsMoreThanOnce-$minPossible;
		$hDegree = 0;
		if ( $zero == 0 ) {
			$hDegree = 1;
		} else {
			$relativeMax = $maxMoreThanOnce - $minPossible;
			$hDegree = 1- ($zero / $relativeMax);
		}
		
		//$hDegree = 1- ($numLabelsMoreThanOnce / $minPossible+$maxMoreThanOnce);
		
		//$hDegree = 1- ($numLabelsMoreThanOnce / $maxMoreThanOnce);

		$this->harmonizationDegree = $hDegree;
		print ("\nHarmonization degree: ".$hDegree);
		return $hDegree;
	}
	
	/**
	 * Prueft zwei Knoten auf Antonyme (Gegensaetze)
	 *
	 * @param unknown_type $node1        	
	 * @param unknown_type $node2        	
	 *
	 * @return boolean
	 */
	private function areAntonyms(&$node1, &$node2) {
		if (in_array ( "not", $node1->wordstems ) && ! in_array ( "not", $node2->wordstems )) {
			return true;
		}
		if (! in_array ( "not", $node1->wordstems ) && in_array ( "not", $node2->wordstems )) {
			return true;
		}
		foreach ( $node1->synonyms as $syns ) {
			foreach ( $node2->antonyms as $ants ) {
				// gleiche Elemente
				$numIntersection = count ( array_intersect ( $syns, $ants ) );
				if ($numIntersection > 0) {
					return true;
				}
			}
		}
		foreach ( $node1->antonyms as $ants ) {
			foreach ( $node2->synonyms as $syns ) {
				// gleiche Elemente
				$numIntersection = count ( array_intersect ( $syns, $ants ) );
				if ($numIntersection > 0) {
					return true;
				}
			}
		}
		return false;
	}
	
	/**
	 * Fuehrt das Clustering der beiden Knoten durch.
	 * Dabei wird auch sichergestellt, dass jeder Knoten
	 * in genau einem Cluster ist-
	 *
	 * @param FunctionOntologyWithSynonyms $node1        	
	 * @param FunctionOntologyWithSynonyms $node2        	
	 */
	private function cluster(FunctionOntologyWithSynonyms $node1, FunctionOntologyWithSynonyms $node2) {
		$clusterIndexOfNode1 = $this->searchClusterForNode ( $node1 );
		$clusterIndexOfNode2 = $this->searchClusterForNode ( $node2 );
		
		if (is_null ( $clusterIndexOfNode1 ) && is_null ( $clusterIndexOfNode2 )) {
			// Keiner der Knoten befindet sich in einem Cluster
			$clusterIndex = $this->addCluster ();
			$this->clusters [$clusterIndex]->addNode ( $node1 );
			$this->clusters [$clusterIndex]->addNode ( $node2 );
		} elseif (! is_null ( $clusterIndexOfNode1 ) && ! is_null ( $clusterIndexOfNode2 )) {
			// Beide Knoten befinden sich in einem Cluster
			// Wenn unterschiedliche Cluster, dann fuege diese zusammen
			if ($clusterIndexOfNode1 != $clusterIndexOfNode2) {
				$this->mergeCluster ( $clusterIndexOfNode1, $clusterIndexOfNode2 );
			}
		} else {
			// Genau einer der beiden Knoten befindet sich in einem Cluster
			$clusterIndex = is_null ( $clusterIndexOfNode1 ) ? $clusterIndexOfNode2 : $clusterIndexOfNode1;
			$this->clusters [$clusterIndex]->addNode ( $node1 );
			$this->clusters [$clusterIndex]->addNode ( $node2 );
		}
	}
	
	/**
	 * Sucht nach einem Cluster, dass den uebergebenen Funktionsknoten enthaelt und gib den Index des Cluster
	 * im Cluster-Array zurueck.
	 * Wenn kein Cluster gefunden wird, wird null zurueckgegeben.
	 *
	 * @param FunctionOntologyWithSynonyms $node        	
	 * @return int|NULL
	 */
	private function searchClusterForNode(FunctionOntologyWithSynonyms $node) {
		foreach ( $this->clusters as $clusterIndex => $cluster ) {
			if ($cluster->contains ( $node ))
				return $clusterIndex;
		}
		return null;
	}
	
	/**
	 * Erstellt ein neues Cluster und gibt den Index des Clusters
	 * aus dem Cluster-Array ($clusters) zurueck
	 *
	 * @return int
	 */
	private function addCluster() {
		$nextClusterIndex = empty ( $this->clusters ) ? 0 : max ( array_keys ( $this->clusters ) ) + 1;
		$this->clusters [$nextClusterIndex] = new NodeCluster ();
		return $nextClusterIndex;
	}
	
	/**
	 * Fuegt zwei Cluster zusammen, indem es alle Knoten aus dem zweiten Cluster
	 * in das erste Cluster einfuegt und das zweite Cluster dann entfernt.
	 *
	 * @param int $clusterIndex1
	 *        	Index des ersten Clusters
	 * @param int $clusterIndex2
	 *        	Index des zweiten Clusters
	 */
	private function mergeCluster($clusterIndex1, $clusterIndex2) {
		foreach ( $this->clusters [$clusterIndex2]->nodes as $node ) {
			$this->clusters [$clusterIndex1]->addNode ( $node );
		}
		unset ( $this->clusters [$clusterIndex2] );
	}
	public function extractBinaryMapping(&$epc1, &$epc2) {
		$binaryMapping = new BinaryMapping ( $epc1, $epc2 );
		$binaryMapping->setParams ( array (
				'clusters' => $this->clusters 
		) );
		return $binaryMapping;
	}
	public function printDebug() {
		$output = $this->generateDebug ();
		print ($output) ;
	}
	public function exportDebug($folderName = "", $filename_suffix = "") {
		$output = $this->generateDebug ();
		$fileGenerator = new FileGenerator ( "clusters" . $filename_suffix . ".txt", $output );
		if (! empty ( $folderName ))
			$fileGenerator->setPath ( $folderName );
		$fileGenerator->setFilename ( "clusters" . $filename_suffix . ".txt" );
		$fileGenerator->setContent ( $output );
		$file = $fileGenerator->execute ();
		return $file;
	}
	private function generateDebug() {
		$text = "\r\n\r\nAnzahl Cluster: " . count ( $this->clusters );
		foreach ( $this->clusters as $index => $cluster ) {
			$text .= "\r\n\r\n Cluster " . $index . " enhaelt " . count ( $cluster->nodes ) . " Knoten.";
			foreach ( $cluster->nodes as $node ) {
				$text .= "\r\n          " . $node->label . " [" . $node->id . "] (" . $node->epc->internalID . ")";
			}
		}
		return $text;
	}
	
	/**
	 * Bereinigt die Cluster.
	 * Es werden dabei Funktionen entfernt, deren Labels fuer Ereignisse sprechen
	 */
	private function cleanClusters() {
		foreach ( $this->clusters as $index => &$cluster ) {
			$removedEvents = $cluster->removePossibleEvents ();
			foreach ( $removedEvents as $node ) {
				array_push ( $this->removedPossibleEvents, $node );
			}
		}
	}
}
?>