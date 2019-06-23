var LoginPage = require("../pages/login.page.js");
describe("manageMeasurements", function() {
  it("can manage measurements", function() {
    var loginPage = new LoginPage();
    loginPage.login();

    // Add a measurement and save it.
    browser.get("manageMeasurements.php?projectid=1");
    element(by.name("newMeasurement")).sendKeys("Processors");
    element(by.name("showSummaryPage")).click();
    element(by.name("submit")).click();

    // Reload and verify.
    browser.get("manageMeasurements.php?projectid=1");
    expect(element.all(by.repeater("measurement in cdash.measurements")).count()).toBe(1);
    var measurement = element(by.repeater("measurement in cdash.measurements").row(0));
    expect(measurement.element(by.name("measurement_name")).getAttribute("value")).toBe("Processors");
    expect(measurement.element(by.name("summarypage")).getAttribute("checked")).toBeFalsy();

    // Modify the measurement's settings.
    measurement.element(by.name("measurement_name")).clear();
    measurement.element(by.name("measurement_name")).sendKeys("CPUs");
    measurement.element(by.name("summarypage")).click();
    measurement.element(by.name("testpage")).click();
    element(by.name("submit")).click();
    browser.get("manageMeasurements.php?projectid=1");
    var measurement = element(by.repeater("measurement in cdash.measurements").row(0));
    expect(measurement.element(by.name("measurement_name")).getAttribute("value")).toBe("CPUs");
    expect(measurement.element(by.name("summarypage")).getAttribute("checked")).toBeTruthy();
    expect(measurement.element(by.name("testpage")).getAttribute("checked")).toBeFalsy();

    // Delete the measurement.
    measurement.element(by.className("glyphicon-trash")).click();
    browser.wait(function() {
      return element(by.id("modal-delete-measurement-button")).isPresent();
    });
    element(by.id("modal-delete-measurement-button")).click();
    browser.waitForAngular();
    browser.get("manageMeasurements.php?projectid=1");
    expect(element.all(by.repeater("measurement in cdash.measurements")).count()).toBe(0);
  });
});
