#!/usr/bin/env php
<?php

ini_set('display_errors',1);  
error_reporting(E_ALL);
date_default_timezone_set('America/Indianapolis');
$version = 0;
$package_dir = "/home/bbrown/submission-packages";

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



    //
    // Extract raw data from nodes
    //////////////////////////////

    $submission_node_id = $submission->nid;
    $submission_machine_title = $submission->title;
    $submission_time_created = $submission->created;
    $submission_time_last_modified = $submission->changed;
    $submission_submitter_first_name = $submission->field_first_name->und->n0->value;
    $submission_submitter_last_name = $submission->field_last_name->und->n0->value;
    $submission_submitter_email = $submission->field_email->und->n0->value;
    $submission_submitter_fsu_dept = $submission->field_fsu_department->und->n0->target_id;
    $submission_type = $submission->field_submission_type->und->n0->value;
    $submission_title = $submission->field_submission_title->und->n0->value;
    $submission_subtitle = get_optional_field_value($submission->field_submission_subtitle);
    $submission_authors = $submission->field_scholarly_author->und->children();
    $submission_abstract = get_optional_field_value($submission->field_abstract);
    $submission_doi = get_optional_field_value($submission->field_item_standard_identifier);
    $submission_keywords = get_optional_field_value($submission->field_keywords);
    $submission_embargo_period = get_optional_field_value($submission->field_embargo_period);
    $submission_visibility = $submission->field_visibility->und->n0->value;
    $submission_publication_date = $submission->field_publication_date_string->und->n0->value;
    $submission_publication_title = get_optional_field_value($submission->field_publication_title);
    $submission_publication_volume = get_optional_field_value($submission->field_publication_volume);
    $submission_publication_issue = get_optional_field_value($submission->field_publication_issue);
    $submission_publication_page_range = get_optional_field_value($submission->field_publication_page_range);
    $submission_publication_note = get_optional_field_value($submission->field_publication_note);
    $submission_preferred_citation = get_optional_field_value($submission->field_preferred_citation);
    $submission_filename = $submission->field_primary_file_upload->und->n0->filename;
    $submission_note_to_submission_staff = get_optional_field_value($submission->field_note_to_submission_staff);


    //
    // Format extracted data
    ////////////////////////

    // Build IID from machine title
    $submission_iid = "FSU_libsubv{$version}_{$submission_machine_title}";

    // Grab submitter dept by taxonomy ID
    $submitter_dept = trim(shell_exec("drush sqlq --extra=\"-sN\" \"select name from omega_taxonomy_term_data where tid={$submission_submitter_fsu_dept}\""));

    // Parse title

    // Build array of authors
    unset($authors);
    $author_array = array();
    foreach ($submission_authors as $a) {
      $author = array();
      $author['first_name'] = trim(shell_exec("drush sqlq --extra=\"-sN\" \"select field_first_name_value from omega_field_data_field_first_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['middle_name'] = trim(shell_exec("drush sqlq --extra=\"-sN\" \"select field_middle_name_value from omega_field_data_field_middle_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['last_name'] = trim(shell_exec("drush sqlq --extra=\"-sN\" \"select field_last_name_value from omega_field_data_field_last_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['institution'] = trim(shell_exec("drush sqlq --extra=\"-sN\" \"select field_institution_value from omega_field_data_field_institution where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author_array[] = $author;
    }

    // Get IP embargo status 
    $ip_embargo = ($submission_visibility == 0 ? "No" : "Yes");
  
    // Create computed cron embargo status
    if ($submission_embargo_period == "U") {
      echo "$submission_machine_title has an unknown embargo date. Please fix.\n";
      $embargo_msg = "Unknown";
    }
    elseif ($submission_embargo_period == "") {
      $embargo_msg = "No embargo"; 
    }
    elseif (strtotime($submission_publication_date)) {
      // Actually create the computed embargo expirty date
      $embargo_expiry_date = date("Y-m-d", strtotime("+{$submission_embargo_period} months", strtotime($submission_publication_date)));
      if (strtotime($embargo_expiry_date) > time()) {
        $embargo_msg = "Embargo until $embargo_expiry_date";
      }
      else {
        // If its already expired, then we don't care
        $embargo_msg = "No embargo";
      }
    }
    else {
      echo "$submission_publication_date is not a valid YYYY-MM-DD date. Please fix.\n";
      $embargo_msg = "Invalid publication date";
    }


    //
    // Create MODS object
    /////////////////////

    $xml = new SimpleXMLElement('<mods xmlns="http://www.loc.gov/mods/v3" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xmlns:xlink="http://www.w3.org/1999/xlink" xmlns:mods="http://www.loc.gov/mods/v3" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:etd="http://www.ndltd.org/standards/metadata/etdms/1.0/" xmlns:flvc="info:flvc/manifest/v1" xsi:schemaLocation="http://www.loc.gov/standards/mods/v3/mods-3-4.xsd" version="3.4"></mods>');

    // Build title
    $xml->addChild('titleInfo');
    $xml->titleInfo->addAttribute('lang', 'eng');
    //$xml->titleInfo->addChild('title', $title); // Parse out for display later with subtitles/nonsorts


    // Build authors

    // Origin Info

    // Language

    // Abstract

    // Notes (keywords, publication note, preferred citation)

    // Add identifiers (IID, DOI)

    // Add FLVC extensions
    $flvc = $xml->addChild('extension')->addChild('flvc:flvc', '', 'info:flvc/manifest/v1');
    $flvc->addChild('flvc:owningInstitution', 'FSU');
    $flvc->addChild('flvc:submittingInstitution', 'FSU');

    // Add static elements
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

    // Format XML and write to file
    $package_path = "{$package_dir}/{$submission_iid}";
    if (file_exists($package_path)) {
      shell_exec("rm -rf {$package_path}");
    }
    shell_exec("mkdir {$package_path}");
    $output = fopen("{$package_path}/{$submission_iid}.xml", "w");
    $dom = new DOMDocument('1.0');
    $dom->preserveWhiteSpace = false;
    $dom->formatOutput = true;
    $dom->loadXML($xml->asXML());
    fwrite($output, $dom->saveXML());
    fclose($output);

    // Handle PDF
    shell_exec("cp /var/www/html/sites/default/files/scholarship/{$submission_filename} {$package_path}/{$submission_iid}.pdf");

    // Create zip
    //shell_exec("zip -r {$package_path}/{$submission_iid}.zip {$package_path}/*");

    echo "$submission_title: $submission_filename\n";
  }
}
?>
