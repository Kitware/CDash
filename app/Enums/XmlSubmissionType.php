<?php

namespace App\Enums;

/**
 * The types of XML file which can be submitted, as detected from each file's elements.
 */
enum XmlSubmissionType: string
{
    case BUILD = 'Build';
    case CONFIGURE = 'Configure';
    case COVERAGE = 'Coverage';
    case COVERAGE_JUNIT = 'CoverageJUnit';
    case COVERAGE_LOG = 'CoverageLog';
    case DONE = 'Done';
    case DYNAMIC_ANALYSIS = 'DynamicAnalysis';
    case NOTES = 'Notes';
    case PROJECT = 'Project';
    case TEST = 'Test';
    case TEST_JUNIT = 'TestJUnit';
    case UPDATE = 'Update';
    case UPLOAD = 'Upload';
}
