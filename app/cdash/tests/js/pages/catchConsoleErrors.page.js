beforeEach(async function() {
  const browserLogs = await browser.manage().logs().get('browser');
  browserLogs.forEach((log) => {
    if (log.level.value > 900) { // it's an error log
      console.log(`Browser console error: ${log.message}`);
      fail(log.message);
    }
  });
});
