#!/usr/bin/php

<?php
/**************************************************************************
 * Testing Reprints API                                                   *
 *************************************************************************/
// Set Variables
define ('WSDL_URL', 'https://www.stg.reprintsdesk.com/webservice/main.asmx?wsdl');
define ('USERNAME', 'ws_stg_FSU2394-1@reprintsdesk.com');
define ('PASSWORD', 'FSUDocs102218**');
define ('NS', 'http://reprintsdesk.com/webservices/');

// Connect to web service
$client = new SOAPClient(WSDL_URL, array('trace' => true));

// Prepare SoapHeader parameters
$header_parameters = array(
  'UserName' => USERNAME,
  'Password' => PASSWORD);
$headers = new SoapHeader(NS, 'UserCredentials', $header_parameters, false);

// Prepare SOAP Client
$client->__setSOAPHeaders(array($headers));

/* Test credentials and print out result
echo "Testing credentials...\n";
$credentials_test = $client->Test_Credentials();
echo "Request headers: \n" . $client->__getLastRequestHeaders();
echo "\nRequest body: \n" . $client->__getLastRequest();
echo "\n Response headers: \n" . $client->__getLastResponseHeaders();
echo "\n Response body: \n" . $client->__getLastResponse();
echo "\n" . $credentials_test->Test_CredentialsResult . "\n"; */

// Create XML request
$xml = new XMLWriter();
$xml->openMemory();
$xml->startElementNS('web','Order_GetPriceEstimate2', NS);

$xml->startElementNS('web','xmlInput', NS);

$xml->startElement('input');
$xml->writeAttribute('schemaversionid', 1);

$xml->startElement('standardnumber');
$xml->startCdata();
$xml->text('10959203');
$xml->endCdata();
$xml->endElement();

$xml->startElement('year');
$xml->startCdata();
$xml->text('1975');
$xml->endCdata();
$xml->endElement();

$xml->startElement('totalpages');
$xml->startCdata();
$xml->text('4');
$xml->endCdata();
$xml->endElement();

$xml->startElement('pricetypeid');
$xml->startCdata();
$xml->text('2');
$xml->endCdata();
$xml->endElement();

$xml->endElement();
$xml->endElement();
$xml->endElement();

// Convert it to a valid SoapVar
$args = new SoapVar($xml->outputMemory(), XSD_ANYXML);

// Call the CheckAvailability service
try {
  echo "Contacting Reprints Desk API. Please wait...\n\n";
  $price_estimate = $client->Order_GetPriceEstimate2($args);
} catch (Exception $e) {
  echo "** There was an error. Here is the debugging information. **\n\n";
  echo "Request headers: \n" . $client->__getLastRequestHeaders();
  echo "\n Request body: \n" . $client->__getLastRequest();
  echo "\n Response headers: \n" . $client->__getLastResponseHeaders();
  echo "\n Response body: \n" . $client->__getLastResponse();
  echo "\n\n ** End of debugging information. ** \n\n";
}

$xml_estimate = new SimpleXMLElement($price_estimate->xmlOutput->any);

echo "You requested the following:\n\n";
echo "ISSN: " . $xml_estimate->standardNumber . "\n";
echo "Pages: " . $xml_estimate->totalpages . "\n";
echo "Year: " . $xml_estimate->year . "\n\n";

echo "The service charge for this article is: " . $xml_estimate->servicecharge . "\n";
echo "The copyright charge for this article is: " . $xml_estimate->copyrightcharge . "\n\n";
echo "Additional Information: \n" . $xml_estimate->disclaimer . "\n\n";
