#!/usr/bin/env php
<?php

ini_set('display_errors',1);  
error_reporting(E_ALL);
$version = 0;

// Grab field values only if they exist, and set FALSE otherwise
function get_optional_field_value($field) {
  if (isset($field->und->n0->value)) {
    return $field->und->n0->value;
  }
  else {
    return FALSE;
  }
}

$submissions = simplexml_load_string(shell_exec("drush ne-export --format=xml --type=faculty_scholarship_submission"));
foreach ($submissions as $submission) {
  if ($submission->field_submission_status->und->n0->value == "approved") {

    // Extract raw data from nodes
    $submission_node_id = $submission->nid;
    $submission_machine_title = $submission->title;
    $submission_time_created = $submission->created;
    $submission_time_last_modified = $submission->changed;
    $submission_submitter_first_name = $submission->field_first_name->und->n0->value;
    $submission_submitter_last_name = $submission->field_last_name->und->n0->value;
    $submission_submitter_email = $submission->field_email->und->n0->value;
    $submission_type = $submission->field_submission_type->und->n0->value;
    $submission_title = $submission->field_submission_title->und->n0->value;
    $submission_subtitle = get_optional_field_value($submission->field_submission_subtitle);
    $submission_publication_date = $submission->field_publication_date_string->und->n0->value;
    $submission_publication_title = get_optional_field_value($submission->field_publication_title);
    $submission_publication_volume = get_optional_field_value($submission->field_publication_volume);
    $submission_publication_issue = get_optional_field_value($submission->field_publication_issue);
    $submission_publication_page_range = get_optional_field_value($submission->field_publication_page_range);
    $submission_publication_note = get_optional_field_value($submission->field_publication_note);
    $submission_preferred_citation = get_optional_field_value($submission->field_preferred_citation);
    $submission_note_to_submission_staff = get_optional_field_value($submission->field_note_to_submission_staff);
    $submission_filename = $submission->field_primary_file_upload->und->n0->filename;
    $submission_visibility = $submission->field_visibility->und->n0->value;
    $submission_abstract = get_optional_field_value($submission->field_abstract);
    $submission_keywords = get_optional_field_value($submission->field_keywords);
    $submission_doi = get_optional_field_value($submission->field_item_standard_identifier);
    $submission_embargo_period = get_optional_field_value($submission->field_embargo_period);

    // Match up returned ints with external data types
    $submission_submitter_fsu_dept = $submission->field_fsu_department->und->n0->value; // Check this against the taxonomy later
    foreach ($submission->field_scholarly_author->und->children() as $author) {
      //echo "$author->value\n"; // lookup author data from field collections
    }
  
    // Transform extracted data into the proper format for MODS record
    $submission_iid = "FSU_libsubv{$version}_{$submission_machine_title}";
    $ip_embargo = ($submission_visibility == 0 ? "Viewable by general public" : "Viewable only on FSU campus");
    $date_embargo = ($submission_embargo_period == "" ? "None" : "$submission_embargo_period months");
    $keywords = explode(", ", $submission_keywords);


    // Create MODS object
    $xml = new SimpleXMLElement('<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/" xmlns:flvc="info:flvc/manifest/v1" xsi:schemaLocation="http://www.loc.gov/standards/mods/v3/mods-3-4.xsd" version="3.4"></mods>');
    $xml->addChild('typeOfResource', 'text');
    $xml->addChild('genre', 'text');
    $xml->genre->addAttribute('authority', 'rdacontent');
    $xml->addChild('physicalDescription');
    $rda_media = $xml->physicalDescription->addChild('form', 'computer');
    $rda_media->addAttribute('authority', 'rdamedia'); 
    $rda_media->addAttribute('type', 'RDA media terms');
    $rda_carrier = $xml->physicalDescription->addChild('form', 'online resource');
    $rda_carrier->addAttribute('authority', 'rdacarrier'); 
    $rda_carrier->addAttribute('type', 'RDA carrier terms');
    $xml->physicalDescription->addChild('extent', '1 online resource');
    $xml->physicalDescription->addChild('digitalOrigin', 'born digital');
    $xml->physicalDescription->addChild('internetMediaType', 'application/pdf');
    $xml->addChild('recordInfo');
    $xml->recordInfo->addChild('recordCreationDate', date('Y-m-d'))->addAttribute('encoding', 'w3cdtf');
    $xml->recordInfo->addChild('descriptionStandard', 'rda');
    $flvc = $xml->addChild('extension')->addChild('flvc:flvc', '', 'info:flvc/manifest/v1');
    $flvc->addChild('flvc:owningInstitution', 'FSU');
    $flvc->addChild('flvc:submittingInstitution', 'FSU');

    // Build title
    $xml->addChild('titleInfo');
    $xml->titleInfo->addAttribute('lang', 'eng');
    //$xml->titleInfo->addChild('title', $title); // Parse out for display later with subtitles/nonsorts

    // Build authors (WHERE ARE THE AUTHORS?!?!)

    // Format XML and write to file
    $output = fopen("{$submission_iid}.xml", "w");
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    echo $dom->saveXML();
    fwrite($output, $dom->saveXML());
    fclose($output);

  }
}
?>
