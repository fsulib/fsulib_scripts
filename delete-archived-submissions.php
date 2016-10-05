#!/usr/bin/env php
<?php

// Grab field values only if they exist, and set FALSE otherwise
function get_optional_field_value($field) {
  if (isset($field->und->n0->value)) {
    return $field->und->n0->value;
  }
  else {
    return FALSE;
  }
}

$submissions = simplexml_load_string(shell_exec("drush --root=/var/www/html ne-export --format=xml --type=faculty_scholarship_submission"));

foreach ($submissions as $submission) {
  if ($submission->field_submission_status->und->n0->value == "archived") {

    //
    // Extract raw data from nodes
    //////////////////////////////
    $submission_title = $submission->field_submission_title->und->n0->value;
    $submission_subtitle = get_optional_field_value($submission->field_submission_subtitle);
    $submission_node_id = $submission->nid;
    $submission_machine_title = $submission->title;
    $submission_time_created = $submission->created;
    $submission_time_last_modified = $submission->changed;
    $submission_submitter_first_name = $submission->field_first_name->und->n0->value;
    $submission_submitter_last_name = $submission->field_last_name->und->n0->value;
    $submission_submitter_email = $submission->field_email->und->n0->value;
    $submission_submitter_fsu_dept = $submission->field_fsu_department->und->n0->target_id;
    $submission_type = $submission->field_submission_type->und->n0->value;
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
    $submission_grant_number = get_optional_field_value($submission->field_grant_number);
    $exploded_path = explode("/", $submission->field_primary_file_upload->und->n0->uri);
    $submission_filename = end($exploded_path);
    $submission_note_to_submission_staff = get_optional_field_value($submission->field_note_to_submission_staff);


    // Grab submitter dept by taxonomy ID
    $submitter_dept = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select name from omega_taxonomy_term_data where tid={$submission_submitter_fsu_dept}\""));

    // Build array of authors
    unset($author_array);
    $author_array = array();
    foreach ($submission_authors as $a) {
      $author = array();
      $author['first_name'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_first_name_value from omega_field_data_field_first_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['middle_name'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_middle_name_value from omega_field_data_field_middle_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['last_name'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_last_name_value from omega_field_data_field_last_name where bundle='field_scholarly_author' and entity_id={$a->value}\""));
      $author['institution'] = trim(shell_exec("drush --root=/var/www/html sqlq --extra=\"-sN\" \"select field_institution_value from omega_field_data_field_institution where bundle='field_scholarly_author' and entity_id={$a->value}\""));
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
    $cpdate = date('Y', strtotime($submission_publication_date));

    echo "Deleting {$submission_title} ({$submission_node_id})\n";
    shell_exec("drush php-eval 'node_delete({$submission_node_id});'");

  }  
}
?>
