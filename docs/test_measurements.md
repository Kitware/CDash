CTest can be instructed to include additional test measurements in the
testing results that get uploaded to CDash. The name of each measurement,
its value, and data type are specified in the `Test.xml` file generated
by CTest.

# Test Measurement Types

CDash recognizes following types of test measurements.

## Displayed in Browser

### Text
* `numeric/*`: numeric results produced by this test.
* `text/string`: short text rendered in browser.
* `text/preformatted`: Useful for longer text fields. Newlines, whitespace, and ANSI color codes are preserved.
* `text/link`: associate a hyperlink with this test.

### Images
* `image/jpg`: JPEG image rendered in browser.
* `image/gif`: GIF image rendered in browser.
* `image/png`: PNG image rendered in browser.

CDash will render images together in an interactive comparison format if they are uploaded with one of the following names:
* `TestImage`
* `ValidImage`
* `BaselineImage`

## Files available for download
* `file`: upload a file and make it available for download from the test.

# Test Measurements Configuration

All measurements reported for a test can be viewed on that test's results page.

When logged in as a project administrator, you can go **Settings -> Measurements** to define
measurements that should be displayed as additional columns on the _View Tests_
and/or _Test Summary_ pages.

Adding the `Processors` test measurement here will cause `Proc Time`
to be calculated and displayed on the _View Tests_, _Test Summary_, and _Query Tests_
pages, and in advanced view on _index.php_.

A test's `Proc Time` is calculated as: (**wall clock time * [number of processors](https://cmake.org/cmake/help/latest/prop_test/PROCESSORS.html)**)
