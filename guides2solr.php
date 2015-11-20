#!/usr/bin/php

<?php

// Open LibGuides Export file and retrieve XML in a string variable
$clean_xml = cleanXMLFile($argv[1]);

// Create a SimpleXMLElement object using the XML string
$xml_object = createSimpleXMLObject($clean_xml);

/***********************************
 * Function Definitions Start Here *
 ***********************************/

// Takes file path as input and returns an XML string without invalid characters
function cleanXMLFile($file_path)
{ 
  $raw_xml = file_get_contents($file_path);

  if ($raw_xml) {
    echo "Successfully opened file " . $file_path . "...\n";
  }

  $safe_xml = preg_replace('/[\x0-\x1f\x7f-\x9f]/u', ' ', $raw_xml);

  return $safe_xml;
}

// Takes valid XML string as input and returns a SimpleXMLElement object
function createSimpleXMLObject($xml)
{
  libxml_use_internal_errors(true);

  $xml_object = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_PARSEHUGE);
  
  if ($xml_object) {
    echo "SimpleXMLElement object successfully created...\n";
  } else {
    echo "\nFailed loading XML\n";
    foreach(libxml_get_errors() as $error) {
      echo "Error code " . $error->code . " in line " . $error->line . ": " . $error->message;
    }
  }
  
  return $xml_object;
}
