# Validating XML files for CDash

CDash maintains a set of schema files for the expected structure of
the incoming XML files that will be parsed.  Normally, this is not a
problem as CTest writes properly formatted files.

In the rare case that the XML files are not being created by CTest, we have a
few opportunities for the user to be informed that there are problems
with the XML files that are uploaded

1. Prior to Submission

The CDash Docker container has an entry point which will look for files within
a directory which has been mounted to the container.  The directory of files
should be mounted to `/tmp/validate` using the `-v` flag.  The entry point
to call `validate-xml` should be executed via the `docker run` command.

```bash
docker run -v /example/host/directory/xml:/tmp/validate kitware/cdash validate-xml
```
For example, when all files are properly validated, the report would print to
the screen like this:

```bash
Validated file: /tmp/validate/Build.xml.
Validated file: /tmp/validate/Configure.xml.
Validated file: /tmp/validate/Coverage.xml.
Validated file: /tmp/validate/CoverageLog-0.xml.
Validated file: /tmp/validate/Done.xml.
Validated file: /tmp/validate/Test.xml.
```

Errors in a file will be printed to the screen:

```bash
WARNING: Element 'Site': The attribute 'Name' is required but missing.
 in /tmp/validate/Build.xml, line: 25, column: 0
Validated file: /tmp/validate/Configure.xml.
Validated file: /tmp/validate/Coverage.xml.
Validated file: /tmp/validate/CoverageLog-0.xml.
FAILED: Some XML file checks did not pass!
```

2. During Submission

For validation during the submission process, CDash will look at the
`VALIDATE_SUBMISSIONS` environment variable.  The value of the variable
determines how strict CDash will be when accepting XML files that may be
invalid.

* `SILENT`
  * CDash will log a message to its log file about the failures it finds,
    but will continue to process the content of the file.
  * This is the default value.
* `WARN`
  * CDash will log a message to its log file about the failures it finds.
    It will also send the failure results in the `message` attribute of the
    response from the HTTP request but will continue processing the content
    of the file.
* `REJECT`
  * CDash will log a message to its log file about the failures it finds.
    It will also halt processing the file, returning a 400 response code, and
    informing the user the failure results