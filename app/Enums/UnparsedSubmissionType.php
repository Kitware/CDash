<?php

namespace App\Enums;

/**
 * The types of data file which can be submitted via the two-phase "unparsed" submission process.
 * Each value corresponds to a handler class named App\Http\Submission\Handlers\<value>Handler.
 */
enum UnparsedSubmissionType: string
{
    case BAZEL_JSON = 'BazelJSON';
    case BUILD_PROPERTIES_JSON = 'BuildPropertiesJSON';
    case GCOV_TAR = 'GcovTar';
    case JAVA_JSON_TAR = 'JavaJSONTar';
    case JSCOVER_TAR = 'JSCoverTar';
    case OPENCOVER_TAR = 'OpenCoverTar';
    case SUBPROJECT_DIRECTORIES = 'SubProjectDirectories';
}
