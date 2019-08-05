exports.config = {
    seleniumAddress: 'http://selenium-hub:4444/wd/hub',
    baseUrl: 'http://cdash/',
    capabilities: {
        browserName: 'chrome',
        chromeOptions: {
            args: [ "--headless", "--disable-gpu", "--no-sandbox", "--window-size=1680,2400" ]
        }
    }
}
