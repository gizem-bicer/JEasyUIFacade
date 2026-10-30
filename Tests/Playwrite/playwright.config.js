const {defineConfig} = require(process.env.PLAYWRIGHT_MODULE || '@playwright/test');

module.exports = defineConfig({
    testDir: __dirname,
    testMatch: 'markian.spec.js',
    outputDir: '.test-results',
    reporter: 'list',
    use: {baseURL: process.env.JEASYUI_TEST_URL || 'http://127.0.0.1:5197', viewport: {width: 1100, height: 850}},
});