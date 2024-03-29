CTest can be instructed to include additional test measurements in the
testing results that get uploaded to CDash. The name of each measurement,
its value, and data type are specified in the `Test.xml` file generated
by CTest.

# Test Measurement Types

CDash recognizes following types of test measurements.

## Displayed in Browser

### Text
* `numeric/double`: numeric results produced by this test.
  * All measurements whose type begins with `numeric/` are treated identically by CDash. For example, you can provide your measurement as `numeric/integer` or `numeric/float` and CDash's behavior will not change.
  * CDash displays plots for numeric measurements on the test details page. This allows you to see how measurements change from one run of a test to the next.
* `text/string`: short text rendered in browser.
* `text/preformatted`: Useful for longer text fields. Newlines, whitespace, and ANSI color codes are preserved.
* `text/link`: associate a hyperlink with this test.

### Images
* `image/jpg`: JPEG image rendered in browser.
* `image/gif`: GIF image rendered in browser.
* `image/png`: PNG image rendered in browser.

CDash will render images together in an interactive comparison format if they are uploaded with two or more of the following names:
* `TestImage`
* `ValidImage`
* `BaselineImage`
* `DifferenceImage2`

The typical use case is to upload two images from this set (eg. `TestImage` and `ValidImage`).
By convention, `TestImage` is the image generated by your test, while `ValidImage`
represents the expected result. For historical reasons, `BaselineImage` is also
accepted as an alternative to `ValidImage`.

![2x2 image comparison](/docs/images/image_comparison.png)

All four of the above image names can be provided to render a full 2x2 interactive
image comparison grid.

![2x2 image comparison](/docs/images/image_comparison_2x2.png)

Some commonly used techniques for generating difference images are
[vtkImageDifference](https://vtk.org/doc/nightly/html/classvtkImageDifference.html)
and [diffimg](https://github.com/nicolashahn/diffimg).

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
